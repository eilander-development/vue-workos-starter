import React from "react";
import { X, Table2 } from "lucide-react";

export type KpiBreakdownColumn = {
  key: string;
  label: string;
  align?: "left" | "right";
  mono?: boolean;
  emphasize?: boolean;
  colorClass?: (value: number | string, row: KpiBreakdownRow) => string | undefined;
};

export type KpiBreakdownRow = {
  id: string;
  cells: Record<string, string | number>;
  onClick?: () => void;
};

export type KpiBreakdownProps = {
  isOpen: boolean;
  title: string;
  formula: string;
  subtitle?: string;
  columns: KpiBreakdownColumn[];
  rows: KpiBreakdownRow[];
  totalLabel?: string;
  totalValue: number;
  totalColorClass?: string;
  emptyMessage?: string;
  onClose: () => void;
};

function euro(value: number): string {
  return value.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCell(value: string | number): string {
  return typeof value === "number" ? `€ ${euro(value)}` : value;
}

export const KpiBreakdownModal: React.FC<KpiBreakdownProps> = ({
  isOpen,
  title,
  formula,
  subtitle,
  columns,
  rows,
  totalLabel = "Totaal",
  totalValue,
  totalColorClass = "text-white",
  emptyMessage = "Geen rijen voor dit getal.",
  onClose,
}) => {
  if (!isOpen) return null;

  return (
    <div
      id="kpi-breakdown-overlay"
      className="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/75 backdrop-blur-sm"
      onClick={onClose}
    >
      <div
        className="bg-slate-900 border border-slate-800 w-full sm:max-w-3xl sm:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3 shrink-0">
          <div className="flex items-start gap-3 min-w-0">
            <div className="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
              <Table2 className="w-4 h-4" />
            </div>
            <div className="min-w-0">
              <h3 className="font-bold text-white text-base">{title}</h3>
              <p className="text-xs text-slate-400 mt-1 leading-relaxed">{formula}</p>
              {subtitle && <p className="text-[11px] text-slate-500 mt-1">{subtitle}</p>}
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors shrink-0"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="overflow-auto flex-1">
          {rows.length === 0 ? (
            <div className="px-5 py-10 text-center text-sm text-slate-400">{emptyMessage}</div>
          ) : (
            <table className="w-full text-left text-xs">
              <thead className="sticky top-0 bg-slate-900/95 backdrop-blur border-b border-slate-800">
                <tr className="text-slate-400 font-semibold uppercase tracking-wider">
                  {columns.map((column) => (
                    <th
                      key={column.key}
                      className={`py-2.5 px-4 whitespace-nowrap ${
                        column.align === "right" ? "text-right" : "text-left"
                      }`}
                    >
                      {column.label}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/70">
                {rows.map((row) => {
                  const clickable = Boolean(row.onClick);
                  return (
                    <tr
                      key={row.id}
                      className={
                        clickable
                          ? "hover:bg-slate-800/50 cursor-pointer transition-colors"
                          : "hover:bg-slate-800/30"
                      }
                      onClick={row.onClick}
                    >
                      {columns.map((column) => {
                        const raw = row.cells[column.key];
                        const color =
                          column.colorClass?.(raw, row) ||
                          (column.emphasize ? "text-white font-semibold" : "text-slate-300");
                        return (
                          <td
                            key={column.key}
                            className={`py-2.5 px-4 ${
                              column.align === "right" ? "text-right" : "text-left"
                            } ${column.mono || typeof raw === "number" ? "font-mono" : ""} ${color}`}
                          >
                            {formatCell(raw)}
                          </td>
                        );
                      })}
                    </tr>
                  );
                })}
              </tbody>
              <tfoot className="border-t border-slate-700 bg-slate-950/80 sticky bottom-0">
                <tr>
                  <td
                    colSpan={Math.max(1, columns.length - 1)}
                    className="py-3 px-4 text-xs font-semibold text-slate-300"
                  >
                    {totalLabel} ({rows.length} {rows.length === 1 ? "rij" : "rijen"})
                  </td>
                  <td className={`py-3 px-4 text-right font-mono font-bold text-sm ${totalColorClass}`}>
                    € {euro(totalValue)}
                  </td>
                </tr>
              </tfoot>
            </table>
          )}
        </div>

        <div className="px-5 py-3 border-t border-slate-800 flex justify-end shrink-0">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium transition-colors"
          >
            Sluiten
          </button>
        </div>
      </div>
    </div>
  );
};
