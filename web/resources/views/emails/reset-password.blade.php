<x-mail::message>
# Redefinir senha

Olá, **{{ $user->name }}**. Recebemos um pedido para redefinir a senha da sua conta no Leilão Radar.

O link vale por 60 minutos. Se você não pediu isso, ignore este e-mail.

<x-mail::button :url="$resetUrl">
Escolher nova senha
</x-mail::button>

Leilão Radar — do grupo VerifyCar.
</x-mail::message>
