<script setup lang="ts">
interface PaginationData {
    current_page?: number;
    last_page?: number;
    total?: number;
    from?: number;
    to?: number;
}

interface Props {
    pagination: PaginationData;
    itemLabel: string;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'previous'): void;
    (e: 'next'): void;
}>();
</script>

<template>
    <div class="rounded-b-md flex items-center justify-between border-t border-slate-900 bg-muted px-4 py-3 text-sm text-muted-foreground">
        <div>
            Toon {{ pagination?.from ?? 0 }} - {{ pagination?.to ?? 0 }} van {{ pagination?.total ?? 0 }} {{ itemLabel }}
        </div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="(pagination?.current_page ?? 1) <= 1"
                @click="emit('previous')"
            >
                Vorige
            </button>
            <button
                type="button"
                class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="(pagination?.current_page ?? 1) >= (pagination?.last_page ?? 1)"
                @click="emit('next')"
            >
                Volgende
            </button>
        </div>
    </div>
</template>
