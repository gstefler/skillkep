<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Shared by "Cég felvétele" and "Cég szerkesztése".
defineProps<{
    form: { action: string; method: 'get' | 'post' };
    submitLabel: string;
    cancelHref: string;
    company?: {
        name: string;
        email: string;
        vat_id: string | null;
        phone: string | null;
    };
}>();
</script>

<template>
    <Form
        v-bind="form"
        v-slot="{ errors, processing }"
        class="grid max-w-xl gap-5"
    >
        <div class="grid gap-2">
            <Label for="name">Cég neve</Label>
            <Input
                id="name"
                name="name"
                required
                v-focus
                :default-value="company?.name"
                autocomplete="organization"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Kapcsolattartó e-mail-címe</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                :default-value="company?.email"
                placeholder="iroda@ceg.hu"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="vat_id">Adószám</Label>
                <Input
                    id="vat_id"
                    name="vat_id"
                    :default-value="company?.vat_id ?? ''"
                    placeholder="12345678-2-41"
                    class="font-mono"
                />
                <InputError :message="errors.vat_id" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Telefonszám</Label>
                <Input
                    id="phone"
                    type="tel"
                    name="phone"
                    :default-value="company?.phone ?? ''"
                    placeholder="+36 1 234 5678"
                />
                <InputError :message="errors.phone" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing">{{
                submitLabel
            }}</Button>
            <Button variant="outline" as-child>
                <Link :href="cancelHref">Mégse</Link>
            </Button>
        </div>
    </Form>
</template>
