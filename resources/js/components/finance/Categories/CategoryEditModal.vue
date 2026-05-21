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
import {
    dynamicBackgroundColor,
    dynamicTextColor,
} from '@/composables/colorVariants';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Check, ChevronDown, Plus, Trash2 } from 'lucide-vue-next';

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
    budgets: [] as Array<{
        id?: number;
        name: string;
        budget: number | string;
    }>,
});
const deleteBudgetPayload = ref<{ categoryId: number; budgetId: number } | null>(null);
const slugPreview = computed(() =>
    (formData.value.name || '')
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w-]/g, '')
        .replace(/--+/g, '-'),
);

const colorOptions = [
    'red',
    'orange',
    'amber',
    'yellow',
    'lime',
    'green',
    'emerald',
    'teal',
    'cyan',
    'sky',
    'blue',
    'indigo',
    'violet',
    'purple',
    'fuchsia',
    'pink',
    'rose',
    'slate',
    'gray',
    'zinc',
    'neutral',
    'stone',
];
const colorPreview: Record<string, string> = {
    red: '#ef4444', orange: '#f97316', amber: '#f59e0b', yellow: '#eab308',
    lime: '#84cc16', green: '#22c55e', emerald: '#10b981', teal: '#14b8a6',
    cyan: '#06b6d4', sky: '#0ea5e9', blue: '#3b82f6', indigo: '#6366f1',
    violet: '#8b5cf6', purple: '#a855f7', fuchsia: '#d946ef', pink: '#ec4899',
    rose: '#f43f5e', slate: '#64748b', gray: '#6b7280', zinc: '#71717a',
    neutral: '#737373', stone: '#78716c',
};

const iconOptions = [
    'Plus',
    'Home',
    'Settings',
    'User',
    'Mail',
    'Clock',
    'AlertCircle',
    'Check',
    'X',
    'ChevronDown',
    'ChevronUp',
    'ChevronLeft',
    'ChevronRight',
    'Search',
    'Filter',
    'Download',
    'Upload',
    'Copy',
    'Trash',
    'Edit',
    'Eye',
    'EyeOff',
    'Lock',
    'Unlock',
    'Shield',
    'Heart',
    'Star',
    'Zap',
    'Gift',
    'Briefcase',
    'ShoppingCart',
    'CreditCard',
    'Wallet',
    'TrendingUp',
    'TrendingDown',
    'BarChart',
    'PieChart',
    'DollarSign',
    'Utensils',
    'Salad',
    'Coffee',
    'Wine',
    'Plane',
    'Car',
    'Bus',
    'Home',
    'Zap',
    'Droplets',
    'Wind',
    'Smartphone',
    'Headphones',
    'Gamepad2',
    'BookOpen',
    'Users',
    'Award',
    'Activity',
    'Anchor',
    'Aperture',
    'Archive',
    'ArrowDown',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'Cat',
    'Dog',
    'Fish',
    'Bird',
    'HeartCrack',
    'HeartHandshake',
    'HeartOff',
    'HeartPulse',
    'House',
    'IceCream',
    'Lollipop',
    'Mug',
    'Pizza',
    'Ramen',
    'Sushi',
    'Table',
    'Tree',
    'Umbrella',
    'Wand',
    'Watch',
    'Wifi',
    'ZapOff',
    'ZoomIn',
    'ZoomOut',
    'Anchor',
    'Aperture',
    'Archive',
    'ArrowDown',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ShieldAlert',
    'ShieldCheck',
    'ShieldClose',
    'ShieldOff',
    'ShieldQuestion',
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
            budgets: Array.isArray(props.category.budgets)
                ? props.category.budgets.map((budget: any) => ({
                      id: budget.id,
                      name: budget.name ?? '',
                      budget: budget.budget ?? '',
                  }))
                : [],
        };
    }
};

const addBudgetRow = () => {
    formData.value.budgets.push({
        name: '',
        budget: '',
    });
};

