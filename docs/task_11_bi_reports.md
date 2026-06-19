# Task 11 — BI Reporting & Admin Analytics

**Depends on:** All previous tasks (BI queries real data from tasks 01–10).  
**Produces:** Nothing written to DB — read-only query layer. Terminal task.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — `AuditLog` rows written; auth for admin access
- ✅ [Task 02 — Master Data](task_02_master_data.md) — Board 2: `StockItem` WAC + qty; Board 5: `StockItemPrice`, `Supplier`
- ✅ [Task 03 — Patient Registration](task_03_patient_registration.md) — Board 1: `Patient` + `CaseRecord` counts
- ✅ [Task 04 — Medical Exam](task_04_medical_exam.md) — Board 1: open cases count
- ✅ [Task 06 — Pricing Engine](task_06_pricing_engine.md) — Board 4: `CaseRecord.total_cost` (military), `quote_total` (civilian)
- ✅ [Task 08 — Stock Receiving](task_08_stock_receiving.md) — Board 2: updated qty + WAC after receipts
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — Board 3: BOM stage counts (raw/wip/finished)
- ✅ [Task 10 — Delivery & Finance](task_10_delivery_finance.md) — Board 1: `delivered_at` for turnaround calc; Board 4: `ContractCompanyDebt` balances

**Used By:**
- *(none — terminal task)*

---

## Objective

Build the five BI command boards and the admin overview by querying real data from all previous tasks. No mock arrays, no JS seeds — all metrics served from dedicated Report Services, consumed by the existing static Blade partials (`partials/dashboard-bi-empty.blade.php`, `partials/dashboard-analytics-empty.blade.php`).

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Admin | `/admin/overview` — high-level KPIs + audit preview |
| Admin | `/admin/bi` — 5 BI command boards |
| Admin | `/admin/reports` — printable reports |
| Admin | `/admin/audit` — immutable audit log viewer |

---

## Models Used (read-only)

| Model | Used by board |
|---|---|
| `CaseRecord` | Board 1, 3, 4 |
| `Patient` | Board 1 |
| `StockItem` | Board 2 |
| `StockMovement` | Board 2 |
| `ContractCompanyDebt` | Board 4 |
| `ContractCompany` | Board 4 |
| `StockItemPrice` | Board 5 |
| `Supplier` | Board 5 |
| `AuditLog` | Audit page, Overview |

---

## Controllers to Implement

### `App\Http\Controllers\Reports\AdminOverviewController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/overview` | Summary KPIs + last 5 audit entries |

Passes to Blade:
```php
[
  'open_cases'          => CaseRecord count not delivered,
  'ready_for_delivery'  => CaseRecord count at ready_delivery,
  'sla_breached'        => BiReportService::boardPatients()['sla_breached'],
  'audit_preview'       => AuditLog::latest('logged_at')->take(5)->get(),
]
```

### `App\Http\Controllers\Reports\BiController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/bi` | Renders all 5 boards |

Passes to Blade:
```php
[
  'board1' => BiReportService::boardPatients(),
  'board2' => BiReportService::boardInventory(),
  'board3' => BiReportService::boardOperations(),
  'board4' => BiReportService::boardEntitiesAndCosts(),
  'board5' => BiReportService::boardPurchasing(),
]
```

### `App\Http\Controllers\Reports\AuditLogController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/audit` | Paginated log; filterable by action, tag, date |

**No `store`, `update`, or `destroy` methods — ever.**

---

## Services to Implement

### `App\Services\BiReportService`

#### `boardPatients()` → BI Board 1

```php
public function boardPatients(): array
```

Returns:
```php
[
  'total_cases'      => CaseRecord::count(),
  'civilian_count'   => CaseRecord::where('patient_type', 'civilian')->count(),
  'military_count'   => CaseRecord::where('patient_type', 'military')->count(),
  'open_count'       => CaseRecord::where('stage_key', '!=', 'delivered')->count(),
  'avg_turnaround'   => // AVG(DATEDIFF(delivered_at, quote_date)) on delivered civilian cases
  'sla_breached'     => // open cases where DATEDIFF(now(), quote_date) > SLA_DAYS (default 21)
                        // civilian only (military has no quote_date)
  'sla_days'         => config('erp.sla_days', 21),
]
```

#### `boardInventory()` → BI Board 2

```php
public function boardInventory(): array
```

Returns:
```php
[
  'total_value'    => // SUM(qty * wac) across all StockItems
  'item_count'     => StockItem::count(),
  'low_stock'      => StockItem::where('status', 'low')->count(),
  'stagnant_items' => StockItem::where('last_moved_at', '<', now()->subDays(180))->get(['code','name','qty']),
]
```

#### `boardOperations()` → BI Board 3

```php
public function boardOperations(): array
```

Returns:
```php
[
  'open_work_orders'    => CaseRecord::where('stage_key', 'manufacturing')->count(),
  'awaiting_dispense'   => // Cases with BOM stage = raw
  'in_workshop'         => // Cases with BOM stage = wip
  'ready_for_delivery'  => CaseRecord::where('stage_key', 'ready_delivery')->count(),
]
```

#### `boardEntitiesAndCosts()` → BI Board 4

```php
public function boardEntitiesAndCosts(): array
```

Returns:
```php
[
  'civilian_cumulative_cost'  => // SUM(quote_total) on civilian cases (delivered + open)
  'military_aggregated_cost'  => // SUM(total_cost) on military cases
  'net_debts'                 => // SUM(due - collected) from ContractCompanyDebt
  'company_debts'             => ContractCompanyDebt::with('contractCompany')->get()
                                   ->map(fn($d) => [...due, collected, remaining, status]),
]
```

#### `boardPurchasing()` → BI Board 5

```php
public function boardPurchasing(): array
```

Returns:
```php
[
  'supplier_count' => Supplier::where('is_active', true)->count(),
  'price_comparison' => // Per StockItem: code, name, wac, highest_purchase_price, diff (highest - wac)
                        // Flag items where diff > 0 (margin erosion risk)
                        // Top N items by diff descending
]
```

---

## SLA Configuration

Add to `config/erp.php` (new file):
```php
return [
    'sla_days' => 21,  // Days from quote_date for civilian open cases
];
```

---

## Audit Log Viewer Rules

- Paginated (20 per page).
- Filters: `tag`, `action`, `user_id`, `date_from`, `date_to`.
- No `payload_before` / `payload_after` shown in the list — only in detail view.
- **No delete route registered.**
- **No edit route registered.**
- Export to PDF/Excel is allowed (read-only dump).

---

## Overview Page Preview (last 5 audit entries)

```php
AuditLog::query()
    ->latest('logged_at')
    ->take(5)
    ->get(['user_name', 'action', 'description', 'tag', 'logged_at'])
```

No `payload_before` / `payload_after` on the overview page — security.

---

## Acceptance Criteria

- [ ] Board 1: total/civilian/military counts match `CaseRecord` table exactly.
- [ ] Board 1: `avg_turnaround` is null (not 0) when no delivered cases exist.
- [ ] Board 2: `total_value` = SUM(qty × wac) — verified against manual calculation.
- [ ] Board 2: stagnant list only includes items with no movement in 180+ days.
- [ ] Board 4: civilian and military costs are never mixed in the same sum.
- [ ] Board 5: `diff` flagged correctly; WAC and highestPrice sourced from correct fields.
- [ ] Audit log page has no `store/update/destroy` routes.
- [ ] Audit log renders even when `payload_before` / `payload_after` are null.
- [ ] All BI Blade partials replaced: no JS arrays, no hardcoded metrics, no `localStorage`.
