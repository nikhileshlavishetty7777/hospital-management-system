# MediCare HMS — Installation Guide

## System Requirements

| Component  | Minimum Version |
|------------|----------------|
| PHP        | 8.0+           |
| MySQL      | 5.7+ / MariaDB 10.3+ |
| Web Server | Apache 2.4+ / Nginx 1.18+ |
| Browser    | Chrome 90+, Firefox 88+, Edge 90+ |

---

## 1. Clone / Download

```bash
# Place the project folder in your web root
cp -r hospital-management-system /var/www/html/
# or for XAMPP:
cp -r hospital-management-system C:/xampp/htdocs/
```

---

## 2. Database Setup

```sql
-- 1. Create the database and all tables
mysql -u root -p < database/hospital_management.sql

-- 2. Insert sample / demo data
mysql -u root -p hospital_management < database/seed_data.sql
```

Or via phpMyAdmin:
1. Open phpMyAdmin → Import
2. Import `database/hospital_management.sql`
3. Import `database/seed_data.sql`

---

## 3. Configure the Application

Edit **`config/config.php`** and set:

```php
// Or use environment variables (recommended for production)
define('APP_URL', 'http://localhost/hospital-management-system');
```

Edit **`config/database.php`** or set environment variables:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=hospital_management
DB_USER=root
DB_PASS=your_password
APP_URL=http://localhost/hospital-management-system
```

---

## 4. File Permissions

```bash
chmod -R 755 hospital-management-system/
chmod -R 777 hospital-management-system/assets/uploads/
```

---

## 5. Apache Virtual Host (optional)

```apache
<VirtualHost *:80>
    ServerName hms.local
    DocumentRoot /var/www/html/hospital-management-system
    <Directory /var/www/html/hospital-management-system>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable mod_rewrite:
```bash
a2enmod rewrite
systemctl restart apache2
```

---

## 6. Access the Application

Open your browser and go to:
```
http://localhost/hospital-management-system/
```

You will be redirected to the login page.

---

## 7. Default Login Credentials

All demo accounts use the password: **`password123`**

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@hospital.com | password123 |
| Doctor | anil.kapoor@hospital.com | password123 |
| Doctor | sunita.rao@hospital.com | password123 |
| Receptionist | kavita@hospital.com | password123 |
| Pharmacist | rajan@hospital.com | password123 |
| Lab Technician | meena@hospital.com | password123 |
| Patient | arjun.verma@email.com | password123 |

> ⚠️ **IMPORTANT**: Change all default passwords immediately in a production environment!

---

## 8. Project Structure Overview

```
hospital-management-system/
├── index.php              # Entry point (redirects based on auth)
├── login.php              # Login page
├── config/
│   ├── database.php       # PDO database singleton
│   ├── config.php         # App constants, helpers
│   └── auth.php           # Session auth, RBAC
├── includes/
│   ├── header.php         # HTML <head>
│   ├── sidebar.php        # Role-aware navigation
│   ├── navbar.php         # Top navigation bar
│   └── footer.php         # JS bundles, closing tags
├── assets/
│   ├── css/
│   │   ├── style.css      # Core styles, variables
│   │   ├── animations.css # All keyframe animations
│   │   ├── dashboard.css  # Dashboard-specific
│   │   └── responsive.css # Mobile / print breakpoints
│   ├── js/
│   │   ├── app.js         # HMS global object
│   │   ├── ajax.js        # Fetch wrapper
│   │   └── charts.js      # Chart.js wrappers
│   └── uploads/           # User-uploaded files (writable)
├── admin/
│   ├── dashboard.php      # Admin overview
│   ├── manage_patients.php
│   ├── manage_doctors.php
│   ├── appointments.php
│   ├── billing.php
│   ├── pharmacy.php
│   ├── laboratory.php
│   └── reports.php
├── doctor/
│   └── dashboard.php
├── receptionist/
│   └── dashboard.php
├── pharmacist/
│   └── dashboard.php
├── laboratory/
│   └── dashboard.php
├── patient/
│   └── dashboard.php
├── api/
│   ├── patients.php       # REST: patients
│   ├── doctors.php        # REST: doctors
│   ├── appointments.php   # REST: appointments
│   ├── billing.php        # REST: invoices
│   └── reports.php        # REST: lab orders
├── ajax/
│   ├── notifications.php  # Notification AJAX
│   ├── search_patient.php # Live search
│   └── load_dashboard.php # Stats refresh
├── authentication/
│   ├── logout.php
│   └── forgot_password.php
└── database/
    ├── hospital_management.sql  # Schema
    └── seed_data.sql            # Sample data
```

---

## 9. Security Checklist (Production)

- [ ] Change all default passwords
- [ ] Set `APP_URL` to your actual domain (HTTPS)
- [ ] Move `config/` outside the web root, or add `.htaccess` to deny direct access
- [ ] Set `display_errors = Off` in `php.ini`
- [ ] Enable HTTPS / SSL certificate
- [ ] Set strict file permissions on `assets/uploads/`
- [ ] Configure session settings for production (secure cookies, HTTPS-only)
- [ ] Set up regular database backups
- [ ] Consider adding CSRF tokens to all forms
- [ ] Review and restrict CORS headers for API endpoints
- [ ] Enable MySQL `strict` mode

---

## 10. CDN Dependencies (loaded from internet)

The project uses these CDNs — ensure internet access or download them locally:

| Library | Version | CDN |
|---------|---------|-----|
| Bootstrap | 5.3.3 | jsdelivr.net |
| Font Awesome | 6.5.1 | cdnjs.cloudflare.com |
| jQuery | 3.7.1 | code.jquery.com |
| DataTables | 1.13.8 | cdn.datatables.net |
| Chart.js | 4.4.2 | jsdelivr.net |
| ApexCharts | 3.46.0 | jsdelivr.net |
| Google Fonts | Plus Jakarta Sans | fonts.googleapis.com |

---

## 11. Troubleshooting

### "Database connection failed"
- Check `config/database.php` credentials
- Ensure MySQL is running
- Verify database name `hospital_management` exists

### "Page not found" / 404
- Ensure `APP_URL` matches your actual server path
- For Apache: enable `mod_rewrite`
- For Nginx: configure `try_files`

### Upload fails
- Check `assets/uploads/` is writable: `chmod -R 777 assets/uploads/`
- Check `upload_max_filesize` and `post_max_size` in php.ini

### Charts not rendering
- Check browser console for JS errors
- Ensure CDN resources load (internet connectivity)
- Verify Chart.js and ApexCharts are loaded before `charts.js`

---

## 12. Extending the System

### Adding a New Module
1. Create the page in the appropriate role folder (e.g., `admin/new_module.php`)
2. Add a nav link in `includes/sidebar.php`
3. Add API endpoints in `api/` if needed
4. Follow the same header/footer/auth pattern

### Adding a New Role
1. Add the role to the ENUM in `users` table
2. Add role handling in `config/auth.php` → `dashboardUrl()`
3. Create a folder `new_role/` with `dashboard.php`
4. Add nav links in `includes/sidebar.php`

---

*MediCare HMS v1.0.0 — Built with PHP 8+, MySQL, Bootstrap 5, Chart.js*
