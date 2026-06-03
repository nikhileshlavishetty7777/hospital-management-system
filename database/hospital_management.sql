-- ============================================================
-- HOSPITAL MANAGEMENT SYSTEM - DATABASE SCHEMA
-- Version: 1.0.0 | Engine: InnoDB | Charset: utf8mb4
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `hospital_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hospital_management`;

-- ============================================================
-- TABLE: users (unified auth table for all roles)
-- ============================================================
CREATE TABLE `users` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `uuid`         VARCHAR(36)     NOT NULL UNIQUE,
  `full_name`    VARCHAR(150)    NOT NULL,
  `email`        VARCHAR(180)    NOT NULL UNIQUE,
  `phone`        VARCHAR(20)     DEFAULT NULL,
  `password`     VARCHAR(255)    NOT NULL,
  `role`         ENUM('admin','doctor','receptionist','pharmacist','lab_technician','patient') NOT NULL DEFAULT 'patient',
  `avatar`       VARCHAR(255)    DEFAULT NULL,
  `status`       ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `otp_code`     VARCHAR(10)     DEFAULT NULL,
  `otp_expires`  DATETIME        DEFAULT NULL,
  `last_login`   DATETIME        DEFAULT NULL,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_role`  (`role`),
  KEY `idx_uuid`  (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: departments
-- ============================================================
CREATE TABLE `departments` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL,
  `code`        VARCHAR(20)   NOT NULL UNIQUE,
  `description` TEXT          DEFAULT NULL,
  `hod_id`      INT UNSIGNED  DEFAULT NULL COMMENT 'Head of Department (doctor id)',
  `icon`        VARCHAR(50)   DEFAULT 'fa-hospital',
  `color`       VARCHAR(20)   DEFAULT '#0ea5e9',
  `status`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: doctors
-- ============================================================
CREATE TABLE `doctors` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED  NOT NULL,
  `doctor_code`       VARCHAR(20)   NOT NULL UNIQUE,
  `department_id`     INT UNSIGNED  NOT NULL,
  `specialization`    VARCHAR(150)  NOT NULL,
  `qualification`     VARCHAR(255)  NOT NULL,
  `experience_years`  INT           NOT NULL DEFAULT 0,
  `consultation_fee`  DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `bio`               TEXT          DEFAULT NULL,
  `license_number`    VARCHAR(50)   DEFAULT NULL,
  `rating`            DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
  `total_ratings`     INT           NOT NULL DEFAULT 0,
  `available_days`    VARCHAR(100)  DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  `time_from`         TIME          DEFAULT '09:00:00',
  `time_to`           TIME          DEFAULT '17:00:00',
  `slot_duration`     INT           NOT NULL DEFAULT 30 COMMENT 'minutes per slot',
  `status`            ENUM('available','on_leave','off_duty') NOT NULL DEFAULT 'available',
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_doctor_user`       (`user_id`),
  KEY `fk_doctor_department` (`department_id`),
  CONSTRAINT `fk_doctor_user`       FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doctor_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: patients
