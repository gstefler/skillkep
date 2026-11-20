<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, CircleCheck } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { index, show } from '@/routes/courses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Képzéseim', href: index() }],
    },
});

type CourseCard = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    modules_count: number;
    tests_count: number;
    passed_count: number;
    percent: number;
    completed: boolean;
    enrolled_at: string | null;
};

defineProps<{ courses: CourseCard[] }>();

const percent = (value: number) =>
    `${value.toLocaleString('hu-HU', { maximumFractionDigits: 1 })} %`;
</script>

<template>
    <Head title="Képzéseim" />

    <PageHeader
        title="Képzéseim"
        description="Azok a képzések, amelyekhez a szervezete hozzáférést adott."
    />

    <EmptyState
        v-if="courses.length === 0"
        :icon="BookOpen"
        title="Még nincs hozzárendelt képzés"
        description="Amint a szervezete képzést rendel Önhöz, itt jelenik meg."
    />

    <ul v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <li v-for="course in courses" :key="course.id">
            <Link
                :href="show(course.slug)"
                class="grid h-full content-start gap-3 rounded-lg border border-border bg-card p-(--kep-card-pad) transition-colors hover:border-border-strong focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-base font-semibold">{{ course.title }}</h2>
                    <Badge v-if="course.completed" variant="achievement"
                        ><CircleCheck /> Teljesítve</Badge
                    >
                </div>
                <p
                    v-if="course.description"
                    class="line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ course.description }}
                </p>
                <p class="text-xs text-subtle tabular-nums">
                    {{ course.modules_count }} modul
                    <template v-if="course.enrolled_at">
                        · hozzáférés: {{ course.enrolled_at }}</template
                    >
                </p>
                <div v-if="course.tests_count > 0" class="grid gap-1.5">
                    <div
                        class="flex justify-between text-xs text-muted-foreground tabular-nums"
                    >
                        <span
                            >{{ course.passed_count }} /
                            {{ course.tests_count }} teszt teljesítve</span
                        >
                        <span>{{ percent(course.percent) }}</span>
                    </div>
                    <ProgressBar
                        :value="course.percent"
                        :tone="course.completed ? 'success' : 'primary'"
                        :label="`${course.title} haladás`"
                    />
                </div>
            </Link>
        </li>
    </ul>
</template>
