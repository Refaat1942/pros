# Task 08 — Stock Receiving & WAC Recalculation

**Depends on:** Task 02 (`StockItem` and `Supplier` must exist); Task 01 (AuditService)  
**Parallel to:** Tasks 03–07 (this task is operationally independent of any active case).  
**Produces:** Updated `StockItem.qty` + recalculated WAC; `StockMovement` rows (type `receive`); feeds Task 09 dispense validation.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`
- ✅ [Task 02 — Master Data](task_02_master_data.md) — `StockItem` and `Supplier` rows must exist

> **This task is parallel to Tasks 03–07.** It does not depend on any active case and can run at any time.

**Used By:**
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — `StockItem.qty` must be sufficient for dispense validation in `BomService::releaseToWip()`
- ✅ [Task 11 — BI Reports](task_11_bi_reports.md) — BI Board 2 reads updated `qty` + `wac` for total inventory value

---

## Objective

The warehouse manager (technical dashboard) receives purchased goods from suppliers. Each receipt creates a stock movement, increments the item balance, and recalculates WAC. This task runs independently of any case — it can happen at any time and must not be blocked by case flow state.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Technical | `/technical/inventory` — receive goods, view balances |
| Admin | `/admin/catalog` — view updated WAC + price batches |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `StockItem` | `stock_items` | Balance (qty) and WAC updated on each receipt |
| `StockItemPrice` | `stock_item_prices` | New price batch recorded; used for `highestUnitPrice` and WAC |
| `StockMovement` | `stock_movements` | Immutable receipt record (type = `receive`) |
| `Supplier` | `suppliers` | Linked to each receipt |

---

## Controllers to Implement

### `App\Http\Controllers\Stock\StockReceiveController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /technical/inventory` | Stock catalog: qty, WAC, status, last movement date |
| `receive()` | `POST /technical/inventory/receive` | Receive goods → calls `StockReceiveService::receive()` |
| `movements()` | `GET /technical/inventory/{item}/movements` | Movement history for a specific item |

---

## Services to Implement

### `App\Services\StockReceiveService`

```php
public function receive(
    StockItem $item,
    int       $qty,
    float     $unitPrice,
    Supplier  $supplier,
    string    $invoiceNo,
    Carbon    $movedAt,
    User      $performedBy,
): StockMovement
```

**Steps (inside `DB::transaction()`):**
1. Capture `$before = ['qty' => $item->qty, 'wac' => $item->wac]`.
2. Create `StockMovement`:
   - `movement_type = receive`
   - `quantity = +$qty` (positive)
   - `unit_cost = $unitPrice`
   - `supplier_id`, `invoice_no`, `moved_at`, `performed_by_user_id`
   - `balance_after = $item->qty + $qty`
   - `reference_type = null`, `reference_id = null` (receipts have no BOM reference)
3. Add `StockItemPrice` batch via `StockPriceService::addBatch(...)`.
4. Call `StockPriceService::recalcWac($item, $qty, $unitPrice)` → updates `StockItem.wac` (or derives from price batches if no dedicated column — see note below).
5. Increment `StockItem.qty += $qty`.
6. Update `StockItem.last_moved_at = $movedAt`.
7. Recalculate `StockItem.status`: if `qty <= LOW_QTY_THRESHOLD` → `low`; else `ok`.
8. Capture `$after = ['qty' => $item->qty, 'wac' => new_wac]`.
9. `AuditService::log('receive', 'استلام بضاعة: ' . $item->code, 'warehouse', $before, $after)`.
10. Return `StockMovement`.

---

## WAC Storage Decision

> **Implementation note:** The current `stock_items` migration does not have a dedicated `wac` column. Two options:
>
> **Option A (recommended):** Add a `wac` decimal column to `stock_items` via a new migration. `StockPriceService::recalcWac()` writes to it directly. Fast for reads and BI Board 2.
>
> **Option B:** Derive WAC on-the-fly from `stock_item_prices` aggregation. Slower for large catalogs but no migration needed.
>
> **Decision must be made before implementing Task 08.** This plan assumes Option A.

---

## WAC Formula

```
new_wac = (old_qty × old_wac + in_qty × in_price) / (old_qty + in_qty)
```

- If `old_qty = 0` → `new_wac = in_price` (first stock entry).
- WAC is stored with 2 decimal precision (`decimal(15,2)`).
- WAC is **never** used for quote pricing. Only `highestUnitPrice` is used for quotes (Task 06).

---

## Stock Status Thresholds

- `StockItem::LOW_QTY_THRESHOLD = 3` (defined as constant on the model).
- `status = 'ok'` when `qty > threshold`.
- `status = 'low'` when `qty <= threshold`.
- Status is recalculated after every receive and every dispense.

---

## Technical Role Data Isolation

The warehouse manager (`technical` role) can see:
- `StockItem.qty`, `StockItem.reserved`, `StockItem.status`
- `StockItem.code`, `name`, `spec`, `category`, `uom`, `barcode`

The warehouse manager **cannot see**:
- `StockItemPrice.amount` (purchase prices)
- `StockItem.wac` (financial valuation)
- Any `CaseRecord` financial fields

---

## Acceptance Criteria

- [ ] Receiving stock increments `StockItem.qty` exactly.
- [ ] WAC is recalculated correctly after each receipt (verify formula).
- [ ] Receiving with `old_qty = 0` sets WAC = `in_price`.
- [ ] `StockMovement` row is immutable (no update/delete route registered).
- [ ] `status` transitions from `ok` to `low` when qty drops to threshold.
- [ ] Technical role Blade views do not include price or WAC columns.
- [ ] All receipts logged to `audit_logs` with before/after qty and WAC.
