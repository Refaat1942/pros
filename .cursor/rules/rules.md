# Cursor Project Rules — prosthetics

You are a senior Laravel developer. Follow ALL rules below strictly on every task.

---

## Architecture

- Pattern: Controller → Service
- Controllers: thin — request/response only, no logic
- Services: all business logic here

- Use dependency injection everywhere
- Avoid tight coupling
- Code must be production-ready and scalable

---

## File Uploads

- Use `UploadTrait` for ALL file handling (images, videos, documents)
- Never handle upload logic inside controllers
- Must support: storage abstraction, path normalization, safe file naming

---

## Pagination

- Use `PaginationTrait` for ALL listing endpoints
- Never return full unpaginated datasets
- Standardize: page size, meta, links

---

## Validation

- Use `FormRequest` for all validation — extend `BaseRequest`
- Use `withValidator` for complex conditions
- If a query runs inside FormRequest, store result in request and reuse — do NOT re-query in Service

---

## Database

- Avoid N+1 — always use eager loading
- Use DB transactions in Service layer
- Use `lockForUpdate()` when needed
- Reuse queries across layers — never duplicate

---

## Guards

- Each guard is fully isolated (guard1, guard2, guard3)
- Separate route files, controllers, and translation files per guard
- Translation files: `lang/en/{guard}.php`, `lang/en/{guard}.php`
- Never mix guard logic

---

## Routing

```
routes/doctor.php
routes/admin.php
routes/home.php
routes/operations.php
routes/reception.php
routes/spec.php
routes/technical.php
```

## Enums & Constants

- Never use hardcoded values
- All constants must be Enum or Rule class from `app/Enums`
- Use enums in Services, Controllers, Validation

---

## Blade / Admin Panel

- Follow existing folder structure exactly — never invent new structure
- Never write CSS inside blade — styles go in `public/assets`
- Never write business logic inside blade
- Every section must support filtering via reusable FormRequest

---

## No Duplication Rules

- No duplicate queries across Controller / Service
- No duplicate logic across Blade / Controller / Service
- Traits are the single source of truth — never reimplement trait logic

---

## General

- Never hardcode anything
- Optimize queries — avoid unnecessary loops
- Use eager loading
- Update README when adding major features
- Code must be clean, readable, and scalable

---

## Domain rules (from client analysis)

Full spec: `docs/new_analysis_by_client.md`

Cursor rules (`.cursor/rules/*.mdc`):

| Rule | Scope |
|------|--------|
| `domain-core.mdc` | Always — ERP domain, civilian/military isolation |
| `patient-workflow.mdc` | Cases, patients, workflow services |
| `inventory-barcode.mdc` | Stock, BOM, barcode dispense |
| `pricing-financial.mdc` | Pricing, quotes, debts, WAC |
| `audit-immutable.mdc` | AuditLog append-only |
| `dashboard-blade.mdc` | Blade dashboards, one page per sidebar link |

Project skills (`.cursor/skills/prosthetics-*`):

- `prosthetics-patient-pathway` — civilian/military journeys
- `prosthetics-workflow-engine` — event-driven transitions
- `prosthetics-inventory-barcode` — receive/dispense/WAC
- `prosthetics-pricing-wac` — highest price vs WAC
- `prosthetics-bi-dashboards` — 5 BI boards KPIs
- `prosthetics-audit-log` — immutable audit implementation
