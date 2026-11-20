<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search, UserPlus, Users } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, destroy, index, show } from '@/routes/learners';
import { passwordReset } from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Tanulók', href: index() },
        ],
    },
});

type Learner = {
    id: number;
    name: string;
    email: string;
    company: { id: number; name: string } | null;
    created_at: string;
};

const props = defineProps<{
    learners: {
        data: Learner[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q: string };
}>();

const showCompany = computed(
    () => usePage().props.auth.user.role === 'superadmin',
);

const query = ref(props.filters.q);
const search = useDebounceFn(() => {
    router.get(
        index.url(),
        { q: query.value || undefined },
        { preserveState: true, replace: true },
    );
}, 300);
</script>

<template>
    <Head title="Tanulók" />

    <PageHeader
        title="Tanulók"
        :description="
            showCompany
                ? 'Az összes cég tanulója.'
                : 'A cége tanulói. Az új tanuló automatikusan a céghez kerül.'
        "
        :meta="`${learners.total} tanuló`"
    >
        <template #actions>
            <Button as-child>
                <Link :href="create()"><UserPlus /> Tanuló felvétele</Link>
            </Button>
        </template>
    </PageHeader>

    <form
        role="search"
        class="kep-input-group max-w-sm"
        @submit.prevent="search"
    >
        <Search class="kep-icon size-4" aria-hidden="true" />
        <input
            v-model="query"
            type="search"
            class="kep-input"
            placeholder="Keresés névre vagy e-mail-címre"
            aria-label="Tanulók keresése"
            @input="search"
        />
    </form>

    <EmptyState
        v-if="learners.data.length === 0"
        :icon="Users"
        :title="filters.q ? 'Nincs találat' : 'Még nincs felvett tanuló'"
        :description="
            filters.q
                ? 'Próbáljon másik névre vagy e-mail-címre keresni.'
                : 'Vegye fel az első tanulót; a belépési adatokat e-mailben kapja meg.'
        "
    >
        <Button v-if="!filters.q" as-child>
            <Link :href="create()">Tanuló felvétele</Link>
        </Button>
    </EmptyState>

    <div v-else class="grid gap-2">
        <DataTable>
            <thead>
                <tr>
                    <th>Név</th>
                    <th>E-mail-cím</th>
                    <th v-if="showCompany">Cég</th>
                    <th>Felvéve</th>
                    <th><span class="sr-only">Műveletek</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="learner in learners.data" :key="learner.id">
                    <td class="font-medium">
                        <Link
                            :href="show(learner.id)"
                            class="text-link hover:underline"
                            >{{ learner.name }}</Link
                        >
                    </td>
                    <td>{{ learner.email }}</td>
                    <td v-if="showCompany">
                        {{ learner.company?.name ?? '–' }}
                    </td>
                    <td class="tabular-nums">{{ learner.created_at }}</td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <ConfirmAction
                                :title="`${learner.name} jelszavának visszaállítása`"
                                description="Új ideiglenes jelszó készül, amelyet e-mailben is megkap. A régi jelszó azonnal érvénytelen lesz."
                                confirm-label="Jelszó visszaállítása"
                                variant="default"
                                :action="passwordReset(learner.id)"
                            >
                                <Button variant="ghost" size="sm"
                                    >Jelszó visszaállítása</Button
                                >
                            </ConfirmAction>
                            <ConfirmAction
                                :title="`${learner.name} törlése`"
                                description="A fiók véglegesen törlődik, ez nem vonható vissza."
                                confirm-label="Tanuló törlése"
                                :action="destroy(learner.id)"
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
            :links="learners.links"
            :from="learners.from"
            :to="learners.to"
            :total="learners.total"
        />
    </div>
</template>
