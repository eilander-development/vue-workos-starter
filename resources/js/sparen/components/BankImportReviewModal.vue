<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRightLeft,
    CheckCircle2,
    RefreshCw,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { normalizeAllocations } from '../allocations';
import { acceptBankReview, loadBankReview, loadBankReviews } from '../api';
import {
    bankReviewSplitPreview,
    type BankReviewDetails,
    type BankReviewResult,
    type BankReviewSummary,
} from '../bankReviews';
import { useTransactionDetail } from '../composables/useTransactionDetail';
import { transactionStatusLabel } from '../transactionStatus';
import type { BudgetItem } from '../types';
import ModalShell from './ModalShell.vue';

const props = defineProps<{
    initialReviewKey?: string | null;
    budgetItems: BudgetItem[];
}>();
const emit = defineEmits<{
    close: [];
    resolved: [result: BankReviewResult];
    count: [total: number];
}>();
const { openTransactionDetail } = useTransactionDetail();
const rows = ref<BankReviewSummary[]>([]);
const review = ref<BankReviewDetails | null>(null);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const keeperKey = ref('');
const linkSource = ref('');
const scaleSplit = ref(false);
const confirmed = ref(false);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const keeper = computed(() =>
    review.value?.existing.find((tx) => tx.id === keeperKey.value),
);
const preview = computed(() =>
    review.value
        ? bankReviewSplitPreview(
              review.value,
              keeperKey.value,
              linkSource.value,
          )
        : null,
);
const directionChanged = computed(() =>
    review.value?.existing.some(
        (tx) => tx.amount < 0 !== review.value!.amount < 0,
    ),
);
const canSave = computed(() =>
    Boolean(
        review.value?.status === 'open' &&
            keeper.value &&
            linkSource.value &&
            confirmed.value &&
            !directionChanged.value &&
            !loading.value &&
            !saving.value &&
            preview.value?.valid &&
            (!preview.value?.needsScaling || scaleSplit.value),
    ),
);
const euro = (amount: number) =>
    amount.toLocaleString('nl-NL', { style: 'currency', currency: 'EUR' });
const date = (value: string) => value.split('-').reverse().join('-');
const itemName = (id: string) =>
    props.budgetItems.find((item) => item.id === id)?.name ?? id;
watch([keeperKey, linkSource], () => {
    confirmed.value = false;
    scaleSplit.value = false;
});

async function list(nextPage = 1) {
    loading.value = true;
    error.value = '';
    try {
        const data = await loadBankReviews(nextPage);
        rows.value = data.data;
        total.value = data.total;
        page.value = data.current_page;
        lastPage.value = data.last_page;
        review.value = null;
        emit('count', data.total);
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Lijst laden mislukt.';
    } finally {
        loading.value = false;
    }
}
async function open(key: string) {
    loading.value = true;
    error.value = '';
    confirmed.value = false;
    scaleSplit.value = false;
    try {
        review.value = await loadBankReview(key);
        keeperKey.value =
            review.value.existing.length === 1
                ? review.value.existing[0].id
                : '';
        linkSource.value = review.value.linksCompatible ? 'merge' : '';
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Bankboeking laden mislukt.';
    } finally {
        loading.value = false;
    }
}
async function save() {
    if (!canSave.value || !review.value) return;
    const existingRevisions = Object.fromEntries(
        review.value.existing.map((tx) => [tx.id, tx.revision ?? '']),
    );
    saving.value = true;
    error.value = '';
    try {
        const result = await acceptBankReview(review.value.key, {
            revision: review.value.revision,
            keeperKey: keeperKey.value,
            existingRevisions,
            linkSource: linkSource.value,
            scaleSplit: scaleSplit.value,
        });
        emit('resolved', result);
        review.value = null;
        rows.value = [];
        confirmed.value = false;
        await list();
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Bankboeking verwerken mislukt.';
    } finally {
        saving.value = false;
    }
}
onMounted(() =>
    props.initialReviewKey ? open(props.initialReviewKey) : list(),
);
</script>

