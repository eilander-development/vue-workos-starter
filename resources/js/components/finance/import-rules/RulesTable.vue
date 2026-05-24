<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { EllipsisVertical, Eye, Pencil, Trash2 } from 'lucide-vue-next';

defineProps<{
  rules: any[];
  rulesMeta: any;
  labelForCategory: (id: number) => string;
  labelForBudget: (id: number) => string;
}>();

const emit = defineEmits<{
  openTransactions: [rule: any];
  openSimilar: [rule: any];
  openEdit: [rule: any];
  openReassign: [rule: any];
  openDelete: [ruleId: number];
}>();
</script>

<template>
  <table class="w-full table-auto text-sm">
    <tbody>
      <tr v-for="rule in rules" :key="rule.id" class="group border-b border-slate-900 transition-colors hover:bg-muted/50">
        <td class="p-2">{{ rule.type === 'iban' ? 'Rekeningnummer bevat' : 'Omschrijving bevat' }}</td>
        <td class="p-2">{{ rule.match_value }}</td>
        <td class="p-2">{{ labelForCategory(rule.category_id) }}</td>
        <td class="p-2">{{ labelForBudget(rule.budget_id) }}</td>
        <td class="w-0 p-2 text-right">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button
                type="button"
                class="rounded-md p-1 text-muted-foreground opacity-0 transition hover:bg-muted group-hover:opacity-100 focus:opacity-100 focus-visible:opacity-100"
                aria-label="Meer acties"
              >
                <EllipsisVertical class="h-4 w-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-40">
              <DropdownMenuItem @click="emit('openTransactions', rule)">
                <Eye class="mr-2 h-4 w-4" />
                Transacties
              </DropdownMenuItem>
              <DropdownMenuItem @click="emit('openSimilar', rule)">
                <Eye class="mr-2 h-4 w-4" />
                Vergelijkbare transacties
              </DropdownMenuItem>
              <DropdownMenuItem @click="emit('openEdit', rule)">
                <Pencil class="mr-2 h-4 w-4" />
                Bewerken
              </DropdownMenuItem>
              <DropdownMenuItem @click="emit('openReassign', rule)">
                <Pencil class="mr-2 h-4 w-4" />
                Zet match om
              </DropdownMenuItem>
              <DropdownMenuItem @click="emit('openDelete', rule.id)">
                <Trash2 class="mr-2 h-4 w-4 text-red-500" />
                <span class="text-red-500">Verwijderen</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </td>
      </tr>
    </tbody>
  </table>
  <div v-if="rulesMeta && !Array.isArray(rulesMeta)" class="flex items-center justify-between border-t border-slate-900 bg-muted px-4 py-3 text-sm text-muted-foreground">
    <div>
      Toon {{ rulesMeta.from ?? 0 }} - {{ rulesMeta.to ?? 0 }} van {{ rulesMeta.total ?? 0 }} regels
    </div>
    <div class="flex items-center gap-2">
      <a
        :href="rulesMeta.prev_page_url || '#'"
        class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted"
        :class="{ 'pointer-events-none opacity-50': !rulesMeta.prev_page_url }"
      >
        Vorige
      </a>
      <a
        :href="rulesMeta.next_page_url || '#'"
        class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted"
        :class="{ 'pointer-events-none opacity-50': !rulesMeta.next_page_url }"
      >
        Volgende
      </a>
    </div>
  </div>
</template>
