# Task 04 — Medical Examination & Case Initiation

**Depends on:** Task 03 (`Patient` must exist); Task 01 (`WorkflowService` uses `AuditService`)  
**Produces:** `CaseRecord` at `stage_key = technical`; `MedicalRecord` locked; case visible to the Spec dashboard.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`; `WorkflowService` uses `AuditService`
- ✅ [Task 03 — Patient Registration](task_03_patient_registration.md) — `Patient` must exist; `Appointment` feeds the doctor queue

**Used By:**
- ✅ [Task 05 — Technical Spec](task_05_technical_spec.md) — spec queue reads cases at `stage_key = technical`

---

## Objective

The doctor processes the clinic queue, records diagnosis and prescription, and approves the exam. On approval, the system automatically creates a `CaseRecord` — the central aggregate that every subsequent task reads and advances. This is the single point where a patient visit becomes an active operational case.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Doctor | `/doctor/queue` — clinic waiting list |
| Doctor | `/doctor/diagnosis` — write diagnosis + prescription |
| Doctor | `/doctor/records` — approved records archive |
| Doctor | `/doctor/transfer` — cases transferred to spec queue |
| Reception | `/reception/patients` — active case now visible on patient card |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `Patient` | `patients` | Source of patient identity and type |
| `Appointment` | `appointments` | Queue entry; status updated to `in_clinic` / `done` |
| `MedicalRecord` | `medical_records` | Diagnosis + prescription; locked on approval |
| `MedicalRecordItem` | `medical_record_items` | Recommended items (codes only, no prices) |
| `CaseRecord` | `cases` | Created on exam approval — the central workflow aggregate |

---

## Controllers to Implement

### `App\Http\Controllers\MedicalRecord\MedicalRecordController`

| Method | Route | Description |
|---|---|---|
| `queue()` | `GET /doctor/queue` | Today's appointment list for the clinic |
| `create()` | `GET /doctor/diagnosis/{appointment}` | Open exam form for a patient |
| `store()` | `POST /doctor/diagnosis` | Save draft medical record |
| `lock()` | `POST /doctor/records/{record}/lock` | Approve and lock → triggers case initiation |
| `index()` | `GET /doctor/records` | Paginated approved records archive |
| `transfers()` | `GET /doctor/transfer` | Cases already forwarded to spec |

---

## Services to Implement

### `App\Services\CaseService`

```php
// Create CaseRecord from an approved medical record
// Called by WorkflowService after exam approval
public function initiate(Patient $patient, MedicalRecord $record): CaseRecord

// Central stage transition — wraps every stage change
// Called by every service that advances the workflow
public function advance(CaseRecord $case, string $event): void
```

**`initiate()` steps (inside `DB::transaction()`):**
1. Generate `case_no` → `CASE-YYYY-NNNN` (sequential per year).
2. Generate `order_ref` → `ORD-YYYY-NNNN` (same sequence, different prefix).
3. Copy `patient_type`, `rank`, `sovereign_entity` from `Patient`.
4. Copy `contract_company_id` from `Patient`.
5. Set `stage_key = reception`, `path = standard` (civilian) or `path = military`.
6. `CaseRecord::create([...])`.
7. `AuditService::log('create', 'إنشاء حالة جديدة', 'cases', null, $case)`.
8. Return `$case`.

### `App\Services\WorkflowService`

**The single authority over `stage_key` and `manufacturing_stage` transitions.**  
No controller or other service modifies these fields directly.

```php
public function advance(CaseRecord $case, string $event): void
```

**Transition map (mirrors analysis §2):**

| Event constant | Allowed from `stage_key` | Sets `stage_key` to | Side effects |
|---|---|---|---|
| `exam_approved` | `reception` / `exam` | `technical` | — |
| `spec_saved` | `technical` | `cost_calc` | Creates `PricingRequest` (via `SpecService`) |
| `pricing_completed_civilian` | `cost_calc` | `waiting_return` | Creates `Quote` (via `QuoteService`) |
| `pricing_completed_military` | `cost_calc` | `manufacturing` | Sets `manufacturing_stage = warehouse` |
| `approval_scanned` | `waiting_return` | `manufacturing` | Generates `work_order_no` (via `WorkOrderService`) |
| `bom_finished` | `manufacturing` | `ready_delivery` | Fires `SmsNotificationService::notifyReady()` |
| `delivered` | `ready_delivery` | `delivered` | Sets `delivered_at`; fires `FinancialPostingService::post()` |

**Rules:**
- `advance()` always runs inside `DB::transaction(lockForUpdate)`.
- If the transition is not in the map for the current `stage_key` → throw `InvalidWorkflowTransitionException`.
- Always calls `AuditService::log()` after every transition with before/after `stage_key`.

---

## MedicalRecord Lock Rules

When `lock()` is called:
1. `MedicalRecord.locked = true` — record becomes read-only; no further edits allowed.
2. `MedicalRecord.status = 'معتمد'`.
3. `Appointment.status = done` (if linked).
4. Calls `CaseService::initiate(patient, record)`.
5. Calls `WorkflowService::advance(case, 'exam_approved')` → `stage_key = technical`.
6. `AuditService::log('lock', 'اعتماد الكشف الطبي', 'medical', $before, $after)`.

---

## Doctor Role Data Isolation

The doctor **must never** see:
- Any `StockItemPrice.amount` value.
- Any `CaseRecord.quote_total` or `total_cost`.
- Any `ContractCompanyDebt` data.

`MedicalRecordItem` stores `stock_item_code` + `name` + `qty` only — no prices.

---

## Acceptance Criteria

- [ ] Locking a `MedicalRecord` creates a `CaseRecord` and advances it to `stage_key = technical`.
- [ ] Attempting to edit a locked `MedicalRecord` → 403.
- [ ] The case appears in the Spec dashboard queue after transition.
- [ ] `WorkflowService::advance()` with an invalid transition → exception, no DB change.
- [ ] All transitions logged to `audit_logs` with before/after stage.
- [ ] Doctor Blade views contain no price or financial fields.
