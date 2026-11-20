<script setup lang="ts">
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    Building2,
    CircleAlert,
    Eye,
    FileText,
    ListChecks,
    Lock,
    Search,
    Users,
    Video,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmAction from '@/components/ConfirmAction.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBytes, formatDuration } from '@/composables/useChunkedUpload';
import { dashboard } from '@/routes';
import { show as preview } from '@/routes/courses';
import {
    destroy,
    index,
    publish,
    sequentialModules,
    show,
    update,
} from '@/routes/manage/courses';
import { update as updateCompanies } from '@/routes/manage/courses/companies';
import { update as updateLearners } from '@/routes/manage/courses/learners';
import { transcode } from '@/routes/manage/materials';

type Material = {
    id: number;
    title: string;
    type: 'pdf' | 'video';
    size: number;
    video_status: 'pending' | 'processing' | 'ready' | 'failed' | null;
    video_status_label: string | null;
    video_error: string | null;
    duration_seconds: number | null;
};

type Module = {
    id: number;
    title: string;
    prerequisite: string | null;
    test: { questions_count: number; pass_score: number } | null;
    materials: Material[];
};

type CompanyOption = {
    id: number;
    name: string;
    learners_count: number;
    enrolled_count: number;
    assigned: boolean;
};

type LearnerOption = {
    id: number;
    name: string;
    email: string;
    company: { id: number; name: string } | null;
    enrolled: boolean;
};

const props = defineProps<{
    course: {
        id: number;
        title: string;
        slug: string;
        description: string | null;
        is_published: boolean;
        sequential_modules: boolean;
        created_at: string;
    };
    modules: Module[];
    companies: CompanyOption[];
    learners: LearnerOption[];
    canAdminister: boolean;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Irányítópult', href: dashboard() },
        { title: 'Képzések', href: index() },
        { title: props.course.title, href: show(props.course.id) },
    ],
});

// --- Companies (superadmin) ---------------------------------------------
const selectedCompanies = ref<number[]>([]);
const savingCompanies = ref(false);

watch(
    () => props.companies,
    (companies) => {
        selectedCompanies.value = companies
            .filter((c) => c.assigned)
            .map((c) => c.id);
    },
    { immediate: true },
);

const companiesDirty = computed(() => {
    const original = props.companies
        .filter((c) => c.assigned)
        .map((c) => c.id)
        .sort();
    const current = [...selectedCompanies.value].sort();

    return original.join(',') !== current.join(',');
});

const losingAccess = computed(() =>
    props.companies
        .filter((c) => c.assigned && !selectedCompanies.value.includes(c.id))
        .reduce((sum, c) => sum + c.enrolled_count, 0),
);

function saveCompanies(): void {
    router.put(
        updateCompanies.url(props.course.id),
        { company_ids: selectedCompanies.value },
        {
            preserveScroll: true,
            onStart: () => (savingCompanies.value = true),
            onFinish: () => (savingCompanies.value = false),
        },
    );
}

// --- Learners --------------------------------------------------------------
const selectedLearners = ref<number[]>([]);
const learnerQuery = ref('');
const savingLearners = ref(false);
const learnerError = ref<string | null>(null);

watch(
    () => props.learners,
    (learners) => {
        selectedLearners.value = learners
            .filter((l) => l.enrolled)
            .map((l) => l.id);
    },
    { immediate: true },
);

const learnerGroups = computed(() => {
    const term = learnerQuery.value.trim().toLocaleLowerCase('hu');
    const groups = new Map<
        string,
        { key: string; name: string; learners: LearnerOption[] }
    >();

    for (const learner of props.learners) {
        if (
            term &&
            !learner.name.toLocaleLowerCase('hu').includes(term) &&
            !learner.email.toLocaleLowerCase('hu').includes(term)
        ) {
            continue;
        }

        const key = String(learner.company?.id ?? 'none');

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                name: learner.company?.name ?? 'Cég nélkül',
                learners: [],
            });
        }

        groups.get(key)!.learners.push(learner);
    }

    return [...groups.values()];
});

