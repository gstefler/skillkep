<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Plus } from '@lucide/vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, destroy, edit, index, show } from '@/routes/companies';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Cégek', href: index() },
        ],
    },
});

type Company = {
    id: number;
    name: string;
    email: string;
    vat_id: string | null;
    phone: string | null;
    learners_count: number;
    admins_count: number;
};

defineProps<{
    companies: {
        data: Company[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();
</script>

<template>
    <Head title="Cégek" />

    <PageHeader
        title="Cégek"
        description="Vegye fel a céget, majd rendeljen hozzá tanulókat és adminisztrátorokat."
        :meta="`${companies.total} cég`"
    >
        <template #actions>
            <Button as-child>
                <Link :href="create()"><Plus /> Cég felvétele</Link>
            </Button>
        </template>
    </PageHeader>

    <EmptyState
        v-if="companies.data.length === 0"
        :icon="Building2"
        title="Még nincs felvett cég"
        description="Vegye fel az első céget, utána tanulókat és adminisztrátorokat rendelhet hozzá."
    >
        <Button as-child>
            <Link :href="create()">Cég felvétele</Link>
        </Button>
    </EmptyState>

    <div v-else class="grid gap-2">
        <DataTable>
            <thead>
                <tr>
                    <th>Cég</th>
                    <th>E-mail-cím</th>
                    <th>Adószám</th>
                    <th>Telefonszám</th>
                    <th class="is-num">Tanulók</th>
                    <th class="is-num">Adminisztrátorok</th>
                    <th><span class="sr-only">Műveletek</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="company in companies.data" :key="company.id">
                    <td class="font-medium">
                        <Link
                            :href="show(company.id)"
                            class="text-link hover:underline"
                            >{{ company.name }}</Link
                        >
                    </td>
                    <td>{{ company.email }}</td>
                    <td class="font-mono text-xs">
                        {{ company.vat_id ?? '–' }}
                    </td>
                    <td>{{ company.phone ?? '–' }}</td>
                    <td class="is-num">{{ company.learners_count }}</td>
                    <td class="is-num">{{ company.admins_count }}</td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="edit(company.id)"
                                    >Szerkesztés</Link
                                >
                            </Button>
                            <ConfirmAction
                                :title="`${company.name} törlése`"
                                description="Csak olyan cég törölhető, amelyhez már nem tartozik felhasználó."
                                confirm-label="Cég törlése"
                                :action="destroy(company.id)"
                            >
                                <Button variant="ghost" size="sm"
                                    >Törlés</Button
                                >
                            </ConfirmAction>
                        </div>
                    </td>
                </tr>
            </tbody>
        </DataTable>
        <PaginationLinks
            :links="companies.links"
            :from="companies.from"
            :to="companies.to"
            :total="companies.total"
        />
    </div>
</template>
