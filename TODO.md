# TODO — Usability improvements for CareSync EHR (PHP)

## Step 1 — Tracking file

- [x] Create this `TODO.md`

## Step 2 — Fix small usability bug

- [x] Fix patient age calculation display in `patient_dashboard.php` (there appeared to be an extra parenthesis causing incorrect output / potential parse issue).

## Step 3 — Accessibility improvements

- [x] Add accessibility attributes for dynamic care plan builder in `patient_view.php`:
  - [x] Add `aria-label` / `title` for the icon-only “remove task” button.
  - [x] Add accessible labels for task completion toggle buttons/checkbox semantics on the patient checklist.

## Step 4 — Checklist usability polish (patient experience)

- [x] Improve checklist “completed” visual readability on `patient_dashboard.php` (avoid overly low contrast).
- [x] Add small helper text (“Tap the checkbox to mark complete”) if it can fit without changing layout too much.

## Step 5 — Safer, clearer input constraints (forms)

- [x] Add/adjust HTML input constraints in `patient_view.php` forms (where low-risk):
  - [x] vitals: add pattern/input hints for blood pressure (backend remains authoritative)
  - [x] care plan tasks: add max-length constraints to align with server truncation
