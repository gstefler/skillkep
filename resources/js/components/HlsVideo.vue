<script setup lang="ts">
import {
    Check,
    Gauge,
    LoaderCircle,
    Maximize,
    Minimize,
    Pause,
    Play,
    RotateCcw,
    Volume1,
    Volume2,
    VolumeX,
} from '@lucide/vue';
import type Hls from 'hls.js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatDuration } from '@/composables/useChunkedUpload';

// Encrypted HLS playback (ADR-0004) with controls in the design-system look.
// hls.js (loaded on demand) wherever Media Source Extensions exist;
// otherwise (iPhone Safari) native HLS. The playlist and the key come from
// Laravel with the session cookie, the segments from the static hls directory.
//
// Keyboard (when the player has focus): Space/K play–pause, ←/→ 5 s,
// ↑/↓ volume, M mute, F full screen, Home/End start/end.
const props = defineProps<{
    src: string;
    title: string;
    /** Known length in seconds, shown until the stream reports its own. */
    duration?: number | null;
}>();

const root = ref<HTMLDivElement | null>(null);
const video = ref<HTMLVideoElement | null>(null);
const track = ref<HTMLDivElement | null>(null);
const error = ref<string | null>(null);

const playing = ref(false);
const waiting = ref(false);
const ended = ref(false);
const started = ref(false);
const currentTime = ref(0);
const duration = ref(props.duration ?? 0);
const buffered = ref(0);
const volume = ref(1);
const muted = ref(false);
const rate = ref('1');
const fullscreen = ref(false);
const controlsVisible = ref(true);
const scrubbing = ref(false);
const hoverTime = ref<number | null>(null);
const hoverX = ref(0);

let hls: Hls | null = null;
let hideTimer: ReturnType<typeof setTimeout> | null = null;

const progress = computed(() =>
    duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0,
);
const bufferedPercent = computed(() =>
    duration.value > 0 ? (buffered.value / duration.value) * 100 : 0,
);
const volumeIcon = computed(() =>
    muted.value || volume.value === 0
        ? VolumeX
        : volume.value < 0.5
          ? Volume1
          : Volume2,
);
const rates = ['0.75', '1', '1.25', '1.5', '2'];
const rateLabel = (value: string) =>
    `${Number(value).toLocaleString('hu-HU')}×`;

// --- Playback -------------------------------------------------------------
function togglePlay(): void {
    const element = video.value;
    rateMenuOpen.value = false;

    if (!element || error.value) {
        return;
    }

    if (element.paused || element.ended) {
        element.play().catch(() => {
            // Autoplay policies or a load error; the error handler reports the latter.
        });
    } else {
        element.pause();
    }
}

// A tap on hidden controls (touch screens) only brings them back.
function onSurfaceClick(event: MouseEvent): void {
    const isTouch = (event as PointerEvent).pointerType === 'touch';

    if (isTouch && !showControls.value) {
        reveal();

        return;
    }

    togglePlay();
}

function seekTo(seconds: number): void {
    const element = video.value;

    if (!element || !duration.value) {
        return;
    }

    element.currentTime = Math.min(duration.value, Math.max(0, seconds));
    currentTime.value = element.currentTime;
}

function seekBy(seconds: number): void {
    seekTo(currentTime.value + seconds);
    reveal();
}

function setVolume(value: number): void {
    const element = video.value;

    if (!element) {
        return;
    }

    element.volume = Math.min(1, Math.max(0, value));
    element.muted = element.volume === 0;
}

function toggleMute(): void {
    const element = video.value;

    if (!element) {
        return;
    }

    if (element.muted || element.volume === 0) {
        element.muted = false;
        element.volume = element.volume === 0 ? 0.5 : element.volume;
    } else {
        element.muted = true;
    }
}

const rateMenuOpen = ref(false);

function setRate(value: string): void {
    rate.value = value;
    rateMenuOpen.value = false;

    if (video.value) {
        video.value.playbackRate = Number(value);
    }
}

async function toggleFullscreen(): Promise<void> {
    const element = root.value;
    const media = video.value as
        | (HTMLVideoElement & { webkitEnterFullscreen?: () => void })
        | null;

    if (!element) {
        return;
    }

    if (document.fullscreenElement) {
        await document.exitFullscreen();
    } else if (element.requestFullscreen) {
        await element.requestFullscreen();
    } else {
        // iPhone Safari only allows full screen on the video itself.
        media?.webkitEnterFullscreen?.();
    }
}

// --- Scrubbing on the progress track --------------------------------------
function timeAt(clientX: number): number {
    const rect = track.value!.getBoundingClientRect();
    const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));

    return ratio * duration.value;
}

