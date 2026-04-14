@php
    /** @var array<int, array{label:string, value:string}> $rows */
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0; border-collapse:collapse;">
    @foreach ($rows as $row)
        <tr>
            <td style="padding:12px 0; color:#94a3b8; font-size:14px; border-bottom:1px solid rgba(255,255,255,0.06);">
                {{ $row['label'] }}
            </td>
            <td align="right" style="padding:12px 0; color:#ffffff; font-size:14px; font-weight:700; border-bottom:1px solid rgba(255,255,255,0.06);">
                {{ $row['value'] }}
            </td>
        </tr>
    @endforeach
</table>
