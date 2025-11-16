<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { PiggyBank } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <PiggyBank class="size-6 fill-current text-white" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold"
            >Finance</span
        >
    </div>
</template>
