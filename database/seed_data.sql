-- ============================================================
-- HOSPITAL MANAGEMENT SYSTEM - SEED / DEMO DATA
-- Run AFTER hospital_management.sql
-- ============================================================

USE `hospital_management`;

-- ============================================================
-- DEPARTMENTS
-- ============================================================
INSERT INTO `departments` (`name`, `code`, `description`, `icon`, `color`) VALUES
('Cardiology',       'CARD', 'Heart and cardiovascular diseases',            'fa-heart-pulse',    '#ef4444'),
('Neurology',        'NEUR', 'Brain and nervous system disorders',           'fa-brain',          '#8b5cf6'),
('Orthopedics',      'ORTH', 'Bone, joint and muscle conditions',            'fa-bone',           '#f97316'),
('Pediatrics',       'PEDI', 'Medical care for infants and children',        'fa-child',          '#06b6d4'),
('Gynecology',       'GYNE', 'Female reproductive health',                   'fa-venus',          '#ec4899'),
('Oncology',         'ONCO', 'Cancer diagnosis and treatment',               'fa-ribbon',         '#14b8a6'),
('Emergency',        'EMRG', '24x7 Emergency and Trauma care',               'fa-truck-medical',  '#dc2626'),
('Radiology',        'RADI', 'Imaging and diagnostic radiology',             'fa-x-ray',          '#6366f1'),
('Gastroenterology', 'GAST', 'Digestive system disorders',                   'fa-stethoscope',    '#84cc16'),
('Dermatology',      'DERM', 'Skin, hair and nail conditions',               'fa-hand-dots',      '#f59e0b');

-- ============================================================
-- SUPPLIERS
-- ============================================================
INSERT INTO `suppliers` (`name`, `contact`, `phone`, `email`, `address`, `gst_no`) VALUES
('Sun Pharma Distributors', 'Rajesh Sharma',  '9876543210', 'rajesh@sunpharma.com',  '123 MG Road, Mumbai',    '27AABCS1429B1Z1'),
('Cipla Medical Supplies',  'Priya Patel',    '9871234560', 'priya@cipla.com',        '456 Ring Road, Ahmedabad','24AABCC1429C2Z2'),
('Dr. Reddy Labs Supply',   'Suresh Reddy',   '9863214590', 'suresh@drreddy.com',     '789 Hitech City, Hyderabad','36AABCD1429D3Z3');

-- ============================================================
-- USERS (passwords are bcrypt of "password123")
-- ============================================================
INSERT INTO `users` (`uuid`, `full_name`, `email`, `phone`, `password`, `role`, `status`) VALUES
('a1b2c3d4-0001-0001-0001-000000000001', 'Dr. Admin Singh',      'admin@hospital.com',        '9800000001', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'admin',          'active'),
('a1b2c3d4-0002-0002-0002-000000000002', 'Dr. Anil Kapoor',      'anil.kapoor@hospital.com',  '9800000002', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'doctor',         'active'),
('a1b2c3d4-0003-0003-0003-000000000003', 'Dr. Sunita Rao',       'sunita.rao@hospital.com',   '9800000003', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'doctor',         'active'),
('a1b2c3d4-0004-0004-0004-000000000004', 'Dr. Rahul Mehta',      'rahul.mehta@hospital.com',  '9800000004', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'doctor',         'active'),
('a1b2c3d4-0005-0005-0005-000000000005', 'Kavita Receptionist',  'kavita@hospital.com',       '9800000005', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'receptionist',   'active'),
('a1b2c3d4-0006-0006-0006-000000000006', 'Rajan Pharmacist',     'rajan@hospital.com',        '9800000006', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'pharmacist',     'active'),
('a1b2c3d4-0007-0007-0007-000000000007', 'Meena Lab Tech',       'meena@hospital.com',        '9800000007', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'lab_technician', 'active'),
('a1b2c3d4-0008-0008-0008-000000000008', 'Arjun Verma',          'arjun.verma@email.com',     '9800000008', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'patient',        'active'),
('a1b2c3d4-0009-0009-0009-000000000009', 'Priya Sharma',         'priya.sharma@email.com',    '9800000009', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'patient',        'active'),
('a1b2c3d4-0010-0010-0010-000000000010', 'Sanjay Gupta',         'sanjay.gupta@email.com',    '9800000010', '$2y$12$7t.xCptOC.FDxm5cPKJqhOq.OUZQUf.5wQjF6Y2KBMI/OXZMU4B2', 'patient',        'active');

