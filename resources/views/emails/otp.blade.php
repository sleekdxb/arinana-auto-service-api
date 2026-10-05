<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Verify Your Email – Arinana Auto Service</title>

  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet"/>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: #f0f0f0;
      font-family: 'Barlow', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 16px;
    }

    .email-wrapper {
      width: 100%;
      max-width: 620px;
      margin: 0 auto;
    }

    .card {
      background: #ffffff;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      overflow: hidden;
      box-shadow:
        0 8px 40px rgba(0, 0, 0, 0.10),
        0 0 0 1px rgba(200, 20, 20, 0.07);
    }

    .accent-line {
      height: 3px;
      background: linear-gradient(
        90deg,
        #8b0000 0%,
        #c0392b 40%,
        #e74c3c 60%,
        #c0392b 80%,
        #8b0000 100%
      );
    }

    /* HEADER */
    .header {
      background: #ffffff;
      padding: 24px 20px;
      text-align: center;
      border-bottom: 3px solid #c0392b;
      position: relative;
      overflow: hidden;
    }

    .header::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(
        ellipse 80% 60% at 50% 110%,
        rgba(192, 57, 43, 0.18) 0%,
        transparent 70%
      );
      pointer-events: none;
    }

    .header img {
      max-width: 200px;
      width: 100%;
      height: 65px;
      object-fit: contain;
      display: block;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    /* HERO */
    .hero-band {
      background: linear-gradient(
        135deg,
        #fdf2f2 0%,
        #fce8e8 40%,
        #fdf0f0 100%
      );
      padding: 28px 48px;
      display: flex;
      align-items: center;
      gap: 16px;
      border-bottom: 1px solid #f0d0d0;
    }

    .hero-icon {
      flex-shrink: 0;
      width: 44px;
      height: 44px;
      background: #c0392b;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .hero-icon svg {
      width: 22px;
      height: 22px;
      fill: #fff;
    }

    .hero-text h1 {
      font-family: 'Barlow Condensed', sans-serif;
      font-weight: 800;
      font-size: 22px;
      color: #b07070;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      line-height: 1.1;
    }

    .hero-text p {
      font-size: 13px;
      color: #b07070;
      margin-top: 3px;
      font-weight: 300;
      letter-spacing: 0.02em;
    }

    /* BODY */
    .body {
      padding: 40px 48px 36px;
    }

    .greeting {
      font-size: 17px;
      color: #333333;
      font-weight: 400;
      margin-bottom: 18px;
    }

    .greeting strong {
      color: #111111;
      font-weight: 600;
    }

    .intro-text {
      font-size: 14.5px;
      color: #666;
      line-height: 1.75;
      margin-bottom: 32px;
      font-weight: 300;
    }

    .intro-text strong {
      color: #444444;
      font-weight: 500;
    }

    /* OTP */
    .otp-section {
      background: #fafafa;
      border: 1px solid #e8e8e8;
      border-left: 4px solid #c0392b;
      border-radius: 4px;
      padding: 28px 32px;
      margin-bottom: 32px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .otp-section::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(
        ellipse 90% 60% at 50% 120%,
        rgba(192, 57, 43, 0.04) 0%,
        transparent 70%
      );
      pointer-events: none;
    }

    .otp-label {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: #c0392b;
      margin-bottom: 14px;
    }

    .otp-code {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 36px;
      font-weight: 800;
      letter-spacing: 0.25em;
      color: #111111;
      line-height: 1;
      position: relative;
      z-index: 1;
      text-shadow: 0 2px 20px rgba(192, 57, 43, 0.2);
    }

    .otp-expiry {
      margin-top: 14px;
      font-size: 12px;
      color: #999;
      font-weight: 400;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      position: relative;
      z-index: 1;
    }

    /* DIVIDER */
    .divider {
      border: none;
      border-top: 1px solid #eeeeee;
      margin: 28px 0;
    }

    /* SECURITY NOTE */
    .security-note {
      background: #fafafa;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      padding: 16px 20px;
      display: flex;
      gap: 14px;
      align-items: flex-start;
      margin-bottom: 28px;
    }

    .security-note svg {
      flex-shrink: 0;
      width: 18px;
      height: 18px;
      margin-top: 1px;
      fill: #c0392b;
      opacity: 0.8;
    }

    .security-note p {
      font-size: 12.5px;
      color: #666;
      line-height: 1.65;
      font-weight: 300;
    }

    .security-note p strong {
      color: #666;
      font-weight: 500;
    }

    /* NEXT STEPS */
    .next-steps {
      margin-bottom: 32px;
    }

    .next-steps-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: #aaaaaa;
      margin-bottom: 14px;
    }

    .steps-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .steps-list li {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 13.5px;
      color: #666;
      font-weight: 300;
    }

    .step-num {
      flex-shrink: 0;
      width: 22px;
      height: 22px;
      background: #f5f5f5;
      border: 1px solid #e8e8e8;
      border-radius: 50%;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 11px;
      font-weight: 700;
      color: #c0392b;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* SIGN OFF */
    .sign-off {
      font-size: 14px;
      color: #888;
      font-weight: 300;
      line-height: 1.7;
    }

    .sign-off .team-name {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 15px;
      font-weight: 700;
      color: #c0392b;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      display: block;
      margin-top: 6px;
    }

    /* FOOTER */
    .footer {
      background: #d80621;
      border-top: 1px solid #e0e0e0;
    }

    .footer-top {
      padding: 32px 48px 24px;
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 24px;
      border-bottom: 1px solid #b4041a;
    }

    .footer-col h4 {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: #ffffff;
      margin-bottom: 10px;
    }

    .footer-col p,
    .footer-col a {
      display: block;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.85);
      text-decoration: none;
      line-height: 1.9;
      font-weight: 300;
    }

    .footer-col a:hover {
      color: #ffffff;
    }

    .footer-brand {
      padding: 20px 48px;
      display: flex;
      align-items: center;
      gap: 16px;
      border-bottom: 1px solid #b4041a;
    }

    .footer-logo {
      height: 45px;
      width: auto;
      object-fit: contain;
    }

    .footer-brand-text {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.8);
      font-weight: 300;
      letter-spacing: 0.03em;
    }

    .footer-brand-text strong {
      color: #ffffff;
      font-weight: 500;
    }

    .footer-bottom {
      padding: 16px 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .footer-bottom p {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.75);
      font-weight: 300;
      letter-spacing: 0.02em;
    }

    .footer-bottom-links {
      display: flex;
      gap: 16px;
    }

    .footer-bottom-links a {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.75);
      text-decoration: none;
      letter-spacing: 0.02em;
      font-weight: 300;
    }

    /* TABLET */
    @media (max-width: 600px) {
      body {
        padding: 16px 8px;
        align-items: flex-start;
      }

      .email-wrapper {
        max-width: 100%;
      }

      .header {
        padding: 20px;
      }

      .header img {
        height: 52px;
      }

      .hero-band {
        padding: 18px 20px;
        gap: 12px;
      }

      .hero-icon {
        width: 36px;
        height: 36px;
      }

      .hero-icon svg {
        width: 18px;
        height: 18px;
      }

      .hero-text h1 {
        font-size: 17px;
      }

      .hero-text p {
        font-size: 11px;
      }

      .body {
        padding: 24px 20px;
      }

      .greeting {
        font-size: 15px;
      }

      .intro-text {
        font-size: 13.5px;
        margin-bottom: 24px;
      }

      .otp-section {
        padding: 20px 16px;
        margin-bottom: 24px;
      }

      .otp-code {
        font-size: 30px;
        letter-spacing: 0.2em;
      }

      .security-note {
        padding: 14px;
        gap: 10px;
      }

      .security-note p {
        font-size: 12px;
      }

      .steps-list li {
        font-size: 13px;
      }

      .sign-off {
        font-size: 13px;
      }

      .footer-top {
        padding: 24px 20px 20px;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
      }

      .footer-brand {
        padding: 16px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .footer-bottom {
        padding: 14px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }

    /* MOBILE */
    @media (max-width: 400px) {
      .header img {
        height: 42px;
      }

      .hero-band {
        flex-direction: column;
        align-items: flex-start;
        padding: 16px;
        gap: 10px;
      }

      .hero-text h1 {
        font-size: 15px;
      }

      .body {
        padding: 20px 16px;
      }

      .otp-code {
        font-size: 26px;
        letter-spacing: 0.15em;
      }

      .footer-top {
        grid-template-columns: 1fr;
        padding: 20px 16px;
        gap: 16px;
      }

      .footer-brand {
        padding: 14px 16px;
      }

      .footer-bottom {
        padding: 12px 16px;
      }
    }
  </style>
</head>

<body>

<div class="email-wrapper">

  <div class="card">

    <div class="accent-line"></div>

    <!-- HEADER -->
    <div class="header">
      <img
        src="https://caddispatch.com/Cadispatch_Api/resources/Logo.png"
        alt="Arinana Auto Service Logo"
      >
    </div>

    <!-- HERO BAND -->
    <div class="hero-band">

      <div class="hero-icon">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
        </svg>
      </div>

      <div class="hero-text">

        @if($type === 'verify_email')
          <h1>Email Verification Required</h1>
        @else
          <h1>Password Reset Verification Required</h1>
        @endif

        <p>One-Time Password &nbsp;·&nbsp; Expires in 10 minutes</p>

      </div>
    </div>

    <!-- BODY -->
    <div class="body">

      <p class="greeting">
        Hello, <strong>{{ $name }}</strong>
      </p>

      @if($type === 'verify_email')

        <p class="intro-text">
          Welcome to <strong>Arinana Auto Service</strong>.
          To complete your account registration and secure your profile,
          please verify your email address using the One-Time Password below.
        </p>

      @else

        <p class="intro-text">
          We received a request to reset your password for
          <strong>Arinana Auto Service</strong>.
          To proceed and secure your account, please use the One-Time Password
          (OTP) below to verify your identity and continue the password reset process.
        </p>

      @endif

      <!-- OTP BOX -->
      <div class="otp-section">

        <div class="otp-label">
          Your Verification Code
        </div>

        <div class="otp-code">
          {{ $code }}
        </div>

        <div class="otp-expiry">
          <svg
            viewBox="0 0 24 24"
            width="13"
            height="13"
            fill="none"
            stroke="#555"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>

          This code will expire in
          <strong style="color:#666;">10 minutes</strong>
        </div>

      </div>

      <!-- SECURITY NOTE -->
      <div class="security-note">

        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-3-3 1.41-1.41L11 12.17l4.59-4.58L17 9l-6 6z"/>
        </svg>

        @if($type === 'verify_email')

          <p>
            <strong>Didn't request this?</strong>
            If you did not create an account with Arinana Auto Service,
            you can safely ignore this email. Your account will not be
            activated without verification. No action is required on your part.
          </p>

        @else

          <p>
            <strong>Didn't request this?</strong>
            If you did not request a password reset for your Arinana Auto Service
            account, you can safely ignore this email. Your account password
            will remain unchanged, and no further action is required.
          </p>

        @endif

      </div>

      <hr class="divider"/>

      <!-- NEXT STEPS -->
      <div class="next-steps">

        <div class="next-steps-title">
          After Verification
        </div>

        <ul class="steps-list">

          <li>
            <span class="step-num">1</span>
            Complete your profile
          </li>

          <li>
            <span class="step-num">2</span>
            Upload required documents and information
          </li>

          <li>
            <span class="step-num">3</span>
            Start using Arinana Auto Service
          </li>

        </ul>

      </div>

      <hr class="divider"/>

      <!-- SIGN OFF -->
      <div class="sign-off">

        Best regards,

        <span class="team-name">
          Arinana Auto Service Team
        </span>

      </div>

    </div>

    <!-- FOOTER -->
    <div class="footer">

      <div class="footer-top">

        <div class="footer-col">

          <h4>Support</h4>

          <a href="mailto:support@arinanaautoservice.com">
            support@arinanaautoservice.com
          </a>

          <p>Mon – Fri, 9 AM – 6 PM</p>
          <p>Response within 24 hours</p>

        </div>

        <div class="footer-col">

          <h4>Platform</h4>

          <a href="#">Customer Portal</a>
          <a href="#">Service Portal</a>
          <a href="#">Help Centre</a>

        </div>

        <div class="footer-col">

          <h4>Legal</h4>

          <a href="#">Privacy Policy</a>
          <a href="#">Terms of Service</a>
          <a href="#">Cookie Policy</a>
          <a href="#">Unsubscribe</a>

        </div>

      </div>

      <!-- FOOTER BRAND -->
      <div class="footer-brand">

        <img
          class="footer-logo"
          src="https://caddispatch.com/Cadispatch_Api/resources/Logo.png"
          alt="Arinana Auto Service Logo"
        >

        <p class="footer-brand-text">
          <strong>Arinana Auto Service</strong>
          &mdash;
          Providing reliable automotive services and solutions.
        </p>

      </div>

      <!-- FOOTER BOTTOM -->
      <div class="footer-bottom">

        <p>
          &copy; {{ date('Y') }} Arinana Auto Service. All rights reserved.
        </p>

        <div class="footer-bottom-links">
          <a href="#">Preferences</a>
          <a href="#">Help Centre</a>
        </div>

      </div>

    </div>

    <div class="accent-line"></div>

  </div>

</div>

</body>
</html>

