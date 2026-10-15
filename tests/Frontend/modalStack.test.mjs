import assert from "node:assert/strict";
import { test } from "node:test";
import { buildSync } from "esbuild";

const bundled = buildSync({
  entryPoints: [new URL("../../resources/js/sparen/composables/modalStack.ts", import.meta.url).pathname],
  bundle: true,
  write: false,
  platform: "node",
  format: "esm",
});
const { createModalStack } = await import(
  `data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString("base64")}`
);

function openModal(stack, onClose = () => {}, canDismiss = () => true) {
  const id = Symbol("modal");
  stack.register({ id, onClose, canDismiss });
  return id;
}

function escapeEvent(overrides = {}) {
  return {
    key: "Escape",
    repeat: false,
    prevented: false,
    stopped: false,
    preventDefault() { this.prevented = true; },
    stopImmediatePropagation() { this.stopped = true; },
    ...overrides,
  };
}

test("opening transaction details preserves the parent and only the latest modal can close", () => {
  const stack = createModalStack();
  const closed = [];
  let visible = [];
  stack.subscribe((ids) => { visible = ids; });
  const parent = openModal(stack, () => closed.push("expenses"));
  const transactions = openModal(stack, () => closed.push("transactions"));
  const detail = openModal(stack, () => closed.push("detail"));

  assert.deepEqual(visible, [parent, transactions, detail]);
  assert.equal(stack.requestClose(parent), false);
  assert.equal(stack.requestClose(transactions), false);
  assert.equal(stack.requestClose(detail), true);
  assert.deepEqual(closed, ["detail"]);

  stack.unregister(detail);

  assert.deepEqual(visible, [parent, transactions]);
  assert.equal(stack.isTop(transactions), true);
});

test("unmounting an earlier modal leaves the newest modal active", () => {
  const stack = createModalStack();
  const parent = openModal(stack);
  const child = openModal(stack);
  const detail = openModal(stack);

  stack.unregister(child);
  stack.unregister(child);
  stack.unregister(parent);

  assert.equal(stack.isTop(detail), true);
  assert.equal(stack.size, 1);
});

test("one Escape closes only the transaction detail even when it immediately unmounts", () => {
  const stack = createModalStack();
  const closed = [];
  const parent = openModal(stack, () => closed.push("parent"));
  const detail = openModal(stack, () => {
    closed.push("detail");
    stack.unregister(detail);
  });
  const event = escapeEvent();

  stack.handleEscape(event);

  assert.deepEqual(closed, ["detail"]);
  assert.equal(event.prevented, true);
  assert.equal(event.stopped, true);
  assert.equal(stack.isTop(parent), true);
});

test("holding Escape never dismisses the newly revealed parent", () => {
  const stack = createModalStack();
  const closed = [];
  openModal(stack, () => closed.push("parent"));
  const child = openModal(stack, () => {
    closed.push("child");
    stack.unregister(child);
  });

  stack.handleEscape(escapeEvent());
  stack.handleEscape(escapeEvent({ repeat: true }));
  stack.handleEscape(escapeEvent({ repeat: true }));

  assert.deepEqual(closed, ["child"]);
});

test("a saving modal blocks dismissal and protects its parent until saving finishes", () => {
  const stack = createModalStack();
  const closed = [];
  let saving = true;
  openModal(stack, () => closed.push("parent"));
  const savingModal = openModal(stack, () => closed.push("saving"), () => !saving);
  const event = escapeEvent();

  stack.handleEscape(event);
  assert.equal(stack.requestClose(savingModal), false);
  assert.deepEqual(closed, []);
  assert.equal(event.stopped, true);

  saving = false;
  stack.handleEscape(escapeEvent());

  assert.deepEqual(closed, ["saving"]);
});

test("repeated close events before unmount invoke the close callback only once", async () => {
  const stack = createModalStack();
  const closed = [];
  const modal = openModal(stack, () => closed.push("modal"));

  stack.requestClose(modal);
  stack.requestClose(modal);
  stack.handleEscape(escapeEvent());

  assert.deepEqual(closed, ["modal"]);
  await Promise.resolve();
  stack.unregister(modal);
});

test("regular keys and Escape without an open modal keep their default behavior", () => {
  const stack = createModalStack();
  const closed = [];
  const withoutModal = escapeEvent();
  stack.handleEscape(withoutModal);
  openModal(stack, () => closed.push("modal"));
  const enter = escapeEvent({ key: "Enter" });

  stack.handleEscape(enter);

  assert.deepEqual(closed, []);
  assert.equal(withoutModal.prevented, false);
  assert.equal(withoutModal.stopped, false);
  assert.equal(enter.prevented, false);
  assert.equal(enter.stopped, false);
});