const learnersDirty = computed(() => {
    const original = props.learners
        .filter((l) => l.enrolled)
        .map((l) => l.id)
        .sort();
    const current = [...selectedLearners.value].sort();

    return original.join(',') !== current.join(',');
});

function groupState(learners: LearnerOption[]): boolean | 'indeterminate' {
    const selected = learners.filter((l) =>
        selectedLearners.value.includes(l.id),
    ).length;

    if (selected === 0) {
        return false;
    }

    return selected === learners.length ? true : 'indeterminate';
}

function toggleGroup(learners: LearnerOption[]): void {
    const ids = learners.map((l) => l.id);

    if (groupState(learners) === true) {
        selectedLearners.value = selectedLearners.value.filter(
            (id) => !ids.includes(id),
        );
    } else {
        selectedLearners.value = [
            ...new Set([...selectedLearners.value, ...ids]),
        ];
    }
}

function saveLearners(): void {
    learnerError.value = null;
    router.put(
        updateLearners.url(props.course.id),
        { learner_ids: selectedLearners.value },
        {
            preserveScroll: true,
            onStart: () => (savingLearners.value = true),
            onFinish: () => (savingLearners.value = false),
            onError: (errors) =>
                (learnerError.value =
                    errors.learner_ids ?? Object.values(errors)[0] ?? null),
        },
    );
}

function toggleSequential(): void {
    router.patch(
        sequentialModules.url(props.course.id),
        {},
        { preserveScroll: true },
    );
}

const enrolledCount = computed(() => selectedLearners.value.length);
// An admin only sees courses available to the own company.
const hasCompanies = computed(
    () => !props.canAdminister || props.companies.some((c) => c.assigned),
);

const videoBadge = {
    pending: 'secondary',
    processing: 'info',
    ready: 'success',
    failed: 'destructive',
} as const;
</script>

