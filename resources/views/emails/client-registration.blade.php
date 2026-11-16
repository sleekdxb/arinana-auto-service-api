@php
    $steps = [
        'Add your vehicle and its documents',
        'Choose a service and pick a time',
        'Track progress until your car is ready',
    ];
@endphp
@extends('emails.layout', [
    'title' => 'Welcome to Ariana Auto Service',
    'preheader' => 'Your Ariana Auto Service account is ready.',
    'footer' => 'account',
])

@section('body')
  @include('emails.partials.intro', [
      'eyebrow' => 'Welcome',
      'heading' => 'Your account is ready, ' . $data['first_name'],
      'message' => 'Thanks for joining Ariana Auto Service. You can now add your vehicles, book a service and follow every step of it in the app.',
  ])

  @include('emails.partials.rows', ['rows' => array_values(array_filter([
      ['Name', trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''))],
      ['Email', $data['email']],
      !empty($data['account_type']) ? ['Account type', ucfirst(strtolower($data['account_type']))] : null,
      !empty($data['client_id']) ? ['Client ID', $data['client_id']] : null,
  ]))])

  <div style="margin-top:20px;">
    <p style="margin:0;font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6E717A;">Get started</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
      @foreach($steps as $i => $step)
        <tr>
          <td valign="middle" style="padding-top:12px;">
            <div style="width:28px;height:28px;line-height:28px;border-radius:14px;background:#FDECEE;color:#C2101C;font-size:13px;font-weight:700;text-align:center;">{{ $i + 1 }}</div>
          </td>
          <td valign="middle" style="padding:12px 0 0 12px;font-size:14px;color:#34363C;">{{ $step }}</td>
        </tr>
      @endforeach
    </table>
  </div>

  @include('emails.partials.button', ['label' => 'Open the app', 'url' => config('mail.brand.app_url')])
@endsection
