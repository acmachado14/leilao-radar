<div class="mx-auto max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 sm:p-8">
    <h1 class="text-2xl font-bold">Criar conta</h1>
    <p class="mt-1 text-sm text-slate-400">Cadastro gratuito. Depois do PIX, a equipe aprova a conta e os alertas passam a chegar no e-mail.</p>
    <form wire:submit="register" class="mt-6 space-y-4">
        <div>
            <label class="mb-1 block text-sm text-slate-300">Nome</label>
            <input type="text" wire:model="name" autocomplete="name" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
            @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">E-mail</label>
            <input type="email" wire:model="email" autocomplete="email" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
            @error('email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">WhatsApp (opcional)</label>
            <input type="tel" wire:model="phone" autocomplete="tel" placeholder="11999999999" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
            @error('phone') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Senha</label>
            <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
            @error('password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-300">Confirmar senha</label>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
        </div>
        <label class="flex items-start gap-3 text-sm leading-relaxed text-slate-300">
            <input type="checkbox" wire:model="notify_whatsapp" class="mt-1 h-4 w-4 shrink-0 rounded border-slate-600 bg-slate-950 accent-emerald-500">
            <span class="min-w-0 flex-1">
                Quero alertas no WhatsApp quando o canal estiver ativo.
                @unless (config('radar.whatsapp.enabled'))
                    <span class="mt-1 block text-slate-500">Ainda não enviamos — o opt-in fica salvo.</span>
                @endunless
            </span>
        </label>
        <label class="flex items-start gap-3 text-sm leading-relaxed text-slate-300">
            <input type="checkbox" wire:model="terms_accepted" class="mt-1 h-4 w-4 shrink-0 rounded border-slate-600 bg-slate-950 accent-emerald-500">
            <span class="min-w-0 flex-1">Aceito receber alertas de leilão com base nas minhas preferências (LGPD).</span>
        </label>
        @error('terms_accepted') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        <button type="submit" class="w-full rounded-lg bg-emerald-500 py-3 font-semibold text-slate-950 hover:bg-emerald-400">
            Criar conta
        </button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-400">
        Já tem conta? <a href="{{ route('login') }}" class="text-emerald-400 hover:underline">Entrar</a>
    </p>
</div>
