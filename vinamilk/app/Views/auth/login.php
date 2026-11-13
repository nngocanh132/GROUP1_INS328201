<?php
/**
 * View: Login page
 *
 * Expected variables from AuthController::login():
 *   $error    string
 *   $email    string
 *   $remember bool
 *   $is_dev   bool
 *
 * This is a completely self-contained view (no layout_head/layout_foot).
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Vinamilk CLM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">

  <style>
  /* ═══════════════════════════════════════════════════════
     Login page styles — scoped to .lp-* to avoid bleeding
     into other pages that share app.css
     ═══════════════════════════════════════════════════════ */

  html, body { height: 100%; }

  body.lp-body {
    margin: 0;
    padding: 0;
    background: #eef1f6;
    display: flex;
    align-items: stretch;
    justify-content: flex-start;
    min-height: 100vh;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
  }

  /* ── Outer wrapper ── */
  .lp-wrap {
    width: 100%;
    max-width: 100%;
    padding: 0;
    box-sizing: border-box;
    display: flex;
    flex: 1;
  }

  /* ── The card ── */
  .lp-card {
    display: flex;
    background: #fff;
    border-radius: 0;
    box-shadow: none;
    overflow: hidden;
    min-height: 100vh;
    width: 100%;
  }

  /* ──────────────────────────────────────────────────────
     LEFT PANEL  (office photo + dark-blue overlay)
     ────────────────────────────────────────────────────── */
  .lp-left {
    flex: 0 0 52%;
    position: relative;
    background:
      linear-gradient(145deg, rgba(8,18,40,.82) 0%, rgba(10,28,70,.72) 50%, rgba(6,14,36,.88) 100%),
      url('assets/img/login-bg.jpg') center center / cover no-repeat;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 32px 36px;
    overflow: hidden;
    color: #fff;
  }

  /* Subtle vignette at the bottom to improve text contrast */
  .lp-left::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(4,12,30,.55) 0%, transparent 55%);
    pointer-events: none;
    z-index: 0;
  }

  /* Top-right accent glow */
  .lp-left::after {
    content: '';
    position: absolute;
    top: -60px; right: -50px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(23,97,168,.22) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
  }

  .lp-left-inner { position: relative; z-index: 1; display: contents; }

  /* Badge at the top of the left panel */
  .lp-badge {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 20px;
    padding: 5px 13px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: rgba(255,255,255,.8);
    position: relative;
    z-index: 1;
  }
  .lp-badge-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #4ade80;
    flex-shrink: 0;
  }

  /* Headline block */
  .lp-left-body {
    position: relative;
    z-index: 1;
    margin-top: auto;
    padding-bottom: 8px;
  }
  .lp-left-body h2 {
    font-size: 28px;
    font-weight: 800;
    line-height: 1.2;
    margin: 0 0 14px;
    letter-spacing: -.02em;
  }
  .lp-left-body p {
    font-size: 13px;
    color: rgba(255,255,255,.6);
    line-height: 1.65;
    margin: 0;
    max-width: 270px;
  }

  /* Bottom labels */
  .lp-left-footer {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0;
    padding-top: 24px;
    flex-wrap: wrap;
    gap: 6px;
  }
  .lp-left-footer-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: rgba(255,255,255,.45);
    font-weight: 500;
  }
  .lp-left-footer-item svg {
    width: 12px; height: 12px;
    stroke: rgba(255,255,255,.45);
    flex-shrink: 0;
  }
  .lp-left-footer-sep {
    width: 3px; height: 3px;
    border-radius: 50%;
    background: rgba(255,255,255,.25);
    margin: 0 6px;
  }

  /* ──────────────────────────────────────────────────────
     RIGHT PANEL
     ────────────────────────────────────────────────────── */
  .lp-right {
    flex: 0 0 48%;
    display: flex;
    flex-direction: column;
    padding: 32px 38px 28px;
    overflow-y: auto;
  }

  /* Top row: brand + role badge */
  .lp-right-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 40px;
    flex-shrink: 0;
  }

  .lp-brand {
    display: flex;
    align-items: center;
    gap: 9px;
  }
  .lp-brand-icon {
    width: 30px; height: 30px;
    background: #0e2a5c;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .lp-brand-icon svg {
    width: 15px; height: 15px;
    stroke: #fff;
    fill: none;
    stroke-width: 2;
  }
  .lp-brand-name {
    font-size: 12.5px;
    font-weight: 700;
    color: #0e2a5c;
    letter-spacing: .04em;
  }
  .lp-brand-name span { color: #1761a8; }

  .lp-role-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eef4ff;
    border: 1px solid #c7d9f5;
    border-radius: 20px;
    padding: 4px 11px;
    font-size: 11px;
    font-weight: 600;
    color: #1761a8;
  }
  .lp-role-pill-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #1761a8;
    flex-shrink: 0;
  }

  /* Heading */
  .lp-heading {
    font-size: 22px;
    font-weight: 700;
    color: #0c1f3a;
    margin: 0 0 4px;
    letter-spacing: -.02em;
  }
  .lp-subheading {
    font-size: 13px;
    color: #8898aa;
    margin: 0 0 26px;
  }

  /* Error alert */
  .lp-alert {
    background: #fef3f2;
    border: 1px solid #fdc9c5;
    border-radius: 8px;
    padding: 10px 13px;
    font-size: 12.5px;
    color: #9a1a17;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 18px;
  }
  .lp-alert svg { width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px; }

  /* Form fields */
  .lp-field { margin-bottom: 15px; }
  .lp-label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #5a6a7e;
    margin-bottom: 6px;
  }
  .lp-input-wrap { position: relative; }
  .lp-input {
    display: block;
    width: 100%;
    height: 42px;
    padding: 0 42px 0 13px;
    border: 1.5px solid #dce4ef;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    color: #0c1f3a;
    background: #fff;
    outline: none;
    box-sizing: border-box;
    transition: border-color .15s, box-shadow .15s;
  }
  .lp-input:focus {
    border-color: #1761a8;
    box-shadow: 0 0 0 3px rgba(23,97,168,.1);
  }
  .lp-input.is-err {
    border-color: #d93025;
    box-shadow: 0 0 0 3px rgba(217,48,37,.07);
  }
  .lp-input::placeholder { color: #b8c4d0; }

  .lp-eye {
    position: absolute;
    right: 11px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; padding: 2px;
    color: #8898aa;
    display: flex; align-items: center;
    transition: color .15s;
  }
  .lp-eye:hover { color: #1761a8; }
  .lp-eye svg { width: 16px; height: 16px; display: block; }

  /* Remember + Forgot row */
  .lp-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 14px 0 20px;
  }
  .lp-remember {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: #5a6a7e;
    cursor: pointer;
    user-select: none;
  }
  .lp-remember input {
    width: 14px; height: 14px;
    accent-color: #1761a8;
    margin: 0; cursor: pointer;
  }
  .lp-forgot {
    font-size: 12px;
    font-weight: 600;
    color: #1761a8;
    text-decoration: none;
  }
  .lp-forgot:hover { text-decoration: underline; }

  /* Submit button */
  .lp-btn {
    width: 100%;
    height: 43px;
    background: #1761a8;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 600;
    font-family: inherit;
    letter-spacing: .01em;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background .15s, box-shadow .15s;
  }
  .lp-btn:hover { background: #1351a0; box-shadow: 0 4px 16px rgba(23,97,168,.32); }
  .lp-btn:active { background: #0f3d80; }
  .lp-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

  /* ── Quick Login section ── */
  .lp-quick {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid #edf0f5;
  }
  .lp-quick-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 12px;
  }
  .lp-quick-line {
    flex: 1;
    height: 1px;
    background: #edf0f5;
  }
  .lp-quick-title {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #b0bac8;
    white-space: nowrap;
  }
  .lp-dev-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 4px;
    padding: 2px 7px;
    font-size: 9.5px;
    font-weight: 700;
    color: #92400e;
    margin-bottom: 10px;
    width: fit-content;
    margin-left: auto; margin-right: auto;
    display: flex;
  }
  .lp-quick-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    max-width: 380px;
    margin: 0 auto;
    width: 100%;
  }
  .lp-quick-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    padding: 10px 6px 9px;
    background: #fafbfd;
    border: 1.5px solid #e4eaf4;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    transition: border-color .15s, background .15s, box-shadow .15s;
    font-family: inherit;
  }
  .lp-quick-card:hover {
    border-color: #1761a8;
    background: #f0f4fb;
    box-shadow: 0 2px 10px rgba(23,97,168,.13);
  }
  .lp-quick-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
  }
  .lp-qa-admin { background: #0e2a5c; }
  .lp-qa-staff { background: #1761a8; }
  .lp-qa-user  { background: #12a150; }
  .lp-quick-role {
    font-size: 11px;
    font-weight: 600;
    color: #3a4a5e;
  }
  .lp-quick-hint {
    font-size: 9.5px;
    color: #a0aab5;
    text-align: center;
    max-width: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
  }

  /* ── Right panel footer ── */
  .lp-footer {
    margin-top: auto;
    padding-top: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    max-width: 460px;
    width: 100%;
    align-self: center;
  }
  .lp-footer-copy {
    font-size: 11px;
    color: #b0bac8;
  }
  .lp-footer-links {
    display: flex;
    gap: 14px;
  }
  .lp-footer-links a {
    font-size: 11px;
    color: #b0bac8;
    text-decoration: none;
  }
  .lp-footer-links a:hover { color: #1761a8; }

  /* ── Responsive ── */
  @media (max-width: 700px) {
    .lp-card { flex-direction: column; }
    .lp-left {
      flex: none;
      min-height: 170px;
      padding: 24px 26px;
    }
    .lp-left-body h2 { font-size: 20px; }
    .lp-right { padding: 24px 26px 20px; }
    .lp-right-top { margin-bottom: 24px; }
    .lp-quick-hint { display: none; }
  }
  @media (max-width: 420px) {
    .lp-wrap { padding: 12px; }
    .lp-right { padding: 20px 18px 16px; }
  }
  </style>
</head>
<body class="lp-body">

<div class="lp-wrap">
<div class="lp-card">

  <!-- ══════════════════════════════════
       LEFT PANEL
       ══════════════════════════════════ -->
  <div class="lp-left">

    <!-- Top badge -->
    <div class="lp-badge" style="position:relative;z-index:1;">
      <span class="lp-badge-dot"></span>
      Contract Lifecycle
    </div>

    <!-- Decorative SVG: architectural lines suggestion -->
    <svg viewBox="0 0 360 420" xmlns="http://www.w3.org/2000/svg"
         style="position:absolute;inset:0;width:100%;height:100%;opacity:.09;pointer-events:none;z-index:0;">
      <!-- horizontal floors -->
      <line x1="0" y1="120" x2="360" y2="120" stroke="white" stroke-width="1"/>
      <line x1="0" y1="200" x2="360" y2="200" stroke="white" stroke-width="1"/>
      <line x1="0" y1="280" x2="360" y2="280" stroke="white" stroke-width="1"/>
      <line x1="0" y1="360" x2="360" y2="360" stroke="white" stroke-width="1"/>
      <!-- vertical columns -->
      <line x1="72"  y1="0" x2="72"  y2="420" stroke="white" stroke-width="1"/>
      <line x1="144" y1="0" x2="144" y2="420" stroke="white" stroke-width="1"/>
      <line x1="216" y1="0" x2="216" y2="420" stroke="white" stroke-width="1"/>
      <line x1="288" y1="0" x2="288" y2="420" stroke="white" stroke-width="1"/>
      <!-- window rectangles -->
      <rect x="90"  y="135" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="90"  y="215" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="90"  y="295" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="162" y="135" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="162" y="215" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="162" y="295" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="234" y="135" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="234" y="215" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <rect x="234" y="295" width="36" height="48" rx="2" fill="none" stroke="white" stroke-width="1"/>
      <!-- soft circle accent -->
      <circle cx="330" cy="60" r="60" fill="none" stroke="white" stroke-width="1"/>
      <circle cx="30"  cy="380" r="40" fill="none" stroke="white" stroke-width="1"/>
    </svg>

    <!-- Main copy -->
    <div class="lp-left-body" style="position:relative;z-index:1;">
      <h2>Manage Contracts.<br>Simplified.</h2>
      <p>Manage contract requests, documents and approvals in one place.</p>
    </div>

    <!-- Footer labels -->
    <div class="lp-left-footer" style="position:relative;z-index:1;">
      <div class="lp-left-footer-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        Enterprise Security
      </div>
      <div class="lp-left-footer-sep"></div>
      <div class="lp-left-footer-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
          <rect x="2" y="7" width="20" height="14" rx="2"/>
          <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
        </svg>
        Vinamilk CLM System
      </div>
    </div>

  </div><!-- /lp-left -->

  <!-- ══════════════════════════════════
       RIGHT PANEL
       ══════════════════════════════════ -->
  <div class="lp-right">

    <!-- Brand + role -->
    <div class="lp-right-top">
      <div class="lp-brand">
        <div class="lp-brand-icon">
          <svg viewBox="0 0 24 24">
            <rect x="2" y="7" width="20" height="14" rx="2"/>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          </svg>
        </div>
        <div class="lp-brand-name">VINA<span>MILK</span></div>
      </div>
      <div class="lp-role-pill">
        <span class="lp-role-pill-dot"></span>
        Secure Login
      </div>
    </div>

    <!-- Heading -->
    <h1 class="lp-heading">Welcome Back</h1>
    <p class="lp-subheading">Sign in to your account</p>

    <!-- Error -->
    <?php if ($error): ?>
    <div class="lp-alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span><?= e($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- Login form -->
    <form id="loginForm" method="POST" action="<?= BASE_URL ?>/login.php" novalidate>

      <div class="lp-field">
        <label class="lp-label" for="email">Email Address</label>
        <div class="lp-input-wrap">
          <input type="email" id="email" name="email"
                 class="lp-input<?= $error ? ' is-err' : '' ?>"
                 value="<?= e($email) ?>"
                 placeholder="abc@vinamilk.com"
                 autocomplete="email"
                 required>
        </div>
      </div>

      <div class="lp-field">
        <label class="lp-label" for="password">Password</label>
        <div class="lp-input-wrap">
          <input type="password" id="password" name="password"
                 class="lp-input<?= $error ? ' is-err' : '' ?>"
                 placeholder="••••••••••••"
                 autocomplete="current-password"
                 required>
          <button type="button" class="lp-eye" id="togglePw" aria-label="Show/hide password">
            <!-- eye-open icon (default) -->
            <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <!-- eye-closed icon (hidden by default) -->
            <svg id="eyeOff" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="display:none;">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="lp-meta">
        <label class="lp-remember">
          <input type="checkbox" name="remember" value="1" <?= $remember ? 'checked' : '' ?>>
          Remember me
        </label>
        <a href="#" class="lp-forgot">Forgot Password?</a>
      </div>

      <button type="submit" class="lp-btn">
        Sign In
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="5" y1="12" x2="19" y2="12"/>
          <polyline points="12 5 19 12 12 19"/>
        </svg>
      </button>

    </form>

    <!-- ════════════════════════════════
         QUICK LOGIN  (dev / localhost only)
         ════════════════════════════════ -->
    <?php if ($is_dev): ?>
    <div class="lp-quick">
      <div class="lp-quick-header">
        <div class="lp-quick-line"></div>
        <div class="lp-quick-title">Quick Login</div>
        <div class="lp-quick-line"></div>
      </div>

      <div class="lp-dev-tag">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="10" height="10">
          <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
        </svg>
        Development Only
      </div>

      <!--
        These forms POST the real credentials to the normal handler.
        No authentication is bypassed — password_verify() still runs.
        Accounts from seed data (password = "password"):
          Admin : khoavd@vinamilk.com.vn    (RoleName = Manager/Admin)
          Staff : maitt@vinamilk.com.vn     (RoleName = Staff)
          User  : an.nguyen@abclogistics.vn (RoleName = External)
      -->
      <div class="lp-quick-grid">

        <!-- Admin -->
        <form method="POST" action="<?= BASE_URL ?>/login.php">
          <input type="hidden" name="email"    value="khoavd@vinamilk.com.vn">
          <input type="hidden" name="password" value="password">
          <button type="submit" class="lp-quick-card">
            <div class="lp-quick-avatar lp-qa-admin">A</div>
            <div class="lp-quick-role">Admin</div>
            <div class="lp-quick-hint">khoavd@vinamilk</div>
          </button>
        </form>

        <!-- Staff -->
        <form method="POST" action="<?= BASE_URL ?>/login.php">
          <input type="hidden" name="email"    value="maitt@vinamilk.com.vn">
          <input type="hidden" name="password" value="password">
          <button type="submit" class="lp-quick-card">
            <div class="lp-quick-avatar lp-qa-staff">S</div>
            <div class="lp-quick-role">Staff</div>
            <div class="lp-quick-hint">maitt@vinamilk</div>
          </button>
        </form>

        <!-- User / External partner -->
        <form method="POST" action="<?= BASE_URL ?>/login.php">
          <input type="hidden" name="email"    value="an.nguyen@abclogistics.vn">
          <input type="hidden" name="password" value="password">
          <button type="submit" class="lp-quick-card">
            <div class="lp-quick-avatar lp-qa-user">U</div>
            <div class="lp-quick-role">User / Partner</div>
            <div class="lp-quick-hint">abclogistics.vn</div>
          </button>
        </form>

      </div>
    </div>
    <?php endif; /* $is_dev */ ?>

    <!-- Footer links -->
    <div class="lp-footer">
      <div class="lp-footer-copy">© <?= date('Y') ?> Vinamilk CLM System</div>
      <div class="lp-footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
      </div>
    </div>

  </div><!-- /lp-right -->

</div><!-- /lp-card -->
</div><!-- /lp-wrap -->

<script>
/* Password toggle */
(function () {
  const btn    = document.getElementById('togglePw');
  const input  = document.getElementById('password');
  const eyeOn  = document.getElementById('eyeOpen');
  const eyeOff = document.getElementById('eyeOff');
  if (!btn || !input) return;
  btn.addEventListener('click', function () {
    const visible = input.type === 'text';
    input.type       = visible ? 'password' : 'text';
    eyeOn.style.display  = visible ? '' : 'none';
    eyeOff.style.display = visible ? 'none' : '';
  });
})();
</script>

</body>
</html>
