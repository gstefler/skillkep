<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

// Confirmation dialog for destructive or irreversible actions. The trigger
// goes in the default slot; the request is sent as a Wayfinder action.
const props = defineProps<{
    title: string;
    description: string;
    confirmLabel: string;
    action: {
        url: string;
        method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    };
    variant?: 'default' | 'destructive';
}>();

const open = ref(false);
const processing = ref(false);

function confirm() {
    router.visit(props.action.url, {
        method: props.action.method,
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Mégse</Button>
                <Button
                    :variant="variant ?? 'destructive'"
                    :disabled="processing"
                    @click="confirm"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
