<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { Head, Form, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { DialogClose, Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import NotificationBanner from '@/components/NotificationBanner.vue';
import { useNotification } from '@/composables/useNotification';
import { ref, computed } from 'vue';
import { EllipsisVertical, Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';

const page = usePage();
const categories = Array.isArray(page.props.categories) ? page.props.categories : Object.values(page.props.categories ?? {});
const categoryMap = computed<Map<number, any>>(() => new Map(categories.map((c: any) => [c.id, c])));
const budgetMap = computed<Map<number, { id: number; name: string; categoryName: string }>>(
  () =>
    new Map(
      categories.flatMap((c: any) =>
        (c.budgets ?? []).map((b: any) => [b.id, { ...b, categoryName: c.name }]),
      ),
    ),
);
const rulesMeta = page.props.rules as any;
const rules = computed(() =>
    Array.isArray(rulesMeta?.data)
        ? rulesMeta.data
        : Array.isArray(rulesMeta)
        ? rulesMeta
        : [],
);
const flash = (page.props.flash as any) ?? {};
const result = computed(() => page.props.result ?? flash.result);
const { notification, showNotification } = useNotification();

if (flash.success) {
  showNotification(flash.success, 'success');
} else if (flash.error) {
  showNotification(flash.error, 'error');
} else if (result.value?.total !== undefined) {
  showNotification(
    `Import voltooid: ${result.value.imported} van ${result.value.total} transacties.`,
    'success',
  );
}

const createOpen = ref(false);
const editOpen = ref(false);
const activeRule = ref<any | null>(null);
const deleteRuleId = ref<number | null>(null);
const selectedRuleForTransactions = ref<any | null>(null);
const ruleTransactionsOpen = ref(false);
const ruleTransactions = ref<any[]>([]);
const ruleTransactionsPagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 100,
  total: 0,
  from: 0,
  to: 0,
});
const similarRuleTransactionsOpen = ref(false);
const similarRuleTransactions = ref<any[]>([]);
const similarRuleTransactionsPagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 100,
  total: 0,
  from: 0,
  to: 0,
});
const ruleType = ref<'iban' | 'description'>('iban');
const editType = ref<'iban' | 'description'>('iban');
const matchPlaceholder = computed(() => (ruleType.value === 'iban' ? 'NL00BANK...' : 'Bijv. Albert Heijn'));
const editPlaceholder = computed(() => (editType.value === 'iban' ? 'NL00BANK...' : 'Bijv. Albert Heijn'));
const labelForCategory = (id: number) => categoryMap.value.get(id)?.name ?? `#${id}`;
const labelForBudget = (id: number) => {
  const budget = budgetMap.value.get(id);
  return budget ? `${budget.name}` : `#${id}`;
};

const openEdit = (rule: any) => {
  activeRule.value = rule;
  editType.value = rule.type;
  editOpen.value = true;
};
const openDeleteRuleModal = (ruleId: number) => {
  deleteRuleId.value = ruleId;
};

const closeRuleTransactions = () => {
  ruleTransactionsOpen.value = false;
  selectedRuleForTransactions.value = null;
  ruleTransactions.value = [];
};

const closeSimilarRuleTransactions = () => {
  similarRuleTransactionsOpen.value = false;
  selectedRuleForTransactions.value = null;
  similarRuleTransactions.value = [];
};

const fetchRuleTransactions = async (ruleId: number | null, page = 1) => {
  if (!ruleId) {
    return;
  }

  try {
    const response = await axios.get(`/imports/transactions/rules/${ruleId}/transactions`, {
      params: { page },
      headers: { Accept: 'application/json' },
    });

    ruleTransactions.value = response.data.transactions ?? [];
    ruleTransactionsPagination.value = response.data.pagination ?? ruleTransactionsPagination.value;
  } catch (error) {
    console.error('Kon gekoppelde transacties niet ophalen:', error);
    showNotification('Kon gekoppelde transacties niet ophalen.', 'error');
  }
};

const openRuleTransactions = async (rule: any) => {
  selectedRuleForTransactions.value = rule;
  ruleTransactionsOpen.value = true;
  await fetchRuleTransactions(rule.id);
};

