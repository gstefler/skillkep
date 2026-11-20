<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    FileArchive,
    Pause,
    Play,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    discardUpload,
    formatBytes,
    formatDuration,
    useChunkedUpload,
} from '@/composables/useChunkedUpload';
import type { UploadPayload } from '@/composables/useChunkedUpload';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/manage/courses';
import { create } from '@/routes/manage/courses/uploads';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Képzések', href: index() },
            { title: 'Kurzus feltöltése', href: create() },
        ],
    },
});

const props = defineProps<{
    recentUploads: (UploadPayload & { updated_at: string })[];
    chunkSize: number;
    maxSize: number;
}>();

const {
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
} = useChunkedUpload();

const passScore = ref(60);
const lockModules = ref(false);
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);
// 'current' = the upload on this page; otherwise the id of a recent upload.
const pendingDiscard = ref<string | null>(null);
const confirmDiscard = computed({
    get: () => pendingDiscard.value !== null,
    set: (open: boolean) => {
        if (!open) {
            pendingDiscard.value = null;
        }
    },
});

const canEditSettings = computed(() => phase.value === 'idle');
const canChooseFile = computed(() =>
    ['idle', 'completed', 'failed'].includes(phase.value),
);

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    pick(target.files?.[0] ?? null);
    target.value = '';
}

function onDrop(event: DragEvent): void {
    dragging.value = false;

    if (canChooseFile.value) {
        pick(event.dataTransfer?.files[0] ?? null);
    }
}

function pick(selected: File | null): void {
    if (phase.value === 'completed' || phase.value === 'failed') {
        reset();
    }

    choose(selected);

    if (selected && selected.size > props.maxSize) {
        choose(null);
        error.value = `A fájl legfeljebb ${formatBytes(props.maxSize)} lehet.`;
    }
}

function begin(): void {
    start({ passScore: passScore.value, lockModules: lockModules.value });
}

async function confirmAndDiscard(): Promise<void> {
    const target = pendingDiscard.value;
    pendingDiscard.value = null;

    try {
        if (target === 'current') {
            await discard();
        } else if (target) {
            await discardUpload(target);
        }
    } finally {
        router.reload({ only: ['recentUploads'] });
    }
}

const stageLabels: Record<string, string> = {
    queued: 'Várakozás a háttérfeldolgozóra…',
    extracting: 'Archívum kicsomagolása…',
    importing: 'Modulok importálása…',
};

const progressTitle = computed(() => {
    switch (phase.value) {
        case 'starting':
            return 'Feltöltés előkészítése…';
        case 'uploading':
            return 'Feltöltés folyamatban';
        case 'paused':
            return error.value
                ? 'A feltöltés megszakadt'
                : 'A feltöltés szüneteltetve';
        case 'finalizing':
            return 'Feltöltés lezárása…';
        case 'processing':
            return stageLabels[upload.value?.stage ?? 'queued'];
        case 'completed':
            return 'Az importálás kész';
        case 'failed':
            return 'Az importálás sikertelen';
        default:
            return '';
    }
});

const progressValue = computed(() => {
    if (phase.value === 'processing') {
        return upload.value?.stage === 'queued'
            ? 0
            : (upload.value?.stage_progress ?? 0);
    }

    return phase.value === 'completed' ? 100 : uploadPercent.value;
});

const percentText = computed(
    () =>
        `${progressValue.value.toLocaleString('hu-HU', { maximumFractionDigits: phase.value === 'processing' ? 0 : 1 })} %`,
);

const progressDetail = computed(() => {
    if (!['uploading', 'paused', 'starting'].includes(phase.value)) {
        return '';
    }

    const parts = [
        `${formatBytes(bytesDone.value)} / ${formatBytes(bytesTotal.value)}`,
    ];

    if (totalChunks.value > 0) {
        parts.push(`${receivedCount.value} / ${totalChunks.value} rész`);
    }

    if (phase.value === 'uploading' && speed.value > 0) {
        parts.push(`${formatBytes(speed.value)}/s`);
    }

    if (phase.value === 'uploading' && secondsLeft.value !== null) {
        parts.push(`még kb. ${formatDuration(secondsLeft.value)}`);
    }

    return parts.join(' · ');
});

const showProgress = computed(() => phase.value !== 'idle');

// While a recent upload is still being processed (e.g. after a reload), poll the list.
let pollTimer: ReturnType<typeof setInterval> | null = null;

