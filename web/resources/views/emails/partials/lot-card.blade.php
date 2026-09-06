@php
    $fonteLabel = $lot->fonte === 'palacio' ? 'Palácio' : 'Sodré';
    $photoUrl = $lot->emailPhotoUrl();
    $hasCover = $lot->coverPhotoUrl() !== null;
@endphp

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 18px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: #ffffff;">
<tr>
<td width="148" style="vertical-align: top; padding: 0; background-color: #0f172a;">
<a href="{{ $lot->shareUrl() }}" style="display: block; text-decoration: none;">
<img src="{{ $photoUrl }}" alt="{{ $lot->titulo }}" width="148" height="112" style="display: block; width: 148px; height: 112px; object-fit: cover; border: 0;{{ $hasCover ? '' : ' padding: 24px; box-sizing: border-box; object-fit: contain;' }}">
</a>
</td>
<td style="vertical-align: top; padding: 14px 16px;">
<p style="margin: 0 0 6px; font-size: 16px; font-weight: 700; line-height: 1.35; color: #0f172a;">
<a href="{{ $lot->shareUrl() }}" style="color: #0f172a; text-decoration: none;">{{ $lot->titulo }}</a>
</p>
<p style="margin: 0 0 8px; font-size: 14px; line-height: 1.4; color: #475569;">
{{ $lot->marca }} {{ $lot->modelo }}@if ($lot->ano_mod) · {{ $lot->ano_mod }}@endif
</p>
<p style="margin: 0; font-size: 13px; line-height: 1.4; color: #64748b;">
{{ $fonteLabel }} · {{ $lot->desconto_label ?: 'FIPE N/A' }}
@if (! empty($showAuctionWhen))
· {{ $lot->auctionWhenLabel() }}
@endif
</p>
<p style="margin: 10px 0 0; font-size: 13px; font-weight: 600;">
<a href="{{ $lot->shareUrl() }}" style="color: #059669; text-decoration: none;">Ver lote no catálogo →</a>
</p>
</td>
</tr>
</table>
