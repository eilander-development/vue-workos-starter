<script setup lang="ts">
import { CalendarDays, History, TriangleAlert, X } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    euro,
    monthLabel,
    type ForecastSnapshot,
    type ForecastWarning,
} from '../forecast';
import ModalShell from './ModalShell.vue';
const props = defineProps<{
    month: ForecastSnapshot['months'][number];
    onClose: () => void;
}>();
const historical = computed(
    () =>
        props.month.warnings?.filter(
            (warning) => warning.kind === 'historical',
        ) ?? [],
);
const planned = computed(
    () =>
        props.month.warnings?.filter((warning) => warning.kind === 'planned') ??
        [],
);
const sum = (rows: ForecastWarning[]) =>
    rows.reduce((total, row) => total + Math.round(row.amount * 100), 0) / 100;
const groups = computed(() =>
    [
        {
            key: 'historical',
            label: 'Historische referentie — geen voorspelling',
            description:
                'Begrote bedragen uit eerdere maanden of ongedateerde referentiebedragen. Dit zijn geen bankbetalingen en geen verwachte kosten voor deze prognosemaand. Ze tellen niet mee in de structurele vrije ruimte.',
            rows: historical.value,
        },
        {
            key: 'planned',
            label: 'Expliciet geplande extra kosten',
            description:
                'Deze bedragen staan al begroot voor de toekomstige maand of volgen uit een doorgevoerde wijziging. Ze staan buiten de structurele vrije ruimte, maar kunnen die maand wel geld kosten.',
            rows: planned.value,
        },
    ].filter((group) => group.rows.length),
);
const reason = (row: ForecastWarning) =>
    ({
        marked_extra: 'Als extra kosten ingesteld',
        deferred: 'Klarna / creditcard',
        month_entries: 'Losse begrotingsregels',
    })[row.reason];
</script>

<template>
    <ModalShell :on-close="onClose">
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="forecast-extras-title"
            class="max-h-[90vh] w-full max-w-3xl space-y-5 overflow-y-auto rounded-2xl border border-amber-500/25 bg-slate-900 p-5 shadow-xl shadow-black/20"
        >
            <div class="flex items-start justify-between gap-3">
                <h2
                    id="forecast-extras-title"
                    class="flex items-center gap-2 text-lg font-bold text-white"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-300"
                        ><TriangleAlert class="h-4 w-4" aria-hidden="true"
                    /></span>
                    Extra kosten bij {{ monthLabel(month.month) }}
                </h2>
                <button
                    type="button"
                    aria-label="Sluiten"
                    class="p-1 text-slate-400 hover:text-white"
                    @click="onClose"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
            <section v-for="group in groups" :key="group.key" class="space-y-3">
                <h3
                    class="flex items-center gap-2 font-semibold"
                    :class="
                        group.key === 'historical'
                            ? 'text-amber-300'
                            : 'text-violet-300'
                    "
                >
                    <History
                        v-if="group.key === 'historical'"
                        class="h-4 w-4 shrink-0"
                        aria-hidden="true"
                    /><CalendarDays
                        v-else
                        class="h-4 w-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ group.label }}
                </h3>
                <p class="text-sm text-slate-400">{{ group.description }}</p>
                <article
                    v-for="row in group.rows"
                    :key="row.itemId"
                    class="space-y-2 rounded-xl border border-l-4 p-3 text-sm"
                    :class="
                        group.key === 'historical'
                            ? 'border-amber-500/20 border-l-amber-400 bg-amber-500/[0.04]'
                            : 'border-violet-500/20 border-l-violet-400 bg-violet-500/[0.04]'
                    "
                >
                    <div class="flex justify-between gap-3">
                        <strong class="text-white">{{ row.name }}</strong
                        ><strong
                            class="font-mono whitespace-nowrap"
                            :class="
                                group.key === 'historical'
                                    ? 'text-amber-300'
                                    : 'text-violet-300'
                            "
                            >{{ euro(row.amount) }}</strong
                        >
                    </div>
                    <p class="text-xs text-slate-400">
                        {{ row.group }} · {{ reason(row) }}
                    </p>
                    <p class="text-xs text-amber-200">
                        {{
                            group.key === 'historical'
                                ? 'Referentie'
                                : 'Begrotingsbron'
                        }}:
                        {{
                            row.sourceMonth
                                ? monthLabel(row.sourceMonth)
                                : 'ongedateerd basisbedrag'
                        }}
                        ·
                        {{
                            group.key === 'historical'
                                ? 'niet voorspeld voor deze maand'
                                : 'apart gepland'
                        }}
                    </p>
                    <details
                        v-if="row.entries?.length"
                        class="text-xs text-slate-400"
                    >
                        <summary class="cursor-pointer">
                            Begrotingsregels bekijken
                        </summary>
                        <ul class="mt-2 space-y-1">
                            <li
                                v-for="(entry, index) in row.entries"
                                :key="entry.id || index"
                                class="flex justify-between gap-3"
                            >
                                <span>{{
                                    entry.description || 'Zonder omschrijving'
                                }}</span
                                ><span class="whitespace-nowrap">{{
                                    euro(entry.amount)
                                }}</span>
                            </li>
                        </ul>
                    </details>
                </article>
                <p class="text-right text-sm text-slate-300">
                    {{
                        group.key === 'historical'
                            ? 'Totaal historische referentie (niet aftrekken)'
                            : 'Totaal geplande extra kosten'
                    }}: <strong>{{ euro(sum(group.rows)) }}</strong>
                </p>
            </section>
            <div
                class="space-y-3 rounded-xl border border-indigo-500/20 bg-indigo-500/[0.06] p-4 text-sm text-slate-300"
            >
                <p class="flex justify-between gap-3">
                    <span>Structurele vrije ruimte</span
                    ><strong>{{ euro(month.after.free) }}</strong>
                </p>
                <p v-if="planned.length" class="flex justify-between gap-3">
                    <span>Na aftrek van expliciet geplande extra kosten</span
                    ><strong>{{
                        euro(
                            Math.round(
                                (month.after.free - sum(planned)) * 100,
                            ) / 100,
                        )
                    }}</strong>
                </p>
                <p class="text-xs text-slate-500">
                    Een vaste maandelijkse aflossing kun je bij de
                    begrotingspost onder ‘Meenemen in prognoses’ als Vast
                    instellen.
                </p>
            </div>
        </section>
    </ModalShell>
</template>
