const CARDS_PER_BATCH = 24;

const state = {
  payload: { items: [], count: 0 },
  rows: [],
  display: {
    filteredRows: [],
    renderedCount: 0,
  },
  lightbox: {
    row: null,
    photos: [],
    index: 0,
  },
  interests: new Set(),
  evaluationPollTimer: null,
};

let scrollObserver = null;
let imageObserver = null;

const PLACEHOLDER_IMG =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect fill='%23111821' width='640' height='360'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%239aa7b5' font-family='system-ui' font-size='20'%3ESem foto%3C/text%3E%3C/svg%3E";

function money(value) {
  if (value == null || Number.isNaN(value)) return "—";
  return `R$ ${Math.round(value).toLocaleString("pt-BR")}`;
}

function pct(value) {
  if (value == null || value <= -900) return "N/A";
  return `${(value * 100).toFixed(1)}%`;
}

function formatDateTime(value) {
  if (!value) return "—";
  const date = parseAuctionDate(value);
  if (!date) return String(value).trim();
  return date.toLocaleString("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    timeZone: "America/Sao_Paulo",
  });
}

/** Parse auction datetimes as America/Sao_Paulo (UTC-3, no DST). */
function parseAuctionDate(value) {
  if (!value) return null;
  const raw = String(value).trim();

  // Palácio exports date-only; treat as open until end of that day in BRT.
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    const date = new Date(`${raw}T23:59:59-03:00`);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  let normalized = raw.includes("T") ? raw : raw.replace(" ", "T");
  // Naive timestamps from Sodré are São Paulo local time.
  if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(normalized)) {
    normalized = `${normalized}-03:00`;
  }
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return null;
  return date;
}

function daysUntilAuction(row, now = Date.now()) {
  const date = parseAuctionDate(row.leilao_fim || row.leilao_em);
  if (!date) return null;
  return (date.getTime() - now) / 86400000;
}

function enrichRow(row, now = Date.now()) {
  const days = daysUntilAuction(row, now);
  return {
    ...row,
    days_until_auction: days == null ? null : Math.round(days * 100) / 100,
  };
}

function isUpcomingAuction(row) {
  const days = row.days_until_auction;
  return days == null || days >= 0;
}

function catalogConfig() {
  const root = document.getElementById("catalog-root");
  let quota = null;
  try {
    quota = root?.dataset.quota ? JSON.parse(root.dataset.quota) : null;
  } catch (_err) {
    quota = null;
  }
  return {
    isAuth: root?.dataset.auth === "1",
    isApproved: root?.dataset.approved === "1",
    loginUrl: root?.dataset.loginUrl || "/login",
    registerUrl: root?.dataset.registerUrl || "/register",
    interestsUrl: (root?.dataset.interestsUrl || "/interesses").replace(/\/$/, ""),
    evaluationsUrl: (root?.dataset.evaluationsUrl || "/avaliacoes").replace(/\/$/, ""),
    plansUrl: root?.dataset.plansUrl || "/#planos",
    checkoutUrl: root?.dataset.checkoutUrl || "",
    quota,
    csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
  };
}

function updateQuota(quota) {
  if (!quota) return;
  const root = document.getElementById("catalog-root");
  if (root) root.dataset.quota = JSON.stringify(quota);
}

function isInterested(loteId) {
  return state.interests.has(String(loteId));
}

function goToLoginForInterest() {
  const cfg = catalogConfig();
  const next = `${window.location.pathname}${window.location.search}${window.location.hash}`;
  window.location.href = `${cfg.loginUrl}?redirect=${encodeURIComponent(next)}`;
}

function setInterestButtonState(button, loteId) {
  if (!button) return;
  const on = isInterested(loteId);
  const compact = button.hasAttribute("data-interest-toggle");
  button.classList.toggle("is-on", on);
  button.setAttribute("aria-pressed", on ? "true" : "false");
  button.textContent = on
    ? "Com interesse"
    : compact
      ? "Interesse"
      : "Tenho interesse";
}

function syncInterestUi(loteId) {
  const id = String(loteId);
  document.querySelectorAll(`[data-open-lote="${id}"]`).forEach((card) => {
    card.classList.toggle("is-interested", isInterested(id));
    setInterestButtonState(card.querySelector("[data-interest-toggle]"), id);
  });
  const lightboxBtn = document.getElementById("lightbox-interest");
  const openId = state.lightbox.row ? String(state.lightbox.row.lote_id) : "";
  if (lightboxBtn && openId === id) {
    setInterestButtonState(lightboxBtn, id);
  }
}

