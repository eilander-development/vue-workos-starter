<script setup lang="ts">
defineProps<{
  fixedAmount: number;
  monthAmount: number;
}>();

function formatAmount(amount: number): string {
  return amount.toLocaleString("nl-NL", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<template>
  <div class="space-y-1.5 text-[11px]">
    <div class="flex flex-wrap items-baseline justify-between gap-x-2 rounded-lg border border-blue-900/50 bg-blue-950/30 px-2 py-1.5">
      <span class="flex items-center gap-1.5 font-medium text-blue-300">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-400" aria-hidden="true"></span>
        Vast
      </span>
      <span class="font-mono font-semibold text-blue-200">{{ formatAmount(fixedAmount) }}</span>
    </div>
    <div class="flex flex-wrap items-baseline justify-between gap-x-2 rounded-lg border border-violet-900/50 bg-violet-950/30 px-2 py-1.5">
      <span class="flex items-center gap-1.5 font-medium text-violet-300">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" aria-hidden="true"></span>
        Los deze maand
      </span>
      <span class="font-mono font-semibold text-violet-200">{{ formatAmount(monthAmount) }}</span>
    </div>
  </div>
</template>
