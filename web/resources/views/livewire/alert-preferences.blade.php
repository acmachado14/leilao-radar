<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Alertas</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Preferências</h1>
        <p class="mt-1 text-sm text-slate-400">O digest diário (~05:30 BRT) usa estes filtros. Prévia: {{ $preview->count() }} lotes hoje.</p>
    </div>

    <form wire:submit="save" class="grid gap-6 rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <label class="mb-1 block text-sm text-slate-300">Busca (marca / modelo)</label>
            <input type="search" wire:model="search" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3" placeholder="Jetta GLI">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Fontes</label>
            <div class="space-y-2 text-sm">
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="fontes" value="sodre" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Sodré</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="fontes" value="palacio" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Palácio</span></label>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Match FIPE</label>
            <div class="space-y-2 text-sm">
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="fipe_matches" value="exact" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Exato</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="fipe_matches" value="closest" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Mais próximo</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="fipe_matches" value="failed" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Sem match</span></label>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Classificação</label>
            <div class="space-y-2 text-sm">
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="monta" value="sem_sinistro" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Sem sinistro</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="monta" value="pequena" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Pequena</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="monta" value="media" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Média</span></label>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="monta" value="outro" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">Outro</span></label>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Marcas (vazio = todas)</label>
            <select wire:model="marcas" multiple class="h-40 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                @foreach ($marcasDisponiveis as $marca)
                    <option value="{{ $marca }}">{{ $marca }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Desconto mínimo: {{ $min_desconto }}%</label>
            <input type="range" min="-50" max="80" wire:model.live="min_desconto" class="w-full">
            <label class="mt-4 flex items-center gap-3 text-sm text-slate-300">
                <input type="checkbox" wire:model="exclude_grande" class="h-4 w-4 shrink-0 rounded accent-emerald-500">
                <span class="min-w-0">Excluir grande monta</span>
            </label>
            <label class="mt-4 block text-sm text-slate-300">Prazo máximo (dias)</label>
            <input type="number" min="1" max="60" wire:model="max_days_until" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
        </div>
        <div class="space-y-3 text-sm lg:col-span-2">
            <label class="flex items-center gap-3"><input type="checkbox" wire:model="notify_email" class="h-4 w-4 shrink-0 rounded accent-emerald-500"> <span class="min-w-0">E-mail</span></label>
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="notify_whatsapp" class="mt-1 h-4 w-4 shrink-0 rounded accent-emerald-500">
                <span class="min-w-0 flex-1">WhatsApp @unless($whatsappReady)<span class="text-slate-500">(canal desligado até a WABA/template)</span>@endunless</span>
            </label>
            @error('notify_whatsapp') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div class="lg:col-span-2">
            <button type="submit" class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-slate-950 hover:bg-emerald-400">Salvar preferências</button>
        </div>
    </form>

    @if ($preview->isNotEmpty())
        <section>
            <h2 class="mb-3 text-lg font-semibold">Prévia</h2>
            <ul class="space-y-2 text-sm text-slate-300">
                @foreach ($preview->take(8) as $lot)
                    <li class="rounded-lg border border-slate-800 px-3 py-2">
                        <a href="{{ $lot->shareUrl() }}" class="text-emerald-400 hover:underline">{{ $lot->titulo }}</a>
                        · {{ $lot->fonte }} · {{ $lot->desconto_label ?: 'FIPE N/A' }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
