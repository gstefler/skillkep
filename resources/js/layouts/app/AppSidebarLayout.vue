<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import GeneratedPasswordBanner from '@/components/GeneratedPasswordBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!-- Main area: page background, max content width, .kep-shell__main padding -->
            <div
                class="mx-auto flex w-full max-w-(--kep-content-max) flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <GeneratedPasswordBanner />
                <slot />
            </div>
        </AppContent>
        <Toaster position="bottom-right" />
    </AppShell>
</template>
