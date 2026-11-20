<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CompanyForm from '@/components/CompanyForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/companies';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Cégek', href: index() },
            { title: 'Cég felvétele', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Cég felvétele" />

    <PageHeader
        title="Cég felvétele"
        description="A cég létrehozása után tanulókat és adminisztrátorokat rendelhet hozzá."
    />

    <CompanyForm
        :form="store.form()"
        submit-label="Cég felvétele"
        :cancel-href="index().url"
    />
</template>
