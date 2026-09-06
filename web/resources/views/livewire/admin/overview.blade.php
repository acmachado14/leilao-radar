<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-amber-300">Admin</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Painel</h1>
        <p class="mt-1 text-sm text-slate-400">Usuários ativos, fila de aprovação e atividade recente.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('admin.assinantes') }}?filtro=pending" class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">Aguardando</p>
            <p class="mt-2 text-3xl font-bold">{{ $stats['pending'] }}</p>
        </a>
        <a href="{{ route('admin.assinantes') }}?filtro=active" class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-400">Ativos</p>
            <p class="mt-2 text-3xl font-bold">{{ $stats['active'] }}</p>
        </a>
        <a href="{{ route('admin.assinantes') }}?filtro=paused" class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Pausados</p>
            <p class="mt-2 text-3xl font-bold">{{ $stats['paused'] }}</p>
        </a>
        <a href="{{ route('admin.assinantes') }}?filtro=expired" class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Expirados</p>
            <p class="mt-2 text-3xl font-bold">{{ $stats['expired'] }}</p>
        </a>
        <a href="{{ route('admin.assinantes') }}?filtro=blocked" class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-red-400">Bloqueados</p>
            <p class="mt-2 text-3xl font-bold">{{ $stats['blocked'] }}</p>
        </a>
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Lotes no snapshot</p>
            <p class="mt-2 text-3xl font-bold">{{ $stats['lots'] }}</p>
        </div>
        <div class="rounded-2xl border border-violet-500/30 bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">IA no mês</p>
            <p class="mt-2 text-3xl font-bold">{{ $stats['ai_month'] }}</p>
            <p class="mt-1 text-xs text-slate-500">R$ {{ number_format($stats['ai_cost_month'], 2, ',', '.') }} estimado</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.assinantes') }}" class="btn-emerald px-4 py-2 text-sm">Usuários</a>
        <a href="{{ route('admin.logs') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm hover:border-emerald-500">Logs</a>
    </div>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Fila de aprovação</h2>
            <a href="{{ route('admin.assinantes') }}?filtro=pending" class="text-sm text-emerald-400 hover:underline">Ver todos</a>
        </div>
        @if ($pendingUsers->isEmpty())
            <p class="text-sm text-slate-500">Nenhum cadastro pendente.</p>
        @else
            <ul class="space-y-3">
                @foreach ($pendingUsers as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 px-3 py-3">
                        <div>
                            <p class="font-semibold">{{ $item->name }}</p>
                            <p class="text-sm text-slate-400">{{ $item->email }} · {{ $item->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="approve('{{ $item->id }}')" class="rounded border border-emerald-500/40 px-2 py-1 text-sm text-emerald-300">Aprovar 30d</button>
                            <button type="button" wire:click="approveTrial('{{ $item->id }}')" class="rounded border border-slate-600 px-2 py-1 text-sm">Trial 7d</button>
                            <button type="button" wire:confirm="Recusar este cadastro?" wire:click="reject('{{ $item->id }}')" class="rounded border border-red-500/40 px-2 py-1 text-sm text-red-300">Recusar</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Atividade recente</h2>
            <a href="{{ route('admin.logs') }}" class="text-sm text-emerald-400 hover:underline">Logs completos</a>
        </div>
        @if ($recentLogs->isEmpty())
            <p class="text-sm text-slate-500">Nenhum evento ainda.</p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach ($recentLogs as $log)
                    <li class="rounded-lg border border-slate-800 px-3 py-2 text-slate-300">
                        <span class="text-slate-500">{{ $log->created_at->timezone('America/Sao_Paulo')->format('d/m H:i') }}</span>
                        · {{ $log->message }}
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
