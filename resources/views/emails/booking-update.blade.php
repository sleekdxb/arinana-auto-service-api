<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f1f1f;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="background:#C2101C;padding:20px 28px;">
              <span style="font-size:18px;font-weight:bold;color:#ffffff;letter-spacing:0.3px;">Ariana Auto Service</span>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 8px;">
              <p style="margin:0 0 16px;font-size:15px;color:#3d3d3d;">Hello {{ $details['name'] }},</p>
              @if(!empty($details['status']))
                <span style="display:inline-block;background:#fde7e9;color:#C2101C;font-size:12px;font-weight:bold;padding:5px 10px;border-radius:6px;margin-bottom:12px;">{{ $details['status'] }}</span>
              @endif
              <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#111111;">{{ $details['heading'] }}</h1>
              @if(!empty($details['message']))
                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#3d3d3d;">{{ $details['message'] }}</p>
              @endif
            </td>
          </tr>
          @if(!empty($details['note']))
            <tr>
              <td style="padding:0 28px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e4e7;border-radius:8px;">
                  <tr>
                    <td style="padding:14px 16px;">
                      <p style="margin:0 0 4px;font-size:12px;color:#757575;">Note from our team</p>
                      <p style="margin:0;font-size:14px;line-height:1.5;color:#1f1f1f;">{{ $details['note'] }}</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          @endif
          @if(!empty($details['rows']))
            <tr>
              <td style="padding:0 28px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f7;border-radius:8px;">
                  @foreach($details['rows'] as [$label, $value])
                    <tr>
                      <td style="padding:10px 16px;font-size:13px;color:#757575;width:40%;vertical-align:top;">{{ $label }}</td>
                      <td style="padding:10px 16px;font-size:14px;color:#111111;font-weight:bold;vertical-align:top;">{{ $value }}</td>
                    </tr>
                  @endforeach
                </table>
              </td>
            </tr>
          @endif
          <tr>
            <td style="padding:0 28px 28px;">
              <p style="margin:0;font-size:14px;line-height:1.6;color:#3d3d3d;">{{ $details['footer'] ?? 'Open the Ariana Auto Service app to see the full timeline of your booking.' }}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 28px;border-top:1px solid #f0efef;">
              <p style="margin:0 0 4px;font-size:12px;color:#757575;">Questions? Email us at <a href="mailto:support@arianaautoservice.com" style="color:#C2101C;text-decoration:none;">support@arianaautoservice.com</a>.</p>
              <p style="margin:0;font-size:12px;color:#757575;">You are receiving this because email updates are on in your app's notification settings.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
