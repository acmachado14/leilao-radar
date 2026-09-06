<x-mail::message>
# {{ $isTest ? 'Teste de alerta' : 'Novos lotes no VerifyRadar' }}

Olá, **{{ $user->name }}**. {{ $isTest ? 'Este é um e-mail de teste — o formato é o mesmo do resumo que chega todo dia de manhã.' : 'Encontramos ofertas que batem com as suas preferências.' }}

@foreach ($lots as $lot)
- **{{ $lot->titulo }}** — {{ $lot->marca }} {{ $lot->modelo }} · {{ $lot->fonte === 'palacio' ? 'Palácio' : 'Sodré' }} · {{ $lot->desconto_label ?: 'FIPE N/A' }} · [abrir]({{ $lot->shareUrl() }})
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
