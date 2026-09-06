<div class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 sm:p-8">
    <h1 class="text-2xl font-bold">Entrar</h1>
    <p class="mt-1 text-sm text-slate-400">Use o e-mail e a senha do cadastro.</p>
    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="redirect" value="{{ $redirectTo }}">
        <div>
            <label class="mb-1 block text-sm text-slate-300">E-mail</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3"
            >
            @error('email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Senha</label>
            <input
                type="password"
                name="password"
                autocomplete="current-password"
                required
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3"
            >
        </div>
        <label class="flex items-center gap-3 text-sm text-slate-300">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 shrink-0 rounded border-slate-600 accent-emerald-500">
            <span class="min-w-0">Lembrar de mim</span>
        </label>
        <button type="submit" class="w-full rounded-lg bg-emerald-500 py-3 font-semibold text-slate-950 hover:bg-emerald-400">
            Entrar
        </button>
        <p class="text-center text-sm">
            <a href="{{ route('password.request') }}" class="text-emerald-400 hover:underline">Esqueci a senha</a>
        </p>
    </form>
    <p class="mt-6 text-center text-sm text-slate-400">
        Ainda não tem conta? <a href="{{ route('register') }}" class="text-emerald-400 hover:underline">Criar conta</a>
    </p>
</div>
