<?php
// ============================================================
// laboratory/uploads.php — Report Upload Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['lab_technician','admin']);

$pageTitle = 'Upload Reports';

// Orders awaiting upload (processing status)
$pendingUpload = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.created_at, lo.status,
           lt.name AS test_name, lt.category, lt.turnaround,
           u.full_name AS patient_name, p.patient_code
    FROM lab_orders lo
    JOIN lab_tests lt ON lt.id=lo.test_id
    JOIN patients  p  ON p.id=lo.patient_id  JOIN users u ON u.id=p.user_id
    WHERE lo.status IN ('processing','sample_collected')
    ORDER BY lo.created_at ASC
");

// Recently uploaded
$recentUploads = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.report_date, lo.report_file, lo.result,
           lt.name AS test_name,
           u.full_name AS patient_name, p.patient_code
    FROM lab_orders lo
    JOIN lab_tests lt ON lt.id=lo.test_id
    JOIN patients  p  ON p.id=lo.patient_id  JOIN users u ON u.id=p.user_id
    WHERE lo.status='completed' AND lo.report_file IS NOT NULL
    ORDER BY lo.report_date DESC LIMIT 15
");

$pendingCount = count($pendingUpload);
$uploadedToday = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='completed' AND report_file IS NOT NULL AND DATE(report_date)=CURDATE()")['c'];

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-upload me-2 text-primary"></i>Upload Reports</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/laboratory/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Uploads</li>
          </ol></nav>
        </div>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div><div class="stat-value" data-counter="<?= $pendingCount ?>">0</div><div class="stat-label">Awaiting Upload</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-cloud-upload-alt"></i></div>
            <div><div class="stat-value" data-counter="<?= $uploadedToday ?>">0</div><div class="stat-label">Uploaded Today</div></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card border-0 h-100" style="background:linear-gradient(135deg,rgba(14,165,233,.08),rgba(99,102,241,.08))">
            <div class="card-body d-flex align-items-center gap-3">
              <i class="fa-solid fa-circle-info fa-2x text-primary"></i>
              <div class="text-sm">Accepted formats: <strong>PDF, JPG, PNG</strong>. Max size: <strong>10 MB</strong> per file.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <!-- Pending Upload List -->
        <div class="col-xl-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-clock me-2 text-warning"></i>Awaiting Upload</span>
              <span class="badge bg-warning text-dark"><?= $pendingCount ?></span>
            </div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
              <?php if(empty($pendingUpload)): ?>
              <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-check-circle fa-3x text-success mb-3 d-block opacity-50"></i>
                All reports have been uploaded!
              </div>
              <?php else: ?>
              <?php foreach ($pendingUpload as $i => $order): ?>
              <div class="d-flex align-items-start gap-3 p-3 border-bottom hover-lift animate-fade-in delay-<?= min($i+1,8) ?>">
                <div class="flex-shrink-0 text-center" style="width:48px">
                  <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto"
                       style="width:42px;height:42px;background:rgba(245,158,11,.15)">
                    <i class="fa-solid fa-flask text-warning"></i>
                  </div>
                </div>
                <div class="flex-1 min-width-0">
                  <div class="fw-700 text-sm"><?= htmlspecialchars($order['test_name']) ?></div>
                  <div class="text-muted text-xs mt-1">
                    <span class="me-2"><i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($order['patient_name']) ?></span>
                    <span><i class="fa-solid fa-id-card me-1"></i><?= htmlspecialchars($order['patient_code']) ?></span>
                  </div>
                  <div class="text-muted text-xs">
                    <i class="fa-solid fa-clock me-1"></i>Ordered: <?= date('d M, h:i A', strtotime($order['created_at'])) ?>
                    · TAT: <?= $order['turnaround'] ?>h
                  </div>
                  <div class="mt-1">
                    <span class="status-badge status-<?= $order['status']==='processing'?'in_progress':'confirmed' ?>">
                      <?= ucfirst(str_replace('_',' ',$order['status'])) ?>
                    </span>
                    <span class="badge bg-secondary ms-1 text-xs"><?= htmlspecialchars($order['category']) ?></span>
                  </div>
                </div>
                <button class="btn btn-sm btn-primary flex-shrink-0" onclick="openUpload(<?= $order['id'] ?>,'<?= addslashes($order['test_name']) ?>')">
                  <i class="fa-solid fa-upload"></i>
                </button>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Recent Uploads + Drag-Drop Zone -->
        <div class="col-xl-6">
          <!-- Drag & Drop Upload Zone -->
          <div class="card mb-4" id="dropZone">
            <div class="card-body text-center p-5" style="border:2px dashed var(--border);border-radius:var(--radius-lg);cursor:pointer;transition:all .3s"
                 onclick="document.getElementById('bulkUpload').click()"
                 ondragover="handleDragOver(event)" ondrop="handleDrop(event)" ondragleave="handleDragLeave(event)">
              <i class="fa-solid fa-cloud-arrow-up fa-3x text-primary mb-3 animate-float"></i>
              <h6 class="fw-700">Drag & Drop Report Files</h6>
              <p class="text-muted text-sm">or click to select files (PDF, JPG, PNG — max 10MB each)</p>
              <input type="file" id="bulkUpload" multiple accept=".pdf,.jpg,.jpeg,.png" style="display:none" onchange="handleFileSelect(this.files)"/>
            </div>
          </div>

          <!-- Selected files queue -->
          <div id="uploadQueue" style="display:none" class="card mb-4">
            <div class="card-header fw-700"><i class="fa-solid fa-list me-2"></i>Upload Queue</div>
            <div class="card-body" id="uploadQueueBody"></div>
            <div class="card-footer border-0">
              <button class="btn btn-success w-100 ripple-btn" onclick="HMS.toast('Upload queue feature — link each file to an order.','info')">
                <i class="fa-solid fa-upload me-2"></i>Upload All
              </button>
            </div>
          </div>

          <!-- Recently Uploaded -->
          <div class="card">
            <div class="card-header fw-700"><i class="fa-solid fa-history me-2 text-success"></i>Recently Uploaded</div>
            <div class="list-group list-group-flush" style="max-height:320px;overflow-y:auto">
              <?php foreach ($recentUploads as $r): ?>
              <div class="list-group-item px-3 py-3">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-600 text-sm"><?= htmlspecialchars($r['test_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($r['patient_name']) ?> · <?= htmlspecialchars($r['patient_code']) ?></div>
                    <div class="text-muted text-xs mt-1">
                      <?= $r['report_date'] ? date('d M Y h:i A', strtotime($r['report_date'])) : '—' ?>
                    </div>
                  </div>
                  <div class="d-flex gap-1">
                    <a href="<?= APP_URL.'/assets/uploads/'.htmlspecialchars($r['report_file']) ?>" class="btn btn-sm btn-outline-success" target="_blank">
                      <i class="fa-solid fa-download"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-primary" onclick="HMS.toast('Viewing report…','info')">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if(empty($recentUploads)): ?>
              <div class="list-group-item text-muted text-sm text-center py-3">No uploads yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-upload me-2 text-success"></i>Upload Lab Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="uploadOrderId"/>
        <div class="alert alert-info py-2 mb-3">
          <i class="fa-solid fa-circle-info me-2"></i>
          <strong id="uploadTestLabel"></strong>
        </div>
        <div class="mb-3">
          <label class="form-label">Result / Values *</label>
          <textarea id="uploadResult" class="form-control" rows="4" placeholder="Enter test result values, reference ranges, interpretation…"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Report File (PDF / Image)</label>
          <input type="file" id="uploadFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png"/>
          <div class="text-muted text-xs mt-1">Max 10 MB. PDF preferred.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Technician Remarks</label>
          <textarea id="uploadRemarks" class="form-control" rows="2" placeholder="Additional observations, notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success ripple-btn" onclick="submitUpload()">
          <i class="fa-solid fa-upload me-2"></i>Upload & Complete
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

