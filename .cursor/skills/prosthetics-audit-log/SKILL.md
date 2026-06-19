---
name: prosthetics-audit-log
description: Append-only immutable audit logging for all domain mutations. Use when creating or updating services that change patients, cases, stock, pricing, quotes, or user actions; when the user mentions سجل الرقابة, audit log, or immutable log.
---

# Audit Log Implementation

Model: `AuditLog` — **append-only**.

## When to log

Log on every successful:

- Create / update / delete of domain records
- Workflow stage transition
- Stock receive / issue (including blocked dispense)
- Quote print, approval scan, delivery scan
- Pricing approval / rejection
- Credit note create / approve / reject

Do **not** log: read-only list views (unless compliance requires), failed validation (except security blocks).

## Service helper pattern

```php
AuditLog::create([
    'user_id' => auth()->id(),
    'user_name' => auth()->user()->name,
    'action' => 'update',
    'description' => 'تعديل صنف مخزون',
    'tag' => 'warehouse',
    'ip_address' => request()->ip(),
    'payload_before' => $before,
    'payload_after' => $after,
    'logged_at' => now(),
]);
```

## Forbidden

- `$auditLog->update()` / `delete()` — ever
- Cascade delete on audit rows
- Storing passwords or full card data in payload

## Admin UI

- `/admin/audit` — filter by action, tag, date; export Excel/PDF
- Preview last 5 on overview — from real query, not seed array

## Migration constraint

No `updated_at` reliance for audit integrity; `logged_at` is the canonical timestamp.

Reference: `docs/new_analysis_by_client.md` §7