-- ============================================================
-- DOCTORS
-- ============================================================
INSERT INTO `doctors` (`user_id`, `doctor_code`, `department_id`, `specialization`, `qualification`, `experience_years`, `consultation_fee`, `available_days`, `time_from`, `time_to`) VALUES
(2, 'DOC-001', 1, 'Interventional Cardiologist', 'MBBS, MD, DM Cardiology', 15, 1500.00, 'Mon,Tue,Wed,Thu,Fri', '09:00:00', '17:00:00'),
(3, 'DOC-002', 2, 'Senior Neurologist',          'MBBS, MD, DM Neurology',  12, 1200.00, 'Mon,Wed,Fri',          '10:00:00', '16:00:00'),
(4, 'DOC-003', 3, 'Orthopedic Surgeon',          'MBBS, MS Orthopedics',    10, 1000.00, 'Tue,Thu,Sat',          '09:00:00', '14:00:00');

-- ============================================================
-- PATIENTS
-- ============================================================
INSERT INTO `patients` (`user_id`, `patient_code`, `dob`, `gender`, `blood_group`, `address`, `city`, `allergies`, `emergency_name`, `emergency_phone`, `emergency_relation`) VALUES
(8,  'PAT-0001', '1985-06-15', 'male',   'B+', '12 Sector-7, Dwarka', 'New Delhi',  'Penicillin',   'Sunita Verma',   '9811111111', 'Wife'),
(9,  'PAT-0002', '1992-03-22', 'female', 'O+', '45 Satellite Road',   'Ahmedabad',  NULL,           'Rahul Sharma',   '9822222222', 'Husband'),
(10, 'PAT-0003', '1975-11-08', 'male',   'A+', '7 Anna Nagar',        'Chennai',    'Sulfa drugs',  'Anita Gupta',    '9833333333', 'Wife');

-- ============================================================
-- WARDS & BEDS
-- ============================================================
INSERT INTO `wards` (`name`, `ward_type`, `total_beds`, `occupied_beds`, `charge_per_day`, `department_id`, `floor`) VALUES
('General Ward A',     'general',     20, 14, 800.00,  1, 'Ground Floor'),
('ICU Ward',           'icu',          8,  5, 5000.00, 7, '1st Floor'),
('Private Suite A',    'private',     10,  6, 3000.00, 1, '2nd Floor'),
('Semi-Private B',     'semi_private', 15, 10, 1500.00, 3, '1st Floor'),
('Pediatric Ward',     'general',     12,  7, 1000.00, 4, 'Ground Floor'),
('Emergency Ward',     'emergency',   10,  4, 2000.00, 7, 'Ground Floor');

INSERT INTO `beds` (`ward_id`, `bed_number`, `status`) VALUES
(1,'A-01','occupied'),(1,'A-02','available'),(1,'A-03','occupied'),(1,'A-04','available'),
(2,'ICU-01','occupied'),(2,'ICU-02','occupied'),(2,'ICU-03','available'),
(3,'PS-01','occupied'),(3,'PS-02','available');

-- ============================================================
-- MEDICINES
-- ============================================================
INSERT INTO `medicines` (`name`, `generic_name`, `category`, `manufacturer`, `batch_no`, `unit`, `purchase_price`, `sell_price`, `stock_qty`, `min_stock`, `expiry_date`, `supplier_id`) VALUES
('Azithromycin 500mg', 'Azithromycin',  'Antibiotic',    'Sun Pharma',       'B2024-01', 'tablet', 8.00,  15.00, 500, 50, '2026-06-30', 1),
('Paracetamol 500mg',  'Paracetamol',   'Analgesic',     'Cipla',            'B2024-02', 'tablet', 1.50,   5.00,1200, 100,'2026-09-30', 2),
('Metformin 500mg',    'Metformin',     'Antidiabetic',  'Dr. Reddy Labs',   'B2024-03', 'tablet', 3.00,   8.00, 800, 80, '2025-12-31', 3),
('Atorvastatin 10mg',  'Atorvastatin',  'Cardiac',       'Sun Pharma',       'B2024-04', 'tablet', 5.00,  12.00, 400, 40, '2026-03-31', 1),
('Omeprazole 20mg',    'Omeprazole',    'Antacid',       'Cipla',            'B2024-05', 'capsule',4.00,  10.00, 600, 60, '2025-10-31', 2),
('Amlodipine 5mg',     'Amlodipine',    'Antihypertensive','Dr. Reddy Labs', 'B2024-06', 'tablet', 6.00,  14.00, 350, 35, '2026-01-31', 3),
('Cefixime 200mg',     'Cefixime',      'Antibiotic',    'Sun Pharma',       'B2024-07', 'tablet',10.00,  22.00, 200, 25, '2025-08-31', 1),
('Pantoprazole 40mg',  'Pantoprazole',  'Antacid',       'Cipla',            'B2024-08', 'tablet', 7.00,  16.00, 450, 45, '2026-05-31', 2),
('Aspirin 75mg',       'Aspirin',       'Antiplatelet',  'Dr. Reddy Labs',   'B2024-09', 'tablet', 2.00,   6.00, 900, 90, '2026-08-31', 3),
('Insulin Glargine',   'Insulin',       'Antidiabetic',  'Sanofi',           'B2024-10', 'vial',  180.00, 350.00, 50, 10, '2025-11-30', 1);

