<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

// Empty state (design-system .kep-empty): icon, what is missing, what to do next.
defineProps<{
    title: string;
    description?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border-strong bg-card px-6 py-12 text-center"
    >
        <div
            v-if="icon"
            class="grid size-12 place-items-center rounded-full bg-muted text-subtle"
        >
            <component :is="icon" class="size-6" aria-hidden="true" />
        </div>
        <div class="max-w-sm space-y-1">
            <p class="text-base font-semibold">{{ title }}</p>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.default"
            class="mt-2 flex flex-wrap justify-center gap-2"
        >
            <slot />
        </div>
    </div>
</template>
