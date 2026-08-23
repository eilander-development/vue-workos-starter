const params = new URLSearchParams(location.search);

const money = (n) => n.toLocaleString("nl-NL", { style: "currency", currency: "EUR" });
const pct = (a, b) => (b <= 0 ? 0 : Math.round((a / b) * 100));

const DATA = {
  monthLabel: "Augustus 2026",
  period: "15 jul – 15 aug",
  balance: 6471.2,
  income: {
    id: "inkomsten",
    name: "Inkomsten",
    type: "income",
    color: "emerald",
    page: "inkomsten",
    planned: 4865.91,
    spent: 245,
    budgets: [
      { name: "Voorlopige teruggave", planned: 559.5, spent: 0, payments: 0 },
      { name: "Kindgebonden budget", planned: 185, spent: 245, payments: 1 },
      { name: "Aangifte", planned: 0, spent: 0, payments: 0 },
      { name: "Salaris (Beekman)", planned: 3821.44, spent: 0, payments: 0 },
      { name: "Kinderbijslag", planned: 299.97, spent: 0, payments: 0 },
    ],
  },
  categories: [
    {
      id: "eten",
      name: "Eten/drinken",
      type: "expense",
      color: "red",
      page: "uitgaven",
      planned: 0,
      spent: 105.38,
      budgets: [
        { name: "Restaurants", planned: 0, spent: 0, payments: 0 },
        { name: "Boodschappen", planned: 0, spent: 105.38, payments: 9 },
      ],
    },
    {
      id: "woning",
      name: "Woning",
      type: "expense",
      color: "orange",
      page: "uitgaven",
      planned: 352.1,
      spent: 72.4,
      budgets: [
        { name: "Stroom (Greenchoice)", planned: 89, spent: 0, payments: 0 },
        { name: "Warmte (Ennatuurlijk)", planned: 142.5, spent: 0, payments: 0 },
        { name: "Water (Vitens + GBLT)", planned: 48.2, spent: 0, payments: 0 },
        { name: "Internet (KPN)", planned: 72.4, spent: 72.4, payments: 1 },
      ],
    },
    {
      id: "dagelijks",
      name: "Dagelijks leven",
      type: "expense",
      color: "rose",
      page: "uitgaven",
      planned: 1069.23,
      spent: 327.4,
      budgets: [
        { name: "Boodschappen", planned: 500, spent: 186.4 },
        { name: "Telefoon", planned: 32, spent: 32 },
        { name: "Kinderalimentatie", planned: 350, spent: 0 },
        { name: "Gemeentelijke belastingen", planned: 78.23, spent: 0 },
        { name: "Zorgverzekering", planned: 109, spent: 109 },
      ],
    },
    {
      id: "vervoer",
      name: "Vervoer",
      type: "expense",
      color: "sky",
      page: "uitgaven",
      planned: 342.58,
      spent: 41.2,
      budgets: [
        { name: "Wegenbelasting", planned: 124, spent: 0 },
        { name: "Autoverzekering", planned: 118.58, spent: 0 },
        { name: "Brandstof", planned: 100, spent: 41.2 },
      ],
    },
    {
      id: "verzekeringen",
      name: "Verzekeringen",
      type: "expense",
      color: "violet",
      page: "uitgaven",
      planned: 86.81,
      spent: 0,
      budgets: [
        { name: "Begrafenis (asr)", planned: 12.68, spent: 0 },
        { name: "Woonverzekering (InShared)", planned: 41.29, spent: 0 },
        { name: "Overlijdensrisico (NN)", planned: 32.84, spent: 0 },
      ],
    },
    {
      id: "sparen",
      name: "Sparen",
      type: "saving",
      color: "blue",
      page: "sparen",
      planned: 925,
      spent: 0,
      budgets: [
        { name: "Buffer", planned: 600, spent: 0 },
        { name: "Tuin / huis", planned: 75, spent: 0 },
        { name: "Uitjes", planned: 50, spent: 0 },
        { name: "Timmie", planned: 50, spent: 0 },
        { name: "Auto", planned: 50, spent: 0 },
        { name: "Kinderen / gezin", planned: 100, spent: 0 },
      ],
    },
    {
      id: "leningen",
      name: "Leningen",
      type: "expense",
      color: "amber",
      page: "uitgaven",
      planned: 1734,
      spent: 0,
      budgets: [
        { name: "Hypotheek (Aegon)", planned: 1248, spent: 0 },
        { name: "Persoonlijke lening (Defam)", planned: 412, spent: 0 },
        { name: "ORV bij lening", planned: 74, spent: 0 },
      ],
    },
    {
      id: "overig",
      name: "Overige vaste kosten",
      type: "expense",
      color: "slate",
      page: "uitgaven",
      planned: 81.98,
      spent: 24.99,
      budgets: [
        { name: "Loterijen", planned: 15, spent: 0 },
        { name: "Sport", planned: 24.99, spent: 24.99 },
        { name: "Zakgeld", planned: 16, spent: 0 },
        { name: "Wasmachine + droger (Coolblue)", planned: 25.99, spent: 0 },
      ],
    },
    {
      id: "rest",
      name: "Rest",
      type: "expense",
      color: "yellow",
      page: "uitgaven",
      planned: 593.06,
      spent: 0,
      budgets: [
        { name: "Zorgkosten (eigen risico)", planned: 32, spent: 0 },
        { name: "Openstaand", planned: 521.76, spent: 0 },
        { name: "Laptops kinderen school", planned: 39.3, spent: 0 },
      ],
    },
  ],
  txs: [
    { date: "18-08", desc: "PLUS 4321 APELDOORN", cat: "Dagelijks leven", budget: "Boodschappen", amount: -54.12, type: "expense" },
    { date: "17-08", desc: "AH To Go", cat: "Dagelijks leven", budget: "Boodschappen", amount: -68.4, type: "expense" },
    { date: "16-08", desc: "Jumbo", cat: "Dagelijks leven", budget: "Boodschappen", amount: -63.88, type: "expense" },
    { date: "17-08", desc: "KPN MOBILE", cat: "Dagelijks leven", budget: "Telefoon", amount: -32, type: "expense" },
    { date: "16-08", desc: "Zilveren Kruis", cat: "Dagelijks leven", budget: "Zorgverzekering", amount: -109, type: "expense" },
    { date: "15-08", desc: "KPN Glasvezel", cat: "Woning", budget: "Internet (KPN)", amount: -72.4, type: "expense" },
    { date: "14-08", desc: "Shell", cat: "Vervoer", budget: "Brandstof", amount: -41.2, type: "expense" },
    { date: "12-08", desc: "Sportcity", cat: "Overige vaste kosten", budget: "Sport", amount: -24.99, type: "expense" },
    { date: "11-08", desc: "Belastingdienst TOESLAGEN", cat: "Inkomsten", budget: "Kindgebonden budget", amount: 412, type: "income" },
    { date: "08-08", desc: "Belastingdienst TERUGGAVE", cat: "Inkomsten", budget: "Voorlopige teruggave", amount: 185.75, type: "income" },
  ],
  inbox: [
    { date: "20-08", desc: "PLUS 8891", amount: -23.1, suggestion: "Boodschappen", conf: "hoog" },
    { date: "19-08", desc: "Tikkie *Jansen", amount: -28.5, suggestion: "Rest · Openstaand", conf: "middel" },
    { date: "18-08", desc: "Naar Rek Spaar 1", amount: -600, suggestion: "Transfer · Buffer", conf: "hoog" },
  ],
  rules: [
    { type: "IBAN", match: "NL44ZKBK...", cat: "Woning", budget: "Internet (KPN)" },
    { type: "Omschrijving", match: "PLUS", cat: "Dagelijks leven", budget: "Boodschappen" },
    { type: "Omschrijving", match: "Zilveren Kruis", cat: "Dagelijks leven", budget: "Zorgverzekering" },
  ],
};

