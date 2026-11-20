<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { BookOpen } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as showCompany } from '@/routes/companies';
import { destroy, index, show } from '@/routes/learners';
import { show as showCourse } from '@/routes/manage/courses';
import {
    destroy as unenrol,
    store as enrol,
} from '@/routes/manage/courses/learners';
import { passwordReset } from '@/routes/users';

const props = defineProps<{
    learner: {
        id: number;
        name: string;
        email: string;
        company: { id: number; name: string } | null;
        created_by: string | null;
        created_at: string;
    };
    courses: {
        id: number;
        title: string;
        is_published: boolean;
        enrolled: boolean;
        progress: number | null;
    }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Irányítópult', href: dashboard() },
        { title: 'Tanulók', href: index() },
        { title: props.learner.name, href: show(props.learner.id) },
    ],
});

const isSuperAdmin = computed(
    () => usePage().props.auth.user.role === 'superadmin',
);
</script>

<template>
    <Head :title="learner.name" />

    <PageHeader :title="learner.name" :description="learner.email">
        <template #actions>
            <ConfirmAction
                :title="`${learner.name} jelszavának visszaállítása`"
                description="Új ideiglenes jelszó készül, amelyet e-mailben is megkap. A régi jelszó azonnal érvénytelen lesz."
                confirm-label="Jelszó visszaállítása"
                variant="default"
                :action="passwordReset(learner.id)"
            >
                <Button variant="outline">Jelszó visszaállítása</Button>
            </ConfirmAction>
            <ConfirmAction
                :title="`${learner.name} törlése`"
                description="A fiók véglegesen törlődik, ez nem vonható vissza."
                confirm-label="Tanuló törlése"
                :action="destroy(learner.id)"
            >
                <Button variant="destructive">Tanuló törlése</Button>
            </ConfirmAction>
        </template>
    </PageHeader>

    <section
        class="rounded-lg border border-border bg-card p-(--kep-card-pad)"
        aria-labelledby="learner-details"
    >
        <h2 id="learner-details" class="mb-4 text-lg font-semibold">Adatok</h2>
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div class="grid gap-1">
                <dt class="text-xs font-medium text-subtle">Cég</dt>
                <dd>
                    <Link
                        v-if="learner.company && isSuperAdmin"
                        :href="showCompany(learner.company.id)"
                        class="text-link hover:underline"
                        >{{ learner.company.name }}</Link
                    >
                    <template v-else>{{
                        learner.company?.name ?? '–'
                    }}</template>
                </dd>
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-medium text-subtle">Felvette</dt>
                <dd>{{ learner.created_by ?? '–' }}</dd>
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-medium text-subtle">Felvéve</dt>
                <dd class="tabular-nums">{{ learner.created_at }}</dd>
            </div>
        </dl>
    </section>

    <section class="grid gap-3" aria-labelledby="learner-courses">
        <div>
            <h2 id="learner-courses" class="text-lg font-semibold">Képzések</h2>
            <p class="text-sm text-muted-foreground">
                A tanuló cégéhez rendelt kurzusok. Hozzáférést csak az kap,
                akinek itt vagy a kurzus oldalán megadják.
            </p>
        </div>
        <EmptyState
            v-if="courses.length === 0"
            :icon="BookOpen"
            title="A tanuló cégéhez még nincs kurzus rendelve"
            description="Amint egy kurzus elérhető a cég számára, itt adhat hozzá hozzáférést."
        />
        <DataTable v-else>
            <thead>
                <tr>
                    <th>Kurzus</th>
                    <th>Hozzáférés</th>
                    <th>Haladás</th>
                    <th><span class="sr-only">Műveletek</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="course in courses" :key="course.id">
                    <td class="font-medium">
                        <Link
                            :href="showCourse(course.id)"
                            class="text-link hover:underline"
                            >{{ course.title }}</Link
                        >
                        <Badge
                            v-if="!course.is_published"
                            variant="secondary"
                            class="ml-2"
                            >Piszkozat</Badge
                        >
                    </td>
                    <td>
                        <Badge
                            :variant="course.enrolled ? 'success' : 'outline'"
                            >{{ course.enrolled ? 'Van' : 'Nincs' }}</Badge
                        >
                    </td>
                    <td class="min-w-40">
                        <div
                            v-if="course.progress !== null"
                            class="flex items-center gap-2"
                        >
                            <ProgressBar
                                :value="course.progress"
                                :label="`${course.title} haladás`"
                            />
                            <span class="text-xs tabular-nums"
                                >{{
                                    course.progress.toLocaleString('hu-HU')
                                }}
                                %</span
                            >
                        </div>
                        <span v-else class="text-subtle">–</span>
                    </td>
                    <td>
                        <div class="flex justify-end">
                            <ConfirmAction
                                v-if="course.enrolled"
                                :title="`Hozzáférés megszüntetése: ${course.title}`"
                                :description="`${learner.name} nem éri el többé a kurzust. Az eredményei megmaradnak.`"
                                confirm-label="Hozzáférés megszüntetése"
                                :action="
                                    unenrol({
                                        course: course.id,
                                        user: learner.id,
                                    })
                                "
                            >
                                <Button variant="ghost" size="sm"
                                    >Hozzáférés megszüntetése</Button
                                >
                            </ConfirmAction>
                            <ConfirmAction
                                v-else
                                :title="`Hozzáférés megadása: ${course.title}`"
                                :description="`${learner.name} elérheti és elvégezheti a kurzust${course.is_published ? '.' : ', amint közzéteszik.'}`"
                                confirm-label="Hozzáférés megadása"
                                variant="default"
                                :action="
                                    enrol({
                                        course: course.id,
                                        user: learner.id,
                                    })
                                "
                            >
                                <Button variant="outline" size="sm"
                                    >Hozzáférés megadása</Button
                                >
                            </ConfirmAction>
                        </div>
                    </td>
                </tr>
            </tbody>
        </DataTable>
    </section>
</template>
