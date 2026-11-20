<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/admins';
import { create as createCompany } from '@/routes/companies';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Adminisztrátorok', href: index() },
            { title: 'Adminisztrátor felvétele', href: create() },
        ],
    },
});

defineProps<{
    companies: { id: number; name: string }[];
    selectedCompanyId: number | null;
}>();
</script>

<template>
    <Head title="Adminisztrátor felvétele" />

    <PageHeader
        title="Adminisztrátor felvétele"
        description="A cég adminisztrátora a saját cége tanulóit kezeli. Ideiglenes jelszót kap e-mailben."
    />

    <EmptyState
        v-if="companies.length === 0"
        :icon="Building2"
        title="Előbb vegyen fel egy céget"
        description="Az adminisztrátor mindig egy céghez tartozik."
    >
        <Button as-child>
            <Link :href="createCompany()">Cég felvétele</Link>
        </Button>
    </EmptyState>

    <Form
        v-else
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

        <div class="grid gap-2">
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

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing"
                >Adminisztrátor felvétele</Button
            >
            <Button variant="outline" as-child>
                <Link :href="index()">Mégse</Link>
            </Button>
        </div>
    </Form>
</template>
