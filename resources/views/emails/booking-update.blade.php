@php
    $d = $details;
    $kind = $d['footer'] ?? 'account';
    $accent = $kind === 'staff' ? '#0F1115' : '#C2101C';
    $greetBlock = ($d['greet'] ?? null) === 'block';
@endphp
@extends('emails.layout', [
    'title' => $subjectLine,
    'preheader' => $d['preheader'] ?? ($d['message'] ?? null),
    'footer' => $kind,
])

@section('body')
  @include('emails.partials.intro', [
      'chip' => $d['status'] ?? null,
      'chipTone' => $d['status_tone'] ?? 'alert',
      'eyebrow' => $d['eyebrow'] ?? null,
      'heading' => $d['heading'],
      'name' => $d['name'] ?? null,
      'message' => $d['message'] ?? null,
      'greetBlock' => $greetBlock,
      'accent' => '#C2101C',
  ])

  @if($greetBlock)
    <div style="margin-top:20px;font-size:15px;line-height:1.6;color:#34363C;">
      <p style="margin:0;">Hi {{ $d['name'] }},</p>
      <p style="margin:12px 0 0;">{!! nl2br(e($d['message'] ?? '')) !!}</p>
      <p style="margin:12px 0 0;">Ariana Auto Service</p>
    </div>
  @endif

  @if(!empty($d['appointment']))
    @include('emails.partials.appointment', $d['appointment'])
  @endif

  @if(!empty($d['progress']))
    @include('emails.partials.progress', ['steps' => $d['progress']])
  @endif

  @if(!empty($d['item']))
    @include('emails.partials.item', $d['item'])
  @endif

  @if(!empty($d['compare']))
    @include('emails.partials.compare', $d['compare'])
  @endif

  @if(!empty($d['note']) && empty($d['note_last']))
    @include('emails.partials.callout', ['label' => 'Note from our team', 'text' => $d['note']])
  @endif

  @if(!empty($d['rows']))
    @include('emails.partials.rows', ['rows' => $d['rows']])
  @endif

  @if(!empty($d['note']) && !empty($d['note_last']))
    @include('emails.partials.callout', ['label' => 'Note from our team', 'text' => $d['note']])
  @endif

  @if(!empty($d['quote']))
    <div style="margin-top:20px;background:#F5F5F7;border-radius:12px;padding:14px 16px;">
      <p style="margin:0;font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6E717A;">{{ $d['quote'][0] }}</p>
      <p style="margin:6px 0 0;font-size:14px;line-height:1.55;color:#34363C;">{!! nl2br(e($d['quote'][1])) !!}</p>
    </div>
  @endif

  @foreach($d['callouts'] ?? [] as $callout)
    @include('emails.partials.callout', $callout)
  @endforeach

  @if(!empty($d['closing']))
    <p style="margin:20px 0 0;font-size:15px;line-height:1.6;color:#34363C;">{{ $d['closing'] }}</p>
  @endif

  @if(!empty($d['button']))
    @include('emails.partials.button', ['label' => $d['button'][0], 'url' => $d['button'][1], 'accent' => $accent])
  @endif
@endsection
