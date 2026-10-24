import assert from "node:assert/strict";
import { test } from "node:test";
import { buildSync } from "esbuild";

const bundled = buildSync({
  entryPoints: [new URL("../../resources/js/sparen/syncReport.ts", import.meta.url).pathname],
  bundle: true, write: false, platform: "node", format: "esm",
});
const { isPendingSyncIssue, pendingSyncSummary } = await import(
  `data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString("base64")}`
);

test("both waiting payments and provisional date changes appear under not yet booked", () => {
  const issues = [{ code: "pending" }, { code: "pending_booking" }, { code: "conflict" }, { code: "missing_identity" }];
  assert.deepEqual(issues.filter(isPendingSyncIssue).map(issue => issue.code), ["pending", "pending_booking"]);
  assert.deepEqual(issues.filter(issue => !isPendingSyncIssue(issue)).map(issue => issue.code), ["conflict", "missing_identity"]);
});

test("summary separates waiting for the bank from processing a definitive booking", () => {
  const text = pendingSyncSummary({ pending: 27, pending_booking: 22 });
  assert.match(text, /5 wachten op definitieve boeking bij de bank/);
  assert.match(text, /22 definitieve boekingen vragen verwerking/);
});

test("old reports containing only pending payments still get the waiting explanation", () => {
  assert.equal(pendingSyncSummary({ pending: 5 }), "5 wachten op definitieve boeking bij de bank");
  assert.equal(pendingSyncSummary({}), "");
});
