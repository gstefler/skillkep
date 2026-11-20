import { computed, onBeforeUnmount, ref, shallowRef } from 'vue';
import { jsonHeaders } from '@/lib/http';
import {
    chunk as chunkRoute,
    complete as completeRoute,
    destroy as destroyRoute,
    show as showRoute,
    store as storeRoute,
} from '@/routes/manage/courses/uploads';

// Resumable, chunked ZIP upload of a course (see CourseUploadController).
//
// The file is cut into fixed-size chunks, sent in parallel with raw PUTs.
// "Pause" aborts the requests in flight; "Resume" asks the server which
// chunks it already has and sends only the rest. Picking the same file again
// after a reload resumes too, because the server matches it by name, size
// and modification time. After the last chunk the server queues the import,
// and this composable polls it until it finishes.

export type UploadPhase =
    | 'idle'
    | 'starting'
    | 'uploading'
    | 'paused'
    | 'finalizing'
    | 'processing'
    | 'completed'
    | 'failed';

export type UploadStatus = 'uploading' | 'processing' | 'completed' | 'failed';

export type UploadPayload = {
    id: string;
    filename: string;
    size: number;
    chunk_size: number;
    total_chunks: number;
    received: number[];
    status: UploadStatus;
    status_label: string;
    stage: 'queued' | 'extracting' | 'importing' | null;
    stage_progress: number;
    result: { modules: number; materials: number; questions: number } | null;
    error: string | null;
    course: { id: number; title: string } | null;
};

export type UploadSettings = { passScore: number; lockModules: boolean };

const PARALLEL = 3;
const MAX_RETRIES = 5;
const POLL_MS = 2000;

class FatalError extends Error {}

class HttpError extends Error {
    constructor(
        message: string,
        public status: number,
        public retriable: boolean,
    ) {
        super(message);
    }
}

const SESSION_EXPIRED =
    'A munkamenet lejárt. Töltse újra az oldalt, és válassza ki újra ugyanazt a fájlt a folytatáshoz.';

function errorMessage(data: unknown, status: number): string {
    const body = data as {
        message?: string;
        errors?: Record<string, string[]>;
    } | null;

    return (
        Object.values(body?.errors ?? {}).flat()[0] ??
        body?.message ??
        `Hiba történt (HTTP ${status}).`
    );
}

async function requestJson<T>(
    method: string,
    url: string,
    body?: unknown,
): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new HttpError('Hálózati hiba.', 0, true);
    }

    const data =
        response.status === 204
            ? null
            : await response.json().catch(() => null);

    if (!response.ok) {
        if (response.status === 401 || response.status === 419) {
            throw new FatalError(SESSION_EXPIRED);
        }

        throw new HttpError(
            errorMessage(data, response.status),
            response.status,
            response.status >= 500 || response.status === 429,
        );
    }

    return data as T;
}

function sleep(ms: number, signal?: AbortSignal): Promise<void> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(resolve, ms);
        signal?.addEventListener(
            'abort',
            () => {
                clearTimeout(timer);
                reject(new DOMException('Aborted', 'AbortError'));
            },
            { once: true },
        );
    });
}

function isAbort(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
}

