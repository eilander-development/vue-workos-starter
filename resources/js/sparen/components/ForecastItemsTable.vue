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
    forecastBasisFor,
    forecastTableBreakdown,
    groupForecastItems,
    monthLabel,
    signedEuro,
    type ForecastSetting,
    type ForecastSettingChange,
    type ForecastSnapshot,
    type ForecastSource,
} from '../forecast';

const props = defineProps<{
    snapshot: ForecastSnapshot;
    month: ForecastSnapshot['months'][number];
    editable?: boolean;
    settings?: ForecastSetting[];
    source?: ForecastSource | null;
}>();
const emit = defineEmits<{
    history: [item: { id: string; name: string }];
    openItem: [itemId: string];
    setting: [change: ForecastSettingChange];
}>();
const settingFor = (id: string) =>
    props.settings?.find((setting) => setting.itemId === id);
const breakdown = computed(() =>
    forecastTableBreakdown(props.snapshot, props.month, props.source),
);
const groups = computed(() =>
    groupForecastItems(props.snapshot, props.month).map((group) => {
        const basis =
            group.rows.reduce(
                (sum, item) =>
                    sum + Math.round(breakdown.value[item.id].basis * 100),
                0,
            ) / 100;
        return {
            ...group,
            basis,
            basisDifference: Math.round((basis - group.before) * 100) / 100,
            ruleEffect: Math.round((group.after - basis) * 100) / 100,
        };
    }),
);
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
    <div class="mb-3 space-y-1 text-xs leading-relaxed text-slate-400">
        <p>
            <strong class="text-slate-300">Referentiebegroting:</strong> de
            oorspronkelijke maandbedragen, met de bronperiode per post.
            Historische bedragen dienen alleen als vergelijking.
        </p>
        <p>
            <strong class="text-indigo-200">Basis vóór regels:</strong> het
            bedrag op basis van de laatst bekende begroting, het betaalritme en
            jouw keuzes voor deze prognose. Het verschil met de referentie staat
            eronder.
        </p>
        <p>
            <strong class="text-violet-300">Effect regels:</strong> alleen de
            wijziging door jouw bedrag- of percentageregels. Basis + effect
            regels = prognose.
        </p>
    </div>
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
                    <th class="p-2 text-right">Referentiebegroting</th>
                    <th class="p-2 text-right">Basis vóór regels</th>
                    <th class="p-2 text-right text-violet-300">
                        Effect regels
                    </th>
                    <th class="p-2 text-right">Prognose</th>
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
                        :colspan="snapshot.version >= 3 ? 7 : 6"
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
                                :aria-label="`${item.name} meenemen in prognose`"
                                @change="
                                    emit('setting', {
                                        itemId: item.id,
                                        included: (
                                            $event.target as HTMLInputElement
                                        ).checked,
                                    })
                                "
                            />Meenemen in prognose</label
                        >
                    </td>
                    <td class="px-3 py-3">
                        <button
                            type="button"
                            class="block text-left font-medium underline decoration-indigo-500/30 underline-offset-4 transition-colors hover:text-indigo-300 hover:decoration-indigo-400"
                            :class="
                                item.cell.omitted
                                    ? 'text-slate-500 line-through'
                                    : 'text-white'
                            "
                            :aria-label="`Post bekijken: ${item.name}`"
                            @click="emit('openItem', item.id)"
                        >
                            {{ item.name }}</button
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
                                    : item.cell.excludedCost
                                      ? 'Extra: buiten structurele vrije ruimte'
                                      : item.cell.scheduled
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
                            v-if="snapshot.version >= 3"
                            class="mt-1 text-[10px] text-indigo-300"
                        >
                            {{
                                forecastBasisFor(
                                    item,
                                    snapshot,
                                    settings ?? [],
                                ) === 'latest'
                                    ? 'Laatste vaste bedrag'
                                    : 'Afwijkend maandprofiel'
                            }}
                            ·
                            {{
                                (settingFor(item.id)?.everyMonth ??
                                item.forecastMonthly)
                                    ? 'Iedere maand'
                                    : 'Bestaande betaalmaanden'
                            }}
                        </p>
                        <details
                            v-if="snapshot.version >= 3 && editable"
                            class="mt-2 max-w-sm text-xs text-slate-300"
                        >
                            <summary
                                class="cursor-pointer text-[11px] text-indigo-300"
                            >
                                Afwijking instellen
                            </summary>
                            <div
                                class="mt-2 space-y-3 rounded-lg border border-slate-700 bg-slate-950/50 p-3"
                            >
                                <p
                                    class="text-[10px] leading-relaxed text-indigo-200"
                                >
                                    Standaard is alles Automatisch. Een keuze
                                    hieronder geldt alleen voor deze prognose en
                                    wordt bewaard met Prognose opslaan.
                                </p>
                                <label
                                    v-if="item.type === 'uitgaven'"
                                    class="block text-[10px] text-slate-400"
                                    >Meenemen in deze prognose<select
                                        :aria-label="`Prognosebehandeling ${item.name}`"
                                        :value="
                                            settingFor(item.id)?.costMode ??
                                            'auto'
                                        "
                                        class="mt-1 block w-full rounded-lg border border-slate-700 bg-slate-800 p-1.5 text-slate-200"
                                        @change="
                                            emit('setting', {
                                                itemId: item.id,
                                                costMode: (
                                                    $event.target as HTMLSelectElement
                                                ).value as
                                                    | 'auto'
                                                    | 'fixed'
                                                    | 'extra',
                                            })
                                        "
                                    >
                                        <option value="auto">
                                            Automatisch (standaard)
                                        </option>
                                        <option value="fixed">
                                            Vast meetellen
                                        </option>
                                        <option value="extra">
                                            Als extra apart houden
                                        </option>
                                    </select></label
                                >
                                <p
                                    v-if="item.type === 'uitgaven'"
                                    class="text-[10px] leading-relaxed text-slate-400"
                                >
                                    {{
                                        settingFor(item.id)?.costMode ===
                                        'fixed'
                                            ? 'Deze post telt mee in de structurele vrije ruimte. Kies zo nodig Iedere maand als het om een maandelijkse aflossing gaat.'
                                            : settingFor(item.id)?.costMode ===
                                                'extra'
                                              ? 'Deze post staat apart bij extra kosten en telt niet mee in de structurele vrije ruimte. Actieve bedragregels worden bij doorvoeren wel verwerkt.'
                                              : 'Normale vaste posten tellen mee. Losse begrotingsregels en Klarna/creditcard staan apart als extra kosten. Voor normale posten hoef je niets te kiezen.'
                                    }}
                                </p>
                                <label class="block text-[10px] text-slate-400"
                                    >Bedragbasis
                                    <select
                                        :aria-label="`Bedragbasis ${item.name}`"
                                        :value="
                                            forecastBasisFor(
                                                item,
                                                snapshot,
                                                settings ?? [],
                                            )
                                        "
                                        class="mt-1 block w-full min-w-[180px] rounded-lg border border-slate-700 bg-slate-800 p-1.5 text-[10px] text-slate-200"
                                        @change="
                                            emit('setting', {
                                                itemId: item.id,
                                                basis: (
                                                    $event.target as HTMLSelectElement
                                                ).value as 'profile' | 'latest',
                                            })
                                        "
                                    >
                                        <option
                                            value="latest"
                                            :disabled="
                                                !item.latestFixed &&
                                                !(
                                                    settingFor(item.id)
                                                        ?.costMode ===
                                                        'fixed' &&
                                                    item.latestUnfiltered
                                                )
                                            "
                                        >
                                            Automatisch: laatste bekende
                                            begroting{{
                                                item.latestFixed
                                                    ? `: ${euro(item.latestFixed.amount)}${item.latestFixed.sourceMonth ? ` (${monthLabel(item.latestFixed.sourceMonth)})` : ''}`
                                                    : ''
                                            }}
                                        </option>
                                        <option value="profile">
                                            Maandprofiel behouden
                                        </option>
                                    </select>
                                </label>
                                <label class="block text-[10px] text-slate-400"
                                    >Betaalritme
                                    <select
                                        :aria-label="`Betaalritme ${item.name}`"
                                        :value="
                                            settingFor(item.id)?.everyMonth ===
                                            undefined
                                                ? 'auto'
                                                : settingFor(item.id)
                                                        ?.everyMonth
                                                  ? 'monthly'
                                                  : 'existing'
                                        "
                                        class="mt-1 block w-full rounded-lg border border-slate-700 bg-slate-800 p-1.5 text-[10px] text-slate-200"
                                        @change="
                                            ($event.target as HTMLSelectElement)
                                                .value === 'auto'
                                                ? emit('setting', {
                                                      itemId: item.id,
                                                      automaticCadence: true,
                                                  })
                                                : emit('setting', {
                                                      itemId: item.id,
                                                      everyMonth:
                                                          (
                                                              $event.target as HTMLSelectElement
                                                          ).value === 'monthly',
                                                  })
                                        "
                                    >
                                        <option value="auto">
                                            Automatisch (herkend betaalritme)
                                        </option>
                                        <option value="existing">
                                            Bestaande betaalmaanden
                                        </option>
                                        <option value="monthly">
                                            Iedere maand, ook bij historische
                                            gaten
                                        </option>
                                    </select>
                                </label>
                                <p class="text-[10px] text-slate-500">
                                    Bedragen in de begroting veranderen pas bij
                                    doorvoeren. Uitsluitingen hieronder gelden
                                    alleen voor deze prognose.
                                </p>
                                <button
                                    type="button"
                                    class="text-[10px] text-indigo-300 underline underline-offset-2"
                                    :aria-label="`${item.name} terug naar Automatisch`"
                                    @click="
                                        emit('setting', {
                                            itemId: item.id,
                                            reset: true,
                                        })
                                    "
                                >
                                    Terug naar Automatisch
                                </button>
                                <label
                                    class="mt-2 flex items-center gap-1.5 text-[10px] whitespace-nowrap text-slate-400"
                                    ><input
                                        type="checkbox"
                                        :checked="
                                            !settingFor(
                                                item.id,
                                            )?.excludedMonths.includes(
                                                month.month,
                                            )
                                        "
                                        :disabled="
                                            !editable ||
                                            settingFor(item.id)?.included ===
                                                false
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
                            </div>
                        </details>
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap text-slate-400 tabular-nums"
                    >
                        {{ euro(item.before) }}
                        <span
                            class="mt-1 block text-[10px] font-normal text-slate-500"
                            >{{ breakdown[item.id].referenceLabel }}</span
                        >
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap text-indigo-200 tabular-nums"
                    >
                        {{ euro(breakdown[item.id].basis) }}
                        <span class="mt-1 block text-[10px] text-slate-500">{{
                            breakdown[item.id].basisLabel
                        }}</span>
                        <span
                            class="mt-1 block text-[10px]"
                            :class="
                                breakdown[item.id].basisDifference
                                    ? 'text-indigo-300'
                                    : 'text-slate-500'
                            "
                            >vs referentie
                            {{
                                signedEuro(breakdown[item.id].basisDifference)
                            }}</span
                        >
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap tabular-nums"
                        :class="
                            breakdown[item.id].ruleEffect
                                ? 'text-violet-300'
                                : 'text-slate-500'
                        "
                    >
                        {{ signedEuro(breakdown[item.id].ruleEffect) }}
                        <span class="mt-1 block text-[10px]">{{
                            breakdown[item.id].ruleEffect
                                ? 'Door prognoseregels'
                                : 'Geen regeffect'
                        }}</span>
                    </td>
                    <td
                        class="p-2 text-right font-mono font-semibold whitespace-nowrap"
                        :class="group.color"
                    >
                        {{ euro(item.after) }}
                        <span
                            class="mt-1 block text-[10px] font-normal text-slate-400"
                            >vs referentie
                            {{
                                signedEuro(breakdown[item.id].totalDifference)
                            }}</span
                        >
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
                        class="p-2 text-right whitespace-nowrap text-indigo-200 tabular-nums"
                    >
                        {{ euro(group.basis) }}
                        <span
                            class="mt-1 block text-[10px] font-normal text-slate-400"
                            >vs referentie
                            {{ signedEuro(group.basisDifference) }}</span
                        >
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap text-violet-300 tabular-nums"
                    >
                        {{ signedEuro(group.ruleEffect) }}
                    </td>
                    <td
                        class="p-2 text-right whitespace-nowrap text-white tabular-nums"
                    >
                        {{ euro(group.after) }}
                        <span
                            class="mt-1 block text-[10px] font-normal text-slate-400"
                            >vs referentie
                            {{
                                signedEuro(
                                    Math.round(
                                        (group.after - group.before) * 100,
                                    ) / 100,
                                )
                            }}</span
                        >
                    </td>
                    <td />
                </tr>
            </tbody>
            <tbody v-if="!groups.length">
                <tr>
                    <td
                        :colspan="snapshot.version >= 3 ? 7 : 6"
                        class="p-4 text-center text-slate-400"
                    >
                        Geen geplande posten in deze maand.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
