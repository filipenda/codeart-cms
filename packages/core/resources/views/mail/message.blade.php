<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $palette['background'] }};color:{{ $palette['text'] }};font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $palette['background'] }};padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:{{ $palette['card'] }};border:1px solid {{ $palette['border'] }};border-radius:8px;">
                <tr>
                    <td style="height:6px;background:{{ $palette['primary'] }};border-radius:8px 8px 0 0;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="padding:28px 32px 8px 32px;">
                        <div style="font-size:14px;color:{{ $palette['muted'] }};">{{ $appName }}</div>
                        <h1 style="margin:8px 0 0 0;font-size:22px;line-height:1.3;color:{{ $palette['text'] }};">{{ $heading }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 32px 8px 32px;">
                        @foreach ($paragraphs as $paragraph)
                            <p style="margin:12px 0;">{{ $paragraph }}</p>
                        @endforeach
                    </td>
                </tr>
                @if ($actionUrl)
                    <tr>
                        <td style="padding:8px 32px 16px 32px;">
                            <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 24px;background:{{ $palette['primary'] }};color:{{ $palette['on_primary'] }};text-decoration:none;font-weight:bold;border-radius:6px;">{{ $actionText }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px 32px;font-size:13px;color:{{ $palette['muted'] }};">
                            <p style="margin:0 0 8px 0;">{{ $footer }}</p>
                            <p style="margin:0;word-break:break-all;"><a href="{{ $actionUrl }}" style="color:{{ $palette['link'] }};">{{ $actionUrl }}</a></p>
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
</body>
</html>
