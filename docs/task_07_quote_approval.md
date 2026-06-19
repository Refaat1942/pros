# Task 07 — Civilian Quote Issuance & Approval Gate (OCR / QR Scan)

**Depends on:** Task 06 (`Quote` at `status = pending`; case at `waiting_return`)  
**Applies to:** Civilian cases only — military cases skip this task entirely.  
**Produces:** `CaseRecord.approval_confirmed_at` set; `work_order_no` generated; case at `stage_key = manufacturing`.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`
- ✅ [Task 06 — Pricing Engine](task_06_pricing_engine.md) — `Quote` at `status = pending`; case at `waiting_return`

> **Military cases skip this task entirely.** They go directly from Task 06 → Task 09.

**Used By:**
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — civilian cases only: `work_order_no` generated here is required before BOM creation

---

## Objective

Reception issues the official quote document (printed with a QR code) to the patient's entity. The system then waits for the signed approval letter to be returned. When the letter is scanned (QR or manual OCR), the case is unlocked, a work order number is generated, and the case advances to manufacturing — enabling BOM creation in Task 09.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Reception | `/reception/quote` — issue quote, print, track status |
| Reception | `/reception/ocr` — upload / scan approval letter |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `Quote` | `quotes` | Status updated from `pending` → `issued` → `approved` |
| `CaseRecord` | `cases` | Receives `approval_confirmed_at`, `approval_date`, `work_order_no`; stage advanced |

---

## Controllers to Implement

### `App\Http\Controllers\Quote\QuoteController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /reception/quote` | List quotes: pending issue, issued, returned |
| `issue()` | `POST /reception/quote/{quote}/issue` | Mark quote as issued → prints QR label → calls `QuoteService::markIssued()` |
| `print()` | `GET /reception/quote/{quote}/print` | Blade print view with embedded QR |

### `App\Http\Controllers\Quote\ApprovalScanController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /reception/ocr` | Upload / scan interface |
| `scan()` | `POST /reception/ocr/scan` | Validate QR payload → calls `ApprovalService::confirm()` |

---

## Services to Implement

### `App\Services\QuoteService` (extended from Task 06)

```php
// Mark the quote as officially issued to the entity
// Generates QR string for the printed document
public function markIssued(Quote $quote): void
```

**`markIssued()` steps:**
1. Validate `quote->status == pending`.
2. Set `Quote.status = issued`.
3. `AuditService::log('issue', 'إصدار عرض السعر', 'quotes', $before, $after)`.

### `App\Services\ApprovalService`

```php
// Process the scanned QR from the entity's approval letter
public function confirm(CaseRecord $case, string $scannedQr): void
```

**`confirm()` steps (inside `DB::transaction()`):**
1. Validate `case->stage_key == waiting_return`.
2. Validate `scannedQr` matches `Quote.quote_no` linked to this case (the QR on the printed quote encodes the `quote_no`).
3. Set `CaseRecord.approval_date = today()`.
4. Set `CaseRecord.approval_confirmed_at = now()`.
5. Call `WorkOrderService::generate($case)` → sets `CaseRecord.work_order_no`.
6. Set `Quote.status = approved`.
7. Call `WorkflowService::advance($case, 'approval_scanned')` → `stage_key = manufacturing`, `manufacturing_stage = warehouse`.
8. `AuditService::log('scan', 'مسح موافقة الجهة', 'quotes', $before, $after)`.

### `App\Services\WorkOrderService`

```php
// Generate a unique work order number and persist it on the case
public function generate(CaseRecord $case): string
```

**Format:** `WO-YYYY-NNNN` — sequential per year, generated inside a `DB::transaction()` with `lockForUpdate` to prevent duplicates.

---

## QR Encoding Note

The QR code embedded in the printed quote document encodes the `quote_no` string (e.g., `QT-2026-0047`). When the entity returns the signed letter and the receptionist scans it, the system decodes this string, looks up the `Quote` by `quote_no`, and confirms the matching case. The QR does not need to be encrypted — it is a reference code, not a security token. Tampering would be caught by case status validation.

---

## What Happens if Approval Never Arrives

- Case stays at `waiting_return` indefinitely.
- This case appears in BI Board 1 as an "SLA breached" open case if the wait exceeds the configured SLA limit (default 21 days from `quote_date`).
- No automatic timeout or rejection in this version.

---

## Acceptance Criteria

- [ ] Issuing a quote that is not `pending` → rejected.
- [ ] Scanning a QR that does not match the linked quote → validation error; case unchanged.
- [ ] After valid scan: `work_order_no` is unique and formatted `WO-YYYY-NNNN`.
- [ ] Case moves to `manufacturing` with `manufacturing_stage = warehouse`.
- [ ] `Quote.status = approved`.
- [ ] Military cases do not appear in the `/reception/quote` list.
- [ ] All steps logged to `audit_logs`.
