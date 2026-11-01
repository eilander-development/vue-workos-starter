import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildSync } from 'esbuild';

const bundled = buildSync({ entryPoints: [new URL('../../resources/js/sparen/bankReviews.ts', import.meta.url).pathname], bundle: true, write: false, platform: 'node', format: 'esm' });
const { bankReviewSplitPreview } = await import(`data:text/javascript;base64,${Buffer.from(bundled.outputFiles[0].text).toString('base64')}`);
const split = [{ budgetItemId: 'a', amount: 100 }, { budgetItemId: 'b', amount: 50 }];
const review = (values = {}) => ({ amount: -180.01, existing: [{ id: 'pending', allocations: split }, { id: 'booked' }], ...values });

test('bank correction preview preserves cents and matches the server split rescaling', () => {
  const result = bankReviewSplitPreview(review(), 'booked', 'merge');
  assert.equal(result.source.id, 'pending');
  assert.equal(result.needsScaling, true);
  assert.equal(result.valid, true);
  assert.deepEqual(result.before, split);
  assert.deepEqual(result.after, [{ budgetItemId: 'a', amount: 120.01 }, { budgetItemId: 'b', amount: 60 }]);
});

test('explicit source selection does not silently transfer another registrations split', () => {
  const result = bankReviewSplitPreview(review(), 'booked', 'booked');
  assert.deepEqual(result.after, []);
  assert.equal(result.needsScaling, false);
  assert.equal(result.valid, true);
});

test('merge prefers the keeper split and does not require rescaling an equal total', () => {
  const result = bankReviewSplitPreview(review({ amount: -150, existing: [{ id: 'keeper', allocations: split }, { id: 'other', allocations: [{ budgetItemId: 'x', amount: 50 }, { budgetItemId: 'y', amount: 100 }] }] }), 'keeper', 'merge');
  assert.equal(result.source.id, 'keeper');
  assert.deepEqual(result.after, split);
  assert.equal(result.needsScaling, false);
});

test('a bank amount too small for positive split parts is marked invalid before approval', () => {
  const result = bankReviewSplitPreview(review({ amount: -0.01 }), 'pending', 'pending');
  assert.equal(result.valid, false);
  assert.equal(result.needsScaling, true);
});
