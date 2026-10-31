<script setup lang="ts">
import {
    ArrowDownCircle,
    ArrowUpCircle,
    History,
    PiggyBank,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    euro,
    groupForecastItems,
    monthLabel,
    signedEuro,
    type ForecastSetting,
    type ForecastSettingChange,
    type ForecastSnapshot,
} from '../forecast';

const props = defineProps<{
    snapshot: ForecastSnapshot;
    month: ForecastSnapshot['months'][number];
    editable?: boolean;
    settings?: ForecastSetting[];
}>();
const emit = defineEmits<{
    history: [item: { id: string; name: string }];
    setting: [change: ForecastSettingChange];
}>();
const settingFor = (id: string) =>
    props.settings?.find((setting) => setting.itemId === id);
const groups = computed(() => groupForecastItems(props.snapshot, props.month));
const tones = {
    inkomsten: {
        icon: ArrowUpCircle,
        header: 'border-emerald-500/50 bg-emerald-500/10',
        badge: 'bg-emerald-500/10 text-emerald-300',
        total: 'bg-emerald-500/[0.04]',
    },
    uitgaven: {
        icon: ArrowDownCircle,
        header: 'border-rose-500/50 bg-rose-500/10',
        badge: 'bg-rose-500/10 text-rose-300',
        total: 'bg-rose-500/[0.04]',
    },
    sparen: {
        icon: PiggyBank,
        header: 'border-blue-500/50 bg-blue-500/10',
        badge: 'bg-blue-500/10 text-blue-300',
        total: 'bg-blue-500/[0.04]',
    },
};
</script>