-- ============================================================
CREATE TABLE `patients` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED  NOT NULL,
  `patient_code`     VARCHAR(20)   NOT NULL UNIQUE,
  `dob`              DATE          DEFAULT NULL,
  `gender`           ENUM('male','female','other') NOT NULL,
  `blood_group`      VARCHAR(5)    DEFAULT NULL,
  `address`          TEXT          DEFAULT NULL,
  `city`             VARCHAR(100)  DEFAULT NULL,
  `state`            VARCHAR(100)  DEFAULT NULL,
  `pincode`          VARCHAR(10)   DEFAULT NULL,
  `allergies`        TEXT          DEFAULT NULL,
  `chronic_diseases` TEXT          DEFAULT NULL,
  `emergency_name`   VARCHAR(150)  DEFAULT NULL,
  `emergency_phone`  VARCHAR(20)   DEFAULT NULL,
  `emergency_relation` VARCHAR(50) DEFAULT NULL,
  `insurance_provider` VARCHAR(100) DEFAULT NULL,
  `insurance_number`   VARCHAR(50)  DEFAULT NULL,
  `insurance_expiry`   DATE         DEFAULT NULL,
  `qr_code`          VARCHAR(255)  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_patient_user` (`user_id`),
  CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: appointments
-- ============================================================
CREATE TABLE `appointments` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `appointment_no`  VARCHAR(20)   NOT NULL UNIQUE,
  `patient_id`      INT UNSIGNED  NOT NULL,
  `doctor_id`       INT UNSIGNED  NOT NULL,
  `department_id`   INT UNSIGNED  NOT NULL,
  `appointment_date` DATE         NOT NULL,
  `appointment_time` TIME         NOT NULL,
  `token_number`    INT           NOT NULL DEFAULT 0,
  `type`            ENUM('opd','ipd','emergency','teleconsult') NOT NULL DEFAULT 'opd',
  `status`          ENUM('booked','confirmed','waiting','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
  `symptoms`        TEXT          DEFAULT NULL,
  `notes`           TEXT          DEFAULT NULL,
  `payment_status`  ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `created_by`      INT UNSIGNED  DEFAULT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_appt_patient`    (`patient_id`),
  KEY `fk_appt_doctor`     (`doctor_id`),
  KEY `fk_appt_department` (`department_id`),
  KEY `idx_appt_date`      (`appointment_date`),
  CONSTRAINT `fk_appt_patient`    FOREIGN KEY (`patient_id`)    REFERENCES `patients`    (`id`),
  CONSTRAINT `fk_appt_doctor`     FOREIGN KEY (`doctor_id`)     REFERENCES `doctors`     (`id`),
  CONSTRAINT `fk_appt_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: wards
-- ============================================================
CREATE TABLE `wards` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)  NOT NULL,
  `ward_type`    ENUM('general','private','icu','semi_private','emergency') NOT NULL DEFAULT 'general',
  `total_beds`   INT           NOT NULL DEFAULT 10,
  `occupied_beds` INT          NOT NULL DEFAULT 0,
  `charge_per_day` DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `floor`        VARCHAR(20)   DEFAULT NULL,
  `status`       TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: beds
-- ============================================================
CREATE TABLE `beds` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ward_id`    INT UNSIGNED NOT NULL,
  `bed_number` VARCHAR(20)  NOT NULL,
  `status`     ENUM('available','occupied','maintenance','reserved') NOT NULL DEFAULT 'available',
  `patient_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bed_ward` (`ward_id`),
  CONSTRAINT `fk_bed_ward` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: admissions (IPD)
-- ============================================================
CREATE TABLE `admissions` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `admission_no`    VARCHAR(20)   NOT NULL UNIQUE,
  `patient_id`      INT UNSIGNED  NOT NULL,
  `doctor_id`       INT UNSIGNED  NOT NULL,
  `bed_id`          INT UNSIGNED  NOT NULL,
  `ward_id`         INT UNSIGNED  NOT NULL,
  `admission_date`  DATETIME      NOT NULL,
  `discharge_date`  DATETIME      DEFAULT NULL,
  `diagnosis`       TEXT          DEFAULT NULL,
  `treatment`       TEXT          DEFAULT NULL,
  `discharge_summary` TEXT        DEFAULT NULL,
  `status`          ENUM('admitted','discharged','transferred') NOT NULL DEFAULT 'admitted',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_adm_patient` (`patient_id`),
  KEY `fk_adm_doctor`  (`doctor_id`),
  KEY `fk_adm_bed`     (`bed_id`),
  CONSTRAINT `fk_adm_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_adm_doctor`  FOREIGN KEY (`doctor_id`)  REFERENCES `doctors`  (`id`),
  CONSTRAINT `fk_adm_bed`     FOREIGN KEY (`bed_id`)     REFERENCES `beds`     (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: prescriptions
-- ============================================================
CREATE TABLE `prescriptions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_no` VARCHAR(20) NOT NULL UNIQUE,
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `patient_id`     INT UNSIGNED NOT NULL,
  `doctor_id`      INT UNSIGNED NOT NULL,
  `diagnosis`      TEXT         DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `follow_up_date` DATE         DEFAULT NULL,
  `status`         ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_presc_patient` (`patient_id`),
  KEY `fk_presc_doctor`  (`doctor_id`),
  CONSTRAINT `fk_presc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_presc_doctor`  FOREIGN KEY (`doctor_id`)  REFERENCES `doctors`  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: prescription_items
