# MediCare HMS — API Documentation

Base URL: `http://your-domain/hospital-management-system/api`

All endpoints require an authenticated session cookie.
All responses are JSON.

---

## Authentication

### POST /authentication/login_process.php
Login and establish session.

**Body (form-data)**
```json
{ "email": "admin@hospital.com", "password": "password123" }
```
**Response**
```json
{ "success": true, "redirect": "/admin/dashboard.php" }
```

---

## Patients `/api/patients.php`

| Method | Params | Description |
|--------|--------|-------------|
| GET    | `?id=N` | Get single patient with history |
| GET    | `?q=search&limit=50&offset=0` | Search/list patients |
| POST   | body (JSON/form-data) | Register new patient |
| PUT    | `?id=N` + body | Update patient |
| DELETE | `?id=N` | Deactivate patient (admin only) |

**POST body fields:**
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "9876543210",
  "gender": "male",
  "dob": "1990-01-15",
  "blood_group": "O+",
  "address": "123 Main St",
  "city": "Mumbai",
  "state": "Maharashtra",
  "pincode": "400001",
  "allergies": "Penicillin",
  "chronic_diseases": "Diabetes",
  "emergency_name": "Jane Doe",
  "emergency_phone": "9876543211",
  "emergency_relation": "Spouse",
  "insurance_provider": "Star Health",
  "insurance_number": "SH123456",
  "insurance_expiry": "2025-12-31",
  "password": "securepass123"
}
```

---

## Doctors `/api/doctors.php`

| Method | Params | Description |
|--------|--------|-------------|
| GET    | `?id=N` | Get doctor with schedule |
| GET    | `?department_id=N&status=available&q=search` | List/filter doctors |
| POST   | body | Add doctor (admin only) |
| PUT    | `?id=N` + body | Update doctor profile |

---

## Appointments `/api/appointments.php`

| Method | Params | Description |
|--------|--------|-------------|
| GET    | `?id=N` | Single appointment details |
| GET    | `?date=YYYY-MM-DD&doctor_id=N&status=booked&department_id=N` | Filtered list |
| POST   | body | Book appointment |
| PUT    | `?id=N` + `{status, appointment_date, appointment_time, notes}` | Update |
| DELETE | `?id=N` | Cancel appointment |

**POST body:**
```json
{
  "patient_id": 1,
  "doctor_id": 1,
  "department_id": 1,
  "appointment_date": "2024-12-25",
  "appointment_time": "10:00",
  "type": "opd",
  "symptoms": "Chest pain"
}
```

**Status values:** `booked` → `confirmed` → `waiting` → `in_progress` → `completed`

---

## Billing `/api/billing.php`

| Method | Params | Description |
|--------|--------|-------------|
| GET    | `?id=N` | Invoice with items |
| GET    | `?patient_id=N&status=pending&from=date&to=date` | Filter invoices |
| POST   | body | Create invoice |
| PUT    | `?id=N` + `{paid, payment_method}` | Record payment |

**POST body:**
```json
{
  "patient_id": 1,
  "appointment_id": 5,
  "payment_method": "card",
  "discount": 100,
  "paid": 1500,
  "gst_number": "27AABCS1429B1Z1",
  "items": [
    { "description": "Consultation", "category": "consultation", "qty": 1, "unit_price": 1500 },
    { "description": "ECG", "category": "procedure", "qty": 1, "unit_price": 300 }
  ]
}
```

---

## Lab Reports `/api/reports.php`

| Method | Params | Description |
|--------|--------|-------------|
| GET    | `?id=N` | Single lab order |
| GET    | `?patient_id=N&status=ordered` | List orders |
| POST   | body | Create lab order |
| POST   | `?id=N` + multipart | Upload report file |
| PUT    | `?id=N` + `{status}` | Update order status |

**Status flow:** `ordered` → `sample_collected` → `processing` → `completed`

---

## AJAX Endpoints

### GET `/ajax/notifications.php`
- `?action=list` — Get user notifications
- `?action=read&id=N` — Mark one as read
- `?action=read_all` — Mark all as read
- `?action=unread_count` — Get unread count

### GET `/ajax/search_patient.php?q=search`
Returns: `{ success, data: [{id, full_name, patient_code, phone, email, gender, blood_group}] }`

### GET `/ajax/load_dashboard.php`
Returns role-specific dashboard stats.

---

## Response Format

**Success:**
```json
{
  "success": true,
  "message": "Optional message",
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error description",
  "errors": { "field": "validation message" }
}
```

**HTTP Status Codes:**
- `200` OK
- `201` Created
- `400` Bad Request
- `401` Unauthorized
- `403` Forbidden
- `404` Not Found
- `409` Conflict (duplicate)
- `422` Unprocessable Entity (validation)
- `500` Server Error
