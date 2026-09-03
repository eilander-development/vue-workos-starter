export type BudgetCategoryGroup =
  | "Inkomsten"
  | "Woning"
  | "Dagelijks Leven"
  | "Vervoersmiddelen"
  | "Verzekeringen"
  | "Spaargeld"
  | "Leningen"
  | "Overige Vaste Kosten"
  | "Overige Kosten"
  | string;

export type BudgetType = "inkomsten" | "uitgaven" | "sparen";

export interface CategoryDefinition {
  id: string;
  name: string;
  type: BudgetType;
  icon?: string;
  color?: string;
  description?: string;
  isDefault?: boolean;
}

export interface BudgetMonthEntry {
  id: string;
  description: string;
  amount: number;
}

export interface BudgetItem {
  id: string;
  name: string;
  group: BudgetCategoryGroup;
  type: BudgetType;
  estimated: number; // Geschat
  actual: number; // Werkelijk
  paidOrReceived: number; // Betaald of Ontvangen
  /** Bankuitgaven op een potje: tellen niet mee in begroting, wel voor verrekening */
  shadowSpent?: number;
  paymentCount?: number; // Aantal transacties / betalingen
  notes?: string;
  icon?: string;
  isPaid?: boolean;
  /** Losse regels per maand (som = begroot bedrag) */
  monthEntries?: BudgetMonthEntry[];
}

export interface MonthlyBudget {
  monthId: string; // e.g. "jan", "feb", "mrt", etc.
  monthName: string; // "Januari", "Februari", etc.
  year: number;
  periodStart?: string; // YYYY-MM-DD
  periodEnd?: string; // YYYY-MM-DD
  opRekening: number; // Saldo op rekening aan begin/eind van de maand
  /** Vastgelegd eindsaldo na sluiting van de periode (14e). */
  endBalance?: number | null;
  endBalanceCaptured?: boolean;
  items: BudgetItem[];
}

export interface ReportingSettings {
  startDayOfMonth: number;
  defaultMonthId: string;
  defaultYear: number;
}

export interface Transaction {
  id: string;
  date: string; // YYYY-MM-DD
  time?: string;
  description: string;
  amount: number; // negative for expense, positive for income
  type: "Inkomsten" | "Uitgave" | "Sparen";
  categoryGroup: BudgetCategoryGroup | "Ongecategoriseerd";
  budgetItemId?: string;
  accountIban: string;
  counterparty?: string;
  isPending?: boolean;
  matchedRuleId?: string;
  source: "EnableBanking" | "Handmatig" | "CSV-import";
  /** Automatisch of handmatig: nooit koppelen aan begrotingspost */
  linkExcluded?: boolean;
  linkExclusionReason?: string;
  /** Handmatige toewijzing aan spaardoel/potje; wint van omschrijving. */
  assignedSavingsGoalId?: string | null;
}

export interface Rule {
  id: string;
  name: string;
  keyword: string; // e.g. "GreenChoice", "Albert Heijn", "Aegon"
  matchField: "description" | "counterparty" | "both";
  targetGroup: BudgetCategoryGroup;
  targetBudgetItemId?: string;
  targetType: BudgetType;
  isActive: boolean;
  matchedCount: number;
}

export interface BankAccount {
  id: string;
  name: string;
  bankName: string;
  iban: string;
  type: "checking" | "savings";
  balance: number;
  availableBalance: number;
  currency: string;
  lastSync: string;
  lastSyncedAt?: string;
  status: "connected" | "syncing" | "disconnected" | "error";
  syncCountToday: number;
  consentValidUntil?: string | null;
  consentDaysRemaining?: number | null;
  consentExpired?: boolean;
}

export interface EnableBankingConsent {
  validUntil: string | null;
  daysRemaining: number | null;
  expired: boolean;
  aspspName?: string | null;
}

export interface SavingsRow {
  month: string;
  monthId: string;
  opRekening: number;
  sparen: number;
  extra: number;
  opgenomen: number;
  totaal: number;
}

export type SavingsGoalKind = "goal" | "pot";

export interface SavingsGoal {
  id: string;
  name: string;
  accountIban: string;
  bankName: string;
  targetAmount: number;
  initialAmount: number;
  monthlyContribution: number;
  color: string;
  iconName: string;
  notes?: string;
  /** @deprecated Prefer categoryBudgetItemIds; kept as first selected id for older payloads */
  categoryBudgetItemId?: string;
  /** Gekoppelde uitgavenrubrieken (potjes kunnen er meerdere hebben) */
  categoryBudgetItemIds?: string[];
  /** goal = spaardoel; pot = verrekenpotje gekoppeld aan een uitgavenrubriek */
  kind?: SavingsGoalKind;
}

export interface CategoryOverviewSummary {
  group: BudgetCategoryGroup;
  type: BudgetType;
  totalEstimated: number;
  totalActual: number;
  totalPaid: number;
  totalRemaining: number;
  difference: number;
  itemCount: number;
  items: BudgetItem[];
}

export type ActiveTab =
  | "dashboard"
  | "maandbegroting"
  | "uitgaven"
  | "inkomsten"
  | "sparen"
  | "transacties"
  | "enablebanking"
  | "categorieen"
  | "koppelregels"
  | "jaaroverzicht"
  | "settings";

export type SaveState = "idle" | "saving" | "saved" | "error";
