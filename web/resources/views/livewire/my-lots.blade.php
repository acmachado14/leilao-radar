<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Meus lotes</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Carros que você está acompanhando</h1>
        <p class="mt-1 text-sm text-slate-400">Interesse gera o aviso de 1 hora antes. Avaliação de IA guarda o teto de lance daquele lote.</p>
    </div>

    <section>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Tenho interesse</h2>
            <a href="{{ route('catalog') }}" class="text-sm text-emerald-400 hover:underline">Ver ofertas</a>
        </div>
        @if ($interested->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-700 px-4 py-6 text-slate-400">Nenhum carro marcado ainda. Abra um lote em ofertas e clique em “Tenho interesse”.</p>
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
        <h2 class="mb-4 text-lg font-semibold">Avaliados pela IA</h2>
        @if ($evaluated->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-700 px-4 py-6 text-slate-400">Nenhuma análise ainda. Abra um lote e clique em “Avaliar com IA”.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($evaluated as $lot)
                    <a href="{{ $lot->shareUrl() }}" class="rounded-2xl border border-violet-500/30 bg-slate-900 p-4 hover:border-violet-400">
                        <p class="font-semibold">{{ $lot->titulo }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $lot->marca }} · {{ $lot->modelo }} · {{ $lot->fonte }}</p>
                        <p class="mt-2 text-sm text-violet-300">Ver parecer de IA</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
