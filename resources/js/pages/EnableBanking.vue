<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { home, enabled_banking } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useNotification } from '@/composables/useNotification';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { customScrollbar } from '@/composables/scrollbar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
    {
        title: 'EnableBanking',
        href: enabled_banking().url,
    },
];

interface AccountBalance {
    accountId: string | null;
    name: string | null;
    currency: string | null;
    balance: number | null;
    available: number | null;
    raw: Record<string, any>;
}

interface UsageEntry {
    lastFetch: string | null;
    countToday: number;
    date: string;
}

const accounts = ref<AccountBalance[]>([]);
const selectedAccountId = ref<string>('');
const transactions = ref<Record<string, any>[]>([]);
const loadingBalance = ref(false);
const loadingTransactions = ref(false);
const importingTransactions = ref(false);
const disconnectConfirmOpen = ref(false);
const importStatsOpen = ref(false);
const importStats = ref<{ total: number; imported: number; duplicates: number; matched: number; unmatched: number } | null>(null);
const connecting = ref(false);
const { showNotification } = useNotification();

const usage = ref<{ balance: UsageEntry; transactions: UsageEntry }>({
    balance: { lastFetch: null, countToday: 0, date: '' },
    transactions: { lastFetch: null, countToday: 0, date: '' },
});

const storageKey = 'enabledBankingUsage';

const today = () => new Date().toISOString().slice(0, 10);

const loadUsage = () => {
    const raw = localStorage.getItem(storageKey);
    if (!raw) {
        return;
    }

    try {
        const parsed = JSON.parse(raw);
        if (parsed?.balance) {
            usage.value.balance = parsed.balance;
        }
        if (parsed?.transactions) {
            usage.value.transactions = parsed.transactions;
        }
    } catch {
        localStorage.removeItem(storageKey);
    }
};

const saveUsage = () => {
    localStorage.setItem(storageKey, JSON.stringify(usage.value));
};

const recordUsage = (type: 'balance' | 'transactions') => {
    const entry = usage.value[type];
    const currentDay = today();
    if (entry.date !== currentDay) {
        entry.countToday = 0;
        entry.date = currentDay;
    }
    entry.countToday += 1;
    entry.lastFetch = new Date().toISOString();
    saveUsage();
};

