<script setup lang="ts">
import { computed } from 'vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'

const props = defineProps<{
  type: 'success' | 'error' | 'info'
  message: string
}>()

const variant = computed(() => (props.type === 'error' ? 'destructive' : 'default'))
const title = computed(() => {
  if (props.type === 'error') {
    return 'Fout'
  }

  if (props.type === 'info') {
    return 'Info'
  }

  return 'Gelukt'
})
</script>

<template>
  <Alert :variant="variant" class="space-y-1">
    <AlertTitle>{{ title }}</AlertTitle>
    <AlertDescription>{{ props.message }}</AlertDescription>
  </Alert>
</template>
