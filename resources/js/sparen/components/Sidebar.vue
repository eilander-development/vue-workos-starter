<script setup lang="ts">
import { ref } from "vue";
import type { ActiveTab, BankAccount } from "../types";
import SidebarPanel from "./SidebarPanel.vue";

withDefaults(
  defineProps<{
    activeTab: ActiveTab;
    setActiveTab: (tab: ActiveTab) => void;
    bankAccount: BankAccount;
    isSyncing: boolean;
    isMobileOpen?: boolean;
  }>(),
  {
    isMobileOpen: false,
  }
);

const emit = defineEmits<{
  sync: [];
  closeMobile: [];
}>();

const STORAGE_KEY = "financien_sidebar_collapsed";

const isCollapsed = ref(readCollapsed());

function readCollapsed(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) === "true";
  } catch {
    return false;
  }
}

function handleToggle() {
  isCollapsed.value = !isCollapsed.value;
  try {
    localStorage.setItem(STORAGE_KEY, String(isCollapsed.value));
  } catch {
    // private mode / blocked storage
  }
}
</script>

<template>
  <aside
    id="app-sidebar-desktop"
    class="hidden md:flex bg-slate-900 border-r border-slate-800 flex-col h-screen sticky top-0 z-30 overflow-hidden transition-[width] duration-200 ease-in-out"
    :class="isCollapsed ? 'w-16 min-w-16 max-w-16' : 'w-64 min-w-64 max-w-64'"
  >
    <SidebarPanel
      :collapsed="isCollapsed"
      :active-tab="activeTab"
      :set-active-tab="setActiveTab"
      :bank-account="bankAccount"
      :is-syncing="isSyncing"
      :show-collapse-toggle="true"
      @sync="emit('sync')"
      @close-mobile="emit('closeMobile')"
      @toggle-collapse="handleToggle"
    />
  </aside>

  <div v-if="isMobileOpen" class="md:hidden fixed inset-0 z-50 flex">
    <div
      class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
      @click="emit('closeMobile')"
    />
    <div
      class="relative w-4/5 max-w-xs bg-slate-900 border-r border-slate-800 h-full shadow-2xl z-10 animate-fade-in flex flex-col min-w-0 overflow-hidden"
    >
      <SidebarPanel
        :collapsed="false"
        :active-tab="activeTab"
        :set-active-tab="setActiveTab"
        :bank-account="bankAccount"
        :is-syncing="isSyncing"
        :show-close-mobile="true"
        @sync="emit('sync')"
        @close-mobile="emit('closeMobile')"
      />
    </div>
  </div>
</template>
