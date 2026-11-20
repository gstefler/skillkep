<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { CircleCheck, FileText, Lock } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { index, show } from '@/routes/courses';
import { show as showModule } from '@/routes/courses/modules';

type ModuleRow = {
    id: number;
    title: string;
    materials_count: number;
    has_test: boolean;
    passed: boolean;
    best_score: number | null;
    locked: boolean;
    prerequisite: string | null;
};

const props = defineProps<{
    course: {
        id: number;
        slug: string;
        title: string;
        description: string | null;
        sequential_modules: boolean;
    };
    progress: {
        percent: number;
        passed: number;
        tests: number;
        completed: boolean;
    };
    modules: ModuleRow[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Képzéseim', href: index() },
        { title: props.course.title, href: show(props.course.slug) },
    ],
});

const percent = (value: number) =>
    `${value.toLocaleString('hu-HU', { maximumFractionDigits: 1 })} %`;
</script>

<template>
    <Head :title="course.title" />

    <PageHeader
        :title="course.title"
        :description="course.description ?? undefined"
    />

    <section
        v-if="progress.tests > 0"
        class="grid gap-2 rounded-lg border border-border bg-card p-(--kep-card-pad)"
        aria-labelledby="course-progress"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="course-progress" class="text-sm font-semibold">Haladás</h2>
            <p class="text-sm text-muted-foreground tabular-nums">
                {{ progress.passed }} / {{ progress.tests }} teszt teljesítve ·
                {{ percent(progress.percent) }}
            </p>
        </div>
        <ProgressBar
            :value="progress.percent"
            size="lg"
            :tone="progress.completed ? 'success' : 'primary'"
            label="Haladás a kurzusban"
        />
        <p
            v-if="progress.completed"
            class="flex items-center gap-2 text-sm font-medium text-success"
        >
            <CircleCheck class="size-4" aria-hidden="true" /> Minden tesztet
            teljesített.
        </p>
    </section>

    <section class="grid gap-3" aria-labelledby="course-modules">
        <h2 id="course-modules" class="text-lg font-semibold">Modulok</h2>
        <ol class="grid gap-2">
            <li v-for="(module, position) in modules" :key="module.id">
                <component
                    :is="module.locked ? 'div' : Link"
                    :href="
                        module.locked
                            ? undefined
                            : showModule([course.slug, module.id])
                    "
                    :aria-disabled="module.locked || undefined"
                    :class="[
                        'flex min-h-14 flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-border bg-card px-(--kep-card-pad) py-3',
                        module.locked
                            ? 'text-muted-foreground'
                            : 'transition-colors hover:border-border-strong focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                    ]"
                >
                    <span
                        class="grid size-8 shrink-0 place-items-center rounded-full bg-muted text-sm font-semibold tabular-nums"
                        aria-hidden="true"
                    >
                        <Lock v-if="module.locked" class="size-4" />
                        <CircleCheck
                            v-else-if="module.passed"
                            class="size-4 text-success"
                        />
                        <template v-else>{{ position + 1 }}</template>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{
                            module.title
                        }}</span>
                        <span class="block text-xs text-muted-foreground">
                            <template v-if="module.locked"
                                >Akkor nyílik meg, ha teljesíti ezt:
                                {{ module.prerequisite }}</template
                            >
                            <template v-else>
                                <FileText
                                    class="inline size-3"
                                    aria-hidden="true"
                                />
                                {{ module.materials_count }} tananyag<template
                                    v-if="module.has_test"
                                    >, teszt</template
                                >
                            </template>
                        </span>
                    </span>
                    <Badge v-if="module.locked" variant="secondary"
                        ><Lock /> Zárolva</Badge
                    >
                    <Badge v-else-if="module.passed" variant="success"
                        ><CircleCheck /> Teljesítve<template
                            v-if="module.best_score !== null"
                        >
                            · {{ module.best_score }} %</template
                        ></Badge
                    >
                    <Badge
                        v-else-if="module.best_score !== null"
                        variant="warning"
                        >Legjobb eredmény: {{ module.best_score }} %</Badge
                    >
                </component>
            </li>
        </ol>
    </section>
</template>
