<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#020617">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $metaDescription ?? 'Ofertas de leilão vs tabela FIPE. Alertas por e-mail com base nas suas preferências.' }}">
    <title>{{ $title ?? config('app.name', 'VerifyRadar') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/css/catalog.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 pb-[env(safe-area-inset-bottom)]">
    <nav class="sticky top-0 z-50 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur" x-data="{ open: false, account: false }" @keydown.escape.window="open = false; account = false">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:py-4">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3">
                <img src="{{ asset('images/logo.png') }}" alt="VerifyRadar" class="h-8 w-8 rounded-lg">
                <span>
                    <span class="block text-sm font-semibold text-white">Verify<span class="text-emerald-400">Radar</span></span>
                    <span class="block text-[11px] uppercase tracking-[0.16em] text-emerald-400">grupo VerifyCar</span>
                </span>
            </a>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg border border-slate-700 p-2 text-slate-300 hover:border-emerald-500 hover:text-emerald-400 md:hidden"
                @click="open = !open; account = false"
                :aria-expanded="open"
                aria-controls="mobile-nav"
                aria-label="Abrir menu"
            >
                <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <div class="hidden items-center gap-5 text-sm md:flex">
                @include('layouts.partials.nav-links', ['mobile' => false])
                @auth
                    <div class="relative" @click.outside="account = false">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-slate-100 hover:border-emerald-500"
                            @click="account = !account"
                            :aria-expanded="account"
                        >
                            <span>{{ explode(' ', auth()->user()->name)[0] }}</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div
                            x-show="account"
                            x-cloak
                            x-transition
                            class="absolute right-0 mt-2 w-52 rounded-xl border border-slate-800 bg-slate-950 p-2 shadow-xl"
                        >
                            @include('layouts.partials.nav-account', ['mobile' => false])
                        </div>
                    </div>
                @endauth
            </div>
        </div>

        <div
            x-show="open"
            x-cloak
            x-transition
            id="mobile-nav"
            class="border-t border-slate-800 bg-slate-950 px-4 py-3 md:hidden"
        >
            <div class="flex flex-col gap-1 text-base">
                @include('layouts.partials.nav-links', ['mobile' => true])
            </div>
        </div>
    </nav>

    <main @class(['mx-auto max-w-6xl px-4 py-6 sm:py-8' => empty($fullBleed)])>
        @if (session('success'))
            <div class="mx-auto mb-4 max-w-6xl rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-emerald-300">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mx-auto mb-4 max-w-6xl rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-red-300">
                {{ session('error') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-slate-800 bg-slate-950">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>VerifyRadar — do grupo VerifyCar. Ofertas vs tabela FIPE.</p>
            <p>radar.verifycar.com.br</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
