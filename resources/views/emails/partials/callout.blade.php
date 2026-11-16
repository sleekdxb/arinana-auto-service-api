@php $alert = ($tone ?? 'neutral') === 'alert'; @endphp
<div style="margin-top:20px;background:{{ $alert ? '#FDECEE' : '#FAFAFB' }};{{ $alert ? '' : 'border:1px solid #EFEFF2;' }}border-radius:12px;padding:16px 20px;">
  @if(!empty($label))
    <p style="margin:0 0 4px;font-size:12px;color:#6E717A;">{{ $label }}</p>
  @endif
  @if(!empty($title))
    <p style="margin:0 0 4px;font-size:14px;font-weight:600;color:{{ $alert ? '#9A0A15' : '#0F1115' }};">{{ $title }}</p>
  @endif
  <p style="margin:0;font-size:14px;line-height:1.55;color:{{ !empty($label) ? '#0F1115' : '#34363C' }};">{!! $html ?? nl2br(e($text ?? '')) !!}</p>
</div>
