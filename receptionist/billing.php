<?php
// ============================================================
// receptionist/billing.php — Receptionist Billing
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['receptionist','admin']);

$pageTitle = 'Billing';

// Pre-load appointment if passed
$preApptId = (int)($_GET['appt_id'] ?? 0);
$preAppt   = null;
if ($preApptId) {
    $preAppt = Database::fetchOne("
        SELECT a.*, u_p.full_name AS patient_name, p.patient_code, p.id AS patient_id,
               u_d.full_name AS doctor_name, d.consultation_fee
        FROM appointments a
        JOIN patients p ON p.id=a.patient_id JOIN users u_p ON u_p.id=p.user_id
        JOIN doctors  d ON d.id=a.doctor_id  JOIN users u_d ON u_d.id=d.user_id
        WHERE a.id=?", [$preApptId]);
}

// Today's invoices
$invoices = Database::fetchAll("
    SELECT i.*, u.full_name AS patient_name, p.patient_code
    FROM invoices i JOIN patients p ON p.id=i.patient_id JOIN users u ON u.id=p.user_id
    WHERE DATE(i.created_at)=CURDATE()
    ORDER BY i.id DESC LIMIT 30
");

$todayRevenue = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM invoices WHERE DATE(created_at)=CURDATE()")['c'];
$todayCount   = Database::fetchOne("SELECT COUNT(*) AS c FROM invoices WHERE DATE(created_at)=CURDATE()")['c'];
$pendingAmt   = Database::fetchOne("SELECT COALESCE(SUM(balance),0) AS c FROM invoices WHERE payment_status!='paid' AND DATE(created_at)=CURDATE()")['c'];

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-receipt me-2 text-primary"></i>Billing</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/receptionist/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Billing</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#createInvModal">
          <i class="fa-solid fa-plus me-2"></i>Create Invoice
        </button>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="stat-value" data-counter="<?= number_format($todayRevenue,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Today's Revenue</div></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div><div><div class="stat-value" data-counter="<?= $todayCount ?>">0</div><div class="stat-label">Invoices Today</div></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-clock"></i></div><div><div class="stat-value" data-counter="<?= number_format($pendingAmt,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Pending Today</div></div></div></div>
      </div>

      <!-- Today's Invoice Table -->
      <div class="card animate-fade-in">
        <div class="card-header"><i class="fa-solid fa-list me-2 text-primary"></i>Today's Invoices</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="invTable">
              <thead><tr><th>#</th><th>Invoice No</th><th>Patient</th><th>Total</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($invoices as $i => $inv): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><span class="text-mono fw-600 text-primary"><?= htmlspecialchars($inv['invoice_no']) ?></span></td>
                  <td><div class="fw-600"><?= htmlspecialchars($inv['patient_name']) ?></div><div class="text-muted text-xs"><?= $inv['patient_code'] ?></div></td>
                  <td class="fw-700">₹<?= number_format($inv['total'],2) ?></td>
                  <td class="text-success fw-600">₹<?= number_format($inv['paid'],2) ?></td>
                  <td class="<?= $inv['balance']>0?'text-danger':'text-muted' ?> fw-600">₹<?= number_format($inv['balance'],2) ?></td>
                  <td><span class="badge bg-secondary"><?= strtoupper($inv['payment_method']) ?></span></td>
                  <td>
                    <?php $cls=['paid'=>'status-completed','partial'=>'status-in_progress','pending'=>'status-booked','refunded'=>'status-cancelled'][$inv['payment_status']]??'status-booked'; ?>
                    <span class="status-badge <?= $cls ?>"><?= ucfirst($inv['payment_status']) ?></span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" onclick="printInv(<?= $inv['id'] ?>)"><i class="fa-solid fa-print"></i></button>
                      <?php if($inv['payment_status']!=='paid'): ?>
                      <button class="btn btn-sm btn-outline-success" onclick="payNow(<?= $inv['id'] ?>,<?= $inv['balance'] ?>)"><i class="fa-solid fa-indian-rupee-sign"></i></button>
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

<!-- Create Invoice Modal -->
<div class="modal fade" id="createInvModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Create Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Patient *</label>
            <input type="text" id="invPatSearch" class="form-control" placeholder="Search patient…"
              value="<?= $preAppt ? htmlspecialchars($preAppt['patient_name']) : '' ?>"/>
            <input type="hidden" id="invPatId" value="<?= $preAppt ? $preAppt['patient_id'] : '' ?>"/>
            <div id="invPatResults"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Payment Method</label>
            <select id="invMethod" class="form-select">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="upi">UPI</option>
              <option value="insurance">Insurance</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">GST Number</label>
            <input type="text" id="invGst" class="form-control" placeholder="Optional"/>
          </div>
        </div>

        <div class="d-flex justify-content-between mb-2">
          <strong>Items</strong>
          <button class="btn btn-sm btn-outline-primary" onclick="addItem()"><i class="fa-solid fa-plus me-1"></i>Add Row</button>
        </div>
        <div id="invItemsWrap"></div>
        <hr/>

        <!-- Pre-fill from appointment -->
        <?php if ($preAppt): ?>
        <script>
          document.addEventListener('DOMContentLoaded', () => {
            invItems = [{ desc:'<?= addslashes($preAppt['doctor_name']) ?> — Consultation', category:'consultation', qty:1, price:<?= $preAppt['consultation_fee'] ?> }];
            renderItems();
          });
        </script>
        <?php endif; ?>

        <div class="row g-2 mb-3">
          <div class="col-md-4"><label class="form-label">Discount (₹)</label><input type="number" id="invDisc" class="form-control" value="0" min="0" oninput="recalc()"/></div>
          <div class="col-md-4"><label class="form-label">Amount Paid (₹)</label><input type="number" id="invPaid" class="form-control" value="0" min="0" oninput="recalc()"/></div>
          <div class="col-md-4"><label class="form-label">Notes</label><input type="text" id="invNotes" class="form-control" placeholder="Optional"/></div>
        </div>
        <div class="card" style="background:var(--bg)">
          <div class="card-body py-3">
            <div class="row text-sm">
              <div class="col-6">Subtotal:</div><div class="col-6 text-end fw-600" id="dSub">₹0.00</div>
              <div class="col-6">Discount:</div><div class="col-6 text-end text-success fw-600" id="dDisc">-₹0.00</div>
              <div class="col-6">GST (18%):</div><div class="col-6 text-end fw-600" id="dTax">₹0.00</div>
              <div class="col-6 fw-800">Total:</div><div class="col-6 text-end fw-800 text-primary" id="dTotal">₹0.00</div>
              <div class="col-6 text-danger fw-700">Balance:</div><div class="col-6 text-end text-danger fw-700" id="dBal">₹0.00</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="saveInv()"><i class="fa-solid fa-save me-2"></i>Save Invoice</button>
      </div>
    </div>
  </div>
</div>

<!-- Quick Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0"><h6 class="modal-title fw-800">Record Payment</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="payInvId"/>
        <label class="form-label">Amount (₹) — Balance: <span id="payBal" class="text-danger fw-700"></span></label>
        <input type="number" id="payAmt" class="form-control mb-3"/>
        <select id="payMeth" class="form-select"><option value="cash">Cash</option><option value="card">Card</option><option value="upi">UPI</option></select>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-success w-100 ripple-btn" onclick="submitPay()"><i class="fa-solid fa-check me-2"></i>Confirm Payment</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
let invItems = [];
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  if (!invItems.length) addItem();
  HMSAjax.liveSearch('#invPatSearch','invPatResults',APP_URL+'/ajax/search_patient.php',
    function(items) {
  return '<div class="border rounded">' + items.map(function(p) {
    return '<div class="p-2 border-bottom text-sm cursor-pointer" onclick="selPat(' + p.id + ',\'' + p.full_name.replace(/'/g,"\\'") + '\')">' +
      '<strong>' + p.full_name + '</strong> <small class=text-muted>' + p.patient_code + '</small></div>';
  }).join('') + '</div>';
}
  );
});

function selPat(id,name) {
  document.getElementById('invPatId').value=id;
  document.getElementById('invPatSearch').value=name;
  document.getElementById('invPatResults').innerHTML='';
}

function addItem() {
  invItems.push({desc:'',category:'consultation',qty:1,price:0});
  renderItems();
}

function removeItem(i) { invItems.splice(i,1); renderItems(); }

function renderItems() {
  const cats=['consultation','lab','medicine','procedure','room','other'];
  const wrap=document.getElementById('invItemsWrap');
  wrap.innerHTML = invItems.map((it,i)=>`
    <div class="row g-2 mb-2 align-items-end">
      <div class="col-md-4"><label class="form-label text-xs">Description</label>
        <input type="text" class="form-control form-control-sm" value="${it.desc}" placeholder="Service name" oninput="invItems[${i}].desc=this.value"/></div>
      <div class="col-md-2"><label class="form-label text-xs">Category</label>
        <select class="form-select form-select-sm" onchange="invItems[${i}].category=this.value">
          ${cats.map(c=>'<option value="'+c+'" '+(it.category===c?'selected':'')+'>'+c.charAt(0).toUpperCase()+c.slice(1)+'</option>').join('')}
        </select></div>
      <div class="col-md-2"><label class="form-label text-xs">Qty</label>
        <input type="number" class="form-control form-control-sm" value="${it.qty}" min="1" oninput="invItems[${i}].qty=+this.value;recalc()"/></div>
      <div class="col-md-3"><label class="form-label text-xs">Unit Price (₹)</label>
        <input type="number" class="form-control form-control-sm" value="${it.price}" min="0" step="0.01" oninput="invItems[${i}].price=+this.value;recalc()"/></div>
      <div class="col-md-1"><button class="btn btn-sm btn-outline-danger w-100" onclick="removeItem(${i})"><i class="fa-solid fa-trash"></i></button></div>
    </div>`).join('');
  recalc();
}

function recalc() {
  let sub=invItems.reduce((s,i)=>s+i.qty*i.price,0);
  const disc=+document.getElementById('invDisc').value||0;
  const paid=+document.getElementById('invPaid').value||0;
  const tax=(sub-disc)*.18; const total=(sub-disc)+tax; const bal=total-paid;
  document.getElementById('dSub').textContent='₹'+sub.toFixed(2);
  document.getElementById('dDisc').textContent='-₹'+disc.toFixed(2);
  document.getElementById('dTax').textContent='₹'+tax.toFixed(2);
  document.getElementById('dTotal').textContent='₹'+total.toFixed(2);
  document.getElementById('dBal').textContent='₹'+Math.max(bal,0).toFixed(2);
}

async function saveInv() {
  const pid=document.getElementById('invPatId').value;
  if (!pid) { HMS.toast('Select a patient.','warning'); return; }
  const validItems=invItems.filter(i=>i.desc.trim());
  if (!validItems.length) { HMS.toast('Add at least one item.','warning'); return; }
  const res=await HMSAjax.post(APP_URL+'/api/billing.php',{
    patient_id:pid, payment_method:document.getElementById('invMethod').value,
    gst_number:document.getElementById('invGst').value,
    discount:document.getElementById('invDisc').value,
    paid:document.getElementById('invPaid').value,
    notes:document.getElementById('invNotes').value,
    items:validItems.map(i=>({description:i.desc,category:i.category,qty:i.qty,unit_price:i.price}))
  });
  if (res.success) {
    HMS.toast(res.message,'success');
    bootstrap.Modal.getInstance(document.getElementById('createInvModal')).hide();
    setTimeout(()=>location.reload(),900);
  }
}

function payNow(id,balance) {
  document.getElementById('payInvId').value=id;
  document.getElementById('payAmt').value=balance;
  document.getElementById('payBal').textContent='₹'+parseFloat(balance).toFixed(2);
  new bootstrap.Modal(document.getElementById('payModal')).show();
}

async function submitPay() {
  const id=document.getElementById('payInvId').value;
  const amt=document.getElementById('payAmt').value;
  const meth=document.getElementById('payMeth').value;
  const res=await HMSAjax.put(APP_URL+'/api/billing.php?id='+id,{paid:amt,payment_method:meth});
  if (res.success) {
    HMS.toast('Payment recorded!','success');
    bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
    setTimeout(()=>location.reload(),800);
  }
}

function printInv(id) { HMS.toast('Print preview for invoice '+id,'info'); }
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
