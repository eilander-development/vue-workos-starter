export interface SyncIssue {
  code: "conflict" | "missing_identity" | "invalid" | "pending" | "pending_booking";
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
  /** Subset of pending: bank has finalized a previously stored provisional entry. */
  pending_booking?: number;
  syncedAt?: string;
  issues?: SyncIssue[];
}

export function isPendingSyncIssue(issue: Pick<SyncIssue, "code">): boolean {
  return issue.code === "pending" || issue.code === "pending_booking";
}

export function pendingSyncSummary(report: SyncReport): string {
  const toProcess = report.pending_booking ?? 0;
  const waiting = Math.max(0, (report.pending ?? 0) - toProcess);
  return [
    waiting ? `${waiting} wachten op definitieve boeking bij de bank` : "",
    toProcess ? `${toProcess} definitieve boekingen vragen verwerking van een oude voorlopige registratie` : "",
  ].filter(Boolean).join(" · ");
}
