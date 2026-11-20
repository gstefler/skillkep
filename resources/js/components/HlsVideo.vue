<script setup lang="ts">
import type Hls from 'hls.js';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Encrypted HLS playback (ADR-0004). hls.js (loaded on demand) wherever
// Media Source Extensions exist; otherwise (iPhone Safari) native HLS. The playlist and the key come from Laravel
// with the session cookie, the segments from the static hls directory.
const props = defineProps<{
    src: string;
    title: string;
}>();

const video = ref<HTMLVideoElement | null>(null);
const error = ref<string | null>(null);
let hls: Hls | null = null;

onMounted(async () => {
    const element = video.value;

    if (!element) {
        return;
    }

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

onBeforeUnmount(() => hls?.destroy());
</script>

<template>
    <div class="grid gap-2">
        <video
            ref="video"
            controls
            playsinline
            preload="metadata"
            :aria-label="title"
            class="aspect-video w-full rounded-md bg-black"
        />
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
    </div>
</template>
