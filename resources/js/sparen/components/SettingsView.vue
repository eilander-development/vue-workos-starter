<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Archive, Download, LogOut, Monitor, Moon, Sun, Upload, User } from "lucide-vue-next";
import { useAppearance } from "@/composables/useAppearance";
import { getSparenUser, logoutSparen, type SparenUser } from "../auth";
import { pathForSettingsSection, settingsSectionFromPath } from "../navigation";
import DataBackupModal, { type BackupStep } from "./DataBackupModal.vue";
import ImportReviewDialog, { type ImportTableSummary } from "./ImportReviewDialog.vue";

type SettingsTab = "profiel" | "uiterlijk" | "data" | "uitloggen";

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
const currentPassword = ref("");
const password = ref("");
const passwordConfirmation = ref("");
const loggingOut = ref(false);
const importInput = ref<HTMLInputElement | null>(null);
const pendingImportFile = ref<File | null>(null);
const reviewOpen = ref(false);
const reviewBusy = ref(false);
const reviewTables = ref<ImportTableSummary[]>([]);
const backupOpen = ref(false);
const backupTitle = ref("Backup");
const backupDescription = ref("");
const backupSteps = ref<BackupStep[]>([]);
const backupError = ref("");
const backupDone = ref(false);
const backupDoneLabel = ref("");

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

function csrfHeaders(accept = "application/json", jsonBody = true): HeadersInit {
  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  const xsrf = match ? decodeURIComponent(match[1]) : "";
  const token = meta || xsrf;
  const headers: Record<string, string> = {
    Accept: accept,
    "X-Requested-With": "XMLHttpRequest",
    "X-CSRF-TOKEN": token,
    "X-XSRF-TOKEN": xsrf || token,
  };
  if (jsonBody) {
    headers["Content-Type"] = "application/json";
  }

  return headers;
}

function markStep(id: string, status: BackupStep["status"]) {
  backupSteps.value = backupSteps.value.map((step) => (step.id === id ? { ...step, status } : step));
}

function filenameFromDisposition(header: string | null, fallback: string): string {
  if (!header) return fallback;
  const utf = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf?.[1]) return decodeURIComponent(utf[1]);
  const quoted = header.match(/filename="([^"]+)"/i);
  if (quoted?.[1]) return quoted[1];
  const plain = header.match(/filename=([^;]+)/i);

  return plain?.[1]?.trim() || fallback;
}

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

async function exportBackup() {
  backupOpen.value = true;
  backupTitle.value = "Data exporteren";
  backupDescription.value = "Er wordt een zip gemaakt. Daarna start de download en verdwijnt het bestand van de server.";
  backupError.value = "";
  backupDone.value = false;
  backupDoneLabel.value = "";
  backupSteps.value = [
    { id: "catalog", label: "Catalogus verzamelen", status: "active" },
    { id: "ledger", label: "Ledger verzamelen", status: "pending" },
    { id: "zip", label: "Zip maken", status: "pending" },
    { id: "download", label: "Download aanbieden", status: "pending" },
    { id: "cleanup", label: "Serverbestand verwijderen", status: "pending" },
  ];

  try {
    const response = await fetch("/api/sparen/backup/export", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders("application/zip", false),
    });
    const contentType = response.headers.get("content-type") || "";
    if (!response.ok || contentType.includes("application/json")) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data.message || "Export mislukt.");
    }

    markStep("catalog", "done");
    markStep("ledger", "done");
    markStep("zip", "done");
    markStep("download", "active");

    const blob = await response.blob();
    const filename = filenameFromDisposition(
      response.headers.get("content-disposition"),
      `finance-backup-${new Date().toISOString().slice(0, 10)}.zip`
    );
    triggerDownload(blob, filename);

    markStep("download", "done");
    markStep("cleanup", "done");
    backupDone.value = true;
    backupDoneLabel.value = `${filename} is gedownload. Het serverbestand is verwijderd.`;
  } catch (error) {
    backupError.value = error instanceof Error ? error.message : "Export mislukt.";
  }
}

function chooseImportFile() {
  importInput.value?.click();
}

function onImportFile(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;
  input.value = "";
  if (!file) return;
  pendingImportFile.value = file;
  void previewImport(file);
}

async function previewImport(file: File) {
  reviewBusy.value = true;
  reviewOpen.value = true;
  try {
    const body = new FormData();
    body.append("file", file);
    body.append("preview", "1");
    const response = await fetch("/api/sparen/backup/import", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders("application/json", false),
      body,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || "Vergelijken mislukt.");
    }
    reviewTables.value = (data.summary?.tables ?? []) as ImportTableSummary[];
  } catch (error) {
    reviewOpen.value = false;
    pendingImportFile.value = null;
    backupOpen.value = true;
    backupTitle.value = "Data importeren";
    backupDescription.value = "";
    backupError.value = error instanceof Error ? error.message : "Vergelijken mislukt.";
    backupDone.value = false;
    backupSteps.value = [];
  } finally {
    reviewBusy.value = false;
  }
}