function remaining(b) {
  return b.planned - b.spent;
}
function paymentsOf(b) {
  return b.payments ?? (b.spent > 0.005 ? 1 : 0);
}
function enrich(cat) {
  const planned = cat.budgets.reduce((s, b) => s + b.planned, 0);
  const spent = cat.budgets.reduce((s, b) => s + b.spent, 0);
  const unpaid = cat.budgets.reduce((s, b) => s + Math.max(remaining(b), 0), 0);
  const overdue = cat.budgets.reduce((s, b) => s + Math.max(-remaining(b), 0), 0);
  return { ...cat, planned, spent, unpaid, overdue, budgetCount: cat.budgets.length };
}

const expenseTotal = DATA.categories.reduce((s, c) => s + c.budgets.reduce((a, b) => a + b.planned, 0), 0);
const expenseSpent = DATA.categories.reduce((s, c) => s + c.budgets.reduce((a, b) => a + b.spent, 0), 0);
const stillToPay = DATA.categories.reduce((s, c) => s + c.budgets.reduce((a, b) => a + Math.max(remaining(b), 0), 0), 0);
const overspentTotal = DATA.categories.reduce((s, c) => s + c.budgets.reduce((a, b) => a + Math.max(-remaining(b), 0), 0), 0);
const savingUnpaid = DATA.categories
  .filter((c) => c.type === "saving")
  .reduce((s, c) => s + c.budgets.reduce((a, b) => a + Math.max(remaining(b), 0), 0), 0);
