<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

// Skill K.E.P mark: three rising bars on the brand tile (design-system preview).
// Follows tenant branding, because it is drawn with the primary tokens.
const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 28 28"
        aria-hidden="true"
        :class="cn('size-7 shrink-0', props.class)"
    >
        <rect width="28" height="28" rx="6" class="fill-primary" />
        <path
            d="M8 20v-3M14 20v-7M20 20V9"
            class="stroke-primary-foreground"
            stroke-width="2.4"
            stroke-linecap="round"
            fill="none"
        />
    </svg>
</template>
