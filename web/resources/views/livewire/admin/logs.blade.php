<div class="space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-amber-300">Admin</p>
        <h1 class="mt-2 text-2xl font-bold">Logs</h1>
        <p class="mt-1 text-sm text-slate-400">Aprovações, cadastros e as últimas linhas do log da aplicação.</p>
    </div>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Atividade</h2>
        <ul class="mt-4 space-y-2 text-sm">
            @forelse ($activity as $log)
                <li class="rounded-lg border border-slate-800 px-3 py-2">
                    <p class="text-slate-200">{{ $log->message }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $log->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
                        · {{ $log->action }}
                        @if ($log->actor)
                            · por {{ $log->actor->email }}
                        @endif
                        @if ($log->ip_address)
                            · {{ $log->ip_address }}
                        @endif
                    </p>
                </li>
            @empty
                <li class="text-slate-500">Nenhum evento registrado.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $activity->links() }}</div>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Log da aplicação</h2>
        @if ($appLogLines === [])
            <p class="mt-3 text-sm text-slate-500">Arquivo de log ainda vazio neste ambiente.</p>
        @else
            <pre class="mt-4 max-h-[28rem] overflow-auto rounded-lg bg-slate-950 p-3 text-xs leading-5 text-slate-400">{{ implode("\n", $appLogLines) }}</pre>
        @endif
    </section>
</div>
