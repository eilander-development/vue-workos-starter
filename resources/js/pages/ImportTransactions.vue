<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';

const page = usePage();
const categories = Array.isArray(page.props.categories) ? page.props.categories : Object.values(page.props.categories ?? {});
const budgetOptions = categories.flatMap((c: any) => (c.budgets ?? []).map((b: any) => ({ id: b.id, label: `${c.name} - ${b.name}` })));
const rules = Array.isArray(page.props.rules) ? page.props.rules : [];
const result = page.props.result as any;
</script>

<template>
  <Head title="ING Import" />
  <AppLayout :breadcrumbs="[{ title: 'Home', href: home().url }, { title: 'ING Import', href: '/imports/transactions' }]">
    <main class="p-4 space-y-4">
      <div class="rounded-lg border p-4">
        <h2 class="text-base font-semibold mb-3">Importeer ING CSV</h2>
        <Form action="/imports/transactions" method="post" enctype="multipart/form-data" class="flex items-center gap-3">
          <input type="file" name="file" accept=".csv,.txt" class="text-sm" required />
          <button class="rounded-md border px-3 py-2 text-sm">Importeren</button>
        </Form>
      </div>

      <div v-if="result" class="rounded-lg border p-4">
        <h3 class="font-medium mb-2">Resultaat</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
          <div>Totaal: {{ result.total }}</div>
          <div>Ingelezen: {{ result.imported }}</div>
          <div>Dubbel: {{ result.duplicates }}</div>
          <div>Gekoppeld: {{ result.matched }}</div>
          <div>Niet gekoppeld: {{ result.unmatched }}</div>
        </div>
        <div class="mt-3 h-2 rounded bg-muted overflow-hidden">
          <div class="h-2 bg-green-600" :style="{ width: `${result.total ? Math.round((result.imported / result.total) * 100) : 0}%` }" />
        </div>
      </div>

      <div class="rounded-lg border p-4">
        <h2 class="text-base font-semibold mb-3">Koppelregel toevoegen</h2>
        <Form action="/imports/transactions/rules" method="post" class="grid gap-3 md:grid-cols-4">
          <select name="type" class="rounded-md border px-3 py-2 text-sm">
            <option value="iban">Rekeningnummer bevat</option>
            <option value="description">Omschrijving bevat</option>
          </select>
          <input name="match_value" class="rounded-md border px-3 py-2 text-sm" placeholder="NL00BANK..." required />
          <select name="category_id" class="rounded-md border px-3 py-2 text-sm" required>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select name="budget_id" class="rounded-md border px-3 py-2 text-sm" required>
            <option v-for="b in budgetOptions" :key="b.id" :value="b.id">{{ b.label }}</option>
          </select>
          <button class="rounded-md border px-3 py-2 text-sm md:col-span-4">Regel opslaan</button>
        </Form>
      </div>

      <div class="rounded-lg border p-4">
        <h2 class="text-base font-semibold mb-3">Bestaande regels</h2>
        <ul class="space-y-1 text-sm">
          <li v-for="rule in rules" :key="rule.id">{{ rule.type }}: "{{ rule.match_value }}" → categorie {{ rule.category_id }}, budget {{ rule.budget_id }}</li>
        </ul>
      </div>
    </main>
  </AppLayout>
</template>