<template>
    <Head :title="course.title" />

    <PageHeader :title="course.title" :description="course.description ?? ''">
        <template #actions>
            <Badge :variant="course.is_published ? 'success' : 'secondary'">{{
                course.is_published ? 'Közzétéve' : 'Piszkozat'
            }}</Badge>
            <Button variant="outline" as-child>
                <Link :href="preview(course.slug)"><Eye /> Tanulói nézet</Link>
            </Button>
            <template v-if="canAdminister">
                <ConfirmAction
                    :title="
                        course.is_published
                            ? 'Kurzus visszavonása'
                            : 'Kurzus közzététele'
                    "
                    :description="
                        course.is_published
                            ? 'A beiratkozott tanulók nem látják többé a kurzust. Az eredményeik megmaradnak.'
                            : 'A beiratkozott tanulók azonnal látják a kurzust.'
                    "
                    :confirm-label="
                        course.is_published
                            ? 'Kurzus visszavonása'
                            : 'Kurzus közzététele'
                    "
                    variant="default"
                    :action="publish(course.id)"
                >
                    <Button>{{
                        course.is_published
                            ? 'Kurzus visszavonása'
                            : 'Kurzus közzététele'
                    }}</Button>
                </ConfirmAction>
                <ConfirmAction
                    :title="`${course.title} törlése`"
                    description="A kurzus, a tananyagai, a videói és a tanulók eredményei véglegesen törlődnek. Ez nem vonható vissza."
                    confirm-label="Kurzus törlése"
                    :action="destroy(course.id)"
                >
                    <Button variant="destructive">Kurzus törlése</Button>
                </ConfirmAction>
            </template>
        </template>
    </PageHeader>

    <!-- Course data and module order (superadmin) -->
    <section
        v-if="canAdminister"
        class="grid gap-5 rounded-lg border border-border bg-card p-(--kep-card-pad)"
        aria-labelledby="course-settings"
    >
        <h2 id="course-settings" class="text-lg font-semibold">Beállítások</h2>
        <Form
            v-bind="update.form(course.id)"
            v-slot="{ errors, processing }"
            class="grid max-w-2xl gap-4"
            :options="{ preserveScroll: true }"
        >
            <div class="grid gap-2">
                <Label for="title">Cím</Label>
                <Input
                    id="title"
                    name="title"
                    required
                    :default-value="course.title"
                />
                <InputError :message="errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="description">Leírás</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="kep-textarea"
                    :value="course.description ?? ''"
                />
                <InputError :message="errors.description" />
            </div>
            <div>
                <Button type="submit" variant="outline" :disabled="processing"
                    >Adatok mentése</Button
                >
            </div>
        </Form>
        <label class="kep-switch">
            <input
                type="checkbox"
                :checked="course.sequential_modules"
                @change="toggleSequential"
            />
            Sorrendben teljesítendő modulok (a következő modul az előző teszt
            teljesítése után nyílik meg)
        </label>
    </section>

    <!-- Companies (superadmin) -->
    <section
        v-if="canAdminister"
        class="grid gap-3"
        aria-labelledby="course-companies"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="course-companies" class="text-lg font-semibold">
                    Cégek
                </h2>
                <p class="text-sm text-muted-foreground">
                    A kurzus a kijelölt cégek számára válik elérhetővé. Ettől
                    még senki nem kap hozzáférést: a tanulókat lent kell
                    kijelölni.
                </p>
            </div>
            <Button
                :disabled="!companiesDirty || savingCompanies"
                @click="saveCompanies"
                >Cégek mentése</Button
            >
        </div>

        <EmptyState
            v-if="companies.length === 0"
            :icon="Building2"
            title="Még nincs cég"
            description="Vegyen fel egy céget, hogy hozzá rendelhesse a kurzust."
        />
        <div
            v-else
            class="grid gap-2 rounded-lg border border-border bg-card p-(--kep-card-pad) sm:grid-cols-2 lg:grid-cols-3"
        >
            <label
                v-for="company in companies"
                :key="company.id"
                class="kep-check min-h-11 items-center"
            >
                <input
                    v-model="selectedCompanies"
                    type="checkbox"
                    :value="company.id"
                />
                <span>
                    {{ company.name }}
                    <span
                        class="block text-xs text-muted-foreground tabular-nums"
                        >{{ company.learners_count }} tanuló<template
                            v-if="company.enrolled_count > 0"
                            >, ebből
                            {{ company.enrolled_count }} beiratkozott</template
                        ></span
                    >
                </span>
            </label>
        </div>
        <Alert v-if="losingAccess > 0" variant="warning">
            <CircleAlert />
            <AlertDescription>
                Mentés után {{ losingAccess }} tanuló hozzáférése megszűnik,
                mert a cégéről lekerül a kurzus. Az eredményeik megmaradnak.
            </AlertDescription>
        </Alert>
    </section>

    <!-- Learners -->
    <section class="grid gap-3" aria-labelledby="course-learners">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="course-learners" class="text-lg font-semibold">
                    Tanulók
                    <span class="text-sm font-normal text-subtle tabular-nums"
                        >({{ enrolledCount }} beiratkozott)</span
                    >
                </h2>
                <p class="text-sm text-muted-foreground">
                    Csak a kijelölt tanulók végezhetik el a kurzust.
                    <template v-if="!course.is_published"
                        >Amíg a kurzus nincs közzétéve, ők sem látják.</template
                    >
                </p>
            </div>
            <Button
                :disabled="!learnersDirty || savingLearners"
                @click="saveLearners"
                >Hozzáférés mentése</Button
            >
        </div>

        <EmptyState
            v-if="!hasCompanies"
            :icon="Users"
            title="A kurzus még egy céghez sincs rendelve"
            description="Előbb rendelje a kurzust legalább egy céghez, utána jelölheti ki a cég tanulóit."
        />
        <EmptyState
            v-else-if="learners.length === 0"
            :icon="Users"
            title="Nincs kijelölhető tanuló"
            description="A kurzushoz rendelt cégekhez még nem tartozik tanuló."
        />
        <template v-else>
            <form
                role="search"
                class="kep-input-group max-w-sm"
                @submit.prevent
            >
                <Search aria-hidden="true" />
                <input
                    v-model="learnerQuery"
                    type="search"
                    class="kep-input"
                    placeholder="Keresés név vagy e-mail-cím szerint"
                    aria-label="Tanulók keresése"
                />
            </form>

            <InputError :message="learnerError ?? undefined" />

            <div
                v-for="group in learnerGroups"
                :key="group.key"
                class="grid gap-2 rounded-lg border border-border bg-card p-(--kep-card-pad)"
            >
                <label class="kep-check min-h-11 items-center font-semibold">
                    <input
                        type="checkbox"
                        :checked="groupState(group.learners) === true"
                        :indeterminate="
                            groupState(group.learners) === 'indeterminate'
                        "
                        @change="toggleGroup(group.learners)"
                    />
                    {{ group.name }}
                    <span class="font-normal text-muted-foreground tabular-nums"
                        >({{ group.learners.length }})</span
                    >
                </label>
                <div class="grid gap-1 pl-6 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="learner in group.learners"
                        :key="learner.id"
                        class="kep-check min-h-11 items-center"
                    >
                        <input
                            v-model="selectedLearners"
                            type="checkbox"
                            :value="learner.id"
                        />
                        <span class="min-w-0">
                            <span class="block truncate">{{
                                learner.name
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{ learner.email }}</span
                            >
                        </span>
                    </label>
                </div>
            </div>
            <p
                v-if="learnerGroups.length === 0"
                class="text-sm text-muted-foreground"
            >
                Nincs a keresésnek megfelelő tanuló.
            </p>
        </template>
    </section>

    <!-- Modules -->
    <section class="grid gap-3" aria-labelledby="course-modules">
        <h2 id="course-modules" class="text-lg font-semibold">
            Modulok
            <span class="text-sm font-normal text-subtle tabular-nums"
                >({{ modules.length }})</span
            >
        </h2>

        <ol class="grid gap-3">
            <li
                v-for="(module, position) in modules"
                :key="module.id"
                class="grid gap-3 rounded-lg border border-border bg-card p-(--kep-card-pad)"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h3 class="font-semibold">
                        <span class="text-subtle tabular-nums"
                            >{{ position + 1 }}.</span
                        >
                        {{ module.title }}
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-if="module.prerequisite" variant="outline"
                            ><Lock /> Feltétel: {{ module.prerequisite }}</Badge
                        >
                        <Badge v-if="module.test" variant="info"
                            ><ListChecks /> Teszt:
                            {{ module.test.questions_count }} kérdés,
                            {{ module.test.pass_score }} %-tól sikeres</Badge
                        >
                    </div>
                </div>
                <ul
                    v-if="module.materials.length > 0"
                    class="grid gap-2 text-sm"
                >
                    <li
                        v-for="material in module.materials"
                        :key="material.id"
                        class="flex flex-wrap items-center gap-x-3 gap-y-1"
                    >
                        <component
                            :is="material.type === 'video' ? Video : FileText"
                            class="size-4 text-subtle"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">{{ material.title }}</span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            {{
                                material.duration_seconds
                                    ? formatDuration(material.duration_seconds)
                                    : formatBytes(material.size)
                            }}
                        </span>
                        <Badge
                            v-if="material.video_status"
                            :variant="videoBadge[material.video_status]"
                            :title="material.video_error ?? undefined"
                            >{{ material.video_status_label }}</Badge
                        >
                        <ConfirmAction
                            v-if="
                                canAdminister &&
                                material.video_status === 'failed'
                            "
                            :title="`${material.title} újrafeldolgozása`"
                            :description="
                                material.video_error ??
                                'A videó feldolgozása újraindul a háttérben.'
                            "
                            confirm-label="Feldolgozás újraindítása"
                            variant="default"
                            :action="transcode(material.id)"
                        >
                            <Button variant="ghost" size="sm"
                                >Feldolgozás újraindítása</Button
                            >
                        </ConfirmAction>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Ebben a modulban nincs tananyag.
                </p>
            </li>
        </ol>
    </section>
</template>
