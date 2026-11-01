<script setup lang="ts">
import { Info } from 'lucide-vue-next';
import {
    euro,
    monthLabel,
    signedEuro,
    type ForecastPostContext,
} from '../forecast';
defineProps<{ context: ForecastPostContext }>();
</script>

<template>
    <section
        class="space-y-3 rounded-xl border border-indigo-500/30 bg-indigo-500/[0.07] p-3.5"
        aria-label="Uitleg prognosebedrag"
    >
        <h4
            class="flex items-center gap-2 text-xs font-semibold text-indigo-200"
        >
            <Info class="h-4 w-4" />Gebruikt in prognose ·
            {{ monthLabel(context.month) }}
        </h4>
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <p class="text-[10px] text-slate-400">
                    {{
                        context.explicit
                            ? 'Gepland maandbedrag'
                            : `Actuele basis vóór ${monthLabel(context.startMonth)}`
                    }}
                </p>
                <strong class="mt-1 block font-mono text-sm text-indigo-200">{{
                    euro(context.basisAmount)
                }}</strong>
                <p class="mt-1 text-[10px] text-slate-500">
                    {{
                        context.explicit
                            ? 'Expliciete begroting gaat voor'
                            : `Basismaand: ${monthLabel(context.basisPeriod)}`
                    }}<span class="block"
                        >Bron:
                        {{
                            context.basisMonth
                                ? monthLabel(context.basisMonth)
                                : 'basisbedrag van post'
                        }}</span
                    >
                </p>
            </div>
            <div>
                <p class="text-[10px] text-slate-400">
                    Historische maandreferentie
                </p>
                <strong class="mt-1 block font-mono text-sm text-amber-200">{{
                    euro(context.referenceAmount)
                }}</strong>
                <p class="mt-1 text-[10px] text-slate-500">
                    {{
                        context.referenceMonth
                            ? monthLabel(context.referenceMonth)
                            : 'ongedateerd basisbedrag'
                    }}
                </p>
            </div>
            <div>
                <p class="text-[10px] text-slate-400">
                    Prognose na aanpassingen
                </p>
                <strong class="mt-1 block font-mono text-sm text-white">{{
                    euro(context.amount)
                }}</strong>
                <p class="mt-1 text-[10px] text-slate-500">
                    {{
                        !context.included
                            ? 'Uitgesloten van deze berekening'
                            : !context.scheduled
                              ? 'Geen betaling in deze maand'
                              : 'Telt mee in deze maand'
                    }}
                </p>
            </div>
        </div>
        <p
            v-if="context.basisAmount !== context.referenceAmount"
            class="text-[11px] leading-relaxed text-amber-200"
        >
            De actuele basis wijkt
            {{
                signedEuro(
                    Math.round(
                        (context.basisAmount - context.referenceAmount) * 100,
                    ) / 100,
                )
            }}
            af van de historische maandreferentie.
            {{
                context.basis === 'latest'
                    ? 'De prognose gebruikt de actuele basis op de betaalmaanden.'
                    : 'Voor deze prognose is bewust het afwijkende maandprofiel gekozen.'
            }}
        </p>
        <p class="text-[11px] text-slate-300">
            Behandeling:
            {{
                context.costMode === 'fixed'
                    ? 'vast meetellen, alleen in deze prognose'
                    : context.costMode === 'extra'
                      ? 'als extra apart houden, alleen in deze prognose'
                      : 'Automatisch (standaard)'
            }}.
        </p>
        <p class="text-[11px] text-slate-300">
            Betaalritme:
            {{
                context.monthly
                    ? 'iedere maand'
                    : 'bestaande betaalmaanden behouden, bijvoorbeeld per kwartaal'
            }}.
        </p>
        <p
            v-if="context.monthly && context.cadenceReason === 'monthly-start'"
            class="text-[11px] leading-relaxed text-indigo-200"
        >
            Maandelijkse reeks herkend: eerst maanden zonder bedrag, daarna
            minstens twee aaneengesloten betaalmaanden. De nullen vóór het begin
            van die reeks worden niet ieder jaar herhaald. Een einddatum volgt
            niet uit dit patroon.
        </p>
        <p
            v-if="context.monthly && context.cadenceReason === 'monthly-stop'"
            class="text-[11px] leading-relaxed text-indigo-200"
        >
            De laatst bekende begroting staat op €0 na een aaneengesloten
            maandelijkse betaalreeks. De prognose houdt deze nul aan en herhaalt
            de eerdere betaalde maanden niet automatisch.
        </p>
        <p
            v-if="
                context.monthly &&
                context.cadenceReason === 'monthly-continuation'
            "
            class="text-[11px] leading-relaxed text-indigo-200"
        >
            Maandelijks ritme herkend: er zijn ten minste acht betaalmaanden en
            de laatste maanden vormen een doorlopende reeks. De laatst bekende
            begroting wordt iedere maand voortgezet; een oud ontbrekend
            maandbedrag wordt niet jaarlijks herhaald.
        </p>
        <p class="text-[10px] leading-relaxed text-slate-500">
            De referentie en basis zijn begrotingsbedragen. De transacties
            rechts tonen de werkelijke betalingen ter controle.
        </p>
    </section>
</template>
