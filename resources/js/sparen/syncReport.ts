export interface SyncIssue {
  code: "conflict" | "missing_identity" | "invalid" | "pending";
  reason: string;
  date: string | null;
  amount: number | null;
  description: string;
  reference: string | null;
  account: string | null;
  existing: { id: string; date: string | null; amount: number; description: string; differences: string[] }[];
}
export interface SyncReport {
  imported: number;
  duplicates: number;
  blocked?: number;
  pending?: number;
  syncedAt?: string;
  issues?: SyncIssue[];
}
