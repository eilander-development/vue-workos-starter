import { onMounted, ref, defineEmits } from 'vue';
import { type TransactionFilter } from '@/types';

const activeFilter = ref<TransactionFilter>('all');
const emit = defineEmits(['filterChanged']);

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
        
        // Roep de emit functie aan als deze is meegegeven
        if (emit) {
            emit('filterChanged', value);
        }
    }

    return {
        activeFilter,
        setActiveFilter,
    };
}
