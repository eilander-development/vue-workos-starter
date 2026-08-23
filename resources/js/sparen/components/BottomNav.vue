<script setup lang="ts">
import { computed } from "vue";
import {
  Table,
  ArrowDownCircle,
  ArrowUpCircle,
  ArrowLeftRight,
  Menu,
} from "lucide-vue-next";
import type { ActiveTab } from "../types";
import { handleSparenNavClick, pathForTab } from "../navigation";

const props = defineProps<{
  activeTab: ActiveTab;
  setActiveTab: (tab: ActiveTab) => void;
}>();

const emit = defineEmits<{
  openMobileMenu: [];
}>();

const itemClass = (isActive: boolean, activeColor: string) =>
  `flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all ${
    isActive ? `${activeColor} font-bold scale-105` : "text-slate-400 hover:text-slate-200"
  }`;

const isMoreActive = computed(() =>
  [
    "dashboard",
    "sparen",
    "enablebanking",
    "categorieen",
    "koppelregels",
    "jaaroverzicht",
  ].includes(props.activeTab)
);

function onNavClick(event: MouseEvent, tab: ActiveTab) {
  handleSparenNavClick(event, tab, props.setActiveTab);
}
</script>

<template>
  <nav
    id="mobile-bottom-nav"
    aria-label="Mobiele navigatie"
    class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-t border-slate-800 px-2 py-1.5 shadow-2xl flex items-center justify-around"
  >
    <a
      :href="pathForTab('maandbegroting')"
      :aria-current="activeTab === 'maandbegroting' ? 'page' : undefined"
      :class="itemClass(activeTab === 'maandbegroting', 'text-indigo-400')"
      @click="onNavClick($event, 'maandbegroting')"
    >
      <Table class="w-5 h-5 mb-0.5" />
      <span class="text-[10px]">Begroting</span>
    </a>

    <a
      :href="pathForTab('uitgaven')"
      :aria-current="activeTab === 'uitgaven' ? 'page' : undefined"
      :class="itemClass(activeTab === 'uitgaven', 'text-rose-400')"
      @click="onNavClick($event, 'uitgaven')"
    >
      <ArrowDownCircle class="w-5 h-5 mb-0.5" />
      <span class="text-[10px]">Uitgaven</span>
    </a>

    <a
      :href="pathForTab('inkomsten')"
      :aria-current="activeTab === 'inkomsten' ? 'page' : undefined"
      :class="itemClass(activeTab === 'inkomsten', 'text-emerald-400')"
      @click="onNavClick($event, 'inkomsten')"
    >
      <ArrowUpCircle class="w-5 h-5 mb-0.5" />
      <span class="text-[10px]">Inkomsten</span>
    </a>

    <a
      :href="pathForTab('transacties')"
      :aria-current="activeTab === 'transacties' ? 'page' : undefined"
      :class="itemClass(activeTab === 'transacties', 'text-indigo-400')"
      @click="onNavClick($event, 'transacties')"
    >
      <ArrowLeftRight class="w-5 h-5 mb-0.5" />
      <span class="text-[10px]">Transacties</span>
    </a>

    <button
      type="button"
      :class="itemClass(isMoreActive, 'text-indigo-400')"
      @click="emit('openMobileMenu')"
    >
      <Menu class="w-5 h-5 mb-0.5" />
      <span class="text-[10px]">Menu</span>
    </button>
  </nav>
</template>
