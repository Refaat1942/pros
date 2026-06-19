# Smart Prosthetics ERP — Implementation Plan
**Version:** 1.0 — June 2026  
**Reference source:** `docs/new_analysis.md`  
**Principle:** Tasks follow Business Flow order, not Dashboard order. Each task is a complete, self-contained unit that produces output consumed by the next task.

---

## Task Index

| # | File | Name | Dashboards |
|---|---|---|---|
| 01 | [task_01_foundation.md](task_01_foundation.md) | System Foundation: Auth + Routing + Audit | All |
| 02 | [task_02_master_data.md](task_02_master_data.md) | Master Data: Companies, Suppliers, Stock Catalog | Admin |
| 03 | [task_03_patient_registration.md](task_03_patient_registration.md) | Patient Registration & Appointment Scheduling | Reception |
| 04 | [task_04_medical_exam.md](task_04_medical_exam.md) | Medical Examination & Case Initiation | Doctor, Reception |
| 05 | [task_05_technical_spec.md](task_05_technical_spec.md) | Technical Specification | Spec |
| 06 | [task_06_pricing_engine.md](task_06_pricing_engine.md) | Pricing Engine (Civilian & Military) | Admin, Spec |
| 07 | [task_07_quote_approval.md](task_07_quote_approval.md) | Civilian Quote Issuance & Approval Gate | Reception |
| 08 | [task_08_stock_receiving.md](task_08_stock_receiving.md) | Stock Receiving & WAC Recalculation | Technical, Admin |
| 09 | [task_09_bom_manufacturing.md](task_09_bom_manufacturing.md) | BOM, Barcode Dispense & Manufacturing | Technical, Operations, Adjustments |
| 10 | [task_10_delivery_finance.md](task_10_delivery_finance.md) | Delivery, Financial Settlement & Credit Notes | Reception, Admin |
| 11 | [task_11_bi_reports.md](task_11_bi_reports.md) | BI Reporting & Admin Analytics | Admin |

---

## Execution Dependency Map

```
TASK 01 — Foundation (Auth + Audit)
│
└── TASK 02 — Master Data (Companies, Suppliers, Stock)
        │
        ├── TASK 08 — Stock Receiving / WAC  ← parallel, independent of cases
        │       └── feeds TASK 09 (qty available for dispense)
        │
        └── TASK 03 — Patient Registration & Appointments
                └── TASK 04 — Medical Exam & Case Initiation
                        └── TASK 05 — Technical Specification
                                └── TASK 06 — Pricing Engine
                                        │
                                        ├── [civilian] → TASK 07 — Quote & Approval Gate
                                        │                       └── TASK 09 — BOM & Manufacturing
                                        │
                                        └── [military] ────────────→ TASK 09 — BOM & Manufacturing
                                                                              └── TASK 10 — Delivery & Finance
                                                                                          └── TASK 11 — BI & Reports
```

---

## Cross-Cutting Rules (apply to every task)

| Rule | Enforced in |
|---|---|
| Every Service mutation wrapped in `DB::transaction()` | All Services |
| `AuditService::log()` called after every successful mutation | All Services |
| Price/cost fields never returned to `doctor`, `spec`, `technical` roles | Controllers + Blade views |
| `patient_type` always scoped on queries touching cases or finances | All queries — never mix civilian/military |
| BOM dispense requires `StockItem.qty - reserved >= requested_qty` | `BomService::releaseToWip()` |
| `AuditLog` — no `update()` or `delete()` ever called | `AuditService` + no route registered |
| WAC → inventory valuation only; `highestUnitPrice` → quotes only | `StockPriceService` (two separate methods) |

---

## Services Location Convention

```
app/
├── Services/
│   ├── AuditService.php          ← Task 01
│   ├── ContractDebtService.php   ← Task 02
│   ├── StockPriceService.php     ← Task 02 + 08
│   ├── PatientQrService.php      ← Task 03
│   ├── CaseService.php           ← Task 04
│   ├── WorkflowService.php       ← Task 04 (used by all)
│   ├── SpecService.php           ← Task 05
│   ├── PricingService.php        ← Task 06
│   ├── QuoteService.php          ← Task 06
│   ├── WorkOrderService.php      ← Task 07
│   ├── StockReceiveService.php   ← Task 08
│   ├── BomService.php            ← Task 09
│   ├── BarcodeValidationService.php ← Task 09
│   ├── ReturnNoteService.php     ← Task 09
│   ├── DeliveryService.php       ← Task 10
│   ├── FinancialPostingService.php ← Task 10
│   ├── CreditNoteService.php     ← Task 10
│   └── SmsNotificationService.php  ← Task 10
│
├── Http/Controllers/
│   ├── Auth/
│   ├── Dashboard/         ← already exists (static Blade only)
│   ├── Patient/
│   ├── Appointment/
│   ├── MedicalRecord/
│   ├── TechOrderSpec/
│   ├── Pricing/
│   ├── Quote/
│   ├── Stock/
│   ├── Bom/
│   ├── Delivery/
│   ├── Finance/
│   └── Reports/
```
