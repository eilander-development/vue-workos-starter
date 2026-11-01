<script setup lang="ts">
import { ArrowRightLeft, CheckCircle2, RefreshCw, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { normalizeAllocations, scaleAllocationsToAmount } from '../allocations';
import { loadReservationReview, resolveReservation } from '../api';
import { useTransactionDetail } from '../composables/useTransactionDetail';
import type { BudgetItem, Transaction } from '../types';
import ModalShell from './ModalShell.vue';

type Candidate = Transaction & { linksCompatible: boolean };
const props = defineProps<{
    reservationKey: string;
    budgetItems: BudgetItem[];
}>();
const emit = defineEmits<{
    close: [];
    resolved: [result: { reservationKey: string; booked: Transaction | null }];
}>();
const { openTransactionDetail } = useTransactionDetail();
const reservation = ref<Transaction | null>(null);
const candidates = ref<Candidate[]>([]);
const search = ref('');
const targetKey = ref('');
const action = ref<'match' | 'expire'>('match');
const linkChoice = ref<'' | 'merge' | 'reservation' | 'booked'>('merge');
const scaleSplit = ref(false);
const confirmed = ref(false);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const selected = computed(() =>
    candidates.value.find((tx) => tx.id === targetKey.value),
);
const reservationSplit = computed(
    () => normalizeAllocations(reservation.value?.allocations) ?? [],
);
const amountChanged = computed(() =>
    Boolean(
        selected.value &&
            reservation.value &&
            Math.round(selected.value.amount * 100) !==
                Math.round(reservation.value.amount * 100),
    ),
);
const usesReservationSplit = computed(
    () =>
        reservationSplit.value.length > 0 &&
        (linkChoice.value === 'reservation' ||
            (linkChoice.value === 'merge' &&
                !normalizeAllocations(selected.value?.allocations))),
);
const needsScaling = computed(
    () => amountChanged.value && usesReservationSplit.value,
);
const finalSplit = computed(() => {
    if (!selected.value) return [];
    return usesReservationSplit.value
        ? scaleAllocationsToAmount(
              reservationSplit.value,
              selected.value.amount,
          )
        : (normalizeAllocations(selected.value.allocations) ?? []);
});
const canSave = computed(() =>
    Boolean(
        reservation.value?.revision &&
            confirmed.value &&
            !saving.value &&
            !loading.value &&
            (action.value === 'expire' ||
                (selected.value?.revision &&
                    linkChoice.value &&
                    (!needsScaling.value || scaleSplit.value) &&
                    (!usesReservationSplit.value ||
                        finalSplit.value.every((row) => row.amount > 0)))),
    ),
);
const euro = (amount: number) =>
    amount.toLocaleString('nl-NL', { style: 'currency', currency: 'EUR' });
const date = (value: string) => value.split('-').reverse().join('-');
const itemName = (id: string) =>
    props.budgetItems.find((item) => item.id === id)?.name ?? id;
watch([targetKey, action], () => {
    confirmed.value = false;
    scaleSplit.value = false;
    linkChoice.value = selected.value?.linksCompatible === false ? '' : 'merge';
});
watch(linkChoice, () => {
    confirmed.value = false;
    scaleSplit.value = false;
});

async function load() {
    if (loading.value || saving.value) return;
    loading.value = true;
    error.value = '';
    try {
        const data = await loadReservationReview(
            props.reservationKey,
            search.value,
        );
        reservation.value = data.reservation;
        candidates.value = data.candidates;
        targetKey.value = '';
        confirmed.value = false;
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Beoordeling laden mislukt.';
    } finally {
        loading.value = false;
    }
}
async function save() {
    if (!canSave.value || !reservation.value?.revision) return;
    saving.value = true;
    error.value = '';
    try {
        const result = await resolveReservation(props.reservationKey, {
            action: action.value,
            revision: reservation.value.revision,
            ...(action.value === 'match' && selected.value
                ? {
                      bookedKey: selected.value.id,
                      bookedRevision: selected.value.revision,
                      linkChoice: linkChoice.value || 'merge',
                      scaleSplit: scaleSplit.value,
                  }
                : {}),
        });
        emit('resolved', result);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Beoordeling opslaan mislukt.';
    } finally {
        saving.value = false;
    }
}
onMounted(load);
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
            aria-labelledby="reservation-review-title"
            class="max-h-[92vh] w-full max-w-5xl space-y-4 overflow-y-auto rounded-2xl border border-amber-500/25 bg-slate-900 p-5 text-slate-200"
        >
            <div class="flex items-center gap-3">
                <span
                    class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-2 text-amber-300"
                    ><ArrowRightLeft class="h-5 w-5"
                /></span>
                <h2
                    id="reservation-review-title"
                    class="text-lg font-bold text-white"
                >
                    Reservering beoordelen
                </h2>
                <button
                    type="button"
                    class="ml-auto rounded-xl bg-slate-800 p-2"
                    aria-label="Beoordeling sluiten"
                    :disabled="saving"
                    @click="emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
            <p class="text-sm leading-relaxed text-slate-400">
                De bank heeft deze betaling nog als reservering staan.
                Controleer of er al een definitieve betaling is, of markeer de
                reservering bewust als vervallen. De reservering blijft bewaard
                als Historie en telt niet mee als betaald.
            </p>
            <p v-if="loading" role="status" class="text-sm text-slate-400">
                Gegevens laden…
            </p>
            <p
                v-if="error"
                role="alert"
                class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-200"
            >
                {{ error }}
            </p>
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl border border-slate-700 px-3 py-2 text-xs text-indigo-200 disabled:opacity-40"
                :disabled="loading || saving"
                @click="load"
            >
                <RefreshCw class="h-3.5 w-3.5" />Gegevens opnieuw laden
            </button>

            <template v-if="reservation">
                <div class="grid gap-4 md:grid-cols-2">
                    <section
                        class="space-y-3 rounded-xl border border-amber-500/25 bg-amber-500/[0.04] p-4"
                    >
                        <h3 class="text-sm font-semibold text-amber-300">
                            Reservering
                        </h3>
                        <button
                            type="button"
                            class="text-left font-semibold text-white underline decoration-amber-500/30 underline-offset-4"
                            @click="openTransactionDetail(reservation)"
                        >
                            {{ reservation.description }}
                        </button>
                        <p class="text-sm text-slate-300">
                            {{ date(reservation.date) }} ·
                            <strong class="font-mono">{{
                                euro(reservation.amount)
                            }}</strong>
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ reservation.categoryGroup
                            }}{{
                                reservation.budgetItemName
                                    ? ` · ${reservation.budgetItemName}`
                                    : ''
                            }}
                        </p>
                        <p class="text-xs break-all text-slate-500">
                            Bankreferentie:
                            {{ reservation.bankReference ?? 'ontbreekt' }}
                        </p>
                        <p
                            v-for="row in reservationSplit"
                            :key="row.budgetItemId"
                            class="flex justify-between gap-3 text-xs"
                        >
                            <span>{{ itemName(row.budgetItemId) }}</span
                            ><span class="font-mono">{{
                                euro(row.amount)
                            }}</span>
                        </p>
                    </section>
                    <section
                        class="space-y-3 rounded-xl border border-indigo-500/20 bg-slate-950/40 p-4"
                    >
                        <h3 class="text-sm font-semibold text-indigo-200">
                            Wat is er gebeurd?
                        </h3>
                        <label class="flex items-start gap-2 text-sm"
                            ><input
                                v-model="action"
                                type="radio"
                                value="match"
                                :disabled="saving"
                                class="mt-1"
                            />Dezelfde betaling is al definitief geboekt</label
                        ><label class="flex items-start gap-2 text-sm"
                            ><input
                                v-model="action"
                                type="radio"
                                value="expire"
                                :disabled="saving"
                                class="mt-1"
                            />De reservering is vervallen</label
                        >
                        <p class="text-xs leading-relaxed text-slate-400">
                            Een reservering die niet meer bij de bank verschijnt
                            is niet automatisch vervallen. Je bepaalt dit na
                            controle.
                        </p>
                    </section>
                </div>

                <template v-if="action === 'match'">
                    <form class="flex flex-wrap gap-2" @submit.prevent="load">
                        <input
                            v-model="search"
                            type="search"
                            aria-label="Zoek definitieve betaling"
                            placeholder="Zoek een andere betaling op omschrijving"
                            :disabled="saving || loading"
                            class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-800 p-2.5 text-sm"
                        /><button
                            type="submit"
                            :disabled="saving || loading"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm"
                        >
                            Zoeken
                        </button>
                    </form>
                    <p class="text-xs text-slate-400">
                        Zonder zoekterm zie je maximaal 50 betalingen rond de
                        reserveringsdatum. Zoeken kijkt ook buiten die periode.
                        Alleen dezelfde eigen rekening en betaalrichting worden
                        aangeboden.
                    </p>
                    <label class="block text-xs text-slate-400"
                        >Definitieve betaling<select
                            v-model="targetKey"
                            aria-label="Definitieve betaling"
                            :disabled="saving || loading"
                            class="mt-1 block w-full min-w-0 rounded-xl border border-slate-700 bg-slate-800 p-2.5 text-sm text-white"
                        >
                            <option value="">
                                Kies zelf de juiste betaling
                            </option>
                            <option
                                v-for="tx in candidates"
                                :key="tx.id"
                                :value="tx.id"
                            >
                                {{ date(tx.date) }} · {{ euro(tx.amount) }} ·
                                {{ tx.description }}
                            </option>
                        </select></label
                    >
                    <p
                        v-if="!loading && !candidates.length"
                        class="text-sm text-slate-400"
                    >
                        Geen passende definitieve betalingen gevonden. Zoek op
                        omschrijving of laat de reservering openstaan.
                    </p>
                    <section
                        v-if="selected"
                        class="space-y-3 rounded-xl border border-indigo-500/20 bg-indigo-500/[0.04] p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h3
                                    class="text-sm font-semibold text-indigo-200"
                                >
                                    Geselecteerde definitieve betaling
                                </h3>
                                <button
                                    type="button"
                                    class="mt-2 text-left text-sm font-semibold text-white underline decoration-indigo-500/30 underline-offset-4"
                                    @click="openTransactionDetail(selected)"
                                >
                                    {{ selected.description }}
                                </button>
                                <p
                                    class="mt-1 text-xs break-all text-slate-500"
                                >
                                    Bankreferentie:
                                    {{ selected.bankReference ?? 'ontbreekt' }}
                                </p>
                            </div>
                            <strong class="font-mono text-white">{{
                                euro(selected.amount)
                            }}</strong>
                        </div>
                        <p class="text-xs text-slate-400">
                            {{ selected.categoryGroup
                            }}{{
                                selected.budgetItemName
                                    ? ` · ${selected.budgetItemName}`
                                    : ''
                            }}
                        </p>
                        <div
                            v-for="row in normalizeAllocations(
                                selected.allocations,
                            ) ?? []"
                            :key="row.budgetItemId"
                            class="flex justify-between gap-3 text-xs text-slate-300"
                        >
                            <span>{{ itemName(row.budgetItemId) }}</span
                            ><span class="font-mono">{{
                                euro(row.amount)
                            }}</span>
                        </div>
                        <p
                            v-if="amountChanged"
                            class="rounded-lg border border-amber-500/25 bg-amber-500/10 p-3 text-sm text-amber-200"
                        >
                            Het bedrag wijkt af:
                            {{ euro(reservation.amount) }} →
                            {{ euro(selected.amount) }}. Het definitieve
                            bankbedrag blijft leidend.
                        </p>
                        <p
                            v-if="!selected.linksCompatible"
                            class="text-sm text-amber-200"
                        >
                            De koppelingen of splitsingen verschillen. Kies
                            hieronder bewust welke je wilt behouden. De vorige
                            gegevens blijven terug te vinden in Historie.
                        </p>
                        <label class="block text-xs text-slate-400"
                            >Koppelingen en splitsingen behouden van<select
                                v-model="linkChoice"
                                aria-label="Koppelingen behouden van"
                                :disabled="saving"
                                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-800 p-2.5 text-sm text-white"
                            >
                                <option disabled value="">
                                    Kies welke gegevens je wilt behouden
                                </option>
                                <option
                                    value="merge"
                                    :disabled="!selected.linksCompatible"
                                >
                                    Ontbrekende koppelingen overnemen
                                </option>
                                <option value="reservation">
                                    De reservering
                                </option>
                                <option value="booked">
                                    De definitieve betaling
                                </option>
                            </select></label
                        >
                        <label
                            v-if="needsScaling"
                            class="flex items-start gap-2 rounded-lg border border-amber-500/25 p-3 text-xs leading-relaxed text-amber-200"
                            ><input
                                v-model="scaleSplit"
                                type="checkbox"
                                :disabled="saving"
                                class="mt-1"
                            />De splitsing van de reservering evenredig
                            aanpassen aan het definitieve bedrag, volgens de
                            verdeling hieronder.</label
                        >
                        <p
                            v-if="
                                usesReservationSplit &&
                                finalSplit.some((row) => row.amount <= 0)
                            "
                            class="text-xs text-rose-200"
                        >
                            Het bankbedrag is te klein voor deze splitsing. Pas
                            eerst de koppeling aan.
                        </p>
                        <div
                            v-if="linkChoice"
                            class="space-y-2 rounded-xl bg-slate-950/50 p-3"
                        >
                            <h4 class="text-xs font-semibold text-slate-300">
                                Na beoordeling
                            </h4>
                            <p class="text-xs text-slate-400">
                                Eén definitieve betaling:
                                {{ euro(selected.amount) }}. De reservering
                                verdwijnt uit het actieve overzicht en blijft in
                                Historie.
                            </p>
                            <div
                                v-for="row in finalSplit"
                                :key="row.budgetItemId"
                                class="flex justify-between gap-3 text-xs"
                            >
                                <span>{{ itemName(row.budgetItemId) }}</span
                                ><strong class="font-mono text-indigo-200">{{
                                    euro(row.amount)
                                }}</strong>
                            </div>
                            <p
                                v-if="!finalSplit.length"
                                class="text-xs text-slate-400"
                            >
                                {{
                                    linkChoice === 'reservation'
                                        ? (reservation.budgetItemName ??
                                          reservation.budgetItemId ??
                                          'Geen gekoppelde begrotingspost')
                                        : (selected.budgetItemName ??
                                          selected.budgetItemId ??
                                          (linkChoice === 'merge'
                                              ? (reservation.budgetItemName ??
                                                reservation.budgetItemId ??
                                                'Geen gekoppelde begrotingspost')
                                              : 'Geen gekoppelde begrotingspost'))
                                }}
                            </p>
                        </div>
                    </section>
                </template>
                <p
                    v-else
                    class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-4 text-sm leading-relaxed text-amber-100"
                >
                    Deze reservering gaat naar Historie en telt nergens meer
                    mee. Dezelfde voorlopige banklevering maakt haar niet
                    opnieuw actief. Als de bank later alsnog een definitieve
                    betaling aanlevert, wordt die wel verwerkt.
                </p>
                <label class="flex items-start gap-2 text-sm text-slate-300"
                    ><input
                        v-model="confirmed"
                        type="checkbox"
                        :disabled="saving"
                        class="mt-1"
                    />{{
                        action === 'match'
                            ? 'Ik heb gecontroleerd dat dit dezelfde betaling is en wil deze koppelingen behouden.'
                            : 'Ik heb gecontroleerd dat deze reservering is vervallen.'
                    }}</label
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
                        Open laten staan</button
                    ><button
                        type="button"
                        :disabled="!canSave"
                        class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        @click="save"
                    >
                        <CheckCircle2 class="h-4 w-4" />{{
                            saving
                                ? 'Opslaan…'
                                : action === 'match'
                                  ? 'Koppelen en reservering archiveren'
                                  : 'Als vervallen archiveren'
                        }}
                    </button>
                </div>
            </template>
        </section>
    </ModalShell>
</template>
