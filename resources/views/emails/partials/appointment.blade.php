<div style="margin-top:20px;background:#F5F5F7;border-radius:12px;padding:20px;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td valign="middle" width="64" style="width:64px;height:64px;border-radius:12px;background:#FFFFFF;text-align:center;">
        <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.08em;color:#C2101C;">{{ $month }}</p>
        <p style="margin:0;font-size:24px;line-height:1.1;font-weight:700;color:#0F1115;">{{ $day }}</p>
      </td>
      <td valign="middle" style="padding-left:16px;">
        <p style="margin:0;font-size:17px;font-weight:700;color:#0F1115;">{{ $when }}</p>
        @if(!empty($what))
          <p style="margin:2px 0 0;font-size:14px;color:#34363C;">{{ $what }}</p>
        @endif
        @if(!empty($where))
          <p style="margin:2px 0 0;font-size:13px;color:#6E717A;">{{ $where }}</p>
        @endif
      </td>
    </tr>
  </table>
</div>
