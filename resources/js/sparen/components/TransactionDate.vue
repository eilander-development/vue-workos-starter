<script setup lang="ts">
function displayDate(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})(?:T.*)?$/.exec(value);
  return match ? `${match[3]}-${match[2]}-${match[1]}` : value;
}

withDefaults(
  defineProps<{
    date: string;
    time?: string;
    align?: "left" | "right";
    size?: "sm" | "md";
  }>(),
  {
    align: "left",
    size: "md",
  }
);
</script>

<template>
  <div
    class="font-mono whitespace-nowrap leading-tight"
    :class="align === 'right' ? 'text-right' : ''"
    title="Boekdatum"
  >
    <div :class="size === 'sm' ? 'text-[11px] text-slate-400' : 'text-slate-200'">
      {{ displayDate(date) }}
    </div>
    <div
      v-if="time"
      :class="size === 'sm' ? 'text-[9px] text-slate-500' : 'text-[10px] text-slate-500'"
    >
      {{ time }}
    </div>
  </div>
</template>
