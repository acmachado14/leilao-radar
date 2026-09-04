<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-amber-300">Admin</p>
        <h1 class="mt-2 text-2xl font-bold">Assinantes</h1>
        <p class="text-sm text-slate-400">Ative após o PIX. Sem checkout no app.</p>
    </div>

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar nome ou e-mail" class="w-full max-w-md rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">

    <div class="overflow-x-auto rounded-2xl border border-slate-800">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-900 text-slate-400">
                <tr>
                    <th class="px-3 py-2">Nome</th>
                    <th class="px-3 py-2">E-mail</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Até</th>
                    <th class="px-3 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $item)
                    <tr class="border-t border-slate-800">
                        <td class="px-3 py-3">{{ $item->name }}</td>
                        <td class="px-3 py-3 text-slate-400">{{ $item->email }}</td>
                        <td class="px-3 py-3">{{ $item->subscriptionLabel() }} @unless($item->active)<span class="text-red-400">bloqueado</span>@endunless</td>
                        <td class="px-3 py-3">{{ $item->subscription_until?->timezone('America/Sao_Paulo')->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="activate('{{ $item->id }}')" class="rounded border border-emerald-500/40 px-2 py-1 text-emerald-300">Ativar 30d</button>
                                <button type="button" wire:click="pause('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1">Pausar</button>
                                <button type="button" wire:click="expire('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1">Expirar</button>
                                <button type="button" wire:click="toggleActive('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1">{{ $item->active ? 'Bloquear' : 'Liberar' }}</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-slate-500">Nenhum usuário.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
