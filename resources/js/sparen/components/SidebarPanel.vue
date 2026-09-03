<script setup lang="ts">
import type { Component } from "vue";
import {
  LayoutDashboard,
  Table,
  ArrowDownCircle,
  ArrowUpCircle,
  PiggyBank,
  ArrowLeftRight,
  Landmark,
  Tags,
  Sliders,
  Calendar,
  CheckCircle2,
  PanelLeftClose,
  PanelLeftOpen,
  X,
  ChevronsUpDown,
  UserRound,
  Palette,
  LogOut,
  Settings,
} from "lucide-vue-next";
import type { ActiveTab, BankAccount } from "../types";
import { handleSparenNavClick, pathForTab, pathForSettingsSection } from "../navigation";
import {
  formatIbanDisplay,
  getSparenUser,
  logoutSparen,
  userInitials,
} from "../auth";
import { computed, onUnmounted, ref, watch } from "vue";

const props = defineProps<{
  collapsed: boolean;
  activeTab: ActiveTab;
  setActiveTab: (tab: ActiveTab) => void;
  bankAccount: BankAccount;
  isSyncing: boolean;
  showCloseMobile?: boolean;
  showCollapseToggle?: boolean;
}>();

const emit = defineEmits<{
  sync: [];
  closeMobile: [];
  toggleCollapse: [];
}>();

function goSettings(event: MouseEvent, section: "profiel" | "uiterlijk") {
  if (event.defaultPrevented || event.button !== 0) return;
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  userMenuOpen.value = false;
  const path = pathForSettingsSection(section);
  if (window.location.pathname !== path) {
    window.history.pushState({ tab: "settings", section }, "", path);
  }
  props.setActiveTab("settings");
  emit("closeMobile");
}

const menuItems: {
  id: ActiveTab;
  label: string;
  icon: Component;
  badge?: string;
}[] = [
  { id: "maandbegroting", label: "Maandbegroting", icon: Table, badge: "Hoofd" },
  { id: "dashboard", label: "Dashboard (Analyse)", icon: LayoutDashboard },
  { id: "uitgaven", label: "Uitgaven", icon: ArrowDownCircle },
  { id: "inkomsten", label: "Inkomsten", icon: ArrowUpCircle },
  { id: "sparen", label: "Sparen & Buffer", icon: PiggyBank },
  { id: "transacties", label: "Transacties", icon: ArrowLeftRight, badge: "Live" },
  { id: "enablebanking", label: "Bankkoppeling", icon: Landmark, badge: "PSD2" },
  { id: "categorieen", label: "Categorieën & Rubrieken", icon: Tags },
  { id: "koppelregels", label: "Koppelregels", icon: Sliders },
  { id: "jaaroverzicht", label: "Jaaroverzicht", icon: Calendar },
  { id: "settings", label: "Instellingen", icon: Settings },
];

const isConnected = computed(() => props.bankAccount.status === "connected");
const formattedBalance = computed(() =>
  props.bankAccount.balance.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
);

const lastUpdateLabel = computed(() => {
  const iso = props.bankAccount.lastSyncedAt;
  if (iso) {
    const parsed = new Date(iso);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed.toLocaleString("nl-NL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    }
  }
  return props.bankAccount.lastSync || "";
});

const user = getSparenUser();
const displayName = user?.name || "Mark Eilander";
const displayIban = computed(
  () => formatIbanDisplay(props.bankAccount.iban) || "Geen IBAN"
);
const initials = userInitials(displayName) || "ME";

const userMenuOpen = ref(false);
const loggingOut = ref(false);
const userMenuRef = ref<HTMLDivElement | null>(null);

function handleSelectTab(tabId: ActiveTab) {
  props.setActiveTab(tabId);
  emit("closeMobile");
}

function onNavClick(event: MouseEvent, tab: ActiveTab) {
  handleSparenNavClick(event, tab, handleSelectTab);
}

function onPointerDown(event: MouseEvent) {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target as Node)) {
    userMenuOpen.value = false;
  }
}

function onKeyDown(event: KeyboardEvent) {
  if (event.key === "Escape") userMenuOpen.value = false;
}

watch(userMenuOpen, (open) => {
  if (open) {
    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);
  } else {
    document.removeEventListener("mousedown", onPointerDown);
    document.removeEventListener("keydown", onKeyDown);
  }
});

onUnmounted(() => {
  document.removeEventListener("mousedown", onPointerDown);
  document.removeEventListener("keydown", onKeyDown);
});

async function handleLogout() {
  if (loggingOut.value) return;
  loggingOut.value = true;
  try {
    await logoutSparen();
  } catch {
    loggingOut.value = false;
    userMenuOpen.value = false;
  }
}
</script>

