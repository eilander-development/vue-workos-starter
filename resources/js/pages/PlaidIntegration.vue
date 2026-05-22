<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { Head, usePage } from '@inertiajs/vue3';
import NotificationBanner from '@/components/NotificationBanner.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useNotification } from '@/composables/useNotification';
import { computed, reactive, ref } from 'vue';
import { home } from '@/routes';

const page = usePage();
const props = page.props as any;
const connection = ref(props.plaidConnection ?? null);
const savedSettings = ref(props.plaidSettings ?? null);
const settings = reactive({
  client_id: savedSettings.value?.client_id ?? '',
  secret: '',
  environment: savedSettings.value?.environment ?? 'sandbox',
  webhook_url: savedSettings.value?.webhook_url ?? '',
});
const settingsModalOpen = ref(false);
const connectLoading = ref(false);
const refreshLoading = ref(false);
const saveLoading = ref(false);
const { notification, showNotification } = useNotification();

const canConnect = computed(() => !!settings.client_id && !!settings.secret);
const hasSavedSettings = computed(() => !!savedSettings.value?.client_id);

const openSettingsModal = () => {
  settingsModalOpen.value = true;
};

const savePlaidSettings = async () => {
  saveLoading.value = true;

  try {
    const response = await axios.post('/plaid/settings', settings, {
      headers: { Accept: 'application/json' },
    });

    savedSettings.value = response.data.settings;
    settings.secret = '';
    settingsModalOpen.value = false;

    showNotification('Plaid API-keys zijn opgeslagen.', 'success');
  } catch (error) {
    console.error('Kon Plaid-instellingen niet opslaan:', error);
    showNotification('Kon Plaid-instellingen niet opslaan.', 'error');
  } finally {
    saveLoading.value = false;
  }
};

const connectBank = async () => {
  connectLoading.value = true;

  try {
    const response = await axios.get('/plaid/link-token', {
      headers: { Accept: 'application/json' },
    });
    const linkToken = response.data.link_token;

    if (!linkToken) {
      throw new Error('Geen Plaid-linktoken ontvangen.');
    }

    await ensurePlaidScriptLoaded();

    const plaid = (window as any).Plaid;
    if (!plaid) {
      throw new Error('Plaid Link is niet beschikbaar.');
    }

    const handler = plaid.create({
      token: linkToken,
      env: settings.environment,
      onSuccess: async (publicToken: string) => {
        await submitPublicToken(publicToken);
      },
      onExit: (error: any) => {
        if (error) {
          console.error('Plaid Link exit', error);
          showNotification('Plaid-link is afgebroken.', 'error');
        }
        connectLoading.value = false;
      },
    });

    handler.open();
  } catch (error) {
    console.error('Kon Plaid-link niet starten:', error);
    showNotification('Kon Plaid-link niet starten.', 'error');
    connectLoading.value = false;
  }
};

const submitPublicToken = async (publicToken: string) => {
  try {
    const response = await axios.post(
      '/plaid/connect',
      { public_token: publicToken },
      { headers: { Accept: 'application/json' } },
    );

    connection.value = response.data.connection;
    const imported = response.data.summary?.imported ?? 0;
    const total = response.data.summary?.total ?? 0;

    showNotification(`Bank gekoppeld. ${imported} van ${total} transacties geïmporteerd.`, 'success');
  } catch (error) {
    console.error('Kon Plaid-verbinding niet voltooien:', error);
    showNotification('Kon Plaid-verbinding niet voltooien.', 'error');
  } finally {
    connectLoading.value = false;
  }
};

const refreshTransactions = async () => {
  refreshLoading.value = true;

  try {
    const response = await axios.post('/plaid/refresh', {}, {
      headers: { Accept: 'application/json' },
    });

    const imported = response.data.summary?.imported ?? 0;
    const total = response.data.summary?.total ?? 0;

    showNotification(`Transacties vernieuwd. ${imported} van ${total} transacties geïmporteerd.`, 'success');
  } catch (error) {
    console.error('Kon transacties niet verversen:', error);
    showNotification('Kon transacties niet verversen.', 'error');
  } finally {
    refreshLoading.value = false;
  }
};

