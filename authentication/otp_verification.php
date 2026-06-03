<?php
// ============================================================
// authentication/otp_verification.php
// Handles OTP verification for BOTH:
//   context=register  → new account creation
//   context=reset     → password reset (from forgot_password.php)
// Matches login.php glassmorphism design exactly
// ============================================================
require_once __DIR__ . '/../config/config.php';

// Already logged in
if (Auth::check()) redirect(Auth::dashboardUrl());

$context = clean($_GET['context'] ?? 'register'); // 'register' | 'reset'
$error   = '';
$success = '';

// ── Validate session state ────────────────────────────────────
if ($context === 'register') {
    if (empty($_SESSION['pending_reg']) || empty($_SESSION['reg_otp'])) {
        flash('danger', 'Registration session not found. Please start again.');
        redirect(APP_URL . '/register.php');
    }
    $email      = $_SESSION['pending_reg']['email'] ?? '';
    $name       = $_SESSION['pending_reg']['full_name'] ?? '';
    $expiresAt  = $_SESSION['reg_otp_expires'] ?? 0;
    $demoOtp    = $_SESSION['reg_demo_otp'] ?? null;

} elseif ($context === 'reset') {
    if (empty($_SESSION['fp_user_id']) || empty($_SESSION['fp_step']) || $_SESSION['fp_step'] !== 2) {
        flash('danger', 'Password reset session not found.');
        redirect(APP_URL . '/authentication/forgot_password.php');
    }
    $userId    = $_SESSION['fp_user_id'];
    $userRow   = Database::fetchOne("SELECT full_name, email FROM users WHERE id=?", [$userId]);
    $email     = $userRow['email'] ?? '';
    $name      = $userRow['full_name'] ?? '';
    $expiresAt = time() + 900; // OTP handled by Auth::verifyOTP which checks DB expiry
    $demoOtp   = $_SESSION['fp_demo_otp'] ?? null;

} else {
    redirect(APP_URL . '/login.php');
}

// ── Handle POST (OTP submission) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otpInput = implode('', array_map(fn($i) => clean($_POST["otp_{$i}"] ?? ''), range(1, 6)));
    $otpInput = preg_replace('/\D/', '', $otpInput); // digits only

    if (strlen($otpInput) !== 6) {
        $error = 'Please enter all 6 digits of the OTP.';

    } elseif ($context === 'register') {
        // Check expiry
        if (time() > $expiresAt) {
            unset($_SESSION['reg_otp'], $_SESSION['reg_otp_expires'], $_SESSION['reg_demo_otp']);
            $error = 'OTP has expired. Please register again.';
        } elseif (!password_verify($otpInput, $_SESSION['reg_otp'])) {
            $error = 'Incorrect OTP. Please check and try again.';
        } else {
            // OTP valid — mark session as verified and process registration
            unset($_SESSION['reg_otp'], $_SESSION['reg_otp_expires'], $_SESSION['reg_demo_otp']);
            $_SESSION['reg_verified'] = true;
            // Delegate to register_process.php
            redirect(APP_URL . '/authentication/register_process.php');
        }

    } elseif ($context === 'reset') {
        if (!Auth::verifyOTP($userId, $otpInput)) {
            $error = 'Incorrect or expired OTP. Please try again.';
        } else {
            $_SESSION['fp_step'] = 3;
            redirect(APP_URL . '/authentication/forgot_password.php');
        }
    }
}

// ── Resend OTP ────────────────────────────────────────────────
if (isset($_GET['resend'])) {
    if ($context === 'register' && !empty($_SESSION['pending_reg'])) {
        $newOtp = Auth::generateOTP();
        $_SESSION['reg_otp']         = password_hash($newOtp, PASSWORD_BCRYPT);
        $_SESSION['reg_otp_expires']  = time() + 900;
        $_SESSION['reg_demo_otp']     = $newOtp;
        $success = 'A new OTP has been sent.';
    } elseif ($context === 'reset') {
        $newOtp = Auth::generateOTP();
        Auth::saveOTP($userId, $newOtp);
        $_SESSION['fp_demo_otp'] = $newOtp;
        $success = 'A new OTP has been sent.';
    }
    // Redirect to remove ?resend from URL
    redirect(APP_URL . "/authentication/otp_verification.php?context={$context}" . ($success ? '&resent=1' : ''));
}

if (isset($_GET['resent'])) {
    $success = 'A new OTP has been sent to ' . $email;
    $demoOtp = $_SESSION['reg_demo_otp'] ?? $_SESSION['fp_demo_otp'] ?? null;
}