const afterPosts = DATA.balance - stillToPay;
const netto = DATA.income.budgets.reduce((s, b) => s + b.planned, 0) - expenseTotal;

let state = {
  page: "maand",
  selected: "woning",
  typeFilter: "all",
  dashFilter: "all",
  expanded: {},
};

function rowClass(b, type) {
  const r = remaining(b);
  if (type === "income") return r <= 0.005 ? "paid" : "open";
  if (r < -0.005) return "over";
  if (r <= 0.005) return "paid";
  return "open";
}
function barClass(cat) {
  const p = pct(cat.spent, cat.planned);
  if (cat.type === "income") return "green";
  if (p >= 100) return "red";
  if (p >= 60) return "amber";
  return "green";
}

const CAT_ICONS = {
  inkomsten:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>',
  eten: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
  woning:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h14V10"/></svg>',
  dagelijks:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57L21 6H6"/></svg>',
  vervoer:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8A3 3 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>',
  verzekeringen:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
  sparen:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 0 3 2 4.5V20h4v-2h6v2h4v-4s1.9-1.3 2.3-3.5c.4-2.2.2-7.5-4.3-7.5z"/><path d="M16 9h.01"/></svg>',
  leningen:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3"/></svg>',
  overig:
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
  rest: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3"/><path d="m6.6 5.2 1.8 2.4"/><path d="M3 12h3"/><path d="m5.2 17.4 2.4-1.8"/><path d="M12 18v3"/><path d="m17.4 18.8-1.8-2.4"/><path d="M21 12h-3"/><path d="m18.8 6.6-2.4 1.8"/></svg>',
};

function typeLabel(type) {
  if (type === "income") return "Inkomsten";
  if (type === "saving") return "Sparen";
  return "Uitgaven";
}
function paidLabel(type) {
  if (type === "income") return "Ontvangen";
  if (type === "saving") return "Gespaard";
  return "Betaald";
}
function dueLabel(type) {
  if (type === "income") return "Nog te ontvangen";
  if (type === "saving") return "Nog te sparen";
  return "Nog te betalen";
}
function overLabel(type) {
  if (type === "income") return "Teveel ontvangen";
  if (type === "saving") return "Teveel gespaard";
  return "Teveel betaald";
}

function dueCell(r, type) {
  const isExpense = type === "expense";
  let cls = "due-zero";
  let text = money(0);
  if (isExpense) {
    if (r > 0.005) {
      cls = "due-ok";
      text = money(r);
    } else if (r < -0.005) {
      cls = "due-bad";
      text = money(r);
    }
  } else if (r > 0.005) {
    cls = "due-warn";
    text = money(r);
  } else if (r < -0.005) {
    cls = "due-ok";
    text = "+" + money(Math.abs(r));
  }
  return `<td class="num ${cls}">${text}</td>`;
}

function isEmptyBudget(b) {
  return b.planned < 0.005 && b.spent < 0.005;
}
function isQuiet(cat) {
  return cat.spent < 0.005 && cat.overdue < 0.005;
}
function alertClass(cat) {
  if (cat.overdue <= 0.005) return "";
  return cat.type === "expense" ? "alert-expense" : "alert-income";
}

