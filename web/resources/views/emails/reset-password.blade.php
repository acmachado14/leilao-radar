<x-mail::message>
# Redefinir senha

Olá, **{{ $user->name }}**. Recebemos um pedido para redefinir a senha da sua conta no VerifyRadar.

O link vale por 60 minutos. Se você não pediu isso, ignore este e-mail.

<x-mail::button :url="$resetUrl">
Escolher nova senha
</x-mail::button>

VerifyRadar — do grupo VerifyCar.
</x-mail::message>
