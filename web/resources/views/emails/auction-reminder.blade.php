<x-mail::message>
# Leilão perto de começar

@include('emails.partials.intro', [
    'user' => $user,
    'intro' => 'Os carros em que você marcou interesse estão prestes a ir a leilão.',
])

@foreach ($lots as $lot)
@include('emails.partials.lot-card', ['lot' => $lot, 'showAuctionWhen' => true])
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
