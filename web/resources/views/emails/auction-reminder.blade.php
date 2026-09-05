<x-mail::message>
# Leilão perto de começar

Olá, **{{ $user->name }}**. Os carros em que você marcou interesse estão prestes a ir a leilão.

@foreach ($lots as $lot)
- **{{ $lot->titulo }}** — {{ $lot->marca }} {{ $lot->modelo }} · {{ $lot->fonte === 'palacio' ? 'Palácio' : 'Sodré' }} · {{ $lot->auctionWhenLabel() }} · {{ $lot->desconto_label ?: 'FIPE N/A' }} · [abrir]({{ $lot->shareUrl() }})
@endforeach

<x-mail::button :url="$catalogUrl">
Ver ofertas no VerifyRadar
</x-mail::button>

<x-mail::button :url="$alertsUrl">
Ajustar preferências
</x-mail::button>

VerifyRadar — do grupo VerifyCar.

[Parar alertas por e-mail]({{ $unsubscribeUrl }})
</x-mail::message>
