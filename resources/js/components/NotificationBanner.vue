<script setup lang="ts">
import { computed } from 'vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { CircleCheck, CircleAlert, Info } from 'lucide-vue-next'

const props = defineProps<{
  type: 'success' | 'error' | 'info'
  message: string
}>()

const variant = computed(() => (props.type === 'error' ? 'destructive' : 'default'))
const typeClass = computed(() => {
  if (props.type === 'error') {
    return 'border-red-500/70 bg-red-950 text-red-100 [&_svg]:text-red-300'
  }

  if (props.type === 'info') {
    return 'border-sky-500/70 bg-sky-950 text-sky-100 [&_svg]:text-sky-300'
  }

  return 'border-emerald-500/70 bg-emerald-950 text-emerald-100 [&_svg]:text-emerald-300'
})
const title = computed(() => {
  if (props.type === 'error') {
    return 'Fout'
  }

  if (props.type === 'info') {
    return 'Info'
  }

  return 'Gelukt'
})
const Icon = computed(() => {
  if (props.type === 'error') return CircleAlert
  if (props.type === 'info') return Info
  return CircleCheck
})
</script>

<template>
  <Alert :variant="variant" :class="`space-y-1 shadow-lg ${typeClass}`">
    <component :is="Icon" class="h-4 w-4" />
    <AlertTitle>{{ title }}</AlertTitle>
    <AlertDescription>{{ props.message }}</AlertDescription>
  </Alert>
</template>