-- ============================================================
CREATE TABLE `prescription_items` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `prescription_id` INT UNSIGNED  NOT NULL,
  `medicine_name`   VARCHAR(150)  NOT NULL,
  `dosage`          VARCHAR(100)  NOT NULL,
  `frequency`       VARCHAR(100)  DEFAULT NULL,
  `duration`        VARCHAR(50)   DEFAULT NULL,
  `instructions`    TEXT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pi_prescription` (`prescription_id`),
  CONSTRAINT `fk_pi_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: medicines (pharmacy inventory)
-- ============================================================
CREATE TABLE `medicines` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150)  NOT NULL,
  `generic_name`  VARCHAR(150)  DEFAULT NULL,
  `category`      VARCHAR(100)  DEFAULT NULL,
  `manufacturer`  VARCHAR(150)  DEFAULT NULL,
  `batch_no`      VARCHAR(50)   DEFAULT NULL,
  `barcode`       VARCHAR(50)   DEFAULT NULL UNIQUE,
  `unit`          VARCHAR(20)   DEFAULT 'tablet',
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sell_price`    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `stock_qty`     INT           NOT NULL DEFAULT 0,
  `min_stock`     INT           NOT NULL DEFAULT 10,
  `expiry_date`   DATE          DEFAULT NULL,
  `supplier_id`   INT UNSIGNED  DEFAULT NULL,
  `location`      VARCHAR(100)  DEFAULT NULL,
  `status`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_med_name`    (`name`),
  KEY `idx_med_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: pharmacy_sales
-- ============================================================
CREATE TABLE `pharmacy_sales` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sale_no`     VARCHAR(20)   NOT NULL UNIQUE,
  `patient_id`  INT UNSIGNED  DEFAULT NULL,
  `sold_by`     INT UNSIGNED  NOT NULL,
  `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','card','upi','insurance') NOT NULL DEFAULT 'cash',
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: pharmacy_sale_items
-- ============================================================
CREATE TABLE `pharmacy_sale_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sale_id`     INT UNSIGNED  NOT NULL,
  `medicine_id` INT UNSIGNED  NOT NULL,
  `qty`         INT           NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `subtotal`    DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_psi_sale`     (`sale_id`),
  KEY `fk_psi_medicine` (`medicine_id`),
  CONSTRAINT `fk_psi_sale`     FOREIGN KEY (`sale_id`)     REFERENCES `pharmacy_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psi_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`      (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: lab_tests (master list)
-- ============================================================
CREATE TABLE `lab_tests` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)  NOT NULL,
  `code`        VARCHAR(30)   NOT NULL UNIQUE,
  `category`    VARCHAR(100)  DEFAULT NULL,
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `turnaround`  INT           NOT NULL DEFAULT 24 COMMENT 'hours to deliver result',
  `description` TEXT          DEFAULT NULL,
  `status`      TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: lab_orders
