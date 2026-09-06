<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Painel</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Olá, {{ $user->name }}</h1>
        <p class="mt-1 text-sm text-slate-400">
            Plano {{ $user->planLabel() }} · assinatura {{ $user->subscriptionLabel() }}
            @if ($user->subscription_until)
                · válida até {{ $user->subscription_until->timezone('America/Sao_Paulo')->format('d/m/Y') }}
            @endif
            · {{ $lotCount }} lotes no snapshot.
        </p>
    </div>

    <section class="rounded-2xl border border-violet-500/30 bg-slate-900 p-4 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">IA do VerifyRadar</p>
        <h2 class="mt-2 text-lg font-semibold">Análises deste mês</h2>
        <p class="mt-2 text-3xl font-bold">
            @if ($quota['unlimited'])
                Ilimitado
            @else
                {{ $quota['used'] }}/{{ $quota['limit'] }}
            @endif
        </p>
        <p class="mt-1 text-sm text-slate-400">
            @if (! $quota['unlimited'])
                {{ $quota['remaining'] }} restantes
                ·
            @endif
            custo estimado da IA: R$ {{ number_format($quota['spent_brl_month'], 2, ',', '.') }} neste mês
            (R$ {{ number_format($quota['spent_brl'], 2, ',', '.') }} no total).
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('catalog') }}" class="btn-emerald px-4 py-2 text-sm">Avaliar um lote</a>
            <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener" class="rounded-lg border border-violet-400/40 px-4 py-2 text-sm text-violet-200 hover:border-violet-300">Falar com atendente</a>
        </div>
    </section>

    @if (! $user->canReceiveAlerts())
        <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-amber-200">
            Alertas pausados. Após o PIX, a equipe libera a assinatura no painel admin.
        </div>
    @endif

    @unless ($whatsappReady)
        <p class="text-sm text-slate-500">WhatsApp ainda não está ligado. O opt-in nas preferências fica salvo para quando a API oficial estiver aprovada.</p>
    @endunless

    <section>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Carros com interesse</h2>
            <a href="{{ route('catalog') }}" class="text-sm text-emerald-400 hover:underline">Ver ofertas</a>
        </div>
        @if ($interested->isEmpty())
            <p class="text-slate-400">Nenhum carro marcado ainda. Abra um lote e clique em “Tenho interesse” para receber o aviso de 1 hora antes.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($interested as $lot)
                    <a href="{{ $lot->shareUrl() }}" class="rounded-2xl border border-emerald-500/30 bg-slate-900 p-4 hover:border-emerald-500">
                        <p class="font-semibold">{{ $lot->titulo }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $lot->marca }} · {{ $lot->modelo }} · {{ $lot->fonte }}</p>
                        <p class="mt-2 text-sm text-emerald-400">{{ $lot->auctionWhenLabel() }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Lotes da sua faixa</h2>
            <a href="{{ route('alertas') }}" class="text-sm text-emerald-400 hover:underline">Editar filtros</a>
        </div>
        @if ($matches->isEmpty())
            <p class="text-slate-400">Nenhum lote no recorte atual. Ajuste as preferências ou aguarde a coleta da madrugada.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($matches as $lot)
                    <a href="{{ $lot->shareUrl() }}" class="rounded-2xl border border-slate-800 bg-slate-900 p-4 hover:border-emerald-500">
                        <p class="font-semibold">{{ $lot->titulo }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $lot->marca }} · {{ $lot->modelo }} · {{ $lot->fonte }}</p>
                        <p class="mt-2 text-sm text-emerald-400">{{ $lot->desconto_label ?: 'FIPE N/A' }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
