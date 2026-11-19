<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <!-- Topbar: surface strip with a bottom border, like .kep-shell__topbar -->
    <header
        class="sticky top-0 z-10 flex h-(--kep-topbar-height) shrink-0 items-center gap-3 border-b border-border bg-card px-4 md:px-6"
    >
        <SidebarTrigger class="-ml-1.5 text-muted-foreground" />
        <Separator
            v-if="breadcrumbs.length > 0"
            orientation="vertical"
            class="h-5!"
        />
        <Breadcrumbs v-if="breadcrumbs.length > 0" :breadcrumbs="breadcrumbs" />

        <div class="ml-auto flex items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
