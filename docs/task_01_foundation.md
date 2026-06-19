# Task 01 — System Foundation: Auth + Routing + Audit

**Depends on:** Nothing — this is the root task.  
**Produces:** Authenticated session; `AuditService::log()` available everywhere; dashboard route protection.

---

## Dependency Graph

**Depends On:**
- *(none — root task)*

**Used By:**
- ✅ [Task 02 — Master Data](task_02_master_data.md) — needs `AuditService` + auth
- ✅ [Task 03 — Patient Registration](task_03_patient_registration.md) — needs auth session
- ✅ [Task 04 — Medical Exam](task_04_medical_exam.md) — needs `AuditService` + auth
- ✅ [Task 05 — Technical Spec](task_05_technical_spec.md) — needs `AuditService` + auth
- ✅ [Task 06 — Pricing Engine](task_06_pricing_engine.md) — needs `AuditService` + auth
- ✅ [Task 07 — Quote Approval](task_07_quote_approval.md) — needs `AuditService` + auth
- ✅ [Task 08 — Stock Receiving](task_08_stock_receiving.md) — needs `AuditService` + auth
- ✅ [Task 09 — BOM & Manufacturing](task_09_bom_manufacturing.md) — needs `AuditService` + auth
- ✅ [Task 10 — Delivery & Finance](task_10_delivery_finance.md) — needs `AuditService` + auth
- ✅ [Task 11 — BI Reports](task_11_bi_reports.md) — needs auth + audit viewer

---

## Objective

Every subsequent task depends on knowing *who* the user is, *which dashboard* they belong to, and on every domain action being silently logged. This task provides those three pillars before any business logic is written.

---

## Dashboards Involved

All 7 dashboards (infrastructure layer — transparent to each).

---

## Models Used

| Model | Table | Role in this task |
|---|---|---|
| `User` | `users` | Authentication subject |
| `Role` | `roles` | Determines which dashboard the user is routed to |
| `AuditLog` | `audit_logs` | Append-only log written by `AuditService` |

---

## Controllers to Implement

### `App\Http\Controllers\Auth\AuthController`

| Method | Route | Description |
|---|---|---|
| `showLogin()` | `GET /login` | Show login form |
| `login()` | `POST /login` | Authenticate; on success redirect to `/{role_slug}` |
| `logout()` | `POST /logout` | Invalidate session; redirect to `/login` |

**Login redirect logic:**
```
auth()->user()->role->slug  →  route("{slug}.dashboard")
```

---

## Services to Implement

### `App\Services\AuditService`

Single shared helper. Called at the end of every successful Service mutation.

```php
// Signature
public static function log(
    string $action,
    string $description,
    string $tag,
    mixed  $before = null,
    mixed  $after  = null,
): void
```

**Rules:**
- Reads `auth()->id()`, `auth()->user()->name`, `request()->ip()`, `request()->header('X-Mac-Address')` internally.
- Creates one `AuditLog` row via `AuditLog::create([...])`.
- **Never** calls `AuditLog::update()` or `AuditLog::delete()` — anywhere in the codebase.
- Uses `logged_at = now()` — not `created_at`.

---

## Middleware to Implement

### `App\Http\Middleware\AuditContextMiddleware`

- Runs on every authenticated request.
- Stores `ip_address` + `mac_address` in the request object so `AuditService` can read them without touching `request()` from inside a Service.

### `App\Http\Middleware\DashboardGuardMiddleware`

- Applied to every route group (`/admin/*`, `/reception/*`, `/doctor/*`, `/spec/*`, `/technical/*`, `/operations/*`, `/adjustments/*`).
- Reads `auth()->user()->role->slug`.
- If slug does not match the route prefix → `abort(403)`.

---

## Routes to Register

```php
// routes/web/auth.php  (new file)
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout')->middleware('auth');
```

Each existing dashboard route group gains two middlewares:
```php
->middleware(['auth', 'dashboard.guard'])
```

---

## What Must NOT Be Done Here

- No role/permission management UI.
- No password reset flow (out of scope for this ERP — admin resets manually via Tinker or a simple admin form added later if needed).
- No API tokens.

---

## Acceptance Criteria

- [ ] Unauthenticated request to `/admin/overview` → redirects to `/login`.
- [ ] Doctor user logging in → redirected to `/doctor/queue`, not `/admin`.
- [ ] Admin user logging in → redirected to `/admin/overview`.
- [ ] Any domain Service action → one row written to `audit_logs` with correct `user_id`, `ip_address`, `logged_at`.
- [ ] No `UPDATE` or `DELETE` SQL possible on `audit_logs` table through the application layer.