function categoryCard(raw, collapsed) {
  const cat = enrich(raw);
  const isExpense = cat.type === "expense";
  const tone = cat.type === "income" ? "income" : cat.type === "saving" ? "saving" : "expense";
  if (collapsed) {
    return `<button class="cat-card collapsed type-${tone}" type="button" data-expand="${cat.id}">
      <div class="cat-id">
        <span class="cat-icon ${cat.color || "slate"}">${CAT_ICONS[cat.id] || CAT_ICONS.overig}</span>
        <div>
          <div class="cat-name">${cat.name}</div>
          <div class="cat-sub">Nog geen beweging · ${cat.budgetCount} budgetten</div>
        </div>
      </div>
      <span class="type-pill ${cat.type}">${typeLabel(cat.type)}</span>
      <strong class="cat-open-amt">${money(cat.unpaid)}</strong>
    </button>`;
  }
  const budgets = cat.budgets.filter((b) => !isEmptyBudget(b) || cat.budgets.every(isEmptyBudget));
  const rows = budgets
    .map((b) => {
      const r = remaining(b);
      return `<tr class="clickable" data-open="tx-detail">
        <td class="cat-row-name">${b.name}</td>
        <td class="num cat-pay">${paymentsOf(b)}</td>
        <td class="num">${money(b.planned)}</td>
        <td class="num">${money(b.spent)}</td>
        ${dueCell(r, cat.type)}
      </tr>`;
    })
    .join("");
  const overRow =
    cat.overdue > 0.005
      ? `<tr class="over-row ${isExpense ? "expense" : "income"}">
        <td class="cat-row-name">${overLabel(cat.type)}</td>
        <td></td><td></td><td></td>
        <td class="num">${isExpense ? money(-cat.overdue) : "+" + money(cat.overdue)}</td>
      </tr>`
      : "";
  const collapseBtn = `<span class="cat-actions"><button class="cat-open" data-page="${cat.page}" data-cat="${cat.id}">Openen</button>${
    isQuiet(cat) ? `<button class="cat-open" type="button" data-expand="${cat.id}">Inklappen</button>` : ""
  }</span>`;
  return `<article class="cat-card type-${tone} ${alertClass(cat)}">
    <div class="cat-card-h">
      <div class="cat-id">
        <span class="cat-icon ${cat.color || "slate"}">${CAT_ICONS[cat.id] || CAT_ICONS.overig}</span>
        <div>
          <div class="cat-name">${cat.name}</div>
          <div class="cat-sub">${cat.budgetCount} budgetten</div>
        </div>
      </div>
      ${collapseBtn}
      <span class="type-pill ${cat.type}">${typeLabel(cat.type)}</span>
    </div>
    <table class="cat-table">
      <thead>
        <tr>
          <th>Categorie</th>
          <th class="cat-pay">Betalingen</th>
          <th class="num">Budget</th>
          <th class="num">${paidLabel(cat.type)}</th>
          <th class="num">${dueLabel(cat.type)}</th>
        </tr>
      </thead>
      <tbody>
        ${rows}
        <tr class="total-row">
          <td class="cat-row-name">Totaal</td>
          <td></td>
          <td class="num">${money(cat.planned)}</td>
          <td class="num">${money(cat.spent)}</td>
          <td class="num">${money(cat.unpaid)}</td>
        </tr>
        ${overRow}
      </tbody>
    </table>
  </article>`;
}

function kpiCoverage() {
  const enough = afterPosts >= 0;
  return `<div class="kpis dash-kpis">
    <button class="kpi income" data-open="income-list">
      <div class="label">Inkomsten</div>
      <div class="value">${money(DATA.income.spent)}</div>
      <div class="hint muted">gepland ${money(DATA.income.planned)}</div>
    </button>
    <button class="kpi expense" data-open="expense-list">
      <div class="label">Uitgaven</div>
      <div class="value">${money(expenseSpent)}</div>
      <div class="hint muted">gepland ${money(expenseTotal)}</div>
    </button>
    <button class="kpi cover-hero ${enough ? "ok" : "bad"}" data-open="coverage">
      <div class="label">Na open posten</div>
      <div class="value">${enough ? "blijft " + money(afterPosts) + " over" : "tekort " + money(Math.abs(afterPosts))}</div>
      <p class="cover-note">Sparen ${money(savingUnpaid)} moet nog van de rekening. Saldo nu ${money(DATA.balance)} · nog te betalen ${money(stillToPay)} · teveel ${money(overspentTotal)}</p>
    </button>
  </div>`;
}