const formatUsageTime = (timestamp: string | null) => {
    if (!timestamp) {
        return 'Nooit';
    }

    const date = new Date(timestamp);
    return date.toLocaleString('nl-NL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Start de officiële bankkoppeling flow door de redirect-URL op te vragen en te openen
 */
const startConnection = async () => {
    connecting.value = true;
    try {
        const response = await axios.get('/enabled-banking/connect');
        if (response.data.url) {
            // Stuur de gebruiker door naar de beveiligde omgeving van de bank
            window.location.href = response.data.url;
        } else {
            showNotification('Fout', 'Geen koppelings-URL ontvangen van de server.', 'destructive');
        }
    } catch (error) {
        console.error(error);
        showNotification('Fout', 'Kon de bankkoppeling niet starten.', 'destructive');
    } finally {
        connecting.value = false;
    }
};

/**
 * Haalt de rekeningen en bijbehorende saldi op van de Laravel backend
 */
const fetchBalance = async () => {
    loadingBalance.value = true;
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const sessionId = urlParams.get('session_id') ?? urlParams.get('code');

        const response = await axios.get('/enabled-banking/balance', {
            params: sessionId ? { session_id: sessionId } : {}
        });

        accounts.value = Array.isArray(response.data.accounts)
            ? response.data.accounts.filter((account: any) => account != null)
            : [];

        // CRUCIALE FIX: Voeg index [0] toe om het account ID correct te selecteren uit de array
        if (accounts.value.length > 0 && !selectedAccountId.value) {
            selectedAccountId.value = accounts.value[0]?.accountId ?? '';
        }
        
        recordUsage('balance');
        showNotification('Succes', 'Saldo succesvol opgehaald.', 'success');
    } catch (error) {
        console.error(error);
        showNotification('Fout', 'Kon het saldo niet ophalen.', 'destructive');
    } finally {
        loadingBalance.value = false;
    }
};

/**
 * Haalt de transacties op voor het geselecteerde rekening-ID
 */
const fetchTransactions = async () => {
    if (!selectedAccountId.value) {
        showNotification('Fout', 'Selecteer eerst een rekening.', 'destructive');
        return;
    }

    loadingTransactions.value = true;
    try {
        const response = await axios.get(`/enabled-banking/accounts/${selectedAccountId.value}/transactions`);

        transactions.value = response.data.transactions ?? [];
        recordUsage('transactions');
        showNotification('Succes', 'Transacties zijn opgehaald.', 'success');
    } catch (error) {
        console.error(error);
        showNotification('Fout', 'Kon transacties niet ophalen.', 'destructive');
    } finally {
        loadingTransactions.value = false;
    }
};

const importEnabledBankingTransactions = async () => {
    if (!transactions.value.length) {
        showNotification('Fout', 'Er zijn geen transacties om te importeren.', 'destructive');
        return;
    }

    importingTransactions.value = true;
    try {
        const response = await axios.post('/enabled-banking/import-transactions', {
            transactions: transactions.value,
        });
        const stats = response.data.stats;
        importStats.value = stats;
        importStatsOpen.value = true;
        showNotification(
            'Succes',
            `Import voltooid: ${stats.imported} opgeslagen, ${stats.matched} gematcht, ${stats.duplicates} duplicaten.`,
            'success'
        );
    } catch (error) {
        console.error(error);
        showNotification('Fout', 'Kon EnableBanking transacties niet importeren.', 'destructive');
    } finally {
        importingTransactions.value = false;
    }
};

const accountOptions = computed(() =>
    accounts.value
        .filter((account): account is AccountBalance => Boolean(account && account.accountId))
        .map((account) => ({
            label: account.name ?? account.accountId ?? 'Onbekende rekening',
            value: account.accountId ?? '',
        })),
);

/**
 * Verbreekt de actieve bankkoppeling en wist de opgeslagen data
 */
const disconnectBank = async () => {
    try {
        await axios.post('/enabled-banking/disconnect');
        
        // Maak de reactieve states in de frontend direct leeg
        accounts.value = [];
        selectedAccountId.value = '';
        transactions.value = [];
        
        // Zet de prop handmatig om naar false zodat de UI direct terugspringt naar de koppelknop
        props.hasActiveConnection = false;
        
        showNotification('Succes', 'De bankkoppeling is succesvol verbroken.', 'success');
        disconnectConfirmOpen.value = false;

        setTimeout(() => {
            window.location.href = '/enabled-banking';
        }, 500);
    } catch (error) {
        console.error(error);
        showNotification('Fout', 'Kon de bankkoppeling niet verbreken.', 'destructive');
    }
};

const props = defineProps<{
    hasActiveConnection: boolean;
}>();

// Automatisch transacties laden wanneer de geselecteerde rekening verandert
watch(selectedAccountId, (newId) => {
    if (newId) {
        fetchTransactions(); // Haalt direct de transacties op zonder knop!
    }
});

onMounted(() => {
    loadUsage();

    // 2. Als er al een actieve koppeling in de sessie zit,
    // OF als de gebruiker net terugkomt van de bank (session_id in URL),
    // dan halen we direct automatisch de saldi en rekeningen binnen.
    const urlParams = new URLSearchParams(window.location.search);
    if (props.hasActiveConnection || urlParams.has('session_id') || urlParams.has('code')) {
        fetchBalance();
    }
});
</script>

<template>
    <Head title="EnableBanking" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="p-4 h-full">
            <div class="flex h-full flex-col rounded-lg border bg-card text-card-foreground shadow-sm">
                <div class="flex flex-col space-y-1.5 p-4 sm:p-6">
                    <div>
                        <h1 class="text-base font-medium tracking-tight sm:text-lg">EnableBanking</h1>
                        <p class="text-xs text-muted-foreground sm:text-sm">
                            Haal je balans en banktransacties op met EnableBanking.
                        </p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border bg-background p-4">
                        <p class="text-sm font-medium text-muted-foreground">Laatste saldo-ophaal</p>
                        <p class="mt-2 text-base font-semibold">{{ formatUsageTime(usage.balance.lastFetch) }}</p>
                        <p class="text-sm text-muted-foreground">Vandaag opgehaald: {{ usage.balance.countToday }}x</p>
                    </div>
                    <div class="rounded-lg border bg-background p-4">
                        <p class="text-sm font-medium text-muted-foreground">Laatste transacties-ophaal</p>
                        <p class="mt-2 text-base font-semibold">{{ formatUsageTime(usage.transactions.lastFetch) }}</p>
                        <p class="text-sm text-muted-foreground">Vandaag opgehaald: {{ usage.transactions.countToday }}x</p>
                    </div>
                </div>
                <!-- Dynamische Actieknoppen -->
                <div class="flex flex-col sm:flex-row gap-3 items-center justify-between w-full">
                    
                    <!-- LINKERKANT: Status van de bankkoppeling (Koppelen of Verbinding verbreken) -->
                    <div class="w-full sm:w-auto flex-1">
                        <!-- Wordt getoond als er NOG GEEN actieve bankkoppeling is -->
                        <Button
                            v-if="!props.hasActiveConnection"
                            variant="outline"
                            class="w-full sm:w-auto"
                            :disabled="connecting"
                            @click="startConnection"
                        >
                            {{ connecting ? 'Verbinden...' : 'Bank koppelen (Start Flow)' }}
                        </Button>

                        <!-- Wordt getoond als de bank SUCCESVOL gekoppeld is -->
                        <div 
                            v-else 
                            class="flex items-center justify-between w-full rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-medium text-green-700"
                        >
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                </span>
                                Actieve bankkoppeling
                            </div>
                            
                            <button 
                                type="button" 
                                @click="disconnectConfirmOpen = true"
                                class="text-xs font-semibold text-red-600 hover:text-red-800 hover:underline focus:outline-none transition-colors ml-4"
                            >
                                Verbinding verbreken
                            </button>
                        </div>
                    </div>

                    <!-- RECHTERKANT: Saldo ophalen knop (Verdwijnt automatisch zodra er rekeningen op het scherm staan!) -->
                    <div v-if="accounts.length === 0" class="w-full sm:w-auto">
                        <Button
                            :disabled="loadingBalance"
                            @click="fetchBalance"
                            class="w-full sm:w-auto"
                        >
                            {{ loadingBalance ? 'Bezig...' : 'Haal saldo en rekeningen op' }}
                        </Button>
                    </div>
                </div>
                </div>

            <div class="space-y-4 border-t p-4 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-medium tracking-tight sm:text-lg">Rekeningen</h2>
                        <p class="text-sm text-muted-foreground">Selecteer een rekening om transacties op te halen.</p>
                    </div>
                    <div>
                        <select
                            v-model="selectedAccountId"
                            class="rounded-lg border border-border bg-background px-3 py-2 text-sm w-full sm:w-auto"
                        >
                            <option value="" disabled>Selecteer een rekening</option>
                            <option
                                v-for="account in accountOptions"
                                :key="account.value"
                                :value="account.value"
                            >
                                {{ account.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="accounts.length === 0" class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                    Geen rekeningen geladen. Klik eerst op "Bank koppelen" of daarna op "Haal saldo en rekeningen op" om te starten.
                </div>

                <div v-else class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="account in accounts"
                            :key="account?.accountId ?? String(Math.random())"
                            class="rounded-lg border bg-background p-4"
                        >
                            <p class="text-sm font-medium text-muted-foreground">{{ account?.name ?? 'Onbekende rekening' }}</p>
                            <p class="mt-3 text-2xl font-semibold">
                                {{ account?.balance != null ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: account?.currency ?? 'EUR' }).format(account.balance) : 'N.v.t.' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Beschikbaar: {{ account?.available != null ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: account?.currency ?? 'EUR' }).format(account.available) : 'N.v.t.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 border-t p-4 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-medium tracking-tight sm:text-lg">Transacties</h2>
                        <p class="text-sm text-muted-foreground">Vernieuw de tabel met de geselecteerde rekening.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted-foreground">{{ transactions.length }} transacties</span>
                        <Button
                            size="sm"
                            :disabled="importingTransactions || transactions.length === 0"
                            @click="importEnabledBankingTransactions"
                        >
                            {{ importingTransactions ? 'Importeren...' : 'Importeer' }}
                        </Button>
                    </div>
                </div>

                <div v-if="transactions.length === 0" class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                    Geen transacties geladen. Klik op "Haal transacties op" nadat je een rekening geselecteerd hebt.
                </div>

                <div v-else :class="`${customScrollbar} -mx-4 overflow-x-auto sm:mx-0`">
                    <div class="inline-block min-w-full px-4 align-middle sm:px-0">
                    <table class="w-full table-auto text-sm">
                        <thead>
                            <tr>
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Datum</th>
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Omschrijving</th>
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Rekeningnummer</th>
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Bedrag</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(transaction, index) in transactions" :key="transaction.id ?? index" class="border-b border-slate-900 transition-colors hover:bg-muted/50">
                                <td class="p-2 px-4">{{ transaction.posted_at ?? transaction.date ?? '-' }}</td>
                                <td class="p-2 px-4">{{ transaction.description ?? transaction.merchant ?? 'Onbekend' }}</td>
                                <td class="p-2 px-4">{{ transaction.counterparty_iban ?? '-' }}</td>
                                <td class="p-2 px-4">
                                    {{ transaction.amount !== undefined ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: transaction.currency ?? 'EUR' }).format(transaction.amount) : '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            </div>
            <Dialog :open="importStatsOpen" @update:open="(value) => importStatsOpen = value">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Importresultaat</DialogTitle>
                        <DialogDescription>
                            Overzicht van de laatst geïmporteerde EnableBanking transacties.
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="importStats" class="grid grid-cols-2 gap-2 text-sm">
                        <div>Totaal: {{ importStats.total }}</div>
                        <div>Ingelezen: {{ importStats.imported }}</div>
                        <div>Duplicaten: {{ importStats.duplicates }}</div>
                        <div>Gematcht: {{ importStats.matched }}</div>
                        <div>Niet gematcht: {{ importStats.unmatched }}</div>
                    </div>
                    <DialogFooter>
                        <Button variant="secondary" @click="importStatsOpen = false">Sluiten</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <ConfirmDialog
                :open="disconnectConfirmOpen"
                title="Bankkoppeling verbreken"
                description="Weet je zeker dat je de actieve bankkoppeling wilt verbreken?"
                confirm-text="Verbreken"
                confirm-variant="destructive"
                @update:open="(value) => disconnectConfirmOpen = value"
                @confirm="disconnectBank"
            />
        </main>
    </AppLayout>
</template>