<template>
  <div
    class="flex flex-col h-full min-h-0 min-w-0 flex-1 text-slate-300 select-none overflow-hidden"
  >
    <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden">
      <div
        class="p-3 sm:p-4 border-b border-slate-800 flex items-center"
        :class="collapsed ? 'justify-center' : 'justify-between'"
      >
        <div class="flex items-center gap-3">
          <a
            :href="pathForTab('maandbegroting')"
            class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20 font-bold text-lg shrink-0"
            title="Financiën Realtime Gezinsbudget"
            @click="onNavClick($event, 'maandbegroting')"
          >
            €
          </a>
          <div v-if="!collapsed">
            <h1 class="font-bold text-white tracking-tight text-base flex items-center gap-1.5">
              Financiën
              <span
                class="text-[10px] bg-indigo-500/20 text-indigo-400 px-1.5 py-0.5 rounded font-mono font-medium"
              >
                Realtime
              </span>
            </h1>
            <p class="text-xs text-slate-400">Gezinsbudget & PSD2</p>
          </div>
        </div>

        <button
          v-if="showCloseMobile && !collapsed"
          type="button"
          class="md:hidden p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800"
          @click="emit('closeMobile')"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <div
        v-if="!collapsed"
        class="p-3 mx-3 my-3 bg-slate-800/80 rounded-xl border border-slate-700/60 shadow-sm"
      >
        <div class="flex items-center justify-between mb-1.5">
          <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
              <span
                v-if="isConnected"
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
              />
              <span
                class="relative inline-flex rounded-full h-2.5 w-2.5"
                :class="isConnected ? 'bg-emerald-500' : 'bg-amber-500'"
              />
            </span>
            <span class="text-xs font-semibold text-white">ING Bank Koppeling</span>
          </div>
          <span
            class="text-[10px] px-1.5 py-0.5 rounded border"
            :class="
              isConnected && (bankAccount.consentDaysRemaining == null || bankAccount.consentDaysRemaining > 14)
                ? 'text-emerald-400 bg-emerald-950/60 border-emerald-800/50'
                : 'text-amber-400 bg-amber-950/60 border-amber-800/50'
            "
          >
            {{
              isConnected
                ? bankAccount.consentDaysRemaining != null
                  ? bankAccount.consentDaysRemaining <= 14
                    ? `Verloopt · ${bankAccount.consentDaysRemaining}d`
                    : `Actief · ${bankAccount.consentDaysRemaining}d`
                  : "Actief"
                : "Niet gekoppeld"
            }}
          </span>
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-[11px] text-slate-400">Saldo:</span>
          <span
            class="text-sm font-bold font-mono"
            :class="isConnected ? 'text-emerald-400' : 'text-slate-300'"
          >
            € {{ formattedBalance }}
          </span>
        </div>
        <div class="flex items-baseline justify-between mt-0.5">
          <span class="text-[11px] text-slate-400">Laatste update:</span>
          <span class="text-[11px] font-mono text-slate-300">
            {{ lastUpdateLabel || "—" }}
          </span>
        </div>
        <button
          id="sidebar-quick-sync-btn"
          type="button"
          :disabled="isSyncing"
          class="w-full mt-2 text-[11px] font-medium text-slate-300 hover:text-white bg-slate-700/70 hover:bg-slate-700 py-1.5 px-2 rounded-lg transition-colors flex items-center justify-center gap-1.5 disabled:opacity-50 active:scale-95"
          @click="emit('sync')"
        >
          <span v-if="isSyncing" class="inline-block animate-spin text-xs">⟳</span>
          <CheckCircle2 v-else class="w-3.5 h-3.5 text-emerald-400" />
          {{ isSyncing ? "Synchroniseren..." : "Nu bank synchroniseren" }}
        </button>
      </div>

      <div v-else class="p-2 mx-2 my-3 flex flex-col items-center gap-2">
        <div
          class="relative group cursor-pointer"
          :title="`${isConnected ? 'ING Actief' : 'ING niet gekoppeld'}${bankAccount.consentDaysRemaining != null ? ` · nog ${bankAccount.consentDaysRemaining} dagen` : ''}: € ${formattedBalance}`"
        >
          <button
            type="button"
            :disabled="isSyncing"
            class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-emerald-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm active:scale-95"
            @click="emit('sync')"
          >
            <span v-if="isSyncing" class="inline-block animate-spin text-xs">⟳</span>
            <Landmark v-else class="w-4 h-4" />
          </button>
          <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
            <span
              v-if="isConnected"
              class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
            />
            <span
              class="relative inline-flex rounded-full h-2.5 w-2.5"
              :class="isConnected ? 'bg-emerald-500' : 'bg-amber-500'"
            />
          </span>
        </div>
      </div>

      <nav class="space-y-1.5 mt-2" :class="collapsed ? 'px-2' : 'px-3'">
        <a
          v-for="item in menuItems"
          :id="`sidebar-nav-${item.id}`"
          :key="item.id"
          :href="pathForTab(item.id)"
          :aria-current="activeTab === item.id ? 'page' : undefined"
          :title="
            collapsed
              ? `${item.label}${item.badge ? ` (${item.badge})` : ''}`
              : undefined
          "
          class="w-full flex items-center rounded-xl text-sm font-medium transition-all group relative"
          :class="[
            collapsed ? 'justify-center p-2.5' : 'justify-between px-3 py-2.5',
            activeTab === item.id
              ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 font-semibold'
              : 'text-slate-300 hover:bg-slate-800/80 hover:text-white',
          ]"
          @click="onNavClick($event, item.id)"
        >
          <div class="flex items-center" :class="collapsed ? 'justify-center' : 'gap-3'">
            <component
              :is="item.icon"
              class="w-4 h-4"
              :class="
                activeTab === item.id
                  ? 'text-white'
                  : 'text-slate-400 group-hover:text-white'
              "
            />
            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
          </div>

          <span
            v-if="!collapsed && item.badge"
            class="text-[10px] font-bold px-1.5 py-0.5 rounded"
            :class="
              activeTab === item.id
                ? 'bg-indigo-700 text-white'
                : 'bg-slate-800 text-slate-400 border border-slate-700'
            "
          >
            {{ item.badge }}
          </span>

          <span
            v-if="collapsed && item.badge && activeTab !== item.id"
            class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-indigo-400"
          />
        </a>
      </nav>
    </div>

    <div class="shrink-0 border-t border-slate-800 bg-slate-900">
        <button
          v-if="showCollapseToggle"
          id="sidebar-toggle-collapse-btn"
          type="button"
          class="w-full p-2 hidden md:flex border-b border-slate-800/60 text-slate-400 hover:text-white hover:bg-slate-800/80 transition-colors relative z-10"
          :class="collapsed ? 'justify-center' : 'justify-between items-center'"
          :aria-expanded="!collapsed"
          :title="
            collapsed
              ? 'Menu uitklappen (voor meer details)'
              : 'Menu inklappen naar iconen (voor meer schermruimte)'
          "
          @click.stop="emit('toggleCollapse')"
        >
          <span v-if="!collapsed" class="text-[11px] pl-2">Menu inklappen</span>
          <span class="p-1.5 rounded-lg border border-slate-700/80 flex items-center justify-center">
            <PanelLeftOpen v-if="collapsed" class="w-5 h-5 text-indigo-400" />
            <PanelLeftClose v-else class="w-4 h-4" />
          </span>
        </button>

      <div class="p-3" :class="collapsed ? 'flex justify-center' : 'p-4'">
        <div ref="userMenuRef" class="relative w-full">
          <button
            type="button"
            id="sidebar-user-menu-btn"
            aria-haspopup="menu"
            :aria-expanded="userMenuOpen"
            class="w-full flex items-center gap-3 rounded-xl transition-colors"
            :class="[
              collapsed
                ? 'justify-center p-1.5 hover:bg-slate-800'
                : 'px-1.5 py-1.5 hover:bg-slate-800/80',
              userMenuOpen ? 'bg-slate-800/80' : '',
            ]"
            :title="`${displayName} (${displayIban})`"
            @click="userMenuOpen = !userMenuOpen"
          >
            <div
              class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-indigo-400 text-xs shrink-0"
            >
              {{ initials }}
            </div>
            <template v-if="!collapsed">
              <div class="overflow-hidden text-left flex-1 min-w-0">
                <p class="text-xs font-semibold text-white truncate">{{ displayName }}</p>
                <p class="text-[10px] text-slate-400 font-mono truncate">{{ displayIban }}</p>
              </div>
              <ChevronsUpDown class="w-4 h-4 text-slate-500 shrink-0" />
            </template>
          </button>

          <div
            v-if="userMenuOpen"
            role="menu"
            class="absolute z-50 min-w-56 rounded-xl border border-slate-700 bg-slate-900 shadow-xl shadow-black/40 py-1"
            :class="
              collapsed
                ? 'left-full bottom-0 ml-2'
                : 'left-0 right-0 bottom-full mb-2'
            "
          >
            <div class="px-3 py-2.5 border-b border-slate-800">
              <p class="text-xs font-semibold text-white truncate">{{ displayName }}</p>
              <p
                v-if="user?.email"
                class="text-[11px] text-slate-400 truncate mt-0.5"
              >
                {{ user.email }}
              </p>
            </div>

            <a
              role="menuitem"
              href="/instellingen/profiel"
              class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-200 hover:bg-slate-800 hover:text-white transition-colors"
              @click="(event) => goSettings(event, 'profiel')"
            >
              <UserRound class="w-4 h-4 text-slate-400" />
              Profiel
            </a>
            <a
              role="menuitem"
              href="/instellingen/uiterlijk"
              class="flex items-center gap-2.5 px-3 py-2 text-xs text-slate-200 hover:bg-slate-800 hover:text-white transition-colors"
              @click="(event) => goSettings(event, 'uiterlijk')"
            >
              <Palette class="w-4 h-4 text-slate-400" />
              Uiterlijk
            </a>

            <div class="my-1 border-t border-slate-800" />

            <button
              type="button"
              role="menuitem"
              data-test="logout-button"
              :disabled="loggingOut"
              class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-rose-300 hover:bg-rose-950/50 hover:text-rose-200 transition-colors disabled:opacity-50"
              @click="handleLogout"
            >
              <LogOut class="w-4 h-4" />
              {{ loggingOut ? "Bezig met uitloggen..." : "Uitloggen" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
