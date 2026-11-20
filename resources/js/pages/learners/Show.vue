<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { BookOpen } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as showCompany } from '@/routes/companies';
import { destroy, index, show } from '@/routes/learners';
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
        <h2 id="learner-courses" class="text-lg font-semibold">Képzések</h2>
        <EmptyState
            :icon="BookOpen"
            title="Még nincs hozzárendelt képzés"
            description="A képzések hozzárendelése később érhető el."
        />
    </section>
</template>
