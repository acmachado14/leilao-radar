<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-violet-300">Meu plano</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Radar Pro é o plano para quem vive de leilão.</h1>
        <p class="mt-2 max-w-2xl text-slate-400">Você está no {{ $user->planLabel() }}. O Pro libera 80 análises de IA por mês, 12 recortes de alerta e o teto de lance em quase todo lote sério.</p>
    </div>

    <section class="rounded-2xl border border-violet-500/30 bg-slate-900 p-4 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Uso deste mês</p>
        <p class="mt-2 text-3xl font-bold">
            @if ($quota['unlimited'])
                Ilimitado
            @else
                {{ $quota['used'] }}/{{ $quota['limit'] }} análises
            @endif
        </p>
        @unless ($quota['unlimited'])
            <p class="mt-1 text-sm text-slate-400">{{ $quota['remaining'] }} restantes neste mês.</p>
        @endunless
        <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener" class="btn-emerald mt-4 inline-flex px-5 py-3">Falar com atendente</a>
    </section>

    <article class="plan-card plan-card-featured max-w-xl">
        <p class="plan-badge">Recomendado</p>
        <h2 class="plan-name">{{ $pro['name'] ?? 'Radar Pro' }}</h2>
        <p class="plan-price">{{ $pro['price'] ?? 'R$ 97/mês' }}</p>
        <p class="plan-tagline">{{ $pro['tagline'] ?? '' }}</p>
        <ul class="plan-features">
            @foreach ($pro['features'] ?? [] as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        </ul>
        <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener" class="btn-emerald w-full px-4 py-3">Falar com atendente</a>
        <p class="plan-note">Abre o WhatsApp com a mensagem do Radar Pro.</p>
    </article>

    <section>
        <h2 class="mb-4 text-lg font-semibold">Comparar planos</h2>
        @include('partials.plans', ['plans' => $plans])
    </section>
</div>
