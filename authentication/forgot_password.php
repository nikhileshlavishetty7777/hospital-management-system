<?php
// ============================================================
// authentication/forgot_password.php — Forgot / Reset Password
// ============================================================
require_once __DIR__ . '/../config/config.php';
if (Auth::check()) redirect(Auth::dashboardUrl());

$step    = $_SESSION['fp_step'] ?? 1;   // 1=email, 2=otp, 3=new-password
$message = ['type' => '', 'text' => ''];

// ── Step 1: Enter email ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'send_otp') {
        $email = clean($_POST['email'] ?? '');
        $user  = Database::fetchOne("SELECT id, full_name FROM users WHERE email=? AND status='active'", [$email]);
        if (!$user) {
            $message = ['type'=>'danger', 'text'=>'No active account found with that email.'];
        } else {
            $otp = Auth::generateOTP();
            Auth::saveOTP($user['id'], $otp);
            $_SESSION['fp_user_id'] = $user['id'];
            $_SESSION['fp_step']    = 2;
            // In production: send via email/SMS. For demo we show it.
            $_SESSION['fp_demo_otp'] = $otp;
            $message = ['type'=>'success', 'text'=>"OTP sent! (Demo: {$otp})"];
            $step = 2;
        }
    }

    elseif ($_POST['action'] === 'verify_otp') {
        $otp    = clean($_POST['otp'] ?? '');
        $userId = $_SESSION['fp_user_id'] ?? 0;
        if (Auth::verifyOTP($userId, $otp)) {
            $_SESSION['fp_step'] = 3;
            $step = 3;
            $message = ['type'=>'success', 'text'=>'OTP verified! Set your new password.'];
        } else {
            $message = ['type'=>'danger', 'text'=>'Invalid or expired OTP.'];
        }
    }

    elseif ($_POST['action'] === 'reset_password') {
        $pw   = $_POST['password'] ?? '';
        $cpw  = $_POST['confirm_password'] ?? '';
        $userId = $_SESSION['fp_user_id'] ?? 0;
        if (!$userId) { redirect(APP_URL . '/authentication/forgot_password.php'); }
        if (strlen($pw) < 8) {
            $message = ['type'=>'danger', 'text'=>'Password must be at least 8 characters.'];
            $step = 3;
        } elseif ($pw !== $cpw) {
            $message = ['type'=>'danger', 'text'=>'Passwords do not match.'];
            $step = 3;
        } else {
            Database::query("UPDATE users SET password=? WHERE id=?",
                [password_hash($pw, PASSWORD_BCRYPT), $userId]);
            unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_demo_otp']);
            flash('success', 'Password reset successfully! Please log in.');
            redirect(APP_URL . '/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <style>
    body { font-family:'Plus Jakarta Sans',sans-serif; min-height:100vh; background:linear-gradient(135deg,#0f172a,#1e1b4b,#0c2340); display:flex; align-items:center; justify-content:center; padding:20px; }
    .fp-card { width:100%;max-width:420px; background:rgba(255,255,255,.08); backdrop-filter:blur(24px); border:1px solid rgba(255,255,255,.12); border-radius:24px; padding:40px 36px; box-shadow:0 25px 50px rgba(0,0,0,.4); animation:fadeUp .5s ease; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
    h2 { color:#fff;font-size:22px;font-weight:800;margin-bottom:6px; }
    .sub { color:rgba(255,255,255,.55);font-size:13px;margin-bottom:28px; }
    .form-label { color:rgba(255,255,255,.8);font-size:12.5px;font-weight:600; }
    .form-control { background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.15);border-radius:10px;color:#fff;padding:11px 14px;font-size:14px; }
    .form-control:focus { background:rgba(255,255,255,.12);border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,.2);color:#fff; }
    .form-control::placeholder { color:rgba(255,255,255,.35); }
    .btn-submit { width:100%;background:linear-gradient(135deg,#0ea5e9,#6366f1);border:none;border-radius:10px;color:#fff;font-size:15px;font-weight:700;padding:13px;cursor:pointer;transition:all .25s;box-shadow:0 4px 16px rgba(14,165,233,.4); }
    .btn-submit:hover { transform:translateY(-2px);box-shadow:0 8px 24px rgba(14,165,233,.5); }
    .back-link { color:rgba(255,255,255,.5);font-size:13px;text-decoration:none;display:flex;align-items:center;gap:6px;margin-top:16px; }
    .back-link:hover { color:#0ea5e9; }
    .step-indicator { display:flex;gap:8px;margin-bottom:24px;align-items:center;justify-content:center; }
    .step-dot { width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:700;transition:all .3s; }
    .step-dot.active { background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;box-shadow:0 4px 12px rgba(14,165,233,.4); }
    .step-dot.done   { background:#22c55e;color:#fff; }
    .step-dot.pending{ background:rgba(255,255,255,.1);color:rgba(255,255,255,.4); }
    .step-line { flex:1;height:2px;background:rgba(255,255,255,.1);border-radius:2px; }
    .step-line.done { background:#22c55e; }
    .otp-inputs { display:flex;gap:10px;justify-content:center;margin:16px 0; }
    .otp-inputs input { width:48px;height:52px;text-align:center;font-size:22px;font-weight:700;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.2);border-radius:10px;color:#fff; }
    .otp-inputs input:focus { border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,.2);outline:none; }
    .alert-msg { border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px; }
    .alert-success { background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);color:#4ade80; }
    .alert-danger  { background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5; }
  </style>
</head>
<body>
<div class="fp-card">
  <!-- Step indicator -->
  <div class="step-indicator">
    <div class="step-dot <?= $step==1?'active':($step>1?'done':'pending') ?>"><?= $step>1?'<i class="fa-solid fa-check"></i>':'1' ?></div>
    <div class="step-line <?= $step>1?'done':'' ?>"></div>
    <div class="step-dot <?= $step==2?'active':($step>2?'done':'pending') ?>"><?= $step>2?'<i class="fa-solid fa-check"></i>':'2' ?></div>
    <div class="step-line <?= $step>2?'done':'' ?>"></div>
    <div class="step-dot <?= $step==3?'active':'pending' ?>">3</div>
  </div>

  <?php if ($step == 1): ?>
  <h2>Forgot Password?</h2>
  <p class="sub">Enter your registered email and we'll send you a 6-digit OTP.</p>
  <?php elseif ($step == 2): ?>
  <h2>Verify OTP</h2>
  <p class="sub">Enter the 6-digit code sent to your email address.</p>
  <?php else: ?>
  <h2>New Password</h2>
  <p class="sub">Choose a strong password for your account.</p>
  <?php endif; ?>

  <?php if ($message['text']): ?>
  <div class="alert-msg alert-<?= $message['type'] ?>">
    <i class="fa-solid <?= $message['type']==='success'?'fa-circle-check':'fa-circle-xmark' ?>"></i>
    <?= htmlspecialchars($message['text']) ?>
  </div>
  <?php endif; ?>

  <!-- Step 1: Email -->
  <?php if ($step == 1): ?>
  <form method="POST">
    <input type="hidden" name="action" value="send_otp"/>
    <div class="mb-4">
      <label class="form-label">Email Address</label>
      <input type="email" name="email" class="form-control" required autofocus placeholder="Enter your registered email"/>
    </div>
    <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane me-2"></i>Send OTP</button>
  </form>

  <!-- Step 2: OTP -->
  <?php elseif ($step == 2): ?>
  <form method="POST" id="otpForm">
    <input type="hidden" name="action" value="verify_otp"/>
    <input type="hidden" name="otp" id="otpHidden"/>
    <div class="otp-inputs">
      <?php for($i=0;$i<6;$i++): ?>
      <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
      <?php endfor; ?>
    </div>
    <div class="text-center mb-3">
      <small style="color:rgba(255,255,255,.4)">Didn't receive? <a href="?" style="color:#0ea5e9">Resend</a></small>
    </div>
    <button type="submit" class="btn-submit"><i class="fa-solid fa-shield-check me-2"></i>Verify OTP</button>
  </form>

  <!-- Step 3: New password -->
  <?php else: ?>
  <form method="POST">
    <input type="hidden" name="action" value="reset_password"/>
    <div class="mb-3">
      <label class="form-label">New Password</label>
      <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters"/>
    </div>
    <div class="mb-4">
      <label class="form-label">Confirm Password</label>
      <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password"/>
    </div>
    <button type="submit" class="btn-submit"><i class="fa-solid fa-lock me-2"></i>Reset Password</button>
  </form>
  <?php endif; ?>

  <a href="<?= APP_URL ?>/login.php" class="back-link">
    <i class="fa-solid fa-arrow-left"></i> Back to Login
  </a>
</div>

<script>
  // OTP input auto-advance
  const digits = document.querySelectorAll('.otp-digit');
  digits.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      if (inp.value && i < digits.length - 1) digits[i + 1].focus();
      const val = [...digits].map(d => d.value).join('');
      const h = document.getElementById('otpHidden');
      if (h) h.value = val;
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && i > 0) digits[i - 1].focus();
    });
    inp.addEventListener('paste', e => {
      const txt = (e.clipboardData || window.clipboardData).getData('text').slice(0, 6);
      [...txt].forEach((c, j) => { if (digits[j]) digits[j].value = c; });
      const h = document.getElementById('otpHidden');
      if (h) h.value = txt;
      e.preventDefault();
    });
  });
</script>
</body>
</html>
