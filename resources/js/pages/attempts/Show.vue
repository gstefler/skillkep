<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { formatDuration } from '@/composables/useChunkedUpload';
import { jsonHeaders } from '@/lib/http';
import { answer, finish } from '@/routes/attempts';
import { index, show } from '@/routes/courses';
import { show as showModule } from '@/routes/courses/modules';

type Question = {
    id: number;
    type: 'true_false' | 'write' | 'select_one' | 'select_multiple' | 'pair';
    prompt: string;
    points: number;
    options: { id: number; label: string }[];
    matches: string[];
    answer: string | null;
};

const props = defineProps<{
    attempt: { id: number; expires_at: string | null };
    test: { title: string; pass_score: number };
    course: { slug: string; title: string };
    module: { id: number; title: string };
    questions: Question[];
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

// Answers in their stored string form (see GradingService).
const answers = reactive<Record<number, string | null>>(
    Object.fromEntries(props.questions.map((q) => [q.id, q.answer])),
);

function parseList<T>(value: string | null): T[] {
    try {
        const parsed = JSON.parse(value ?? '[]');

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function selectedOptions(question: Question): number[] {
    return parseList<number>(answers[question.id]).map(Number);
}

function toggleOption(question: Question, optionId: number): void {
    const current = selectedOptions(question);
    const next = current.includes(optionId)
        ? current.filter((id) => id !== optionId)
        : [...current, optionId];
    save(question, JSON.stringify(next));
}

function pairValue(question: Question, optionId: number): string {
    const pair = parseList<[number, string]>(answers[question.id]).find(
        ([id]) => Number(id) === optionId,
    );

    return pair?.[1] ?? '';
}

function setPair(question: Question, optionId: number, match: string): void {
    const pairs = parseList<[number, string]>(answers[question.id]).filter(
        ([id]) => Number(id) !== optionId,
    );

    if (match !== '') {
        pairs.push([optionId, match]);
    }

    save(question, JSON.stringify(pairs));
}

// --- Autosave -----------------------------------------------------------
const saveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const pending = new Map<number, ReturnType<typeof setTimeout>>();

function save(question: Question, value: string | null, delay = 0): void {
    answers[question.id] = value;
    clearTimeout(pending.get(question.id));
    pending.set(
        question.id,
        setTimeout(() => send(question.id, value), delay),
    );
}

async function send(questionId: number, value: string | null): Promise<void> {
    pending.delete(questionId);
    saveState.value = 'saving';

    try {
        const response = await fetch(answer.url(props.attempt.id), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question_id: questionId,
                answer_value: value,
            }),
        });

        if (response.status === 409) {
            router.visit(finish.url(props.attempt.id), { method: 'post' });

            return;
        }

        saveState.value = response.ok ? 'saved' : 'error';
    } catch {
        saveState.value = 'error';
    }
}

async function flush(): Promise<void> {
    const waiting = [...pending.keys()];
    waiting.forEach((id) => clearTimeout(pending.get(id)));
    await Promise.all(waiting.map((id) => send(id, answers[id] ?? null)));
}

const answeredCount = computed(
    () =>
        props.questions.filter((q) => {
            const value = answers[q.id];

            return value !== null && value !== '' && value !== '[]';
        }).length,
);

// --- Time limit ----------------------------------------------------------
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;
const expiresAt = props.attempt.expires_at
    ? new Date(props.attempt.expires_at).getTime()
    : null;
const secondsLeft = computed(() =>
    expiresAt === null ? null : Math.max(0, (expiresAt - now.value) / 1000),
);

async function submit(): Promise<void> {
    await flush();
    router.visit(finish.url(props.attempt.id), { method: 'post' });
}

