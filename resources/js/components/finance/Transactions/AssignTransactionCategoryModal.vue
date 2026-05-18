<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { dynamicBackgroundColor, dynamicTextColor } from '@/composables/colorVariants';
import { computed, ref, watch } from 'vue';

interface Budget {
  id: number;
  name: string;
}

interface CategoryData {
  id: number;
  category: string;
  slug: string;
  color: string;
  icon: string;
  budgets: Budget[];
}

interface Transaction {
  id: number;
  amount: number;
  categoryId: number | null;
  budgetId: number | null;
  type: string | null;
  description: string;
  date: string;
}

const props = defineProps<{
  open: boolean;
  transaction: Transaction | null;
  categories: Record<number, CategoryData> | Record<string, CategoryData>;
}>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'assigned', payload: {
    transactionId: number;
    categoryId: number;
    budgetId: number;
    type: string;
    icon: string;
    color: string;
  }): void;
}>();

const selectedCategoryId = ref<number | null>(null);
const selectedBudgetId = ref<number | null>(null);
const selectedType = ref<'expense' | 'income' | 'saving'>('expense');

const categoryItems = computed(() => Object.values(props.categories || {}));
const selectedCategory = computed(() =>
  categoryItems.value.find((category) => category.id === selectedCategoryId.value),
);
const budgetItems = computed(() => selectedCategory.value?.budgets || []);

const resetSelection = () => {
  if (categoryItems.value.length === 0) {
    selectedCategoryId.value = null;
    selectedBudgetId.value = null;
    selectedType.value = 'expense';
    return;
  }

  if (props.transaction && props.transaction.categoryId) {
    selectedCategoryId.value = props.transaction.categoryId;
  } else {
    selectedCategoryId.value = categoryItems.value[0].id;
  }

  const category = categoryItems.value.find(
    (category) => category.id === selectedCategoryId.value,
  );

  selectedBudgetId.value = category?.budgets?.[0]?.id ?? null;

  const transactionType = props.transaction?.type;
  selectedType.value =
    transactionType === 'income' ||
    transactionType === 'expense' ||
    transactionType === 'saving'
      ? transactionType
      : props.transaction?.amount != null && props.transaction.amount >= 0
      ? 'income'
      : 'expense';
};

watch(
  () => props.open,
  (open) => {
    if (open) {
      resetSelection();
    }
  },
);

watch(
  () => props.transaction,
  () => {
    if (props.open) {
      resetSelection();
    }
  },
);

watch(selectedCategoryId, (newCategoryId) => {
  if (!newCategoryId) {
    return;
  }

  const category = categoryItems.value.find((category) => category.id === newCategoryId);
  selectedBudgetId.value = category?.budgets?.[0]?.id ?? null;
});

const handleClose = () => {
  emit('update:open', false);
};

const handleAssign = () => {
  if (!props.transaction || !selectedCategoryId.value || !selectedBudgetId.value) {
    return;
  }

  emit('assigned', {
    transactionId: props.transaction.id,
    categoryId: selectedCategoryId.value,
    budgetId: selectedBudgetId.value,
    type: selectedType.value,
    icon: selectedCategory.value?.icon ?? 'Plus',
    color: selectedCategory.value?.color ?? 'slate',
  });
  handleClose();
};
</script>

<template>
  <Dialog :open="props.open" @update:open="(value) => emit('update:open', value)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader class="space-y-3">
        <DialogTitle>Transactie koppelen</DialogTitle>
        <DialogDescription>
          Kies eerst een categorie. Daarna kies je budget.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <div class="space-y-2">
          <label class="text-sm font-medium">Categorie</label>
          <select
            v-model="selectedCategoryId"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
          >
            <option
              v-for="category in categoryItems"
              :key="category.id"
              :value="category.id"
            >
              {{ category.category }}
            </option>
          </select>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Budget</label>
          <select
            v-model="selectedBudgetId"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
          >
            <option
              v-for="budget in budgetItems"
              :key="budget.id"
              :value="budget.id"
            >
              {{ budget.name }}
            </option>
          </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-1">
          <div class="space-y-2">
            <label class="text-sm font-medium">Type</label>
            <select
              v-model="selectedType"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="expense">Uitgaven</option>
              <option value="income">Inkomsten</option>
              <option value="saving">Sparen</option>
            </select>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200/70 bg-gray-800 p-4">
          <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] items-center">
            <div class="flex items-center gap-2">
              <div
                :class="`${dynamicBackgroundColor(selectedCategory?.color, true)} p-2 rounded-full`"
              >
                <Icon
                  :name="selectedCategory?.icon ?? 'Plus'"
                  :class="`${dynamicTextColor(selectedCategory?.color)} h-4 w-4`"
                />
              </div>
              <div class="flex flex-col">
                <div class="text-xs font-medium">
                  {{ selectedCategory?.category ?? 'Selecteer een categorie' }}
                </div>
                <div class="text-[10px] text-muted-foreground">
                  {{ budgetItems.length ? budgetItems.find((budget) => budget.id === selectedBudgetId)?.name : 'Selecteer een budget' }}
                </div>
              </div>
            </div>

            <div class="flex items-center justify-content gap-4">
              <div class="text-[10px] text-muted-foreground">
                {{ props.transaction?.date ?? '' }}
              </div>
              <div class="text-sm font-medium">
                {{ props.transaction?.description ?? '' }}
              </div>
              <div class="text-sm font-semibold text-right">
                <span :class="transaction.amount > 0 ? 'rounded-md bg-green-800 p-2 py-1 text-white': ''">
                    {{ props.transaction ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(props.transaction.amount) : '' }}
                </span>
            </div>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter class="mt-4 gap-2">
        <DialogClose as-child>
          <Button variant="secondary" @click="handleClose">Annuleren</Button>
        </DialogClose>
        <Button :disabled="!selectedCategoryId || !selectedBudgetId" @click="handleAssign">
          Opslaan
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