function viewMaandA() {
  const cards = [DATA.income, ...DATA.categories].map(enrich);
  const openCount = cards.filter((c) => c.unpaid > 0.005).length;
  const alertCount = cards.filter((c) => c.overdue > 0.005).length;
  const visible = cards.filter((c) => {
    if (state.dashFilter === "open") return c.unpaid > 0.005;
    if (state.dashFilter === "alert") return c.overdue > 0.005;
    return true;
  });
  const empty =
    visible.length === 0
      ? `<div class="card card-b muted">Geen categorieën in deze filter. Kies Alle om alles weer te zien.</div>`
      : "";
  const inboxN = DATA.inbox.length;
  return `
    ${kpiCoverage()}
    <button class="inbox-strip" type="button" data-open="assign">
      <span><strong>${inboxN} transacties</strong> nog toewijzen</span>
      <span class="inbox-action">Toewijzen</span>
    </button>
    <div class="dash-toolbar">
      <div>
        <div class="h2" style="margin-bottom:4px">Maandblad</div>
        <p class="muted small" style="margin:0">${DATA.period} · dezelfde kaarten als Uitgaven / Inkomsten / Sparen</p>
      </div>
      <div class="tabs">
        <button class="tab ${state.dashFilter === "all" ? "active" : ""}" data-dash-filter="all">Alle <span class="tab-count">${cards.length}</span></button>
        <button class="tab ${state.dashFilter === "open" ? "active" : ""}" data-dash-filter="open">Nog open <span class="tab-count">${openCount}</span></button>
        <button class="tab ${state.dashFilter === "alert" ? "active" : ""}" data-dash-filter="alert">Aandacht <span class="tab-count">${alertCount}</span></button>
      </div>
    </div>
    <div class="cat-grid">
      ${visible
        .map((c) => {
          const collapse = state.dashFilter === "all" && isQuiet(c) && !state.expanded[c.id];
          return categoryCard(c, collapse);
        })
        .join("")}
    </div>
    ${empty}`;
}

function viewMaandB() {
  const list = (title, items) => `<div class="card"><div class="card-h">${title}</div><div class="card-b">
    ${items
      .slice(0, 6)
      .map((b) => `<div style="margin-bottom:10px">
        <div class="row"><strong>${b.name}</strong><span class="grow"></span><span class="small muted">${money(b.spent)} / ${money(b.planned)}</span></div>
        <div class="bar ${b.spent > b.planned ? "red" : "green"}"><i style="width:${Math.min(100, pct(b.spent, b.planned))}%"></i></div>
      </div>`)
      .join("")}
  </div></div>`;
  const budgets = DATA.categories.flatMap((c) => c.budgets);
  return `
    ${kpiCoverage()}
    <div class="tabs">
      <button class="tab ${state.typeFilter === "all" ? "active" : ""}" data-filter="all">Alle</button>
      <button class="tab ${state.typeFilter === "expense" ? "active" : ""}" data-filter="expense">Uitgaven</button>
      <button class="tab ${state.typeFilter === "income" ? "active" : ""}" data-filter="income">Inkomsten</button>
      <button class="tab ${state.typeFilter === "saving" ? "active" : ""}" data-filter="saving">Sparen</button>
    </div>
    <div class="grid-5">
      <div class="card">
        <div class="card-h">Jaaroverzicht inkomsten / uitgaven</div>
        <div class="card-b">
          <div class="bar green" style="height:18px;margin-bottom:10px"><i style="width:78%"></i></div>
          <div class="bar red" style="height:18px"><i style="width:74%"></i></div>
          <p class="small muted" style="margin:12px 0 0">Placeholder voor de bestaande Apex-chart. Groen = inkomsten, rood = uitgaven per kalenderjaar.</p>
        </div>
      </div>
      ${list(
        "Begroot per budget",
        budgets.sort((a, b) => b.planned - a.planned),
      )}
      ${list(
        "Uitgaven per budget",
        budgets.sort((a, b) => b.spent - a.spent),
      )}
    </div>
    <div class="grid-4">
      <div class="card">
        <div class="card-h">Categorieën</div>
        <div class="card-b">
          ${DATA.categories
            .map(
              (c) => `<button class="cat" data-page="uitgaven" data-cat="${c.id}">
            <strong>${c.name}</strong>
            <div class="bar ${barClass(c)}" style="margin-top:8px"><i style="width:${Math.min(100, pct(c.spent, c.planned))}%"></i></div>
            <div class="meta"><span>${money(c.spent)}</span><span>${money(c.planned)}</span></div>
          </button>`,
            )
            .join("")}
        </div>
      </div>
      <div class="card">
        <div class="card-h">Laatste transacties <button class="btn btn-ghost small" data-page="transacties">Alles</button></div>
        <div class="card-b" style="padding:0">${txTable(DATA.txs)}</div>
      </div>
    </div>`;
}

