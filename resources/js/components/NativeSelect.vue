<script setup lang="ts">
// Native <select> in the design-system look (.kep-select). Native on purpose:
// it submits with Inertia's <Form> and works on every touch device.
defineOptions({ inheritAttrs: false });

const model = defineModel<string | number | null>();
</script>

<template>
    <select v-model="model" class="kep-select" v-bind="$attrs">
        <slot />
    </select>
</template>
