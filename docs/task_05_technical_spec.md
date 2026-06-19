# Task 05 — Technical Specification

**Depends on:** Task 04 (`CaseRecord` at `stage_key = technical`); Task 02 (stock item codes must exist in `stock_items`)  
**Produces:** `TechOrderSpec` + `TechOrderSpecItem` locked; `PricingRequest` + `PricingRequestItem` created; case advanced to `stage_key = cost_calc`.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`
- ✅ [Task 02 — Master Data](task_02_master_data.md) — `stock_item_code` values validated against `stock_items`
- ✅ [Task 04 — Medical Exam](task_04_medical_exam.md) — `CaseRecord` must be at `stage_key = technical`

**Used By:**
- ✅ [Task 06 — Pricing Engine](task_06_pricing_engine.md) — reads `PricingRequestItem` rows to calculate cost
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — BOM items can be prefilled from `TechOrderSpecItem`

---

## Objective

The specialist receives the case from the doctor queue, records the required item codes and quantities (no prices, no stock balance visible), and submits the specification. Submission automatically creates a `PricingRequest` and advances the case into the pricing engine.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Spec | `/spec/orders` — incoming cases awaiting spec |
| Spec | `/spec/spec` — write and preview spec items |
| Spec | `/spec/pricing` — read-only status after submission |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `CaseRecord` | `cases` | Must be at `stage_key = technical` |
| `TechOrderSpec` | `tech_order_specs` | Header: links to case, records doctor/spec notes |
| `TechOrderSpecItem` | `tech_order_spec_items` | Line items: code + quantity only |
| `PricingRequest` | `pricing_requests` | Created on submission; feeds Task 06 |
| `PricingRequestItem` | `pricing_request_items` | Mirrors spec items for the pricing engine |

---

## Controllers to Implement

### `App\Http\Controllers\TechOrderSpec\TechOrderSpecController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /spec/orders` | List cases at `stage_key = technical` |
| `create()` | `GET /spec/spec/{case}` | Open spec form for a case |
| `store()` | `POST /spec/spec` | Save draft spec + items |
| `update()` | `PUT /spec/spec/{spec}` | Edit items while still draft |
| `submit()` | `POST /spec/spec/{spec}/submit` | Submit spec → calls `SpecService::submit()` |
| `preview()` | `GET /spec/spec/{spec}/preview` | Read-only spec preview (post-submit) |
| `pricingStatus()` | `GET /spec/pricing` | List cases the spec user submitted; shows pricing status |

---

## Services to Implement

### `App\Services\SpecService`

```php
public function submit(TechOrderSpec $spec): PricingRequest
```

**`submit()` steps (inside `DB::transaction()`):**
1. Validate spec has at least one item.
2. Validate every `stock_item_code` in `TechOrderSpecItem` exists in `stock_items`. If any code is missing → throw `InvalidSpecItemException` with the offending code.
3. Generate `request_no` → `QT-PENDING-NNNN`.
4. `PricingRequest::create([...])` with `status_key = pending`, `step = 1`, `patient_type` copied from case.
5. Mirror each `TechOrderSpecItem` → `PricingRequestItem`.
6. Call `WorkflowService::advance($case, 'spec_saved')` → `stage_key = cost_calc`.
7. `AuditService::log('create', 'إرسال التوصيف للتسعير', 'spec', null, $pricingRequest)`.
8. Return `$pricingRequest`.

---

## Spec Role Data Isolation

The specialist **must never** see:
- `StockItemPrice.amount` — no purchase price.
- `StockItem.qty` or `StockItem.reserved` — no stock balance.
- `CaseRecord.quote_total`, `total_cost`, `paid` — no financial summary.

`TechOrderSpecItem` stores `stock_item_code` + `name` + `qty` only.

When rendering the spec form, the controller may pass `StockItem` records to populate a code-search dropdown — but only the fields `code`, `name`, `spec`, `category`, `uom` are passed. The `qty`, `reserved`, `status` fields must be excluded from the response.

---

## One Spec Per Case Rule

`tech_order_specs.case_id` is effectively 1:1 with `cases.id`. If a second submit is attempted for the same case → return an error. The spec can be edited as a draft but once submitted it is locked (same `locked` pattern as `MedicalRecord`).

---

## Acceptance Criteria

- [ ] Submitting a spec with an unknown `stock_item_code` → validation error naming the offending code.
- [ ] After submit: `PricingRequest` created, case moves to `cost_calc`.
- [ ] Spec user cannot see qty/price columns in the spec form.
- [ ] Attempting a second submit for the same case → error.
- [ ] Submission logged to `audit_logs`.
- [ ] The submitted spec appears read-only in `/spec/pricing` with the pricing request status.
