<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { UserPlus, Users } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/learners';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Irányítópult', href: dashboard() }],
    },
});

const props = defineProps<{
    stats: {
        learners: number;
        admins: number | null;
        companies: number | null;
    };
    company: string | null;
    recentLearners: {
        id: number;
        name: string;
        email: string;
        company: string | null;
        created_at: string;
    }[];
}>();

const user = computed(() => usePage().props.auth.user);
const isSuperAdmin = computed(() => user.value.role === 'superadmin');
</script>

<template>
    <Head title="Irányítópult" />

    <PageHeader
        :title="`Üdvözöljük, ${user.name}`"
        :description="
            company
                ? `${company} tanulóinak kezelése.`
                : 'Cégek, adminisztrátorok és tanulók kezelése.'
        "
    >
        <template #actions>
            <Button as-child>
                <Link :href="create()"><UserPlus /> Tanuló felvétele</Link>
            </Button>
        </template>
    </PageHeader>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <StatCard label="Tanulók" :value="props.stats.learners" />
        <StatCard
            v-if="isSuperAdmin"
            label="Cégek"
            :value="props.stats.companies ?? 0"
        />
        <StatCard
            v-if="isSuperAdmin"
            label="Adminisztrátorok"
            :value="props.stats.admins ?? 0"
        />
    </div>

    <section class="grid gap-3" aria-labelledby="recent-learners">
        <div class="flex items-center justify-between">
            <h2 id="recent-learners" class="text-lg font-semibold">
                Legutóbb felvett tanulók
            </h2>
            <Button variant="ghost" size="sm" as-child>
                <Link :href="index()">Összes tanuló</Link>
            </Button>
        </div>

        <EmptyState
            v-if="recentLearners.length === 0"
            :icon="Users"
            title="Még nincs felvett tanuló"
            description="Vegye fel az első tanulót; a belépési adatokat e-mailben kapja meg."
        >
            <Button as-child>
                <Link :href="create()">Tanuló felvétele</Link>
            </Button>
        </EmptyState>

        <DataTable v-else>
            <thead>
                <tr>
                    <th>Név</th>
                    <th>E-mail-cím</th>
                    <th>Cég</th>
                    <th>Felvéve</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="learner in recentLearners" :key="learner.id">
                    <td class="font-medium">
                        <Link
                            :href="show(learner.id)"
                            class="text-link hover:underline"
                            >{{ learner.name }}</Link
                        >
                    </td>
                    <td>{{ learner.email }}</td>
                    <td>{{ learner.company ?? '–' }}</td>
                    <td class="tabular-nums">{{ learner.created_at }}</td>
                </tr>
            </tbody>
        </DataTable>
    </section>
</template>
