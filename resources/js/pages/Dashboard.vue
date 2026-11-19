<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { BookOpen } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Irányítópult',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Irányítópult" />

    <PageHeader
        :title="`Üdvözöljük, ${user.name}`"
        description="Itt látja majd a kötelező képzéseit, a határidőket és a haladását."
    />

    <EmptyState
        :icon="BookOpen"
        title="Még nincs hozzárendelt képzés"
        description="Amint a szervezete képzést rendel Önhöz, itt jelenik meg, a legközelebbi határidővel az élen."
    />
</template>
