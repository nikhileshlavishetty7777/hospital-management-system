<?php
// ============================================================
// ajax/load_dashboard.php — Dashboard stats API (AJAX refresh)
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();
header('Content-Type: application/json');

$role = Auth::role();
$data = [];

// Common stats for admin
if ($role === 'admin') {
    $data['total_patients']    = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM patients")['c'];
    $data['total_doctors']     = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM doctors")['c'];
    $data['today_appointments']= (int)Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date=CURDATE()")['c'];
    $data['month_revenue']     = (float)Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM invoices WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'];
    $data['beds_occupied']     = (int)Database::fetchOne("SELECT SUM(occupied_beds) AS c FROM wards")['c'];
    $data['total_beds']        = (int)Database::fetchOne("SELECT SUM(total_beds) AS c FROM wards")['c'];
    $data['pending_labs']      = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status IN ('ordered','sample_collected','processing')")['c'];
    $data['available_doctors'] = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM doctors WHERE status='available'")['c'];
    $data['pharmacy_today']    = (float)Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM pharmacy_sales WHERE DATE(created_at)=CURDATE()")['c'];
    $data['low_stock_count']   = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE stock_qty <= min_stock AND status=1")['c'];

    // Revenue sparkline (last 7 days)
    $spark = Database::fetchAll("
        SELECT DATE_FORMAT(d.d,'%d %b') AS label, COALESCE(SUM(i.paid),0) AS val
        FROM (
            SELECT CURDATE()-INTERVAL 6 DAY AS d UNION SELECT CURDATE()-INTERVAL 5 DAY
            UNION SELECT CURDATE()-INTERVAL 4 DAY UNION SELECT CURDATE()-INTERVAL 3 DAY
            UNION SELECT CURDATE()-INTERVAL 2 DAY UNION SELECT CURDATE()-INTERVAL 1 DAY
            UNION SELECT CURDATE()
        ) d
        LEFT JOIN invoices i ON DATE(i.created_at) = d.d
        GROUP BY d.d ORDER BY d.d
    ");
    $data['revenue_spark'] = $spark;

    // Queue live (today, in-progress / waiting)
    $data['queue'] = Database::fetchAll("
        SELECT a.token_number, a.status, u.full_name, a.appointment_time
        FROM appointments a
        JOIN patients p ON p.id=a.patient_id JOIN users u ON u.id=p.user_id
        WHERE a.appointment_date=CURDATE() AND a.status IN ('waiting','in_progress','confirmed')
        ORDER BY a.token_number LIMIT 10
    ");
}

// Doctor dashboard
if ($role === 'doctor') {
    $doc = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
    if ($doc) {
        $did = $doc['id'];
        $data['today_appointments'] = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE()", [$did])['c'];
        $data['pending']            = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE() AND status='booked'", [$did])['c'];
        $data['completed_today']    = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE() AND status='completed'", [$did])['c'];
        $data['total_patients']     = (int)Database::fetchOne("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id=?", [$did])['c'];
        $data['next_patient']       = Database::fetchOne("
            SELECT a.token_number, u.full_name, a.appointment_time, a.symptoms
            FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN users u ON u.id=p.user_id
            WHERE a.doctor_id=? AND a.appointment_date=CURDATE() AND a.status='booked'
            ORDER BY a.token_number LIMIT 1
        ", [$did]);
    }
}

// Patient dashboard
if ($role === 'patient') {
    $pat = Database::fetchOne("SELECT id FROM patients WHERE user_id=?", [Auth::id()]);
    if ($pat) {
        $pid = $pat['id'];
        $data['upcoming_appointments'] = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE patient_id=? AND appointment_date>=CURDATE() AND status NOT IN ('cancelled','completed')", [$pid])['c'];
        $data['lab_reports']           = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE patient_id=? AND status='completed'", [$pid])['c'];
        $data['pending_bills']         = (float)Database::fetchOne("SELECT COALESCE(SUM(balance),0) AS c FROM invoices WHERE patient_id=? AND payment_status!='paid'", [$pid])['c'];
        $data['next_appointment']      = Database::fetchOne("
            SELECT a.appointment_date, a.appointment_time, a.token_number, a.status,
                   u.full_name AS doctor_name, dep.name AS dept_name
            FROM appointments a JOIN doctors d ON d.id=a.doctor_id JOIN users u ON u.id=d.user_id
            JOIN departments dep ON dep.id=a.department_id
            WHERE a.patient_id=? AND a.appointment_date>=CURDATE() AND a.status NOT IN ('cancelled','completed')
            ORDER BY a.appointment_date, a.appointment_time LIMIT 1
        ", [$pid]);
    }
}

echo json_encode(['success'=>true,'role'=>$role,'data'=>$data,'timestamp'=>time()]);
