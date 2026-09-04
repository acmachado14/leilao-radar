<div class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 sm:p-8">
    <h1 class="text-2xl font-bold">Nova senha</h1>
    <p class="mt-1 text-sm text-slate-400">Escolha uma senha com pelo menos 8 caracteres.</p>
    <form wire:submit="resetPassword" class="mt-6 space-y-4">
        <div>
            <label class="mb-1 block text-sm text-slate-300">E-mail</label>
            <input type="email" wire:model="email" autocomplete="email" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
            @error('email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Nova senha</label>
            <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
            @error('password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Confirmar senha</label>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-500 py-3 font-semibold text-slate-950 hover:bg-emerald-400">
            Salvar senha
        </button>
    </form>
</div>
