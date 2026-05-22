<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import NotificationBanner from '@/components/NotificationBanner.vue';
import { home } from '@/routes';

const page = usePage();
const props = page.props as any;
</script>

<template>
  <Head title="TrueLayer koppeling" />
  <AppLayout :breadcrumbs="[{ title: 'Home', href: home().url }, { title: 'TrueLayer', href: '/truelayer' }]">
    <main class="space-y-4 p-4">
      <NotificationBanner v-if="props.error" type="error" :message="props.error" />

      <Card class="rounded-md shadow-xl">
        <CardContent>
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-base font-semibold">TrueLayer (ING) koppeling</h2>
              <p class="text-sm text-muted-foreground">Verbind je ING rekening via TrueLayer en toon saldo + transacties.</p>
            </div>
            <Button as-child :disabled="!props.configured">
              <a href="/truelayer/connect">{{ props.connected ? 'Opnieuw verbinden' : 'Verbind met ING' }}</a>
            </Button>
          </div>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-700 bg-slate-950/10 p-4 text-sm">
              <div class="mb-2 font-medium">Status</div>
              <p>{{ props.connected ? 'Verbonden' : 'Niet verbonden' }}</p>
              <p class="text-xs text-muted-foreground">
                {{ props.configured ? 'TrueLayer client credentials geladen.' : 'Zet TRUELAYER_CLIENT_ID en TRUELAYER_CLIENT_SECRET in .env.' }}
              </p>
            </div>
            <div class="rounded-lg border border-slate-700 bg-slate-950/10 p-4 text-sm">
              <div class="mb-2 font-medium">Account</div>
              <p>{{ props.account?.display_name ?? props.account?.provider?.display_name ?? 'Nog geen account geladen.' }}</p>
              <p class="text-xs text-muted-foreground">{{ props.account?.account_id ?? '' }}</p>
            </div>
          </div>

          <div class="mt-4 rounded-lg border border-slate-700 bg-slate-950/10 p-4 text-sm">
            <div class="mb-2 font-medium">Balans</div>
            <p v-if="props.balance">
              {{ props.balance.currency ?? 'EUR' }} {{ props.balance.current ?? props.balance.available ?? '-' }}
            </p>
            <p v-else>Nog geen balans geladen.</p>
          </div>

          <div class="mt-4 rounded-lg border border-slate-700 bg-slate-950/10 p-4 text-sm">
            <div class="mb-2 font-medium">Laatste transacties</div>
            <div v-if="props.transactions?.length" class="space-y-2">
              <div v-for="tx in props.transactions" :key="tx.transaction_id" class="flex items-center justify-between border-b border-slate-700/50 pb-2">
                <div>
                  <p>{{ tx.description ?? tx.merchant_name ?? 'Onbekende transactie' }}</p>
                  <p class="text-xs text-muted-foreground">{{ tx.timestamp ?? tx.booking_date ?? '' }}</p>
                </div>
                <p>{{ tx.currency ?? 'EUR' }} {{ tx.amount ?? '-' }}</p>
              </div>
            </div>
            <p v-else>Geen transacties geladen.</p>
          </div>
        </CardContent>
      </Card>
    </main>
  </AppLayout>
</template>
