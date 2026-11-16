<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;table-layout:fixed;">
  <tr>
    @foreach($steps as $step)
      @php
        $done = $step['state'] !== 'todo';
        $current = $step['state'] === 'current';
      @endphp
      <td valign="top" style="padding:16px 2px 4px;">
        <div style="height:6px;line-height:6px;font-size:0;border-radius:3px;background:{{ $done ? '#C2101C' : '#E4E5E9' }};">&nbsp;</div>
        <p style="margin:8px 0 0;text-align:center;font-size:12px;line-height:1.3;font-weight:{{ $current ? 700 : 500 }};color:{{ $current ? '#C2101C' : ($done ? '#34363C' : '#6E717A') }};">{{ $step['label'] }}</p>
      </td>
    @endforeach
  </tr>
</table>
