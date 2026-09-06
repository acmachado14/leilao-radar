<div
        id="catalog-root"
        data-lots-url="{{ file_exists(public_path('data/lotes.json')) ? asset('data/lotes.json') : config('radar.lots_url') }}"
        data-auth="{{ auth()->check() ? '1' : '0' }}"
        data-approved="{{ auth()->check() && auth()->user()->isApproved() && ! auth()->user()->isPending() ? '1' : '0' }}"
        data-login-url="{{ route('login') }}"
        data-register-url="{{ route('register') }}"
        data-interests-url="{{ url('/interesses') }}"
        data-evaluations-url="{{ url('/avaliacoes') }}"
        data-plans-url="#planos"
        data-checkout-url="{{ $checkoutUrl }}"
        data-quota='@json($quota)'
    >
    <section class="hero-stage mx-auto max-w-6xl px-4 py-8 sm:py-12">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">IA · VerifyRadar</p>
        <h1 class="mt-3 max-w-3xl text-3xl font-bold sm:text-5xl">A IA diz até quanto pagar no leilão — antes do lance.</h1>
        <p class="mt-4 max-w-2xl text-slate-400">Fotos, FIPE, monta e sinistro entram no parecer. Você recebe risco, checklist de pátio e o teto de lance para ainda ter lucro. Alertas avisam a faixa. A IA evita o lance emocional.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            @guest
                <a href="{{ route('register') }}" class="btn-emerald px-5 py-3">Testar 3 análises grátis</a>
                <a href="#planos" class="rounded-lg border border-violet-400/50 px-5 py-3 text-violet-200 hover:border-violet-300">Ver planos com IA</a>
            @else
                <a href="#planos" class="rounded-lg border border-violet-400/50 px-5 py-3 text-violet-200 hover:border-violet-300">Meu plano de IA</a>
                <a href="{{ route('alertas') }}" class="btn-emerald px-5 py-3">Ajustar meus alertas</a>
            @endguest
        </div>
        <div class="ai-hero-grid mt-8">
            <article class="ai-hero-card">
                <p class="ai-hero-kicker">Parecer em segundos</p>
                <p class="ai-hero-value">Risco 6/10</p>
                <p class="ai-hero-copy">A IA lê as fotos do pátio e cruza com a tabela. Você vê o que a ficha esconde.</p>
            </article>
            <article class="ai-hero-card ai-hero-card-accent">
                <p class="ai-hero-kicker">Limite sugerido de lance</p>
                <p class="ai-hero-value">R$ 32.000</p>
                <p class="ai-hero-copy">Revenda estimada − custos − lucro alvo. O teto para não comprar no vermelho.</p>
            </article>
            <article class="ai-hero-card">
                <p class="ai-hero-kicker">Alertas no ponto</p>
                <p class="ai-hero-value">Digest + 1h</p>
                <p class="ai-hero-copy">E-mail da faixa todo dia. Lembrete só nos carros em que você marcar interesse.</p>
            </article>
        </div>
        <div class="meta mt-6 text-sm text-slate-400" id="meta"></div>
    </section>

    <section class="metrics mx-auto max-w-6xl px-4" id="metrics"></section>

    <section id="planos" class="mx-auto max-w-6xl px-4 py-6">
        <div class="mb-5">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-300">Planos</p>
            <h2 class="mt-2 text-2xl font-bold">IA e alertas no mesmo plano.</h2>
            <p class="mt-2 max-w-2xl text-slate-400">O catálogo é público. A avaliação com IA e os recortes de alerta entram no plano. A compra é com um atendente no WhatsApp — sem cartão no site.</p>
        </div>
        <div class="plan-grid">
            @foreach ($plans as $plan)
                <article @class(['plan-card', 'plan-card-featured' => $plan['highlight']])>
                    @if ($plan['highlight'])
                        <p class="plan-badge">Mais escolhido</p>
                    @endif
                    <h3 class="plan-name">{{ $plan['name'] }}</h3>
                    <p class="plan-price">{{ $plan['price'] }}</p>
                    <p class="plan-tagline">{{ $plan['tagline'] }}</p>
                    <ul class="plan-features">
                        @foreach ($plan['features'] as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ $plan['checkout_url'] }}" target="_blank" rel="noopener" class="{{ $plan['highlight'] ? 'btn-emerald' : 'plan-cta' }} w-full px-4 py-3">
                        {{ $plan['cta'] }}
                    </a>
                    <p class="plan-note">{{ $plan['price_note'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-6">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Filtros</h2>
            <p class="mt-1 text-sm text-slate-500">Abra um lote e peça a avaliação. A IA só gasta cota quando você solicita aquele carro.</p>

            <label class="mt-4 block">
                <span class="mb-1.5 block text-sm text-slate-300">Buscar marca ou modelo</span>
                <input
                    id="search-filter"
                    type="search"
                    placeholder="Ex.: Jetta GLI, Amarok, Volkswagen…"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 placeholder:text-slate-500"
                >
            </label>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <fieldset>
                    <legend class="mb-2 text-sm text-slate-300">Fonte</legend>
                    <div id="fonte-filter" class="flex flex-wrap gap-2">
                        <button type="button" class="filter-chip" data-filter-chip data-value="sodre" aria-pressed="true">Sodré</button>
                        <button type="button" class="filter-chip" data-filter-chip data-value="palacio" aria-pressed="true">Palácio</button>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 text-sm text-slate-300">Match FIPE</legend>
                    <div id="match-filter" class="flex flex-wrap gap-2">
                        <button type="button" class="filter-chip" data-filter-chip data-value="exact" aria-pressed="true">Exato</button>
                        <button type="button" class="filter-chip" data-filter-chip data-value="closest" aria-pressed="true">Mais próximo</button>
                        <button type="button" class="filter-chip" data-filter-chip data-value="failed" aria-pressed="true">Sem match</button>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 text-sm text-slate-300">Classificação</legend>
                    <div id="monta-filter" class="flex flex-wrap gap-2">
                        <button type="button" class="filter-chip" data-filter-chip data-value="sem_sinistro" aria-pressed="true">Sem sinistro</button>
                        <button type="button" class="filter-chip" data-filter-chip data-value="pequena" aria-pressed="true">Pequena</button>
                        <button type="button" class="filter-chip" data-filter-chip data-value="media" aria-pressed="true">Média</button>
                        <button type="button" class="filter-chip" data-filter-chip data-value="outro" aria-pressed="false">Outro</button>
                    </div>
                </fieldset>

                <div>
                    <label for="min-desconto" class="mb-1.5 flex items-center justify-between gap-3 text-sm text-slate-300">
                        <span>Desconto mínimo</span>
                        <span id="min-desconto-label" class="font-semibold text-emerald-400">0%</span>
                    </label>
                    <input id="min-desconto" type="range" min="-50" max="80" value="0" class="w-full accent-emerald-500">
                </div>

                <div>
                    <span class="mb-1.5 block text-sm text-slate-300">Marcas <span class="font-normal text-slate-500">(vazio = todas)</span></span>
                    <div id="marca-filter" class="max-h-44 overflow-y-auto rounded-lg border border-slate-700 bg-slate-950 p-2">
                        <p class="px-2 py-3 text-sm text-slate-500">Carregando marcas…</p>
                    </div>
                </div>

                <div class="flex flex-col justify-end gap-4">
                    <label class="flex items-center gap-3 text-sm text-slate-300">
                        <input id="exclude-grande" type="checkbox" checked class="h-4 w-4 shrink-0 rounded border-slate-600 accent-emerald-500">
                        <span class="min-w-0">Excluir grande monta</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm text-slate-300">Máximo de cards</span>
                        <input id="row-limit" type="number" min="10" max="500" value="100" step="10" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
                    </label>
                </div>
            </div>

            <p class="mt-5 text-sm text-slate-500">Relevância = 40% desconto vs FIPE + 35% proximidade do leilão + 25% classificação.</p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
            <h2 class="mb-4 flex flex-wrap items-baseline gap-2 text-lg font-semibold">
                Ofertas mais relevantes
                <span id="table-count" class="text-sm font-medium text-slate-400"></span>
            </h2>
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
