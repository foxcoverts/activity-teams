@props(['urls', 'prompt' => null])

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 16px 0;">
<tr>
<td align="center" style="background-color: #edf2f7; padding: 16px;">
<p style="margin: 0 0 12px; font-size: 18px; font-weight: bold; line-height: 1.4; text-align: center; color: #718096;">{{ $prompt ?? __('Can you attend this event?') }}</p>
<table cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
@foreach ($urls as $choice)
<td style="padding: 0 4px;">
<a href="{{ $choice['url'] }}" target="_blank" rel="noopener" style="background-color: {{ $choice['color'] }}; border-bottom: 8px solid {{ $choice['color'] }}; border-left: 18px solid {{ $choice['color'] }}; border-right: 18px solid {{ $choice['color'] }}; border-top: 8px solid {{ $choice['color'] }}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; text-decoration: none;">{{ $choice['label'] }}</a>
</td>
@endforeach
</tr>
</table>
</td>
</tr>
</table>