<template>
    <ModalShell
        :on-close="
            () => {
                if (!saving) emit('close');
            }
        "
        :dismissible="!saving"
    >
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="bank-import-review-title"
            class="max-h-[92vh] w-full max-w-5xl space-y-4 overflow-y-auto rounded-2xl border border-indigo-500/25 bg-slate-900 p-5 text-slate-200"
        >
            <div class="flex items-center gap-3">
                <span
                    class="rounded-xl border border-indigo-500/25 bg-indigo-500/10 p-2 text-indigo-300"
                    ><ArrowRightLeft class="h-5 w-5"
                /></span>
                <h2
                    id="bank-import-review-title"
                    class="text-lg font-bold text-white"
                >
                    Bankboekingen beoordelen
                </h2>
                <button
                    type="button"
                    class="ml-auto rounded-xl bg-slate-800 p-2"
                    aria-label="Bankbeoordeling sluiten"
                    :disabled="saving"
                    @click="emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
            <p v-if="loading" role="status" class="text-sm text-slate-400">
                Bankgegevens laden…
            </p>
            <p
                v-if="error"
                role="alert"
                class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-200"
            >
                {{ error }}
            </p>
            <template v-if="!review">
                <p class="text-sm leading-relaxed text-slate-400">
                    Deze definitieve bankboekingen wijken af van eerder
                    opgeslagen gegevens. Ze blijven hier beschikbaar totdat ze
                    zijn beoordeeld. Ze tellen nog niet mee; de bestaande
                    registratie blijft staan.
                </p>
                <button
                    type="button"
                    :disabled="loading || saving"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 px-3 py-2 text-xs text-indigo-200"
                    @click="list(page)"
                >
                    <RefreshCw class="h-3.5 w-3.5" />Lijst vernieuwen
                </button>
                <p
                    v-if="!loading && !rows.length"
                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/[0.04] p-4 text-sm text-emerald-200"
                >
                    Geen bankboekingen die beoordeling nodig hebben.
                </p>
                <button
                    v-for="row in rows"
                    :key="row.key"
                    type="button"
                    :disabled="loading || saving"
                    class="block w-full space-y-2 rounded-xl border border-indigo-500/20 bg-slate-950/30 p-4 text-left hover:border-indigo-400/50"
                    @click="open(row.key)"
                >
                    <span class="flex flex-wrap justify-between gap-3"
                        ><strong class="text-white">{{
                            row.description
                        }}</strong
                        ><strong class="font-mono text-indigo-200">{{
                            euro(row.amount)
                        }}</strong></span
                    ><span class="block text-xs text-slate-400"
                        >{{ date(row.date) }} · Bankreferentie
                        {{ row.reference }} · Ontvangen
                        {{ new Date(row.seenAt).toLocaleString('nl-NL') }}</span
                    >
                </button>
                <div
                    v-if="total > 25"
                    class="flex items-center justify-between text-xs"
                >
                    <button
                        type="button"
                        :disabled="loading || page <= 1"
                        class="rounded-lg bg-slate-800 px-3 py-2 disabled:opacity-40"
                        @click="list(page - 1)"
                    >
                        Vorige</button
                    ><span
                        >{{ page }} / {{ lastPage }} ·
                        {{ total }} boekingen</span
                    ><button
                        type="button"
                        :disabled="loading || page >= lastPage"
                        class="rounded-lg bg-slate-800 px-3 py-2 disabled:opacity-40"
                        @click="list(page + 1)"
                    >
                        Volgende
                    </button>
                </div>
            </template>
            <template v-else>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="loading || saving"
                        class="flex items-center gap-2 rounded-xl border border-slate-700 px-3 py-2 text-xs text-slate-300"
                        @click="list()"
                    >
                        <ArrowLeft class="h-3.5 w-3.5" />Naar de lijst</button
                    ><button
                        type="button"
                        :disabled="loading || saving"
                        class="flex items-center gap-2 rounded-xl border border-slate-700 px-3 py-2 text-xs text-indigo-200"
                        @click="open(review.key)"
                    >
                        <RefreshCw class="h-3.5 w-3.5" />Gegevens opnieuw laden
                    </button>
                </div>
                <p
                    v-if="review.status !== 'open'"
                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-sm text-emerald-200"
                >
                    Deze bankversie is al verwerkt of vervangen door een latere
                    versie.
                </p>
                <section
                    class="space-y-2 rounded-xl border border-emerald-500/25 bg-emerald-500/[0.04] p-4"
                >
                    <h3 class="text-sm font-semibold text-emerald-300">
                        Definitief aangeleverd door de bank
                    </h3>
                    <p class="font-semibold text-white">
                        {{ review.description }}
                    </p>
                    <p class="text-sm">
                        {{ date(review.date) }} ·
                        <strong class="font-mono text-lg text-white">{{
                            euro(review.amount)
                        }}</strong>
                    </p>
                    <p class="text-xs break-all text-slate-400">
                        Eigen rekening {{ review.account }} · Bankreferentie
                        {{ review.reference }}
                    </p>
                    <p class="text-xs text-slate-400">
                        Laatst aangeleverd
                        {{ new Date(review.seenAt).toLocaleString('nl-NL') }}.
                        Dit bedrag en deze boekdatum worden na jouw bevestiging
                        overgenomen.
                    </p>
                </section>
                <p
                    v-if="review.otherVersions"
                    class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-3 text-sm text-amber-200"
                >
                    Er zijn {{ review.otherVersions }} andere bankversies voor
                    dezelfde betaling. Controleer welke versie klopt. Na
                    accepteren worden de andere voorstellen afgesloten en
                    bewaard.
                </p>
                <div class="grid gap-3 md:grid-cols-2">
                    <section
                        v-for="tx in review.existing"
                        :key="tx.id"
                        class="space-y-2 rounded-xl border border-slate-700 bg-slate-950/40 p-4"
                    >
                        <p class="text-xs font-semibold text-amber-200">
                            Nu opgeslagen · {{ transactionStatusLabel(tx) }}
                        </p>
                        <button
                            type="button"
                            class="text-left font-semibold text-white underline decoration-indigo-500/30 underline-offset-4"
                            @click="openTransactionDetail(tx)"
                        >
                            {{ tx.description }}
                        </button>
                        <p class="text-sm">
                            {{ date(tx.date) }} ·
                            <strong class="font-mono">{{
                                euro(tx.amount)
                            }}</strong>
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ tx.categoryGroup
                            }}{{
                                tx.budgetItemName
                                    ? ` · ${tx.budgetItemName}`
                                    : ''
                            }}
                        </p>
                        <div
                            v-for="split in normalizeAllocations(
                                tx.allocations,
                            ) ?? []"
                            :key="split.budgetItemId"
                            class="flex justify-between gap-2 text-xs"
                        >
                            <span>{{ itemName(split.budgetItemId) }}</span
                            ><span class="font-mono">{{
                                euro(split.amount)
                            }}</span>
                        </div>
                        <p class="text-[11px] break-all text-slate-500">
                            {{ tx.id }}
                        </p>
                    </section>
                </div>
                <p
                    v-if="!review.existing.length || directionChanged"
                    class="rounded-xl border border-amber-500/25 p-3 text-sm text-amber-200"
                >
                    {{
                        directionChanged
                            ? 'De betaalrichting wijkt af. Deze gegevens kunnen niet als dezelfde betaling worden verwerkt.'
                            : 'De oorspronkelijke registratie is niet meer actief. Laad de beoordeling opnieuw.'
                    }}
                </p>
                <label
                    v-if="review.existing.length > 1"
                    class="block text-xs text-slate-400"
                    >Registratie behouden<select
                        v-model="keeperKey"
                        aria-label="Registratie behouden"
                        :disabled="saving"
                        class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-800 p-2.5 text-sm text-white"
                    >
                        <option value="">
                            Kies welke registratie haar ID behoudt
                        </option>
                        <option
                            v-for="tx in review.existing"
                            :key="tx.id"
                            :value="tx.id"
                        >
                            {{ transactionStatusLabel(tx) }} ·
                            {{ date(tx.date) }} · {{ euro(tx.amount) }} ·
                            {{ tx.description }}
                        </option>
                    </select></label
                >
                <label class="block text-xs text-slate-400"
                    >Koppelingen en splitsingen behouden van<select
                        v-model="linkSource"
                        aria-label="Koppelingen van bankboeking behouden"
                        :disabled="saving"
                        class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-800 p-2.5 text-sm text-white"
                    >
                        <option value="" disabled>
                            Kies welke gegevens je wilt behouden
                        </option>
                        <option
                            value="merge"
                            :disabled="!review.linksCompatible"
                        >
                            Bestaande koppelingen behouden en ontbrekende
                            overnemen
                        </option>
                        <option
                            v-for="tx in review.existing"
                            :key="tx.id"
                            :value="tx.id"
                        >
                            {{ transactionStatusLabel(tx) }} ·
                            {{ euro(tx.amount) }} ·
                            {{ tx.budgetItemName ?? tx.description }}
                        </option>
                    </select></label
                >
                <p
                    v-if="!review.linksCompatible"
                    class="text-xs leading-relaxed text-amber-200"
                >
                    De bestaande koppelingen verschillen. Kies bewust één bron.
                    Alle andere koppelingen blijven terug te vinden in Historie.
                </p>
                <section
                    v-if="keeper && linkSource"
                    class="space-y-3 rounded-xl border border-indigo-500/20 bg-indigo-500/[0.04] p-4"
                >
                    <h3 class="text-sm font-semibold text-indigo-200">
                        Na accepteren
                    </h3>
                    <p class="text-sm text-slate-300">
                        Eén definitieve betaling van
                        {{ euro(review.amount) }} op {{ date(review.date) }}. De
                        vorige bankgegevens en eventuele dubbele registraties
                        blijven in Historie.
                    </p>
                    <label
                        v-if="preview?.needsScaling"
                        class="flex items-start gap-2 rounded-lg border border-amber-500/25 p-3 text-xs leading-relaxed text-amber-200"
                        ><input
                            v-model="scaleSplit"
                            type="checkbox"
                            :disabled="saving"
                            class="mt-1"
                        />De splitsing evenredig aanpassen aan het nieuwe
                        bankbedrag, volgens de verdeling hieronder.</label
                    >
                    <p
                        v-if="preview && !preview.valid"
                        class="text-xs text-rose-200"
                    >
                        Het bankbedrag is te klein voor deze splitsing. Pas
                        eerst de koppeling aan en laad de beoordeling opnieuw.
                    </p>
                    <div
                        v-for="row in preview?.after ?? []"
                        :key="row.budgetItemId"
                        class="flex justify-between gap-2 text-sm"
                    >
                        <span>{{ itemName(row.budgetItemId) }}</span
                        ><strong class="font-mono text-indigo-200">{{
                            euro(row.amount)
                        }}</strong>
                    </div>
                    <p
                        v-if="!preview?.after.length"
                        class="text-xs text-slate-400"
                    >
                        De gekozen koppelingen blijven behouden; er is geen
                        splitsing om aan te passen.
                    </p>
                </section>
                <label class="flex items-start gap-2 text-sm text-slate-300"
                    ><input
                        v-model="confirmed"
                        type="checkbox"
                        :disabled="saving || review.status !== 'open'"
                        class="mt-1"
                    />Ik heb bedragen en koppelingen gecontroleerd en wil de
                    definitieve bankboeking verwerken.</label
                >
                <div
                    class="flex flex-wrap justify-end gap-3 border-t border-slate-800 pt-4"
                >
                    <button
                        type="button"
                        :disabled="saving"
                        class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm"
                        @click="emit('close')"
                    >
                        Later beoordelen</button
                    ><button
                        type="button"
                        :disabled="!canSave"
                        class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        @click="save"
                    >
                        <CheckCircle2 class="h-4 w-4" />{{
                            saving
                                ? 'Verwerken…'
                                : 'Definitieve bankboeking accepteren'
                        }}
                    </button>
                </div>
            </template>
        </section>
    </ModalShell>
</template>
