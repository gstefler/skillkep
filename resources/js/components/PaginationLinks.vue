<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Pagination (design-system .kep-pagination) for a Laravel paginator payload.
type PageLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
}>();

// First and last entries are "previous" / "next"; the rest are page numbers.
const pages = computed(() => props.links.slice(1, -1));
const previous = computed(() => props.links[0]);
const next = computed(() => props.links[props.links.length - 1]);
</script>

<template>
    <nav v-if="pages.length > 1" class="kep-pagination" aria-label="Lapozás">
        <span class="tabular-nums"> {{ from }}–{{ to }} / {{ total }} </span>
        <div class="kep-pagination__pages">
            <component
                :is="previous.url ? Link : 'span'"
                :href="previous.url ?? undefined"
                class="kep-pagination__page inline-grid place-items-center px-2"
                :class="{ 'pointer-events-none opacity-40': !previous.url }"
                aria-label="Előző oldal"
                preserve-scroll
            >
                ‹
            </component>
            <Link
                v-for="page in pages"
                :key="page.label"
                :href="page.url ?? ''"
                class="kep-pagination__page inline-grid place-items-center px-2"
                :aria-current="page.active ? 'page' : undefined"
                preserve-scroll
            >
                {{ page.label }}
            </Link>
            <component
                :is="next.url ? Link : 'span'"
                :href="next.url ?? undefined"
                class="kep-pagination__page inline-grid place-items-center px-2"
                :class="{ 'pointer-events-none opacity-40': !next.url }"
                aria-label="Következő oldal"
                preserve-scroll
            >
                ›
            </component>
        </div>
    </nav>
</template>
