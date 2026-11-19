<script setup lang="ts">
// Page title row (design-system .kep-page-title): title + optional description,
// meta text on the right, and an `actions` slot for the page's main buttons.
defineProps<{
    title: string;
    description?: string;
    meta?: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div class="min-w-0 space-y-1">
            <h1
                class="text-2xl leading-tight font-semibold tracking-tight text-balance"
            >
                {{ title }}
            </h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div
            v-if="meta || $slots.actions"
            class="flex shrink-0 items-center gap-3"
        >
            <span v-if="meta" class="text-xs text-subtle">{{ meta }}</span>
            <slot name="actions" />
        </div>
    </div>
</template>
