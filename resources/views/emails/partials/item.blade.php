@php $assets = rtrim(config('mail.brand.assets_url') ?: url('images/email'), '/'); @endphp
<div style="margin-top:20px;border:1px solid #EFEFF2;border-radius:12px;padding:16px;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td valign="middle" width="44" style="width:44px;height:44px;border-radius:10px;background:{{ $icon === 'certificate' ? '#FDECEE' : '#F5F5F7' }};text-align:center;">
        <img src="{{ $assets }}/icon-{{ $icon }}.png" width="22" height="22" alt="" style="display:inline-block;border:0;width:22px;height:22px;vertical-align:middle;">
      </td>
      <td valign="middle" style="padding-left:14px;">
        <p style="margin:0;font-size:15px;font-weight:600;color:#0F1115;">{{ $title }}</p>
        @if(!empty($meta))
          <p style="margin:2px 0 0;font-size:13px;color:#6E717A;">{{ $meta }}</p>
        @endif
      </td>
    </tr>
  </table>
</div>
