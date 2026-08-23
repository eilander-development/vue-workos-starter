export type SparenUser = {
  name: string;
  email: string;
  avatar?: string | null;
};

declare global {
  interface Window {
    __SPAREN__?: {
      user?: SparenUser | null;
    };
  }
}

export function getSparenUser(): SparenUser | null {
  return window.__SPAREN__?.user ?? null;
}

export function userInitials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

export function formatIbanDisplay(iban?: string | null): string {
  const compact = (iban ?? "").replace(/\s+/g, "").toUpperCase();
  if (!compact) return "";
  return compact.replace(/(.{4})/g, "$1 ").trim();
}

function csrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  if (meta) return meta;

  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : "";
}

export async function logoutSparen(): Promise<void> {
  const response = await fetch("/logout", {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "text/html, application/json",
      "Content-Type": "application/x-www-form-urlencoded",
      "X-Requested-With": "XMLHttpRequest",
      "X-CSRF-TOKEN": csrfToken(),
      "X-XSRF-TOKEN": csrfToken(),
    },
    body: `_token=${encodeURIComponent(csrfToken())}`,
  });

  if (response.redirected) {
    window.location.href = response.url;
    return;
  }

  window.location.href = "/";
}
