<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="dialog-header"
    :class="cn('z-10 border-b bg-background px-6 py-4 flex flex-col gap-2 text-center sm:text-left', props.class)"
  >
    <slot />
  </div>
</template>
