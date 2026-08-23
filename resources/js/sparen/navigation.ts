import { useCallback, useEffect, useState, type MouseEvent } from "react";
import { ActiveTab } from "./types";

export const SPAREN_PAGES: {
  tab: ActiveTab;
  path: string;
  title: string;
}[] = [
  { tab: "maandbegroting", path: "/maandbegroting", title: "Maandbegroting" },
  { tab: "dashboard", path: "/dashboard", title: "Dashboard" },
  { tab: "uitgaven", path: "/uitgaven", title: "Uitgaven" },
  { tab: "inkomsten", path: "/inkomsten", title: "Inkomsten" },
  { tab: "sparen", path: "/sparen", title: "Sparen & Buffer" },
  { tab: "transacties", path: "/transacties", title: "Transacties" },
  { tab: "enablebanking", path: "/bankkoppeling", title: "Bankkoppeling" },
  { tab: "categorieen", path: "/categorieen", title: "Categorieën & Rubrieken" },
  { tab: "koppelregels", path: "/koppelregels", title: "Koppelregels" },
  { tab: "jaaroverzicht", path: "/jaaroverzicht", title: "Jaaroverzicht" },
];

export const PATH_BY_TAB: Record<ActiveTab, string> = SPAREN_PAGES.reduce(
  (acc, page) => {
    acc[page.tab] = page.path;
    return acc;
  },
  {} as Record<ActiveTab, string>
);

export const TITLE_BY_TAB: Record<ActiveTab, string> = SPAREN_PAGES.reduce(
  (acc, page) => {
    acc[page.tab] = page.title;
    return acc;
  },
  {} as Record<ActiveTab, string>
);

export function tabFromPath(pathname: string): ActiveTab {
  const normalized = pathname.replace(/\/+$/, "") || "/";
  if (normalized === "/") {
    return "maandbegroting";
  }

  return SPAREN_PAGES.find((page) => page.path === normalized)?.tab ?? "maandbegroting";
}

export function pathForTab(tab: ActiveTab): string {
  return PATH_BY_TAB[tab];
}

function applyDocumentTitle(tab: ActiveTab) {
  document.title = `${TITLE_BY_TAB[tab]} · Financiën`;
}

export function useSparenRoute(): [ActiveTab, (tab: ActiveTab) => void] {
  const [tab, setTab] = useState<ActiveTab>(() => tabFromPath(window.location.pathname));

  useEffect(() => {
    applyDocumentTitle(tab);
  }, [tab]);

  useEffect(() => {
    const onPopState = () => {
      const next = tabFromPath(window.location.pathname);
      setTab(next);
      applyDocumentTitle(next);
    };

    window.addEventListener("popstate", onPopState);
    return () => window.removeEventListener("popstate", onPopState);
  }, []);

  const navigate = useCallback((next: ActiveTab) => {
    const path = pathForTab(next);
    if (window.location.pathname !== path) {
      window.history.pushState({ tab: next }, "", path);
    }
    setTab(next);
    applyDocumentTitle(next);
  }, []);

  return [tab, navigate];
}

export function handleSparenNavClick(
  event: MouseEvent<HTMLAnchorElement>,
  tab: ActiveTab,
  navigate: (tab: ActiveTab) => void
) {
  if (event.defaultPrevented || event.button !== 0) {
    return;
  }
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return;
  }

  event.preventDefault();
  navigate(tab);
}
