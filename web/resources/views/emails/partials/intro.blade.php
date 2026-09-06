<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 20px;">
<tr>
<td width="52" style="vertical-align: top; padding-right: 12px;">
<img src="{{ \App\Support\EmailBranding::searchIconUrl() }}" alt="" width="44" height="44" style="display: block; border: 0;">
</td>
<td style="vertical-align: top;">
<p style="margin: 0; font-size: 16px; line-height: 1.5; color: #334155;">
Olá, <strong>{{ $user->name }}</strong>.
{{ $intro }}
</p>
</td>
</tr>
</table>
