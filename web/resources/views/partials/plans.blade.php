<div class="plan-grid">
    @foreach ($plans as $plan)
        <article @class(['plan-card', 'plan-card-featured' => $plan['highlight'] || ($plan['key'] ?? '') === 'radar_pro'])>
            @if (($plan['highlight'] ?? false) || ($plan['key'] ?? '') === 'radar_pro')
                <p class="plan-badge">Mais escolhido</p>
            @endif
            <h3 class="plan-name">{{ $plan['name'] }}</h3>
            <p class="plan-price">{{ $plan['price'] }}</p>
            <p class="plan-tagline">{{ $plan['tagline'] }}</p>
            <ul class="plan-features">
                @foreach ($plan['features'] as $feature)
                    <li>{{ $feature }}</li>
                @endforeach
            </ul>
            <a href="{{ $plan['checkout_url'] }}" target="_blank" rel="noopener" class="{{ ($plan['highlight'] ?? false) || ($plan['key'] ?? '') === 'radar_pro' ? 'btn-emerald' : 'plan-cta' }} w-full px-4 py-3">
                {{ $plan['cta'] }}
            </a>
            <p class="plan-note">{{ $plan['price_note'] }}</p>
        </article>
    @endforeach
</div>
