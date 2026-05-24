<script setup lang="ts">
import { customScrollbar } from '@/composables/scrollbar';
import { cn } from '@/lib/utils';
import { Check, ChevronDown, Search } from 'lucide-vue-next';
import { colorOptions, colorPreview } from './pickerOptions';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const model = defineModel<string>({ required: true });
const props = withDefaults(defineProps<{ placeholder?: string }>(), {
    placeholder: 'Zoek kleur...',
});

const open = ref(false);
const query = ref('');
const rootRef = ref<HTMLElement | null>(null);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return colorOptions;
    return colorOptions.filter((color) => color.includes(q));
});

const onClickOutside = (event: MouseEvent) => {
    if (!open.value) return;
    const target = event.target as Node | null;
    if (rootRef.value && target && !rootRef.value.contains(target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="rootRef" class="relative" :class="{ 'z-[220]': open }">
        <button type="button" class="flex w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" @click="open = !open">
            <span class="flex items-center gap-2 truncate">
                <span class="h-3 w-3 rounded-full border border-white/20 shrink-0" :style="{ backgroundColor: colorPreview[model] ?? '#64748b' }" />
                <span class="truncate">{{ model }}</span>
            </span>
            <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0" />
        </button>
            <div v-if="open" class="absolute z-[260] mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md">
                <div class="border-b p-2">
                    <div class="relative">
                        <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="query" :placeholder="props.placeholder" class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-2 text-sm outline-none focus:ring-2 focus:ring-ring" />
                    </div>
                </div>
                <div :class="cn(customScrollbar, 'max-h-72 overflow-y-auto p-1')">
                    <button
                        v-for="color in filtered"
                        :key="color"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
                        @click="
                            model = color;
                            open = false;
                        "
                    >
                        <span class="h-3 w-3 rounded-full border border-white/20" :style="{ backgroundColor: colorPreview[color] ?? '#64748b' }" />
                        <span class="flex-1 truncate text-left">{{ color }}</span>
                        <Check v-if="model === color" class="h-4 w-4" />
                    </button>
                </div>
            </div>
    </div>
</template>
