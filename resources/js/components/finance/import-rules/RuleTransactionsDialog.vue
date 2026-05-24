<script setup lang="ts">
import { DialogClose, Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { customScrollbar } from '@/composables/scrollbar';

defineProps<{
  open: boolean;
  title: string;
  description?: string;
  transactions: any[];
  pagination: any;
  showActions?: boolean;
  showBulkButton?: boolean;
}>();

const emit = defineEmits<{
  updateOpen: [value: boolean];
  previous: [];
  next: [];
  applyOne: [transactionId: number];
  applyAll: [];
}>();
</script>

<template>
  <Dialog :open="open" @update:open="(value) => emit('updateOpen', value)">
    <DialogContent class="sm:max-w-4xl">
      <DialogHeader class="space-y-3">
        <DialogTitle>{{ title }}</DialogTitle>
        <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
      </DialogHeader>

      <div :class="`${customScrollbar} max-h-[60vh] overflow-y-auto overflow-x-auto rounded-xl border border-slate-800 bg-slate-950/20`">
        <table class="w-full table-auto text-sm">
          <thead class="sticky top-0 bg-slate-950">
            <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
              <th class="p-2">Datum</th>
              <th class="p-2">Omschrijving</th>
              <th class="p-2">IBAN</th>
              <th class="p-2">Categorie</th>
              <th class="p-2">Budget</th>
              <th class="p-2 text-right">Bedrag</th>
              <th v-if="showActions" class="p-2 text-right">Acties</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="transaction in transactions" :key="transaction.id" class="border-b border-slate-800">
              <td class="p-2 text-sm">{{ transaction.date }}</td>
              <td class="p-2 text-sm">{{ transaction.description }}</td>
              <td class="p-2 text-sm">{{ transaction.counterparty_iban ?? '-' }}</td>
              <td class="p-2 text-sm">{{ transaction.category_name ?? '-' }}</td>
              <td class="p-2 text-sm">{{ transaction.budget_name ?? '-' }}</td>
              <td class="p-2 text-right text-sm">{{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(transaction.amount) }}</td>
              <td v-if="showActions" class="p-2 text-right text-sm">
                <button type="button" class="rounded-md border border-input bg-background px-2 py-1 text-xs transition hover:bg-muted" @click="emit('applyOne', transaction.id)">Koppelen</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between border-t border-slate-800 px-4 py-3 text-sm text-muted-foreground">
        <div>Toon {{ pagination.from }} - {{ pagination.to }} van {{ pagination.total }} transacties</div>
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50" @click="emit('previous')" :disabled="pagination.current_page <= 1">Vorige</button>
          <button type="button" class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50" @click="emit('next')" :disabled="pagination.current_page >= pagination.last_page">Volgende</button>
        </div>
      </div>

      <DialogFooter class="mt-4 gap-2">
        <Button v-if="showBulkButton" @click="emit('applyAll')">Koppel alles</Button>
        <DialogClose as-child><Button variant="secondary">Sluiten</Button></DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
