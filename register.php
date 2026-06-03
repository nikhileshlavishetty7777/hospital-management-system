<?php
// ============================================================
// register.php — Multi-Role Public Registration
// Matches login.php glassmorphism design exactly
// ============================================================
require_once __DIR__ . '/config/config.php';

// Already logged in → go to dashboard
if (Auth::check()) redirect(Auth::dashboardUrl());

// Only these roles can self-register publicly
$publicRoles = [
    'patient'      => ['label'=>'Patient',       'icon'=>'fa-user-injured',  'color'=>'#06b6d4', 'desc'=>'Book appointments & manage health records'],
    'doctor'       => ['label'=>'Doctor',        'icon'=>'fa-user-doctor',   'color'=>'#0ea5e9', 'desc'=>'Manage patients, prescriptions & schedule'],
    'receptionist' => ['label'=>'Receptionist',  'icon'=>'fa-headset',       'color'=>'#6366f1', 'desc'=>'Handle appointments, billing & registration'],
];

$selectedRole = clean($_GET['role'] ?? '');
if ($selectedRole && !array_key_exists($selectedRole, $publicRoles)) $selectedRole = '';

$error   = '';
$success = '';

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = clean($_POST['role']     ?? '');
    $fullName = clean($_POST['full_name'] ?? '');
    $email    = clean($_POST['email']    ?? '');
    $phone    = clean($_POST['phone']    ?? '');
    $password = $_POST['password']        ?? '';
    $confirm  = $_POST['confirm_password']?? '';
    $gender   = clean($_POST['gender']   ?? '');

    // ── Validation ───────────────────────────────────────────
    if (!array_key_exists($role, $publicRoles)) {
        $error = 'Please select a valid role.';
    } elseif (strlen($fullName) < 3) {
        $error = 'Full name must be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($phone) < 10) {
        $error = 'Please enter a valid phone number.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$gender) {
        $error = 'Please select your gender.';
    } else {
        // ── Duplicate email check ────────────────────────────
        $existing = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'This email address is already registered. Please login instead.';
        } else {
            // ── Handle avatar upload ─────────────────────────
            $avatarPath = null;
            if (!empty($_FILES['avatar']['name'])) {
                $avatarPath = handleUpload($_FILES['avatar'], 'avatars');
                // avatarPath is null if upload failed — non-fatal
            }

            // ── Store pending registration in session for OTP ─
            $_SESSION['pending_reg'] = [
                'role'      => $role,
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => $phone,
                'password'  => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'gender'    => $gender,
                'dob'       => clean($_POST['dob'] ?? ''),
                'avatar'    => $avatarPath,
                // Extra fields per role
                'specialization'  => clean($_POST['specialization']  ?? ''),
                'qualification'   => clean($_POST['qualification']   ?? ''),
                'department_id'   => (int)($_POST['department_id']   ?? 0),
                'experience_years'=> (int)($_POST['experience_years']?? 0),
                'consultation_fee'=> (float)($_POST['consultation_fee'] ?? 500),
            ];

            // ── Generate + save OTP ──────────────────────────
            $otp = Auth::generateOTP();
            $_SESSION['reg_otp']         = password_hash($otp, PASSWORD_BCRYPT);
            $_SESSION['reg_otp_expires']  = time() + 900; // 15 minutes
            $_SESSION['reg_otp_email']    = $email;

            // In production: send via email/SMS. Demo: pass in URL for testing.
            $_SESSION['reg_demo_otp'] = $otp; // remove in production

            auditLog('REGISTER_ATTEMPT', 'users', 0, [], ['email' => $email, 'role' => $role]);

            redirect(APP_URL . '/authentication/otp_verification.php?context=register');
        }
    }
}

