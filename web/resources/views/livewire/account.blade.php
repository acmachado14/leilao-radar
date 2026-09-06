<div class="mx-auto max-w-2xl space-y-8">
    <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Conta</p>
        <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Minha conta</h1>
        <p class="mt-1 text-sm text-slate-400">Plano {{ $user->planLabel() }} · assinatura {{ $user->subscriptionLabel() }}@if($user->subscription_until) até {{ $user->subscription_until->timezone('America/Sao_Paulo')->format('d/m/Y') }}@endif.</p>
    </div>

    <section class="rounded-2xl border border-violet-500/30 bg-slate-900 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Uso da IA</h2>
        <p class="mt-2 text-sm text-slate-400">Cada pedido de avaliação conta uma consulta. Pedir de novo o mesmo carro no mês não gasta outra.</p>
        <dl class="mt-4">
            <div class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-3">
                <dt class="text-xs uppercase tracking-widest text-slate-500">Consultas neste mês</dt>
                <dd class="mt-1 text-2xl font-bold">{{ $quota['unlimited'] ? 'Ilimitado' : $quota['used'].'/'.$quota['limit'] }}</dd>
                @unless ($quota['unlimited'])
                    <p class="mt-1 text-sm text-slate-500">{{ $quota['remaining'] }} restantes no seu plano.</p>
                @endunless
            </div>
        </dl>
        <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener" class="btn-emerald mt-4 inline-flex px-5 py-3">Falar com atendente</a>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Trocar de plano</h2>
        <p class="mt-1 text-sm text-slate-400">O botão abre o WhatsApp com a mensagem pronta. A equipe libera o plano no admin.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ($plans as $plan)
                <a href="{{ $plan['checkout_url'] }}" target="_blank" rel="noopener" class="rounded-xl border {{ $plan['key'] === $user->plan ? 'border-emerald-500/50' : 'border-slate-800' }} bg-slate-950 p-4 hover:border-emerald-500">
                    <p class="font-semibold">{{ $plan['name'] }}</p>
                    <p class="mt-1 text-emerald-400">{{ $plan['price'] }}</p>
                    <p class="mt-2 text-sm text-slate-400">{{ $plan['analyses_per_month'] }} análises · {{ $plan['alerts'] }} recortes</p>
                </a>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Dados pessoais</h2>
        <form wire:submit="updateProfile" class="mt-4 space-y-4">
            <div>
                <label class="mb-1 block text-sm text-slate-300">Nome</label>
                <input type="text" wire:model="name" autocomplete="name" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
                @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-300">E-mail</label>
                <p class="rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-3 text-slate-400">{{ $email }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-300">WhatsApp</label>
                <input type="tel" wire:model="phone" autocomplete="tel" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
                @error('phone') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-slate-950 hover:bg-emerald-400">
                Salvar dados
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">Alterar senha</h2>
        <form wire:submit="updatePassword" class="mt-4 space-y-4">
            <div>
                <label class="mb-1 block text-sm text-slate-300">Senha atual</label>
                <input type="password" wire:model="current_password" autocomplete="current-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
                @error('current_password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-300">Nova senha</label>
                <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
                @error('password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-300">Confirmar nova senha</label>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3">
            </div>
            <button type="submit" class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-slate-950 hover:bg-emerald-400">
                Alterar senha
            </button>
        </form>
    </section>
</div>