function onTrackPointerDown(event: PointerEvent): void {
    if (!duration.value) {
        return;
    }

    scrubbing.value = true;
    track.value!.setPointerCapture(event.pointerId);
    seekTo(timeAt(event.clientX));
}

function onTrackPointerMove(event: PointerEvent): void {
    if (!duration.value) {
        return;
    }

    const rect = track.value!.getBoundingClientRect();
    hoverX.value = Math.min(rect.width, Math.max(0, event.clientX - rect.left));
    hoverTime.value = timeAt(event.clientX);

    if (scrubbing.value) {
        seekTo(hoverTime.value);
    }
}

function onTrackPointerUp(event: PointerEvent): void {
    scrubbing.value = false;
    track.value?.releasePointerCapture(event.pointerId);
}

function onTrackKeydown(event: KeyboardEvent): void {
    const steps: Record<string, number> = {
        ArrowLeft: -5,
        ArrowRight: 5,
        PageDown: -30,
        PageUp: 30,
    };

    if (event.key in steps) {
        event.preventDefault();
        event.stopPropagation();
        seekBy(steps[event.key]);
    } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        event.stopPropagation();
        seekTo(event.key === 'Home' ? 0 : duration.value);
    }
}

function onVolumeKeydown(event: KeyboardEvent): void {
    const delta = {
        ArrowLeft: -0.1,
        ArrowDown: -0.1,
        ArrowRight: 0.1,
        ArrowUp: 0.1,
    }[event.key];

    if (delta !== undefined) {
        event.preventDefault();
        event.stopPropagation();
        setVolume((muted.value ? 0 : volume.value) + delta);
    }
}

function onVolumePointer(event: PointerEvent): void {
    if (event.type === 'pointerdown') {
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    } else if (event.buttons !== 1) {
        return;
    }

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    setVolume((event.clientX - rect.left) / rect.width);
}

// --- Player-wide keyboard shortcuts ---------------------------------------
function onKeydown(event: KeyboardEvent): void {
    if (event.altKey || event.ctrlKey || event.metaKey) {
        return;
    }

    const target = event.target as HTMLElement;
    const onButton = target.tagName === 'BUTTON';

    if (event.key === 'Escape' && rateMenuOpen.value) {
        rateMenuOpen.value = false;

        return;
    }

    if (rateMenuOpen.value) {
        return;
    }

    switch (event.key) {
        case ' ':
        case 'k':
        case 'K':
            if (onButton && event.key === ' ') {
                return;
            }

            event.preventDefault();
            togglePlay();
            break;
        case 'ArrowLeft':
            event.preventDefault();
            seekBy(-5);
            break;
        case 'ArrowRight':
            event.preventDefault();
            seekBy(5);
            break;
        case 'ArrowUp':
            event.preventDefault();
            setVolume(volume.value + 0.1);
            break;
        case 'ArrowDown':
            event.preventDefault();
            setVolume(volume.value - 0.1);
            break;
        case 'm':
        case 'M':
            toggleMute();
            break;
        case 'f':
        case 'F':
            toggleFullscreen();
            break;
        default:
            return;
    }

    reveal();
}

// --- Auto-hiding controls while playing -----------------------------------
function reveal(): void {
    controlsVisible.value = true;

    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    hideTimer = setTimeout(() => {
        if (playing.value && !scrubbing.value) {
            controlsVisible.value = false;
        }
    }, 2500);
}

const showControls = computed(
    () =>
        controlsVisible.value ||
        !playing.value ||
        scrubbing.value ||
        rateMenuOpen.value,
);

// --- Media events -----------------------------------------------------------
function syncFromVideo(): void {
    const element = video.value;

    if (!element) {
        return;
    }

    playing.value = !element.paused && !element.ended;
    ended.value = element.ended;
    currentTime.value = element.currentTime;
    duration.value =
        Number.isFinite(element.duration) && element.duration > 0
            ? element.duration
            : (props.duration ?? 0);
    volume.value = element.volume;
    muted.value = element.muted;

    const ranges = element.buffered;
    buffered.value = ranges.length > 0 ? ranges.end(ranges.length - 1) : 0;
}

const onFullscreenChange = () => {
    fullscreen.value = document.fullscreenElement === root.value;
};

onMounted(async () => {
    const element = video.value;

    if (!element) {
        return;
    }

    document.addEventListener('fullscreenchange', onFullscreenChange);

    const { default: HlsPlayer } = await import('hls.js');

    if (!HlsPlayer.isSupported()) {
        if (element.canPlayType('application/vnd.apple.mpegurl')) {
            element.src = props.src;
        } else {
            error.value = 'A böngészője nem tudja lejátszani ezt a videót.';
        }

        return;
    }

    hls = new HlsPlayer({
        xhrSetup: (xhr) => {
            xhr.withCredentials = true;
        },
    });
    hls.on(HlsPlayer.Events.ERROR, (_event, data) => {
        if (data.fatal) {
            error.value =
                'A videó nem tölthető be. Frissítse az oldalt, és próbálja újra.';
        }
    });
    hls.loadSource(props.src);
    hls.attachMedia(element);
});

