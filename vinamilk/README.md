# Vinamilk Contract & Document Lifecycle Management System

A professional enterprise web application for managing contract requests, approvals, and documents.

---

## Requirements

- XAMPP with Apache and MySQL (PHP 8.1+)
- A modern web browser

---

## Setup Instructions

### 1. Place the project

Ensure the project is located at:

```
C:\xampp\htdocs\vinamilk\
```

### 2. Create the database

1. Open your browser and navigate to: `http://localhost/phpmyadmin`
2. Click **Import** in the top menu.
3. Choose the file: `C:\xampp\htdocs\vinamilk\database\schema.sql`
4. Click **Go** to run the import.

Alternatively, open the XAMPP MySQL shell and run:

```sql
SOURCE C:/xampp/htdocs/vinamilk/database/schema.sql;
```

This will create the `vinamilk_contracts` database, all tables, and sample demo data.

### 3. Verify database configuration

Open `includes/config.php` and confirm:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'vinamilk_contracts');
define('DB_USER', 'root');
define('DB_PASS', '');   // Default XAMPP has no password
```

Change `DB_PASS` if your MySQL has a root password set.

### 4. Start XAMPP services

- Start **Apache**
- Start **MySQL**

### 5. Access the application

Open your browser and go to:

```
http://localhost/vinamilk/
```

You will be redirected to the login page.

---

## Demo Accounts

All demo accounts use the password: **password**

| Email | Role | Department |
|---|---|---|
| khoavd@vinamilk.com.vn | Manager/Admin | Legal (TS. Vũ Đình Khoa) |
| maitt@vinamilk.com.vn | Staff | Procurement (Trần Thị Mai) |
| binh@vinamilk.com.vn | Staff | Logistics (Nguyễn Văn Bình) |
| anh@vinamilk.com.vn | Staff | Legal (Trần Minh Anh) |
| an.nguyen@abclogistics.vn | External | ABC Logistics (partner portal) |

> **Note:** These credentials are for local development only. Do not use on any shared or production environment.

---

## Features

### Implemented

- Session-based authentication with role management (Staff / Approver / Admin)
- Dashboard with live metrics, recent requests, and activity log
- Create Contract Request — full form with frontend and backend validation, CSRF protection, and file upload
- Contract Requests listing with search, filters, and pagination
- Request Detail page with full information and approval history
- Approval workflow — Approvers can approve, reject, or return requests for revision
- Contracts page — Browse formally executed contracts
- Documents page — Browse all uploaded supporting documents
- Reports page — Summary statistics by status, type, department, and value
- Settings page (Admin only) — User management and system information
- Audit logging throughout
- Responsive design (desktop / tablet / mobile)

### Not yet implemented

- Inline document download (files are stored server-side)
- User self-registration and password reset
- Email notifications
- Multi-step approval workflows
- Export to Excel / PDF

---

## File Structure

```
vinamilk/
├── assets/
│   ├── css/app.css          Main stylesheet
│   └── js/app.js            Main JavaScript
├── database/
│   └── schema.sql           Database schema + demo data
├── includes/
│   ├── config.php           Application configuration
│   ├── db.php               PDO connection helper
│   ├── auth.php             Session, CSRF, permission helpers
│   ├── helpers.php          Utility functions
│   ├── layout_head.php      HTML shell header + sidebar + topnav
│   └── layout_foot.php      HTML shell footer
├── pages/
│   ├── create_request.php   Create Contract Request form
│   ├── requests.php         Contract Requests list
│   ├── request_detail.php   Request detail and approval history
│   ├── approvals.php        Approval queue and review form
│   ├── contracts.php        Contracts list
│   ├── documents.php        Documents list
│   ├── reports.php          Summary reports
│   ├── settings.php         Admin settings
│   └── 403.php              Access denied page
├── storage/
│   ├── documents/           Uploaded files (protected from direct access)
│   └── .htaccess            Deny direct file access
├── index.php                Dashboard
├── login.php                Login page
├── logout.php               Logout handler
└── README.md                This file
```

---

## Security Notes

- Passwords are hashed with `password_hash()` (bcrypt).
- All database queries use PDO prepared statements.
- CSRF tokens protect all state-changing forms.
- Uploaded files are stored outside the public web directory.
- Server-side role enforcement on every protected page.
- HTML output is escaped with `htmlspecialchars`.

---

## Troubleshooting

**Cannot connect to database**
→ Ensure MySQL is running and the credentials in `includes/config.php` are correct.

**Blank page or PHP errors**
→ Check Apache error logs at `C:\xampp\apache\logs\error.log`.

**File uploads not saving**
→ Ensure the `storage/documents/` directory exists and Apache has write permission.

**Login loop**
→ Verify the `vinamilk_contracts` database was created and demo data was imported.
