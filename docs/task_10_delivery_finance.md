# Task 10 — Delivery, Financial Settlement & Credit Notes

**Depends on:** Task 09 (`Bom.stage = finished`; case at `ready_delivery`); Task 02 (`ContractCompanyDebt` must exist for civilian)  
**Produces:** `CaseRecord.stage_key = delivered`; `ContractCompanyDebt.due` updated (civilian); `CaseRecord.total_cost` used for sovereign aggregation (military); case fully closed.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`
- ✅ [Task 02 — Master Data](task_02_master_data.md) — `ContractCompanyDebt` row must exist for civilian financial posting
- ✅ [Task 03 — Patient Registration](task_03_patient_registration.md) — `patient_qr` validated via `PatientQrService` at delivery scan
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — `BomService::canDeliver()` must return true (`Bom.stage = finished`)

**Used By:**
- ✅ [Task 11 — BI Reports](task_11_bi_reports.md) — BI Board 1 reads `delivered_at` for turnaround; Board 4 reads updated `ContractCompanyDebt` and military `total_cost`

---

## Objective

Reception closes the case by scanning the patient's QR card. The system verifies the BOM is finished, marks the case delivered, then executes financial posting: civilian → increases the entity's debt ledger; military → the `total_cost` already on the case is used for BI sovereign reporting (no separate posting). Post-delivery, admin can issue credit notes (civilian only).

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Reception | `/reception/delivery` — QR scan to close case |
| Admin | `/admin/debts` — view/record payments; approve credit notes |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `CaseRecord` | `cases` | `stage_key` set to `delivered`; `delivered_at` stamped |
| `Bom` | `boms` | Gate check: `stage = finished` |
| `ContractCompany` | `contract_companies` | Looked up for debt posting |
| `ContractCompanyDebt` | `contract_company_debts` | `due` incremented on delivery (civilian) |
| `CreditNote` | `credit_notes` | Post-delivery adjustment (civilian only) |

---

## Controllers to Implement

### `App\Http\Controllers\Delivery\DeliveryController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /reception/delivery` | List cases at `ready_delivery` |
| `scan()` | `POST /reception/delivery/scan` | Accept QR string → calls `DeliveryService::close()` |
| `show()` | `GET /reception/delivery/{case}` | Delivery detail view (no financial amounts visible to reception) |

### `App\Http\Controllers\Finance\DebtController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/debts` | All company debts with due / collected / remaining |
| `recordPayment()` | `POST /admin/debts/{company}/payment` | Record a payment → calls `ContractDebtService::recordPayment()` |

### `App\Http\Controllers\Finance\CreditNoteController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/debts` (same page, separate section) | List credit notes by status |
| `store()` | `POST /admin/debts/credit-notes` | Create credit note (civilian, post-delivery only) |
| `approve()` | `POST /admin/debts/credit-notes/{note}/approve` | Approve → calls `CreditNoteService::apply()` |
| `reject()` | `POST /admin/debts/credit-notes/{note}/reject` | Reject |

---

## Services to Implement

### `App\Services\DeliveryService`

```php
public function close(CaseRecord $case, string $scannedQr): void

public function canDeliver(CaseRecord $case): bool  // delegates to BomService
```

**`close()` steps (inside `DB::transaction()`):**
1. Call `BomService::canDeliver($case)` — if false → throw `DeliveryNotReadyException` with reason.
2. Validate `scannedQr` matches `$case->patient->patient_qr` via `PatientQrService::validate()`.
3. Set `CaseRecord.stage_key = delivered`, `CaseRecord.delivered_at = today()`.
4. Call `WorkflowService::advance($case, 'delivered')`.
5. Call `FinancialPostingService::post($case)`.
6. `AuditService::log('deliver', 'تسليم الطرف للمريض', 'delivery', $before, $after)`.

---

### `App\Services\FinancialPostingService`

```php
public function post(CaseRecord $case): void
```

**Civilian path:**
1. Retrieve `ContractCompany` via `$case->contract_company_id`.
2. Call `ContractDebtService::increaseDue($company, $case->quote_total)`.
3. `AuditService::log('post', 'ترحيل مستحق مدني', 'finance', $before, $after)`.

**Military path:**
1. No `ContractCompanyDebt` row is written.
2. `CaseRecord.total_cost` is already set from Task 06.
3. This value will be aggregated in BI Board 4 (`boardEntitiesAndCosts()`) via a SUM query on military delivered cases.
4. `AuditService::log('post', 'ترحيل تكلفة عسكري', 'finance', null, ['total_cost' => $case->total_cost])`.

---

### `App\Services\ContractDebtService` (extended from Task 02)

```php
// Increase due amount on delivery
public function increaseDue(ContractCompany $company, float $amount): void

// Record a payment received from the entity
public function recordPayment(ContractCompany $company, float $amount): void
```

**`increaseDue()` steps:**
1. `$debt->increment('due', $amount)`.
2. Recalculate `status`: if `collected >= due` → `paid`; if `collected > 0` → `partial`; else `pending`.
3. `AuditService::log('debt', 'زيادة مستحق جهة', 'finance', $before, $after)`.

**`recordPayment()` steps:**
1. `$debt->increment('collected', $amount)`.
2. Recalculate `status` same as above.
3. `AuditService::log('payment', 'تسجيل تحصيل', 'finance', $before, $after)`.

---

### `App\Services\CreditNoteService`

```php
public function apply(CreditNote $note): void
```

**`apply()` steps (inside `DB::transaction()`):**
1. Validate `note->status == pending`.
2. Validate linked case is `delivered` and is civilian.
3. `ContractDebtService::decreaseDue($company, $note->amount)` → new method: decrements `due`.
4. Set `CaseRecord.credit_note_no`, `credit_note_amount`.
5. Set `CreditNote.status = approved`, `approved_at`, `approved_by_user_id`.
6. `AuditService::log('credit_note', 'تطبيق إشعار دائن', 'finance', $before, $after)`.

---

### `App\Services\SmsNotificationService`

```php
// Called from BomService::closeFinished() in Task 09
public function notifyReady(CaseRecord $case): void
```

**Steps:**
1. Build message: `"طرفك جاهز للتسليم، يرجى التواصل مع مركز الأطراف الصناعية"` + case reference.
2. Send to `$case->patient->phone` (if set).
3. Send to company contact if available.
4. `AuditService::log('sms', 'إرسال إشعار SMS جاهز للتسليم', 'sms', null, ['case' => $case->case_no])`.
5. If SMS gateway unavailable → log failure, do not throw (delivery must not be blocked by SMS failure).

---

## Acceptance Criteria

- [ ] Scanning wrong QR at delivery → error; case unchanged.
- [ ] Scanning correct QR when BOM is not finished → `DeliveryNotReadyException`.
- [ ] After delivery: `CaseRecord.stage_key = delivered`, `delivered_at` set.
- [ ] Civilian delivery → `ContractCompanyDebt.due` incremented by `quote_total`.
- [ ] Military delivery → no `ContractCompanyDebt` row written.
- [ ] Credit note approved on a military case → rejected.
- [ ] Credit note `apply()` reduces `ContractCompanyDebt.due`.
- [ ] SMS failure does not block the delivery transaction.
- [ ] All steps logged to `audit_logs`.
- [ ] Reception Blade views at `/reception/delivery` show no financial amounts.
