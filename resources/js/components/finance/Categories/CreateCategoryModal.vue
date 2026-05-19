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
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
  open: boolean;
}>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const formData = ref({
  name: '',
  slug: '',
  icon: 'Plus',
  color: 'slate',
  type: 'expense',
});

const colorOptions = [
  'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
  'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink',
  'rose', 'slate', 'gray', 'zinc', 'neutral', 'stone'
];

const iconOptions = [
  'Plus', 'Home', 'Settings', 'User', 'Mail', 'Clock', 'AlertCircle',
  'Check', 'X', 'ChevronDown', 'ChevronUp', 'ChevronLeft', 'ChevronRight',
  'Search', 'Filter', 'Download', 'Upload', 'Copy', 'Trash', 'Edit',
  'Eye', 'EyeOff', 'Lock', 'Unlock', 'Shield', 'Heart', 'Star',
  'Zap', 'Gift', 'Briefcase', 'ShoppingCart', 'CreditCard', 'Wallet',
  'TrendingUp', 'TrendingDown', 'BarChart', 'PieChart', 'DollarSign',
  'Utensils', 'Salad', 'Coffee', 'Wine', 'Plane', 'Car', 'Bus',
  'Home' , 'Zap', 'Droplets', 'Wind', 'Smartphone', 'Headphones',
  'Gamepad2', 'BookOpen', 'Users', 'Award', 'Activity', 'Anchor',
  'Aperture', 'Archive', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowUp'
];

const typeOptions = [
  { value: 'expense', label: 'Uitgaven' },
  { value: 'income', label: 'Inkomsten' },
  { value: 'saving', label: 'Sparen' },
  { value: 'uncategorized', label: 'Ongecategoriseerd' },
];

const close = () => {
  formData.value = { name: '', slug: '', icon: 'Plus', color: 'slate', type: 'expense' };
  emit('update:open', false);
};

const generateSlug = () => {
  formData.value.slug = formData.value.name
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^\w-]/g, '');
};
</script>

<template>
  <Dialog :open="props.open" @update:open="(value) => emit('update:open', value)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader class="space-y-3">
        <DialogTitle>Nieuwe categorie</DialogTitle>
        <DialogDescription>
          Voeg een categorie toe met kleur, icoon en type.
        </DialogDescription>
      </DialogHeader>

      <Form id="create-category-form" action="/categories" method="post" class="space-y-4">
        <div class="grid gap-3">
          <label for="name" class="text-sm font-medium">Naam</label>
          <input id="name" name="name" class="rounded-2xl border px-4 py-3 outline-none focus:ring-2 focus:ring-slate-400" placeholder="Eten/drinken" required />
        </div>
        <div class="grid gap-3">
          <label for="slug" class="text-sm font-medium">Slug</label>
          <input id="slug" name="slug" class="rounded-2xl border px-4 py-3 outline-none focus:ring-2 focus:ring-slate-400" placeholder="eten-drinken" required />
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="grid gap-3">
            <label for="icon" class="text-sm font-medium">Icoon</label>
            <input id="icon" name="icon" class="rounded-2xl border px-4 py-3 outline-none focus:ring-2 focus:ring-slate-400" placeholder="Salad" />
          </div>
          <div class="grid gap-3">
            <label for="color" class="text-sm font-medium">Kleur</label>
            <input id="color" name="color" class="rounded-2xl border px-4 py-3 outline-none focus:ring-2 focus:ring-slate-400" placeholder="slate" />
          </div>
        </div>
        <div class="grid gap-3">
          <label for="type" class="text-sm font-medium">Type</label>
          <select id="type" name="type" class="rounded-2xl border px-4 py-3 outline-none focus:ring-2 focus:ring-slate-400" required>
            <option value="expense">Uitgaven</option>
            <option value="income">Inkomsten</option>
            <option value="saving">Sparen</option>
            <option value="uncategorized">Ongecategoriseerd</option>
          </select>
        </div>
      </Form>

      <DialogFooter class="mt-4 gap-2">
        <DialogClose as-child>
          <Button variant="secondary" @click="close">Annuleren</Button>
        </DialogClose>
        <Button form="create-category-form" type="submit">Opslaan</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
