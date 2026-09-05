<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-amber-300">Admin</p>
        <h1 class="mt-2 text-2xl font-bold">Usuários</h1>
        <p class="text-sm text-slate-400">Aprove ou recuse cadastros. PIX fica fora do app.</p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar nome ou e-mail" class="w-full max-w-md rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
        <select wire:model.live="filter" class="rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm">
            <option value="all">Todos</option>
            <option value="pending">Pendentes</option>
            <option value="active">Ativos</option>
            <option value="paused">Pausados</option>
            <option value="expired">Expirados</option>
            <option value="blocked">Bloqueados</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-800">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-900 text-slate-400">
                <tr>
                    <th class="px-3 py-2">Usuário</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Até</th>
                    <th class="px-3 py-2">Login</th>
                    <th class="px-3 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $item)
                    <tr class="border-t border-slate-800 align-top">
                        <td class="px-3 py-3">
                            <p class="font-semibold">{{ $item->name }}</p>
                            <p class="text-slate-400">{{ $item->email }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $item->alert_preferences_count }} recortes
                                · cadastro {{ $item->created_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}
                            </p>
                        </td>
                        <td class="px-3 py-3">
                            {{ $item->subscriptionLabel() }}
                            @unless($item->active)
                                <span class="block text-red-400">bloqueado</span>
                            @endunless
                            @if ($item->isPending())
                                <span class="block text-amber-300">aguardando você</span>
                            @endif
                        </td>
                        <td class="px-3 py-3">{{ $item->subscription_until?->timezone('America/Sao_Paulo')->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-3 py-3 text-slate-400">{{ $item->last_login_at?->timezone('America/Sao_Paulo')->format('d/m H:i') ?: '—' }}</td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                @if ($item->isPending())
                                    <button type="button" wire:click="approve('{{ $item->id }}')" class="rounded border border-emerald-500/40 px-2 py-1 text-emerald-300">Aprovar 30d</button>
                                    <button type="button" wire:click="approveTrial('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1">Trial 7d</button>
                                    <button type="button" wire:click="reject('{{ $item->id }}')" wire:confirm="Recusar este cadastro?" class="rounded border border-red-500/40 px-2 py-1 text-red-300">Recusar</button>
                                @else
                                    <button type="button" wire:click="approve('{{ $item->id }}')" class="rounded border border-emerald-500/40 px-2 py-1 text-emerald-300">Renovar 30d</button>
                                    <button type="button" wire:click="pause('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1">Pausar</button>
                                    <button type="button" wire:click="expire('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1">Expirar</button>
                                    <button type="button" wire:click="toggleActive('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1">{{ $item->active ? 'Bloquear' : 'Liberar' }}</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-slate-500">Nenhum usuário neste filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
