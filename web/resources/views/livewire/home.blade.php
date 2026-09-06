<div>
<section class="hero-stage mx-auto max-w-6xl px-4 py-10 sm:py-16">
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">IA · VerifyRadar</p>
    <h1 class="mt-3 max-w-3xl text-3xl font-bold sm:text-5xl">A IA diz até quanto pagar no leilão — antes do lance.</h1>
    <p class="mt-4 max-w-2xl text-slate-400">Fotos, FIPE, monta e sinistro entram no parecer. Você recebe risco, checklist de pátio e o teto de lance para ainda ter lucro. Alertas avisam a faixa. A IA evita o lance emocional.</p>
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('register') }}" class="btn-emerald px-5 py-3">Testar 3 análises grátis</a>
        <a href="{{ route('catalog') }}" class="rounded-lg border border-slate-700 px-5 py-3 text-slate-100 hover:border-emerald-500">Ver ofertas</a>
        <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener" class="rounded-lg border border-violet-400/50 px-5 py-3 text-violet-200 hover:border-violet-300">Falar com atendente</a>
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
</section>

<section class="mx-auto max-w-6xl px-4 py-8">
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-300">Como funciona</p>
    <h2 class="mt-2 text-2xl font-bold">Três passos até o teto de lance.</h2>
    <ol class="mt-6 grid gap-4 sm:grid-cols-3">
        <li class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm font-semibold text-emerald-400">1. Abra o lote</p>
            <p class="mt-2 text-sm text-slate-400">Catálogo público de Sodré e Palácio, já cruzado com a FIPE.</p>
        </li>
        <li class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm font-semibold text-emerald-400">2. Peça a IA</p>
            <p class="mt-2 text-sm text-slate-400">Ela lê as fotos, a monta e calcula até quanto pagar visando lucro.</p>
        </li>
        <li class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm font-semibold text-emerald-400">3. Receba o alerta</p>
            <p class="mt-2 text-sm text-slate-400">Digest da sua faixa e aviso 1 hora antes nos carros com interesse.</p>
        </li>
    </ol>
</section>

<section id="planos" class="mx-auto max-w-6xl px-4 py-8 pb-16">
    <div class="mb-5">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-300">Planos</p>
        <h2 class="mt-2 text-2xl font-bold">IA e alertas no mesmo plano.</h2>
        <p class="mt-2 max-w-2xl text-slate-400">O catálogo é público. A avaliação com IA e os recortes de alerta entram no plano. A compra é com um atendente no WhatsApp — sem cartão no site.</p>
    </div>
    @include('partials.plans', ['plans' => $plans])
</section>
</div>
