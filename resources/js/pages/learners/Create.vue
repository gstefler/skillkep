<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/learners';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Tanulók', href: index() },
            { title: 'Tanuló felvétele', href: create() },
        ],
    },
});

// A superadmin picks the company; an admin's learners always join their own.
defineProps<{
    companies: { id: number; name: string }[];
    ownCompany: string | null;
    selectedCompanyId: number | null;
}>();
</script>

<template>
    <Head title="Tanuló felvétele" />

    <PageHeader
        title="Tanuló felvétele"
        description="A tanuló ideiglenes jelszót kap, amelyet e-mailben küldünk el neki."
    />

    <Form
        v-bind="store.form()"
        v-slot="{ errors, processing }"
        class="grid max-w-xl gap-5"
    >
        <div class="grid gap-2">
            <Label for="name">Név</Label>
            <Input
                id="name"
                name="name"
                required
                v-focus
                autocomplete="off"
                placeholder="Családnév Keresztnév"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">E-mail-cím</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="off"
                placeholder="nev@ceg.hu"
            />
            <InputError :message="errors.email" />
        </div>

        <div v-if="companies.length > 0" class="grid gap-2">
            <Label for="company_id">Cég</Label>
            <NativeSelect
                id="company_id"
                name="company_id"
                required
                :model-value="selectedCompanyId ?? ''"
                :aria-invalid="errors.company_id ? 'true' : undefined"
            >
                <option value="" disabled>Válasszon céget</option>
                <option
                    v-for="company in companies"
                    :key="company.id"
                    :value="company.id"
                >
                    {{ company.name }}
                </option>
            </NativeSelect>
            <InputError :message="errors.company_id" />
        </div>
        <div v-else class="grid gap-1">
            <span class="text-sm font-medium">Cég</span>
            <span class="text-sm text-muted-foreground">{{
                ownCompany ?? 'Nincs céghez rendelve'
            }}</span>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing"
                >Tanuló felvétele</Button
            >
            <Button variant="outline" as-child>
                <Link :href="index()">Mégse</Link>
            </Button>
        </div>
    </Form>
</template>
