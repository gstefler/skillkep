<script setup lang="ts">
import { TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

// KPI tile (design-system .kep-stat): label, big tabular figure, optional unit and change.
const props = defineProps<{
    label: string;
    value: string | number;
    unit?: string;
    /** Change vs. the previous period, already formatted, e.g. "+4,2 %". */
    delta?: string;
    /** Whether the change is good news; colours the delta. */
    trend?: 'up' | 'down';
    hint?: string;
}>();

const trendClass = computed(() =>
    props.trend === 'up'
        ? 'text-success'
        : props.trend === 'down'
          ? 'text-destructive'
          : 'text-subtle',
);
</script>

<template>
    <div
        class="grid gap-1 rounded-lg border border-border bg-card p-(--kep-card-pad)"
    >
        <span class="text-xs font-medium text-subtle">{{ label }}</span>
        <span
            class="text-3xl leading-[1.1] font-semibold tracking-tight tabular-nums"
        >
            {{ value
            }}<span
                v-if="unit"
                class="ml-0.5 text-lg font-medium text-subtle"
                >{{ unit }}</span
            >
        </span>
        <span
            v-if="delta || hint"
            class="flex items-center gap-2 text-xs tabular-nums"
        >
            <span
                v-if="delta"
                :class="[
                    'inline-flex items-center gap-0.5 font-medium',
                    trendClass,
                ]"
            >
                <TrendingUp
                    v-if="trend === 'up'"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <TrendingDown
                    v-else-if="trend === 'down'"
                    class="size-3.5"
                    aria-hidden="true"
                />
                {{ delta }}
            </span>
            <span v-if="hint" class="text-subtle">{{ hint }}</span>
        </span>
    </div>
</template>
