# CareSync EHR — Electronic Health Records & Personalised Care Planner

A complete, production-ready EHR web application built with **Vanilla PHP 8.x**, **MySQL/PDO**, and **Tailwind CSS**.

---

## 📁 File Structure

```
ehr-app/
├── config/
│   └── db.php                  # PDO singleton database connection
├── includes/
│   ├── auth.php                # Session, CSRF, role helpers
│   ├── header.php              # Global HTML head + sidebar navigation
│   └── footer.php              # Closing tags, footer bar
├── index.php                   # Login / landing page
├── register.php                # New user registration (doctor or patient)
├── logout.php                  # Session destruction
├── doctor_dashboard.php        # Clinician portal — patient directory + stats
├── patient_view.php            # Doctor's detailed patient EHR + care plan builder
├── patient_dashboard.php       # Patient portal — health summary + daily checklist
├── process_action.php          # Centralised POST handler (PRG pattern)
└── schema.sql                  # Full MySQL schema + seed data
```

---

## ⚙️ Setup Instructions

### 1. Database

```bash
mysql -u root -p < schema.sql
```

This creates the `ehr_db` database with all tables and seeds two demo accounts.

### 2. Web Server

Point your web server (Apache/Nginx) document root at the `ehr-app/` folder, **or** use PHP's built-in dev server:

```bash
cd ehr-app
php -S localhost:8000
```

Then visit: [http://localhost:8000](http://localhost:8000)

### 3. Database Configuration

By default the app connects to `localhost` with user `root` and no password.

Override any value via environment variables (recommended for production):

```bash
export DB_HOST=localhost
export DB_NAME=ehr_db
export DB_USER=your_db_user
export DB_PASS=your_db_password
```

Or edit `config/db.php` directly for local development.

---

## 🔐 Demo Accounts

| Role    | Email              | Password    |
|---------|--------------------|-------------|
| Doctor  | doctor@ehr.dev     | password123 |
| Patient | patient@ehr.dev    | password123 |

---

## ✨ Features

### 👩‍⚕️ Doctor / Clinician
- **Dashboard** — stats overview (total patients, active care plans, recent vitals)
- **Patient Directory** — searchable table with blood type, active conditions, care plan status
- **Patient Profile View** — full EHR including:
  - Latest vitals with history
  - Allergies with severity
  - Diagnoses / medical history
  - Current care plan summary
- **Record Vitals** — inline form per patient visit
- **Add Allergy** — with severity classification
- **Add Diagnosis** — with ICD-10 code, date, status
- **Care Plan Builder** — dynamic task list (add/remove rows) with medication name, dosage, frequency

### 🧑 Patient
- **Health Dashboard** — personal stats, latest vitals, allergies, diagnoses
- **Personalised Care Plan** — view plan assigned by doctor (goals, diet, exercise notes)
- **Daily Checklist** — check off tasks for today; visual progress bar

---

## 🔒 Security

| Feature | Implementation |
|---|---|
| SQL Injection | PDO prepared statements on every query |
| Password storage | `password_hash()` with BCRYPT cost 12 |
| Password verification | `password_verify()` |
| CSRF protection | Per-session token checked on every POST |
| Session fixation | `session_regenerate_id(true)` on login |
| XSS prevention | `htmlspecialchars()` via `e()` helper on all output |
| Input validation | `filter_input()` / `filter_var()` throughout |
| Role enforcement | `require_role()` guard on every protected page |
| Patient isolation | Patients can only toggle their own tasks (user_id from session) |
| Redirect after POST | PRG pattern in `process_action.php` prevents duplicate submissions |

---

## 🛠 Tech Stack

- **PHP 8.x** — no frameworks, no Composer dependencies
- **MySQL 8.x** — InnoDB with foreign key constraints
- **PDO** — native prepared statements, `ERRMODE_EXCEPTION`
- **Tailwind CSS 3** — via CDN, configured with custom brand palette
- **Google Fonts** — DM Sans + DM Mono

---

## 📋 Database Schema Overview

```
users               → base account (doctor | patient)
patient_profiles    → demographics, emergency contact (1:1 with patient users)
vitals              → time-series vital signs per patient
allergies           → allergen registry with severity
diagnoses           → medical history / ICD-coded conditions
care_plans          → one active plan per patient
care_plan_tasks     → individual checklist items (medication / exercise / diet…)
task_completions    → daily patient check-offs (unique per task+date)
```

---

## 🚀 Production Checklist

- [ ] Move DB credentials to `.env` or server environment variables
- [ ] Enable HTTPS and set `'secure' => true` on session cookie in `auth.php`
- [ ] Set `display_errors = Off` and `log_errors = On` in `php.ini`
- [ ] Add rate limiting / captcha to `index.php` and `register.php`
- [ ] Restrict `register.php` to invite-only or admin-approved flows
- [ ] Add audit logging table for all clinical data changes
- [ ] Consider adding soft-delete instead of hard-delete for medical records