const fetchSimilarRuleTransactions = async (ruleId: number | null, page = 1) => {
  if (!ruleId) {
    return;
  }

  try {
    const response = await axios.get(`/imports/transactions/rules/${ruleId}/similar-transactions`, {
      params: { page },
      headers: { Accept: 'application/json' },
    });

    similarRuleTransactions.value = response.data.transactions ?? [];
    similarRuleTransactionsPagination.value = response.data.pagination ?? similarRuleTransactionsPagination.value;
  } catch (error) {
    console.error('Kon vergelijkbare transacties niet ophalen:', error);
    showNotification('Kon vergelijkbare transacties niet ophalen.', 'error');
  }
};

const applyTransactionRule = async (transactionId: number) => {
  const ruleId = selectedRuleForTransactions.value?.id;
  if (!ruleId) {
    return;
  }

  try {
    await axios.post(
      `/imports/transactions/rules/${ruleId}/transactions/${transactionId}/apply`,
      {},
      { headers: { Accept: 'application/json' } },
    );

    await fetchSimilarRuleTransactions(ruleId, similarRuleTransactionsPagination.value.current_page);
    showNotification('Transactie succesvol gekoppeld.', 'success');
  } catch (error) {
    console.error('Kon transactie niet koppelen:', error);
    showNotification('Kon transactie niet koppelen.', 'error');
  }
};
</script>

