---
name: prosthetics-pricing-wac
description: Background pricing, Highest Purchase Price for quotes, WAC for inventory valuation, and contract debt posting. Use when implementing PricingRequest, Quote, CreditNote, ContractCompanyDebt, or financial calculations.
---

# Pricing & WAC

## Two price concepts (do not mix)

| Use case | Price source |
|----------|--------------|
| Inventory valuation, financial statements | **WAC** (weighted average) |
| Civilian quote / pricing request estimate | **Highest registered purchase price** per item |

## Pricing queue flow

1. Technician saves spec → `PricingRequest` with line items (code + qty)
2. Background job/service calculates:
   - For each item: `qty × StockItemPrice.max(unit_price)` or dedicated `highestPrice()` helper
   - Apply margin rule from config (not hardcoded in Blade)
3. Admin approves → status ready for reception quote
4. Reception issues `Quote` (1:1 with pricing request)

## Military silent pricing

Same calculation runs but:

- No quote document to patient
- Store `total_cost` on `CaseRecord` only
- Do not create receivable — accumulate for sovereign reporting

## Contract debts

- `ContractCompanyDebt`: due, collected, remaining per entity
- Civilian delivery increases due; payments reduce collected
- Military: aggregate costs to sovereign entity without payment workflow

## Credit notes

- Only for delivered civilian cases
- Partial or full rejection of billed amount
- Requires admin approval; audit every state change

## Purchasing analytics (BI board 5)

Per item row: WAC | highest purchase price | diff — flags margin erosion when diff > 0.

Reference: `docs/new_analysis_by_client.md` §5
