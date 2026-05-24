<script setup lang="ts">
import NotificationBanner from '@/components/NotificationBanner.vue';
import { useNotification } from '@/composables/useNotification';

const { notification } = useNotification();
</script>

<template>
    <div class="pointer-events-none fixed right-4 bottom-4 z-[100] w-full max-w-md">
        <div class="pointer-events-auto">
            <NotificationBanner v-if="notification" :type="notification.type" :message="notification.message" />
        </div>
    </div>
</template>
