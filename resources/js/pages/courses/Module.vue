<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleCheck,
    Clock,
    Download,
    ExternalLink,
    FileText,
    ListChecks,
    Lock,
} from '@lucide/vue';
import HlsVideo from '@/components/HlsVideo.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDuration } from '@/composables/useChunkedUpload';
import { results, show as showAttempt, store } from '@/routes/attempts';
import { index, show } from '@/routes/courses';
import { show as showModule } from '@/routes/courses/modules';
import { pdf, playlist } from '@/routes/materials';

type Material = {
    id: number;
    title: string;
    type: 'pdf' | 'video';
    playable: boolean;
    video_status: string | null;
    duration_seconds: number | null;
};

const props = defineProps<{
    course: { id: number; slug: string; title: string };
    module: {
        id: number;
        title: string;
        description: string | null;
        passed: boolean;
    };
    materials: Material[];
    test: {
        id: number;
        title: string;
        questions_count: number;
        pass_score: number;
        time_limit_minutes: number | null;
        active_attempt_id: number | null;
        attempts: {
            id: number;
            score: number | null;
            is_passed: boolean | null;
            submitted_at: string | null;
        }[];
    } | null;
    next: { id: number; title: string; locked: boolean } | null;
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
    <Head :title="module.title" />

    <PageHeader
        :title="module.title"
        :description="module.description ?? course.title"
    >
        <template v-if="module.passed" #actions>
            <Badge variant="success"><CircleCheck /> Teljesítve</Badge>
        </template>
    </PageHeader>

    <section class="grid gap-4" aria-labelledby="module-materials">
        <h2 id="module-materials" class="text-lg font-semibold">Tananyag</h2>
        <p v-if="materials.length === 0" class="text-sm text-muted-foreground">
            Ebben a modulban nincs tananyag.
        </p>

        <article
            v-for="material in materials"
            :key="material.id"
            class="grid gap-3 rounded-lg border border-border bg-card p-(--kep-card-pad)"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="flex items-center gap-2 font-semibold">
                    <FileText
                        v-if="material.type === 'pdf'"
                        class="size-4 text-subtle"
                        aria-hidden="true"
                    />
                    {{ material.title }}
                    <span
                        v-if="material.duration_seconds"
                        class="text-xs font-normal text-muted-foreground tabular-nums"
                        >{{ formatDuration(material.duration_seconds) }}</span
                    >
                </h3>
                <div v-if="material.type === 'pdf'" class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="pdf.url(material.id)"
                            target="_blank"
                            rel="noopener"
                            ><ExternalLink /> Megnyitás új lapon</a
                        >
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <a
                            :href="
                                pdf.url(material.id, {
                                    query: { download: 1 },
                                })
                            "
                            ><Download /> Letöltés</a
                        >
                    </Button>
                </div>
            </div>

            <HlsVideo
                v-if="material.type === 'video' && material.playable"
                :src="playlist.url(material.id)"
                :title="material.title"
                :duration="material.duration_seconds"
            />
            <p
                v-else-if="material.type === 'video'"
                class="flex items-center gap-2 rounded-md bg-muted px-4 py-6 text-sm text-muted-foreground"
            >
                <Clock class="size-4" aria-hidden="true" />
                A videó feldolgozása folyamatban van, hamarosan megtekinthető.
            </p>
        </article>
    </section>

    <section
        v-if="test"
        class="grid gap-4 rounded-lg border border-border bg-card p-(--kep-card-pad)"
        aria-labelledby="module-test"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <h2
                    id="module-test"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <ListChecks class="size-5 text-subtle" aria-hidden="true" />
                    {{ test.title }}
                </h2>
                <p class="text-sm text-muted-foreground tabular-nums">
                    {{ test.questions_count }} kérdés ·
                    {{ test.pass_score }} %-tól sikeres<template
                        v-if="test.time_limit_minutes"
                    >
                        · időkeret: {{ test.time_limit_minutes }} perc</template
                    >
                </p>
            </div>
            <Button v-if="test.active_attempt_id" as-child>
                <Link :href="showAttempt(test.active_attempt_id)"
                    >Teszt folytatása</Link
                >
            </Button>
            <Form v-else v-bind="store.form(test.id)" v-slot="{ processing }">
                <Button type="submit" :disabled="processing">{{
                    test.attempts.length > 0
                        ? 'Teszt újrakezdése'
                        : 'Teszt indítása'
                }}</Button>
            </Form>
        </div>

        <div v-if="test.attempts.length > 0" class="grid gap-2">
            <h3 class="text-sm font-semibold">Korábbi kitöltések</h3>
            <ul class="grid gap-1 text-sm">
                <li
                    v-for="attempt in test.attempts"
                    :key="attempt.id"
                    class="flex flex-wrap items-center gap-3"
                >
                    <span class="text-muted-foreground tabular-nums">{{
                        attempt.submitted_at
                    }}</span>
                    <Badge
                        :variant="attempt.is_passed ? 'success' : 'destructive'"
                        >{{ attempt.is_passed ? 'Sikeres' : 'Sikertelen' }} ·
                        {{ attempt.score }} %</Badge
                    >
                    <Link
                        :href="results(attempt.id)"
                        class="text-link hover:underline"
                        >Eredmény megtekintése</Link
                    >
                </li>
            </ul>
        </div>
    </section>

    <div v-if="next" class="flex justify-end">
        <Button v-if="!next.locked" variant="outline" as-child>
            <Link :href="showModule([course.slug, next.id])"
                >Következő modul: {{ next.title }} <ArrowRight
            /></Link>
        </Button>
        <p v-else class="flex items-center gap-2 text-sm text-muted-foreground">
            <Lock class="size-4" aria-hidden="true" /> A következő modul ({{
                next.title
            }}) a teszt teljesítése után nyílik meg.
        </p>
    </div>
</template>
