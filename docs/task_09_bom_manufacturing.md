# Task 09 — BOM Creation, Barcode Dispense & Manufacturing Stages

**Depends on:**
- Task 07 (civilian: case must have `work_order_no`, `stage_key = manufacturing`)
- Task 06 (military: case at `manufacturing` directly after pricing)
- Task 08 (`StockItem.qty` must be up to date for dispense validation)

**Produces:** `Bom.stage = finished`; `CaseRecord.stage_key = ready_delivery`; `BomService::canDeliver(case)` returns true — gate for Task 10.

---

## Dependency Graph

**Depends On:**
- ✅ [Task 01 — Foundation](task_01_foundation.md) — auth session + `AuditService`
- ✅ [Task 06 — Pricing Engine](task_06_pricing_engine.md) — **military path**: case already at `manufacturing` after pricing approval
- ✅ [Task 07 — Quote Approval](task_07_quote_approval.md) — **civilian path only**: `work_order_no` must be set; case at `manufacturing`
- ✅ [Task 08 — Stock Receiving](task_08_stock_receiving.md) — `StockItem.qty` must be sufficient before `releaseToWip()`

> **Entry point differs by path:**
> - Civilian → enters Task 09 after Task 07 (approval scan)
> - Military → enters Task 09 directly after Task 06 (pricing approval)

**Used By:**
- ✅ [Task 10 — Delivery & Finance](task_10_delivery_finance.md) — `BomService::canDeliver()` must return true before delivery is allowed
- ✅ [Task 11 — BI Reports](task_11_bi_reports.md) — BI Board 3 reads BOM stages (raw/wip/finished) for operations counts

---

## Objective

The warehouse manager creates the BOM (raw stage) from the approved case, reserves the required quantities, then dispenses materials by scanning barcodes (raw → WIP). The Operations desk advances manufacturing sub-stages. When production finishes, the BOM is closed (finished) and the case gates to `ready_delivery`. The Adjustments desk records fitting trials during production. Return notes handle over-dispensed or rejected materials.

---

## Dashboards Involved

| Dashboard | Pages |
|---|---|
| Technical | `/technical/bom` — create BOM, barcode dispense, close |
| Technical | `/technical/returns` — create and complete return notes |
| Operations | `/operations/operations` — advance manufacturing sub-stages |
| Adjustments | `/adjustments/adjustments` — record fitting trials |

---

## Models Used

| Model | Table | Role |
|---|---|---|
| `CaseRecord` | `cases` | Must be at `manufacturing`; `manufacturing_stage` advanced here |
| `Bom` | `boms` | One BOM per case; stages: `raw → wip → finished` |
| `BomItem` | `bom_items` | One row per item; tracks `qty`, `issued_qty`, `returned_qty` |
| `StockItem` | `stock_items` | `qty` decremented on dispense; `reserved` managed here |
| `StockMovement` | `stock_movements` | `issue` movements (morph reference → Bom); `return` movements (morph reference → ReturnNote) |
| `ReturnNote` | `return_notes` | Header for material return authorization |
| `ReturnNoteLine` | `return_note_lines` | Line items of a return |
| `FittingTrial` | `fitting_trials` | 1:1 with case; records trial dates and notes |

---

## Controllers to Implement

### `App\Http\Controllers\Bom\BomController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /technical/bom` | List BOMs by stage (raw / wip / finished) |
| `create()` | `GET /technical/bom/create/{case}` | BOM creation form (prefills from PricingRequestItems) |
| `store()` | `POST /technical/bom` | Create BOM → calls `BomService::create()` |
| `scanDispense()` | `POST /technical/bom/{bom}/dispense` | Submit scanned barcodes → calls `BomService::releaseToWip()` |
| `closeFinished()` | `POST /technical/bom/{bom}/finish` | Close BOM as finished → calls `BomService::closeFinished()` |
| `show()` | `GET /technical/bom/{bom}` | BOM detail with item list, issued/returned quantities |

### `App\Http\Controllers\Bom\ReturnNoteController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /technical/returns` | List return notes by status |
| `create()` | `GET /technical/returns/create` | Select BOM + lines to return |
| `store()` | `POST /technical/returns` | Create return note → calls `ReturnNoteService::create()` |
| `complete()` | `POST /technical/returns/{note}/complete` | Submit scanned barcodes → calls `ReturnNoteService::complete()` |

### `App\Http\Controllers\Manufacturing\ManufacturingStageController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /operations/operations` | All cases at `manufacturing` stage with sub-stage |
| `advance()` | `POST /operations/operations/{case}/advance` | Advance `manufacturing_stage` → calls `BomService::advanceManufacturingStage()` |

### `App\Http\Controllers\FittingTrial\FittingTrialController`

| Method | Route | Description |
|---|---|---|
| `index()` | `GET /adjustments/adjustments` | Cases in fitting / manufacturing stage |
| `store()` | `POST /adjustments/adjustments` | Create or update fitting trial record |

---

## Services to Implement

### `App\Services\BomService`

```php
// Create BOM (stage = raw) and reserve quantities
public function create(CaseRecord $case, array $items): Bom

// Validate barcodes and release to WIP
public function releaseToWip(Bom $bom, array $scannedBarcodes): void

// Advance manufacturing sub-stage
public function advanceManufacturingStage(CaseRecord $case, string $newStage): void

// Close BOM as finished and advance case to ready_delivery
public function closeFinished(Bom $bom): void

// Gate check for delivery — called by DeliveryService (Task 10)
public function canDeliver(CaseRecord $case): bool
```

