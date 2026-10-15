import { computed, nextTick, onMounted, onUnmounted, shallowRef, type Ref } from "vue";
import { createModalStack } from "./modalStack";

const stack = createModalStack();
const modalIds = shallowRef<readonly symbol[]>([]);
const focusContexts = new Map<symbol, {
  overlay: Ref<HTMLElement | null>;
  returnTargets: HTMLElement[];
  focusPanel: () => void;
  lastFocusedElement: HTMLElement | null;
}>();
let originalBodyOverflow = "";

stack.subscribe((ids) => {
  modalIds.value = ids;
});

export function useModalStack(
  overlay: Ref<HTMLElement | null>,
  onClose: () => void,
  canDismiss: () => boolean,
) {
  const id = Symbol("modal");
  const isTop = computed(() => modalIds.value.at(-1) === id);
  const zIndex = computed(() => 100 + Math.max(0, modalIds.value.indexOf(id)) * 10);
  let returnTargets: HTMLElement[] = [];

  function focusableElements(): HTMLElement[] {
    if (!overlay.value) {
      return [];
    }

    return [...overlay.value.querySelectorAll<HTMLElement>(
      "a[href], button, input, select, textarea, [tabindex]",
    )].filter((element) =>
      element.tabIndex >= 0 && !element.matches(":disabled") &&
      !element.closest("[inert]") && element.getClientRects().length > 0,
    );
  }

  function focusPanel(): void {
    if (!isTop.value || !overlay.value) {
      return;
    }
    const elements = focusableElements();
    const autofocus = elements.find((element) => element.hasAttribute("autofocus"));
    (autofocus ?? elements[0] ?? overlay.value).focus({ preventScroll: true });
  }

  function keepFocusInside(event: FocusEvent): void {
    if (!isTop.value || !overlay.value) {
      return;
    }
    if (!overlay.value.contains(event.target as Node)) {
      focusPanel();
    } else if (event.target instanceof HTMLElement) {
      const context = focusContexts.get(id);
      if (context) {
        context.lastFocusedElement = event.target;
      }
    }
  }

  function trapFocus(event: KeyboardEvent): void {
    if (event.key !== "Tab" || !isTop.value) {
      return;
    }

    const elements = focusableElements();
    const first = elements[0];
    const last = elements.at(-1);
    const activeIndex = elements.indexOf(document.activeElement as HTMLElement);

    if (!first || !last) {
      event.preventDefault();
      overlay.value?.focus({ preventScroll: true });
    } else if (event.shiftKey && activeIndex <= 0) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (activeIndex === -1 || activeIndex === elements.length - 1)) {
      event.preventDefault();
      first.focus();
    }
  }

  onMounted(() => {
    const parentId = modalIds.value.at(-1);
    const parentContext = parentId ? focusContexts.get(parentId) : undefined;
    const parentTargets = parentContext?.returnTargets ?? [];
    const activeElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const previousFocus = parentContext && !parentContext.overlay.value?.contains(activeElement)
      ? parentContext.lastFocusedElement ?? activeElement
      : activeElement;
    returnTargets = previousFocus
      ? [previousFocus, ...parentTargets]
      : [...parentTargets];
    focusContexts.set(id, { overlay, returnTargets, focusPanel, lastFocusedElement: null });
    if (stack.size === 0) {
      originalBodyOverflow = document.body.style.overflow;
      document.body.style.overflow = "hidden";
      document.addEventListener("keydown", stack.handleEscape, true);
    }

    stack.register({ id, onClose, canDismiss });
    document.addEventListener("keydown", trapFocus, true);
    document.addEventListener("focusin", keepFocusInside, true);
    nextTick(focusPanel);
  });

  onUnmounted(() => {
    const wasTop = stack.isTop(id);
    focusContexts.delete(id);
    stack.unregister(id);
    document.removeEventListener("keydown", trapFocus, true);
    document.removeEventListener("focusin", keepFocusInside, true);
    if (stack.size === 0) {
      document.body.style.overflow = originalBodyOverflow;
      document.removeEventListener("keydown", stack.handleEscape, true);
    }

    if (wasTop) {
      nextTick(() => {
        const topId = modalIds.value.at(-1);
        const activeContext = topId ? focusContexts.get(topId) : undefined;
        const target = returnTargets.find((element) =>
          element.isConnected && !element.closest("[inert]") &&
          (!activeContext || activeContext.overlay.value?.contains(element)),
        );

        if (target) {
          target.focus({ preventScroll: true });
        } else {
          activeContext?.focusPanel();
        }
      });
    }
  });

  return { isTop, zIndex, requestClose: () => stack.requestClose(id) };
}
