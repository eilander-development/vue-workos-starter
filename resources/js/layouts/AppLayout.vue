<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import NotificationCenter from '@/components/NotificationCenter.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <NotificationCenter />
        <template #header-right>
            <slot name="header-right" />
        </template>
        <slot />
    </AppLayout>
</template>
