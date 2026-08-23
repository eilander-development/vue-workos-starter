<script setup lang="ts">
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
  >
    <div :class="size === 'sm' ? 'text-[11px] text-slate-400' : 'text-slate-200'">
      {{ date }}
    </div>
    <div
      v-if="time"
      :class="size === 'sm' ? 'text-[9px] text-slate-500' : 'text-[10px] text-slate-500'"
    >
      {{ time }}
    </div>
  </div>
</template>