---

#### `create()` steps (inside `DB::transaction()`)

1. Validate `case->stage_key == manufacturing`.
2. Validate no existing BOM for this case (`unique case_id`).
3. Generate `bom_no` → `BOM-NNNN`.
4. `Bom::create([...])` with `stage = raw`.
5. For each item in `$items` (from `PricingRequestItem` or `TechOrderSpecItem`):
   - `BomItem::create([stock_item_code, name, qty, unit_cost = highestUnitPrice])`.
   - **Reserve:** `StockItem->increment('reserved', $item->qty)`.
6. `AuditService::log('create', 'إنشاء BOM', 'warehouse', null, $bom)`.

---

#### `releaseToWip()` steps (inside `DB::transaction()`)

1. Validate `bom->stage == raw`.
2. For each expected `BomItem`:
   - Call `BarcodeValidationService::validateScan($scannedBarcode, $bomItem)`.
   - If mismatch → throw `BarcodeDispenseMismatchException`; log blocked attempt via `AuditService`.
3. Validate `StockItem.qty - StockItem.reserved >= 0` for each item (available qty check).
4. For each matched item:
   - `StockMovement::create([type = issue, qty = -$qty, reference → Bom (morph)])`.
   - Decrement `StockItem.qty -= $qty`.
   - Decrement `StockItem.reserved -= $qty`.
   - Set `BomItem.issued_qty = $qty`.
   - Update `StockItem.last_moved_at`, recalculate `status`.
5. Set `Bom.stage = wip`, `Bom.released_at = now()`.
6. Call `WorkflowService` / `advanceManufacturingStage(case, 'issue')`.
7. `AuditService::log('dispense', 'صرف BOM بالباركود', 'warehouse', $before, $after)`.

---

#### `advanceManufacturingStage()` steps

Valid sequence: `warehouse → issue → generation → assembly → casting → finishing`

1. Validate `newStage` is the next allowed step.
2. `CaseRecord->update(['manufacturing_stage' => $newStage])`.
3. `AuditService::log('stage', 'تقدم مرحلة التصنيع', 'operations', $before, $after)`.

---

#### `closeFinished()` steps (inside `DB::transaction()`)

1. Validate `bom->stage == wip`.
2. Validate all `BomItem.issued_qty > 0` (all lines dispensed).
3. Set `Bom.stage = finished`, `Bom.finished_at = now()`.
4. Call `WorkflowService::advance($case, 'bom_finished')` → `stage_key = ready_delivery`.
5. Call `SmsNotificationService::notifyReady($case)`.
6. `AuditService::log('finish', 'إغلاق BOM — تام', 'warehouse', $before, $after)`.

---

#### `canDeliver()` logic

```php
return $case->stage_key === CaseRecord::STAGE_READY_DELIVERY
    && $case->bom?->stage === Bom::STAGE_FINISHED;
```

Returns `false` (not throws) if conditions not met. `DeliveryService` throws if false.

---

### `App\Services\BarcodeValidationService`

```php
public function validateScan(string $barcode, BomItem $bomItem): bool
```

1. Resolve `barcode` → `StockItem` via `StockItem::where('barcode', $barcode)->first()`.
2. Compare `StockItem.code` with `BomItem.stock_item_code`.
3. If match → return true.
4. If no match → `AuditService::log('blocked', 'مسح باركود خاطئ', 'warehouse', ...)` → return false.

---

### `App\Services\ReturnNoteService`

```php
public function create(Bom $bom, array $lines, string $reason): ReturnNote

public function complete(ReturnNote $note, array $scannedLines): void
```

**`complete()` steps (inside `DB::transaction()`):**
1. For each returned line:
   - `StockMovement::create([type = return, qty = +$returnedQty, reference → ReturnNote (morph)])`.
   - Increment `StockItem.qty += $returnedQty`.
   - Increment `BomItem.returned_qty += $returnedQty`.
2. Set `ReturnNote.status = completed`, `completed_at = now()`.
3. `AuditService::log('return', 'ارتجاع مواد', 'warehouse', $before, $after)`.

---

## BOM Life-Cycle Summary

```
raw  ──[releaseToWip + barcode scan]──▶  wip  ──[closeFinished]──▶  finished
 │                                                                       │
 └─ reserves qty on creation                                             └─ case → ready_delivery
```

> **Important distinction (from analysis §2):**  
> `Bom.stage = finished` = manufacturing complete.  
> `CaseRecord.stage_key = delivered` = physical delivery and financial close.  
> These are two separate transitions — one in this task, one in Task 10.

---

## Acceptance Criteria

- [ ] Creating a BOM reserves `StockItem.reserved` for all items.
- [ ] Scanning a wrong barcode → blocked with audit entry; no stock change.
- [ ] Dispensing reduces `qty` and `reserved` by the exact item quantity.
- [ ] `closeFinished()` on a BOM with any `issued_qty = 0` → error.
- [ ] After `closeFinished()`: case at `ready_delivery`, SMS triggered.
- [ ] `canDeliver()` returns false until both conditions met.
- [ ] Return note completion increments stock and updates `BomItem.returned_qty`.
- [ ] All actions logged to `audit_logs` with before/after qty.
