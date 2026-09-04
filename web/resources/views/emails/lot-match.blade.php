<x-mail::message>
# Novos lotes no Radar

Olá, **{{ $user->name }}**. Encontramos ofertas que batem com as suas preferências.

@foreach ($lots as $lot)
- **{{ $lot->titulo }}** — {{ $lot->marca }} {{ $lot->modelo }} · {{ $lot->fonte === 'palacio' ? 'Palácio' : 'Sodré' }} · {{ $lot->desconto_label ?: 'FIPE N/A' }} · [abrir]({{ $lot->shareUrl() }})
@endforeach

<x-mail::button :url="$catalogUrl">
Ver ofertas no Radar
</x-mail::button>

<x-mail::button :url="$alertsUrl">
Ajustar preferências
</x-mail::button>

Leilão Radar — do grupo VerifyCar.

[Parar alertas por e-mail]({{ $unsubscribeUrl }})
</x-mail::message>
