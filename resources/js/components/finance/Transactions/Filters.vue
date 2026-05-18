<script setup lang="ts">
import { useFilters } from '@/composables/finance/Transactions/useFilters';
import { PiggyBank, Euro, Wallet } from 'lucide-vue-next';
import { type TransactionFilter } from '@/types';

const props = defineProps<{
    isLoading?: boolean;
}>();

const model = defineModel<TransactionFilter>('activeFilter', {
    required: true,
});
const emit = defineEmits<{
    change: [value: TransactionFilter];
}>();

const { activeFilter, setActiveFilter } = useFilters();

const tabs = [
    { value: 'all', Icon: null, label: 'Alles' },
    { value: 'expenses', Icon: Wallet, label: 'Uitgaven' },
    { value: 'income', Icon: Euro, label: 'Inkomsten' },
    { value: 'savings', Icon: PiggyBank, label: 'Sparen' },
] as const;

function selectFilter(filter: TransactionFilter) {
    if (!props.isLoading) {
        setActiveFilter(filter);
        model.value = filter;
        emit('change', filter);
    }
}
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-gray-300 p-1 dark:bg-gray-900">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="selectFilter(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                isLoading ? 'cursor-not-allowed opacity-70' : '',
                activeFilter === value
                    ? 'bg-white shadow-xs dark:bg-gray-700 dark:text-gray-100'
                    : 'text-gray-500 hover:bg-gray-200/60 hover:text-black dark:text-gray-400 dark:hover:bg-gray-700/60',
            ]"
        >
            <component v-if="Icon" :is="Icon" class="mr-1 h-4 w-4" />
            <span class="text-sm">{{ label }}</span>
        </button>
    </div>
</template>
