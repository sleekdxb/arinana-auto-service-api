@php
    $alert = ($chipTone ?? 'alert') === 'alert';
    $body = trim((string) ($message ?? ''));
    $inline = !empty($name) && empty($greetBlock);
@endphp
@if(!empty($chip))
  <span style="display:inline-block;background:{{ $alert ? '#FDECEE' : '#F5F5F7' }};color:{{ $alert ? '#9A0A15' : '#34363C' }};font-size:12px;font-weight:600;padding:5px 10px;border-radius:999px;">{{ $chip }}</span>
@elseif(!empty($eyebrow))
  <p style="margin:0;font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:{{ $accent ?? '#C2101C' }};">{{ $eyebrow }}</p>
@endif
<h1 class="ar-h1" style="margin:10px 0 0;font-size:26px;line-height:1.25;font-weight:700;color:#0F1115;">{{ $heading }}</h1>
@if($inline)
  <p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#34363C;">Hi {{ $name }}, {{ $body !== '' ? lcfirst($body) : '' }}</p>
@elseif($body !== '' && empty($greetBlock))
  <p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#34363C;">{{ $body }}</p>
@endif
