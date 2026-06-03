<?php
// ============================================================
// admin/billing.php — Billing & Invoice Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['admin','receptionist']);

$pageTitle = 'Billing & Finance';

// Stats
$todayRevenue = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM invoices WHERE DATE(created_at)=CURDATE()")['c'];
$monthRevenue = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM invoices WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'];
$pendingAmt   = Database::fetchOne("SELECT COALESCE(SUM(balance),0) AS c FROM invoices WHERE payment_status!='paid'")['c'];
$totalInvoices= Database::fetchOne("SELECT COUNT(*) AS c FROM invoices WHERE DATE(created_at)=CURDATE()")['c'];

// Invoice list
$invoices = Database::fetchAll("
    SELECT i.id, i.invoice_no, i.subtotal, i.discount, i.tax, i.total, i.paid, i.balance,
           i.payment_method, i.payment_status, i.created_at,
           u.full_name AS patient_name, p.patient_code
    FROM invoices i
    JOIN patients p ON p.id = i.patient_id
    JOIN users    u ON u.id = p.user_id
    ORDER BY i.id DESC LIMIT 50
");

// Departments for dropdown
$departments = Database::fetchAll("SELECT id, name FROM departments WHERE status=1 ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Billing & Finance</h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
              <li class="breadcrumb-item active">Billing</li>
            </ol>
          </nav>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" onclick="HMS.toast('Export coming soon','info')">
            <i class="fa-solid fa-file-export me-2"></i>Export
          </button>
          <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
            <i class="fa-solid fa-plus me-2"></i>Create Invoice
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card card-green hover-lift">
            <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div><div class="stat-value" data-counter="<?= number_format($todayRevenue,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Today's Revenue</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-blue hover-lift">
            <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div><div class="stat-value" data-counter="<?= number_format($monthRevenue,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Monthly Revenue</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-orange hover-lift">
            <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div><div class="stat-value" data-counter="<?= number_format($pendingAmt,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Pending Amount</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-purple hover-lift">
            <div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalInvoices ?>">0</div><div class="stat-label">Invoices Today</div></div>
          </div>
        </div>
      </div>

      <!-- Invoice table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-list me-2 text-primary"></i>All Invoices</span>
          <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="statusFilter" onchange="filterInvoices()" style="width:140px">
              <option value="">All Status</option>
              <option value="paid">Paid</option>
              <option value="partial">Partial</option>
              <option value="pending">Pending</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="billingTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Invoice No</th>
                  <th>Patient</th>
                  <th>Subtotal</th>
                  <th>Discount</th>
                  <th>Tax (GST)</th>
                  <th>Total</th>
                  <th>Paid</th>
                  <th>Balance</th>
                  <th>Method</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($invoices as $i => $inv): ?>
                <tr data-status="<?= $inv['payment_status'] ?>">
                  <td><?= $i+1 ?></td>
                  <td><span class="text-mono fw-600 text-primary"><?= htmlspecialchars($inv['invoice_no']) ?></span></td>
                  <td>
                    <div class="fw-600 text-sm"><?= htmlspecialchars($inv['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($inv['patient_code']) ?></div>
                  </td>
                  <td>₹<?= number_format($inv['subtotal'],2) ?></td>
                  <td class="text-success">-₹<?= number_format($inv['discount'],2) ?></td>
                  <td>₹<?= number_format($inv['tax'],2) ?></td>
                  <td class="fw-700">₹<?= number_format($inv['total'],2) ?></td>
                  <td class="text-success fw-600">₹<?= number_format($inv['paid'],2) ?></td>
                  <td class="<?= $inv['balance']>0?'text-danger':'text-muted' ?> fw-600">₹<?= number_format($inv['balance'],2) ?></td>
                  <td><span class="badge bg-secondary"><?= strtoupper($inv['payment_method']) ?></span></td>
                  <td>
                    <?php
                    $cls = ['paid'=>'completed','partial'=>'in_progress','pending'=>'booked','refunded'=>'cancelled'][$inv['payment_status']] ?? 'booked';
                    ?>
                    <span class="status-badge status-<?= $cls ?>"><?= ucfirst($inv['payment_status']) ?></span>
                  </td>
                  <td class="text-muted text-xs"><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" title="View" onclick="viewInvoice(<?= $inv['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                      <button class="btn btn-sm btn-outline-success" title="Print PDF" onclick="printInvoice(<?= $inv['id'] ?>)"><i class="fa-solid fa-print"></i></button>
                      <?php if($inv['payment_status'] !== 'paid'): ?>
                      <button class="btn btn-sm btn-outline-warning" title="Record Payment" onclick="recordPayment(<?= $inv['id'] ?>, <?= $inv['balance'] ?>)"><i class="fa-solid fa-indian-rupee-sign"></i></button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ── Create Invoice Modal ── -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Create Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Patient *</label>
            <input type="text" id="invoicePatientSearch" class="form-control" placeholder="Search patient by name or code…"/>
            <input type="hidden" id="invoicePatientId"/>
            <div id="invoicePatientResults" class="mt-1"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Payment Method</label>
            <select id="invPayMethod" class="form-select">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="upi">UPI</option>
              <option value="insurance">Insurance</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">GST Number</label>
            <input type="text" id="invGst" class="form-control" placeholder="Optional"/>
          </div>
        </div>

        <!-- Line items -->
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong>Invoice Items</strong>
          <button class="btn btn-sm btn-outline-primary" onclick="addInvoiceItem()"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
        </div>
        <div id="invoiceItems">
          <!-- JS will render rows -->
        </div>

        <hr/>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Discount (₹)</label>
            <input type="number" id="invDiscount" class="form-control" value="0" min="0" oninput="recalcInvoice()"/>
          </div>
          <div class="col-md-4">
            <label class="form-label">Amount Paid (₹)</label>
            <input type="number" id="invPaid" class="form-control" value="0" min="0" oninput="recalcInvoice()"/>
          </div>
          <div class="col-md-4">
            <label class="form-label">Notes</label>
            <input type="text" id="invNotes" class="form-control" placeholder="Optional"/>
          </div>
        </div>

        <!-- Totals -->
        <div class="card mt-3" style="background:var(--bg)">
          <div class="card-body py-3">
            <div class="row text-sm">
              <div class="col-6">Subtotal:</div>  <div class="col-6 text-end fw-600" id="dispSubtotal">₹0.00</div>
              <div class="col-6">Discount:</div>   <div class="col-6 text-end text-success fw-600" id="dispDiscount">-₹0.00</div>
              <div class="col-6">GST (18%):</div>  <div class="col-6 text-end fw-600" id="dispTax">₹0.00</div>
              <div class="col-6 fw-800">Total:</div><div class="col-6 text-end fw-800 text-primary" id="dispTotal">₹0.00</div>
              <div class="col-6">Paid:</div>        <div class="col-6 text-end text-success fw-600" id="dispPaid">₹0.00</div>
              <div class="col-6 text-danger fw-700">Balance Due:</div><div class="col-6 text-end text-danger fw-700" id="dispBalance">₹0.00</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitInvoice()"><i class="fa-solid fa-save me-2"></i>Save Invoice</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Record Payment Modal ── -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-800">Record Payment</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="payInvoiceId"/>
        <div class="mb-3">
          <label class="form-label">Amount (₹) — Balance: <span id="payBalance" class="fw-700 text-danger"></span></label>
          <input type="number" id="payAmount" class="form-control" min="1"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Payment Method</label>
          <select id="payMethodSelect" class="form-select">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="upi">UPI</option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-success w-100 ripple-btn" onclick="submitPayment()"><i class="fa-solid fa-check me-2"></i>Record Payment</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  addInvoiceItem(); // start with one blank row

  HMSAjax.liveSearch('#invoicePatientSearch','invoicePatientResults',
    APP_URL + '/ajax/search_patient.php',
    items => items.map(p =>
      '<div class="dropdown-item cursor-pointer p-2 border-bottom text-sm" onclick="selectInvPatient(' + p.id + ',\'' + p.full_name.replace("'","\'") + '\')">' +
      '<strong>' + p.full_name + '</strong> <small class=text-muted>' + p.patient_code + ' · ' + p.phone + '</small></div>'
    ).join('')
  );
});

function selectInvPatient(id, name) {
  document.getElementById('invoicePatientId').value = id;
  document.getElementById('invoicePatientSearch').value = name;
  document.getElementById('invoicePatientResults').innerHTML = '';
}

let invItems = [];

function addInvoiceItem() {
  invItems.push({ desc:'', category:'consultation', qty:1, price:0 });
  renderInvItems();
}

function removeInvItem(i) {
  invItems.splice(i,1);
  renderInvItems();
}

function renderInvItems() {
  const container = document.getElementById('invoiceItems');
  container.innerHTML = invItems.map((item,i) => `
    <div class="row g-2 mb-2 align-items-end">
      <div class="col-md-4"><label class="form-label text-xs">Description</label>
        <input type="text" class="form-control form-control-sm" value="${item.desc}" oninput="invItems[${i}].desc=this.value" placeholder="Service / item name"/></div>
      <div class="col-md-2"><label class="form-label text-xs">Category</label>
        <select class="form-select form-select-sm" onchange="invItems[${i}].category=this.value">
          <option ${item.category=='consultation'?'selected':''} value="consultation">Consultation</option>
          <option ${item.category=='lab'?'selected':''} value="lab">Lab</option>
          <option ${item.category=='medicine'?'selected':''} value="medicine">Medicine</option>
          <option ${item.category=='procedure'?'selected':''} value="procedure">Procedure</option>
          <option ${item.category=='room'?'selected':''} value="room">Room/Bed</option>
          <option ${item.category=='other'?'selected':''} value="other">Other</option>
        </select></div>
      <div class="col-md-2"><label class="form-label text-xs">Qty</label>
        <input type="number" class="form-control form-control-sm" value="${item.qty}" min="1" oninput="invItems[${i}].qty=+this.value;recalcInvoice()"/></div>
      <div class="col-md-3"><label class="form-label text-xs">Unit Price (₹)</label>
        <input type="number" class="form-control form-control-sm" value="${item.price}" min="0" step="0.01" oninput="invItems[${i}].price=+this.value;recalcInvoice()"/></div>
      <div class="col-md-1"><button class="btn btn-sm btn-outline-danger w-100" onclick="removeInvItem(${i})"><i class="fa-solid fa-trash"></i></button></div>
    </div>`).join('');
  recalcInvoice();
}

function recalcInvoice() {
  let sub = invItems.reduce((s,i) => s + (i.qty * i.price), 0);
  const disc  = parseFloat(document.getElementById('invDiscount')?.value || 0);
  const paid  = parseFloat(document.getElementById('invPaid')?.value    || 0);
  const tax   = (sub - disc) * 0.18;
  const total = (sub - disc) + tax;
  const bal   = total - paid;
  document.getElementById('dispSubtotal').textContent = '₹' + sub.toFixed(2);
  document.getElementById('dispDiscount').textContent = '-₹' + disc.toFixed(2);
  document.getElementById('dispTax').textContent      = '₹' + tax.toFixed(2);
  document.getElementById('dispTotal').textContent    = '₹' + total.toFixed(2);
  document.getElementById('dispPaid').textContent     = '₹' + paid.toFixed(2);
  document.getElementById('dispBalance').textContent  = '₹' + Math.max(bal,0).toFixed(2);
}

async function submitInvoice() {
  const pid = document.getElementById('invoicePatientId').value;
  if (!pid) { HMS.toast('Please select a patient.','warning'); return; }
  if (!invItems.length) { HMS.toast('Add at least one item.','warning'); return; }

  const payload = {
    patient_id:     pid,
    payment_method: document.getElementById('invPayMethod').value,
    gst_number:     document.getElementById('invGst').value,
    discount:       document.getElementById('invDiscount').value,
    paid:           document.getElementById('invPaid').value,
    notes:          document.getElementById('invNotes').value,
    items:          invItems.map(i => ({ description:i.desc, category:i.category, qty:i.qty, unit_price:i.price })),
  };

  const res = await HMSAjax.post(APP_URL + '/api/billing.php', payload);
  if (res.success) {
    HMS.toast(res.message, 'success');
    bootstrap.Modal.getInstance(document.getElementById('createInvoiceModal')).hide();
    setTimeout(() => location.reload(), 900);
  }
}

function recordPayment(id, balance) {
  document.getElementById('payInvoiceId').value = id;
  document.getElementById('payAmount').value    = balance;
  document.getElementById('payBalance').textContent = '₹' + parseFloat(balance).toFixed(2);
  new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

async function submitPayment() {
  const id     = document.getElementById('payInvoiceId').value;
  const amount = document.getElementById('payAmount').value;
  const method = document.getElementById('payMethodSelect').value;
  const res    = await HMSAjax.put(APP_URL + '/api/billing.php?id=' + id, { paid: amount, payment_method: method });
  if (res.success) {
    HMS.toast('Payment recorded!','success');
    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
    setTimeout(() => location.reload(), 900);
  }
}

function viewInvoice(id)  { HMS.toast('Loading invoice ' + id, 'info'); }
function printInvoice(id) { window.open(APP_URL + '/admin/billing.php?print=' + id, '_blank'); }

function filterInvoices() {
  const status = document.getElementById('statusFilter').value;
  document.querySelectorAll('#billingTable tbody tr').forEach(row => {
    const rs = row.dataset.status || '';
    row.style.display = (!status || rs === status) ? '' : 'none';
  });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