async function loadInterests() {
  const cfg = catalogConfig();
  if (!cfg.isAuth) return;
  const response = await fetch(cfg.interestsUrl, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  if (!response.ok) return;
  const payload = await response.json();
  state.interests = new Set((payload.lote_ids || []).map((id) => String(id)));
}

function showInterestError(message) {
  const button = document.getElementById("lightbox-interest");
  if (!button) return;
  const previous = button.textContent;
  button.textContent = message || "Não salvou — tente de novo";
  button.classList.add("is-error");
  window.clearTimeout(button._errorTimer);
  button._errorTimer = window.setTimeout(() => {
    button.classList.remove("is-error");
    button.textContent = previous;
  }, 2200);
}

async function toggleInterest(loteId) {
  const cfg = catalogConfig();
  if (!cfg.isAuth) {
    goToLoginForInterest();
    return;
  }

  const wanted = !isInterested(loteId);
  const response = await fetch(`${cfg.interestsUrl}/${encodeURIComponent(loteId)}`, {
    method: wanted ? "POST" : "DELETE",
    headers: {
      Accept: "application/json",
      "X-CSRF-TOKEN": cfg.csrf,
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  if (response.status === 401 || response.status === 419) {
    goToLoginForInterest();
    return;
  }
  if (!response.ok) {
    showInterestError(response.status === 404 ? "Lote não encontrado" : "Não salvou — tente de novo");
    return;
  }

  if (wanted) {
    state.interests.add(String(loteId));
  } else {
    state.interests.delete(String(loteId));
  }
  syncInterestUi(loteId);
}

function goToLoginForEvaluation() {
  const cfg = catalogConfig();
  const next = `${window.location.pathname}${window.location.search}${window.location.hash}`;
  window.location.href = `${cfg.loginUrl}?redirect=${encodeURIComponent(next)}`;
}

function stopEvaluationPolling() {
  if (state.evaluationPollTimer) {
    window.clearInterval(state.evaluationPollTimer);
    state.evaluationPollTimer = null;
  }
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function evaluationLoadingMarkup() {
  return `
    <div class="ai-loading">
      <div class="ai-radar" aria-hidden="true">
        <span class="ai-radar-sweep"></span>
        <span class="ai-radar-ping"></span>
      </div>
      <p class="lightbox-evaluation-title">IA analisando o lote</p>
      <p class="lightbox-evaluation-copy">Lendo fotos, cruzando com a FIPE e calculando o teto de lance.</p>
      <ol class="ai-loading-steps">
        <li class="is-active">Lendo as fotos do pátio</li>
        <li>Cruzando monta, sinistro e tabela</li>
        <li>Fechando limite de lance com lucro</li>
      </ol>
    </div>
  `;
}

function quotaPitchMarkup(payload) {
  const cfg = catalogConfig();
  const quota = payload?.quota || cfg.quota || {};
  const checkout = quota.checkout_url || cfg.checkoutUrl;
  const used = quota.used ?? 0;
  const limit = quota.limit ?? 0;
  return `
    <p class="lightbox-evaluation-title">Análises de IA esgotadas</p>
    <p class="lightbox-evaluation-copy">Você já usou ${escapeHtml(used)}/${escapeHtml(limit)} análises deste mês. No plano maior a IA continua dizendo até quanto pagar em cada lote.</p>
    <div class="lightbox-evaluation-actions">
      <a class="btn-emerald px-4 py-2" href="${escapeHtml(checkout)}" target="_blank" rel="noopener">Falar com atendente</a>
      <a class="lightbox-share" href="${escapeHtml(cfg.plansUrl)}">Ver planos</a>
    </div>
  `;
}

function guestPitchMarkup() {
  const cfg = catalogConfig();
  return `
    <p class="lightbox-evaluation-title">IA calcula o teto de lance</p>
    <p class="lightbox-evaluation-copy">Fotos + FIPE + custos de leilão. Você vê risco, o que conferir no pátio e até quanto pagar para ainda ter lucro. Trial com 3 análises grátis.</p>
    <div class="lightbox-evaluation-actions">
      <a class="btn-emerald px-4 py-2" href="${escapeHtml(cfg.registerUrl)}">Testar 3 análises grátis</a>
      <a class="lightbox-share" href="${escapeHtml(cfg.checkoutUrl)}" target="_blank" rel="noopener">Falar com atendente</a>
    </div>
  `;
}

function renderEvaluationPanel(payload) {
  const panel = document.getElementById("lightbox-evaluation");
  const evaluateBtn = document.getElementById("lightbox-evaluate");
  if (!panel) return;

  if (payload?.quota) updateQuota(payload.quota);
  syncEvaluateButton();

  panel.classList.remove("hidden");

  if (payload?.status === "pending") {
    if (evaluateBtn) evaluateBtn.classList.add("hidden");
    panel.innerHTML = evaluationLoadingMarkup();
    window.requestAnimationFrame(() => {
      const steps = panel.querySelectorAll(".ai-loading-steps li");
      steps.forEach((step, index) => {
        window.setTimeout(() => {
          steps.forEach((item) => item.classList.remove("is-active"));
          step.classList.add("is-active");
          if (index > 0) steps[index - 1].classList.add("is-done");
        }, 1800 * index);
      });
    });
    return;
  }

  if (payload?.status === "quota_exceeded") {
    if (evaluateBtn) evaluateBtn.classList.remove("hidden");
    panel.innerHTML = quotaPitchMarkup(payload);
    return;
  }

  if (payload?.status === "guest") {
    panel.innerHTML = guestPitchMarkup();
    return;
  }

  if (payload?.status === "failed") {
    if (evaluateBtn) {
      evaluateBtn.classList.remove("hidden");
      evaluateBtn.textContent = "Tentar de novo";
    }
    panel.innerHTML = `
      <p class="lightbox-evaluation-title">Não foi possível avaliar</p>
      <p class="lightbox-evaluation-copy">${escapeHtml(payload.error || "Tente novamente em instantes.")}</p>
    `;
    return;
  }

  if (payload?.status === "ready" && payload.evaluation) {
    if (evaluateBtn) evaluateBtn.classList.add("hidden");
    const ev = payload.evaluation;
    const flags = (ev.flags || []).map((item) => `<li>${escapeHtml(item)}</li>`).join("");
    const checks = (ev.patio_checks || []).map((item) => `<li>${escapeHtml(item)}</li>`).join("");
    const pricingBlock = ev.max_bid_amount
      ? `
      <div class="lightbox-evaluation-pricing">
        <p class="lightbox-evaluation-subtitle">Limite sugerido de lance (visando lucro)</p>
        <p class="lightbox-evaluation-limit">${money(ev.max_bid_amount)}</p>
        <p class="lightbox-evaluation-metrics">
          Revenda est.: ${money(ev.estimated_resale)}
          · Custos est.: ${money(ev.estimated_costs)}
          · Lucro alvo: ${money(ev.target_profit)}
        </p>
        ${ev.pricing_rationale ? `<p class="lightbox-evaluation-copy">${escapeHtml(ev.pricing_rationale)}</p>` : ""}
      </div>
    `
      : "";
    panel.innerHTML = `
      <p class="lightbox-evaluation-title">Parecer automático · risco ${escapeHtml(ev.risk_score)}/10</p>
      ${pricingBlock}
      <p class="lightbox-evaluation-copy">${escapeHtml(ev.summary)}</p>
      ${flags ? `<ul class="lightbox-evaluation-list">${flags}</ul>` : ""}
      ${checks ? `<p class="lightbox-evaluation-subtitle">No pátio, conferir:</p><ul class="lightbox-evaluation-list">${checks}</ul>` : ""}
      <p class="lightbox-evaluation-disclaimer">Parecer gerado por IA com base na FIPE. Não substitui vistoria nem garante lucro.</p>
    `;
    return;
  }

  panel.classList.add("hidden");
  panel.innerHTML = "";
  if (evaluateBtn) evaluateBtn.classList.remove("hidden");
}

function syncEvaluateButton() {
  const evaluateBtn = document.getElementById("lightbox-evaluate");
  if (!evaluateBtn) return;
  const cfg = catalogConfig();
  evaluateBtn.classList.remove("hidden");
  evaluateBtn.disabled = false;
  if (!cfg.isAuth || !cfg.isApproved) {
    evaluateBtn.textContent = "Avaliar com IA";
    return;
  }
  const quota = cfg.quota;
  if (quota && !quota.unlimited && typeof quota.remaining === "number") {
    evaluateBtn.textContent = quota.remaining > 0
      ? `Avaliar com IA (${quota.remaining} restantes)`
      : "Subir plano de IA";
    return;
  }
  evaluateBtn.textContent = "Avaliar com IA";
}

function resetEvaluationUi() {
  stopEvaluationPolling();
  const panel = document.getElementById("lightbox-evaluation");
  const evaluateBtn = document.getElementById("lightbox-evaluate");
  if (panel) {
    panel.classList.add("hidden");
    panel.innerHTML = "";
  }
  if (evaluateBtn) {
    evaluateBtn.classList.remove("hidden");
    evaluateBtn.disabled = false;
    syncEvaluateButton();
  }
}

async function fetchEvaluation(loteId) {
  const cfg = catalogConfig();
  const response = await fetch(`${cfg.evaluationsUrl}/${encodeURIComponent(loteId)}`, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  if (response.status === 404) return null;
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

async function refreshEvaluationUi(loteId) {
  const cfg = catalogConfig();
  syncEvaluateButton();

  if (!cfg.isApproved) {
    return;
  }

  try {
    const payload = await fetchEvaluation(loteId);
    if (payload === null) {
      renderEvaluationPanel(null);
      return;
    }
    renderEvaluationPanel(payload);
    if (payload.status === "pending") {
      stopEvaluationPolling();
      state.evaluationPollTimer = window.setInterval(() => {
        fetchEvaluation(loteId)
          .then((next) => {
            if (!next || state.lightbox.row?.lote_id !== loteId) return;
            renderEvaluationPanel(next);
            if (next.status !== "pending") stopEvaluationPolling();
          })
          .catch(() => {});
      }, 2500);
    }
  } catch (_err) {
    renderEvaluationPanel(null);
  }
}

async function requestEvaluation() {
  const row = state.lightbox.row;
  if (!row) return;

  const cfg = catalogConfig();
  if (!cfg.isAuth || !cfg.isApproved) {
    renderEvaluationPanel({ status: "guest" });
    return;
  }

  const evaluateBtn = document.getElementById("lightbox-evaluate");
  if (evaluateBtn) evaluateBtn.disabled = true;
  renderEvaluationPanel({ status: "pending" });

  try {
    const response = await fetch(`${cfg.evaluationsUrl}/${encodeURIComponent(row.lote_id)}`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-CSRF-TOKEN": cfg.csrf,
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (response.status === 401 || response.status === 419) {
      goToLoginForEvaluation();
      return;
    }

    const payload = await response.json().catch(() => ({}));
    if (response.status === 402 || payload.status === "quota_exceeded") {
      renderEvaluationPanel({ ...payload, status: "quota_exceeded" });
      return;
    }
    if (!response.ok) {
      renderEvaluationPanel({ status: "failed", error: payload.error || "Não foi possível iniciar a avaliação." });
      return;
    }

    renderEvaluationPanel(payload);
    if (payload.status === "pending") {
      await refreshEvaluationUi(row.lote_id);
    }
  } finally {
    if (evaluateBtn) evaluateBtn.disabled = false;
    syncEvaluateButton();
  }
}

function matchTypeLabel(value) {
  const labels = {
    exact: "Exato",
    closest: "Mais próximo",
    failed: "Sem match",
  };
  return labels[value] || value || "—";
}

function montaClassLabel(value) {
  const labels = {
    sem_sinistro: "Sem sinistro",
    pequena: "Pequena monta",
    media: "Média monta",
    grande: "Grande monta",
    outro: "Outro",
  };
  return labels[value] || value || "—";
}

function daysUntilLabel(value) {
  if (value == null || Number.isNaN(value)) return "Sem data";
  if (value < 0) return "Encerrado";
  if (value < 1) return `${(value * 24).toFixed(0)} h restantes`;
  return `${value.toFixed(1)} dias`;
}

function detailItem(label, value, valueClass = "") {
  const cls = valueClass ? ` class="${valueClass}"` : "";
  return `<div><dt>${label}</dt><dd${cls}>${value}</dd></div>`;
}

function normalizeMarca(value) {
  return String(value || "").trim().toLowerCase();
}

function normalizeSearchText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function matchesTextSearch(row, query) {
  const tokens = normalizeSearchText(query).split(/\s+/).filter(Boolean);
  if (!tokens.length) return true;
  const haystack = normalizeSearchText(`${row.marca || ""} ${row.modelo || ""}`);
  return tokens.every((token) => haystack.includes(token));
}

function getChipValues(containerId) {
  const root = document.getElementById(containerId);
  if (!root) return [];
  return Array.from(root.querySelectorAll("[data-filter-chip][aria-pressed='true']"))
    .map((el) => el.dataset.value)
    .filter(Boolean);
}

function getCheckedValues(containerId) {
  const root = document.getElementById(containerId);
  if (!root) return [];
  return Array.from(root.querySelectorAll("input[type='checkbox']:checked"))
    .map((el) => el.value)
    .filter(Boolean);
}

function cardImageSrc(row) {
  return row.foto_capa || PLACEHOLDER_IMG;
}

function cardPhotos(row) {
  if (row.fotos && row.fotos.length > 0) return row.fotos;
  if (row.foto_capa) return [row.foto_capa];
  return [];
}

function passesMinDesconto(row, minDesconto) {
  const desconto = row.desconto_pct;
  // Lots without FIPE have no meaningful discount — don't hide at 0% minimum.
  if (desconto != null && desconto <= -900) return true;
  return (desconto ?? -999) >= minDesconto;
}

function fonteLabel(fonte) {
  if (fonte === "palacio") return "Palácio";
  return "Sodré";
}

function applyFilters() {
  const searchQuery = document.getElementById("search-filter").value;
  const fonteFilter = getChipValues("fonte-filter");
  const matchFilter = getChipValues("match-filter");
  const minDesconto = Number(document.getElementById("min-desconto").value) / 100;
  const marcaFilter = getCheckedValues("marca-filter");
  const montaFilter = getChipValues("monta-filter");
  const excludeGrande = document.getElementById("exclude-grande").checked;
  const limit = Number(document.getElementById("row-limit").value) || 100;

  let filtered = state.rows.filter((row) => isUpcomingAuction(row));
  filtered = filtered.filter((row) => {
    const fonte = row.fonte || "sodre";
    return fonteFilter.length === 0 || fonteFilter.includes(fonte);
  });
  filtered = filtered.filter((row) => matchFilter.includes(row.fipe_match));
  filtered = filtered.filter((row) => passesMinDesconto(row, minDesconto));

  if (excludeGrande) {
    filtered = filtered.filter((row) => row.classificacao_monta !== "grande");
  }
  if (montaFilter.length > 0) {
    filtered = filtered.filter((row) => montaFilter.includes(row.classificacao_monta));
  }

  if (marcaFilter.length > 0) {
    const keys = new Set(marcaFilter.map(normalizeMarca));
    filtered = filtered.filter((row) => keys.has(normalizeMarca(row.marca)));
  }

  if (searchQuery.trim()) {
    filtered = filtered.filter((row) => matchesTextSearch(row, searchQuery));
  }

  filtered = filtered
    .sort((a, b) => (b.relevance_score || 0) - (a.relevance_score || 0))
    .slice(0, limit);

  state.display.filteredRows = filtered;
  state.display.renderedCount = 0;

  renderMetrics(filtered);
  resetCardsGrid();
  appendCardBatch();
  updateLoadSentinel();
}

function renderMeta() {
  const meta = document.getElementById("meta");
  const exported = state.payload.exported_at || "aguardando primeira exportação";
  meta.innerHTML = `
    <div class="meta-row">Atualizado <strong>${exported}</strong></div>
    <div class="meta-row">${state.payload.region || "sa-east-1"} · <strong>${state.payload.count || 0}</strong> lotes</div>
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
    <div class="metric-card"><span>Lotes filtrados</span><strong>${rows.length}</strong></div>
    <div class="metric-card"><span>Match FIPE exato</span><strong>${exact}</strong></div>
    <div class="metric-card"><span>Desconto médio</span><strong>${avgDesconto == null ? "N/A" : pct(avgDesconto)}</strong></div>
    <div class="metric-card"><span>Lance médio</span><strong>${avgLance == null ? "N/A" : money(avgLance)}</strong></div>
  `;
}

function updateCountLabel() {
  const countLabel = document.getElementById("table-count");
  const { filteredRows, renderedCount } = state.display;
  const total = filteredRows.length;
  const shown = Math.min(renderedCount, total);
  const moreHint = shown < total ? " · role para carregar mais" : "";
  countLabel.textContent = `(exibindo ${shown} de ${total} filtrados · ${state.rows.length} no snapshot${moreHint})`;
}

function resetCardsGrid() {
  const grid = document.getElementById("cards-grid");
  const empty = document.getElementById("empty-state");
  grid.innerHTML = "";
  empty.classList.add("hidden");
  updateCountLabel();
}

function createCardElement(row) {
  const card = document.createElement("article");
  card.className = `lot-card${isInterested(row.lote_id) ? " is-interested" : ""}`;
  card.setAttribute("data-open-lote", row.lote_id);
  card.setAttribute("role", "button");
  card.setAttribute("tabindex", "0");
  card.setAttribute("aria-label", `Ver detalhes de ${row.titulo || "lote"}`);
  const descontoClass =
    row.desconto_pct != null && row.desconto_pct > 0 ? "desconto-positivo" : "desconto-negativo";
  const photoCount = cardPhotos(row).length;
  const imgSrc = cardImageSrc(row);
  const isLazy = imgSrc !== PLACEHOLDER_IMG;

  card.innerHTML = `
    <div class="lot-card-media">
      <img
        ${isLazy ? `data-src="${imgSrc}"` : `src="${imgSrc}"`}
        alt="${row.titulo || "Veículo"}"
        class="${isLazy ? "lazy-img" : ""}"
        decoding="async"
        referrerpolicy="no-referrer"
        onerror="this.onerror=null;this.src='${PLACEHOLDER_IMG}'"
      />
      ${photoCount > 1 ? `<span class="photo-badge">${photoCount} fotos</span>` : ""}
    </div>
    <div class="lot-card-body">
      <h3 class="lot-card-title">${row.titulo || "—"}</h3>
      <p class="lot-card-subtitle">${row.marca || "—"} · ${row.modelo || "—"} · ${row.ano_mod ?? "—"}</p>
      <div class="lot-card-prices">
        <div><span>Lance</span><strong>${money(row.lance_atual)}</strong></div>
        <div><span>FIPE</span><strong>${money(row.fipe_preco)}</strong></div>
        <div><span>Desconto</span><strong class="${descontoClass}">${row.desconto_label || pct(row.desconto_pct)}</strong></div>
      </div>
      <div class="lot-card-tags">
        <span class="tag">${fonteLabel(row.fonte)}</span>
        <span class="tag">${row.sinistro_label || row.sinistro || "—"}</span>
        <span class="tag">${matchTypeLabel(row.fipe_match)}</span>
        <span class="tag tag-urgent">${daysUntilLabel(row.days_until_auction)}</span>
        <span class="tag tag-accent">relevância ${(row.relevance_score ?? 0).toFixed(3)}</span>
      </div>
      <div class="lot-card-footer">
        <span class="lot-card-patio">${row.patio || "—"}</span>
        <button type="button" class="lot-card-interest${isInterested(row.lote_id) ? " is-on" : ""}" data-interest-toggle data-interest-lote="${row.lote_id}" aria-pressed="${isInterested(row.lote_id) ? "true" : "false"}">
          ${isInterested(row.lote_id) ? "Com interesse" : "Interesse"}
        </button>
        ${row.url ? `<a href="${row.url}" target="_blank" rel="noopener" class="lot-card-link" data-external-link>${fonteLabel(row.fonte)}</a>` : ""}
      </div>
    </div>
  `;
  return card;
}

function observeLazyImages(root) {
  if (!imageObserver) return;
  root.querySelectorAll("img.lazy-img[data-src]:not([data-loaded])").forEach((img) => {
    imageObserver.observe(img);
  });
}

function appendCardBatch() {
  const { filteredRows, renderedCount } = state.display;
  const empty = document.getElementById("empty-state");
  const grid = document.getElementById("cards-grid");

  if (!filteredRows.length) {
    empty.classList.remove("hidden");
    updateCountLabel();
    updateLoadSentinel();
    return;
  }

  const batch = filteredRows.slice(renderedCount, renderedCount + CARDS_PER_BATCH);
  if (!batch.length) {
    updateLoadSentinel();
    return;
  }

  const fragment = document.createDocumentFragment();
  for (const row of batch) {
    fragment.appendChild(createCardElement(row));
  }
  grid.appendChild(fragment);
  observeLazyImages(grid);

  state.display.renderedCount += batch.length;
  updateCountLabel();
  updateLoadSentinel();
}

function updateLoadSentinel() {
  const sentinel = document.getElementById("load-sentinel");
  const { filteredRows, renderedCount } = state.display;
  const hasMore = renderedCount < filteredRows.length;

  if (!filteredRows.length || !hasMore) {
    sentinel.classList.add("hidden");
    sentinel.setAttribute("aria-hidden", "true");
    return;
  }

  sentinel.classList.remove("hidden");
  sentinel.setAttribute("aria-hidden", "false");
}

function setupObservers() {
  const sentinel = document.getElementById("load-sentinel");

  imageObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        const img = entry.target;
        const src = img.getAttribute("data-src");
        if (!src) continue;
        img.src = src;
        img.removeAttribute("data-src");
        img.setAttribute("data-loaded", "true");
        imageObserver.unobserve(img);
      }
    },
    { rootMargin: "200px 0px", threshold: 0.01 },
  );

  scrollObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        const { filteredRows, renderedCount } = state.display;
        if (renderedCount < filteredRows.length) {
          appendCardBatch();
        }
      }
    },
    { rootMargin: "400px 0px", threshold: 0 },
  );

  scrollObserver.observe(sentinel);
}

function lotShareId(row) {
  return String(row?.lote_id || "").trim();
}

function lotShareUrl(loteId) {
  const url = new URL(window.location.href);
  url.search = "";
  url.hash = loteId ? `lote=${encodeURIComponent(loteId)}` : "";
  return url.toString();
}

function readLoteFromHash() {
  const raw = window.location.hash.replace(/^#/, "");
  if (!raw) return null;
  const params = new URLSearchParams(raw.includes("=") ? raw : `lote=${raw}`);
  const lote = params.get("lote");
  return lote ? String(lote).trim() : null;
}

function setLoteHash(loteId) {
  const next = loteId ? `#lote=${encodeURIComponent(loteId)}` : "";
  if (window.location.hash === next) return;
  if (next) {
    history.pushState({ loteId }, "", next);
  } else {
    history.pushState({}, "", `${window.location.pathname}${window.location.search}`);
  }
}

function findLotById(loteId) {
  if (!loteId) return null;
  const matchId = (row) => String(row.lote_id) === String(loteId);
  const fromRows =
    state.rows.find(matchId) || state.display.filteredRows.find(matchId) || null;
  if (fromRows) return fromRows;
  // Fallback: allow share links for lots filtered out (ended / filters).
  const fromPayload = (state.payload.items || []).find(matchId);
  return fromPayload ? enrichRow(fromPayload, Date.now()) : null;
}

function openLightbox(row, { syncHash = true } = {}) {
  const photos = cardPhotos(row);
  state.lightbox = { row, photos, index: 0 };
  const lightbox = document.getElementById("lightbox");
  lightbox.classList.remove("hidden");
  lightbox.setAttribute("aria-hidden", "false");
  document.body.classList.add("lightbox-open");
  if (syncHash) setLoteHash(lotShareId(row));
  updateLightbox();
}

function closeLightbox({ syncHash = true } = {}) {
  const lightbox = document.getElementById("lightbox");
  lightbox.classList.add("hidden");
  lightbox.setAttribute("aria-hidden", "true");
  document.body.classList.remove("lightbox-open");
  state.lightbox = { row: null, photos: [], index: 0 };
  resetEvaluationUi();
  if (syncHash) setLoteHash(null);
  resetShareButton();
}

function resetShareButton() {
  const shareBtn = document.getElementById("lightbox-share");
  if (!shareBtn) return;
  shareBtn.textContent = "Copiar link";
  shareBtn.classList.remove("is-copied", "is-error");
}

function copyTextSync(text) {
  if (!text) return false;

  const active = document.activeElement;
  const el = document.createElement("textarea");
  el.value = text;
  el.setAttribute("readonly", "");
  el.setAttribute("aria-hidden", "true");
  el.tabIndex = -1;
  el.style.position = "fixed";
  el.style.top = "0";
  el.style.left = "0";
  el.style.width = "1px";
  el.style.height = "1px";
  el.style.padding = "0";
  el.style.border = "none";
  el.style.opacity = "0";
  el.style.pointerEvents = "none";

  document.body.appendChild(el);
  el.focus({ preventScroll: true });
  el.select();
  el.setSelectionRange(0, text.length);

  let copied = false;
  try {
    copied = document.execCommand("copy");
  } catch (_err) {
    copied = false;
  }

  el.remove();
  if (active && typeof active.focus === "function") {
    active.focus({ preventScroll: true });
  }
  return copied;
}

function showShareCopied() {
  const shareBtn = document.getElementById("lightbox-share");
  if (!shareBtn) return;
  shareBtn.textContent = "Link copiado!";
  shareBtn.classList.remove("is-error");
  shareBtn.classList.add("is-copied");
  window.clearTimeout(shareBtn._copiedTimer);
  shareBtn._copiedTimer = window.setTimeout(resetShareButton, 2000);
}

function showShareError() {
  const shareBtn = document.getElementById("lightbox-share");
  if (!shareBtn) return;
  shareBtn.textContent = "Não copiou — tente de novo";
  shareBtn.classList.remove("is-copied");
  shareBtn.classList.add("is-error");
  window.clearTimeout(shareBtn._copiedTimer);
  shareBtn._copiedTimer = window.setTimeout(resetShareButton, 2200);
}

function copyShareLink(event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  const row = state.lightbox.row;
  if (!row) return;

  const shareUrl = lotShareUrl(lotShareId(row));
  let settled = false;

  const finish = (ok) => {
    if (settled) return;
    settled = true;
    if (ok) showShareCopied();
    else showShareError();
  };

  if (copyTextSync(shareUrl)) {
    finish(true);
    return;
  }

  const clipboard = navigator.clipboard;
  if (clipboard?.writeText) {
    clipboard.writeText(shareUrl).then(() => finish(true)).catch(() => finish(false));
    return;
  }

  finish(false);
}

function openLotFromHash() {
  const loteId = readLoteFromHash();
  if (!loteId) {
    if (state.lightbox.row) closeLightbox({ syncHash: false });
    return;
  }
  if (state.lightbox.row && lotShareId(state.lightbox.row) === loteId) return;
  const row = findLotById(loteId);
  if (row) {
    openLightbox(row, { syncHash: false });
  }
}

function updateLightbox() {
  const { row, photos, index } = state.lightbox;
  if (!row) return;

  const mainImg = document.getElementById("lightbox-main-img");
  const title = document.getElementById("lightbox-title");
  const subtitle = document.getElementById("lightbox-subtitle");
  const details = document.getElementById("lightbox-details");
  const link = document.getElementById("lightbox-link");
  const thumbs = document.getElementById("lightbox-thumbs");
  const prevBtn = document.getElementById("lightbox-prev");
  const nextBtn = document.getElementById("lightbox-next");

  const currentPhoto = photos[index] || PLACEHOLDER_IMG;
  const descontoClass =
    row.desconto_pct != null && row.desconto_pct > 0 ? "desconto-positivo" : "desconto-negativo";

  mainImg.src = currentPhoto;
  mainImg.alt = row.titulo || "Veículo";
  title.textContent = row.titulo || "—";
  subtitle.textContent = `${row.marca || "—"} · ${row.modelo || "—"} · ${row.ano_mod ?? "—"}`;

  details.innerHTML = [
    detailItem("Data do leilão", formatDateTime(row.leilao_em || row.leilao_fim)),
    detailItem("Fim do lote", formatDateTime(row.leilao_fim)),
    detailItem("Prazo", daysUntilLabel(row.days_until_auction)),
    detailItem("Fonte", fonteLabel(row.fonte)),
    detailItem("Match FIPE", matchTypeLabel(row.fipe_match)),
    detailItem("Lance atual", money(row.lance_atual)),
    detailItem("FIPE", money(row.fipe_preco)),
    detailItem("Desconto", row.desconto_label || pct(row.desconto_pct), descontoClass),
    detailItem("Custo est. (+5%)", money(row.custo_estimado_5pct)),
    detailItem("Classificação", montaClassLabel(row.classificacao_monta)),
    detailItem("Sinistro", row.sinistro_label || row.sinistro || "—"),
    detailItem("Pátio", row.patio || "—"),
    detailItem("Relevância", (row.relevance_score ?? 0).toFixed(3)),
    detailItem("ID do lote", row.lote_id || "—"),
  ].join("");

  link.textContent = `Ver no ${fonteLabel(row.fonte)}`;
  link.href = row.url || "#";
  link.style.display = row.url ? "inline-flex" : "none";
  resetShareButton();
  setInterestButtonState(document.getElementById("lightbox-interest"), row.lote_id);
  refreshEvaluationUi(row.lote_id);

  const showNav = photos.length > 1;
  prevBtn.style.display = showNav ? "flex" : "none";
  nextBtn.style.display = showNav ? "flex" : "none";

  thumbs.innerHTML = "";
  if (photos.length > 1) {
    photos.forEach((src, i) => {
      const thumb = document.createElement("button");
      thumb.type = "button";
      thumb.className = `lightbox-thumb${i === index ? " active" : ""}`;
      thumb.innerHTML = `<img src="${src}" alt="" loading="lazy" referrerpolicy="no-referrer" />`;
      thumb.addEventListener("click", () => {
        state.lightbox.index = i;
        updateLightbox();
      });
      thumbs.appendChild(thumb);
    });
  }
}

function shiftLightbox(delta) {
  const { photos } = state.lightbox;
  if (photos.length <= 1) return;
  state.lightbox.index = (state.lightbox.index + delta + photos.length) % photos.length;
  updateLightbox();
}

function populateMarcas() {
  const root = document.getElementById("marca-filter");
  const marcas = [...new Set(state.rows.map((r) => r.marca).filter(Boolean))].sort((a, b) =>
    a.localeCompare(b, "pt-BR", { sensitivity: "base" }),
  );
  root.innerHTML = "";
  if (!marcas.length) {
    const empty = document.createElement("p");
    empty.className = "px-2 py-3 text-sm text-slate-500";
    empty.textContent = "Nenhuma marca no snapshot.";
    root.appendChild(empty);
    return;
  }
  for (const marca of marcas) {
    const label = document.createElement("label");
    label.className = "flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 text-sm text-slate-300 hover:bg-slate-800";
    const input = document.createElement("input");
    input.type = "checkbox";
    input.value = marca;
    input.className = "h-4 w-4 shrink-0 rounded border-slate-600 accent-emerald-500";
    const span = document.createElement("span");
    span.className = "min-w-0 truncate";
    span.textContent = marca;
    label.append(input, span);
    root.appendChild(label);
  }
}

async function loadData() {
  const root = document.getElementById("catalog-root");
  const lotsUrl = root?.dataset.lotsUrl || "/data/lotes.json";
  const response = await fetch(lotsUrl, { cache: "no-store" });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  state.payload = await response.json();
  const now = Date.now();
  // Recalculate days_until from auction dates so stale JSON exports
  // cannot show "Xh restantes" for auctions that already ended.
  state.rows = (state.payload.items || [])
    .map((row) => enrichRow(row, now))
    .filter(isUpcomingAuction);
  state.payload.count = state.rows.length;
  await loadInterests();
  renderMeta();
  populateMarcas();
  applyFilters();
  openLotFromHash();
}

function bindEvents() {
  const minDesconto = document.getElementById("min-desconto");
  const minLabel = document.getElementById("min-desconto-label");
  minDesconto.addEventListener("input", () => {
    minLabel.textContent = `${minDesconto.value}%`;
    applyFilters();
  });

  document.getElementById("search-filter").addEventListener("input", applyFilters);

  document.querySelectorAll("[data-filter-chip]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const on = btn.getAttribute("aria-pressed") !== "true";
      btn.setAttribute("aria-pressed", on ? "true" : "false");
      applyFilters();
    });
  });

  document.getElementById("marca-filter").addEventListener("change", applyFilters);

  ["row-limit", "exclude-grande"].forEach((id) => {
    const el = document.getElementById(id);
    el.addEventListener("change", applyFilters);
    if (el.type === "checkbox") {
      el.addEventListener("input", applyFilters);
    }
  });

  document.getElementById("cards-grid").addEventListener("click", (event) => {
    const interestBtn = event.target.closest("[data-interest-toggle]");
    if (interestBtn) {
      event.preventDefault();
      event.stopPropagation();
      toggleInterest(interestBtn.getAttribute("data-interest-lote"));
      return;
    }
    if (event.target.closest("[data-external-link]")) return;
    const card = event.target.closest("[data-open-lote]");
    if (!card) return;
    const loteId = card.getAttribute("data-open-lote");
    const row = state.display.filteredRows.find((r) => r.lote_id === loteId);
    if (row) openLightbox(row);
  });

  document.getElementById("cards-grid").addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    const card = event.target.closest("[data-open-lote]");
    if (!card || event.target.closest("[data-external-link]")) return;
    event.preventDefault();
    const loteId = card.getAttribute("data-open-lote");
    const row = state.display.filteredRows.find((r) => r.lote_id === loteId);
    if (row) openLightbox(row);
  });

  document.querySelectorAll("[data-close-lightbox]").forEach((el) => {
    el.addEventListener("click", () => closeLightbox());
  });
  document.getElementById("lightbox-prev").addEventListener("click", () => shiftLightbox(-1));
  document.getElementById("lightbox-next").addEventListener("click", () => shiftLightbox(1));
  document.getElementById("lightbox-share").addEventListener("click", copyShareLink, true);
  document.getElementById("lightbox-interest").addEventListener("click", () => {
    const row = state.lightbox.row;
    if (row) toggleInterest(row.lote_id);
  });
  document.getElementById("lightbox-evaluate").addEventListener("click", () => {
    requestEvaluation();
  });

  window.addEventListener("hashchange", openLotFromHash);
  window.addEventListener("popstate", openLotFromHash);

  document.addEventListener("keydown", (event) => {
    if (state.lightbox.row == null) return;
    if (event.key === "Escape") closeLightbox();
    if (event.key === "ArrowLeft") shiftLightbox(-1);
    if (event.key === "ArrowRight") shiftLightbox(1);
  });
}

function bootCatalog() {
  const catalogRoot = document.getElementById("catalog-root");
  if (!catalogRoot) return;
  bindEvents();
  setupObservers();
  loadData().catch((err) => {
    const meta = document.getElementById("meta");
    if (meta) meta.innerHTML = `<div>Erro ao carregar dados: ${err.message}</div>`;
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", bootCatalog);
} else {
  bootCatalog();
}
