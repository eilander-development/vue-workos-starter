<script setup lang="ts">
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

defineProps<{ open: boolean; categories: any[]; budgetId: string; }>();
const emit = defineEmits<{ updateOpen: [value: boolean]; updateBudgetId: [value: string]; submit: []; }>();
</script>
<template>
  <Dialog :open="open" @update:open="(open) => emit('updateOpen', open)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader class="space-y-2"><DialogTitle>Zet matched transacties om</DialogTitle></DialogHeader>
      <div class="space-y-3">
        <select :value="budgetId" @change="emit('updateBudgetId', ($event.target as HTMLSelectElement).value)" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
          <option value="" disabled>Kies nieuw budget</option>
          <optgroup v-for="c in categories" :key="c.id" :label="c.name">
            <option v-for="b in c.budgets ?? []" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
          </optgroup>
        </select>
        <p class="text-xs text-muted-foreground">Alleen transacties met dezelfde matchvoorwaarde worden aangepast.</p>
      </div>
      <DialogFooter>
        <Button type="button" variant="secondary" @click="emit('updateOpen', false)">Annuleren</Button>
        <Button type="button" :disabled="!budgetId" @click="emit('submit')">Omzetten</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