function viewMaandC() {
  const openBudgets = DATA.categories.flatMap((c) =>
    c.budgets.filter((b) => remaining(b) > 0.5).map((b) => ({ ...b, cat: c.name })),
  );
  return `
    <div class="grid-2">
      <div>
        ${kpiCoverage()}
        <div class="card" style="margin-top:12px">
          <div class="card-h">Nog te betalen <span class="muted small">${openBudgets.length} posten</span></div>
          <div class="card-b" style="padding:0">
            <table>
              <thead><tr><th>Post</th><th>Categorie</th><th class="num">Open</th></tr></thead>
              <tbody>
                ${openBudgets
                  .sort((a, b) => remaining(b) - remaining(a))
                  .map(
                    (b) => `<tr class="clickable open" data-open="tx-detail"><td>${b.name}</td><td>${b.cat}</td><td class="num">${money(remaining(b))}</td></tr>`,
                  )
                  .join("")}
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-h">Inbox <span class="badge">${DATA.inbox.length}</span></div>
        <div class="card-b">
          ${DATA.inbox
            .map(
              (i) => `<div class="inbox-item">
              <div class="row"><h4>${i.desc}</h4><span class="grow"></span><strong>${money(i.amount)}</strong></div>
              <p class="small muted" style="margin:4px 0 10px">${i.date} · voorstel: ${i.suggestion} (${i.conf})</p>
              <div class="row">
                <button class="btn btn-primary" data-open="assign">Bevestig</button>
                <button class="btn" data-open="assign">Andere post</button>
              </div>
            </div>`,
            )
            .join("")}
        </div>
      </div>
    </div>`;
}

function txTable(rows) {
  return `<table>
    <thead><tr><th>Datum</th><th>Omschrijving</th><th>Categorie</th><th>Budget</th><th class="num">Bedrag</th><th></th></tr></thead>
    <tbody>
      ${rows
        .map(
          (t) => `<tr class="clickable" data-open="tx-detail">
          <td>${t.date}</td><td>${t.desc}</td><td>${t.cat}</td><td>${t.budget}</td>
          <td class="num">${money(t.amount)}</td>
          <td><button class="btn btn-ghost" data-open="assign">Toewijzen</button></td>
        </tr>`,
        )
        .join("")}
    </tbody>
  </table>`;
}

