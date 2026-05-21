<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ref, computed } from 'vue';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

const page = usePage();
const categories = Array.isArray(page.props.categories) ? page.props.categories : Object.values(page.props.categories ?? {});
const budgetOptions = categories.flatMap((c: any) => (c.budgets ?? []).map((b: any) => ({ id: b.id, label: `${c.name} - ${b.name}` })));
const rules = Array.isArray(page.props.rules) ? page.props.rules : [];
const result = page.props.result as any;

const createOpen = ref(false);
const editOpen = ref(false);
const activeRule = ref<any | null>(null);
const ruleType = ref<'iban' | 'description'>('iban');
const editType = ref<'iban' | 'description'>('iban');
const matchPlaceholder = computed(() => (ruleType.value === 'iban' ? 'NL00BANK...' : 'Bijv. Albert Heijn'));
const editPlaceholder = computed(() => (editType.value === 'iban' ? 'NL00BANK...' : 'Bijv. Albert Heijn'));

const openEdit = (rule: any) => {
  activeRule.value = rule;
  editType.value = rule.type;
  editOpen.value = true;
};
</script>

<template>
  <Head title="ING Import" />
  <AppLayout :breadcrumbs="[{ title: 'Home', href: home().url }, { title: 'ING Import', href: '/imports/transactions' }]">
    <main class="space-y-4 p-4">
      <Card class="rounded-md shadow-xl">
        <CardContent>
          <h2 class="mb-3 text-base font-semibold">Importeer ING CSV</h2>
          <Form action="/imports/transactions" method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
            <input type="file" name="file" accept=".csv,.txt" class="text-sm" required />
            <Button type="submit">Importeren</Button>
          </Form>
        </CardContent>
      </Card>

      <Card v-if="result" class="rounded-md shadow-xl">
        <CardContent>
          <h3 class="mb-2 font-medium">Resultaat</h3>
          <div class="grid grid-cols-2 gap-2 text-sm md:grid-cols-5">
            <div>Totaal: {{ result.total }}</div>
            <div>Ingelezen: {{ result.imported }}</div>
            <div>Dubbel: {{ result.duplicates }}</div>
            <div>Gekoppeld: {{ result.matched }}</div>
            <div>Niet gekoppeld: {{ result.unmatched }}</div>
          </div>
          <div class="mt-3 h-2 overflow-hidden rounded bg-muted">
            <div class="h-2 bg-green-600" :style="{ width: `${result.total ? Math.round((result.imported / result.total) * 100) : 0}%` }" />
          </div>
        </CardContent>
      </Card>

      <Card class="rounded-md shadow-xl">
        <CardContent>
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-semibold">Koppelregels</h2>
            <button type="button" class="rounded-md border border-dashed border-muted-foreground px-3 py-2 text-xs text-muted-foreground hover:bg-green-900 hover:text-white" @click="createOpen = true">
              <div class="flex items-center gap-1"><Plus class="h-4 w-4" /> Regel toevoegen</div>
            </button>
          </div>
          <table class="w-full table-auto text-sm">
            <tbody>
              <tr v-for="rule in rules" :key="rule.id" class="group border-b border-slate-900 transition-colors hover:bg-muted/50">
                <td class="p-2">{{ rule.type === 'iban' ? 'Rekeningnummer bevat' : 'Omschrijving bevat' }}</td>
                <td class="p-2">{{ rule.match_value }}</td>
                <td class="p-2">{{ rule.category_id }}</td>
                <td class="p-2">{{ rule.budget_id }}</td>
                <td class="w-0 p-2 text-right">
                  <button type="button" class="rounded-md p-1 text-muted-foreground opacity-0 transition hover:bg-muted group-hover:opacity-100 focus:opacity-100 focus-visible:opacity-100" @click="openEdit(rule)"><Pencil class="h-4 w-4" /></button>
                </td>
                <td class="w-0 p-2 text-right">
                  <Form :action="`/imports/transactions/rules/${rule.id}`" method="delete">
                    <button type="submit" class="rounded-md p-1 text-red-400 opacity-0 transition hover:bg-red-950/40 group-hover:opacity-100 focus:opacity-100 focus-visible:opacity-100" onclick="return confirm('Weet je zeker dat je deze regel wilt verwijderen?')"><Trash2 class="h-4 w-4" /></button>
                  </Form>
                </td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>
    </main>

    <Dialog :open="createOpen" @update:open="(value) => (createOpen = value)">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader class="space-y-3"><DialogTitle>Koppelregel toevoegen</DialogTitle></DialogHeader>
        <Form id="create-rule-form" action="/imports/transactions/rules" method="post" class="grid gap-3">
          <select v-model="ruleType" name="type" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring">
            <option value="iban">Rekeningnummer bevat</option>
            <option value="description">Omschrijving bevat</option>
          </select>
          <input name="match_value" :placeholder="matchPlaceholder" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required />
          <select name="category_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select name="budget_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required>
            <option v-for="b in budgetOptions" :key="b.id" :value="b.id">{{ b.label }}</option>
          </select>
        </Form>
        <DialogFooter><Button form="create-rule-form" type="submit">Opslaan</Button></DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="editOpen" @update:open="(value) => (editOpen = value)">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader class="space-y-3"><DialogTitle>Koppelregel wijzigen</DialogTitle></DialogHeader>
        <Form v-if="activeRule" id="edit-rule-form" :action="`/imports/transactions/rules/${activeRule.id}`" method="patch" class="grid gap-3">
          <select v-model="editType" name="type" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring">
            <option value="iban">Rekeningnummer bevat</option>
            <option value="description">Omschrijving bevat</option>
          </select>
          <input name="match_value" :value="activeRule.match_value" :placeholder="editPlaceholder" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required />
          <select name="category_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required>
            <option v-for="c in categories" :key="c.id" :value="c.id" :selected="c.id === activeRule.category_id">{{ c.name }}</option>
          </select>
          <select name="budget_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required>
            <option v-for="b in budgetOptions" :key="b.id" :value="b.id" :selected="b.id === activeRule.budget_id">{{ b.label }}</option>
          </select>
        </Form>
        <DialogFooter><Button form="edit-rule-form" type="submit">Opslaan</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
