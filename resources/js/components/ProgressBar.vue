<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

// Linear progress (design-system .kep-progress). Status tone must also be
// stated in text next to it, never by colour alone.
const props = withDefaults(
    defineProps<{
        /** 0–100 */
        value: number;
        tone?: 'primary' | 'success' | 'warning' | 'danger';
        size?: 'default' | 'lg';
        /** Accessible name, e.g. the course title. */
        label?: string;
        class?: string;
    }>(),
    { tone: 'primary', size: 'default' },
);

const clamped = computed(() => Math.min(100, Math.max(0, props.value)));

const fill = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-destructive',
} as const;
</script>

<template>
    <div
        role="progressbar"
        :aria-valuenow="clamped"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="label"
        :class="
            cn(
                'w-full overflow-hidden rounded-full bg-track',
                size === 'lg' ? 'h-2' : 'h-1.5',
                props.class,
            )
        "
    >
        <div
            :class="[
                'h-full rounded-full transition-[width] duration-(--kep-duration-slow) ease-standard',
                fill[tone],
            ]"
            :style="{ width: `${clamped}%` }"
        />
    </div>
</template>