function viewSplit(type) {
  const list = type === "income" ? [DATA.income] : DATA.categories.filter((c) => (type === "saving" ? c.type === "saving" : c.type !== "saving" || type === "expense"));
  const cats = type === "income" ? [DATA.income] : type === "saving" ? DATA.categories.filter((c) => c.type === "saving") : DATA.categories.filter((c) => c.type === "expense" || c.id === "rest");
  const selected = cats.find((c) => c.id === state.selected) || cats[0];
  const dueLabel = selected.type === "income" ? "Te ontvangen" : selected.type === "saving" ? "Te sparen" : "Resterend budget";
  const spendLabel = selected.type === "income" ? "Ontvangen" : selected.type === "saving" ? "Gespaard" : "Uitgegeven";
  const open = remaining(selected);
  return `<div class="grid-exp">
    <div>
      ${cats
        .map(
          (c) => `<button class="cat ${c.id === selected.id ? "active" : ""}" data-select="${c.id}">
          <strong>${c.name}</strong>
          <div class="bar ${barClass(c)}" style="margin-top:8px"><i style="width:${Math.min(100, pct(c.spent, c.planned))}%"></i></div>
          <div class="meta"><span>${money(c.spent)}</span><b>${money(c.planned)}</b></div>
        </button>`,
        )
        .join("")}
    </div>
    <div class="stack">
      <div class="card card-b"><h2 class="h2">${selected.name}</h2></div>
      <div class="card card-b">
        <div class="row muted small"><span>${spendLabel}</span><span class="grow"></span><span>Budget</span></div>
        <div class="row"><b>${money(selected.spent)}</b><span class="grow"></span><b>${money(selected.planned)}</b></div>
        <div class="bar ${barClass(selected)}" style="margin:10px 0"><i style="width:${Math.min(100, pct(selected.spent, selected.planned))}%"></i></div>
        <div class="row small"><span>${pct(selected.spent, selected.planned)}%</span><span class="grow"></span><span>${Math.max(0, 100 - pct(selected.spent, selected.planned))}% open</span></div>
      </div>
      <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
        <div class="kpi" style="cursor:default"><div class="label">Budget</div><div class="value">${money(selected.planned)}</div></div>
        <div class="kpi" style="cursor:default"><div class="label">${spendLabel}</div><div class="value">${money(selected.spent)}</div></div>
        <div class="kpi" style="cursor:default"><div class="label">${dueLabel}</div><div class="value ${open < 0 ? "bad" : "ok"}">${money(Math.abs(open))}</div></div>
      </div>
      <div class="card">
        <div class="card-h">Budgetten</div>
        <div class="card-b" style="padding:0">
          <table>
            <thead><tr><th>Post</th><th class="num">Betalingen</th><th class="num">Budget</th><th class="num">${spendLabel}</th><th class="num">${dueLabel}</th></tr></thead>
            <tbody>${selected.budgets
              .map((b) => {
                const r = remaining(b);
                return `<tr class="clickable ${rowClass(b, selected.type)}" data-open="tx-detail">
                  <td>${b.name}</td><td class="num">${b.spent ? 1 : 0}</td>
                  <td class="num">${money(b.planned)}</td><td class="num">${money(b.spent)}</td>
                  <td class="num">${money(Math.abs(r))}</td></tr>`;
              })
              .join("")}</tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-h">Transacties binnen selectie</div>
        <div class="card-b" style="padding:0">${txTable(DATA.txs.filter((t) => t.cat === selected.name || selected.budgets.some((b) => b.name === t.budget)))}</div>
      </div>
    </div>
  </div>`;
}

function viewTransacties() {
  return `<div class="row">
      <div class="tabs">
        <button class="tab active">Alles</button>
        <button class="tab">Uitgaven</button>
        <button class="tab">Inkomsten</button>
        <button class="tab">Sparen</button>
        <button class="tab">Nog te categoriseren</button>
      </div>
      <span class="grow"></span>
      <input class="input" placeholder="Zoeken…" />
      <button class="btn" data-open="assign">Bulk herverdelen</button>
    </div>
    <div class="card" style="overflow:auto">${txTable(DATA.txs)}</div>`;
}

function viewCategories() {
  return `<div class="row"><h2 class="h2">Categorieën</h2><span class="grow"></span><button class="btn btn-primary" data-open="category">Nieuwe categorie</button></div>
    <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
      <div class="kpi" style="cursor:default"><div class="label">Categorieën</div><div class="value">9</div></div>
      <div class="kpi" style="cursor:default"><div class="label">Budgetten</div><div class="value">32</div></div>
      <div class="kpi" style="cursor:default"><div class="label">Totaal gebudgetteerd</div><div class="value">${money(expenseTotal)}</div></div>
    </div>
    ${[DATA.income, ...DATA.categories]
      .map(
        (c) => `<div class="card" style="margin-bottom:10px">
        <div class="card-h">${c.name} <button class="btn btn-ghost" data-open="category">Bewerken</button></div>
        <div class="card-b" style="padding:0"><table>
          <thead><tr><th>Budget</th><th class="num">Bedrag</th><th class="num">Besteed</th></tr></thead>
          <tbody>${c.budgets.map((b) => `<tr><td>${b.name}</td><td class="num">${money(b.planned)}</td><td class="num">${money(b.spent)}</td></tr>`).join("")}</tbody>
        </table></div>
      </div>`,
      )
      .join("")}`;
}

