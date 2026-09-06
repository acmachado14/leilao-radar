<a href="{{ route('home') }}" class="flex min-w-0 shrink-0 items-center gap-2.5 sm:gap-3" @if (! empty($onClick)) @click="{{ $onClick }}" @endif>
    <img src="{{ asset('images/logo.png') }}" alt="VerifyRadar" class="h-9 w-9 rounded-xl">
    <span class="min-w-0">
        <span class="block text-sm font-semibold leading-tight text-white">Verify<span class="text-emerald-400">Radar</span></span>
        <img src="{{ asset('images/brand/horizontal_branco0.png') }}" alt="VerifyCar" class="mt-0.5 h-3 w-auto sm:h-3.5">
    </span>
</a>