watch(
    () => props.recentUploads.some((u) => u.status === 'processing'),
    (processing) => {
        if (processing && !pollTimer) {
            pollTimer = setInterval(
                () => router.reload({ only: ['recentUploads'] }),
                3000,
            );
        } else if (!processing && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    },
    { immediate: true },
);

watch(phase, (value) => {
    if (value === 'completed' || value === 'failed') {
        router.reload({ only: ['recentUploads'] });
    }
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});

const statusVariant = {
    uploading: 'warning',
    processing: 'info',
    completed: 'success',
    failed: 'destructive',
} as const;
</script>

<template>
    <Head title="Kurzus feltöltése" />

    <PageHeader
        title="Kurzus feltöltése"
        description="Töltse fel a kurzust ZIP-fájlként. A feltöltés bármikor szüneteltethető és folytatható, akár az oldal újratöltése után is."
    />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section
            class="grid gap-5 rounded-lg border border-border bg-card p-(--kep-card-pad)"
            aria-labelledby="upload-title"
        >
            <h2 id="upload-title" class="sr-only">Fájl és beállítások</h2>

            <label
                for="archive"
                :class="[
                    'flex min-h-40 flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-8 text-center transition-colors',
                    canChooseFile
                        ? 'cursor-pointer hover:border-primary hover:bg-primary-subtle'
                        : 'cursor-not-allowed opacity-60',
                    dragging
                        ? 'border-primary bg-primary-subtle'
                        : 'border-border-strong',
                ]"
                @dragenter.prevent="dragging = canChooseFile"
                @dragover.prevent="dragging = canChooseFile"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <FileArchive class="size-8 text-subtle" aria-hidden="true" />
                <span v-if="file" class="text-sm font-medium">
                    {{ file.name }}
                    <span class="font-normal text-muted-foreground tabular-nums"
                        >({{ formatBytes(file.size) }})</span
                    >
                </span>
                <span v-else class="text-sm font-medium"
                    >Húzza ide a ZIP-fájlt, vagy kattintson a tallózáshoz</span
                >
                <span class="text-xs text-muted-foreground"
                    >Legfeljebb {{ formatBytes(maxSize) }}. Egy félbehagyott
                    feltöltés folytatásához válassza ki újra ugyanazt a
                    fájlt.</span
                >
                <input
                    id="archive"
                    ref="input"
                    type="file"
                    accept=".zip,application/zip"
                    class="sr-only"
                    :disabled="!canChooseFile"
                    @change="onFileChange"
                />
            </label>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="pass_score">Sikeres teszt határa (%)</Label>
                    <Input
                        id="pass_score"
                        v-model.number="passScore"
                        type="number"
                        min="0"
                        max="100"
                        class="max-w-32 tabular-nums"
                        :disabled="!canEditSettings"
                    />
                </div>
                <div class="grid content-end gap-2">
                    <label class="kep-switch">
                        <input
                            v-model="lockModules"
                            type="checkbox"
                            :disabled="!canEditSettings"
                        />
                        Sorrendben teljesítendő modulok
                    </label>
                </div>
            </div>

            <Alert v-if="error && !showProgress" variant="destructive">
                <CircleAlert />
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <div
                v-if="showProgress"
                class="grid gap-3 rounded-md border border-border bg-bg-subtle p-4"
            >
                <div
                    class="flex items-baseline justify-between gap-3"
                    aria-live="polite"
                >
                    <p class="text-sm font-semibold">{{ progressTitle }}</p>
                    <p class="text-sm tabular-nums">{{ percentText }}</p>
                </div>
                <ProgressBar
                    :value="progressValue"
                    size="lg"
                    label="Feltöltés állapota"
                    :tone="
                        phase === 'completed'
                            ? 'success'
                            : phase === 'failed'
                              ? 'danger'
                              : phase === 'paused'
                                ? 'warning'
                                : 'primary'
                    "
                />
                <p
                    v-if="progressDetail"
                    class="text-xs text-muted-foreground tabular-nums"
                >
                    {{ progressDetail }}
                </p>
                <p v-if="notice" class="text-xs text-muted-foreground">
                    {{ notice }}
                </p>

                <Alert
                    v-if="error"
                    :variant="phase === 'paused' ? 'warning' : 'destructive'"
                >
                    <CircleAlert />
                    <AlertDescription>
                        {{ error }}
                        <template v-if="phase === 'paused'">
                            A már feltöltött részek megmaradnak; kattintson a
                            Feltöltés folytatása gombra.</template
                        >
                    </AlertDescription>
                </Alert>

                <Alert v-if="phase === 'completed'" variant="success">
                    <CircleCheck />
                    <AlertTitle>
                        {{ upload?.course?.title ?? 'A kurzus' }} importálva
                    </AlertTitle>
                    <AlertDescription>
                        {{ upload?.result?.modules ?? 0 }} modul,
                        {{ upload?.result?.materials ?? 0 }} tananyag,
                        {{ upload?.result?.questions ?? 0 }} tesztkérdés. A
                        kurzus még nincs közzétéve; a videók feldolgozása a
                        háttérben folytatódik.
                    </AlertDescription>
                </Alert>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    v-if="phase === 'idle'"
                    :disabled="!file"
                    @click="begin"
                >
                    <Upload /> Feltöltés indítása
                </Button>
                <Button v-if="phase === 'uploading'" @click="pause">
                    <Pause /> Feltöltés szüneteltetése
                </Button>
                <Button v-if="phase === 'paused'" @click="begin">
                    <Play /> Feltöltés folytatása
                </Button>
                <Button
                    v-if="phase === 'uploading' || phase === 'paused'"
                    variant="outline"
                    @click="pendingDiscard = 'current'"
                >
                    <Trash2 /> Feltöltés elvetése
                </Button>
                <Button v-if="phase === 'completed' && upload?.course" as-child>
                    <Link :href="show(upload.course.id)"
                        >Kurzus megnyitása</Link
                    >
                </Button>
                <Button
                    v-if="phase === 'completed' || phase === 'failed'"
                    variant="outline"
                    @click="reset"
                >
                    Másik kurzus feltöltése
                </Button>
                <Button
                    v-if="!isBusy && phase !== 'paused'"
                    variant="ghost"
                    as-child
                >
                    <Link :href="index()">Vissza a képzésekhez</Link>
                </Button>
            </div>
        </section>

        <aside
            class="grid content-start gap-3 rounded-lg border border-border bg-card p-(--kep-card-pad) text-sm"
            aria-labelledby="zip-structure"
        >
            <h2 id="zip-structure" class="text-base font-semibold">
                A ZIP-fájl szerkezete
            </h2>
            <ul class="grid list-disc gap-2 pl-5 text-muted-foreground">
                <li>
                    Minden számmal kezdődő mappa egy modul (<span
                        class="font-mono text-xs"
                        >01_bevezetes</span
                    >), a szám szerinti sorrendben.
                </li>
                <li>
                    A modulmappában lévő PDF- és MP4-fájlok a modul tananyagai.
                </li>
                <li>
                    A <span class="font-mono text-xs">teszt.json</span> (vagy a
                    mappa egyetlen JSON-fájlja) a modul tesztje.
                </li>
                <li>
                    Egy szám nélküli mappa (például
                    <span class="font-mono text-xs">Záróvizsga</span>) a
                    záróvizsga, amely mindig utolsó.
                </li>
            </ul>
        </aside>
    </div>

    <section
        v-if="recentUploads.length > 0"
        class="grid gap-3"
        aria-labelledby="recent-uploads"
    >
        <h2 id="recent-uploads" class="text-lg font-semibold">
            Legutóbbi feltöltések
        </h2>
        <DataTable>
            <thead>
                <tr>
                    <th>Fájl</th>
                    <th>Állapot</th>
                    <th>Utoljára módosítva</th>
                    <th><span class="sr-only">Műveletek</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in recentUploads" :key="item.id">
                    <td>
                        <div class="font-medium">{{ item.filename }}</div>
                        <div class="text-xs text-muted-foreground tabular-nums">
                            {{ formatBytes(item.size) }}
                            <template v-if="item.status === 'uploading'">
                                · {{ item.received.length }} /
                                {{ item.total_chunks }} rész a szerveren
                            </template>
                        </div>
                        <div v-if="item.error" class="text-xs text-destructive">
                            {{ item.error }}
                        </div>
                    </td>
                    <td>
                        <Badge :variant="statusVariant[item.status]">{{
                            item.status_label
                        }}</Badge>
                    </td>
                    <td class="tabular-nums">{{ item.updated_at }}</td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <Button
                                v-if="item.course"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link :href="show(item.course.id)"
                                    >Kurzus megnyitása</Link
                                >
                            </Button>
                            <Button
                                v-if="
                                    item.status === 'uploading' &&
                                    item.id !== upload?.id
                                "
                                variant="ghost"
                                size="sm"
                                @click="pendingDiscard = item.id"
                                >Elvetés</Button
                            >
                        </div>
                    </td>
                </tr>
            </tbody>
        </DataTable>
    </section>

    <Dialog v-model:open="confirmDiscard">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Feltöltés elvetése</DialogTitle>
                <DialogDescription>
                    Minden eddig feltöltött rész törlődik a szerverről. Ez nem
                    vonható vissza.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="confirmDiscard = false"
                    >Mégse</Button
                >
                <Button variant="destructive" @click="confirmAndDiscard"
                    >Feltöltés elvetése</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
