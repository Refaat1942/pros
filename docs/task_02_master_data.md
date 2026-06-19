# Task 02 — Master Data: Companies, Suppliers, Stock Catalog

**Depends on:** Task 01 (Auth + AuditService)  
**Produces:** `ContractCompany` rows (each with a matching `ContractCompanyDebt` ledger); `Supplier` rows; `StockItem` rows with opening balance and price batches; WAC computed and stored.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`

**Used By:**
- ✅ [Task 03 — Patient Registration](task_03_patient_registration.md) — needs `ContractCompany` rows for patient FK
- ✅ [Task 05 — Technical Spec](task_05_technical_spec.md) — validates `stock_item_code` against `stock_items`
- ✅ [Task 06 — Pricing Engine](task_06_pricing_engine.md) — reads `StockItemPrice` for `highestUnitPrice()`
- ✅ [Task 08 — Stock Receiving](task_08_stock_receiving.md) — needs `StockItem` + `Supplier` to exist
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — reads `StockItem.qty` / `reserved` for dispense validation
- ✅ [Task 10 — Delivery & Finance](task_10_delivery_finance.md) — needs `ContractCompanyDebt` row for civilian posting
- ✅ [Task 11 — BI Reports](task_11_bi_reports.md) — BI Board 2 (inventory value), Board 5 (WAC vs highest price)

---

## Objective

Seed and manage the reference data that the entire system reads but never creates on-the-fly during an active case flow. This data must exist before any patient registration, pricing, or dispense operation can function.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Admin | `/admin/companies`, `/admin/suppliers`, `/admin/catalog` |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `ContractCompany` | `contract_companies` | Civilian/military entity linked to patients and cases |
| `ContractCompanyDebt` | `contract_company_debts` | Running ledger (due / collected) — one row per company |
| `Supplier` | `suppliers` | Source of stock purchases |
| `StockItem` | `stock_items` | Item master: code, barcode, category, UOM, qty, WAC |
| `StockItemPrice` | `stock_item_prices` | Purchase price batches per item per supplier |

---

## Controllers to Implement

### `App\Http\Controllers\Stock\StockCatalogController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/catalog` | List all stock items (paginated, filterable by category/status) |
| `store()` | `POST /admin/catalog` | Create new item; validate unique `code` + unique `barcode` |
| `update()` | `PUT /admin/catalog/{item}` | Edit non-financial fields (name, spec, category, uom) |
| `addPrice()` | `POST /admin/catalog/{item}/prices` | Add a purchase price batch → calls `StockPriceService::addBatch()` |

### `App\Http\Controllers\Finance\ContractCompanyController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/companies` | List companies with debt summary |
| `store()` | `POST /admin/companies` | Create company → calls `ContractDebtService::initialise()` |
| `update()` | `PUT /admin/companies/{company}` | Edit name, is_military |

### `App\Http\Controllers\Stock\SupplierController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /admin/suppliers` | List suppliers |
| `store()` | `POST /admin/suppliers` | Create supplier |
| `update()` | `PUT /admin/suppliers/{supplier}` | Edit supplier details |
| `toggleActive()` | `PATCH /admin/suppliers/{supplier}/toggle` | Activate / deactivate |

---

## Services to Implement

### `App\Services\StockPriceService`

```php
// Add a new purchase price batch and recalculate WAC
public function addBatch(
    StockItem $item,
    int       $qty,
    float     $unitPrice,
    Supplier  $supplier,
    string    $invoiceNo,
    Carbon    $receivedAt,
): StockItemPrice

// Return highest unit price for a stock item (qty > 0 batches only)
// Used by PricingService — NOT for WAC
public function highestUnitPrice(string $stockItemCode): float

// Recalculate and persist WAC after a stock movement
// Formula: (old_qty * old_wac + in_qty * in_price) / (old_qty + in_qty)
public function recalcWac(StockItem $item, int $inQty, float $inPrice): void
```

**Notes:**
- `addBatch()` only stores the price batch (`StockItemPrice` row). It does NOT create a `StockMovement`. Physical receipt is handled by `StockReceiveService` in Task 08.
- `highestUnitPrice()` queries `MAX(amount) WHERE qty > 0` on `stock_item_prices` for the given item code. Returns 0 if no batches exist.
- `recalcWac()` reads current `qty` and derives old WAC from existing batches; updates `StockItem` in place.

### `App\Services\ContractDebtService`

```php
// Auto-called on company creation — creates the one ledger row
public function initialise(ContractCompany $company): ContractCompanyDebt

// Increase the amount due (called at delivery — Task 10)
public function increaseDue(ContractCompany $company, float $amount): void

// Record a payment (called from admin debts page — Task 10)
public function recordPayment(ContractCompany $company, float $amount): void
```

**Rule:** This is the **only** service that writes to `contract_company_debts`. No other service touches this table directly.

---

## Data Isolation Rule

`ContractCompany.is_military` flag determines whether the company is a sovereign entity (military) or a civilian insurer/fund. This flag must be set correctly at creation — it cannot be changed once cases are linked.

---

## Acceptance Criteria

- [ ] Creating a `ContractCompany` automatically creates one `ContractCompanyDebt` row with `due = 0`, `collected = 0`.
- [ ] Adding a price batch calls `recalcWac()` and updates `StockItem`.
- [ ] `highestUnitPrice()` ignores batches where `qty = 0` (sold-out lots).
- [ ] Admin `technical` (store) role can access catalog items but **cannot see** `StockItemPrice.amount`.
- [ ] Every create/update action logged to `audit_logs` via `AuditService`.
