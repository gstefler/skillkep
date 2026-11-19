<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <!-- Guest layout: brand on top, form on a surface card, centred on the page background. -->
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-8 bg-background px-4 py-10"
    >
        <div class="flex w-full max-w-[25rem] flex-col gap-6">
            <div class="flex items-center gap-2.5 self-center">
                <AppLogo />
            </div>

            <div
                class="rounded-lg border border-border bg-card p-6 shadow-sm sm:p-8"
            >
                <header class="mb-6 space-y-1.5">
                    <h1
                        class="text-xl leading-tight font-semibold tracking-tight text-balance"
                    >
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </header>
                <slot />
            </div>

            <p class="text-center text-xs text-subtle">
                © {{ year }} Skill K.E.P
            </p>
        </div>
    </div>
</template>
