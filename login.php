<?php
// ============================================================
// login.php — Multi-role login page
// ============================================================
require_once __DIR__ . '/config/config.php';

// Already logged in → redirect
if (Auth::check()) redirect(Auth::dashboardUrl());

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (Auth::attempt($email, $password)) {
        if ($remember) {
            // Extend session lifetime for "remember me"
            ini_set('session.cookie_lifetime', 86400 * 30);
        }
        redirect(Auth::dashboardUrl());
    } else {
        $error = 'Invalid email or password. Please try again.';
        auditLog('LOGIN_FAILED', 'users', 0, ['email' => $email]);
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <style>
    :root {
      --primary:#0ea5e9; --secondary:#6366f1;
      --font:'Plus Jakarta Sans',sans-serif;
    }
    * { box-sizing:border-box; margin:0; padding:0; }
    body {
      font-family:var(--font);
      min-height:100vh;
      background: linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0c2340 100%);
      display:flex; align-items:center; justify-content:center;
      padding:20px;
      position:relative; overflow:hidden;
    }
    /* Animated blobs */
    .blob {
      position:absolute; border-radius:50%;
      filter:blur(80px); opacity:.25; pointer-events:none;
      animation:blobFloat 8s ease-in-out infinite;
    }
    .blob-1 { width:400px;height:400px;background:#0ea5e9;top:-100px;left:-100px; }
    .blob-2 { width:350px;height:350px;background:#6366f1;bottom:-80px;right:-80px;animation-delay:3s; }
    .blob-3 { width:250px;height:250px;background:#8b5cf6;top:40%;left:50%;animation-delay:5s; }
    @keyframes blobFloat {
      0%,100%{transform:translate(0,0) scale(1);}
      33%    {transform:translate(20px,-20px) scale(1.05);}
      66%    {transform:translate(-15px,15px) scale(.95);}
    }
    /* Login card */
    .login-card {
      width:100%; max-width:440px;
      background:rgba(255,255,255,.07);
      backdrop-filter:blur(24px);
      -webkit-backdrop-filter:blur(24px);
      border:1px solid rgba(255,255,255,.12);
      border-radius:24px;
      padding:44px 40px;
      box-shadow:0 25px 50px rgba(0,0,0,.4);
      position:relative; z-index:10;
      animation:fadeInUp .6s cubic-bezier(.16,1,.3,1);
    }
    @keyframes fadeInUp {
      from{opacity:0;transform:translateY(32px);}
      to  {opacity:1;transform:translateY(0);}
    }
    .brand {
      display:flex; align-items:center; gap:12px;
      margin-bottom:32px;
    }
    .brand-icon {
      width:48px;height:48px;
      border-radius:14px;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      display:grid;place-items:center;
      font-size:22px;color:#fff;
      box-shadow:0 8px 24px rgba(14,165,233,.4);
    }
    .brand-name { font-size:20px;font-weight:800;color:#fff; }
    .brand-sub  { font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.6px;text-transform:uppercase; }
    h2 { font-size:22px;font-weight:800;color:#fff;margin-bottom:6px; }
    .subtitle { font-size:13px;color:rgba(255,255,255,.55);margin-bottom:28px; }
    .form-label { font-size:12.5px;font-weight:600;color:rgba(255,255,255,.8);margin-bottom:6px; }
    .form-control {
      background:rgba(255,255,255,.08);
      border:1.5px solid rgba(255,255,255,.15);
      border-radius:10px;
      color:#fff;
      padding:11px 14px;
      font-size:14px;
      transition:all .2s;
    }
    .form-control::placeholder { color:rgba(255,255,255,.35); }
    .form-control:focus {
      background:rgba(255,255,255,.12);
      border-color:var(--primary);
      box-shadow:0 0 0 3px rgba(14,165,233,.2);
      color:#fff;
      outline:none;
    }
    .input-icon-wrap { position:relative; }
    .input-icon-wrap .icon-left {
      position:absolute;left:14px;top:50%;transform:translateY(-50%);
      color:rgba(255,255,255,.4);font-size:14px; pointer-events:none;
    }
    .input-icon-wrap .form-control { padding-left:40px; }
    .input-icon-wrap .toggle-pw {
      position:absolute;right:14px;top:50%;transform:translateY(-50%);
      background:none;border:none;color:rgba(255,255,255,.4);
      cursor:pointer;font-size:14px;padding:0;
    }
    .btn-login {
      width:100%;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border:none;border-radius:10px;
      color:#fff;font-size:15px;font-weight:700;
      padding:13px;
      cursor:pointer;
      transition:all .25s;
      box-shadow:0 4px 16px rgba(14,165,233,.4);
      margin-top:8px;
    }
    .btn-login:hover { transform:translateY(-2px);box-shadow:0 8px 24px rgba(14,165,233,.5); }
    .btn-login:active { transform:translateY(0); }
    .divider {
      display:flex;align-items:center;gap:12px;
      margin:20px 0;
      color:rgba(255,255,255,.3);font-size:12px;
    }
    .divider::before,.divider::after {
      content:'';flex:1;height:1px;
      background:rgba(255,255,255,.12);
    }
    .demo-accounts { display:grid;gap:8px; }
    .demo-btn {
      display:flex;align-items:center;gap:10px;
      padding:10px 14px;
      background:rgba(255,255,255,.06);
      border:1px solid rgba(255,255,255,.1);
      border-radius:10px;
      color:rgba(255,255,255,.8);
      font-size:12.5px;font-weight:600;
      cursor:pointer;transition:all .2s;
      text-align:left;
    }
    .demo-btn:hover { background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2); }
    .demo-btn .role-dot {
      width:8px;height:8px;border-radius:50%;flex-shrink:0;
    }
    .demo-btn .email-hint { font-weight:400;color:rgba(255,255,255,.45);font-size:11px; }
    .alert-error {
      background:rgba(239,68,68,.15);
      border:1px solid rgba(239,68,68,.3);
      border-radius:10px;
      color:#fca5a5;
      padding:11px 14px;
      font-size:13px;
      margin-bottom:20px;
      display:flex;align-items:center;gap:10px;
    }
    .forgot-link { color:rgba(255,255,255,.55);font-size:12.5px;text-decoration:none; }
    .forgot-link:hover { color:var(--primary); }
    @media(max-width:480px){ .login-card{padding:32px 24px;} }
  </style>
</head>
<body>
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <div class="login-card">
    <!-- Brand -->
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-hospital-user"></i></div>
      <div>
        <div class="brand-name">MediCare HMS</div>
        <div class="brand-sub">Hospital Management System</div>
      </div>
    </div>

    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your account to continue</p>

    <?php if ($error): ?>
    <div class="alert-error">
      <i class="fa-solid fa-circle-exclamation"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="" id="loginForm" novalidate>
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <div class="input-icon-wrap">
          <i class="fa-solid fa-envelope icon-left"></i>
          <input type="email" name="email" class="form-control"
                 placeholder="Enter your email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autocomplete="email" autofocus/>
        </div>
      </div>

      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label mb-0">Password</label>
          <a href="<?= APP_URL ?>/authentication/forgot_password.php" class="forgot-link">Forgot password?</a>
        </div>
        <div class="input-icon-wrap">
          <i class="fa-solid fa-lock icon-left"></i>
          <input type="password" name="password" id="passwordField" class="form-control"
                 placeholder="Enter your password"
                 required autocomplete="current-password"/>
          <button type="button" class="toggle-pw" onclick="togglePw()">
            <i class="fa-solid fa-eye" id="pwEyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="d-flex align-items-center mb-1">
        <input class="form-check-input me-2" type="checkbox" name="remember" id="rememberMe"
               style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.3);cursor:pointer"/>
        <label for="rememberMe" style="color:rgba(255,255,255,.6);font-size:13px;cursor:pointer">Remember me for 30 days</label>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
      </button>
      <div class="demo-accounts">
   ...
</div>

<div style="text-align:center;margin-top:20px;font-size:13px;color:rgba(255,255,255,.45);">
  New patient or doctor?
  <a href="<?= APP_URL ?>/register.php"
     style="color:#0ea5e9;font-weight:700;text-decoration:none;margin-left:4px;">
    Create an account
  </a>
</div>

</div> <!-- login-card end -->
    </form>
    
  </div>

 

  <script>
    function togglePw() {
      const f = document.getElementById('passwordField');
      const i = document.getElementById('pwEyeIcon');
      f.type = f.type === 'password' ? 'text' : 'password';
      i.className = f.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
    }
    function fillDemo(email) {
      document.querySelector('[name=email]').value    = email;
      document.getElementById('passwordField').value = 'password123';
    }
    // Button loading state
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('loginBtn');
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Signing in…';
      btn.disabled  = true;
    });
  </script>
</body>
</html>
