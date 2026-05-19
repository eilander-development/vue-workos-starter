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
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  open: boolean;
  category: Record<string, any> | null;
}>();
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const categoryUpdateAction = computed(() => {
  return props.category ? `/categories/${props.category.id}` : '#';
});

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
  'Home', 'Zap', 'Droplets', 'Wind', 'Smartphone', 'Headphones',
  'Gamepad2', 'BookOpen', 'Users', 'Award', 'Activity', 'Anchor',
  'Aperture', 'Archive', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 
  'Cat', 'Dog', 'Fish', 'Bird', 'HeartCrack', 'HeartHandshake', 'HeartOff', 
  'HeartPulse', 'House', 'IceCream', 'Lollipop', 'Mug', 'Pizza', 'Ramen', 
  'Sushi', 'Table', 'Tree', 'Umbrella', 'Wand', 'Watch', 'Wifi', 'ZapOff',
  'ZoomIn', 'ZoomOut', 'Anchor', 'Aperture', 'Archive', 'ArrowDown',
  'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ShieldAlert', 'ShieldCheck', 
  'ShieldClose', 'ShieldOff', 'ShieldQuestion',
];

const typeOptions = [
  { value: 'expense', label: 'Uitgaven' },
  { value: 'income', label: 'Inkomsten' },
  { value: 'saving', label: 'Sparen' },
  { value: 'uncategorized', label: 'Ongecategoriseerd' },
];

const close = () => emit('update:open', false);

const initFormData = () => {
  if (props.category) {
    formData.value = {
      name: props.category.category || '',
      slug: props.category.slug || '',
      icon: props.category.icon || 'Plus',
      color: props.category.color || 'slate',
      type: props.category.type || 'expense',
    };
  }
};

watch(
  () => props.open,
  (open) => {
    if (open) {
      initFormData();
    }
  },
);

watch(
  () => props.category,
  (category) => {
    if (props.open && category) {
      initFormData();
    }
  },
);
</script>

<template>
  <Dialog :open="props.open" @update:open="(value) => { if (value) initFormData(); emit('update:open', value); }">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader class="space-y-3">
        <DialogTitle>Categorie wijzigen</DialogTitle>
        <DialogDescription>
          Pas de categoriegegevens aan. De preview toont hoe het eruit ziet.
          {{ props.category.icon }}
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <Form id="category-update-form" :action="categoryUpdateAction" method="patch" class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label for="name" class="text-sm font-medium">Naam</label>
            <input
              id="name"
              v-model="formData.name"
              name="name"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
              required
            />
          </div>

          <div class="space-y-2">
            <label for="slug" class="text-sm font-medium">Slug</label>
            <input
              id="slug"
              v-model="formData.slug"
              name="slug"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
              required
            />
          </div>

          <div class="space-y-2">
            <label for="type" class="text-sm font-medium">Type</label>
            <select
              id="type"
              v-model="formData.type"
              name="type"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
              required
            >
              <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <div class="space-y-2">
            <label for="icon" class="text-sm font-medium">Icoon</label>
            <select
              id="icon"
              v-model="formData.icon"
              name="icon"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
            >
              <option v-for="icon in iconOptions" :key="icon" :value="icon">{{ icon }}</option>
            </select>
          </div>

          <div class="space-y-2">
            <label for="color" class="text-sm font-medium">Kleur</label>
            <select
              id="color"
              v-model="formData.color"
              name="color"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
            >
              <option v-for="color in colorOptions" :key="color" :value="color">{{ color }}</option>
            </select>
          </div>
        </Form>

        <div class="rounded-xl border border-slate-200/70 bg-gray-800 p-4">
          <div class="flex items-center gap-3">
            <div :class="`${dynamicBackgroundColor(formData.color, true)} p-2 rounded-full`">
              <Icon :name="formData.icon" :class="`${dynamicTextColor(formData.color)} h-5 w-5`" />
            </div>
            <div class="flex flex-col">
              <div class="text-sm font-medium">{{ formData.name || 'Categorie naam' }}</div>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter class="mt-4 gap-2">
        <DialogClose as-child>
          <Button variant="secondary" @click="close">Annuleren</Button>
        </DialogClose>
        <Button form="category-update-form" type="submit">Opslaan</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
