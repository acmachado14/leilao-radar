<div>
<section class="hero-stage mx-auto max-w-6xl px-4 py-10 sm:py-16">
    <div class="grid items-center gap-10 lg:grid-cols-2">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">IA · VerifyRadar</p>
            <h1 class="mt-3 max-w-3xl text-3xl font-bold sm:text-5xl">A IA diz até quanto pagar no leilão — antes do lance.</h1>
            <p class="mt-4 max-w-2xl text-slate-400">Fotos, FIPE, monta e sinistro entram no parecer. Você recebe risco, checklist de pátio e o teto de lance para ainda ter lucro. Alertas avisam a faixa. A IA evita o lance emocional.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                @guest
                    <a href="{{ route('register') }}" class="btn-emerald px-5 py-3">Testar 3 análises grátis</a>
                    <a href="{{ route('login') }}" class="rounded-lg border border-slate-500 px-5 py-3 font-semibold text-white hover:border-emerald-500">Entrar</a>
                    <a href="{{ route('catalog') }}" class="rounded-lg border border-slate-700 px-5 py-3 text-slate-100 hover:border-emerald-500">Ver ofertas</a>
                @else
                    <a href="{{ route('catalog') }}" class="btn-emerald px-5 py-3">Ver ofertas</a>
                    <a href="{{ route('plano') }}" class="rounded-lg border border-slate-500 px-5 py-3 font-semibold text-white hover:border-emerald-500">Meu plano</a>
                @endguest
            </div>
            <p class="mt-3 text-sm text-slate-500">
                <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener" class="text-violet-300 hover:text-violet-200">Falar com atendente no WhatsApp</a>
            </p>
        </div>
        <div class="mx-auto flex w-full max-w-md flex-col items-center justify-center rounded-3xl border border-slate-800 bg-slate-900/80 p-6 sm:p-8">
            <img src="{{ asset('images/logo.png') }}" alt="VerifyRadar" class="h-28 w-28 rounded-[1.75rem] shadow-2xl shadow-emerald-500/10 sm:h-40 sm:w-40 sm:rounded-[2rem]">
            <p class="mt-4 text-center text-xs font-medium uppercase tracking-widest text-slate-500">grupo VerifyCar</p>
            <img src="{{ asset('images/brand/horizontal_branco0.png') }}" alt="VerifyCar" class="mt-2 h-5 w-auto">
        </div>
    </div>
    <div class="ai-hero-grid mt-10">
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
            <p class="ai-hero-kicker">Aviso no e-mail</p>
            <p class="ai-hero-value">Todo dia de manhã</p>
            <p class="ai-hero-copy">Você recebe os carros da sua busca. Se marcar interesse, avisamos 1 hora antes do leilão.</p>
        </article>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-8">
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-300">Como funciona</p>
    <h2 class="mt-2 text-2xl font-bold">Três passos até o teto de lance.</h2>
    <ol class="mt-6 grid gap-4 sm:grid-cols-3">
        <li class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <img src="{{ asset('images/brand/search.png') }}" alt="" class="mb-4 h-16 w-16 object-contain">
            <p class="text-sm font-semibold text-emerald-400">1. Busque o lote</p>
            <p class="mt-2 text-sm text-slate-400">Catálogo público de Sodré e Palácio, já cruzado com a FIPE.</p>
        </li>
        <li class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <img src="{{ asset('images/brand/money.png') }}" alt="" class="mb-4 h-16 w-16 object-contain">
            <p class="text-sm font-semibold text-emerald-400">2. Veja até quanto pagar</p>
            <p class="mt-2 text-sm text-slate-400">A IA lê as fotos, a monta e calcula o teto de lance visando lucro.</p>
        </li>
        <li class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <img src="{{ asset('images/logo.png') }}" alt="" class="mb-4 h-16 w-16 rounded-2xl object-contain">
            <p class="text-sm font-semibold text-emerald-400">3. Receba o alerta</p>
            <p class="mt-2 text-sm text-slate-400">Todo dia de manhã chega um e-mail com os carros da sua busca. Se você marcar interesse, avisamos 1 hora antes do leilão.</p>
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