function viewRules() {
  return `<div class="row"><h2 class="h2">Koppelregels</h2><span class="grow"></span><button class="btn btn-primary" data-open="rule">Nieuwe regel</button></div>
    <p class="muted small">Blijft bestaan, maar mag later vooral vanuit Inbox/Toewijzen groeien. Bevestigen maakt of versterkt een regel.</p>
    <div class="card"><table>
      <thead><tr><th>Type</th><th>Match</th><th>Categorie</th><th>Budget</th><th></th></tr></thead>
      <tbody>${DATA.rules
        .map(
          (r) => `<tr><td>${r.type}</td><td>${r.match}</td><td>${r.cat}</td><td>${r.budget}</td>
          <td><button class="btn btn-ghost" data-open="rule">Bewerken</button></td></tr>`,
        )
        .join("")}</tbody>
    </table></div>`;
}

function viewBank() {
  return `<div class="grid-2">
    <div class="card">
      <div class="card-h">ING betaalrekening <span class="ok small">actief</span></div>
      <div class="card-b">
        <p>Saldo ${money(DATA.balance)}</p>
        <p class="muted small">Consent tot 12 nov 2026 · laatste sync 2 min geleden</p>
        <p class="muted small">214 nieuw · 0 dubbel · 3 inbox</p>
        <button class="btn btn-primary">Nu synchroniseren</button>
      </div>
    </div>
    <div class="card">
      <div class="card-h">Spaarrekening</div>
      <div class="card-b">
        <p>Niet gekoppeld</p>
        <p class="muted small">Nodig om Buffer als transfer te zien, niet als uitgave.</p>
        <button class="btn">Rekening koppelen</button>
      </div>
    </div>
  </div>`;
}

function maandView() {
  return viewMaandA();
}

const titles = {
  maand: "Dashboard",
  uitgaven: "Uitgaven",
  inkomsten: "Inkomsten",
  sparen: "Sparen",
  transacties: "Transacties",
  categorieen: "Categorieën",
  regels: "Koppelregels",
  bank: "EnableBanking",
};

function render() {
  const root = document.getElementById("view");
  const title = document.getElementById("crumb-page");
  title.textContent = titles[state.page];
  document.querySelectorAll(".nav-btn").forEach((b) => b.classList.toggle("active", b.dataset.page === state.page));
  if (state.page === "maand") root.innerHTML = maandView();
  if (state.page === "uitgaven") root.innerHTML = viewSplit("expense");
  if (state.page === "inkomsten") root.innerHTML = viewSplit("income");
  if (state.page === "sparen") root.innerHTML = viewSplit("saving");
  if (state.page === "transacties") root.innerHTML = viewTransacties();
  if (state.page === "categorieen") root.innerHTML = viewCategories();
  if (state.page === "regels") root.innerHTML = viewRules();
  if (state.page === "bank") root.innerHTML = viewBank();
}

function openModal(id) {
  document.querySelectorAll(".overlay").forEach((el) => el.classList.remove("open"));
  const el = document.getElementById(id);
  if (el) el.classList.add("open");
}
function closeModals() {
  document.querySelectorAll(".overlay").forEach((el) => el.classList.remove("open"));
}

document.addEventListener("click", (e) => {
  const collapseNav = e.target.closest("[data-collapse-nav]");
  if (collapseNav) {
    document.querySelector(".app")?.classList.toggle("nav-collapsed");
    return;
  }
  const expand = e.target.closest("[data-expand]");
  if (expand) {
    const id = expand.dataset.expand;
    state.expanded[id] = !state.expanded[id];
    render();
    return;
  }
  const nav = e.target.closest("[data-page]");
  if (nav) {
    state.page = nav.dataset.page;
    if (nav.dataset.cat) state.selected = nav.dataset.cat;
    render();
    return;
  }
  const sel = e.target.closest("[data-select]");
  if (sel) {
    state.selected = sel.dataset.select;
    render();
    return;
  }
  const dashFilter = e.target.closest("[data-dash-filter]");
  if (dashFilter) {
    state.dashFilter = dashFilter.dataset.dashFilter;
    render();
    return;
  }
  const filter = e.target.closest("[data-filter]");
  if (filter) {
    state.typeFilter = filter.dataset.filter;
    render();
    return;
  }
  const open = e.target.closest("[data-open]");
  if (open) {
    openModal(open.dataset.open);
    return;
  }
  if (e.target.closest("[data-close]")) closeModals();
});

document.addEventListener("DOMContentLoaded", render);
