<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Alertas</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Preferências</h1>
        <p class="mt-1 text-sm text-slate-400">
            Cadastre um recorte por modelo (ex.: Jetta GLI e Amarok). O digest (~05:30 BRT) junta os lotes de todas elas.
            {{ $preferences->count() }}/{{ $maxPreferences }} salvas.
        </p>
    </div>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Onde receber</h2>
        <form wire:submit="saveChannels" class="mt-4 space-y-3 text-sm">
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="notify_email" class="h-4 w-4 shrink-0 rounded accent-emerald-500">
                <span class="min-w-0">E-mail</span>
            </label>
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="notify_whatsapp" class="mt-1 h-4 w-4 shrink-0 rounded accent-emerald-500">
                <span class="min-w-0 flex-1">WhatsApp @unless($whatsappReady)<span class="text-slate-500">(canal desligado até a WABA/template)</span>@endunless</span>
            </label>
            @error('notify_whatsapp') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            <button type="submit" class="rounded-lg border border-slate-700 px-4 py-2 font-semibold text-slate-100 hover:border-emerald-500">Salvar canais</button>
        </form>
    </section>

    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Recortes salvos</h2>
            <button type="button" wire:click="createNew" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400">
                Nova preferência
            </button>
        </div>

        @if ($preferences->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-700 px-4 py-6 text-sm text-slate-400">Nenhum recorte ainda. Use o formulário abaixo — um por modelo.</p>
        @else
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($preferences as $preference)
                    <li @class([
                        'rounded-2xl border px-4 py-3',
                        'border-emerald-500 bg-emerald-500/10' => $editingId === $preference->id,
                        'border-slate-800 bg-slate-900' => $editingId !== $preference->id,
                    ])>
                        <p class="font-semibold">{{ $preference->label() }}</p>
                        <p class="mt-1 text-sm text-slate-400">
                            {{ $preference->search !== '' ? $preference->search : 'qualquer modelo' }}
                            · desconto ≥ {{ (int) round(((float) $preference->min_desconto) * 100) }}%
                        </p>
                        <div class="mt-3 flex gap-2">
                            <button type="button" wire:click="edit('{{ $preference->id }}')" class="text-sm text-emerald-400 hover:underline">Editar</button>
                            <button type="button" wire:click="delete('{{ $preference->id }}')" wire:confirm="Remover esta preferência?" class="text-sm text-slate-500 hover:text-red-400">Excluir</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <form wire:submit="save" class="grid gap-6 rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <h2 class="text-lg font-semibold">{{ $editingId ? 'Editar recorte' : 'Novo recorte' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Cada recorte é um modelo ou busca. Filtros (fonte, FIPE, monta) valem só para este recorte.</p>
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Nome (opcional)</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3" placeholder="Jetta GLI">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Busca (marca / modelo)</label>
            <input type="search" wire:model="search" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3" placeholder="Jetta GLI">
            @error('search') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
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
        <div class="lg:col-span-2">
            <button type="submit" class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-slate-950 hover:bg-emerald-400">
                {{ $editingId ? 'Salvar recorte' : 'Adicionar recorte' }}
            </button>
        </div>
    </form>

    @if ($preview->isNotEmpty())
        <section>
            <h2 class="mb-3 text-lg font-semibold">Prévia deste recorte</h2>
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