onMounted(() => {
    if (expiresAt === null) {
        return;
    }

    timer = setInterval(() => {
        now.value = Date.now();

        if (secondsLeft.value === 0) {
            clearInterval(timer!);
            submit();
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const saveLabel = computed(
    () =>
        ({
            idle: '',
            saving: 'Mentés…',
            saved: 'Válaszok mentve',
            error: 'A mentés nem sikerült. Ellenőrizze az internetkapcsolatot.',
        })[saveState.value],
);
</script>

<template>
    <Head :title="test.title" />

    <PageHeader
        :title="test.title"
        :description="`${questions.length} kérdés · ${test.pass_score} %-tól sikeres. A válaszai automatikusan mentődnek.`"
    >
        <template v-if="secondsLeft !== null" #actions>
            <span
                class="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2 font-mono text-sm tabular-nums"
                :class="{ 'text-destructive': secondsLeft < 60 }"
                role="timer"
                aria-label="Hátralévő idő"
            >
                <Clock class="size-4" aria-hidden="true" />
                {{ formatDuration(secondsLeft) }}
            </span>
        </template>
    </PageHeader>

    <ol class="grid gap-4">
        <li
            v-for="(question, position) in questions"
            :key="question.id"
            class="rounded-lg border border-border bg-card p-(--kep-card-pad)"
        >
            <fieldset class="grid gap-3">
                <legend class="mb-3 text-base font-medium">
                    <span class="text-subtle tabular-nums"
                        >{{ position + 1 }}.</span
                    >
                    {{ question.prompt }}
                    <span class="text-xs font-normal text-muted-foreground"
                        >({{ question.points }} pont)</span
                    >
                </legend>

                <template v-if="question.type === 'true_false'">
                    <label
                        v-for="choice in [
                            { value: 'true', label: 'Igaz' },
                            { value: 'false', label: 'Hamis' },
                        ]"
                        :key="choice.value"
                        class="kep-check min-h-11 items-center"
                    >
                        <input
                            type="radio"
                            :name="`q${question.id}`"
                            :value="choice.value"
                            :checked="answers[question.id] === choice.value"
                            @change="save(question, choice.value)"
                        />
                        {{ choice.label }}
                    </label>
                </template>

                <template v-else-if="question.type === 'select_one'">
                    <label
                        v-for="option in question.options"
                        :key="option.id"
                        class="kep-check min-h-11 items-center"
                    >
                        <input
                            type="radio"
                            :name="`q${question.id}`"
                            :checked="
                                answers[question.id] === String(option.id)
                            "
                            @change="save(question, String(option.id))"
                        />
                        {{ option.label }}
                    </label>
                </template>

                <template v-else-if="question.type === 'select_multiple'">
                    <p class="text-xs text-muted-foreground">
                        Jelölje be az összes helyes választ.
                    </p>
                    <label
                        v-for="option in question.options"
                        :key="option.id"
                        class="kep-check min-h-11 items-center"
                    >
                        <input
                            type="checkbox"
                            :checked="
                                selectedOptions(question).includes(option.id)
                            "
                            @change="toggleOption(question, option.id)"
                        />
                        {{ option.label }}
                    </label>
                </template>

                <template v-else-if="question.type === 'pair'">
                    <div
                        v-for="option in question.options"
                        :key="option.id"
                        class="grid items-center gap-2 sm:grid-cols-2"
                    >
                        <label :for="`q${question.id}-${option.id}`">{{
                            option.label
                        }}</label>
                        <NativeSelect
                            :id="`q${question.id}-${option.id}`"
                            :model-value="pairValue(question, option.id)"
                            @update:model-value="
                                setPair(
                                    question,
                                    option.id,
                                    String($event ?? ''),
                                )
                            "
                        >
                            <option value="">Válasszon…</option>
                            <option
                                v-for="match in question.matches"
                                :key="match"
                                :value="match"
                            >
                                {{ match }}
                            </option>
                        </NativeSelect>
                    </div>
                </template>

                <template v-else>
                    <label :for="`q${question.id}`" class="sr-only"
                        >Válasz</label
                    >
                    <textarea
                        :id="`q${question.id}`"
                        class="kep-textarea"
                        rows="2"
                        :value="answers[question.id] ?? ''"
                        @input="
                            save(
                                question,
                                ($event.target as HTMLTextAreaElement).value,
                                600,
                            )
                        "
                    />
                </template>
            </fieldset>
        </li>
    </ol>

    <div
        class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-border bg-background py-4"
    >
        <p
            class="text-sm text-muted-foreground tabular-nums"
            aria-live="polite"
        >
            {{ answeredCount }} / {{ questions.length }} kérdés
            megválaszolva<template v-if="saveLabel">
                · {{ saveLabel }}</template
            >
        </p>
        <ConfirmAction
            title="Teszt beküldése"
            :description="
                answeredCount < questions.length
                    ? `${questions.length - answeredCount} kérdésre még nem válaszolt. Beküldés után a válaszok nem módosíthatók.`
                    : 'Beküldés után a válaszok nem módosíthatók.'
            "
            confirm-label="Teszt beküldése"
            variant="default"
            :action="finish(attempt.id)"
        >
            <Button @click="flush">Teszt beküldése</Button>
        </ConfirmAction>
    </div>
</template>
