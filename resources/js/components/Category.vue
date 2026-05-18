<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import {
    dynamicBackgroundColor,
    dynamicTextColor,
} from '@/composables/colorVariants';
import { Link } from '@inertiajs/vue3';

interface Props {
    color?: string;
    icon?: string;
    category?: string;
    categoryFont?: string;
    slug?: string;
    budget?: string;
    iconSize?: string;
    iconContainerSize?: string;
}

const props = defineProps<Props>();

const iconSize = props.iconSize ?? 'h-4 w-4';
const iconContainerSize = props.iconContainerSize ?? 'p-2';
</script>

<template>
    <Link :href="`/expenses/${props.slug}`" as="div" class="cursor-pointer">
        <div class="flex items-center gap-2">
            <div
                :class="`${dynamicBackgroundColor(props.color, true)} ${iconContainerSize} rounded-full`"
            >
                <Icon
                    :name="props.icon"
                    :class="`${dynamicTextColor(props.color)} ${iconSize}`"
                />
            </div>
            <div
                v-if="props.category && props.budget != undefined"
                class="flex flex-col"
            >
                <div :class="`${categoryFont ?? ''} text-xs`">
                    {{ props.category }}
                </div>
                <div class="text-[10px] text-muted-foreground">
                    {{ props.budget }}
                </div>
            </div>
            <span
                v-if="props.category && props.budget == undefined"
                :class="`${categoryFont ?? ''} text-sm`"
            >
                {{ props.category }}
            </span>
        </div>
    </Link>
</template>
