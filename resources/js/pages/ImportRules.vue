<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { Head, Form, usePage, router } from '@inertiajs/vue3';
import { home } from '@/routes';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import NotificationBanner from '@/components/NotificationBanner.vue';
import { useNotification } from '@/composables/useNotification';
import { ref, computed } from 'vue';
import { Plus } from 'lucide-vue-next';
import RulesTable from '@/components/finance/import-rules/RulesTable.vue';
import RuleTransactionsDialog from '@/components/finance/import-rules/RuleTransactionsDialog.vue';
import ReassignBudgetDialog from '@/components/finance/import-rules/ReassignBudgetDialog.vue';
import RuleFormDialog from '@/components/finance/import-rules/RuleFormDialog.vue';

const page = usePage();
const categories = Array.isArray(page.props.categories) ? page.props.categories : Object.values(page.props.categories ?? {});
const categoryMap = computed<Map<number, any>>(() => new Map(categories.map((c: any) => [c.id, c])));
const budgetMap = computed<Map<number, { id: number; name: string; categoryName: string }>>(
  () => new Map(categories.flatMap((c: any) => (c.budgets ?? []).map((b: any) => [b.id, { ...b, categoryName: c.name }]))),
);
const rulesMeta = page.props.rules as any;
const rules = computed(() => Array.isArray(rulesMeta?.data) ? rulesMeta.data : Array.isArray(rulesMeta) ? rulesMeta : []);
const flash = (page.props.flash as any) ?? {};
const result = computed(() => page.props.result ?? flash.result);
const { notification, showNotification } = useNotification();

if (flash.success) showNotification(flash.success, 'success');
else if (flash.error) showNotification(flash.error, 'error');
else if (result.value?.total !== undefined) showNotification(`Import voltooid: ${result.value.imported} van ${result.value.total} transacties.`, 'success');

const createOpen = ref(false);
const editOpen = ref(false);
const activeRule = ref<any | null>(null);
const deleteRuleId = ref<number | null>(null);
const selectedRuleForTransactions = ref<any | null>(null);
const ruleTransactionsOpen = ref(false);
const ruleTransactions = ref<any[]>([]);
const ruleTransactionsPagination = ref({ current_page: 1, last_page: 1, per_page: 100, total: 0, from: 0, to: 0 });
const similarRuleTransactionsOpen = ref(false);
const similarRuleTransactions = ref<any[]>([]);
const similarRuleTransactionsPagination = ref({ current_page: 1, last_page: 1, per_page: 100, total: 0, from: 0, to: 0 });
const reassignRuleId = ref<number | null>(null);
const reassignBudgetId = ref<string>('');
const ruleType = ref<'iban' | 'description'>('iban');
const editType = ref<'iban' | 'description'>('iban');
const matchPlaceholder = computed(() => (ruleType.value === 'iban' ? 'NL00BANK...' : 'Bijv. Albert Heijn'));
const editPlaceholder = computed(() => (editType.value === 'iban' ? 'NL00BANK...' : 'Bijv. Albert Heijn'));
const labelForCategory = (id: number) => categoryMap.value.get(id)?.name ?? `#${id}`;
const labelForBudget = (id: number) => budgetMap.value.get(id)?.name ?? `#${id}`;

const openEdit = (rule: any) => { activeRule.value = rule; editType.value = rule.type; editOpen.value = true; };
const closeRuleTransactions = () => { ruleTransactionsOpen.value = false; selectedRuleForTransactions.value = null; ruleTransactions.value = []; };
const closeSimilarRuleTransactions = () => { similarRuleTransactionsOpen.value = false; selectedRuleForTransactions.value = null; similarRuleTransactions.value = []; };

const fetchRuleTransactions = async (ruleId: number | null, pageNumber = 1) => {
  if (!ruleId) return;
  try {
    const response = await axios.get(`/imports/transactions/rules/${ruleId}/transactions`, { params: { page: pageNumber }, headers: { Accept: 'application/json' } });
    ruleTransactions.value = response.data.transactions ?? [];
    ruleTransactionsPagination.value = response.data.pagination ?? ruleTransactionsPagination.value;
  } catch {
    showNotification('Kon gekoppelde transacties niet ophalen.', 'error');
  }
};

const fetchSimilarRuleTransactions = async (ruleId: number | null, pageNumber = 1) => {
  if (!ruleId) return;
  try {
    const response = await axios.get(`/imports/transactions/rules/${ruleId}/similar-transactions`, { params: { page: pageNumber }, headers: { Accept: 'application/json' } });
    similarRuleTransactions.value = response.data.transactions ?? [];
    similarRuleTransactionsPagination.value = response.data.pagination ?? similarRuleTransactionsPagination.value;
  } catch {
    showNotification('Kon vergelijkbare transacties niet ophalen.', 'error');
  }
};

const openRuleTransactions = async (rule: any) => { selectedRuleForTransactions.value = rule; ruleTransactionsOpen.value = true; await fetchRuleTransactions(rule.id); };
const openSimilarRuleTransactions = async (rule: any) => { selectedRuleForTransactions.value = rule; similarRuleTransactionsOpen.value = true; await fetchSimilarRuleTransactions(rule.id); };

const applyTransactionRule = async (transactionId: number) => {
  const ruleId = selectedRuleForTransactions.value?.id;
  if (!ruleId) return;
  try {
    await axios.post(`/imports/transactions/rules/${ruleId}/transactions/${transactionId}/apply`, {}, { headers: { Accept: 'application/json' } });
    await fetchSimilarRuleTransactions(ruleId, similarRuleTransactionsPagination.value.current_page);
    showNotification('Transactie succesvol gekoppeld.', 'success');
  } catch {
    showNotification('Kon transactie niet koppelen.', 'error');
  }
};

