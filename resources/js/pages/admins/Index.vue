<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ShieldCheck, UserPlus } from '@lucide/vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, destroy, index } from '@/routes/admins';
import { show as showCompany } from '@/routes/companies';
import { passwordReset } from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Adminisztrátorok', href: index() },
        ],
    },
});

type Admin = {
    id: number;
    name: string;
    email: string;
    role: { value: 'admin' | 'superadmin'; label: string };
    company: { id: number; name: string } | null;
    created_at: string;
};

defineProps<{
    admins: {
        data: Admin[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

const currentUserId = usePage().props.auth.user.id;
</script>

<template>
    <Head title="Adminisztrátorok" />

    <PageHeader
        title="Adminisztrátorok"
        description="A cégek adminisztrátorai kezelik a saját cégük tanulóit. Szuperadminisztrátort csak a parancssorból lehet létrehozni."
        :meta="`${admins.total} fiók`"
    >
        <template #actions>
            <Button as-child>
                <Link :href="create()"
                    ><UserPlus /> Adminisztrátor felvétele</Link
                >
            </Button>
        </template>
    </PageHeader>

    <EmptyState
        v-if="admins.data.length === 0"
        :icon="ShieldCheck"
        title="Még nincs adminisztrátor"
        description="Vegyen fel egy cégadminisztrátort, aki a cége tanulóit kezeli."
    />

    <div v-else class="grid gap-2">
        <DataTable>
            <thead>
                <tr>
                    <th>Név</th>
                    <th>E-mail-cím</th>
                    <th>Szerepkör</th>
                    <th>Cég</th>
                    <th>Felvéve</th>
                    <th><span class="sr-only">Műveletek</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="admin in admins.data" :key="admin.id">
                    <td class="font-medium">{{ admin.name }}</td>
                    <td>{{ admin.email }}</td>
                    <td>
                        <Badge
                            :variant="
                                admin.role.value === 'superadmin'
                                    ? 'default'
                                    : 'secondary'
                            "
                            >{{ admin.role.label }}</Badge
                        >
                    </td>
                    <td>
                        <Link
                            v-if="admin.company"
                            :href="showCompany(admin.company.id)"
                            class="text-link hover:underline"
                            >{{ admin.company.name }}</Link
                        >
                        <template v-else>–</template>
                    </td>
                    <td class="tabular-nums">{{ admin.created_at }}</td>
                    <td>
                        <div
                            v-if="
                                admin.role.value === 'admin' &&
                                admin.id !== currentUserId
                            "
                            class="flex justify-end gap-1"
                        >
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
                                :action="destroy(admin.id)"
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
            :links="admins.links"
            :from="admins.from"
            :to="admins.to"
            :total="admins.total"
        />
    </div>
</template>
