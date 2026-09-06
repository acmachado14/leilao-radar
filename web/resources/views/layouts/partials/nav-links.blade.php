<a href="{{ route('home') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Home</a>
<a href="{{ route('catalog') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Ofertas</a>
<a href="{{ route('home') }}#planos" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Planos</a>
<a href="{{ route('login') }}" @class(['hover:text-emerald-400' => ! $mobile, 'rounded-lg px-3 py-3 hover:bg-slate-800' => $mobile]) @if ($mobile) @click="open = false" @endif>Entrar</a>
<a href="{{ route('register') }}" @class(['btn-emerald px-4 py-2 text-sm' => ! $mobile, 'btn-emerald px-3 py-3' => $mobile]) @if ($mobile) @click="open = false" @endif>Testar IA</a>
