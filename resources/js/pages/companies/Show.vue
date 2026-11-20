<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { GraduationCap, Pencil, ShieldCheck, UserPlus } from '@lucide/vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    create as createAdmin,
    destroy as destroyAdmin,
} from '@/routes/admins';
import { edit, index, show } from '@/routes/companies';
import {
    create as createLearner,
    show as showLearner,
} from '@/routes/learners';
import { passwordReset } from '@/routes/users';

type Member = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

const props = defineProps<{
    company: {
        id: number;
        name: string;
        email: string;
        vat_id: string | null;
        phone: string | null;
    };
    learners: Member[];
    admins: Member[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Irányítópult', href: dashboard() },
        { title: 'Cégek', href: index() },
        { title: props.company.name, href: show(props.company.id) },
    ],
});
</script>

<template>
    <Head :title="company.name" />

    <PageHeader :title="company.name" :description="company.email">
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="edit(company.id)"
                    ><Pencil /> Cég szerkesztése</Link
                >
            </Button>
        </template>
    </PageHeader>

    <section
        class="rounded-lg border border-border bg-card p-(--kep-card-pad)"
        aria-labelledby="company-details"
    >
        <h2 id="company-details" class="mb-4 text-lg font-semibold">Adatok</h2>
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div class="grid gap-1">
                <dt class="text-xs font-medium text-subtle">Adószám</dt>
                <dd class="font-mono">{{ company.vat_id ?? '–' }}</dd>
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-medium text-subtle">Telefonszám</dt>
                <dd>{{ company.phone ?? '–' }}</dd>
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-medium text-subtle">Kapcsolattartó</dt>
                <dd>{{ company.email }}</dd>
            </div>
        </dl>
    </section>

    <section class="grid gap-3" aria-labelledby="company-learners">
        <div class="flex items-center justify-between gap-3">
            <h2 id="company-learners" class="text-lg font-semibold">
                Tanulók
                <span class="text-sm font-normal text-subtle tabular-nums"
                    >({{ learners.length }})</span
                >
            </h2>
            <Button as-child>
                <Link :href="createLearner({ query: { company: company.id } })"
                    ><UserPlus /> Tanuló felvétele</Link
                >
            </Button>
        </div>

        <EmptyState
            v-if="learners.length === 0"
            :icon="GraduationCap"
            title="Ehhez a céghez még nem tartozik tanuló"
            description="Vegye fel az első tanulót; a belépési adatokat e-mailben kapja meg."
        />
        <DataTable v-else>
            <thead>
                <tr>
                    <th>Név</th>
                    <th>E-mail-cím</th>
                    <th>Felvéve</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="learner in learners" :key="learner.id">
                    <td class="font-medium">
                        <Link
                            :href="showLearner(learner.id)"
                            class="text-link hover:underline"
                            >{{ learner.name }}</Link
                        >
                    </td>
                    <td>{{ learner.email }}</td>
                    <td class="tabular-nums">{{ learner.created_at }}</td>
                </tr>
            </tbody>
        </DataTable>
    </section>

    <section class="grid gap-3" aria-labelledby="company-admins">
        <div class="flex items-center justify-between gap-3">
            <h2 id="company-admins" class="text-lg font-semibold">
                Adminisztrátorok
                <span class="text-sm font-normal text-subtle tabular-nums"
                    >({{ admins.length }})</span
                >
            </h2>
            <Button variant="outline" as-child>
                <Link :href="createAdmin({ query: { company: company.id } })"
                    ><UserPlus /> Adminisztrátor felvétele</Link
                >
            </Button>
        </div>

        <EmptyState
            v-if="admins.length === 0"
            :icon="ShieldCheck"
            title="Ehhez a céghez még nem tartozik adminisztrátor"
            description="A cég adminisztrátora a cég tanulóit kezeli."
        />
        <DataTable v-else>
            <thead>
                <tr>
                    <th>Név</th>
                    <th>E-mail-cím</th>
                    <th>Felvéve</th>
                    <th><span class="sr-only">Műveletek</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="admin in admins" :key="admin.id">
                    <td class="font-medium">{{ admin.name }}</td>
                    <td>{{ admin.email }}</td>
                    <td class="tabular-nums">{{ admin.created_at }}</td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <ConfirmAction
                                :title="`${admin.name} jelszavának visszaállítása`"
                                description="Új ideiglenes jelszó készül, amelyet e-mailben is megkap. A régi jelszó azonnal érvénytelen lesz."
                                confirm-label="Jelszó visszaállítása"
                                variant="default"
                                :action="passwordReset(admin.id)"
                            >
                                <Button variant="ghost" size="sm"
                                    >Jelszó visszaállítása</Button
                                >
                            </ConfirmAction>
                            <ConfirmAction
                                :title="`${admin.name} törlése`"
                                description="A fiók véglegesen törlődik, ez nem vonható vissza."
                                confirm-label="Adminisztrátor törlése"
                                :action="destroyAdmin(admin.id)"
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
    </section>
</template>