<template>
  <Head title="ING Import" />
  <AppLayout :breadcrumbs="[{ title: 'Home', href: home().url }, { title: 'ING Import', href: '/imports/transactions' }]">
    <main class="space-y-4 p-4">
      <NotificationBanner v-if="notification" :type="notification.type" :message="notification.message" />
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
        <CardContent class="py-6">
          <p class="text-sm text-muted-foreground">
            Koppelregels zijn verplaatst naar <a href="/imports/rules" class="underline">de aparte pagina</a>.
          </p>
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
            <optgroup v-for="c in categories" :key="c.id" :label="c.name">
              <option v-for="b in c.budgets ?? []" :key="b.id" :value="b.id">{{ b.name }}</option>
            </optgroup>
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
            <optgroup v-for="c in categories" :key="c.id" :label="c.name">
              <option v-for="b in c.budgets ?? []" :key="b.id" :value="b.id" :selected="b.id === activeRule.budget_id">{{ b.name }}</option>
            </optgroup>
          </select>
        </Form>
        <DialogFooter><Button form="edit-rule-form" type="submit">Opslaan</Button></DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="deleteRuleId !== null" @update:open="(open) => { if (!open) deleteRuleId = null; }">
      <DialogContent class="sm:max-w-md">
        <DialogHeader class="space-y-2">
          <DialogTitle>Koppelregel verwijderen</DialogTitle>
        </DialogHeader>
        <DialogFooter>
          <Button type="button" variant="secondary" @click="deleteRuleId = null">Annuleren</Button>
          <Form v-if="deleteRuleId !== null" :action="`/imports/transactions/rules/${deleteRuleId}`" method="delete">
            <Button type="submit" variant="destructive">Verwijderen</Button>
          </Form>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="ruleTransactionsOpen" @update:open="(value) => { if (!value) closeRuleTransactions(); ruleTransactionsOpen = value; }">
      <DialogContent class="sm:max-w-4xl">
        <DialogHeader class="space-y-3">
          <DialogTitle>Transacties voor koppelregel</DialogTitle>
          <DialogDescription>
            Transacties die gekoppeld zijn aan de geselecteerde regel.
          </DialogDescription>
        </DialogHeader>

        <div class="max-h-[60vh] overflow-y-auto overflow-x-auto rounded-xl border border-slate-800 bg-slate-950/20">
          <table class="w-full table-auto text-sm">
            <thead class="sticky top-0 bg-slate-950">
              <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th class="p-2">Datum</th>
                <th class="p-2">Omschrijving</th>
                <th class="p-2">IBAN</th>
                <th class="p-2">Categorie</th>
                <th class="p-2">Budget</th>
                <th class="p-2 text-right">Bedrag</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in ruleTransactions" :key="transaction.id" class="border-b border-slate-800">
                <td class="p-2 text-sm">{{ transaction.date }}</td>
                <td class="p-2 text-sm">{{ transaction.description }}</td>
                <td class="p-2 text-sm">{{ transaction.counterparty_iban ?? '-' }}</td>
                <td class="p-2 text-sm">{{ transaction.category_name ?? '-' }}</td>
                <td class="p-2 text-sm">{{ transaction.budget_name ?? '-' }}</td>
                <td class="p-2 text-right text-sm">
                  {{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(transaction.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-between border-t border-slate-800 px-4 py-3 text-sm text-muted-foreground">
          <div>
            Toon {{ ruleTransactionsPagination.from }} - {{ ruleTransactionsPagination.to }} van {{ ruleTransactionsPagination.total }} transacties
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
              @click="fetchRuleTransactions(selectedRuleForTransactions?.id, ruleTransactionsPagination.current_page - 1)"
              :disabled="ruleTransactionsPagination.current_page <= 1"
            >
              Vorige
            </button>
            <button
              type="button"
              class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
              @click="fetchRuleTransactions(selectedRuleForTransactions?.id, ruleTransactionsPagination.current_page + 1)"
              :disabled="ruleTransactionsPagination.current_page >= ruleTransactionsPagination.last_page"
            >
              Volgende
            </button>
          </div>
        </div>

        <DialogFooter class="mt-4 gap-2">
          <DialogClose as-child>
            <Button variant="secondary">Sluiten</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="similarRuleTransactionsOpen" @update:open="(value) => { if (!value) closeSimilarRuleTransactions(); similarRuleTransactionsOpen = value; }">
      <DialogContent class="sm:max-w-4xl">
        <DialogHeader class="space-y-3">
          <DialogTitle>Vergelijkbare transacties</DialogTitle>
          <DialogDescription>
            Transacties die nog niet gekoppeld zijn en overeenkomen met deze regel.
          </DialogDescription>
        </DialogHeader>

        <div class="max-h-[60vh] overflow-y-auto overflow-x-auto rounded-xl border border-slate-800 bg-slate-950/20">
          <table class="w-full table-auto text-sm">
            <thead class="sticky top-0 bg-slate-950">
              <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th class="p-2">Datum</th>
                <th class="p-2">Omschrijving</th>
                <th class="p-2">IBAN</th>
                <th class="p-2">Categorie</th>
                <th class="p-2">Budget</th>
                <th class="p-2 text-right">Bedrag</th>
                <th class="p-2 text-right">Acties</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in similarRuleTransactions" :key="transaction.id" class="border-b border-slate-800">
                <td class="p-2 text-sm">{{ transaction.date }}</td>
                <td class="p-2 text-sm">{{ transaction.description }}</td>
                <td class="p-2 text-sm">{{ transaction.counterparty_iban ?? '-' }}</td>
                <td class="p-2 text-sm">{{ transaction.category_name ?? '-' }}</td>
                <td class="p-2 text-sm">{{ transaction.budget_name ?? '-' }}</td>
                <td class="p-2 text-right text-sm">
                  {{ new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(transaction.amount) }}
                </td>
                <td class="p-2 text-right text-sm">
                  <button
                    type="button"
                    class="rounded-md border border-input bg-background px-2 py-1 text-xs transition hover:bg-muted"
                    @click="applyTransactionRule(transaction.id)"
                  >
                    Koppelen
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-between border-t border-slate-800 px-4 py-3 text-sm text-muted-foreground">
          <div>
            Toon {{ similarRuleTransactionsPagination.from }} - {{ similarRuleTransactionsPagination.to }} van {{ similarRuleTransactionsPagination.total }} transacties
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
              @click="fetchSimilarRuleTransactions(selectedRuleForTransactions?.id, similarRuleTransactionsPagination.current_page - 1)"
              :disabled="similarRuleTransactionsPagination.current_page <= 1"
            >
              Vorige
            </button>
            <button
              type="button"
              class="rounded-md border border-input bg-background px-3 py-1 text-xs transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
              @click="fetchSimilarRuleTransactions(selectedRuleForTransactions?.id, similarRuleTransactionsPagination.current_page + 1)"
              :disabled="similarRuleTransactionsPagination.current_page >= similarRuleTransactionsPagination.last_page"
            >
              Volgende
            </button>
          </div>
        </div>

        <DialogFooter class="mt-4 gap-2">
          <DialogClose as-child>
            <Button variant="secondary">Sluiten</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
