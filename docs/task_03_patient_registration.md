# Task 03 — Patient Registration & Appointment Scheduling

**Depends on:** Task 02 (`ContractCompany` rows must exist for FK linkage)  
**Produces:** `Patient` rows with `patient_code` + `patient_qr`; `Appointment` rows feeding the doctor queue.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`
- ✅ [Task 02 — Master Data](task_02_master_data.md) — `ContractCompany` rows must exist for patient FK

**Used By:**
- ✅ [Task 04 — Medical Exam](task_04_medical_exam.md) — doctor queue reads `Appointment`; `Patient` linked to `CaseRecord`
- ✅ [Task 10 — Delivery & Finance](task_10_delivery_finance.md) — `PatientQrService::validate()` verifies QR at delivery scan

---

## Objective

Reception desk creates the patient file (with type classification and QR card), links the patient to their contract company, and schedules appointments. The `Patient` record is the anchor that all downstream domain objects — Case, MedicalRecord, Quote, BOM — reference.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Reception | `/reception/patients` — register + search patients |
| Reception | `/reception/appointments` — calendar + day queue |
| Reception | `/reception/selfservice` — patient-facing QR status lookup (no auth required) |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `Patient` | `patients` | Central patient file with QR and type flag |
| `Appointment` | `appointments` | Scheduled visits; feeds doctor queue |
| `ContractCompany` | `contract_companies` | Read-only here — linked to patient on registration |

---

## Controllers to Implement

### `App\Http\Controllers\Patient\PatientController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /reception/patients` | Paginated list; filterable by type, status, company |
| `store()` | `POST /reception/patients` | Create patient → calls `PatientService::register()` |
| `update()` | `PUT /reception/patients/{patient}` | Edit non-identity fields (phone, company link) |
| `show()` | `GET /reception/patients/{patient}` | Patient card with QR, last case summary |

### `App\Http\Controllers\Appointment\AppointmentController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /reception/appointments` | Day/week view; returns today's list by default |
| `store()` | `POST /reception/appointments` | Book appointment; optionally links to patient |
| `update()` | `PUT /reception/appointments/{appt}` | Reschedule or change visit type |
| `updateStatus()` | `PATCH /reception/appointments/{appt}/status` | Advance status (`waiting → in_clinic → done`) |

### `App\Http\Controllers\Patient\SelfServiceController`

| Method | Route | Description |
|---|---|---|
| `status()` | `GET /selfservice/{qr}` | **Public** (no auth). Returns `stage_key`, queue position, expected delivery. Returns 404 for invalid QR. |

---

## Services to Implement

### `App\Services\PatientQrService`

```php
// Generate a unique QR string for a new patient
// Format: QR-{TYPE}-{NNNN} derived from patient_code
public function generate(string $patientCode): string

// Validate that a scanned QR string belongs to the given patient
// Used at delivery scan (Task 10)
public function validate(string $qrString, Patient $patient): bool
```

### `App\Services\PatientService`

```php
// Full registration flow — called by PatientController::store()
public function register(array $data): Patient
```

**`register()` steps (inside `DB::transaction()`):**
1. Determine next sequence number per type from `patients` table.
2. Generate `patient_code` → `PT-CIV-0001` or `PT-MIL-0001`.
3. Call `PatientQrService::generate($patient_code)` → store as `patient_qr`.
4. For military: require `rank` + `sovereign_entity`; `contract_company_id` = null is allowed.
5. For civilian: require `contract_company_id`.
6. `Patient::create([...])`.
7. `AuditService::log('create', 'تسجيل مريض جديد', 'patients', null, $patient->only(...))`.
8. Return patient.

---

## Civilian / Military Field Rules

| Field | Civilian | Military |
|---|---|---|
| `contract_company_id` | Required | Optional / null |
| `rank` | null | Required |
| `sovereign_entity` | null | Required |
| `patient_qr` | Always generated | Always generated |
| `patient_code` prefix | `PT-CIV-` | `PT-MIL-` |

---

## Self-Service Endpoint Logic

The `/selfservice/{qr}` route is **unauthenticated**. It returns a minimal JSON/Blade view:
```json
{
  "stage_label": "تحت التصنيع",
  "queue_position": 3,
  "expected_delivery": null
}
```
- `queue_position` = count of cases at `manufacturing` stage ahead of this one (ordered by `created_at`).
- `expected_delivery` = null until system has SLA data wired (Task 11).
- No patient name, no financial data exposed.

---

## Acceptance Criteria

- [ ] Registering a civilian patient without `contract_company_id` → validation error.
- [ ] Registering a military patient without `rank` → validation error.
- [ ] `patient_code` and `patient_qr` are unique and never change after creation.
- [ ] `/selfservice/{invalid-qr}` → 404.
- [ ] Every patient create/update logged to `audit_logs`.
- [ ] Reception user cannot see any price fields on patient cards.