-- ============================================================
-- LAB TESTS
-- ============================================================
INSERT INTO `lab_tests` (`name`, `code`, `category`, `price`, `turnaround`) VALUES
('Complete Blood Count',      'CBC',      'Haematology',  300.00,  6),
('Lipid Profile',             'LFT',      'Biochemistry', 600.00, 12),
('Blood Glucose Fasting',     'BGF',      'Biochemistry', 150.00,  4),
('HbA1c',                     'HBA1C',    'Biochemistry', 500.00, 24),
('Thyroid Profile T3 T4 TSH', 'TFT',      'Biochemistry', 700.00, 24),
('Urine Routine',             'URE',      'Pathology',    150.00,  4),
('ECG',                       'ECG',      'Cardiology',   300.00,  1),
('Chest X-Ray',               'CXR',      'Radiology',    400.00,  2),
('MRI Brain',                 'MRIBR',    'Radiology',   4500.00, 48),
('CT Scan Abdomen',           'CTABD',    'Radiology',   3500.00, 24),
('COVID-19 RT-PCR',           'COVIDPCR', 'Microbiology', 800.00, 24),
('Dengue NS1 Antigen',        'DENGNS1',  'Serology',     500.00,  8);

-- ============================================================
-- APPOINTMENTS (sample)
-- ============================================================
INSERT INTO `appointments` (`appointment_no`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `appointment_time`, `token_number`, `type`, `status`, `symptoms`, `payment_status`) VALUES
('APT-20240001', 1, 1, 1, CURDATE(), '09:30:00',  1, 'opd', 'completed',    'Chest pain and shortness of breath', 'paid'),
('APT-20240002', 2, 2, 2, CURDATE(), '10:00:00',  2, 'opd', 'completed',    'Frequent headaches and dizziness',   'paid'),
('APT-20240003', 3, 3, 3, CURDATE(), '11:00:00',  3, 'opd', 'in_progress',  'Knee pain and swelling',             'paid'),
('APT-20240004', 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', 1, 'opd', 'booked', 'Follow-up consultation', 'pending'),
('APT-20240005', 2, 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:30:00', 2, 'opd', 'booked', 'MRI review',             'pending');

-- ============================================================
-- NOTIFICATIONS (sample)
-- ============================================================
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`) VALUES
(1, 'New Patient Registered',   'Patient Arjun Verma has been registered successfully.',  'success', 0),
(1, 'Low Stock Alert',          'Cefixime 200mg stock is running low (200 units left).',  'warning', 0),
(1, 'Lab Report Ready',         'Lab report for PAT-0001 CBC test is ready.',             'info',    0),
(2, 'Appointment Reminder',     'You have 3 appointments scheduled for today.',           'info',    0),
(5, 'New Appointment Booked',   'APT-20240004 has been booked for Dr. Anil Kapoor.',      'success', 0);

-- ============================================================
-- INVOICES (sample)
-- ============================================================
INSERT INTO `invoices` (`invoice_no`, `patient_id`, `appointment_id`, `subtotal`, `discount`, `tax`, `total`, `paid`, `balance`, `payment_method`, `payment_status`) VALUES
('INV-2024-001', 1, 1, 1500.00, 0.00, 270.00, 1770.00, 1770.00, 0.00, 'card', 'paid'),
('INV-2024-002', 2, 2, 1200.00, 0.00, 216.00, 1416.00, 1416.00, 0.00, 'upi',  'paid'),
('INV-2024-003', 3, 3, 1000.00, 100.00, 162.00, 1062.00, 0.00, 1062.00, 'cash','pending');

INSERT INTO `invoice_items` (`invoice_id`, `description`, `category`, `qty`, `unit_price`, `subtotal`) VALUES
(1, 'Cardiology Consultation - Dr. Anil Kapoor', 'consultation', 1, 1500.00, 1500.00),
(2, 'Neurology Consultation - Dr. Sunita Rao',   'consultation', 1, 1200.00, 1200.00),
(3, 'Orthopedic Consultation - Dr. Rahul Mehta', 'consultation', 1, 1000.00, 1000.00);
