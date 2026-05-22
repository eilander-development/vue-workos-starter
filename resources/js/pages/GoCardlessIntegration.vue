<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { Head, Link, usePage } from '@inertiajs/vue3';
import NotificationBanner from '@/components/NotificationBanner.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useNotification } from '@/composables/useNotification';
import { computed, ref } from 'vue';
import { home } from '@/routes';

const page = usePage();
const props = page.props as any;
const merchant = ref(props.gocardlessMerchant ?? null);
const gocardlessConfigured = ref(props.gocardlessConfigured ?? false);
const connecting = ref(false);
const { notification, showNotification } = useNotification();

const merchantLabel = computed(() => {
  if (!gocardlessConfigured.value) {
    return 'GoCardless toegangstoken ontbreekt.';
  }

  if (!merchant.value) {
    return 'Nog geen GoCardless-account verbonden.';
  }

  return merchant.value.name ?? merchant.value.id ?? 'GoCardless-account verbonden';
});

const connectGoCardless = async () => {
  connecting.value = true;

  try {
    const response = await axios.post('/gocardless/connect', {}, {
      headers: { Accept: 'application/json' },
    });

    merchant.value = response.data.merchant;
    showNotification('GoCardless account succesvol verbonden.', 'success');
  } catch (error) {
    console.error('Kon GoCardless niet verbinden:', error);
    const message = error?.response?.data?.message ?? 'Kon GoCardless account niet verbinden.';
    showNotification(message, 'error');
  } finally {
    connecting.value = false;
  }
};
</script>

<template>
  <Head title="GoCardless koppeling" />

  <AppLayout :breadcrumbs="[{ title: 'Home', href: home().url }, { title: 'GoCardless', href: '/gocardless' }]">
    <main class="space-y-4 p-4">
      <NotificationBanner v-if="notification" :type="notification.type" :message="notification.message" />

      <Card class="rounded-md shadow-xl">
        <CardContent>
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-base font-semibold">GoCardless koppeling</h2>
              <p class="text-sm text-muted-foreground">Verbind je GoCardless account met je live access token uit de omgeving.</p>
            </div>
            <Button type="button" :disabled="connecting || !gocardlessConfigured" @click="connectGoCardless">
              {{ merchant ? 'Controleer GoCardless' : 'Verbind GoCardless' }}
            </Button>
          </div>

          <div class="mt-4 rounded-lg border border-slate-700 bg-slate-950/10 p-4 text-sm">
            <div class="mb-2 font-medium">GoCardless status</div>
            <p>{{ merchantLabel }}</p>
            <p class="text-xs text-muted-foreground">
              {{ gocardlessConfigured ? 'Live access token is geladen uit .env.' : 'Voeg GO_CARDLESS_ACCESS_TOKEN toe aan je .env.' }}
            </p>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <Link href="/imports/transactions">
              <Button type="button" variant="outline">Ga naar transacties import</Button>
            </Link>
          </div>
        </CardContent>
      </Card>
    </main>
  </AppLayout>
</template>