const plaidScriptPromise = ref<Promise<void> | null>(null);
const ensurePlaidScriptLoaded = (): Promise<void> => {
  if (plaidScriptPromise.value) {
    return plaidScriptPromise.value;
  }

  plaidScriptPromise.value = new Promise((resolve, reject) => {
    if (typeof window === 'undefined') {
      reject(new Error('Browseromgeving vereist voor Plaid Link')); return;
    }

    if ((window as any).Plaid) {
      resolve();
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdn.plaid.com/link/v2/stable/link-initialize.js';
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Kon Plaid Link-script niet laden.'));
    document.head.appendChild(script);
  });

  return plaidScriptPromise.value;
};
</script>

<template>
  <Head title="Plaid bankkoppeling" />

  <AppLayout :breadcrumbs="[{ title: 'Home', href: home().url }, { title: 'Plaid', href: '/plaid' }]">
    <main class="space-y-4 p-4">
      <NotificationBanner v-if="notification" :type="notification.type" :message="notification.message" />

      <Card class="rounded-md shadow-xl">
        <CardContent>
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-base font-semibold">Plaid bankkoppeling</h2>
              <p class="text-sm text-muted-foreground">Koppel je bankrekening en vernieuw transacties zonder de ING importpagina te verstoren.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <Button type="button" variant="secondary" @click="openSettingsModal">API keys instellen</Button>
              <Button type="button" :disabled="connectLoading || !hasSavedSettings" @click="connectBank">
                {{ connection ? 'Nieuwe bank koppelen' : 'Bank koppelen' }}
              </Button>
              <Button type="button" variant="outline" :disabled="refreshLoading || !connection" @click="refreshTransactions">
                Ververs transacties
              </Button>
            </div>
          </div>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-700 bg-slate-950/10 p-4 text-sm">
              <div class="mb-2 font-medium">Huidige verbinding</div>
              <p>{{ connection ? connection.institution_name ?? 'Onbekend instituut' : 'Geen bank verbonden.' }}</p>
              <p class="text-xs text-muted-foreground">{{ connection ? `Item ID: ${connection.item_id}` : 'Klik op bank koppelen om te verbinden.' }}</p>
            </div>
            <div class="rounded-lg border border-slate-700 bg-slate-950/10 p-4 text-sm">
              <div class="mb-2 font-medium">API keys</div>
              <p>{{ hasSavedSettings ? 'API keys zijn opgeslagen.' : 'API keys ontbreken.' }}</p>
              <p class="text-xs text-muted-foreground">{{ savedSettings?.environment ? `Environment: ${savedSettings.environment}` : 'Environment nog niet ingesteld.' }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Dialog :open="settingsModalOpen" @update:open="(value) => settingsModalOpen = value">
        <DialogContent class="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Plaid API keys</DialogTitle>
          </DialogHeader>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium">Client ID</label>
              <input
                v-model="settings.client_id"
                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
            <div>
              <label class="block text-sm font-medium">Secret</label>
              <input
                v-model="settings.secret"
                type="password"
                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
            <div>
              <label class="block text-sm font-medium">Environment</label>
              <select
                v-model="settings.environment"
                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
              >
                <option value="sandbox">Sandbox</option>
                <option value="development">Development</option>
                <option value="production">Production</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium">Webhook URL</label>
              <input
                v-model="settings.webhook_url"
                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
          </div>

          <DialogFooter class="mt-4 gap-2">
            <DialogClose as-child>
              <Button variant="secondary">Annuleren</Button>
            </DialogClose>
            <Button type="button" :disabled="saveLoading" @click="savePlaidSettings">Opslaan</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </main>
  </AppLayout>
</template>
