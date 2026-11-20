<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { index } from '@/routes/courses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Képzéseim', href: index() }],
    },
});

// Courses do not exist yet; the list is always empty for now.
defineProps<{ courses: unknown[] }>();
</script>

<template>
    <Head title="Képzéseim" />

    <PageHeader
        title="Képzéseim"
        description="Itt látja majd a kötelező képzéseit, a határidőket és a haladását."
    />

    <EmptyState
        :icon="BookOpen"
        title="Még nincs hozzárendelt képzés"
        description="Amint a szervezete képzést rendel Önhöz, itt jelenik meg, a legközelebbi határidővel az élen."
    />
</template>
