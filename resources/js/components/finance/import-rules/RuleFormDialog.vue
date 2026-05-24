<script setup lang="ts">
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Form } from '@inertiajs/vue3';

const props = defineProps<{
  open: boolean;
  mode: 'create' | 'edit';
  categories: any[];
  ruleType: 'iban' | 'description';
  placeholder: string;
  activeRule?: any | null;
}>();

const emit = defineEmits<{
  updateOpen: [value: boolean];
  updateType: [value: 'iban' | 'description'];
}>();
</script>

<template>
  <Dialog :open="open" @update:open="(value) => emit('updateOpen', value)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader class="space-y-3">
        <DialogTitle>{{ mode === 'create' ? 'Koppelregel toevoegen' : 'Koppelregel wijzigen' }}</DialogTitle>
      </DialogHeader>

      <Form
        v-if="mode === 'create'"
        id="rule-create-form"
        action="/imports/transactions/rules"
        method="post"
        class="grid gap-3"
      >
        <select :value="ruleType" @change="emit('updateType', ($event.target as HTMLSelectElement).value as 'iban' | 'description')" name="type" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
          <option value="iban">Rekeningnummer bevat</option>
          <option value="description">Omschrijving bevat</option>
        </select>
        <input name="match_value" :placeholder="placeholder" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required />
        <select name="category_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select name="budget_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required>
          <optgroup v-for="c in categories" :key="c.id" :label="c.name">
            <option v-for="b in c.budgets ?? []" :key="b.id" :value="b.id">{{ b.name }}</option>
          </optgroup>
        </select>
      </Form>

      <Form
        v-else-if="activeRule"
        id="rule-edit-form"
        :action="`/imports/transactions/rules/${activeRule.id}`"
        method="patch"
        class="grid gap-3"
      >
        <select :value="ruleType" @change="emit('updateType', ($event.target as HTMLSelectElement).value as 'iban' | 'description')" name="type" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
          <option value="iban">Rekeningnummer bevat</option>
          <option value="description">Omschrijving bevat</option>
        </select>
        <input name="match_value" :value="activeRule.match_value" :placeholder="placeholder" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required />
        <select name="category_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required>
          <option v-for="c in categories" :key="c.id" :value="c.id" :selected="c.id === activeRule.category_id">{{ c.name }}</option>
        </select>
        <select name="budget_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required>
          <optgroup v-for="c in categories" :key="c.id" :label="c.name">
            <option v-for="b in c.budgets ?? []" :key="b.id" :value="b.id" :selected="b.id === activeRule.budget_id">{{ b.name }}</option>
          </optgroup>
        </select>
      </Form>

      <DialogFooter>
        <Button :form="mode === 'create' ? 'rule-create-form' : 'rule-edit-form'" type="submit">Opslaan</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
