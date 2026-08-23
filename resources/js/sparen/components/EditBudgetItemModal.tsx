import React, { useState, useEffect, useMemo } from "react";
import { X, Check, Repeat, Trash2, Plus } from "lucide-react";
import {
  BudgetItem,
  BudgetCategoryGroup,
  MonthlyBudget,
  CategoryDefinition,
  BudgetType,
  BudgetMonthEntry,
} from "../types";
import { ConfirmDialog } from "./ConfirmDialog";

interface EditBudgetItemModalProps {
  isOpen: boolean;
  onClose: () => void;
  item: BudgetItem | null;
  currentMonthId: string;
  allMonths: MonthlyBudget[];
  categories?: CategoryDefinition[];
  onSave: (
    itemId: string,
    updatedData: {
      name: string;
      group: BudgetCategoryGroup;
      type?: BudgetType;
      monthlyAmounts: Record<string, number>;
      monthlyEntries?: Record<string, BudgetMonthEntry[]>;
    }
  ) => void;
  onDelete?: (itemId: string) => void | Promise<void>;
}

function isOpenstaandItem(item: BudgetItem | null): boolean {
  if (!item) return false;
  return item.name.trim().toLowerCase() === "openstaand" || item.id === "ovk-4";
}

function newEntry(): BudgetMonthEntry {
  return {
    id: `entry-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    description: "",
    amount: 0,
  };
}

function sumEntries(entries: BudgetMonthEntry[]): number {
  return Math.round(entries.reduce((s, e) => s + (Number(e.amount) || 0), 0) * 100) / 100;
}

export const EditBudgetItemModal: React.FC<EditBudgetItemModalProps> = ({
  isOpen,
  onClose,
  item,
  currentMonthId,
  allMonths,
  categories,
  onSave,
  onDelete,
}) => {
  const openstaand = isOpenstaandItem(item);
  const [name, setName] = useState("");
  const [group, setGroup] = useState<BudgetCategoryGroup>("Dagelijks Leven");
  const [currentAmount, setCurrentAmount] = useState<number>(0);
  const [frequencyMode, setFrequencyMode] = useState<"current_only" | "all_months" | "quarterly" | "custom">(
    "current_only"
  );
  const [monthlyValues, setMonthlyValues] = useState<Record<string, number>>({});
  const [monthlyEntries, setMonthlyEntries] = useState<Record<string, BudgetMonthEntry[]>>({});
  const [activeEntryMonth, setActiveEntryMonth] = useState(currentMonthId);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    if (item && isOpen) {
      setName(item.name);
      setGroup(item.group);
      setCurrentAmount(item.actual);
      setActiveEntryMonth(currentMonthId);

      const initialMap: Record<string, number> = {};
      const initialEntries: Record<string, BudgetMonthEntry[]> = {};

      allMonths.forEach((m) => {
        const found = m.items.find((i) => i.id === item.id);
        initialMap[m.monthId] = found ? found.actual : m.monthId === currentMonthId ? item.actual : 0;
        const entries = found?.monthEntries?.length
          ? found.monthEntries.map((e) => ({ ...e }))
          : found && found.actual > 0 && isOpenstaandItem(item)
            ? [{ id: newEntry().id, description: found.name || "Openstaand", amount: found.actual }]
            : [];
        initialEntries[m.monthId] = entries;
        if (entries.length > 0) {
          initialMap[m.monthId] = sumEntries(entries);
        }
      });

      setMonthlyValues(initialMap);
      setMonthlyEntries(initialEntries);

      if (openstaand) {
        setFrequencyMode("custom");
      } else {
        const nonZeroMonths = Object.entries(initialMap).filter(([_, v]) => v > 0);
        const isQuarterly =
          nonZeroMonths.length === 4 && ["jan", "apr", "jul", "okt"].every((m) => (initialMap[m] || 0) > 0);
        setFrequencyMode(isQuarterly ? "quarterly" : "current_only");
      }

      setConfirmDelete(false);
      setDeleting(false);
    }
  }, [item, isOpen, currentMonthId, allMonths, openstaand]);

  const activeEntries = monthlyEntries[activeEntryMonth] || [];
  const activeEntriesTotal = useMemo(() => sumEntries(activeEntries), [activeEntries]);

  if (!isOpen || !item) return null;

  const syncAmountFromEntries = (monthId: string, entries: BudgetMonthEntry[]) => {
    const total = sumEntries(entries);
    setMonthlyValues((prev) => ({ ...prev, [monthId]: total }));
    if (monthId === currentMonthId) {
      setCurrentAmount(total);
    }
  };

  const updateActiveEntries = (next: BudgetMonthEntry[]) => {
    setMonthlyEntries((prev) => ({ ...prev, [activeEntryMonth]: next }));
    syncAmountFromEntries(activeEntryMonth, next);
  };

  const handleFrequencyChange = (mode: "current_only" | "all_months" | "quarterly" | "custom") => {
    setFrequencyMode(mode);
    const updated = { ...monthlyValues };

    if (mode === "all_months") {
      allMonths.forEach((m) => {
        updated[m.monthId] = currentAmount;
      });
    } else if (mode === "quarterly") {
      allMonths.forEach((m) => {
        if (["jan", "apr", "jul", "okt"].includes(m.monthId)) {
          updated[m.monthId] = currentAmount > 0 ? currentAmount : item.actual || 299.97;
        } else {
          updated[m.monthId] = 0;
        }
      });
    } else if (mode === "current_only") {
      updated[currentMonthId] = currentAmount;
    }
    setMonthlyValues(updated);
  };

  const handleCurrentAmountChange = (val: number) => {
    setCurrentAmount(val);
    const updated = { ...monthlyValues };

    if (frequencyMode === "current_only") {
      updated[currentMonthId] = val;
    } else if (frequencyMode === "all_months") {
      allMonths.forEach((m) => {
        updated[m.monthId] = val;
      });
    } else if (frequencyMode === "quarterly") {
      allMonths.forEach((m) => {
        if (["jan", "apr", "jul", "okt"].includes(m.monthId)) {
          updated[m.monthId] = val;
        } else {
          updated[m.monthId] = 0;
        }
      });
    }
    setMonthlyValues(updated);
  };

  const handleMonthAmountChange = (monthId: string, val: number) => {
    setMonthlyValues((prev) => ({
      ...prev,
      [monthId]: val,
    }));
    if (monthId === currentMonthId) {
      setCurrentAmount(val);
    }
    setFrequencyMode("custom");
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    let finalAmounts: Record<string, number> = { ...monthlyValues };
    let finalEntries: Record<string, BudgetMonthEntry[]> | undefined;

    if (openstaand) {
      finalEntries = {};
      allMonths.forEach((m) => {
        const cleaned = (monthlyEntries[m.monthId] || [])
          .map((row) => ({
            ...row,
            description: row.description.trim(),
            amount: Number(row.amount) || 0,
          }))
          .filter((row) => row.description !== "" || Math.abs(row.amount) > 0);
        finalEntries![m.monthId] = cleaned;
        finalAmounts[m.monthId] = sumEntries(cleaned);
      });
    } else if (frequencyMode === "current_only") {
      finalAmounts[currentMonthId] = currentAmount;
    } else if (frequencyMode === "all_months") {
      allMonths.forEach((m) => {
        finalAmounts[m.monthId] = currentAmount;
      });
    } else if (frequencyMode === "quarterly") {
      allMonths.forEach((m) => {
        finalAmounts[m.monthId] = ["jan", "apr", "jul", "okt"].includes(m.monthId) ? currentAmount : 0;
      });
    }

    let matchedType: BudgetType = item.type;
    const foundCat = categories?.find((c) => c.name === group);
    if (foundCat) {
      matchedType = foundCat.type;
    } else if (group === "Inkomsten") {
      matchedType = "inkomsten";
    } else if (group === "Spaargeld") {
      matchedType = "sparen";
    } else {
      matchedType = "uitgaven";
    }

    onSave(item.id, {
      name: name.trim(),
      group,
      type: matchedType,
      monthlyAmounts: finalAmounts,
      monthlyEntries: finalEntries,
    });

    onClose();
  };

  const groupOptions =
    categories && categories.length > 0
      ? categories.map((c) => c.name)
      : [
          "Inkomsten",
          "Woning",
          "Dagelijks Leven",
          "Vervoersmiddelen",
          "Verzekeringen",
          "Spaargeld",
          "Leningen",
          "Overige Vaste Kosten",
          "Overige Kosten",
        ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
      <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        <div className="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-850">
          <div>
            <h3 className="font-bold text-white text-base">
              {openstaand ? "Openstaande posten beheren" : "Begrotingspost Aanpassen"}
            </h3>
            <p className="text-xs text-slate-400">
              {openstaand
                ? "Voeg per maand losse regels toe (omschrijving + bedrag). Die tellen mee in nog te betalen en verwacht eind."
                : "Stel het budgetbedrag in en bepaal in welke maanden deze post actief is"}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-5 overflow-y-auto">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-slate-300 text-xs font-semibold mb-1">Naam Begrotingspost</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div>
              <label className="block text-slate-300 text-xs font-semibold mb-1">Categoriegroep</label>
              <select
                value={group}
                onChange={(e) => setGroup(e.target.value as BudgetCategoryGroup)}
                className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
              >
                {groupOptions.map((g) => (
                  <option key={g} value={g}>
                    {g}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {openstaand ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <label className="text-xs font-semibold text-slate-200">Regels per maand</label>
                <span className="text-[11px] font-mono text-indigo-300">
                  Totaal {allMonths.find((m) => m.monthId === activeEntryMonth)?.monthName}: €{" "}
                  {activeEntriesTotal.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}
                </span>
              </div>

              <div className="flex flex-wrap gap-1.5">
                {allMonths.map((m) => {
                  const total = sumEntries(monthlyEntries[m.monthId] || []);
                  const active = m.monthId === activeEntryMonth;
                  return (
                    <button
                      key={m.monthId}
                      type="button"
                      onClick={() => setActiveEntryMonth(m.monthId)}
                      className={`px-2.5 py-1.5 rounded-lg text-[11px] border transition-colors ${
                        active
                          ? "bg-indigo-600/30 border-indigo-500 text-white font-semibold"
                          : total > 0
                            ? "bg-slate-800 border-slate-600 text-slate-200"
                            : "bg-slate-900 border-slate-800 text-slate-500"
                      }`}
                    >
                      {m.monthName.slice(0, 3)}
                      {total > 0 ? ` · €${Math.round(total)}` : ""}
                    </button>
                  );
                })}
              </div>

              <div className="bg-slate-950/60 border border-slate-800 rounded-xl p-3 space-y-2">
                {activeEntries.length === 0 && (
                  <p className="text-[11px] text-slate-500 px-1 py-2">
                    Nog geen regels. Voeg bijv. “Coolblue restant” of “Tandarts” toe.
                  </p>
                )}

                {activeEntries.map((row, index) => (
                  <div key={row.id} className="grid grid-cols-12 gap-2 items-center">
                    <input
                      type="text"
                      value={row.description}
                      placeholder="Omschrijving"
                      onChange={(e) => {
                        const next = activeEntries.map((entry, i) =>
                          i === index ? { ...entry, description: e.target.value } : entry
                        );
                        updateActiveEntries(next);
                      }}
                      className="col-span-7 bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"
                    />
                    <div className="col-span-4 relative">
                      <span className="absolute left-2 top-1/2 -translate-y-1/2 text-slate-500 text-[11px]">€</span>
                      <input
                        type="number"
                        step="0.01"
                        value={row.amount}
                        onChange={(e) => {
                          const next = activeEntries.map((entry, i) =>
                            i === index ? { ...entry, amount: parseFloat(e.target.value) || 0 } : entry
                          );
                          updateActiveEntries(next);
                        }}
                        className="w-full bg-slate-800 border border-slate-700 rounded-lg pl-5 pr-2 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                      />
                    </div>
                    <button
                      type="button"
                      onClick={() => updateActiveEntries(activeEntries.filter((_, i) => i !== index))}
                      className="col-span-1 p-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 rounded-lg"
                      title="Regel verwijderen"
                    >
                      <Trash2 className="w-3.5 h-3.5 mx-auto" />
                    </button>
                  </div>
                ))}

                <button
                  type="button"
                  onClick={() => updateActiveEntries([...activeEntries, newEntry()])}
                  className="w-full mt-1 flex items-center justify-center gap-1.5 border border-dashed border-slate-700 hover:border-indigo-500/60 text-slate-300 hover:text-white rounded-xl py-2 text-xs transition-colors"
                >
                  <Plus className="w-3.5 h-3.5" />
                  Regel toevoegen
                </button>
              </div>

              <p className="text-[11px] text-slate-500">
                Na opslaan koppel je bankmutaties handmatig aan de post Openstaand (via de post openen). Het
                verschil tussen begroot (som van regels) en gekoppelde betalingen blijft in “nog te betalen” /
                verwacht eind.
              </p>
            </div>
          ) : (
            <>
              <div className="bg-slate-950/60 p-4 rounded-xl border border-slate-800 space-y-3">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-200">Budget Bedrag (€)</label>
                  <span className="text-[11px] text-indigo-400 font-mono">Live gekoppeld aan bank</span>
                </div>
                <div className="relative">
                  <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-mono text-sm">€</span>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={currentAmount}
                    onChange={(e) => handleCurrentAmountChange(parseFloat(e.target.value) || 0)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl pl-8 pr-4 py-2 text-white font-mono text-base font-bold focus:outline-none focus:border-indigo-500"
                    placeholder="0.00"
                  />
                </div>
              </div>

              <div className="space-y-2.5">
                <label className="block text-slate-300 text-xs font-semibold flex items-center gap-1.5">
                  <Repeat className="w-3.5 h-3.5 text-indigo-400" />
                  <span>Uitkeringsschema & Frequentie</span>
                </label>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  {(
                    [
                      ["current_only", "Alleen deze maand", "Enkele aanpassing"],
                      ["all_months", "Elke maand", "12x per jaar hetzelfde"],
                      ["quarterly", "Per kwartaal (4x)", "Jan, Apr, Jul, Okt (Kinderbijslag)"],
                      ["custom", "Handmatig / Maatwerk", "Per maand instellen"],
                    ] as const
                  ).map(([mode, title, subtitle]) => (
                    <button
                      key={mode}
                      type="button"
                      onClick={() => handleFrequencyChange(mode)}
                      className={`p-2.5 rounded-xl border text-left text-xs transition-all ${
                        frequencyMode === mode
                          ? "bg-indigo-600/20 border-indigo-500 text-white font-semibold"
                          : "bg-slate-800/60 border-slate-700/60 text-slate-300 hover:bg-slate-800"
                      }`}
                    >
                      <span className="block font-medium">{title}</span>
                      <span className="text-[10px] text-slate-400">{subtitle}</span>
                    </button>
                  ))}
                </div>
              </div>

              <div className="bg-slate-950/40 p-3.5 rounded-xl border border-slate-800/80">
                <div className="flex items-center justify-between mb-2.5">
                  <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    Begroot bedrag over 2026 per maand
                  </span>
                  <span className="text-[11px] text-slate-500">
                    In maanden met € 0,00 staat de post niet op &quot;In afwachting&quot;
                  </span>
                </div>

                <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 font-mono">
                  {allMonths.map((m) => {
                    const val = monthlyValues[m.monthId] || 0;
                    const isCurrent = m.monthId === currentMonthId;
                    const isZero = val === 0;

                    return (
                      <div
                        key={m.monthId}
                        className={`p-2 rounded-lg border text-center transition-all ${
                          isCurrent ? "bg-indigo-950/40 border-indigo-500/50" : "bg-slate-900 border-slate-800"
                        }`}
                      >
                        <span className="block text-[10px] font-sans font-semibold text-slate-400 mb-1">
                          {m.monthName.slice(0, 3)} {isCurrent && "★"}
                        </span>
                        <input
                          type="number"
                          step="0.01"
                          value={val}
                          onChange={(e) => handleMonthAmountChange(m.monthId, parseFloat(e.target.value) || 0)}
                          className={`w-full bg-slate-800 border rounded text-center text-xs py-1 px-1 focus:outline-none focus:border-indigo-500 ${
                            isZero ? "text-slate-500 border-slate-700/40" : "text-white font-bold border-slate-700"
                          }`}
                        />
                      </div>
                    );
                  })}
                </div>
              </div>
            </>
          )}

          <div className="pt-3 border-t border-slate-800 flex items-center justify-between">
            {onDelete ? (
              <button
                type="button"
                onClick={() => setConfirmDelete(true)}
                className="flex items-center gap-1.5 text-xs text-rose-400 hover:text-rose-300 p-2 rounded-lg hover:bg-rose-500/10 transition-colors"
              >
                <Trash2 className="w-3.5 h-3.5" />
                <span>Post Verwijderen</span>
              </button>
            ) : (
              <div />
            )}

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 rounded-xl text-xs font-medium text-slate-300 hover:bg-slate-800 transition-colors"
              >
                Annuleren
              </button>
              <button
                type="submit"
                className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
              >
                <Check className="w-4 h-4" />
                <span>Wijzigingen Opslaan</span>
              </button>
            </div>
          </div>
        </form>
      </div>

      <ConfirmDialog
        isOpen={confirmDelete}
        title={`“${name || item.name}” verwijderen?`}
        description="Deze begrotingspost verdwijnt uit alle maanden. Gekoppelde mutaties blijven staan, maar raken deze post kwijt."
        confirmLabel="Ja, verwijderen"
        busy={deleting}
        onCancel={() => {
          if (!deleting) {
            setConfirmDelete(false);
          }
        }}
        onConfirm={() => {
          if (!onDelete || deleting) {
            return;
          }
          setDeleting(true);
          Promise.resolve(onDelete(item.id))
            .then(() => {
              setConfirmDelete(false);
              onClose();
            })
            .catch(() => {
              setDeleting(false);
            });
        }}
      />
    </div>
  );
};