const removeBudgetRow = (index: number) => {
    formData.value.budgets.splice(index, 1);
};
const openDeleteBudgetModal = (categoryId: number, budgetId: number) => {
    deleteBudgetPayload.value = { categoryId, budgetId };
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
    <Dialog
        :open="props.open"
        @update:open="
            (value) => {
                if (value) initFormData();
                emit('update:open', value);
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader class="space-y-3">
                <DialogTitle>Categorie wijzigen</DialogTitle>
                <DialogDescription>
                    Pas de categoriegegevens aan. De preview toont hoe het eruit
                    ziet.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div
                    class="rounded-xl border border-slate-200/70 bg-gray-800 p-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            :class="`${dynamicBackgroundColor(formData.color, true)} rounded-full p-2`"
                        >
                            <Icon
                                :name="formData.icon"
                                :class="`${dynamicTextColor(formData.color)} h-5 w-5`"
                            />
                        </div>
                        <div class="flex flex-col">
                            <div class="text-sm font-medium">
                                {{ formData.name || 'Categorie naam' }}
                            </div>
                        </div>
                    </div>
                </div>
                <Form
                    id="category-update-form"
                    :action="categoryUpdateAction"
                    method="patch"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div class="space-y-2">
                        <label for="name" class="text-sm font-medium"
                            >Naam</label
                        >
                        <input
                            id="name"
                            v-model="formData.name"
                            name="name"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <label for="slug" class="text-sm font-medium"
                            >Slug</label
                        >
                        <input
                            id="slug"
                            :value="slugPreview"
                            class="w-full rounded-md border border-input bg-muted px-3 py-2 text-sm text-muted-foreground outline-none"
                            disabled
                        />
                        <div class="text-xs text-muted-foreground">Preview: {{ slugPreview || 'categorie-naam' }}</div>
                    </div>

                    <div class="space-y-2">
                        <label for="type" class="text-sm font-medium"
                            >Type</label
                        >
                        <select
                            id="type"
                            v-model="formData.type"
                            name="type"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                            required
                        >
                            <option
                                v-for="option in typeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label for="icon" class="text-sm font-medium"
                            >Icoon</label
                        >
                        <input type="hidden" name="icon" :value="formData.icon" />
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring">
                                    <span class="flex items-center gap-2">
                                        <Icon :name="formData.icon" class="h-4 w-4" />
                                        {{ formData.icon }}
                                    </span>
                                    <ChevronDown class="h-4 w-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="max-h-64 w-[var(--reka-dropdown-menu-trigger-width)]">
                                <DropdownMenuItem v-for="icon in iconOptions" :key="icon" @click="formData.icon = icon">
                                    <Icon :name="icon" class="h-4 w-4" />
                                    <span class="flex-1">{{ icon }}</span>
                                    <Check v-if="formData.icon === icon" class="h-4 w-4" />
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div class="space-y-2">
                        <label for="color" class="text-sm font-medium"
                            >Kleur</label
                        >
                        <input type="hidden" name="color" :value="formData.color" />
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring">
                                    <span class="flex items-center gap-2">
                                        <span class="h-3 w-3 rounded-full border border-white/20" :style="{ backgroundColor: colorPreview[formData.color] ?? '#64748b' }" />
                                        {{ formData.color }}
                                    </span>
                                    <ChevronDown class="h-4 w-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="max-h-64 w-[var(--reka-dropdown-menu-trigger-width)]">
                                <DropdownMenuItem v-for="color in colorOptions" :key="color" @click="formData.color = color">
                                    <span class="h-3 w-3 rounded-full border border-white/20" :style="{ backgroundColor: colorPreview[color] ?? '#64748b' }" />
                                    <span class="flex-1">{{ color }}</span>
                                    <Check v-if="formData.color === color" class="h-4 w-4" />
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div class="space-y-3 sm:col-span-2 border-t pt-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium">Budgetten</label>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md border border-dashed border-muted-foreground px-3 py-2 text-xs text-muted-foreground hover:bg-green-900 hover:text-white"
                                @click="addBudgetRow"
                            >
                                <div class="flex items-center gap-1">
                                    <Plus class="h-4 w-4" /> Voeg budget toe
                                </div>
                            </button>
                        </div>

                        <div
                            v-if="formData.budgets.length === 0"
                            class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                        >
                            Nog geen budgetten.
                        </div>

                        <div
                            v-for="(budget, index) in formData.budgets"
                            :key="budget.id ?? `new-${index}`"
                            class="grid grid-cols-11 gap-2"
                        >
                            <input
                                v-if="budget.id"
                                type="hidden"
                                :name="`budgets[${index}][id]`"
                                :value="budget.id"
                            />
                            <input
                                v-model="budget.name"
                                :name="`budgets[${index}][name]`"
                                placeholder="Naam budget"
                                class="col-span-5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                            />
                            <input
                                v-model="budget.budget"
                                :name="`budgets[${index}][budget]`"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="col-span-5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                            />
                            <div class="col-span-1 flex items-center justify-end">
                                <button v-if="budget.id && props.category" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-red-400 hover:bg-red-950/40" @click="openDeleteBudgetModal(props.category.id, budget.id)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                                <button v-else type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-red-400 hover:bg-red-950/40" @click="removeBudgetRow(index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </Form>
            </div>

            <Dialog :open="deleteBudgetPayload !== null" @update:open="(open) => { if (!open) deleteBudgetPayload = null; }">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Budget verwijderen</DialogTitle>
                        <DialogDescription>Weet je zeker dat je dit budget wilt verwijderen?</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="secondary" @click="deleteBudgetPayload = null">Annuleren</Button>
                        <Form
                            v-if="deleteBudgetPayload"
                            :action="`/categories/${deleteBudgetPayload.categoryId}/budgets/${deleteBudgetPayload.budgetId}`"
                            method="delete"
                        >
                            <Button type="submit" variant="destructive">Verwijderen</Button>
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <DialogFooter class="mt-4 gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" @click="close"
                        >Annuleren</Button
                    >
                </DialogClose>
                <Button form="category-update-form" type="submit"
                    >Opslaan</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