function updateReviewResolution(key: string, value: "live" | "backup") {
  reviewTables.value = reviewTables.value.map((table) =>
    `${table.connection}.${table.table}` === key ? { ...table, resolution: value } : table
  );
}

async function confirmImport(resolutions: Record<string, "live" | "backup">) {
  const file = pendingImportFile.value;
  if (!file) return;

  reviewOpen.value = false;
  backupOpen.value = true;
  backupTitle.value = "Data importeren";
  backupDescription.value = "Nieuwe rijen worden toegevoegd. Bestaande rijen blijven, tenzij je de backup koos.";
  backupError.value = "";
  backupDone.value = false;
  backupDoneLabel.value = "";
  backupSteps.value = [
    { id: "upload", label: "Zip uploaden", status: "active" },
    { id: "merge", label: "Rijen vergelijken en zetten", status: "pending" },
    { id: "reload", label: "App vernieuwen", status: "pending" },
  ];

  try {
    const body = new FormData();
    body.append("file", file);
    body.append("resolutions", JSON.stringify(resolutions));
    const response = await fetch("/api/sparen/backup/import", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders("application/json", false),
      body,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || "Import mislukt.");
    }

    markStep("upload", "done");
    markStep("merge", "done");
    markStep("reload", "active");
    backupDone.value = true;
    backupDoneLabel.value = "Import klaar. De pagina wordt vernieuwd.";
    window.setTimeout(() => window.location.reload(), 700);
  } catch (error) {
    backupError.value = error instanceof Error ? error.message : "Import mislukt.";
  } finally {
    pendingImportFile.value = null;
  }
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
        current_password: currentPassword.value || undefined,
        password: password.value || undefined,
        password_confirmation: passwordConfirmation.value || undefined,
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
    currentPassword.value = "";
    password.value = "";
    passwordConfirmation.value = "";
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
        Beheer je profiel, thema, data en sessie.
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
          activeTab === 'data'
            ? 'bg-indigo-600 text-white'
            : 'bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700'
        "
        @click="selectSettingsTab('data')"
      >
        Data
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
        <p class="text-xs text-slate-400 mt-0.5">Pas je naam, e-mail en wachtwoord aan.</p>
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
        </div>

        <div>
          <label class="block text-slate-300 text-xs font-semibold mb-1" for="settings-current-password">
            Huidig wachtwoord
          </label>
          <input
            id="settings-current-password"
            v-model="currentPassword"
            type="password"
            autocomplete="current-password"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
          />
          <p v-if="fieldError('current_password')" class="text-xs text-rose-400 mt-1">
            {{ fieldError("current_password") }}
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-300 text-xs font-semibold mb-1" for="settings-password">
              Nieuw wachtwoord
            </label>
            <input
              id="settings-password"
              v-model="password"
              type="password"
              autocomplete="new-password"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
            />
            <p v-if="fieldError('password')" class="text-xs text-rose-400 mt-1">{{ fieldError("password") }}</p>
          </div>
          <div>
            <label class="block text-slate-300 text-xs font-semibold mb-1" for="settings-password-confirmation">
              Bevestigen
            </label>
            <input
              id="settings-password-confirmation"
              v-model="passwordConfirmation"
              type="password"
              autocomplete="new-password"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
            />
          </div>
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

    <div v-else-if="activeTab === 'data'" class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-4">
      <div class="flex items-start gap-3">
        <div class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
          <Archive class="w-4 h-4" />
        </div>
        <div>
          <h3 class="font-bold text-white text-sm">Backup</h3>
          <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
            Exporteer alles als zip. Import voegt ontbrekende rijen toe en vraagt bij twijfel of live of backup wint.
          </p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-colors"
          @click="exportBackup"
        >
          <Download class="w-4 h-4" />
          Exporteren
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-xl text-xs font-semibold transition-colors"
          @click="chooseImportFile"
        >
          <Upload class="w-4 h-4" />
          Importeren
        </button>
        <input
          ref="importInput"
          type="file"
          accept=".zip,application/zip"
          class="hidden"
          @change="onImportFile"
        />
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

    <ImportReviewDialog
      :is-open="reviewOpen"
      :tables="reviewTables"
      :busy="reviewBusy"
      @cancel="() => { reviewOpen = false; pendingImportFile = null; }"
      @confirm="confirmImport"
      @update="updateReviewResolution"
    />

    <DataBackupModal
      :is-open="backupOpen"
      :title="backupTitle"
      :description="backupDescription"
      :steps="backupSteps"
      :error="backupError"
      :done="backupDone"
      :done-label="backupDoneLabel"
      @close="backupOpen = false"
    />
  </div>
</template>
