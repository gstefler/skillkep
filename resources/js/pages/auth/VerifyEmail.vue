<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'E-mail-cím megerősítése',
        description:
            'Kattintson az e-mailben most elküldött linkre a cím megerősítéséhez.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="E-mail-cím megerősítése" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 rounded-md border border-success-border bg-success-subtle px-3 py-2 text-sm font-medium text-success"
    >
        Új megerősítő linket küldtünk a megadott e-mail-címre during
        registration.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Megerősítő e-mail újraküldése
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Kijelentkezés
        </TextLink>
    </Form>
</template>
