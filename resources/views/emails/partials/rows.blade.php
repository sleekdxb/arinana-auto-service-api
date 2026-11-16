<div style="margin-top:20px;border:1px solid #EFEFF2;border-radius:12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    @foreach($rows as $i => $row)
      @php
        [$label, $value] = $row;
        $style = $row[2] ?? null;
        $divider = $i < count($rows) - 1 ? 'border-bottom:1px solid #EFEFF2;' : '';
      @endphp
      @if($style === 'stack')
        <tr>
          <td colspan="2" style="padding:12px 16px;{{ $divider }}">
            <p style="margin:0 0 6px;font-size:13px;color:#6E717A;">{{ $label }}</p>
            <p style="margin:0;font-size:14px;line-height:1.55;color:#34363C;">{!! nl2br(e($value)) !!}</p>
          </td>
        </tr>
      @else
        <tr>
          <td valign="top" style="padding:12px 16px;font-size:13px;color:#6E717A;{{ $divider }}">{{ $label }}</td>
          <td valign="top" align="right" style="padding:12px 16px;font-size:14px;font-weight:600;color:#0F1115;text-align:right;{{ $divider }}">
            @if($style === 'mono')
              <span style="display:inline-block;font-family:'SFMono-Regular',Menlo,Consolas,monospace;font-size:15px;font-weight:700;letter-spacing:0.04em;background:#F5F5F7;padding:4px 10px;border-radius:6px;">{{ $value }}</span>
            @else
              {{ $value }}
            @endif
          </td>
        </tr>
      @endif
    @endforeach
  </table>
</div>
