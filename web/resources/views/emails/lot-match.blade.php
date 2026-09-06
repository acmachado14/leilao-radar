<x-mail::message>
# {{ $isTest ? 'Teste de alerta' : 'Novos lotes no VerifyRadar' }}

@include('emails.partials.intro', [
    'user' => $user,
    'intro' => $isTest
        ? 'Este é um e-mail de teste — o formato é o mesmo do resumo que chega todo dia de manhã.'
        : 'Encontramos ofertas que batem com as suas preferências.',
])

@foreach ($lots as $lot)
@include('emails.partials.lot-card', ['lot' => $lot])
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
