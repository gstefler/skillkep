<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

// Shown once after an account is created or a password is reset: the
// generated password is displayed here and also emailed to the user.
const page = usePage();
const generated = computed(() => page.flash.generatedPassword);

const dismissed = ref(false);
const copied = ref(false);

watch(generated, () => {
    dismissed.value = false;
    copied.value = false;
});

async function copy(password: string) {
    try {
        await navigator.clipboard.writeText(password);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable: the password stays selectable on screen.
    }
}
</script>

<template>
    <Alert v-if="generated && !dismissed" variant="warning" role="status">
        <KeyRound aria-hidden="true" />
        <AlertTitle class="flex items-start justify-between gap-4">
            Ideiglenes jelszó: {{ generated.name }}
            <Button
                variant="ghost"
                size="icon-sm"
                class="-my-1 -mr-2"
                aria-label="Üzenet bezárása"
                @click="dismissed = true"
            >
                <X />
            </Button>
        </AlertTitle>
        <AlertDescription class="gap-3">
            <p>
                A jelszót most látja utoljára, ezért másolja ki. A felhasználó
                e-mailben is megkapja a belépési adatokat ({{
                    generated.email
                }}).
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <code
                    class="rounded-md border border-border-strong bg-card px-3 py-1.5 font-mono text-sm select-all"
                    >{{ generated.password }}</code
                >
                <Button
                    variant="outline"
                    size="sm"
                    @click="copy(generated.password)"
                >
                    <Check v-if="copied" />
                    <Copy v-else />
                    {{ copied ? 'Másolva' : 'Jelszó másolása' }}
                </Button>
            </div>
        </AlertDescription>
    </Alert>
</template>
