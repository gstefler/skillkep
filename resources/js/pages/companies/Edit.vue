<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import CompanyForm from '@/components/CompanyForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { dashboard } from '@/routes';
import { edit, index, show, update } from '@/routes/companies';

const props = defineProps<{
    company: {
        id: number;
        name: string;
        email: string;
        vat_id: string | null;
        phone: string | null;
    };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Irányítópult', href: dashboard() },
        { title: 'Cégek', href: index() },
        { title: props.company.name, href: show(props.company.id) },
        { title: 'Szerkesztés', href: edit(props.company.id) },
    ],
});
</script>

<template>
    <Head :title="`${company.name} szerkesztése`" />

    <PageHeader :title="`${company.name} szerkesztése`" />

    <CompanyForm
        :form="update.form(company.id)"
        :company="company"
        submit-label="Módosítások mentése"
        :cancel-href="show(company.id).url"
    />
</template>
