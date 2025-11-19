<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { dynamicBackgroundColor, dynamicTextColor } from '@/composables/colorVariants';

interface Props {
    color?: string;
    icon?: string;
    category?: string;
    categoryFont?: string
}

const props = defineProps<Props>();

</script>

<template>
    <div class="flex items-center gap-2">
        <div :class="`${dynamicBackgroundColor(props.color, true)} p-2 rounded-full`">
            <Icon :name="props.icon" :class="`${dynamicTextColor(props.color)} h-4 w-4`" />
        </div>
        <span :class="`${categoryFont ?? ''} text-sm`">{{props.category}}</span>
    </div>
</template>
