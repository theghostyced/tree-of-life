<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;width:100%;background:#eff1ea;-webkit-font-smoothing:antialiased;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#20211b;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader ?? $body }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eff1ea;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:496px;margin:0 auto;">

                    {{-- Brand --}}
                    <tr>
                        <td style="padding:0 4px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    {{-- Embedded, not linked. Gmail and Outlook block remote images by
                                         default until the reader clicks "display images", which left the
                                         mark as an empty box. Attaching it to the message sidesteps that.
                                         The url() fallback covers rendering outside a mail context, where
                                         $message does not exist. --}}
                                    <td style="width:40px;height:40px;vertical-align:middle;">
                                        <img src="{{ isset($message) ? $message->embed(public_path('images/brand/tree-of-life.png')) : url('/images/brand/tree-of-life.png') }}" width="40" height="40" alt="{{ $appName }}" style="display:block;width:40px;height:40px;border:0;outline:none;text-decoration:none;">
                                    </td>
                                    <td style="padding-left:12px;font-size:16px;font-weight:600;letter-spacing:-0.01em;color:#20211b;vertical-align:middle;">{{ $appName }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background:#ffffff;border:1px solid #e5e7dc;border-radius:14px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">

                                @isset($illustrationUrl)
                                    <tr>
                                        <td style="padding:32px 36px 0;text-align:center;">
                                            <img src="{{ $illustrationUrl }}" alt="" width="120" style="width:120px;max-width:120px;height:auto;border:0;outline:none;text-decoration:none;">
                                        </td>
                                    </tr>
                                @endisset

                                <tr>
                                    <td style="padding:36px 36px 0;">
                                        @isset($greeting)
                                            <p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#484a3f;">{{ $greeting }}</p>
                                        @endisset
                                        <h1 style="margin:0;font-size:23px;line-height:1.3;font-weight:600;letter-spacing:-0.02em;color:#1b1c17;">
                                            {{ $title }}
                                        </h1>
                                        <p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#484a3f;">
                                            {{ $body }}
                                        </p>
                                    </td>
                                </tr>

                                @if(!empty($actionText) && !empty($actionUrl))
                                    <tr>
                                        <td style="padding:28px 36px 0;">
                                            <table role="presentation" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td style="background:#adb982;border-radius:9px;">
                                                        <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#14160f;text-decoration:none;letter-spacing:-0.01em;">
                                                            {{ $actionText }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    {{-- Plain link fallback --}}
                                    <tr>
                                        <td style="padding:22px 36px 36px;">
                                            <div style="border-top:1px solid #eff1ea;padding-top:16px;">
                                                <p style="margin:0;font-size:12px;line-height:1.5;color:#6b6d65;">
                                                    Or paste this address into your browser:
                                                </p>
                                                <p style="margin:4px 0 0;font-size:12px;line-height:1.5;word-break:break-all;">
                                                    <a href="{{ $actionUrl }}" style="color:#646b4a;text-decoration:underline;">{{ $actionUrl }}</a>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    <tr><td style="padding:0 0 36px;"></td></tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:22px 4px 0;text-align:center;">
                            <p style="margin:0;font-size:12px;color:#6b6d65;">&copy; {{ date('Y') }} {{ $appName }}</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