export function useChunkedUpload() {
    const phase = ref<UploadPhase>('idle');
    const file = shallowRef<File | null>(null);
    const upload = ref<UploadPayload | null>(null);
    const error = ref<string | null>(null);
    const notice = ref<string | null>(null);

    const received = ref(new Set<number>());
    const inflight = ref(new Map<number, number>());
    const speed = ref(0);
    let samples: [number, number][] = [];
    let controller: AbortController | null = null;

    const chunkLength = (index: number): number => {
        if (!upload.value || !file.value) {
            return 0;
        }

        const start = index * upload.value.chunk_size;

        return Math.min(upload.value.chunk_size, file.value.size - start);
    };

    const bytesDone = computed(() => {
        if (!file.value || !upload.value) {
            return 0;
        }

        let total = 0;
        received.value.forEach((index) => (total += chunkLength(index)));
        inflight.value.forEach((loaded) => (total += loaded));

        return Math.min(total, file.value.size);
    });

    const bytesTotal = computed(
        () => file.value?.size ?? upload.value?.size ?? 0,
    );

    const uploadPercent = computed(() =>
        bytesTotal.value > 0 ? (bytesDone.value / bytesTotal.value) * 100 : 0,
    );

    const secondsLeft = computed(() =>
        speed.value > 0
            ? (bytesTotal.value - bytesDone.value) / speed.value
            : null,
    );

    const receivedCount = computed(() => received.value.size);
    const totalChunks = computed(() => upload.value?.total_chunks ?? 0);

    const isBusy = computed(() =>
        ['starting', 'uploading', 'finalizing', 'processing'].includes(
            phase.value,
        ),
    );

    function sample(): void {
        const now = performance.now();
        samples.push([now, bytesDone.value]);

        while (samples.length > 1 && now - samples[0][0] > 6000) {
            samples.shift();
        }

        const [t0, b0] = samples[0];
        speed.value =
            now > t0 ? (bytesDone.value - b0) / ((now - t0) / 1000) : 0;
    }

    function setInflight(index: number, loaded: number | null): void {
        const next = new Map(inflight.value);

        if (loaded === null) {
            next.delete(index);
        } else {
            next.set(index, loaded);
        }

        inflight.value = next;
    }

    function markReceived(index: number): void {
        const next = new Set(received.value);
        next.add(index);
        received.value = next;
    }

    function sendChunk(index: number, signal: AbortSignal): Promise<void> {
        const current = upload.value!;
        const start = index * current.chunk_size;
        const blob = file.value!.slice(start, start + chunkLength(index));

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', chunkRoute.url({ upload: current.id, index }));

            Object.entries(jsonHeaders()).forEach(([name, value]) =>
                xhr.setRequestHeader(name, value),
            );
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');

            xhr.upload.onprogress = (event) => {
                setInflight(index, event.loaded);
                sample();
            };

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    return resolve();
                }

                if (xhr.status === 401 || xhr.status === 419) {
                    return reject(new FatalError(SESSION_EXPIRED));
                }

                let data: unknown = null;

                try {
                    data = JSON.parse(xhr.responseText);
                } catch {
                    // Not JSON; keep the generic message.
                }

                reject(
                    new HttpError(
                        errorMessage(data, xhr.status),
                        xhr.status,
                        xhr.status >= 500 ||
                            [408, 422, 429].includes(xhr.status),
                    ),
                );
            };

            xhr.onerror = () =>
                reject(new HttpError('Hálózati hiba.', 0, true));

            signal.addEventListener(
                'abort',
                () => {
                    xhr.abort();
                    reject(new DOMException('Aborted', 'AbortError'));
                },
                { once: true },
            );

            xhr.send(blob);
        });
    }

    async function sendChunkWithRetries(
        index: number,
        signal: AbortSignal,
    ): Promise<void> {
        for (let attempt = 0; ; attempt++) {
            try {
                await sendChunk(index, signal);

                return;
            } catch (e) {
                setInflight(index, null);

                if (isAbort(e) || e instanceof FatalError) {
                    throw e;
                }

                if (
                    !(e instanceof HttpError) ||
                    !e.retriable ||
                    attempt >= MAX_RETRIES
                ) {
                    throw e;
                }

                await sleep(Math.min(1000 * 2 ** attempt, 15000), signal);
            }
        }
    }

    async function uploadMissingChunks(signal: AbortSignal): Promise<void> {
        const pending: number[] = [];

        for (let i = 0; i < totalChunks.value; i++) {
            if (!received.value.has(i)) {
                pending.push(i);
            }
        }

        let failure: unknown = null;

        const worker = async () => {
            while (pending.length > 0 && failure === null) {
                const index = pending.shift()!;

                try {
                    await sendChunkWithRetries(index, signal);
                    setInflight(index, null);
                    markReceived(index);
                    sample();
                } catch (e) {
                    failure ??= e;

                    return;
                }
            }
        };

        await Promise.all(Array.from({ length: PARALLEL }, worker));

        if (failure !== null) {
            throw failure;
        }
    }

    /** Apply the server's view of a processing upload. Returns true when it has finished. */
    function applyServerState(data: UploadPayload): boolean {
        upload.value = data;

        if (data.status === 'completed') {
            phase.value = 'completed';

            return true;
        }

        if (data.status === 'failed') {
            phase.value = 'failed';
            error.value = data.error ?? 'Az importálás sikertelen.';

            return true;
        }

        phase.value = data.status === 'uploading' ? phase.value : 'processing';

        return false;
    }

    async function pollUntilFinished(signal: AbortSignal): Promise<void> {
        for (;;) {
            const data = await requestJson<UploadPayload>(
                'GET',
                showRoute.url(upload.value!.id),
            );

            if (applyServerState(data)) {
                return;
            }

            await sleep(POLL_MS, signal);
        }
    }

    function choose(selected: File | null): void {
        if (isBusy.value) {
            return;
        }

        error.value = null;
        notice.value = null;

        if (selected && !/\.zip$/i.test(selected.name)) {
            file.value = null;
            error.value = 'A fájlnak ZIP-archívumnak kell lennie.';

            return;
        }

        file.value = selected;
        upload.value = null;
        received.value = new Set();
        inflight.value = new Map();
        phase.value = 'idle';
    }

    /** Start the upload, or resume it after a pause, an error or a reload. */
    async function start(settings: UploadSettings): Promise<void> {
        if (isBusy.value) {
            return;
        }

        if (!file.value) {
            error.value = 'Először válasszon ki egy ZIP-fájlt.';

            return;
        }

        error.value = null;
        notice.value = null;
        controller = new AbortController();
        const { signal } = controller;
        phase.value = 'starting';

        try {
            const data = await requestJson<UploadPayload>(
                'POST',
                storeRoute.url(),
                {
                    filename: file.value.name,
                    size: file.value.size,
                    last_modified: file.value.lastModified,
                    pass_score: settings.passScore,
                    lock_modules: settings.lockModules,
                },
            );

            upload.value = data;

            if (data.status !== 'uploading') {
                phase.value = 'processing';
                await pollUntilFinished(signal);

                return;
            }

            received.value = new Set(data.received);
            inflight.value = new Map();
            samples = [];
            speed.value = 0;

            if (data.received.length > 0) {
                notice.value = `Folytatás: ${data.total_chunks} részből ${data.received.length} már a szerveren van.`;
            }

            phase.value = 'uploading';
            await uploadMissingChunks(signal);

            phase.value = 'finalizing';
            const queued = await requestJson<UploadPayload>(
                'POST',
                completeRoute.url(data.id),
            );

            if (!applyServerState(queued)) {
                await pollUntilFinished(signal);
            }
        } catch (e) {
            if (isAbort(e)) {
                return;
            }

            inflight.value = new Map();
            error.value = e instanceof Error ? e.message : 'Ismeretlen hiba.';

            // Chunks already on the server stay there: the user can resume.
            phase.value =
                upload.value?.status === 'uploading' ? 'paused' : 'failed';
        } finally {
            controller = null;
        }
    }

    function pause(): void {
        if (phase.value !== 'uploading') {
            return;
        }

        controller?.abort();
        controller = null;
        inflight.value = new Map();
        speed.value = 0;
        phase.value = 'paused';
    }

    /** Throw away the unfinished upload and every chunk sent so far. */
    async function discard(): Promise<void> {
        controller?.abort();
        controller = null;

        const current = upload.value;

        if (current && current.status === 'uploading') {
            try {
                await requestJson('DELETE', destroyRoute.url(current.id));
            } catch {
                // Already gone.
            }
        }

        reset();
    }

    function reset(): void {
        controller?.abort();
        controller = null;
        file.value = null;
        upload.value = null;
        received.value = new Set();
        inflight.value = new Map();
        speed.value = 0;
        error.value = null;
        notice.value = null;
        phase.value = 'idle';
    }

    // Leaving the page mid-upload only pauses it, but warn anyway.
    const beforeUnload = (event: BeforeUnloadEvent) => {
        if (phase.value === 'uploading' || phase.value === 'finalizing') {
            event.preventDefault();
        }
    };

    window.addEventListener('beforeunload', beforeUnload);

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnload);
        controller?.abort();
    });

    return {
        phase,
        file,
        upload,
        error,
        notice,
        bytesDone,
        bytesTotal,
        uploadPercent,
        speed,
        secondsLeft,
        receivedCount,
        totalChunks,
        isBusy,
        choose,
        start,
        pause,
        discard,
        reset,
    };
}

export async function discardUpload(id: string): Promise<void> {
    await requestJson('DELETE', destroyRoute.url(id));
}

export async function fetchUpload(id: string): Promise<UploadPayload> {
    return requestJson<UploadPayload>('GET', showRoute.url(id));
}

export function formatBytes(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toLocaleString('hu-HU', { maximumFractionDigits: unit === 0 ? 0 : 1 })} ${units[unit]}`;
}

export function formatDuration(seconds: number): string {
    const total = Math.max(0, Math.round(seconds));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const pad = (n: number) => String(n).padStart(2, '0');

    return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`;
}
