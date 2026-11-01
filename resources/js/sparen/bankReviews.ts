import { normalizeAllocations, scaleAllocationsToAmount } from './allocations';
import type { SyncReport } from './syncReport';
import type { Transaction } from './types';

export interface BankReviewSummary {
    key: string;
    status: 'open' | 'accepted' | 'superseded';
    revision: string;
    date: string;
    amount: number;
    description: string;
    account: string;
    reference: string | null;
    seenAt: string;
    resolvedKey: string | null;
}
export interface BankReviewDetails extends BankReviewSummary {
    existing: Transaction[];
    linksCompatible: boolean;
    otherVersions: number;
}
export interface BankReviewResult {
    booked: Transaction;
    removedKeys: string[];
    report: SyncReport | null;
    reviewCount: number;
}
export function bankReviewSplitPreview(
    review: BankReviewDetails,
    keeperKey: string,
    linkSource: string,
) {
    const keeper = review.existing.find((tx) => tx.id === keeperKey);
    const sources =
        linkSource === 'merge'
            ? [
                  ...(keeper ? [keeper] : []),
                  ...review.existing.filter((tx) => tx.id !== keeperKey),
              ]
            : review.existing.filter((tx) => tx.id === linkSource);
    const source = sources.find((tx) => normalizeAllocations(tx.allocations));
    const before = normalizeAllocations(source?.allocations) ?? [];
    const after = before.length
        ? scaleAllocationsToAmount(before, review.amount)
        : [];
    return {
        before,
        after,
        valid:
            !before.length ||
            (after.length === before.length &&
                after.every((row) => row.amount > 0) &&
                after.reduce(
                    (sum, row) => sum + Math.round(row.amount * 100),
                    0,
                ) === Math.round(Math.abs(review.amount) * 100)),
        needsScaling:
            before.length > 0 &&
            before.reduce(
                (sum, row) => sum + Math.round(row.amount * 100),
                0,
            ) !== Math.round(Math.abs(review.amount) * 100),
        source,
    };
}
