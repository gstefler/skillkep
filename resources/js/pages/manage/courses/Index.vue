<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Upload } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/manage/courses';
import { create as upload } from '@/routes/manage/courses/uploads';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Irányítópult', href: dashboard() },
            { title: 'Képzések', href: index() },
        ],
    },
});

type CourseRow = {
    id: number;
    title: string;
    is_published: boolean;
    modules_count: number;
    companies_count: number;
    learners_count: number;
    created_at: string;
};

defineProps<{
    courses: {
        data: CourseRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    canUpload: boolean;
}>();

const isSuperAdmin = computed(
    () => usePage().props.auth.user.role === 'superadmin',
);
</script>

<template>
    <Head title="Képzések" />

    <PageHeader
        title="Képzések"
        :description="
            isSuperAdmin
                ? 'Az összes kurzus. A céghez rendelés csak elérhetővé teszi a kurzust; a tanulók hozzáférését egyenként kell megadni.'
                : 'A cégéhez rendelt kurzusok. Nyissa meg a kurzust, és jelölje ki, mely tanulók végezhetik el.'
        "
        :meta="`${courses.total} kurzus`"
    >
        <template v-if="canUpload" #actions>
            <Button as-child>
                <Link :href="upload()"><Upload /> Kurzus feltöltése</Link>
            </Button>
        </template>
    </PageHeader>

    <EmptyState
        v-if="courses.data.length === 0"
        :icon="BookOpen"
        :title="
            isSuperAdmin
                ? 'Még nincs feltöltött kurzus'
                : 'A cégéhez még nincs kurzus rendelve'
        "
        :description="
            isSuperAdmin
                ? 'Töltse fel az első kurzust ZIP-fájlként.'
                : 'Amint a szolgáltató kurzust rendel a cégéhez, itt jelenik meg.'
        "
    >
        <Button v-if="canUpload" as-child>
            <Link :href="upload()"><Upload /> Kurzus feltöltése</Link>
        </Button>
    </EmptyState>

    <template v-else>
        <DataTable>
            <thead>
                <tr>
                    <th>Kurzus</th>
                    <th>Állapot</th>
                    <th class="is-num">Modulok</th>
                    <th v-if="isSuperAdmin" class="is-num">Cégek</th>
                    <th class="is-num">Beiratkozott tanulók</th>
                    <th>Feltöltve</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="course in courses.data" :key="course.id">
                    <td class="font-medium">
                        <Link
                            :href="show(course.id)"
                            class="text-link hover:underline"
                            >{{ course.title }}</Link
                        >
                    </td>
                    <td>
                        <Badge
                            :variant="
                                course.is_published ? 'success' : 'secondary'
                            "
                            >{{
                                course.is_published ? 'Közzétéve' : 'Piszkozat'
                            }}</Badge
                        >
                    </td>
                    <td class="is-num tabular-nums">
                        {{ course.modules_count }}
                    </td>
                    <td v-if="isSuperAdmin" class="is-num tabular-nums">
                        {{ course.companies_count }}
                    </td>
                    <td class="is-num tabular-nums">
                        {{ course.learners_count }}
                    </td>
                    <td class="tabular-nums">{{ course.created_at }}</td>
                </tr>
            </tbody>
        </DataTable>

        <PaginationLinks
            :links="courses.links"
            :from="courses.from"
            :to="courses.to"
            :total="courses.total"
        />
    </template>
</template>
