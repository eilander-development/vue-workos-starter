import axios, { AxiosStatic } from 'axios';
import { onUnmounted, ref, Ref } from 'vue';

type FetchCallback<Args extends any[]> = (
    signal: AbortSignal,
    ...args: Args
) => Promise<void>;

interface UseLiveSearchReturn<Args extends any[]> {
    // Dit is nu een gewone, direct uitvoerbare functie zonder debounce
    execute: (...args: Args) => Promise<void>;
    isLoading: Ref<boolean>;
    axios: AxiosStatic; // Voeg Axios toe aan de return interface
}

/**
 * Type-safe composable voor live data ophalen met Axios en automatische request-annulering.
 * Reageert direct (zonder ingebouwde debounce).
 */
export function useLiveSearch<Args extends any[] = []>(
    fetchCallback: FetchCallback<Args>,
): UseLiveSearchReturn<Args> {
    const isLoading = ref<boolean>(false);
    let currentController: AbortController | null = null;

    const execute = async (...args: Args) => {
        // 1. Annuleer direct een lopend vorig verzoek (ongeacht of dat van typen of klikken kwam)
        if (currentController) {
            currentController.abort();
        }

        // 2. Maak een nieuwe controller aan
        currentController = new AbortController();
        isLoading.value = true;

        try {
            await fetchCallback(currentController.signal, ...args);
        } catch (error) {
            if (!axios.isCancel(error)) {
                console.error('LiveSearch API Error:', error);
            }
        } finally {
            if (currentController && !currentController.signal.aborted) {
                isLoading.value = false;
                currentController = null;
            }
        }
    };

    onUnmounted(() => {
        if (currentController) {
            currentController.abort();
        }
    });

    return {
        execute,
        isLoading,
        axios
    };
}
