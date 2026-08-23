import { onUnmounted, ref, type Ref } from "vue";

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

export function useToasts(): {
  toasts: Ref<ToastMessage[]>;
  notify: Notify;
  dismiss: (id: number) => void;
} {
  const toasts = ref<ToastMessage[]>([]);
  const timers: number[] = [];
  let counter = 0;

  const dismiss = (id: number) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
  };

  const notify: Notify = (variant, title, description) => {
    counter += 1;
    const id = counter;

    toasts.value = [...toasts.value, { id, variant, title, description }].slice(-MAX_VISIBLE);
    timers.push(window.setTimeout(() => dismiss(id), DURATION[variant]));
  };

  onUnmounted(() => {
    timers.forEach((timer) => window.clearTimeout(timer));
  });

  return { toasts, notify, dismiss };
}
