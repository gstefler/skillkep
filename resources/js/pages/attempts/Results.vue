<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { CircleCheck, CircleX } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/courses';
import { show as showModule } from '@/routes/courses/modules';

const props = defineProps<{
    attempt: {
        id: number;
        score: number;
        is_passed: boolean;
        submitted_at: string;
    };
    test: { id: number; title: string; pass_score: number };
    course: { slug: string; title: string };
    module: { id: number; title: string };
    questions: {
        id: number;
        prompt: string;
        points: number;
        is_correct: boolean;
        given: string[];
        expected: string[];
    }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Képzéseim', href: index() },
        { title: props.course.title, href: show(props.course.slug) },
        {
            title: props.module.title,
            href: showModule([props.course.slug, props.module.id]),
        },
    ],
});
</script>

<template>
    <Head :title="`${test.title} – eredmény`" />

    <PageHeader
        :title="`${test.title} – eredmény`"
        :meta="`Beküldve: ${attempt.submitted_at}`"
    />

    <Alert :variant="attempt.is_passed ? 'success' : 'destructive'">
        <CircleCheck v-if="attempt.is_passed" />
        <CircleX v-else />
        <AlertTitle class="text-base">
            {{ attempt.is_passed ? 'Sikeres teszt' : 'Sikertelen teszt' }}:
            <span class="tabular-nums">{{ attempt.score }} %</span>
        </AlertTitle>
        <AlertDescription>
            A sikeres teljesítéshez legalább {{ test.pass_score }} % kell.
            <template v-if="!attempt.is_passed"
                >A tananyag átnézése után újra megpróbálhatja.</template
            >
        </AlertDescription>
    </Alert>

    <ol class="grid gap-3">
        <li
            v-for="(question, position) in questions"
            :key="question.id"
            class="grid gap-2 rounded-lg border border-border bg-card p-(--kep-card-pad)"
        >
            <div class="flex items-start gap-2">
                <CircleCheck
                    v-if="question.is_correct"
                    class="mt-0.5 size-4 shrink-0 text-success"
                    aria-hidden="true"
                />
                <CircleX
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-destructive"
                    aria-hidden="true"
                />
                <p class="font-medium">
                    <span class="text-subtle tabular-nums"
                        >{{ position + 1 }}.</span
                    >
                    {{ question.prompt }}
                    <span class="sr-only">{{
                        question.is_correct ? '(helyes)' : '(hibás)'
                    }}</span>
                </p>
            </div>
            <dl class="grid gap-1 pl-6 text-sm sm:grid-cols-[10rem_1fr]">
                <dt class="text-muted-foreground">Az Ön válasza</dt>
                <dd>{{ question.given.join(', ') }}</dd>
                <template v-if="!question.is_correct">
                    <dt class="text-muted-foreground">Helyes válasz</dt>
                    <dd>{{ question.expected.join(', ') }}</dd>
                </template>
            </dl>
        </li>
    </ol>

    <div class="flex flex-wrap gap-3">
        <Button as-child>
            <Link :href="showModule([course.slug, module.id])"
                >Vissza a modulhoz</Link
            >
        </Button>
        <Button variant="outline" as-child>
            <Link :href="show(course.slug)">Vissza a kurzushoz</Link>
        </Button>
    </div>
</template>
