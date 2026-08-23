import React, { useCallback, useEffect, useRef, useState } from "react";
import { AlertCircle, CheckCircle2, Info, X } from "lucide-react";

export type ToastVariant = "success" | "error" | "info";

export interface ToastMessage {
  id: number;
  variant: ToastVariant;
  title: string;
  description?: string;
}

export type Notify = (variant: ToastVariant, title: string, description?: string) => void;

const MAX_VISIBLE = 4;
const DURATION: Record<ToastVariant, number> = {
  success: 4500,
  info: 5000,
  error: 9000,
};

export function useToasts() {
  const [toasts, setToasts] = useState<ToastMessage[]>([]);
  const timers = useRef<number[]>([]);
  const counter = useRef(0);

  const dismiss = useCallback((id: number) => {
    setToasts((prev) => prev.filter((toast) => toast.id !== id));
  }, []);

  const notify = useCallback<Notify>(
    (variant, title, description) => {
      counter.current += 1;
      const id = counter.current;

      setToasts((prev) => [...prev, { id, variant, title, description }].slice(-MAX_VISIBLE));
      timers.current.push(window.setTimeout(() => dismiss(id), DURATION[variant]));
    },
    [dismiss]
  );

  useEffect(() => () => timers.current.forEach((timer) => window.clearTimeout(timer)), []);

  return { toasts, notify, dismiss };
}

const VARIANT_STYLES: Record<ToastVariant, { border: string; icon: string; Icon: typeof CheckCircle2 }> = {
  success: { border: "border-emerald-600/60", icon: "text-emerald-400", Icon: CheckCircle2 },
  error: { border: "border-rose-600/60", icon: "text-rose-400", Icon: AlertCircle },
  info: { border: "border-indigo-500/60", icon: "text-indigo-400", Icon: Info },
};

interface ToastStackProps {
  toasts: ToastMessage[];
  onDismiss: (id: number) => void;
}

export const ToastStack: React.FC<ToastStackProps> = ({ toasts, onDismiss }) => {
  if (toasts.length === 0) return null;

  return (
    <div
      id="toast-stack"
      role="status"
      aria-live="polite"
      className="fixed z-[60] top-16 sm:top-20 right-3 sm:right-5 left-3 sm:left-auto flex flex-col gap-2 sm:w-80 pointer-events-none"
    >
      {toasts.map((toast) => {
        const style = VARIANT_STYLES[toast.variant];

        return (
          <div
            key={toast.id}
            className={`pointer-events-auto bg-slate-900/95 backdrop-blur-sm border ${style.border} rounded-xl shadow-xl shadow-black/40 p-3 flex items-start gap-2.5 animate-in fade-in slide-in-from-bottom-2`}
          >
            <style.Icon className={`w-4 h-4 shrink-0 mt-0.5 ${style.icon}`} />
            <div className="min-w-0 flex-1">
              <p className="text-xs font-semibold text-white break-words">{toast.title}</p>
              {toast.description && (
                <p className="text-[11px] text-slate-400 mt-0.5 break-words">{toast.description}</p>
              )}
            </div>
            <button
              onClick={() => onDismiss(toast.id)}
              className="text-slate-500 hover:text-white p-1 -m-1 rounded-lg hover:bg-slate-800 transition-colors shrink-0"
              title="Melding sluiten"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          </div>
        );
      })}
    </div>
  );
};
