<script setup lang="ts">
import { ArrowRight, ArrowRightLeft, X } from 'lucide-vue-next';
import {
    euro,
    monthLabel,
    signedEuro,
    type forecastMonthComparison,
} from '../forecast';
import ModalShell from './ModalShell.vue';
defineProps<{
    comparison: NonNullable<ReturnType<typeof forecastMonthComparison>>;
    onClose: () => void;
}>();
const emit = defineEmits<{ openItem: [itemId: string] }>();
</script>

<template>
    <ModalShell :on-close="onClose">
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="forecast-month-comparison-title"
            class="max-h-[90vh] w-full max-w-4xl space-y-4 overflow-y-auto rounded-2xl border border-indigo-500/25 bg-slate-900 p-5 shadow-xl"
        >
            <div class="flex items-start justify-between gap-3">
                <h2
                    id="forecast-month-comparison-title"
                    class="flex items-center gap-2 text-lg font-bold text-white"
                >
                    <ArrowRightLeft
                        class="h-5 w-5 shrink-0 text-indigo-400"
                    />Afwijkingen buiten vaste begroting ·
                    {{ monthLabel(comparison.month) }}
                </h2>
                <button type="button" aria-label="Sluiten" @click="onClose">
                    <X class="h-5 w-5 text-slate-400" />
                </button>
            </div>
            <div
                class="flex flex-wrap items-center gap-3 rounded-xl border border-indigo-500/20 bg-indigo-500/[0.06] p-4 text-sm"
            >
                <span class="text-slate-300"
                    >{{ monthLabel(comparison.previousMonth) }}:
                    <strong class="font-mono text-white">{{
                        euro(comparison.before)
                    }}</strong></span
                ><ArrowRight class="h-4 w-4 text-slate-500" /><span
                    class="text-slate-300"
                    >{{ monthLabel(comparison.month) }}:
                    <strong class="font-mono text-white">{{
                        euro(comparison.after)
                    }}</strong></span
                ><span class="ml-auto text-right"
                    ><span class="block text-[10px] text-slate-400"
                        >Afwijking buiten vaste begroting</span
                    ><strong
                        class="font-mono"
                        :class="
                            comparison.difference >= 0
                                ? 'text-emerald-300'
                                : 'text-rose-300'
                        "
                        >{{ signedEuro(comparison.difference) }}</strong
                    ></span
                >
            </div>
            <p class="text-xs leading-relaxed text-slate-400">
                Alleen veranderingen buiten de vastgelegde begrotingsposten
                worden getoond. Normale betaalmaanden, zoals kinderbijslag per
                kwartaal, en begrote maandverschillen geven geen melding. Het
                getoonde effect is de afwijking na correctie voor die vaste
                begroting. De maandtotalen hierboven bevatten alle vaste posten.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-slate-700 text-xs text-slate-400"
                    >
                        <tr>
                            <th class="p-2">Afwijkende post</th>
                            <th class="p-2 text-right">Vorige maand</th>
                            <th class="p-2 text-right">Deze maand</th>
                            <th class="p-2 text-right">
                                Afwijking vrije ruimte
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-for="row in comparison.rows" :key="row.itemId">
                            <td class="p-3">
                                <button
                                    type="button"
                                    class="text-left font-semibold underline decoration-current/30 underline-offset-4 hover:text-indigo-300"
                                    :aria-label="`Post bekijken: ${row.name}`"
                                    @click="emit('openItem', row.itemId)"
                                    :class="
                                        row.type === 'inkomsten'
                                            ? 'text-emerald-300'
                                            : row.type === 'sparen'
                                              ? 'text-blue-300'
                                              : 'text-rose-300'
                                    "
                                >
                                    {{ row.name }}
                                </button>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ row.reason }}
                                </p>
                                <p class="mt-1 text-[10px] text-slate-500">
                                    Vaste begroting:
                                    {{ euro(row.expectedBefore) }} →
                                    {{ euro(row.expectedAfter) }}
                                </p>
                                <p class="mt-1 text-[10px] text-slate-500">
                                    Bron:
                                    {{
                                        row.previousSource
                                            ? monthLabel(row.previousSource)
                                            : 'basisbedrag'
                                    }}
                                    →
                                    {{
                                        row.currentSource
                                            ? monthLabel(row.currentSource)
                                            : 'basisbedrag'
                                    }}
                                </p>
                            </td>
                            <td
                                class="p-2 text-right font-mono whitespace-nowrap text-slate-400"
                            >
                                {{ euro(row.before) }}
                            </td>
                            <td
                                class="p-2 text-right font-mono whitespace-nowrap text-white"
                            >
                                {{ euro(row.after) }}
                            </td>
                            <td
                                class="p-2 text-right font-mono font-semibold whitespace-nowrap"
                                :class="
                                    row.effect >= 0
                                        ? 'text-emerald-300'
                                        : 'text-rose-300'
                                "
                            >
                                {{ signedEuro(row.effect) }}
                            </td>
                        </tr>
                        <tr v-if="!comparison.rows.length">
                            <td
                                colspan="4"
                                class="p-4 text-center text-slate-400"
                            >
                                Geen afwijkingen buiten de vaste begroting.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-slate-700">
                        <tr>
                            <th colspan="3" class="p-3 text-slate-300">
                                Totaal afwijkingen buiten vaste begroting
                            </th>
                            <td
                                class="p-2 text-right font-mono font-bold whitespace-nowrap"
                                :class="
                                    comparison.difference >= 0
                                        ? 'text-emerald-300'
                                        : 'text-rose-300'
                                "
                            >
                                {{ signedEuro(comparison.difference) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p
                class="rounded-xl border border-slate-700 p-3 text-xs leading-relaxed text-slate-400"
            >
                Is een post zoals benzine iedere maand nodig, maar ontbreekt zij
                in een historische maand? Kies bij die post Afwijking instellen
                → Iedere maand. Je bewaart die keuze alleen in deze prognose.
                Bij kwartaalbetalingen blijven de bestaande betaalmaanden
                behouden.
            </p>
        </section>
    </ModalShell>
</template>