const applyRuleToAllSimilarTransactions = async () => {
  const ruleId = selectedRuleForTransactions.value?.id;
  if (!ruleId) return;
  try {
    const response = await axios.post(`/imports/transactions/rules/${ruleId}/apply-all-matches`, {}, { headers: { Accept: 'application/json' } });
    showNotification(`${response.data.updated} transacties in een keer gekoppeld.`, 'success');
    await fetchSimilarRuleTransactions(ruleId, 1);
    await fetchRuleTransactions(ruleId, 1);
    router.reload({ only: ['rules'] });
  } catch {
    showNotification('In een keer koppelen is mislukt.', 'error');
  }
};

const applyRuleToAnotherBudget = async () => {
  if (!reassignRuleId.value || !reassignBudgetId.value) return;
  try {
    const response = await axios.post(`/imports/transactions/rules/${reassignRuleId.value}/reassign-matched-budget`, { budget_id: Number(reassignBudgetId.value) }, { headers: { Accept: 'application/json' } });
    showNotification(`${response.data.updated} transacties omgezet op basis van de matchvoorwaarde.`, 'success');
    reassignRuleId.value = null;
    reassignBudgetId.value = '';
    await fetchSimilarRuleTransactions(selectedRuleForTransactions.value?.id);
    router.reload({ only: ['rules'] });
  } catch {
    showNotification('Omzetten van matched transacties is mislukt.', 'error');
  }
};
</script>

<template>
  <Head title="Koppelregels" />
  <AppLayout :breadcrumbs="[{ title: 'Home', href: home().url }, { title: 'Koppelregels', href: '/imports/rules' }]">
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
        </CardContent>
      </Card>

      <Card class="rounded-md shadow-xl">
        <CardContent>
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-base font-semibold">Koppelregels</h2>
            <Button type="button" variant="outline" size="sm" class="flex items-center gap-1" @click="createOpen = true"><Plus class="h-4 w-4" /> Regel toevoegen</Button>
          </div>

          <RulesTable
            :rules="rules"
            :rules-meta="rulesMeta"
            :label-for-category="labelForCategory"
            :label-for-budget="labelForBudget"
            @open-transactions="openRuleTransactions"
            @open-similar="openSimilarRuleTransactions"
            @open-edit="openEdit"
            @open-reassign="(rule) => reassignRuleId = rule.id"
            @open-delete="(ruleId) => deleteRuleId = ruleId"
          />
        </CardContent>
      </Card>
    </main>

    <RuleFormDialog
      :open="createOpen"
      mode="create"
      :categories="categories as any[]"
      :rule-type="ruleType"
      :placeholder="matchPlaceholder"
      @update-open="(value) => (createOpen = value)"
      @update-type="(value) => (ruleType = value)"
    />

    <RuleFormDialog
      :open="editOpen"
      mode="edit"
      :categories="categories as any[]"
      :rule-type="editType"
      :placeholder="editPlaceholder"
      :active-rule="activeRule"
      @update-open="(value) => (editOpen = value)"
      @update-type="(value) => (editType = value)"
    />

    <Dialog :open="deleteRuleId !== null" @update:open="(open) => { if (!open) deleteRuleId = null; }">
      <DialogContent class="sm:max-w-md">
        <DialogHeader class="space-y-2"><DialogTitle>Koppelregel verwijderen</DialogTitle></DialogHeader>
        <DialogFooter>
          <Button type="button" variant="secondary" @click="deleteRuleId = null">Annuleren</Button>
          <Form v-if="deleteRuleId !== null" :action="`/imports/transactions/rules/${deleteRuleId}`" method="delete">
            <Button type="submit" variant="destructive">Verwijderen</Button>
          </Form>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ReassignBudgetDialog
      :open="reassignRuleId !== null"
      :categories="categories as any[]"
      :budget-id="reassignBudgetId"
      @update-open="(open) => { if (!open) { reassignRuleId = null; reassignBudgetId = ''; } }"
      @update-budget-id="(value) => reassignBudgetId = value"
      @submit="applyRuleToAnotherBudget"
    />

    <RuleTransactionsDialog
      :open="ruleTransactionsOpen"
      title="Transacties voor koppelregel"
      description="Transacties die gekoppeld zijn aan de geselecteerde regel."
      :transactions="ruleTransactions"
      :pagination="ruleTransactionsPagination"
      @update-open="(value) => { if (!value) closeRuleTransactions(); ruleTransactionsOpen = value; }"
      @previous="fetchRuleTransactions(selectedRuleForTransactions?.id, ruleTransactionsPagination.current_page - 1)"
      @next="fetchRuleTransactions(selectedRuleForTransactions?.id, ruleTransactionsPagination.current_page + 1)"
    />

    <RuleTransactionsDialog
      :open="similarRuleTransactionsOpen"
      title="Vergelijkbare transacties"
      description="Transacties die nog niet gekoppeld zijn en overeenkomen met deze regel."
      :transactions="similarRuleTransactions"
      :pagination="similarRuleTransactionsPagination"
      :show-actions="true"
      :show-bulk-button="true"
      @update-open="(value) => { if (!value) closeSimilarRuleTransactions(); similarRuleTransactionsOpen = value; }"
      @previous="fetchSimilarRuleTransactions(selectedRuleForTransactions?.id, similarRuleTransactionsPagination.current_page - 1)"
      @next="fetchSimilarRuleTransactions(selectedRuleForTransactions?.id, similarRuleTransactionsPagination.current_page + 1)"
      @apply-one="applyTransactionRule"
      @apply-all="applyRuleToAllSimilarTransactions"
    />
  </AppLayout>
</template>
