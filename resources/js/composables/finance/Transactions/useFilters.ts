import { onMounted, ref, defineEmits } from 'vue';
import { type TransactionFilter } from '@/types';

const activeFilter = ref<TransactionFilter>('all');

export function useFilters() {
    onMounted(() => {
        const savedTab = localStorage.getItem('activeFilter') as TransactionFilter | null;
        if (savedTab) {
            activeFilter.value = savedTab;
        }
    });

    function setActiveFilter(value: TransactionFilter) {
        activeFilter.value = value;
        localStorage.setItem('activeFilter', value);
    }

    return {
        activeFilter,
        setActiveFilter,
    };
}
