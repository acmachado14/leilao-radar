@auth
    @if (auth()->user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}" @class(['text-amber-300 hover:text-amber-200' => ! $mobile, 'rounded-lg px-3 py-3 text-amber-300 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Admin</a>
        <a href="{{ route('admin.assinantes') }}" @class(['text-amber-300 hover:text-amber-200' => ! $mobile, 'rounded-lg px-3 py-3 text-amber-300 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Usuários</a>
        <a href="{{ route('admin.logs') }}" @class(['text-amber-300 hover:text-amber-200' => ! $mobile, 'rounded-lg px-3 py-3 text-amber-300 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Logs</a>
    @endif
    <a href="{{ route('catalog') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Ofertas</a>
    @if (auth()->user()->isPending())
        <a href="{{ route('aguardando') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Aguardando</a>
    @endif
    @if ($mobile)
        <p class="px-3 pt-3 text-xs font-semibold uppercase tracking-widest text-slate-500">Conta</p>
        @include('layouts.partials.nav-account', ['mobile' => true])
    @endif
@else
    <a href="{{ route('home') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Home</a>
    <a href="{{ route('catalog') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Ofertas</a>
    <a href="{{ route('home') }}#planos" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Planos</a>
    <a href="{{ route('login') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Entrar</a>
    <a href="{{ route('register') }}" @class(['btn-emerald px-4 py-2 text-sm' => ! $mobile, 'btn-emerald px-3 py-3' => $mobile]) @if ($mobile) @click="open = false" @endif>Testar IA</a>
@endauth
