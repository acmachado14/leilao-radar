const state = {
  payload: { items: [], count: 0 },
  rows: [],
};

function money(value) {
  if (value == null || Number.isNaN(value)) return "—";
  return `R$ ${Math.round(value).toLocaleString("pt-BR")}`;
}

function pct(value) {
  if (value == null || value <= -900) return "N/A";
  return `${(value * 100).toFixed(1)}%`;
}

function normalizeMarca(value) {
  return String(value || "").trim().toLowerCase();
}

function getSelectedValues(select) {
  return Array.from(select.selectedOptions).map((opt) => opt.value).filter(Boolean);
}

function applyFilters() {
  const matchFilter = getSelectedValues(document.getElementById("match-filter"));
  const minDesconto = Number(document.getElementById("min-desconto").value) / 100;
  const marcaFilter = getSelectedValues(document.getElementById("marca-filter"));
  const limit = Number(document.getElementById("row-limit").value) || 100;

  let filtered = state.rows.filter((row) => matchFilter.includes(row.fipe_match));
  filtered = filtered.filter((row) => (row.desconto_pct ?? -999) >= minDesconto);

  if (marcaFilter.length > 0) {
    const keys = new Set(marcaFilter.map(normalizeMarca));
    filtered = filtered.filter((row) => keys.has(normalizeMarca(row.marca)));
  }

  filtered = filtered
    .sort((a, b) => (b.relevance_score || 0) - (a.relevance_score || 0))
    .slice(0, limit);

  renderMetrics(filtered);
  renderTable(filtered);
}

function renderMeta() {
  const meta = document.getElementById("meta");
  const exported = state.payload.exported_at || "aguardando primeira exportação";
  meta.innerHTML = `
    <div>Última exportação: <strong>${exported}</strong></div>
    <div>Tabela: <strong>${state.payload.table_name || "leilao-radar-lotes"}</strong></div>
    <div>Região: <strong>${state.payload.region || "sa-east-1"}</strong></div>
    <div>Total no snapshot: <strong>${state.payload.count || 0}</strong></div>
  `;
}

function renderMetrics(rows) {
  const exact = rows.filter((r) => r.fipe_match === "exact").length;
  const withFipe = rows.filter((r) => (r.desconto_pct ?? -999) > -900);
  const avgDesconto =
    withFipe.length
      ? withFipe.reduce((sum, r) => sum + r.desconto_pct, 0) / withFipe.length
      : null;
  const avgLance =
    rows.length ? rows.reduce((sum, r) => sum + (r.lance_atual || 0), 0) / rows.length : null;

  document.getElementById("metrics").innerHTML = `
    <div class="metric-card"><span>Lotes (filtrados)</span><strong>${rows.length}</strong></div>
    <div class="metric-card"><span>Match FIPE exato</span><strong>${exact}</strong></div>
    <div class="metric-card"><span>Desconto médio</span><strong>${avgDesconto == null ? "N/A" : pct(avgDesconto)}</strong></div>
    <div class="metric-card"><span>Lance médio</span><strong>${avgLance == null ? "N/A" : money(avgLance)}</strong></div>
  `;
}

function renderTable(rows) {
  const body = document.getElementById("lots-body");
  const empty = document.getElementById("empty-state");
  body.innerHTML = "";

  if (!rows.length) {
    empty.classList.remove("hidden");
    return;
  }
  empty.classList.add("hidden");

  for (const row of rows) {
    const tr = document.createElement("tr");
    const descontoClass =
      row.desconto_pct != null && row.desconto_pct > 0 ? "desconto-positivo" : "desconto-negativo";
  tr.innerHTML = `
      <td>${row.titulo || "—"}</td>
      <td>${row.marca || "—"}</td>
      <td>${row.modelo || "—"}</td>
      <td>${row.ano_mod ?? "—"}</td>
      <td>${money(row.lance_atual)}</td>
      <td>${money(row.fipe_preco)}</td>
      <td class="${descontoClass}">${row.desconto_label || pct(row.desconto_pct)}</td>
      <td>${(row.relevance_score ?? 0).toFixed(3)}</td>
      <td>${row.days_until_auction != null ? row.days_until_auction.toFixed(1) : "—"}</td>
      <td>${row.leilao_fim || "—"}</td>
      <td>${money(row.custo_estimado_5pct)}</td>
      <td>${row.fipe_match || "—"}</td>
      <td>${row.patio || "—"}</td>
      <td>${row.url ? `<a href="${row.url}" target="_blank" rel="noopener">ver</a>` : "—"}</td>
    `;
    body.appendChild(tr);
  }
}

function populateMarcas() {
  const select = document.getElementById("marca-filter");
  const marcas = [...new Set(state.rows.map((r) => r.marca).filter(Boolean))].sort((a, b) =>
    a.localeCompare(b, "pt-BR", { sensitivity: "base" }),
  );
  select.innerHTML = "";
  for (const marca of marcas) {
    const opt = document.createElement("option");
    opt.value = marca;
    opt.textContent = marca;
    select.appendChild(opt);
  }
}

async function loadData() {
  const response = await fetch("./data/lotes.json", { cache: "no-store" });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  state.payload = await response.json();
  state.rows = state.payload.items || [];
  renderMeta();
  populateMarcas();
  applyFilters();
}

function bindEvents() {
  const minDesconto = document.getElementById("min-desconto");
  const minLabel = document.getElementById("min-desconto-label");
  minDesconto.addEventListener("input", () => {
    minLabel.textContent = `${minDesconto.value}%`;
    applyFilters();
  });

  ["match-filter", "marca-filter", "row-limit"].forEach((id) => {
    document.getElementById(id).addEventListener("change", applyFilters);
  });
}

bindEvents();
loadData().catch((err) => {
  document.getElementById("meta").innerHTML = `<div>Erro ao carregar dados: ${err.message}</div>`;
});
