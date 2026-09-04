<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Painel</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Olá, {{ $user->name }}</h1>
        <p class="mt-1 text-sm text-slate-400">
            Assinatura {{ $user->subscriptionLabel() }}
            @if ($user->subscription_until)
                · válida até {{ $user->subscription_until->timezone('America/Sao_Paulo')->format('d/m/Y') }}
            @endif
            · {{ $lotCount }} lotes no snapshot.
        </p>
    </div>

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
            <h2 class="text-lg font-semibold">Lotes que casam agora</h2>
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
