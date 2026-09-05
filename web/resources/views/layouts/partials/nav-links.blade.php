@auth
    @if (auth()->user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}" @class(['text-amber-300 hover:text-amber-200' => ! $mobile, 'rounded-lg px-3 py-3 text-amber-300 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Admin</a>
        <a href="{{ route('admin.assinantes') }}" @class(['text-amber-300 hover:text-amber-200' => ! $mobile, 'rounded-lg px-3 py-3 text-amber-300 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Usuários</a>
        <a href="{{ route('admin.logs') }}" @class(['text-amber-300 hover:text-amber-200' => ! $mobile, 'rounded-lg px-3 py-3 text-amber-300 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Logs</a>
    @endif
    <a href="{{ route('catalog') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Ofertas</a>
    @if (auth()->user()->isPending())
        <a href="{{ route('aguardando') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Aguardando</a>
    @else
        <a href="{{ route('dashboard') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Painel</a>
        <a href="{{ route('alertas') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Alertas</a>
        <a href="{{ route('conta') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Minha conta</a>
    @endif
    <a href="{{ route('logout') }}" @class(['text-slate-400 hover:text-white' => ! $mobile, 'rounded-lg px-3 py-3 text-slate-400 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Sair</a>
@else
    <a href="{{ route('catalog') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Ofertas</a>
    <a href="{{ route('login') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Entrar</a>
    <a href="{{ route('register') }}" @class(['btn-emerald px-4 py-2 text-sm' => ! $mobile, 'btn-emerald px-3 py-3' => $mobile]) @if ($mobile) @click="open = false" @endif>Receber alertas</a>
@endauth
