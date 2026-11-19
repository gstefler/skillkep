<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(
    defineProps<{
        /** Second line under the product name, e.g. the tenant (company) name. */
        subtitle?: string;
    }>(),
    { subtitle: 'Vállalati tanulási portál' },
);

const name = usePage().props.name;
</script>

<template>
    <AppLogoIcon />
    <div class="grid flex-1 text-left leading-tight">
        <span
            class="truncate text-[0.9375rem] font-semibold tracking-tight text-foreground"
        >
            {{ name }}
        </span>
        <span v-if="subtitle" class="truncate text-2xs text-subtle">
            {{ subtitle }}
        </span>
    </div>
</template>
