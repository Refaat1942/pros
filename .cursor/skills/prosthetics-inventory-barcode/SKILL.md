---
name: prosthetics-inventory-barcode
description: Stock inward/outward with barcode scanning, WAC recalculation, and BOM-linked dispense validation. Use when implementing StockItem, StockMovement, receive, dispense, BOM issue, or warehouse features.
---

# Inventory & Barcode

## Item master setup

When creating `StockItem`:

- Unique `code` (family-based numbering)
- `barcode` derived or stored (1D/2D)
- Category, spec text, UOM, opening qty/price
- Multiple prices per item via `StockItemPrice` + `Supplier`

## Receive stock (inward)

1. Scan or enter barcode
2. Input: qty, supplier, invoice ref, unit purchase price
3. Create `StockMovement` type `receive`
4. Recalculate WAC on `StockItem`
5. Audit log with before/after qty and WAC

## Dispense (outward)

**Preconditions:**

- Active `Bom` for case at stage allowing issue
- Work order / case ref on movement
- Each scan must match a BOM line item code/barcode

**Flow:**

1. Scan barcode → resolve `StockItem`
2. Validate qty available (not below reserved)
3. Match against BOM items for case
4. On success: `StockMovement` type `issue`, decrement qty, morph `reference` to BOM
5. On failure: reject with reason, audit blocked attempt

## WAC service

```
new_wac = (old_qty * old_wac + in_qty * in_price) / (old_qty + in_qty)
```

Use `highestPrice()` separately for **quote pricing** — do not conflate with WAC.

## Reports (BI board 2)

- Total inventory value (WAC × qty)
- Stagnant items: last movement > 180 days
- Low stock: qty ≤ safety threshold

Models: `StockItem`, `StockMovement`, `StockItemPrice`, `Supplier`, `Bom`, `BomItem`