// Mask email for display: te***@gmail.com
$maskedEmail = preg_replace_callback(
    '/^(.{2})(.+)(@.+)$/',
    fn($m) => $m[1] . str_repeat('*', max(1, strlen($m[2]))) . $m[3],
    $email
);

$contextTitle = $context === 'register' ? 'Verify Your Email' : 'Verify Your Identity';
$contextDesc  = $context === 'register'
    ? "We've sent a 6-digit verification code to complete your registration."
    : "Enter the verification code sent to confirm your identity.";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $contextTitle ?> — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <style>
    :root { --primary:#0ea5e9; --secondary:#6366f1; --font:'Plus Jakarta Sans',sans-serif; }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{
      font-family:var(--font);
      min-height:100vh;
      background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0c2340 100%);
      display:flex;align-items:center;justify-content:center;
      padding:20px;position:relative;overflow:hidden;
    }

    /* Blobs — identical to login.php */
    .blob{position:absolute;border-radius:50%;filter:blur(80px);opacity:.25;pointer-events:none;animation:blobFloat 8s ease-in-out infinite;}
    .blob-1{width:400px;height:400px;background:#0ea5e9;top:-100px;left:-100px;}
    .blob-2{width:350px;height:350px;background:#6366f1;bottom:-80px;right:-80px;animation-delay:3s;}
    .blob-3{width:250px;height:250px;background:#8b5cf6;top:40%;left:50%;animation-delay:5s;}
    @keyframes blobFloat{0%,100%{transform:translate(0,0) scale(1);}33%{transform:translate(20px,-20px) scale(1.05);}66%{transform:translate(-15px,15px) scale(.95);}}

    /* Card — identical glassmorphism to login.php */
    .otp-card{
      width:100%;max-width:440px;
      background:rgba(255,255,255,.07);
      backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
      border:1px solid rgba(255,255,255,.12);
      border-radius:24px;
      padding:44px 40px;
      box-shadow:0 25px 50px rgba(0,0,0,.4);
      position:relative;z-index:10;
      animation:fadeInUp .6s cubic-bezier(.16,1,.3,1);
    }
    @keyframes fadeInUp{from{opacity:0;transform:translateY(32px);}to{opacity:1;transform:translateY(0);}}

    /* Brand */
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
    .brand-icon{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--secondary));display:grid;place-items:center;font-size:20px;color:#fff;box-shadow:0 6px 20px rgba(14,165,233,.4);flex-shrink:0;}
    .brand-name{font-size:18px;font-weight:800;color:#fff;}
    .brand-sub{font-size:10px;color:rgba(255,255,255,.5);letter-spacing:.6px;text-transform:uppercase;}

    /* Icon circle */
    .otp-icon-wrap{
      width:72px;height:72px;border-radius:50%;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      display:grid;place-items:center;
      margin:0 auto 20px;
      font-size:28px;color:#fff;
      box-shadow:0 8px 24px rgba(14,165,233,.4);
      animation:pulse 2s ease-in-out infinite;
    }
    @keyframes pulse{0%,100%{box-shadow:0 8px 24px rgba(14,165,233,.4);}50%{box-shadow:0 8px 32px rgba(14,165,233,.7);}}

    h2{font-size:22px;font-weight:800;color:#fff;margin-bottom:6px;text-align:center;}
    .subtitle{font-size:13px;color:rgba(255,255,255,.55);margin-bottom:6px;text-align:center;line-height:1.6;}
    .email-badge{
      display:inline-flex;align-items:center;gap:6px;
      padding:5px 12px;border-radius:20px;
      background:rgba(14,165,233,.15);border:1px solid rgba(14,165,233,.3);
      color:#38bdf8;font-size:12.5px;font-weight:600;
      margin-bottom:24px;
    }

    /* OTP input row */
    .otp-row{display:flex;gap:8px;justify-content:center;margin:24px 0;}
    .otp-digit{
      width:52px;height:56px;
      text-align:center;
      font-size:24px;font-weight:800;
      background:rgba(255,255,255,.08);
      border:1.5px solid rgba(255,255,255,.18);
      border-radius:12px;
      color:#fff;
      transition:all .2s;
      font-family:var(--font);
      caret-color:var(--primary);
    }
    .otp-digit:focus{
      outline:none;
      border-color:var(--primary);
      background:rgba(255,255,255,.14);
      box-shadow:0 0 0 3px rgba(14,165,233,.2);
    }
    .otp-digit.filled{border-color:rgba(14,165,233,.5);background:rgba(14,165,233,.1);}
    .otp-digit.error {border-color:#f87171;animation:shake .4s ease;}
    @keyframes shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-6px);}75%{transform:translateX(6px);}}

    /* Submit btn */
    .btn-verify{
      width:100%;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border:none;border-radius:10px;
      color:#fff;font-size:15px;font-weight:700;
      padding:13px;cursor:pointer;
      transition:all .25s;
      box-shadow:0 4px 16px rgba(14,165,233,.35);
      font-family:var(--font);
    }
    .btn-verify:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(14,165,233,.5);}
    .btn-verify:disabled{opacity:.5;cursor:not-allowed;transform:none;}

    /* Timer */
    .timer-wrap{text-align:center;margin:16px 0;font-size:13px;color:rgba(255,255,255,.5);}
    .timer-count{color:var(--primary);font-weight:700;}
    .resend-link{color:var(--primary);font-weight:700;cursor:pointer;text-decoration:none;}
    .resend-link:hover{color:#38bdf8;}
    .resend-link.disabled{color:rgba(255,255,255,.3);cursor:not-allowed;pointer-events:none;}

    /* Alerts */
    .alert-error  {background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:10px;color:#fca5a5;padding:11px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px;}
    .alert-success{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);border-radius:10px;color:#4ade80;padding:11px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px;}

    /* Demo OTP banner */
    .demo-banner{
      background:rgba(245,158,11,.12);
      border:1px dashed rgba(245,158,11,.4);
      border-radius:10px;
      padding:10px 14px;
      font-size:12.5px;color:#fbbf24;
      text-align:center;margin-bottom:18px;
    }

    /* Back link */
    .back-link{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:18px;color:rgba(255,255,255,.4);font-size:13px;text-decoration:none;}
    .back-link:hover{color:var(--primary);}

    /* Steps indicator */
    .steps{display:flex;justify-content:center;gap:8px;margin-bottom:24px;}
    .step-dot{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.15);transition:all .3s;}
    .step-dot.done{background:var(--primary);}
    .step-dot.active{background:var(--primary);box-shadow:0 0 0 3px rgba(14,165,233,.3);transform:scale(1.2);}

    @media(max-width:480px){.otp-card{padding:32px 20px;}.otp-digit{width:42px;height:48px;font-size:20px;}}
  </style>
</head>
<body>
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <div class="otp-card">

    <!-- Brand -->
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-hospital-user"></i></div>
      <div>
        <div class="brand-name">MediCare HMS</div>
        <div class="brand-sub">Hospital Management System</div>
      </div>
    </div>

    <!-- Steps indicator -->
    <?php if ($context === 'register'): ?>
    <div class="steps">
      <div class="step-dot done" title="Account Details"></div>
      <div class="step-dot active" title="OTP Verification"></div>
      <div class="step-dot" title="Complete"></div>
    </div>
    <?php endif; ?>

    <!-- Icon -->
    <div class="otp-icon-wrap">
      <i class="fa-solid fa-shield-check"></i>
    </div>

    <h2><?= htmlspecialchars($contextTitle) ?></h2>
    <p class="subtitle"><?= htmlspecialchars($contextDesc) ?></p>
    <div class="text-center">
      <span class="email-badge">
        <i class="fa-solid fa-envelope"></i><?= htmlspecialchars($maskedEmail) ?>
      </span>
    </div>

    <?php if ($error): ?>
    <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert-success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Demo OTP display (remove in production) -->
    <?php if ($demoOtp): ?>
    <div class="demo-banner">
      <i class="fa-solid fa-flask me-2"></i>
      <strong>Demo Mode:</strong> Your OTP is <strong style="font-size:18px;letter-spacing:3px"><?= htmlspecialchars($demoOtp) ?></strong>
      <br><small style="opacity:.7">(Remove this banner in production — send via email/SMS)</small>
    </div>
    <?php endif; ?>

    <!-- OTP Form -->
    <form method="POST" id="otpForm" novalidate>
      <input type="hidden" name="otp_combined" id="otpCombined"/>

      <div class="otp-row" id="otpRow">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input type="text" name="otp_<?= $i ?>" class="otp-digit"
               maxlength="1" inputmode="numeric" pattern="[0-9]"
               autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>"
               id="otp<?= $i ?>"
               <?php if ($error): ?>value=""<?php endif; ?>/>
        <?php endfor; ?>
      </div>

      <!-- Timer + Resend -->
      <div class="timer-wrap">
        <span id="timerWrap">
          Resend OTP in <span class="timer-count" id="timerCount">02:00</span>
        </span>
        <span id="resendWrap" style="display:none">
          Didn't receive it?
          <a href="<?= APP_URL ?>/authentication/otp_verification.php?context=<?= $context ?>&resend=1"
             class="resend-link">Resend OTP</a>
        </span>
      </div>

      <button type="submit" class="btn-verify" id="verifyBtn" disabled>
        <i class="fa-solid fa-shield-check me-2"></i>Verify & Continue
      </button>
    </form>

    <!-- Back link -->
    <?php if ($context === 'register'): ?>
    <a href="<?= APP_URL ?>/register.php" class="back-link">
      <i class="fa-solid fa-arrow-left"></i> Back to Registration
    </a>
    <?php else: ?>
    <a href="<?= APP_URL ?>/authentication/forgot_password.php" class="back-link">
      <i class="fa-solid fa-arrow-left"></i> Back to Password Reset
    </a>
    <?php endif; ?>

  </div><!-- /.otp-card -->

  <script>
  // ── OTP input behaviour ────────────────────────────────────
  const inputs  = document.querySelectorAll('.otp-digit');
  const verifyBtn = document.getElementById('verifyBtn');
  const combined  = document.getElementById('otpCombined');

  inputs.forEach((inp, i) => {
    // Auto-advance on digit entry
    inp.addEventListener('input', function () {
      const val = this.value.replace(/\D/g, '');
      this.value = val.slice(-1);
      if (val) {
        this.classList.add('filled');
        if (i < inputs.length - 1) inputs[i + 1].focus();
      } else {
        this.classList.remove('filled');
      }
      syncCombined();
    });

    // Backspace → go back
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !this.value && i > 0) {
        inputs[i - 1].focus();
        inputs[i - 1].value = '';
        inputs[i - 1].classList.remove('filled');
        syncCombined();
      }
      // Allow only digits, backspace, delete, tab, arrows
      if (!/^\d$/.test(e.key) && !['Backspace','Delete','Tab','ArrowLeft','ArrowRight'].includes(e.key)) {
        e.preventDefault();
      }
    });

    // Paste full OTP
    inp.addEventListener('paste', function (e) {
      const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      if (pasted.length === 6) {
        [...pasted].forEach((ch, j) => {
          if (inputs[j]) {
            inputs[j].value = ch;
            inputs[j].classList.add('filled');
          }
        });
        inputs[5].focus();
        syncCombined();
        e.preventDefault();
      }
    });

    // Click → select content
    inp.addEventListener('click', function () { this.select(); });
  });

  function syncCombined() {
    const code = [...inputs].map(i => i.value).join('');
    combined.value = code;
    verifyBtn.disabled = code.length !== 6;
    if (code.length === 6) {
      verifyBtn.style.opacity = '1';
      inputs.forEach(i => i.classList.remove('error'));
    }
  }

  // ── Submit → show loading ──────────────────────────────────
  document.getElementById('otpForm').addEventListener('submit', function (e) {
    const code = [...inputs].map(i => i.value).join('');
    if (code.length !== 6) {
      e.preventDefault();
      inputs.forEach(i => { if (!i.value) i.classList.add('error'); });
      return;
    }
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Verifying…';
  });

  // ── Countdown timer ────────────────────────────────────────
  (function initTimer() {
    const DURATION = 120; // seconds
    let   remaining = DURATION;
    const countEl   = document.getElementById('timerCount');
    const timerWrap = document.getElementById('timerWrap');
    const resendWrap= document.getElementById('resendWrap');

    const tick = setInterval(() => {
      remaining--;
      const m = String(Math.floor(remaining / 60)).padStart(2, '0');
      const s = String(remaining % 60).padStart(2, '0');
      countEl.textContent = `${m}:${s}`;
      if (remaining <= 0) {
        clearInterval(tick);
        timerWrap.style.display  = 'none';
        resendWrap.style.display = '';
      }
    }, 1000);
  })();

  // Auto-focus first input
  document.addEventListener('DOMContentLoaded', () => inputs[0]?.focus());

  <?php if ($error): ?>
  // Shake effect on error
  document.querySelectorAll('.otp-digit').forEach(i => {
    i.classList.add('error');
    i.value = '';
    setTimeout(() => i.classList.remove('error'), 400);
  });
  inputs[0].focus();
  <?php endif; ?>
  </script>
</body>
</html>
