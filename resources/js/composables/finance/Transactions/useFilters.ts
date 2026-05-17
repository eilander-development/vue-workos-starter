import { onMounted, ref, defineEmits } from 'vue';

type ActiveFilter = 'all' | 'expenses' | 'income' | 'savings';

const activeFilter = ref<ActiveFilter>('all');
const emit = defineEmits(['filterChanged']);

export function useFilters() {
    onMounted(() => {
        const savedTab = localStorage.getItem('activeFilter') as ActiveFilter | null;
        if (savedTab) {
            activeFilter.value = savedTab;
        }
    });

    function setActiveFilter(value: ActiveFilter) {
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
