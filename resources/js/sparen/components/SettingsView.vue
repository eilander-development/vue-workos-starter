<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { LogOut, Monitor, Moon, Sun, User } from "lucide-vue-next";
import { useAppearance } from "@/composables/useAppearance";
import { getSparenUser, logoutSparen, type SparenUser } from "../auth";
import { pathForSettingsSection, settingsSectionFromPath } from "../navigation";

type SettingsTab = "profiel" | "uiterlijk" | "uitloggen";

const props = defineProps<{
  user?: SparenUser | null;
}>();

const activeTab = ref<SettingsTab>(settingsSectionFromPath(window.location.pathname));
const resolvedUser = computed(() => props.user ?? getSparenUser());

const name = ref(resolvedUser.value?.name ?? "");
const email = ref(resolvedUser.value?.email ?? "");
const saving = ref(false);
const saved = ref(false);
const errors = ref<Record<string, string[]>>({});
const formError = ref("");
const loggingOut = ref(false);

const { appearance, updateAppearance } = useAppearance();

const appearanceTabs = [
  { value: "light" as const, Icon: Sun, label: "Licht" },
  { value: "dark" as const, Icon: Moon, label: "Donker" },
  { value: "system" as const, Icon: Monitor, label: "Systeem" },
];

function selectSettingsTab(tab: SettingsTab) {
  activeTab.value = tab;
  const path = pathForSettingsSection(tab);
  if (window.location.pathname !== path) {
    window.history.pushState({ tab: "settings", section: tab }, "", path);
  }
}

watch(
  resolvedUser,
  (user) => {
    name.value = user?.name ?? "";
    email.value = user?.email ?? "";
  },
  { immediate: true }
);

function csrfHeaders(): HeadersInit {
  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  const xsrf = match ? decodeURIComponent(match[1]) : "";
  const token = meta || xsrf;

  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-CSRF-TOKEN": token,
    "X-XSRF-TOKEN": xsrf || token,
  };
}

async function saveProfile() {
  saving.value = true;
  saved.value = false;
  errors.value = {};
  formError.value = "";

  try {
    const response = await fetch("/settings/profile", {
      method: "PATCH",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({
        name: name.value.trim(),
        email: email.value.trim(),
      }),
    });

    const data = await response.json().catch(() => ({}));

    if (response.status === 422) {
      errors.value = (data.errors as Record<string, string[]>) ?? {};
      formError.value = data.message || "Controleer de invoer.";
      return;
    }

    if (!response.ok) {
      formError.value = data.message || data.error || "Profiel kon niet worden opgeslagen.";
      return;
    }

    saved.value = true;
    if (window.__SPAREN__?.user) {
      window.__SPAREN__.user = {
        ...window.__SPAREN__.user,
        name: name.value.trim(),
        email: email.value.trim() || window.__SPAREN__.user.email,
      };
    }
  } catch {
    formError.value = "Profiel kon niet worden opgeslagen.";
  } finally {
    saving.value = false;
  }
}

async function handleLogout() {
  loggingOut.value = true;
  try {
    await logoutSparen();
  } finally {
    loggingOut.value = false;
  }
}

function fieldError(field: string): string | undefined {
  return errors.value[field]?.[0];
}
</script>

<template>
  <div id="settings-view" class="space-y-6">
    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
      <div class="flex items-center gap-2">
        <User class="w-5 h-5 text-indigo-400" />
        <h2 class="text-xl font-bold text-white tracking-tight">Instellingen</h2>
      </div>
      <p class="text-xs text-slate-400 mt-1">
        Beheer je profiel, thema en sessie voor Sparen.
      </p>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        type="button"
        class="px-3.5 py-2 rounded-xl text-xs font-semibold transition-colors"
        :class="
          activeTab === 'profiel'
            ? 'bg-indigo-600 text-white'
            : 'bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700'
        "
        @click="selectSettingsTab('profiel')"
      >
        Profiel
      </button>
      <button
        type="button"
        class="px-3.5 py-2 rounded-xl text-xs font-semibold transition-colors"
        :class="
          activeTab === 'uiterlijk'
            ? 'bg-indigo-600 text-white'
            : 'bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700'
        "
        @click="selectSettingsTab('uiterlijk')"
      >
        Uiterlijk
      </button>
      <button
        type="button"
        class="px-3.5 py-2 rounded-xl text-xs font-semibold transition-colors"
        :class="
          activeTab === 'uitloggen'
            ? 'bg-indigo-600 text-white'
            : 'bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700'
        "
        @click="selectSettingsTab('uitloggen')"
      >
        Uitloggen
      </button>
    </div>

    <div v-if="activeTab === 'profiel'" class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-4">
      <div>
        <h3 class="font-bold text-white text-sm">Profiel</h3>
        <p class="text-xs text-slate-400 mt-0.5">Pas je naam en e-mailadres aan.</p>
      </div>

      <form class="space-y-4" @submit.prevent="saveProfile">
        <div>
          <label class="block text-slate-300 text-xs font-semibold mb-1" for="settings-name">Naam</label>
          <input
            id="settings-name"
            v-model="name"
            type="text"
            required
            autocomplete="name"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
          />
          <p v-if="fieldError('name')" class="text-xs text-rose-400 mt-1">{{ fieldError("name") }}</p>
        </div>

        <div>
          <label class="block text-slate-300 text-xs font-semibold mb-1" for="settings-email">E-mail</label>
          <input
            id="settings-email"
            v-model="email"
            type="email"
            autocomplete="username"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500 disabled:opacity-60"
          />
          <p v-if="fieldError('email')" class="text-xs text-rose-400 mt-1">{{ fieldError("email") }}</p>
          <p class="text-[11px] text-slate-500 mt-1">
            Alleen naam wordt standaard door de server bijgewerkt; e-mail kan read-only zijn.
          </p>
        </div>

        <p v-if="formError" class="text-xs text-rose-400">{{ formError }}</p>
        <p v-if="saved" class="text-xs text-emerald-400">Opgeslagen.</p>

        <button
          type="submit"
          :disabled="saving"
          class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-colors"
        >
          {{ saving ? "Bezig…" : "Opslaan" }}
        </button>
      </form>
    </div>

    <div v-else-if="activeTab === 'uiterlijk'" class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-4">
      <div>
        <h3 class="font-bold text-white text-sm">Uiterlijk</h3>
        <p class="text-xs text-slate-400 mt-0.5">Kies licht, donker of volg het systeem.</p>
      </div>

      <div class="inline-flex gap-1 rounded-lg bg-slate-800 p-1 border border-slate-700">
        <button
          v-for="{ value, Icon, label } in appearanceTabs"
          :key="value"
          type="button"
          class="flex items-center rounded-md px-3.5 py-1.5 transition-colors"
          :class="
            appearance === value
              ? 'bg-slate-700 text-white shadow-xs'
              : 'text-slate-400 hover:bg-slate-700/60 hover:text-white'
          "
          @click="updateAppearance(value)"
        >
          <component :is="Icon" class="-ml-1 h-4 w-4" />
          <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
      </div>
    </div>

    <div v-else class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-4">
      <div>
        <h3 class="font-bold text-white text-sm">Uitloggen</h3>
        <p class="text-xs text-slate-400 mt-0.5">Beëindig je sessie op dit apparaat.</p>
      </div>

      <button
        type="button"
        :disabled="loggingOut"
        class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-colors"
        @click="handleLogout"
      >
        <LogOut class="w-4 h-4" />
        {{ loggingOut ? "Bezig…" : "Uitloggen" }}
      </button>
    </div>
  </div>
</template>
