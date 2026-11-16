@php
    $first = strtok(trim((string) $name), ' ') ?: null;
    $copy = match ($type) {
        'reset_password' => [
            'eyebrow' => 'Password reset',
            'heading' => 'Reset your password',
            'message' => 'We got a request to reset the password for your Ariana Auto Service account. Enter this code in the app to choose a new one.',
            'label' => 'Reset code',
        ],
        'un_auth_device' => [
            'eyebrow' => 'Security check',
            'heading' => "Confirm it's you signing in",
            'message' => "Someone is signing in to your account on a device we haven't seen before. If it's you, enter this code to continue.",
            'label' => 'Sign-in code',
        ],
        default => [
            'eyebrow' => 'Verify your email',
            'heading' => "Here's your verification code",
            'message' => 'Welcome to Ariana Auto Service. Enter this code in the app to verify your email and finish creating your account.',
            'label' => 'Verification code',
        ],
    };
@endphp
@extends('emails.layout', [
    'title' => $copy['heading'],
    'preheader' => $copy['label'] . ': ' . $code,
    'footer' => 'account',
])

@section('body')
  @include('emails.partials.intro', [
      'eyebrow' => $copy['eyebrow'],
      'heading' => $copy['heading'],
      'name' => $first,
      'message' => $copy['message'],
  ])

  @include('emails.partials.code', ['label' => $copy['label'], 'code' => $code, 'minutes' => 10])

  @if($type === 'un_auth_device')
    @include('emails.partials.rows', ['rows' => [
        ['When', now()->timezone(config('booking.timezone'))->format('D, M j, Y, g:i A')],
        ['Account', $toEmail],
    ]])
    @include('emails.partials.callout', [
        'tone' => 'alert',
        'title' => 'Not you?',
        'text' => "Don't share this code with anyone. Open the app and change your password from Profile › Password & security.",
    ])
  @elseif($type === 'reset_password')
    @include('emails.partials.callout', [
        'tone' => 'alert',
        'title' => "Didn't ask for this?",
        'text' => 'Ignore this email. Your password stays the same, and nobody can change it without this code.',
    ])
  @else
    @include('emails.partials.callout', [
        'title' => "Didn't sign up?",
        'text' => 'You can ignore this email. No account is created until the code is entered.',
    ])
  @endif
@endsection
