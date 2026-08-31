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

function normalizeMarca(value) {
  return String(value || "").trim().toLowerCase();
}

function getSelectedValues(select) {
  return Array.from(select.selectedOptions).map((opt) => opt.value).filter(Boolean);
}

function cardImageSrc(row) {
  return row.foto_capa || PLACEHOLDER_IMG;
}

function cardPhotos(row) {
  if (row.fotos && row.fotos.length > 0) return row.fotos;
  if (row.foto_capa) return [row.foto_capa];
  return [];
}

function applyFilters() {
  const matchFilter = getSelectedValues(document.getElementById("match-filter"));
  const minDesconto = Number(document.getElementById("min-desconto").value) / 100;
  const marcaFilter = getSelectedValues(document.getElementById("marca-filter"));
  const montaFilter = getSelectedValues(document.getElementById("monta-filter"));
  const excludeGrande = document.getElementById("exclude-grande").checked;
  const limit = Number(document.getElementById("row-limit").value) || 100;

  let filtered = state.rows.filter((row) => matchFilter.includes(row.fipe_match));
  filtered = filtered.filter((row) => (row.desconto_pct ?? -999) >= minDesconto);

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
  card.className = "lot-card";
  const descontoClass =
    row.desconto_pct != null && row.desconto_pct > 0 ? "desconto-positivo" : "desconto-negativo";
  const photoCount = cardPhotos(row).length;
  const imgSrc = cardImageSrc(row);
  const isLazy = imgSrc !== PLACEHOLDER_IMG;

  card.innerHTML = `
    <button type="button" class="lot-card-media" data-open-lightbox="${row.lote_id}" aria-label="Ver fotos de ${row.titulo || "lote"}">
      <img
        ${isLazy ? `data-src="${imgSrc}"` : `src="${imgSrc}"`}
        alt="${row.titulo || "Veículo"}"
        class="${isLazy ? "lazy-img" : ""}"
        decoding="async"
        referrerpolicy="no-referrer"
        onerror="this.onerror=null;this.src='${PLACEHOLDER_IMG}'"
      />
      ${photoCount > 1 ? `<span class="photo-badge">${photoCount} fotos</span>` : ""}
    </button>
    <div class="lot-card-body">
      <h3 class="lot-card-title">${row.titulo || "—"}</h3>
      <p class="lot-card-subtitle">${row.marca || "—"} · ${row.modelo || "—"} · ${row.ano_mod ?? "—"}</p>
      <div class="lot-card-prices">
        <div><span>Lance</span><strong>${money(row.lance_atual)}</strong></div>
        <div><span>FIPE</span><strong>${money(row.fipe_preco)}</strong></div>
        <div><span>Desconto</span><strong class="${descontoClass}">${row.desconto_label || pct(row.desconto_pct)}</strong></div>
      </div>
      <div class="lot-card-tags">
        <span class="tag">${row.sinistro_label || row.sinistro || "—"}</span>
        <span class="tag tag-urgent">${row.days_until_auction != null ? `${row.days_until_auction.toFixed(1)} dias` : "sem data"}</span>
        <span class="tag tag-accent">relevância ${(row.relevance_score ?? 0).toFixed(3)}</span>
      </div>
      <div class="lot-card-footer">
        <span class="lot-card-patio">${row.patio || "—"}</span>
        ${row.url ? `<a href="${row.url}" target="_blank" rel="noopener" class="lot-card-link">Sodré</a>` : ""}
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

function openLightbox(row) {
  const photos = cardPhotos(row);
  state.lightbox = { row, photos, index: 0 };
  const lightbox = document.getElementById("lightbox");
  lightbox.classList.remove("hidden");
  lightbox.setAttribute("aria-hidden", "false");
  document.body.classList.add("lightbox-open");
  updateLightbox();
}

function closeLightbox() {
  const lightbox = document.getElementById("lightbox");
  lightbox.classList.add("hidden");
  lightbox.setAttribute("aria-hidden", "true");
  document.body.classList.remove("lightbox-open");
  state.lightbox = { row: null, photos: [], index: 0 };
}

function updateLightbox() {
  const { row, photos, index } = state.lightbox;
  if (!row) return;

  const mainImg = document.getElementById("lightbox-main-img");
  const title = document.getElementById("lightbox-title");
  const meta = document.getElementById("lightbox-meta");
  const link = document.getElementById("lightbox-link");
  const thumbs = document.getElementById("lightbox-thumbs");
  const prevBtn = document.getElementById("lightbox-prev");
  const nextBtn = document.getElementById("lightbox-next");

  const currentPhoto = photos[index] || PLACEHOLDER_IMG;
  mainImg.src = currentPhoto;
  mainImg.alt = row.titulo || "Veículo";
  title.textContent = row.titulo || "—";
  meta.textContent = `${row.marca || "—"} · Lance ${money(row.lance_atual)} · FIPE ${money(row.fipe_preco)} · ${row.desconto_label || pct(row.desconto_pct)} · ${row.sinistro_label || row.sinistro || "—"}`;
  link.href = row.url || "#";
  link.style.display = row.url ? "inline-flex" : "none";

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

  ["match-filter", "marca-filter", "monta-filter", "row-limit", "exclude-grande"].forEach((id) => {
    const el = document.getElementById(id);
    el.addEventListener("change", applyFilters);
    if (el.type === "checkbox") {
      el.addEventListener("input", applyFilters);
    }
  });

  document.getElementById("cards-grid").addEventListener("click", (event) => {
    const btn = event.target.closest("[data-open-lightbox]");
    if (!btn) return;
    const loteId = btn.getAttribute("data-open-lightbox");
    const row = state.display.filteredRows.find((r) => r.lote_id === loteId);
    if (row) openLightbox(row);
  });

  document.querySelectorAll("[data-close-lightbox]").forEach((el) => {
    el.addEventListener("click", closeLightbox);
  });
  document.getElementById("lightbox-prev").addEventListener("click", () => shiftLightbox(-1));
  document.getElementById("lightbox-next").addEventListener("click", () => shiftLightbox(1));

  document.addEventListener("keydown", (event) => {
    if (state.lightbox.row == null) return;
    if (event.key === "Escape") closeLightbox();
    if (event.key === "ArrowLeft") shiftLightbox(-1);
    if (event.key === "ArrowRight") shiftLightbox(1);
  });
}

bindEvents();
setupObservers();
loadData().catch((err) => {
  document.getElementById("meta").innerHTML = `<div>Erro ao carregar dados: ${err.message}</div>`;
});