function openUpload(id, testName) {
  document.getElementById('uploadOrderId').value      = id;
  document.getElementById('uploadTestLabel').textContent = testName;
  document.getElementById('uploadResult').value       = '';
  document.getElementById('uploadRemarks').value      = '';
  document.getElementById('uploadFile').value         = '';
  new bootstrap.Modal(document.getElementById('uploadModal')).show();
}

async function submitUpload() {
  const id      = document.getElementById('uploadOrderId').value;
  const result  = document.getElementById('uploadResult').value.trim();
  const remarks = document.getElementById('uploadRemarks').value;
  const fileEl  = document.getElementById('uploadFile');

  if (!result) { HMS.toast('Please enter result summary.','warning'); return; }

  const fd = new FormData();
  fd.append('result',  result);
  fd.append('remarks', remarks);
  if (fileEl.files[0]) fd.append('report_file', fileEl.files[0]);

  const res = await HMSAjax.post(APP_URL+'/api/reports.php?id='+id, fd);
  if (res.success) {
    HMS.toast('Report uploaded and marked complete!','success');
    bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
    setTimeout(() => location.reload(), 800);
  }
}

// Drag and Drop handlers
function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('dropZone').querySelector('.card-body').style.background = 'rgba(14,165,233,.08)';
}
function handleDragLeave(e) {
  document.getElementById('dropZone').querySelector('.card-body').style.background = '';
}
function handleDrop(e) {
  e.preventDefault();
  handleDragLeave(e);
  handleFileSelect(e.dataTransfer.files);
}

function handleFileSelect(files) {
  if (!files.length) return;
  const queue = document.getElementById('uploadQueue');
  const body  = document.getElementById('uploadQueueBody');
  queue.style.display = '';
  body.innerHTML = [...files].map((f,i) => `
    <div class="d-flex align-items-center gap-3 mb-2 p-2 rounded" style="background:var(--bg)">
      <i class="fa-solid ${f.type==='application/pdf'?'fa-file-pdf text-danger':'fa-file-image text-primary'}"></i>
      <div class="flex-1">
        <div class="fw-600 text-sm">${f.name}</div>
        <div class="text-muted text-xs">${(f.size/1024/1024).toFixed(2)} MB</div>
      </div>
      <span class="badge bg-warning text-dark text-xs">Not linked</span>
    </div>`
  ).join('');
  HMS.toast(files.length+' file(s) selected. Link each to a lab order to upload.','info');
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
