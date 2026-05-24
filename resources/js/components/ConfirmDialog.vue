<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    confirmVariant?: 'default' | 'destructive' | 'secondary' | 'outline' | 'ghost' | 'link';
    loading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button type="button" variant="secondary" @click="emit('update:open', false)">
                    {{ cancelText ?? 'Annuleren' }}
                </Button>
                <Button type="button" :variant="confirmVariant ?? 'destructive'" :disabled="loading" @click="emit('confirm')">
                    {{ loading ? 'Bezig...' : (confirmText ?? 'Bevestigen') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
