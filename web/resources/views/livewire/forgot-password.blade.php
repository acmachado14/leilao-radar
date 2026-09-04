<div class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 sm:p-8">
    <h1 class="text-2xl font-bold">Esqueci a senha</h1>
    <p class="mt-1 text-sm text-slate-400">Informe o e-mail da conta. Se existir, enviamos um link para redefinir a senha.</p>

    @if ($sent)
        <div class="mt-6 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            Se este e-mail estiver cadastrado, você receberá o link em instantes.
        </div>
        <p class="mt-6 text-center text-sm text-slate-400">
            <a href="{{ route('login') }}" class="text-emerald-400 hover:underline">Voltar ao login</a>
        </p>
    @else
        <form wire:submit="sendResetLink" class="mt-6 space-y-4">
            <div>
                <label class="mb-1 block text-sm text-slate-300">E-mail</label>
                <input type="email" wire:model="email" autocomplete="email" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
                @error('email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full rounded-lg bg-emerald-500 py-3 font-semibold text-slate-950 hover:bg-emerald-400">
                Enviar link
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-400">
            Lembrou a senha? <a href="{{ route('login') }}" class="text-emerald-400 hover:underline">Entrar</a>
        </p>
    @endif
</div>
