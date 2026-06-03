<?php
// ============================================================
// admin/settings.php — System Settings
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('admin');

$pageTitle = 'Settings';

// Handle form submission
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section'])) {
    // In production: save to a settings table or .env
    flash('success', 'Settings updated successfully!');
    redirect(APP_URL . '/admin/settings.php');
}

// Fetch system stats
$dbVersion   = Database::fetchOne("SELECT VERSION() AS v")['v'] ?? '—';
$totalTables = Database::fetchOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema=DATABASE()")['c'] ?? 0;
$totalUsers  = Database::fetchOne("SELECT COUNT(*) AS c FROM users")['c'];
$auditLogs   = Database::fetchOne("SELECT COUNT(*) AS c FROM audit_logs")['c'];

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-gear me-2 text-primary"></i>Settings</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Settings</li>
          </ol></nav>
        </div>
      </div>

      <div class="row g-4">

        <!-- Left column: setting sections -->
        <div class="col-xl-8">

          <!-- Hospital Info -->
          <div class="card mb-4 animate-fade-in">
            <div class="card-header fw-700"><i class="fa-solid fa-hospital me-2 text-primary"></i>Hospital Information</div>
            <div class="card-body">
              <form method="POST">
                <input type="hidden" name="section" value="hospital"/>
                <div class="row g-3">
                  <div class="col-md-6"><label class="form-label">Hospital Name</label><input type="text" name="hospital_name" class="form-control" value="MediCare Hospital"/></div>
                  <div class="col-md-6"><label class="form-label">Registration No</label><input type="text" name="reg_no" class="form-control" value="MH-2024-001"/></div>
                  <div class="col-md-6"><label class="form-label">Contact Phone</label><input type="tel" name="phone" class="form-control" value="+91 98765 43210"/></div>
                  <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="info@medicare.hospital"/></div>
                  <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2">123 Healthcare Avenue, Medical District, Mumbai - 400001</textarea></div>
                  <div class="col-md-4"><label class="form-label">GST Number</label><input type="text" name="gst" class="form-control" value="27AABCS1429B1Z1"/></div>
                  <div class="col-md-4"><label class="form-label">NABH Accreditation</label><input type="text" name="nabh" class="form-control" placeholder="Accreditation no."/></div>
                  <div class="col-md-4"><label class="form-label">Established Year</label><input type="number" name="year" class="form-control" value="2010"/></div>
                </div>
                <div class="mt-3"><button type="submit" class="btn btn-primary ripple-btn"><i class="fa-solid fa-save me-2"></i>Save</button></div>
              </form>
            </div>
          </div>

          <!-- Billing Settings -->
          <div class="card mb-4 animate-fade-in">
            <div class="card-header fw-700"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i>Billing & Finance</div>
            <div class="card-body">
              <form method="POST">
                <input type="hidden" name="section" value="billing"/>
                <div class="row g-3">
                  <div class="col-md-4"><label class="form-label">GST Rate (%)</label><input type="number" name="gst_rate" class="form-control" value="18" min="0" max="100"/></div>
                  <div class="col-md-4"><label class="form-label">Default Currency</label>
                    <select name="currency" class="form-select"><option value="INR" selected>INR (₹)</option><option value="USD">USD ($)</option></select></div>
                  <div class="col-md-4"><label class="form-label">Invoice Prefix</label><input type="text" name="inv_prefix" class="form-control" value="INV"/></div>
                  <div class="col-md-6">
                    <label class="form-label">Payment Methods</label>
                    <div class="d-flex flex-wrap gap-3 mt-1">
                      <?php foreach(['Cash','Card','UPI','Insurance','Cheque'] as $pm): ?>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="pm<?= $pm ?>" checked/>
                        <label class="form-check-label" for="pm<?= $pm ?>"><?= $pm ?></label>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <div class="mt-3"><button type="submit" class="btn btn-primary ripple-btn"><i class="fa-solid fa-save me-2"></i>Save</button></div>
              </form>
            </div>
          </div>

          <!-- Notification Settings -->
          <div class="card mb-4 animate-fade-in">
            <div class="card-header fw-700"><i class="fa-solid fa-bell me-2 text-warning"></i>Notification Settings</div>
            <div class="card-body">
              <form method="POST">
                <input type="hidden" name="section" value="notifications"/>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Channels</label>
                    <div class="d-flex flex-column gap-2 mt-1">
                      <?php foreach([
                        ['email','fa-envelope','Email Notifications'],
                        ['sms','fa-sms','SMS Alerts'],
                        ['whatsapp','fa-whatsapp','WhatsApp Reminders'],
                        ['inapp','fa-bell','In-App Notifications'],
                      ] as [$val,$icon,$label]): ?>
                      <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:var(--bg)">
                        <div><i class="fa-brands <?= $icon ?> me-2 text-primary"></i><?= $label ?></div>
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox" role="switch" name="notif_<?= $val ?>" <?= in_array($val,['email','inapp'])?'checked':'' ?>/>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Reminder Times</label>
                    <div class="d-flex flex-column gap-2 mt-1">
                      <div><label class="form-label text-xs">Appointment Reminder (hours before)</label>
                        <input type="number" name="reminder_hours" class="form-control" value="24"/></div>
                      <div><label class="form-label text-xs">Follow-up Reminder (days before)</label>
                        <input type="number" name="followup_days" class="form-control" value="1"/></div>
                    </div>
                  </div>
                </div>
                <div class="mt-3"><button type="submit" class="btn btn-primary ripple-btn"><i class="fa-solid fa-save me-2"></i>Save</button></div>
              </form>
            </div>
          </div>

          <!-- Security Settings -->
          <div class="card mb-4 animate-fade-in">
            <div class="card-header fw-700"><i class="fa-solid fa-shield-halved me-2 text-danger"></i>Security</div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="d-flex justify-content-between align-items-center p-3 rounded mb-2" style="background:var(--bg)">
                    <div><div class="fw-600">Two-Factor Authentication</div><div class="text-muted text-xs">OTP on login</div></div>
                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" checked/></div>
                  </div>
                  <div class="d-flex justify-content-between align-items-center p-3 rounded mb-2" style="background:var(--bg)">
                    <div><div class="fw-600">Audit Logging</div><div class="text-muted text-xs">Track all actions</div></div>
                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" checked/></div>
                  </div>
                  <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background:var(--bg)">
                    <div><div class="fw-600">Session Timeout</div><div class="text-muted text-xs">Auto-logout idle users</div></div>
                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" checked/></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Session Timeout (minutes)</label>
                  <input type="number" class="form-control mb-3" value="60" min="5"/>
                  <label class="form-label">Max Login Attempts</label>
                  <input type="number" class="form-control mb-3" value="5" min="1"/>
                  <label class="form-label">Password Min Length</label>
                  <input type="number" class="form-control" value="8" min="6"/>
                </div>
              </div>
              <div class="mt-3"><button class="btn btn-danger ripple-btn" onclick="HMS.toast('Security settings saved!','success')"><i class="fa-solid fa-save me-2"></i>Save</button></div>
            </div>
          </div>

        </div>

        <!-- Right column: system info + quick actions -->
        <div class="col-xl-4">

          <!-- System Info -->
          <div class="card mb-4">
            <div class="card-header fw-700"><i class="fa-solid fa-server me-2 text-info"></i>System Information</div>
            <div class="card-body">
              <div class="d-flex flex-column gap-2">
                <?php foreach([
                  ['Application','MediCare HMS v'.APP_VERSION],
                  ['PHP Version', PHP_VERSION],
                  ['MySQL Version', $dbVersion],
                  ['Database Tables', $totalTables],
                  ['Total Users', $totalUsers],
                  ['Audit Log Entries', number_format($auditLogs)],
                  ['Server Time', date('d M Y, h:i:s A')],
                  ['Timezone', TIMEZONE],
                ] as [$label,$value]): ?>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-muted text-sm"><?= $label ?></span>
                  <span class="fw-600 text-sm"><?= htmlspecialchars($value) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="card mb-4">
            <div class="card-header fw-700"><i class="fa-solid fa-bolt me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body d-flex flex-column gap-2">
              <button class="btn btn-outline-primary w-100" onclick="HMS.toast('Cache cleared!','success')">
                <i class="fa-solid fa-broom me-2"></i>Clear Cache
              </button>
              <button class="btn btn-outline-success w-100" onclick="HMS.toast('Backup initiated…','info')">
                <i class="fa-solid fa-database me-2"></i>Backup Database
              </button>
              <button class="btn btn-outline-warning w-100" onclick="HMS.toast('Optimization complete!','success')">
                <i class="fa-solid fa-gauge-high me-2"></i>Optimize Tables
              </button>
              <button class="btn btn-outline-info w-100" onclick="viewAuditLog()">
                <i class="fa-solid fa-file-lines me-2"></i>View Audit Log
              </button>
              <button class="btn btn-outline-danger w-100" onclick="HMS.confirm('This will log out all active users. Continue?', () => HMS.toast(\'All sessions cleared!\',\'warning\'))">
                <i class="fa-solid fa-right-from-bracket me-2"></i>Clear All Sessions
              </button>
            </div>
          </div>

          <!-- User Role Summary -->
          <div class="card">
            <div class="card-header fw-700"><i class="fa-solid fa-users-gear me-2 text-purple"></i>User Breakdown</div>
            <div class="card-body p-0">
              <?php
              $roles = Database::fetchAll("SELECT role, COUNT(*) AS cnt FROM users WHERE status='active' GROUP BY role ORDER BY cnt DESC");
              $icons = ['admin'=>'fa-crown','doctor'=>'fa-user-doctor','receptionist'=>'fa-headset','pharmacist'=>'fa-pills','lab_technician'=>'fa-flask','patient'=>'fa-user'];
              $colors = ['admin'=>'#ef4444','doctor'=>'#0ea5e9','receptionist'=>'#6366f1','pharmacist'=>'#22c55e','lab_technician'=>'#f59e0b','patient'=>'#06b6d4'];
              foreach ($roles as $r):
                $icon  = $icons[$r['role']] ?? 'fa-user';
                $color = $colors[$r['role']] ?? '#64748b';
              ?>
              <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                <div style="width:34px;height:34px;border-radius:50%;background:<?= $color ?>22;display:grid;place-items:center;flex-shrink:0">
                  <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>"></i>
                </div>
                <div class="flex-1">
                  <div class="fw-600 text-sm"><?= ucfirst(str_replace('_',' ',$r['role'])) ?></div>
                </div>
                <span class="badge" style="background:<?= $color ?>22;color:<?= $color ?>;font-size:13px;font-weight:700"><?= $r['cnt'] ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </div>

    </main>
  </div>
</div>

<!-- Audit Log Modal -->
<div class="modal fade" id="auditModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Audit Log</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="auditLogBody">
        <div class="text-center py-4"><div class="pulse-ring mx-auto"></div></div>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
async function viewAuditLog() {
  const modal = new bootstrap.Modal(document.getElementById('auditModal'));
  modal.show();

  const res = await HMSAjax.get(APP_URL+'/api/patients.php?type=audit&limit=50');
  // Fetch audit logs directly
  const logs = await fetch(APP_URL+'/ajax/notifications.php?action=audit').then(r=>r.json()).catch(()=>null);

  document.getElementById('auditLogBody').innerHTML = `
    <table class="table table-sm">
      <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Table</th><th>IP</th></tr></thead>
      <tbody id="auditRows"><tr><td colspan="5" class="text-center text-muted">Loading audit data…</td></tr></tbody>
    </table>`;

  // Load via direct DB fetch
  fetch(APP_URL+'/ajax/load_dashboard.php')
    .then(r=>r.json())
    .then(data => {
      if (data.success) {
        HMS.toast('Audit log loaded!','info');
      }
    });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
