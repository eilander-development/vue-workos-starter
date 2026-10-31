<script setup lang="ts">
import { Calculator, X } from 'lucide-vue-next';
import {
    euro,
    monthLabel,
    type ForecastSetting,
    type ForecastSettingChange,
    type ForecastSnapshot,
} from '../forecast';
import ForecastItemsTable from './ForecastItemsTable.vue';
import ModalShell from './ModalShell.vue';
defineProps<{
    snapshot: ForecastSnapshot;
    month: ForecastSnapshot['months'][number];
    settings: ForecastSetting[];
    editable: boolean;
    onClose: () => void;
}>();
const emit = defineEmits<{
    setting: [change: ForecastSettingChange];
    history: [item: { id: string; name: string }];
}>();
</script>
<template>
    <ModalShell :on-close="onClose">
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="forecast-calculation-title"
            class="max-h-[90vh] w-full max-w-6xl space-y-4 overflow-y-auto rounded-2xl border border-indigo-500/25 bg-slate-900 p-5 shadow-xl"
        >
            <div class="flex items-center justify-between gap-3">
                <h2
                    id="forecast-calculation-title"
                    class="flex items-center gap-2 text-lg font-bold text-white"
                >
                    <Calculator class="h-5 w-5 text-indigo-400" />Berekening en
                    bronnen · {{ monthLabel(month.month) }}
                </h2>
                <button type="button" aria-label="Sluiten" @click="onClose">
                    <X class="h-5 w-5 text-slate-400" />
                </button>
            </div>
            <p class="text-sm leading-relaxed text-slate-400">
                Hier staan de posten die deze berekening bepalen. Historische
                extra kosten tellen niet mee. Een post uitzetten geldt alleen
                voor deze prognose; haar echte begrotingsbedragen blijven
                behouden. Kies per post het laatste vaste bedrag of het
                maandprofiel. De betaalmaanden blijven hetzelfde en expliciet
                geplande latere bedragen gaan voor.
            </p>
            <div
                class="flex flex-wrap gap-3 rounded-xl border border-indigo-500/20 bg-indigo-500/[0.06] p-3 font-mono text-sm"
            >
                <span class="text-emerald-300">{{
                    euro(month.after.income)
                }}</span
                ><span class="text-slate-400">−</span
                ><span class="text-rose-300">{{
                    euro(month.after.expenses)
                }}</span
                ><span class="text-slate-400">−</span
                ><span class="text-blue-300">{{
                    euro(month.after.savings)
                }}</span
                ><span class="text-slate-400">=</span
                ><strong
                    :class="
                        month.after.free < 0
                            ? 'text-rose-300'
                            : 'text-indigo-300'
                    "
                    >{{ euro(month.after.free) }}</strong
                >
            </div>
            <ForecastItemsTable
                :snapshot="snapshot"
                :month="month"
                :settings="settings"
                :editable="editable"
                @setting="emit('setting', $event)"
                @history="emit('history', $event)"
            />
            <p
                v-if="month.warnings?.length"
                class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-amber-300"
            >
                {{ month.warnings.length }} extra
                {{
                    month.warnings.length === 1 ? 'post staat' : 'posten staan'
                }}
                apart bij historische referenties / extra kosten en worden niet
                in de bedragen hierboven opgeteld.
            </p>
        </section>
    </ModalShell>
</template>
