-- ============================================================
-- EHR & Personalized Care Planner — Database Schema
-- Engine: MySQL 8.x | Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS ehr_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ehr_db;

-- ---------------------------------------------------------------
-- 1. USERS  (both doctors and patients share this table)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(120)  NOT NULL,
    email        VARCHAR(180)  NOT NULL UNIQUE,
    password     VARCHAR(255)  NOT NULL,          -- bcrypt hash
    role         ENUM('doctor','patient') NOT NULL DEFAULT 'patient',
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 2. PATIENT PROFILES  (1-to-1 with users where role='patient')
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS patient_profiles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    date_of_birth   DATE,
    gender          ENUM('male','female','non-binary','prefer_not_to_say'),
    phone           VARCHAR(30),
    address         TEXT,
    blood_type      ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    emergency_contact_name  VARCHAR(120),
    emergency_contact_phone VARCHAR(30),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 3. VITALS  (time-series per patient)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vitals (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id          INT UNSIGNED NOT NULL,   -- references users.id
    recorded_by         INT UNSIGNED NOT NULL,   -- doctor user id
    blood_pressure      VARCHAR(20),             -- e.g. "120/80"
    heart_rate          TINYINT UNSIGNED,        -- bpm
    temperature         DECIMAL(4,1),            -- Celsius
    weight_kg           DECIMAL(5,2),
    height_cm           DECIMAL(5,1),
    oxygen_saturation   TINYINT UNSIGNED,        -- % SpO2
    notes               TEXT,
    recorded_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vitals_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_vitals_doctor  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 4. ALLERGIES
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS allergies (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id   INT UNSIGNED NOT NULL,
    allergen     VARCHAR(150) NOT NULL,
    reaction     VARCHAR(200),
    severity     ENUM('mild','moderate','severe') DEFAULT 'mild',
    added_by     INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_allergy_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_allergy_doctor  FOREIGN KEY (added_by)   REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 5. DIAGNOSES / MEDICAL HISTORY
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS diagnoses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id    INT UNSIGNED NOT NULL,
    doctor_id     INT UNSIGNED NOT NULL,
    icd_code      VARCHAR(20),                   -- optional ICD-10 code
    title         VARCHAR(200) NOT NULL,
    description   TEXT,
    diagnosed_on  DATE,
    status        ENUM('active','resolved','chronic') DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_diag_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_diag_doctor  FOREIGN KEY (doctor_id)  REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 6. CARE PLANS  (one active plan per patient)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS care_plans (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id   INT UNSIGNED NOT NULL UNIQUE,   -- one plan per patient
    doctor_id    INT UNSIGNED NOT NULL,
    title        VARCHAR(200) NOT NULL,
    goals        TEXT,                            -- general goals / notes
    diet_notes   TEXT,
    exercise_notes TEXT,
    start_date   DATE,
    review_date  DATE,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cp_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_doctor  FOREIGN KEY (doctor_id)  REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 7. CARE PLAN TASKS  (individual checklist items)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS care_plan_tasks (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    care_plan_id   INT UNSIGNED NOT NULL,
    task_type      ENUM('medication','exercise','diet','lifestyle','other') NOT NULL DEFAULT 'other',
    description    VARCHAR(300) NOT NULL,
    medication_name   VARCHAR(150),
    dosage            VARCHAR(100),
    frequency         VARCHAR(100),              -- e.g. "Twice daily with food"
    sort_order     TINYINT UNSIGNED DEFAULT 0,
    CONSTRAINT fk_cpt_plan FOREIGN KEY (care_plan_id) REFERENCES care_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- 8. TASK COMPLETIONS  (patient daily check-offs)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS task_completions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id      INT UNSIGNED NOT NULL,
    patient_id   INT UNSIGNED NOT NULL,
    completed_on DATE NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_task_day (task_id, patient_id, completed_on),
    CONSTRAINT fk_tc_task    FOREIGN KEY (task_id)    REFERENCES care_plan_tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- SEED DATA — demo doctor + patient accounts
-- Passwords are 'password123' hashed with bcrypt (cost 12)
-- ---------------------------------------------------------------
INSERT INTO users (full_name, email, password, role) VALUES
(
  'Dr. Sarah Mitchell',
  'doctor@ehr.dev',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRgMUzc6e',  -- password123
  'doctor'
),
(
  'James Carter',
  'patient@ehr.dev',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRgMUzc6e',  -- password123
  'patient'
);

INSERT INTO patient_profiles (user_id, date_of_birth, gender, phone, blood_type) VALUES
(2, '1985-06-15', 'male', '555-0192', 'O+');

INSERT INTO vitals (patient_id, recorded_by, blood_pressure, heart_rate, temperature, weight_kg, height_cm, oxygen_saturation, notes) VALUES
(2, 1, '122/78', 72, 36.8, 78.5, 175.0, 98, 'Routine check-up. Patient appears well.');

INSERT INTO allergies (patient_id, allergen, reaction, severity, added_by) VALUES
(2, 'Penicillin',   'Hives, difficulty breathing', 'severe',   1),
(2, 'Pollen',       'Sneezing, watery eyes',        'mild',     1),
(2, 'Latex',        'Contact dermatitis',           'moderate', 1);

INSERT INTO diagnoses (patient_id, doctor_id, icd_code, title, description, diagnosed_on, status) VALUES
(2, 1, 'E11', 'Type 2 Diabetes Mellitus', 'Well-controlled with lifestyle modifications and oral medication.', '2021-03-10', 'chronic'),
(2, 1, 'I10', 'Essential Hypertension',   'Mild hypertension, monitored regularly.',                          '2022-07-22', 'active');

INSERT INTO care_plans (patient_id, doctor_id, title, goals, diet_notes, exercise_notes, start_date, review_date) VALUES
(2, 1,
 'Diabetes & Hypertension Management Plan',
 'Achieve HbA1c < 7.0%, maintain blood pressure below 130/80 mmHg, and improve overall cardiovascular fitness.',
 'Low-glycaemic index diet. Limit sodium to < 2g/day. Avoid processed sugars and saturated fats. Increase fibre intake with vegetables and whole grains.',
 '30 minutes of moderate aerobic exercise (brisk walking, cycling) at least 5 days per week. Incorporate resistance training 2x per week.',
 '2024-01-15', '2024-07-15'
);

INSERT INTO care_plan_tasks (care_plan_id, task_type, description, medication_name, dosage, frequency, sort_order) VALUES
(1, 'medication', 'Take Metformin as prescribed',      'Metformin',   '500mg',  'Twice daily with meals',           1),
(1, 'medication', 'Take Lisinopril as prescribed',     'Lisinopril',  '10mg',   'Once daily in the morning',        2),
(1, 'medication', 'Monitor blood glucose levels',      NULL,          NULL,     'Every morning before breakfast',   3),
(1, 'exercise',   'Brisk walk or cycling session',     NULL,          NULL,     '30 minutes, at least 5x per week', 4),
(1, 'exercise',   'Resistance / strength training',    NULL,          NULL,     '2x per week',                      5),
(1, 'diet',       'Log daily food intake',             NULL,          NULL,     'Every day',                        6),
(1, 'lifestyle',  'Check blood pressure at home',      NULL,          NULL,     'Every morning',                    7),
(1, 'lifestyle',  'Stay hydrated — drink water',       NULL,          NULL,     'Minimum 8 glasses per day',        8);
