import React from "react";
import {
  Table,
  ArrowDownCircle,
  ArrowUpCircle,
  ArrowLeftRight,
  Menu,
} from "lucide-react";
import { ActiveTab } from "../types";
import { handleSparenNavClick, pathForTab } from "../navigation";

interface BottomNavProps {
  activeTab: ActiveTab;
  setActiveTab: (tab: ActiveTab) => void;
  onOpenMobileMenu: () => void;
}

const itemClass = (isActive: boolean, activeColor: string) =>
  `flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all ${
    isActive ? `${activeColor} font-bold scale-105` : "text-slate-400 hover:text-slate-200"
  }`;

export const BottomNav: React.FC<BottomNavProps> = ({
  activeTab,
  setActiveTab,
  onOpenMobileMenu,
}) => {
  const isMoreActive = [
    "dashboard",
    "sparen",
    "enablebanking",
    "categorieen",
    "koppelregels",
    "jaaroverzicht",
  ].includes(activeTab);

  return (
    <nav
      id="mobile-bottom-nav"
      aria-label="Mobiele navigatie"
      className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-t border-slate-800 px-2 py-1.5 shadow-2xl flex items-center justify-around"
    >
      <a
        href={pathForTab("maandbegroting")}
        aria-current={activeTab === "maandbegroting" ? "page" : undefined}
        onClick={(event) => handleSparenNavClick(event, "maandbegroting", setActiveTab)}
        className={itemClass(activeTab === "maandbegroting", "text-indigo-400")}
      >
        <Table className="w-5 h-5 mb-0.5" />
        <span className="text-[10px]">Begroting</span>
      </a>

      <a
        href={pathForTab("uitgaven")}
        aria-current={activeTab === "uitgaven" ? "page" : undefined}
        onClick={(event) => handleSparenNavClick(event, "uitgaven", setActiveTab)}
        className={itemClass(activeTab === "uitgaven", "text-rose-400")}
      >
        <ArrowDownCircle className="w-5 h-5 mb-0.5" />
        <span className="text-[10px]">Uitgaven</span>
      </a>

      <a
        href={pathForTab("inkomsten")}
        aria-current={activeTab === "inkomsten" ? "page" : undefined}
        onClick={(event) => handleSparenNavClick(event, "inkomsten", setActiveTab)}
        className={itemClass(activeTab === "inkomsten", "text-emerald-400")}
      >
        <ArrowUpCircle className="w-5 h-5 mb-0.5" />
        <span className="text-[10px]">Inkomsten</span>
      </a>

      <a
        href={pathForTab("transacties")}
        aria-current={activeTab === "transacties" ? "page" : undefined}
        onClick={(event) => handleSparenNavClick(event, "transacties", setActiveTab)}
        className={itemClass(activeTab === "transacties", "text-indigo-400")}
      >
        <ArrowLeftRight className="w-5 h-5 mb-0.5" />
        <span className="text-[10px]">Transacties</span>
      </a>

      <button
        type="button"
        onClick={onOpenMobileMenu}
        className={itemClass(isMoreActive, "text-indigo-400")}
      >
        <Menu className="w-5 h-5 mb-0.5" />
        <span className="text-[10px]">Menu</span>
      </button>
    </nav>
  );
};
