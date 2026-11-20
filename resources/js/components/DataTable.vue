<script setup lang="ts">
// Data table (design-system .kep-table inside .kep-table-wrap). Use plain
// <thead>/<tbody>/<tr>/<th>/<td> in the slot; add class "is-num" to numeric cells.
</script>

<template>
    <div class="kep-table-wrap">
        <table class="kep-table">
            <slot />
        </table>
    </div>
</template>
