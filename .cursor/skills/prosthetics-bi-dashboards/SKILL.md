---
name: prosthetics-bi-dashboards
description: Five BI command boards and KPI definitions for the admin dashboard. Use when building admin reports, analytics controllers, BI Blade cards, or when the user mentions لوحات القيادة, KPI, SLA, or dashboard metrics.
---

# BI Command Boards (5)

Admin route: `/admin/bi`. Static Blade template: `partials/dashboard-bi-empty.blade.php`.

## Board 1 — Patient management

| Metric | Source |
|--------|--------|
| Total cases | `CaseRecord` count |
| Civilian / military split | group by `patient_type` |
| Avg turnaround | delivered cases: days quote→delivery |
| Open cases | not `delivered` |
| SLA breached | open + turnaround > SLA limit (21d default) |

## Board 2 — Inventory & supply chain

| Metric | Source |
|--------|--------|
| Total WAC value | Σ(qty × wac) |
| Item count | `StockItem` |
| Low stock count | status = low |
| Stagnant list | last movement > 180 days |

## Board 3 — Operations

| Metric | Source |
|--------|--------|
| Open work orders | manufacturing stage cases |
| Awaiting dispense | warehouse queue |
| In workshop | workshop/production stage |
| Ready for delivery | BOM finished + case ready |
| Est. hours per workshop | config/static labels until time tracking exists |

## Board 4 — Entities & costs

| Metric | Source |
|--------|--------|
| Civilian cumulative cost | sum `total_cost` civilian delivered/open |
| Military aggregated cost | sum military cases |
| Net contract debts | Σ(due - collected) on `ContractCompanyDebt` |

## Board 5 — Purchasing & suppliers

| Metric | Source |
|--------|--------|
| Approved supplier count | `Supplier` |
| WAC vs highest price table | top N items comparison |

## Implementation notes

- Serve from dedicated Report/Analytics **Services** — not JS ChartKit with fake arrays
- Use `dashboard-analytics-empty` partial until data wired
- Color coding: green ok, amber warning, red critical (match existing CSS vars)

Reference: `docs/new_analysis_by_client.md` §6
