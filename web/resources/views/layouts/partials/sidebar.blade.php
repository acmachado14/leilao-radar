@php
    $user = auth()->user();
    $item = function (bool $active) {
        return $active
            ? 'flex items-center gap-3 rounded-xl bg-emerald-500/15 px-3 py-2.5 text-sm font-medium text-emerald-300'
            : 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white';
    };
@endphp

<div class="flex h-full flex-col">
    <a href="{{ route('home') }}" class="flex items-center gap-3 px-4 py-5" @click="sidebar = false">
        <img src="{{ asset('images/logo.png') }}" alt="VerifyRadar" class="h-8 w-8 rounded-lg">
        <span>
            <span class="block text-sm font-semibold text-white">Verify<span class="text-emerald-400">Radar</span></span>
            <span class="block text-[11px] uppercase tracking-[0.16em] text-emerald-400">grupo VerifyCar</span>
        </span>
    </a>

    <div class="mx-4 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-3">
        <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
        <p class="mt-0.5 text-xs text-slate-400">{{ $user->planLabel() }} · {{ $user->subscriptionLabel() }}</p>
    </div>

    <nav class="mt-4 flex flex-1 flex-col gap-1 px-3">
        @if ($user->isAdmin())
            <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-widest text-amber-400/80">Admin</p>
            <a href="{{ route('admin.dashboard') }}" class="{{ $item(request()->routeIs('admin.dashboard')) }} text-amber-200" @click="sidebar = false">Admin</a>
            <a href="{{ route('admin.assinantes') }}" class="{{ $item(request()->routeIs('admin.assinantes')) }} text-amber-200" @click="sidebar = false">Usuários</a>
            <a href="{{ route('admin.logs') }}" class="{{ $item(request()->routeIs('admin.logs')) }} text-amber-200" @click="sidebar = false">Logs</a>
        @endif

        <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-widest text-slate-500">Radar</p>
        <a href="{{ route('catalog') }}" class="{{ $item(request()->routeIs('catalog')) }}" @click="sidebar = false">Ofertas</a>
        @if ($user->isPending())
            <a href="{{ route('aguardando') }}" class="{{ $item(request()->routeIs('aguardando')) }}" @click="sidebar = false">Aguardando</a>
        @endif

        <p class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-widest text-slate-500">Minha conta</p>
        <a href="{{ route('conta') }}" class="{{ $item(request()->routeIs('conta')) }}" @click="sidebar = false">Minha conta</a>
        <a href="{{ route('meus-lotes') }}" class="{{ $item(request()->routeIs('meus-lotes')) }}" @click="sidebar = false">Meus lotes</a>
        <a href="{{ route('alertas') }}" class="{{ $item(request()->routeIs('alertas')) }}" @click="sidebar = false">Meus alertas</a>
        <a href="{{ route('plano') }}" class="{{ $item(request()->routeIs('plano')) }}" @click="sidebar = false">Meu plano</a>
    </nav>

    <div class="border-t border-slate-800 p-3">
        <a
            href="{{ route('logout') }}"
            class="flex items-center justify-center rounded-xl border border-slate-700 px-3 py-2.5 text-sm font-medium text-slate-200 hover:border-red-500/50 hover:bg-red-500/10 hover:text-red-300"
            @click="sidebar = false"
        >
            Sair
        </a>
    </div>
</div>