$departments = Database::fetchAll("SELECT id, name FROM departments WHERE status=1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register — <?= APP_NAME ?></title>
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
      padding:24px 16px;
      position:relative;overflow-x:hidden;
    }

    /* Animated background blobs */
    .blob{position:absolute;border-radius:50%;filter:blur(80px);opacity:.2;pointer-events:none;animation:blobFloat 8s ease-in-out infinite;}
    .blob-1{width:400px;height:400px;background:#0ea5e9;top:-100px;left:-100px;}
    .blob-2{width:300px;height:300px;background:#6366f1;bottom:-80px;right:-60px;animation-delay:3s;}
    .blob-3{width:200px;height:200px;background:#8b5cf6;top:40%;right:10%;animation-delay:5s;}
    @keyframes blobFloat{0%,100%{transform:translate(0,0) scale(1);}33%{transform:translate(20px,-20px) scale(1.05);}66%{transform:translate(-15px,15px) scale(.95);}}

    /* Card */
    .reg-card{
      width:100%;max-width:580px;
      background:rgba(255,255,255,.07);
      backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
      border:1px solid rgba(255,255,255,.12);
      border-radius:24px;
      padding:40px 38px;
      box-shadow:0 25px 50px rgba(0,0,0,.4);
      position:relative;z-index:10;
      animation:fadeInUp .6s cubic-bezier(.16,1,.3,1);
    }
    @keyframes fadeInUp{from{opacity:0;transform:translateY(32px);}to{opacity:1;transform:translateY(0);}}

    /* Brand header */
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
    .brand-icon{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--secondary));display:grid;place-items:center;font-size:20px;color:#fff;box-shadow:0 6px 20px rgba(14,165,233,.4);flex-shrink:0;}
    .brand-name{font-size:18px;font-weight:800;color:#fff;}
    .brand-sub{font-size:10px;color:rgba(255,255,255,.5);letter-spacing:.6px;text-transform:uppercase;}

    h2{font-size:21px;font-weight:800;color:#fff;margin-bottom:4px;}
    .subtitle{font-size:13px;color:rgba(255,255,255,.55);margin-bottom:24px;}

    /* Role selector */
    .role-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px;}
    .role-card{
      border:1.5px solid rgba(255,255,255,.12);
      border-radius:12px;padding:14px 10px;text-align:center;
      cursor:pointer;transition:all .2s;
      background:rgba(255,255,255,.04);
    }
    .role-card:hover{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.25);}
    .role-card.selected{background:rgba(14,165,233,.15);border-color:var(--primary);box-shadow:0 0 0 2px rgba(14,165,233,.3);}
    .role-card i{font-size:22px;margin-bottom:8px;display:block;}
    .role-card .role-name{font-size:12px;font-weight:700;color:#fff;}
    .role-card .role-desc{font-size:10px;color:rgba(255,255,255,.45);margin-top:3px;line-height:1.3;}

    /* Form controls */
    .section-title{font-size:11px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:1px;margin:20px 0 12px;display:flex;align-items:center;gap:8px;}
    .section-title::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.08);}

    label{font-size:12.5px;font-weight:600;color:rgba(255,255,255,.8);margin-bottom:6px;display:block;}
    .form-control,.form-select{
      background:rgba(255,255,255,.08);
      border:1.5px solid rgba(255,255,255,.15);
      border-radius:10px;color:#fff;
      padding:10px 14px;font-size:13.5px;
      font-family:var(--font);
      transition:all .2s;
      width:100%;
    }
    .form-control::placeholder{color:rgba(255,255,255,.3);}
    .form-control:focus,.form-select:focus{
      background:rgba(255,255,255,.12);
      border-color:var(--primary);
      box-shadow:0 0 0 3px rgba(14,165,233,.18);
      color:#fff;outline:none;
    }
    .form-select option{background:#1e293b;color:#fff;}
    .form-control.is-invalid{border-color:#f87171;}
    .invalid-feedback-custom{color:#fca5a5;font-size:11.5px;margin-top:4px;}

    /* Input with icon */
    .input-wrap{position:relative;}
    .input-wrap .icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.35);font-size:13px;pointer-events:none;}
    .input-wrap .form-control{padding-left:38px;}
    .input-wrap .toggle-pw{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:13px;padding:0;}

    /* Avatar upload */
    .avatar-upload{
      display:flex;align-items:center;gap:14px;
      padding:14px;border:1.5px dashed rgba(255,255,255,.2);
      border-radius:12px;cursor:pointer;transition:all .2s;
    }
    .avatar-upload:hover{border-color:rgba(14,165,233,.5);background:rgba(14,165,233,.04);}
    .avatar-preview{
      width:52px;height:52px;border-radius:50%;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      display:grid;place-items:center;font-size:20px;color:#fff;
      overflow:hidden;flex-shrink:0;
    }
    .avatar-preview img{width:100%;height:100%;object-fit:cover;}

    /* Password strength */
    .pw-strength{height:4px;border-radius:2px;margin-top:6px;transition:all .3s;background:rgba(255,255,255,.1);}
    .pw-strength-text{font-size:10.5px;margin-top:4px;}

    /* Submit btn */
    .btn-register{
      width:100%;
      background:linear-gradient(135deg,var(--primary),var(--secondary));
      border:none;border-radius:10px;
      color:#fff;font-size:15px;font-weight:700;
      padding:13px;cursor:pointer;
      transition:all .25s;
      box-shadow:0 4px 16px rgba(14,165,233,.35);
      margin-top:4px;
      font-family:var(--font);
    }
    .btn-register:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(14,165,233,.5);}
    .btn-register:disabled{opacity:.6;cursor:not-allowed;transform:none;}

    .login-link{text-align:center;margin-top:18px;font-size:13px;color:rgba(255,255,255,.5);}
    .login-link a{color:var(--primary);text-decoration:none;font-weight:600;}
    .login-link a:hover{color:#38bdf8;}

    /* Alert */
    .alert-error{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:10px;color:#fca5a5;padding:11px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px;}
    .alert-success{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);border-radius:10px;color:#4ade80;padding:11px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px;}

    /* Role-specific extra fields */
    .role-extra{display:none;}
    .role-extra.active{display:block;animation:fadeInUp .3s ease;}

    @media(max-width:520px){
      .reg-card{padding:28px 20px;}
      .role-grid{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <div class="reg-card">

    <!-- Brand -->
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-hospital-user"></i></div>
      <div>
        <div class="brand-name">MediCare HMS</div>
        <div class="brand-sub">Hospital Management System</div>
      </div>
    </div>

    <h2>Create your account</h2>
    <p class="subtitle">Join MediCare — select your role to get started</p>

    <?php if ($error): ?>
    <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" id="regForm" novalidate>

      <!-- ── Role Selection ──────────────────────────────────── -->
      <div class="section-title"><i class="fa-solid fa-user-tag me-1"></i> Select Role</div>
      <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($selectedRole) ?>"/>
      <div class="role-grid">
        <?php foreach ($publicRoles as $roleKey => $roleInfo): ?>
        <div class="role-card <?= $selectedRole === $roleKey ? 'selected' : '' ?>"
             onclick="selectRole('<?= $roleKey ?>')"
             style="--role-color:<?= $roleInfo['color'] ?>">
          <i class="fa-solid <?= $roleInfo['icon'] ?>" style="color:<?= $selectedRole === $roleKey ? $roleInfo['color'] : 'rgba(255,255,255,.5)' ?>"></i>
          <div class="role-name"><?= $roleInfo['label'] ?></div>
          <div class="role-desc"><?= $roleInfo['desc'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ── Personal Information ────────────────────────────── -->
      <div class="section-title"><i class="fa-solid fa-user me-1"></i> Personal Information</div>
      <div class="row g-3 mb-3">
        <div class="col-12">
          <label>Full Name *</label>
          <div class="input-wrap">
            <i class="fa-solid fa-user icon"></i>
            <input type="text" name="full_name" class="form-control" required
                   placeholder="Dr. / Mr. / Ms. Full Name"
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                   minlength="3"/>
          </div>
        </div>
        <div class="col-md-6">
          <label>Email Address *</label>
          <div class="input-wrap">
            <i class="fa-solid fa-envelope icon"></i>
            <input type="email" name="email" class="form-control" required
                   placeholder="your@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
          </div>
        </div>
        <div class="col-md-6">
          <label>Phone Number *</label>
          <div class="input-wrap">
            <i class="fa-solid fa-phone icon"></i>
            <input type="tel" name="phone" class="form-control" required
                   placeholder="+91 98765 43210"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"/>
          </div>
        </div>
        <div class="col-md-6">
          <label>Date of Birth</label>
          <div class="input-wrap">
            <i class="fa-solid fa-calendar icon"></i>
            <input type="date" name="dob" class="form-control"
                   max="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>"/>
          </div>
        </div>
        <div class="col-md-6">
          <label>Gender *</label>
          <select name="gender" class="form-select" required>
            <option value="">Select gender</option>
            <option value="male"   <?= ($_POST['gender'] ?? '')==='male'   ?'selected':'' ?>>Male</option>
            <option value="female" <?= ($_POST['gender'] ?? '')==='female' ?'selected':'' ?>>Female</option>
            <option value="other"  <?= ($_POST['gender'] ?? '')==='other'  ?'selected':'' ?>>Other</option>
          </select>
        </div>
      </div>

      <!-- ── Profile Photo ───────────────────────────────────── -->
      <div class="section-title"><i class="fa-solid fa-camera me-1"></i> Profile Photo (Optional)</div>
      <div class="avatar-upload mb-3" onclick="document.getElementById('avatarInput').click()">
        <div class="avatar-preview" id="avatarPreview">
          <i class="fa-solid fa-user"></i>
        </div>
        <div>
          <div style="color:#fff;font-weight:600;font-size:13px;">Upload Profile Photo</div>
          <div style="color:rgba(255,255,255,.4);font-size:11px;margin-top:2px;">JPG, PNG or WebP — max 5 MB</div>
        </div>
        <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp"
               style="display:none" onchange="previewAvatar(this)"/>
      </div>

      <!-- ── Doctor-specific fields ──────────────────────────── -->
      <div class="role-extra <?= $selectedRole==='doctor'?'active':'' ?>" id="extraDoctor">
        <div class="section-title"><i class="fa-solid fa-stethoscope me-1"></i> Professional Details</div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label>Department *</label>
            <select name="department_id" class="form-select">
              <option value="">Select Department</option>
              <?php foreach ($departments as $dep): ?>
              <option value="<?= $dep['id'] ?>" <?= ($_POST['department_id'] ?? 0)==$dep['id']?'selected':'' ?>><?= htmlspecialchars($dep['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>Specialization *</label>
            <div class="input-wrap">
              <i class="fa-solid fa-heart-pulse icon"></i>
              <input type="text" name="specialization" class="form-control" placeholder="e.g. Cardiologist"
                     value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>"/>
            </div>
          </div>
          <div class="col-md-6">
            <label>Qualification</label>
            <div class="input-wrap">
              <i class="fa-solid fa-graduation-cap icon"></i>
              <input type="text" name="qualification" class="form-control" placeholder="e.g. MBBS, MD, DM"
                     value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>"/>
            </div>
          </div>
          <div class="col-md-3">
            <label>Experience (yrs)</label>
            <input type="number" name="experience_years" class="form-control" min="0" max="60"
                   value="<?= htmlspecialchars($_POST['experience_years'] ?? '0') ?>"/>
          </div>
          <div class="col-md-3">
            <label>Consult Fee (₹)</label>
            <input type="number" name="consultation_fee" class="form-control" min="0"
                   value="<?= htmlspecialchars($_POST['consultation_fee'] ?? '500') ?>"/>
          </div>
        </div>
      </div>

      <!-- ── Password ────────────────────────────────────────── -->
      <div class="section-title"><i class="fa-solid fa-lock me-1"></i> Set Password</div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label>Password *</label>
          <div class="input-wrap">
            <i class="fa-solid fa-lock icon"></i>
            <input type="password" name="password" id="pwField" class="form-control" required
                   placeholder="Minimum 8 characters" minlength="8"
                   oninput="checkStrength(this.value)"/>
            <button type="button" class="toggle-pw" onclick="togglePw('pwField','eyePw')">
              <i class="fa-solid fa-eye" id="eyePw"></i>
            </button>
          </div>
          <div class="pw-strength" id="pwStrengthBar"></div>
          <div class="pw-strength-text" id="pwStrengthText" style="color:rgba(255,255,255,.4)"></div>
        </div>
        <div class="col-md-6">
          <label>Confirm Password *</label>
          <div class="input-wrap">
            <i class="fa-solid fa-lock-open icon"></i>
            <input type="password" name="confirm_password" id="cpwField" class="form-control" required
                   placeholder="Repeat password"
                   oninput="checkMatch()"/>
            <button type="button" class="toggle-pw" onclick="togglePw('cpwField','eyeCpw')">
              <i class="fa-solid fa-eye" id="eyeCpw"></i>
            </button>
          </div>
          <div class="pw-strength-text" id="matchText" style="color:rgba(255,255,255,.4)"></div>
        </div>
      </div>

      <!-- Terms -->
      <div class="d-flex align-items-center gap-2 mb-3">
        <input type="checkbox" id="terms" name="terms" required
               style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer;flex-shrink:0"/>
        <label for="terms" style="margin-bottom:0;cursor:pointer;font-size:12.5px;color:rgba(255,255,255,.65);">
          I agree to the <a href="#" style="color:var(--primary)">Terms of Service</a> and <a href="#" style="color:var(--primary)">Privacy Policy</a>
        </label>
      </div>

      <button type="submit" class="btn-register" id="regBtn">
        <i class="fa-solid fa-user-plus me-2"></i>Create Account & Verify OTP
      </button>
    </form>

    <div class="login-link">
      Already have an account? <a href="<?= APP_URL ?>/login.php">Sign in here</a>
    </div>

  </div><!-- /.reg-card -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // ── Role selection ─────────────────────────────────────────
  const roleColors = <?= json_encode(array_map(fn($r)=>$r['color'],$publicRoles)) ?>;
  const roleIcons  = <?= json_encode(array_map(fn($r)=>$r['icon'],$publicRoles)) ?>;

  function selectRole(role) {
    document.getElementById('roleInput').value = role;

    // Update role cards
    document.querySelectorAll('.role-card').forEach(card => card.classList.remove('selected'));
    const cards = document.querySelectorAll('.role-card');
    const roleKeys = Object.keys(roleColors);
    cards.forEach((card, i) => {
      const rk = roleKeys[i];
      const icon = card.querySelector('i');
      if (rk === role) {
        card.classList.add('selected');
        icon.style.color = roleColors[rk];
      } else {
        icon.style.color = 'rgba(255,255,255,.5)';
      }
    });

    // Show/hide role-specific fields
    document.querySelectorAll('.role-extra').forEach(el => el.classList.remove('active'));
    const extra = document.getElementById('extra' + role.charAt(0).toUpperCase() + role.slice(1));
    if (extra) extra.classList.add('active');
  }

  // ── Avatar preview ─────────────────────────────────────────
  function previewAvatar(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById('avatarPreview');
      preview.innerHTML = `<img src="${e.target.result}" alt="avatar"/>`;
    };
    reader.readAsDataURL(input.files[0]);
  }

  // ── Password toggle ────────────────────────────────────────
  function togglePw(fieldId, eyeId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(eyeId);
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
  }

  // ── Password strength meter ────────────────────────────────
  function checkStrength(pw) {
    const bar  = document.getElementById('pwStrengthBar');
    const text = document.getElementById('pwStrengthText');
    let score  = 0;
    if (pw.length >= 8)               score++;
    if (/[A-Z]/.test(pw))             score++;
    if (/[0-9]/.test(pw))             score++;
    if (/[^A-Za-z0-9]/.test(pw))      score++;

    const levels = [
      { label:'Too short',  color:'#ef4444', w:'15%' },
      { label:'Weak',       color:'#f97316', w:'30%' },
      { label:'Fair',       color:'#f59e0b', w:'55%' },
      { label:'Good',       color:'#22c55e', w:'80%' },
      { label:'Strong',     color:'#0ea5e9', w:'100%'},
    ];
    const lvl  = pw.length === 0 ? null : levels[Math.min(score, 4)];
    bar.style.background  = lvl ? lvl.color : 'rgba(255,255,255,.1)';
    bar.style.width       = lvl ? lvl.w : '0';
    text.textContent      = lvl ? lvl.label : '';
    text.style.color      = lvl ? lvl.color : 'rgba(255,255,255,.4)';
    checkMatch();
  }

  function checkMatch() {
    const pw  = document.getElementById('pwField').value;
    const cpw = document.getElementById('cpwField').value;
    const el  = document.getElementById('matchText');
    if (!cpw) { el.textContent = ''; return; }
    if (pw === cpw) { el.textContent = '✓ Passwords match'; el.style.color = '#4ade80'; }
    else            { el.textContent = '✗ Passwords do not match'; el.style.color = '#f87171'; }
  }

  // ── Form submit loading state ──────────────────────────────
  document.getElementById('regForm').addEventListener('submit', function(e) {
    const role = document.getElementById('roleInput').value;
    if (!role) {
      e.preventDefault();
      alert('Please select a role before continuing.');
      return;
    }
    const pw  = document.getElementById('pwField').value;
    const cpw = document.getElementById('cpwField').value;
    if (pw !== cpw) {
      e.preventDefault();
      alert('Passwords do not match.');
      return;
    }
    const btn = document.getElementById('regBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing…';
  });

  // Pre-select role if provided via GET
  const preRole = '<?= htmlspecialchars($selectedRole) ?>';
  if (preRole) selectRole(preRole);
  </script>
</body>
</html>