onBeforeUnmount(() => {
    hls?.destroy();
    document.removeEventListener('fullscreenchange', onFullscreenChange);

    if (hideTimer) {
        clearTimeout(hideTimer);
    }
});

// The video surface is dark in both themes, so the controls use the fixed
// ink primitives (light on dark) instead of the theme-dependent tokens.
const controlButton =
    'shrink-0 rounded-md text-(--kep-ink-0) transition-colors hover:bg-(--kep-ink-0)/15 focus-visible:ring-2 focus-visible:ring-(--kep-ink-0) focus-visible:outline-none [&>svg]:size-5';
const iconButton = `${controlButton} inline-grid size-11 place-items-center`;
const rateButton = `${controlButton} inline-flex h-11 items-center gap-1.5 px-2`;
</script>

<template>
    <div class="grid gap-2">
        <div
            ref="root"
            role="region"
            :aria-label="`Videólejátszó: ${title}`"
            tabindex="0"
            :class="[
                'group/player relative overflow-hidden rounded-lg bg-(--kep-ink-950) focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                fullscreen ? 'flex items-center' : 'aspect-video',
                showControls ? '' : 'cursor-none',
            ]"
            @keydown="onKeydown"
            @pointermove="reveal"
            @pointerleave="playing && (controlsVisible = false)"
        >
            <video
                ref="video"
                playsinline
                preload="metadata"
                class="size-full object-contain"
                @click="onSurfaceClick"
                @dblclick="toggleFullscreen"
                @play="(syncFromVideo(), (started = true), reveal())"
                @pause="syncFromVideo"
                @ended="syncFromVideo"
                @timeupdate="syncFromVideo"
                @durationchange="syncFromVideo"
                @loadedmetadata="syncFromVideo"
                @progress="syncFromVideo"
                @volumechange="syncFromVideo"
                @waiting="waiting = true"
                @playing="waiting = false"
                @canplay="waiting = false"
            />

            <!-- Large centre button before the first play and after the end -->
            <button
                v-if="(!started || ended) && !error"
                type="button"
                class="absolute inset-0 m-auto grid size-16 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-(--kep-ink-0) focus-visible:ring-offset-2 focus-visible:ring-offset-(--kep-ink-950) focus-visible:outline-none"
                :aria-label="ended ? 'Újrajátszás' : 'Lejátszás'"
                @click="ended ? (seekTo(0), togglePlay()) : togglePlay()"
            >
                <RotateCcw v-if="ended" class="size-7" />
                <Play v-else class="ml-1 size-7 fill-current" />
            </button>

            <LoaderCircle
                v-if="waiting && playing"
                class="pointer-events-none absolute inset-0 m-auto size-10 animate-spin text-(--kep-ink-0)"
                aria-hidden="true"
            />

            <!-- Control bar -->
            <div
                :class="[
                    'absolute inset-x-0 bottom-0 grid gap-1 bg-linear-to-t from-(--kep-ink-950)/85 via-(--kep-ink-950)/50 to-transparent px-3 pt-10 pb-2 text-(--kep-ink-0) transition-opacity duration-(--kep-duration-base)',
                    showControls
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                ]"
            >
                <!-- Progress track -->
                <div
                    ref="track"
                    role="slider"
                    tabindex="0"
                    aria-label="Lejátszási pozíció"
                    :aria-valuemin="0"
                    :aria-valuemax="Math.round(duration)"
                    :aria-valuenow="Math.round(currentTime)"
                    :aria-valuetext="`${formatDuration(currentTime)} / ${formatDuration(duration)}`"
                    class="group/track relative flex h-5 cursor-pointer touch-none items-center rounded-sm focus-visible:ring-2 focus-visible:ring-(--kep-ink-0) focus-visible:outline-none"
                    @pointerdown="onTrackPointerDown"
                    @pointermove="onTrackPointerMove"
                    @pointerup="onTrackPointerUp"
                    @pointerleave="hoverTime = null"
                    @keydown="onTrackKeydown"
                >
                    <div
                        class="relative h-1 w-full overflow-hidden rounded-full bg-(--kep-ink-0)/25 transition-[height] group-hover/track:h-1.5"
                    >
                        <div
                            class="absolute inset-y-0 left-0 bg-(--kep-ink-0)/40"
                            :style="{ width: `${bufferedPercent}%` }"
                        />
                        <div
                            class="absolute inset-y-0 left-0 bg-primary"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                    <div
                        class="absolute size-3.5 -translate-x-1/2 rounded-full bg-primary ring-2 ring-(--kep-ink-0) transition-transform group-hover/track:scale-110"
                        :style="{ left: `${progress}%` }"
                        aria-hidden="true"
                    />
                    <span
                        v-if="hoverTime !== null"
                        class="pointer-events-none absolute bottom-6 -translate-x-1/2 rounded-sm bg-(--kep-ink-950)/80 px-1.5 py-0.5 font-mono text-2xs tabular-nums"
                        :style="{ left: `${hoverX}px` }"
                        aria-hidden="true"
                        >{{ formatDuration(hoverTime) }}</span
                    >
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :class="iconButton"
                        :aria-label="playing ? 'Szünet' : 'Lejátszás'"
                        :title="playing ? 'Szünet (K)' : 'Lejátszás (K)'"
                        @click="togglePlay"
                    >
                        <Pause v-if="playing" class="fill-current" />
                        <Play v-else class="fill-current" />
                    </button>

                    <div class="group/volume flex items-center">
                        <button
                            type="button"
                            :class="iconButton"
                            :aria-label="
                                muted ? 'Hang bekapcsolása' : 'Némítás'
                            "
                            :title="
                                muted ? 'Hang bekapcsolása (M)' : 'Némítás (M)'
                            "
                            @click="toggleMute"
                        >
                            <component :is="volumeIcon" />
                        </button>
                        <div
                            role="slider"
                            tabindex="0"
                            aria-label="Hangerő"
                            :aria-valuemin="0"
                            :aria-valuemax="100"
                            :aria-valuenow="
                                Math.round((muted ? 0 : volume) * 100)
                            "
                            :aria-valuetext="`${Math.round((muted ? 0 : volume) * 100)} %`"
                            class="relative hidden h-11 w-20 cursor-pointer touch-none items-center rounded-sm focus-visible:ring-2 focus-visible:ring-(--kep-ink-0) focus-visible:outline-none sm:flex"
                            @pointerdown="onVolumePointer"
                            @pointermove="onVolumePointer"
                            @keydown="onVolumeKeydown"
                        >
                            <div
                                class="relative h-1 w-full overflow-hidden rounded-full bg-(--kep-ink-0)/25"
                            >
                                <div
                                    class="absolute inset-y-0 left-0 bg-(--kep-ink-0)"
                                    :style="{
                                        width: `${(muted ? 0 : volume) * 100}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <span
                        class="ml-2 font-mono text-xs whitespace-nowrap tabular-nums"
                    >
                        {{ formatDuration(currentTime) }}
                        <span class="text-(--kep-ink-0)/60"
                            >/ {{ formatDuration(duration) }}</span
                        >
                    </span>

                    <div class="ml-auto flex items-center gap-1">
                        <!-- Inline menu: a portalled one would vanish in full screen -->
                        <div class="relative">
                            <button
                                type="button"
                                :class="rateButton"
                                aria-label="Lejátszási sebesség"
                                aria-haspopup="menu"
                                :aria-expanded="rateMenuOpen"
                                title="Lejátszási sebesség"
                                @click="rateMenuOpen = !rateMenuOpen"
                            >
                                <Gauge />
                                <span class="font-mono text-xs tabular-nums">{{
                                    rateLabel(rate)
                                }}</span>
                            </button>
                            <div
                                v-if="rateMenuOpen"
                                role="menu"
                                aria-label="Lejátszási sebesség"
                                class="absolute right-0 bottom-12 z-10 grid min-w-32 gap-0.5 rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
                            >
                                <button
                                    v-for="option in rates"
                                    :key="option"
                                    type="button"
                                    role="menuitemradio"
                                    :aria-checked="rate === option"
                                    class="flex h-9 items-center justify-between gap-3 rounded-sm px-2 text-sm tabular-nums hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                    @click="setRate(option)"
                                >
                                    {{ rateLabel(option) }}
                                    <Check
                                        v-if="rate === option"
                                        class="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            :class="iconButton"
                            :aria-label="
                                fullscreen
                                    ? 'Kilépés a teljes képernyőből'
                                    : 'Teljes képernyő'
                            "
                            :title="
                                fullscreen
                                    ? 'Kilépés a teljes képernyőből (F)'
                                    : 'Teljes képernyő (F)'
                            "
                            @click="toggleFullscreen"
                        >
                            <Minimize v-if="fullscreen" />
                            <Maximize v-else />
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
    </div>
</template>
