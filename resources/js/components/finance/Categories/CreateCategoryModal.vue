<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import ColorPicker from '@/components/finance/pickers/ColorPicker.vue';
import IconPicker from '@/components/finance/pickers/IconPicker.vue';
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
import { Form, router } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { computed, ref } from 'vue';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
}>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const defaultFormData = () => ({
    name: '',
    slug: '',
    icon: 'Plus',
    color: 'slate',
    type: 'expense',
    budgets: [] as Array<{ name: string; budget: number | string }>,
});

const formData = ref(defaultFormData());
const isCreatingCategory = ref(false);
const { showSuccess } = useNotification();
const slugPreview = computed(() =>
    (formData.value.name || '')
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w-]/g, '')
        .replace(/--+/g, '-'),
);
const typeOptions = [
    { value: 'expense', label: 'Uitgaven' },
    { value: 'income', label: 'Inkomsten' },
    { value: 'saving', label: 'Sparen' },
    { value: 'uncategorized', label: 'Ongecategoriseerd' },
];

const addBudgetRow = () => {
    formData.value.budgets.push({ name: '', budget: '' });
};

const removeBudgetRow = (index: number) => {
    formData.value.budgets.splice(index, 1);
};

const close = () => {
    formData.value = defaultFormData();
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="props.open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader class="space-y-3">
                <DialogTitle>Nieuwe categorie</DialogTitle>
                <DialogDescription>Voeg een categorie toe. De preview toont hoe het eruit ziet.</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200/70 bg-gray-800 p-4">
                    <div class="flex items-center gap-3">
                        <div :class="`${dynamicBackgroundColor(formData.color, true)} rounded-full p-2`">
                            <Icon :name="formData.icon" :class="`${dynamicTextColor(formData.color)} h-5 w-5`" />
                        </div>
                        <div class="flex flex-col">
                            <div class="text-sm font-medium">{{ formData.name || 'Categorie naam' }}</div>
                        </div>
                    </div>
                </div>

                <Form
                    id="create-category-form"
                    action="/categories"
                    method="post"
                    class="grid gap-4 sm:grid-cols-2"
                    @start="isCreatingCategory = true"
                    @finish="isCreatingCategory = false"
                    @success="
                        () => {
                            showSuccess('Categorie aangemaakt.');
                            close();
                            router.reload({
                                only: ['categories', 'stats', 'activeFilter', 'pagination'],
                                preserveScroll: true,
                            });
                        }
                    "
                >
                    <div class="space-y-2">
                        <label for="name" class="text-sm font-medium">Naam</label>
                        <input id="name" v-model="formData.name" name="name" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required />
                    </div>

                    <div class="space-y-2">
                        <label for="slug" class="text-sm font-medium">Slug</label>
                        <input id="slug" :value="slugPreview" class="w-full rounded-md border border-input bg-muted px-3 py-2 text-sm text-muted-foreground outline-none" disabled />
                        <div class="text-xs text-muted-foreground">Preview: {{ slugPreview || 'categorie-naam' }}</div>
                    </div>

                    <div class="space-y-2">
                        <label for="type" class="text-sm font-medium">Type</label>
                        <select id="type" v-model="formData.type" name="type" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" required>
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label for="icon" class="text-sm font-medium">Icoon</label>
                        <input type="hidden" name="icon" :value="formData.icon" />
                        <IconPicker v-model="formData.icon" />
                    </div>

                    <div class="space-y-2">
                        <label for="color" class="text-sm font-medium">Kleur</label>
                        <input type="hidden" name="color" :value="formData.color" />
                        <ColorPicker v-model="formData.color" />
                    </div>

                    <div class="space-y-3 sm:col-span-2 border-t pt-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium">Budgetten</label>
                            <button type="button" class="cursor-pointer rounded-md border border-dashed border-muted-foreground px-3 py-2 text-xs text-muted-foreground hover:bg-green-900 hover:text-white" @click="addBudgetRow">
                                <div class="flex items-center gap-1">
                                    <Plus class="h-4 w-4" /> Voeg budget toe
                                </div>
                            </button>
                        </div>

                        <div v-if="formData.budgets.length === 0" class="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                            Nog geen budgetten.
                        </div>

                        <div v-for="(budget, index) in formData.budgets" :key="`new-${index}`" class="grid grid-cols-11 gap-2">
                            <input v-model="budget.name" :name="`budgets[${index}][name]`" placeholder="Naam budget" class="col-span-5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" />
                            <input v-model="budget.budget" :name="`budgets[${index}][budget]`" type="number" min="0" step="0.01" placeholder="0.00" class="col-span-5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" />
                            <div class="col-span-1 flex items-center justify-end">
                                <button type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-red-400 hover:bg-red-950/40" @click="removeBudgetRow(index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </Form>
            </div>

            <DialogFooter class="mt-4 gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isCreatingCategory" @click="close">Annuleren</Button>
                </DialogClose>
                <Button form="create-category-form" type="submit" :disabled="isCreatingCategory">
                    {{ isCreatingCategory ? 'Opslaan...' : 'Opslaan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