-- ============================================================
CREATE TABLE `lab_orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`      VARCHAR(20)  NOT NULL UNIQUE,
  `patient_id`    INT UNSIGNED NOT NULL,
  `doctor_id`     INT UNSIGNED DEFAULT NULL,
  `technician_id` INT UNSIGNED DEFAULT NULL,
  `test_id`       INT UNSIGNED NOT NULL,
  `sample_date`   DATETIME     DEFAULT NULL,
  `report_date`   DATETIME     DEFAULT NULL,
  `report_file`   VARCHAR(255) DEFAULT NULL,
  `result`        TEXT         DEFAULT NULL,
  `remarks`       TEXT         DEFAULT NULL,
  `status`        ENUM('ordered','sample_collected','processing','completed','cancelled') NOT NULL DEFAULT 'ordered',
  `payment_status` ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_lo_patient` (`patient_id`),
  KEY `fk_lo_test`    (`test_id`),
  CONSTRAINT `fk_lo_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`  (`id`),
  CONSTRAINT `fk_lo_test`    FOREIGN KEY (`test_id`)    REFERENCES `lab_tests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: invoices
-- ============================================================
CREATE TABLE `invoices` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `invoice_no`     VARCHAR(20)   NOT NULL UNIQUE,
  `patient_id`     INT UNSIGNED  NOT NULL,
  `appointment_id` INT UNSIGNED  DEFAULT NULL,
  `admission_id`   INT UNSIGNED  DEFAULT NULL,
  `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','card','upi','insurance','cheque') NOT NULL DEFAULT 'cash',
  `payment_status` ENUM('pending','partial','paid','refunded') NOT NULL DEFAULT 'pending',
  `gst_number`     VARCHAR(30)   DEFAULT NULL,
  `notes`          TEXT          DEFAULT NULL,
  `created_by`     INT UNSIGNED  DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_inv_patient` (`patient_id`),
  CONSTRAINT `fk_inv_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: invoice_items
-- ============================================================
CREATE TABLE `invoice_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `invoice_id`  INT UNSIGNED  NOT NULL,
  `description` VARCHAR(255)  NOT NULL,
  `category`    VARCHAR(50)   DEFAULT 'consultation',
  `qty`         INT           NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `subtotal`    DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ii_invoice` (`invoice_id`),
  CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `message`    TEXT         NOT NULL,
  `type`       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `link`       VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: audit_logs
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(100) DEFAULT NULL,
  `record_id`  INT UNSIGNED DEFAULT NULL,
  `old_values` JSON         DEFAULT NULL,
  `new_values` JSON         DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `user_agent` TEXT         DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`  (`user_id`),
  KEY `idx_audit_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: doctor_leaves
-- ============================================================
CREATE TABLE `doctor_leaves` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT UNSIGNED NOT NULL,
  `from_date`  DATE         NOT NULL,
  `to_date`    DATE         NOT NULL,
  `reason`     TEXT         DEFAULT NULL,
  `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_leave_doctor` (`doctor_id`),
  CONSTRAINT `fk_leave_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: uploaded_documents
-- ============================================================
CREATE TABLE `uploaded_documents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`  INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `doc_type`    ENUM('prescription','lab_report','xray','mri','insurance','other') NOT NULL DEFAULT 'other',
  `title`       VARCHAR(200) NOT NULL,
  `file_path`   VARCHAR(255) NOT NULL,
  `file_size`   INT          DEFAULT NULL,
  `file_type`   VARCHAR(50)  DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_doc_patient` (`patient_id`),
  CONSTRAINT `fk_doc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: suppliers
-- ============================================================
CREATE TABLE `suppliers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `contact`    VARCHAR(150) DEFAULT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `email`      VARCHAR(180) DEFAULT NULL,
  `address`    TEXT         DEFAULT NULL,
  `gst_no`     VARCHAR(30)  DEFAULT NULL,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add FK for medicines.supplier_id
ALTER TABLE `medicines` ADD CONSTRAINT `fk_med_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;
-- Add FK for departments.hod_id
ALTER TABLE `departments` ADD CONSTRAINT `fk_dept_hod` FOREIGN KEY (`hod_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL;

COMMIT;
