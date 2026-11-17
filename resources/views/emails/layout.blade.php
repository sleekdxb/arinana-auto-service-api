@php
    $brand = config('mail.brand');
    $assets = rtrim($brand['assets_url'] ?: url('images/email'), '/');
    $staff = ($footer ?? null) === 'staff';
    $accent = $staff ? '#0F1115' : '#C2101C';
    $support = e($brand['support_address']);
    $supportLink = '<a href="mailto:' . $support . '" style="color:#C2101C;text-decoration:underline;">' . $support . '</a>';
    $footerLines = match ($footer ?? 'account') {
        'booking' => [
            'Questions? Email ' . $supportLink . ' or go to Profile › Help in the app.',
            "You're getting this because email updates are on in your notification settings.",
        ],
        'contact' => [
            'Need to add something? Email us at ' . $supportLink . '.',
            "You're getting this because you sent us a message on our website.",
        ],
        'final' => [
            'Questions? Email ' . $supportLink . '.',
            "This is the last email you'll get from us about this account.",
        ],
        'staff' => [
            "If you didn't expect this email, please contact your administrator.",
        ],
        default => [
            'Questions? Email ' . $supportLink . ' or go to Profile › Help in the app.',
            'Ariana Auto Service will never ask for your password or a verification code by phone, WhatsApp or chat.',
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>{{ $title ?? 'Ariana Auto Service' }}</title>
  <style>
    @media (max-width: 520px) {
      .ar-pad { padding-left: 20px !important; padding-right: 20px !important; }
      .ar-outer { padding: 16px 8px !important; }
      .ar-h1 { font-size: 22px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#F5F5F7;-webkit-text-size-adjust:100%;">
  @if(!empty($preheader))
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ $preheader }}</div>
  @endif
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F5F5F7;">
    <tr>
      <td align="center" class="ar-outer" style="padding:40px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#FFFFFF;border-radius:16px;border-top:4px solid {{ $accent }};font-family:Inter,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0F1115;">
          <tr>
            <td class="ar-pad" style="padding:28px 40px 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td><img src="{{ $assets }}/ariana-logo.png" width="130" height="44" alt="Ariana Auto Service" style="display:block;border:0;outline:none;width:130px;height:44px;"></td>
                  @if($staff)
                    <td align="right"><span style="display:inline-block;background:#0F1115;color:#FFFFFF;font-size:12px;font-weight:600;padding:5px 10px;border-radius:999px;">Staff</span></td>
                  @endif
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td class="ar-pad" style="padding:24px 40px 36px;">
              @yield('body')
            </td>
          </tr>
          <tr>
            <td class="ar-pad" style="padding:24px 40px 28px;background:#FAFAFB;border-top:1px solid #EFEFF2;border-radius:0 0 16px 16px;font-size:12px;line-height:1.6;color:#6E717A;">
              @foreach($footerLines as $line)
                <p style="margin:0 0 8px;">{!! $line !!}</p>
              @endforeach
              <p style="margin:0;">&copy; {{ date('Y') }} Ariana Auto Service{{ $staff ? ' · Internal' : '' }}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
