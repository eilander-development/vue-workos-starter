<script setup lang="ts">
import { computed, ref } from "vue";
import type { ActiveTab, BankAccount } from "../types";
import SidebarPanel from "./SidebarPanel.vue";

const props = withDefaults(
  defineProps<{
    activeTab: ActiveTab;
    setActiveTab: (tab: ActiveTab) => void;
    bankAccount: BankAccount;
    isSyncing: boolean;
    isMobileOpen?: boolean;
    isCollapsed?: boolean;
  }>(),
  {
    isMobileOpen: false,
  }
);

const emit = defineEmits<{
  sync: [];
  closeMobile: [];
  toggleCollapse: [];
}>();

const internalCollapsed = ref(
  typeof localStorage !== "undefined" &&
    localStorage.getItem("financien_sidebar_collapsed") === "true"
);

const isCollapsed = computed(() =>
  props.isCollapsed !== undefined ? props.isCollapsed : internalCollapsed.value
);

function handleToggle() {
  if (props.isCollapsed !== undefined) {
    emit("toggleCollapse");
    return;
  }
  const nextVal = !internalCollapsed.value;
  internalCollapsed.value = nextVal;
  localStorage.setItem("financien_sidebar_collapsed", String(nextVal));
}
</script>

<template>
  <aside
    id="app-sidebar-desktop"
    class="hidden md:flex bg-slate-900 border-r border-slate-800 flex-col shrink-0 h-screen sticky top-0 z-30 transition-[width] duration-200 ease-in-out"
    :class="isCollapsed ? 'w-16' : 'w-64'"
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
      class="relative w-4/5 max-w-xs bg-slate-900 border-r border-slate-800 h-full shadow-2xl z-10 animate-fade-in flex flex-col"
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
        @toggle-collapse="handleToggle"
      />
    </div>
  </div>
</template>
