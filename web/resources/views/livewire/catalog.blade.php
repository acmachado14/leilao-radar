<div
        id="catalog-root"
        data-lots-url="{{ file_exists(public_path('data/lotes.json')) ? asset('data/lotes.json') : config('radar.lots_url') }}"
        data-auth="{{ auth()->check() ? '1' : '0' }}"
        data-approved="{{ auth()->check() && auth()->user()->isApproved() && ! auth()->user()->isPending() ? '1' : '0' }}"
        data-login-url="{{ route('login') }}"
        data-register-url="{{ route('register') }}"
        data-interests-url="{{ url('/interesses') }}"
        data-evaluations-url="{{ url('/avaliacoes') }}"
        data-plans-url="{{ route('home') }}#planos"
        data-checkout-url="{{ $checkoutUrl }}"
        data-quota='@json($quota)'
    >
    <section class="mx-auto max-w-6xl px-4 pt-8">
        <div class="rounded-2xl border border-violet-500/30 bg-slate-900 px-4 py-5 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:px-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-violet-300">IA do VerifyRadar</p>
                <p class="mt-2 text-lg font-semibold">A IA calcula o teto de lance com base na FIPE e nas fotos.</p>
                <p class="mt-1 text-sm text-slate-400">Abra um lote e peça a avaliação. A cota só gasta quando você solicita aquele carro.</p>
            </div>
            <div class="mt-4 flex shrink-0 flex-wrap gap-3 sm:mt-0">
                <a href="{{ route('home') }}#planos" class="rounded-lg border border-violet-400/40 px-4 py-2 text-sm text-violet-200 hover:border-violet-300">Ver planos</a>
                @auth
                    <a href="{{ route('plano') }}" class="btn-emerald px-4 py-2 text-sm">Meu plano</a>
                @else
                    <a href="{{ route('register') }}" class="btn-emerald px-4 py-2 text-sm">Testar IA</a>
                @endauth
            </div>
        </div>
        <div class="meta mt-4 text-sm text-slate-400" id="meta"></div>
    </section>

    <section class="metrics mx-auto max-w-6xl px-4" id="metrics"></section>

    <section class="mx-auto max-w-6xl px-4 py-6">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Filtros</h2>
                <button
                    type="button"
                    id="reset-filters"
                    class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm text-slate-300 hover:border-emerald-500 hover:text-emerald-400"
                >
                    Limpar filtros
                </button>
            </div>

            <label class="mt-4 block">
                <span class="mb-1.5 block text-sm font-medium text-slate-300">Buscar</span>
                <input
                    id="search-filter"
                    type="search"
                    placeholder="Marca, modelo ou versão — ex.: Jetta GLI, Amarok…"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 placeholder:text-slate-500"
                >
            </label>

            <div class="mt-4 grid items-stretch gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col rounded-xl border border-slate-800 bg-slate-950/70 p-4" role="group" aria-label="Fonte">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Fonte</p>
                    <div id="fonte-filter" class="filter-chips">
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="sodre" aria-pressed="true">Sodré</button>
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="palacio" aria-pressed="true">Palácio</button>
                    </div>
                </div>

                <div class="flex min-w-0 flex-col rounded-xl border border-slate-800 bg-slate-950/70 p-4" role="group" aria-label="Match FIPE">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Match FIPE</p>
                    <div id="match-filter" class="filter-chips">
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="exact" aria-pressed="true">Exato</button>
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="closest" aria-pressed="true">Mais próximo</button>
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="failed" aria-pressed="true">Sem match</button>
                    </div>
                </div>

                <div class="flex min-w-0 flex-col rounded-xl border border-slate-800 bg-slate-950/70 p-4 sm:col-span-2 lg:col-span-1" role="group" aria-label="Classificação">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Classificação</p>
                    <div id="monta-filter" class="filter-chips">
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="sem_sinistro" aria-pressed="true">Sem sinistro</button>
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="pequena" aria-pressed="true">Pequena</button>
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="true" data-value="media" aria-pressed="true">Média</button>
                        <button type="button" class="filter-chip" data-filter-chip data-default-pressed="false" data-value="outro" aria-pressed="false">Outro</button>
                    </div>
                    <label class="mt-3 flex items-center gap-2.5 text-sm text-slate-300">
                        <input id="exclude-grande" type="checkbox" checked class="h-4 w-4 shrink-0 rounded border-slate-600 accent-emerald-500">
                        <span>Excluir grande monta</span>
                    </label>
                </div>
            </div>

            <div class="mt-3 grid gap-3 lg:grid-cols-12">
                <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4 lg:col-span-4">
                    <label for="min-desconto" class="mb-3 flex items-center justify-between gap-3 text-sm font-medium text-slate-300">
                        <span>Desconto mínimo vs FIPE</span>
                        <span id="min-desconto-label" class="font-semibold text-emerald-400">0%</span>
                    </label>
                    <input id="min-desconto" type="range" min="-50" max="80" value="0" class="w-full accent-emerald-500">
                    <div class="mt-2 flex justify-between text-[11px] text-slate-500">
                        <span>−50%</span>
                        <span>80%</span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-4 lg:col-span-8">
                    <span class="mb-3 block text-xs font-semibold uppercase tracking-widest text-slate-400">Marcas <span class="font-normal normal-case tracking-normal text-slate-500">— vazio = todas</span></span>
                    <div id="marca-filter" class="grid max-h-40 grid-cols-2 gap-x-2 gap-y-0.5 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
                        <p class="col-span-full px-2 py-3 text-sm text-slate-500">Carregando marcas…</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="flex flex-wrap items-baseline gap-2 text-lg font-semibold">
                        Ofertas mais relevantes
                        <span id="table-count" class="text-sm font-medium text-slate-400"></span>
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">Ordenação: 40% desconto vs FIPE + 35% proximidade do leilão + 25% classificação.</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <span class="shrink-0">Mostrar</span>
                    <input id="row-limit" type="number" min="10" max="500" value="100" step="10" class="w-20 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-center">
                    <span class="shrink-0 text-slate-500">cards</span>
                </label>
            </div>
            <div id="empty-state" class="hidden py-6 text-slate-400">Nenhum lote com os filtros atuais.</div>
            <div id="cards-grid" class="cards-grid"></div>
            <div id="load-sentinel" class="load-sentinel hidden" aria-hidden="true">
                <span class="load-spinner" aria-hidden="true"></span>
                <span>Carregando mais ofertas…</span>
            </div>
        </div>
    </section>

    <div id="lightbox" class="lightbox hidden" aria-hidden="true">
        <div class="lightbox-backdrop" data-close-lightbox></div>
        <button type="button" class="lightbox-close" data-close-lightbox aria-label="Fechar">
            <span class="lightbox-close-icon" aria-hidden="true">×</span>
            <span class="lightbox-close-label">Fechar</span>
        </button>
        <div class="lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="lightbox-title">
            <div class="lightbox-main">
                <img id="lightbox-main-img" alt="" referrerpolicy="no-referrer" />
                <button type="button" class="lightbox-nav lightbox-prev" id="lightbox-prev" aria-label="Foto anterior">‹</button>
                <button type="button" class="lightbox-nav lightbox-next" id="lightbox-next" aria-label="Próxima foto">›</button>
            </div>
            <div class="lightbox-thumbs" id="lightbox-thumbs"></div>
            <div class="lightbox-info">
                <h3 id="lightbox-title"></h3>
                <p id="lightbox-subtitle" class="lightbox-subtitle"></p>
                <dl id="lightbox-details" class="lightbox-details"></dl>
                <div id="lightbox-evaluation" class="lightbox-evaluation hidden" aria-live="polite"></div>
                <div class="lightbox-actions">
                    <a id="lightbox-link" href="#" target="_blank" rel="noopener" class="lightbox-cta">Ver no leilão</a>
                    <button type="button" id="lightbox-interest" class="lightbox-interest">Tenho interesse</button>
                    <button type="button" id="lightbox-evaluate" class="lightbox-evaluate">Avaliar com IA</button>
                    <button type="button" id="lightbox-share" class="lightbox-share">Copiar link</button>
                </div>
            </div>
        </div>
    </div>
</div>
