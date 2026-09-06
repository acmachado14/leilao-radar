<div class="mx-auto max-w-lg rounded-2xl border border-slate-800 bg-slate-900 p-6 sm:p-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-300">Cadastro recebido</p>
    <h1 class="mt-2 text-2xl font-bold">Aguardando aprovação</h1>
    <p class="mt-3 text-slate-400">
        Olá, {{ $user->name }}. Sua conta <span class="text-slate-200">{{ $user->email }}</span> ainda não foi liberada.
        Depois do PIX, a equipe aprova o acesso. Aí você configura o que procura e o e-mail da manhã começa a chegar.
    </p>
    <p class="mt-4 text-sm text-slate-500">Enquanto isso o catálogo público continua disponível.</p>
    <a href="{{ route('catalog') }}" class="btn-emerald mt-6 px-5 py-3">Ver ofertas</a>
</div>