<template>
    <div class="overflow-x-auto">
        <table
            class="w-full text-left text-sm"
            aria-label="Begrotingsposten per soort"
        >
            <thead
                class="border-b border-slate-700 bg-slate-950/30 text-[11px] font-semibold tracking-wide text-slate-400"
            >
                <tr>
                    <th v-if="snapshot.version >= 3" class="p-2">Meenemen</th>
                    <th class="p-2">Post</th>
                    <th class="p-2 text-right">Begroting</th>
                    <th class="p-2 text-right">Prognose</th>
                    <th class="p-2 text-right">Verschil</th>
                    <th class="p-2 text-right">Historie</th>
                </tr>
            </thead>
            <tbody
                v-for="group in groups"
                :key="group.type"
                class="divide-y divide-slate-800"
            >
                <tr class="border-t-8 border-slate-900">
                    <th
                        :colspan="snapshot.version >= 3 ? 6 : 5"
                        scope="rowgroup"
                        class="border-l-4 px-3 py-3 text-xs font-bold tracking-wide uppercase"
                        :class="[group.color, tones[group.type].header]"
                    >
                        <span class="flex items-center gap-2"
                            ><component
                                :is="tones[group.type].icon"
                                class="h-4 w-4"
                                aria-hidden="true"
                            />{{ group.label
                            }}<span
                                class="ml-auto rounded-md px-2 py-0.5 text-[10px] font-medium tracking-normal normal-case"
                                :class="tones[group.type].badge"
                                >{{ group.rows.length }}
                                {{
                                    group.rows.length === 1 ? 'post' : 'posten'
                                }}</span
                            ></span
                        >
                    </th>
                </tr>
                <tr
                    v-for="item in group.rows"
                    :key="item.id"
                    class="transition-colors hover:bg-slate-800/40"
                >
                    <td v-if="snapshot.version >= 3" class="p-2 align-top">
                        <label
                            class="flex items-center gap-1.5 text-[10px] whitespace-nowrap text-slate-300"
                            ><input
                                type="checkbox"
                                :checked="
                                    settingFor(item.id)?.included !== false
                                "
                                :disabled="!editable"
                                :aria-label="`${item.name} meenemen in hele prognose`"
                                @change="
                                    emit('setting', {
                                        itemId: item.id,
                                        included: (
                                            $event.target as HTMLInputElement
                                        ).checked,
                                    })
                                "
                            />Hele prognose</label
                        >
                        <label
                            class="mt-2 flex items-center gap-1.5 text-[10px] whitespace-nowrap text-slate-400"
                            ><input
                                type="checkbox"
                                :checked="
                                    !settingFor(
                                        item.id,
                                    )?.excludedMonths.includes(month.month)
                                "
                                :disabled="
                                    !editable ||
                                    settingFor(item.id)?.included === false
                                "
                                :aria-label="`${item.name} meenemen in ${monthLabel(month.month)}`"
                                @change="
                                    emit('setting', {
                                        itemId: item.id,
                                        month: month.month,
                                        monthIncluded: (
                                            $event.target as HTMLInputElement
                                        ).checked,
                                    })
                                "
                            />Deze maand</label
                        >
                    </td>
                    <td class="px-3 py-3">
                        <span
                            class="block"
                            :class="
                                item.cell.omitted
                                    ? 'text-slate-500 line-through'
                                    : 'text-white'
                            "
                            >{{ item.name }}</span
                        ><span class="block text-xs text-slate-500">{{
                            item.group
                        }}</span>
                        <p
                            v-if="snapshot.version >= 3"
                            class="mt-1 text-[10px] leading-relaxed"
                            :class="
                                item.cell.omitted
                                    ? 'text-amber-300'
                                    : 'text-slate-400'
                            "
                        >
                            {{
                                item.cell.omitted
                                    ? 'Niet meegeteld'
                                    : month.beforeItems[item.id].scheduled
                                      ? 'Meegeteld'
                                      : 'Geen betaling deze maand'
                            }}
                            ·
                            {{
                                item.cell.sourceMonth
                                    ? `Bron: ${monthLabel(item.cell.sourceMonth)}`
                                    : 'Basisbedrag van post'
                            }}<span v-if="item.cell.adjusted">
                                · aangepast met een regel</span
                            >
                        </p>
                        <p
                            v-if="
                                snapshot.version >= 3 &&
                                (item.amountVariants?.length ?? 0) > 1 &&
                                !settingFor(item.id)
                            "
                            class="mt-1 text-[10px] text-amber-300"
                        >
                            Verschillende bronbedragen: controleer de
                            bedragkeuze.
                        </p>
                        <select
                            v-if="snapshot.version >= 3 && editable"
                            :aria-label="`Bedragbasis ${item.name}`"
                            :value="settingFor(item.id)?.basis ?? 'profile'"
                            class="mt-2 w-full min-w-[180px] rounded-lg border border-slate-700 bg-slate-800 p-1.5 text-[10px] text-slate-200"
                            @change="
                                emit('setting', {
                                    itemId: item.id,
                                    basis: ($event.target as HTMLSelectElement)
                                        .value as 'profile' | 'latest',
                                })
                            "
                        >
                            <option value="profile">
                                Maandprofiel behouden
                            </option>
                            <option
                                value="latest"
                                :disabled="!item.latestFixed"
                            >
                                Laatste vaste bedrag{{
                                    item.latestFixed
                                        ? `: ${euro(item.latestFixed.amount)}${item.latestFixed.sourceMonth ? ` (${monthLabel(item.latestFixed.sourceMonth)})` : ''}`
                                        : ''
                                }}
                            </option>
                        </select>
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap text-slate-400 tabular-nums"
                    >
                        {{ euro(item.before) }}
                    </td>
                    <td
                        class="p-2 text-right font-mono font-semibold whitespace-nowrap"
                        :class="group.color"
                    >
                        {{ euro(item.after) }}
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap tabular-nums"
                        :class="
                            item.after !== item.before
                                ? 'text-indigo-300'
                                : 'text-slate-500'
                        "
                    >
                        {{
                            signedEuro(
                                Math.round((item.after - item.before) * 100) /
                                    100,
                            )
                        }}
                    </td>
                    <td class="p-2 text-right">
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-indigo-500/10 hover:text-indigo-300 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:outline-none"
                            :aria-label="`Wijzigingshistorie ${item.name}`"
                            @click="emit('history', item)"
                        >
                            <History class="h-4 w-4" />
                        </button>
                    </td>
                </tr>
                <tr class="font-semibold" :class="tones[group.type].total">
                    <th
                        scope="row"
                        :colspan="snapshot.version >= 3 ? 2 : 1"
                        class="p-2"
                        :class="group.color"
                    >
                        Totaal {{ group.label.toLowerCase() }}
                    </th>
                    <td
                        class="p-2 text-right whitespace-nowrap text-slate-400 tabular-nums"
                    >
                        {{ euro(group.before) }}
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap text-white tabular-nums"
                    >
                        {{ euro(group.after) }}
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap text-indigo-300 tabular-nums"
                    >
                        {{
                            signedEuro(
                                Math.round((group.after - group.before) * 100) /
                                    100,
                            )
                        }}
                    </td>
                    <td />
                </tr>
            </tbody>
            <tbody v-if="!groups.length">
                <tr>
                    <td
                        :colspan="snapshot.version >= 3 ? 6 : 5"
                        class="p-4 text-center text-slate-400"
                    >
                        Geen geplande posten in deze maand.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
