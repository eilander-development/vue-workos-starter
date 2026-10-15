type ModalRegistration = {
  id: symbol;
  onClose: () => void;
  canDismiss: () => boolean;
};

type EscapeEvent = Pick<KeyboardEvent, "key" | "repeat" | "preventDefault" | "stopImmediatePropagation">;

export function createModalStack() {
  const entries: ModalRegistration[] = [];
  const subscribers = new Set<(ids: readonly symbol[]) => void>();
  const closing = new Set<symbol>();

  function notify(): void {
    const ids = entries.map((entry) => entry.id);
    subscribers.forEach((subscriber) => subscriber(ids));
  }

  function isTop(id: symbol): boolean {
    return entries.at(-1)?.id === id;
  }

  function requestClose(id: symbol): boolean {
    const entry = entries.at(-1);
    if (!entry || entry.id !== id || !entry.canDismiss() || closing.has(id)) {
      return false;
    }

    closing.add(id);
    try {
      entry.onClose();
    } finally {
      queueMicrotask(() => closing.delete(id));
    }
    return true;
  }

  return {
    get size(): number {
      return entries.length;
    },
    isTop,
    requestClose,
    register(entry: ModalRegistration): void {
      entries.push(entry);
      notify();
    },
    unregister(id: symbol): void {
      const index = entries.findIndex((entry) => entry.id === id);
      if (index === -1) {
        return;
      }
      entries.splice(index, 1);
      closing.delete(id);
      notify();
    },
    subscribe(subscriber: (ids: readonly symbol[]) => void): () => void {
      subscribers.add(subscriber);
      subscriber(entries.map((entry) => entry.id));
      return () => subscribers.delete(subscriber);
    },
    handleEscape(event: EscapeEvent): void {
      const entry = entries.at(-1);
      if (!entry || event.key !== "Escape") {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();
      if (!event.repeat) {
        requestClose(entry.id);
      }
    },
  };
}
