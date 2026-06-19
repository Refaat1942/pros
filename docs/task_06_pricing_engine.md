# Task 06 — Pricing Engine (Civilian & Military)

**Depends on:** Task 05 (`PricingRequest` at `status_key = pending`); Task 02 (`StockItemPrice` data must exist)  
**Produces (civilian):** `Quote` + `QuoteItem`; case at `stage_key = waiting_return`. **Produces (military):** `CaseRecord.total_cost` set; case at `stage_key = manufacturing`.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`
- ✅ [Task 02 — Master Data](task_02_master_data.md) — `StockItemPrice` batches must exist for `highestUnitPrice()`
- ✅ [Task 05 — Technical Spec](task_05_technical_spec.md) — `PricingRequest` at `status_key = pending` must exist

**Used By:**
- ✅ [Task 07 — Quote Approval](task_07_quote_approval.md) — civilian path: `Quote` produced here is issued and scanned in Task 07
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — military path: case advances directly to `manufacturing` after this task; civilian path: reaches manufacturing via Task 07
- ✅ [Task 11 — BI Reports](task_11_bi_reports.md) — BI Board 4 reads `CaseRecord.total_cost` (military) and `quote_total` (civilian)

---

## Objective

The pricing engine calculates the case cost using **Highest Purchase Price** per item (never WAC). For civilians the admin approves, then a `Quote` is issued. For military the calculation runs silently in the background — no quote, no gate — and the case advances directly to manufacturing.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Admin | `/admin/pricing` — approve pending pricing requests |
| Spec | `/spec/pricing` — read-only: status of submitted requests |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `PricingRequest` | `pricing_requests` | Pricing job header; holds status and approval |
| `PricingRequestItem` | `pricing_request_items` | Line items (code + qty) from spec |
| `StockItem` | `stock_items` | Looked up to resolve each item code |
| `StockItemPrice` | `stock_item_prices` | Source of `highestUnitPrice` per item |
| `CaseRecord` | `cases` | Receives `total_cost`; stage advanced by WorkflowService |
| `Quote` | `quotes` | Created for civilian cases on admin approval |
| `QuoteItem` | `quote_items` | Line items of the civilian quote |

---

## Controllers to Implement

### `App\Http\Controllers\Pricing\PricingApprovalController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/pricing` | List pending pricing requests (paginated) |
| `approve()` | `POST /admin/pricing/{request}/approve` | Approve → calls `PricingService::approve()` |
| `show()` | `GET /admin/pricing/{request}` | Detail view with line items and calculated total |

---

## Services to Implement

### `App\Services\PricingService`

```php
// Calculate total cost for a pricing request using highestUnitPrice
// Called automatically when PricingRequest is created (Task 05 trigger)
public function calculate(PricingRequest $request): void

// Admin approves → branch on patient_type
public function approve(PricingRequest $request, User $approver): void
```

**`calculate()` steps (inside `DB::transaction()`):**
1. For each `PricingRequestItem`:
   - Call `StockPriceService::highestUnitPrice($item->stock_item_code)`.
   - If no price found → log warning but continue with 0 (do not block).
   - Line total = `qty × highestUnitPrice`.
2. Sum all line totals → total.
3. Update `PricingRequest` with computed total (optional intermediate storage — field may be added if needed).
4. `AuditService::log('calculate', 'احتساب تكلفة الحالة', 'pricing', null, $totals)`.

**`approve()` steps (inside `DB::transaction()`):**
1. Validate `request->status_key == pending`.
2. Set `approved_at`, `approved_by_user_id`, `step = 2`.
3. Branch on `request->patient_type`:
   - **Civilian:** call `QuoteService::issue($request)` → creates Quote → `WorkflowService::advance(case, 'pricing_completed_civilian')`.
   - **Military:** set `CaseRecord.total_cost = computed_total` → `WorkflowService::advance(case, 'pricing_completed_military')` → case goes directly to `manufacturing`.
4. `AuditService::log('approve', 'اعتماد طلب التسعير', 'pricing', $before, $after)`.

### `App\Services\QuoteService`

```php
// Issue a civilian quote from an approved pricing request
public function issue(PricingRequest $request): Quote
```

**`issue()` steps (inside same transaction as `approve()`):**
1. Generate `quote_no` → `QT-YYYY-NNNN`.
2. `Quote::create([...])` with `status = pending`, linked to `pricing_request_id` and `case_id`.
3. For each `PricingRequestItem` → `QuoteItem::create([...])` with `qty`, `amount = qty × highestUnitPrice`.
4. Store `Quote.total`.
5. Update `CaseRecord.quote_no`, `quote_date`, `quote_total`.
6. `AuditService::log('create', 'إنشاء عرض السعر', 'quotes', null, $quote)`.

---

## Pricing Rule (Critical)

```
Quote price per item = highestUnitPrice(stock_item_code)
                     = MAX(amount) FROM stock_item_prices
                       WHERE stock_item_id = ? AND qty > 0
```

**This is NOT WAC.** WAC is used only for inventory valuation (BI Board 2). These two values must never be swapped.

---

## Military Path (Silent Pricing)

For military cases:
- `PricingRequest` is still created (for audit trail completeness).
- `calculate()` runs the same way.
- On approval: NO `Quote` created. NO `waiting_return` stage.
- `CaseRecord.total_cost` is set; case advances directly to `manufacturing`.
- The cost will be aggregated in BI Board 4 under "التكلفة الافتراضية العسكرية".

---

## Acceptance Criteria

- [ ] `highestUnitPrice()` returns 0 (not an error) for items with no valid price batches.
- [ ] Civilian approval → `Quote` created; case at `waiting_return`.
- [ ] Military approval → no `Quote` row; case at `manufacturing`; `CaseRecord.total_cost` populated.
- [ ] Admin sees line-item breakdown with computed amounts on the approval detail view.
- [ ] `approve()` called twice on same request → second call rejected (status guard).
- [ ] All steps logged to `audit_logs`.
