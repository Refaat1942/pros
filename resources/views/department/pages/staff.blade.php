@php
    use App\Models\User;
    $editUser = $edit_user ?? null;
    $openModal = $editUser || old('form') === 'employee';
    $dashboardKey = $dashboard_key ?? 'reception';
    $role = $role ?? null;
    $staffBase = url("/{$dashboardKey}/staff");
@endphp

<div class="panel dept-staff-panel">
    <div class="panel-header">
        <h3>👥 موظفي القسم — {{ $role?->label_ar ?? '—' }}</h3>
        <button type="button" class="btn-add-rank" id="btnAddEmployee">➕ إضافة موظف</button>
    </div>
    <p class="employee-catalog-visibility-hint" style="padding:0 16px 12px;">
        أضف موظفين تحت إشرافك، حدّد الصفحات التي يرونها، غيّر كلمات المرور، وفعّل أو عطّل الحسابات.
    </p>
    <div class="data-toolbar">
        <input type="text" id="empSearch" placeholder="🔍 بحث بالاسم أو المستخدم...">
        <span class="toolbar-count" id="empCount">{{ $employees->count() }} موظف</span>
    </div>
    <div class="panel-body">
        <div class="bom-table-wrap">
            <table class="bom-table" data-paginate="10">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="employeesTableFull" data-server-rendered="1">
                    @include('partials.employees-table-rows', [
                        'employees' => $employees,
                        'staff_mode' => 'department',
                        'dashboard_key' => $dashboardKey,
                        'show_bulk' => false,
                    ])
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay {{ $openModal ? 'open' : '' }}" id="employeeModal" role="dialog" aria-modal="true">
    <div class="catalog-modal employee-modal" onclick="event.stopPropagation()">
        <form method="POST"
              id="employeeForm"
              class="employee-modal-form"
              data-validate-form
              data-department-mode="1"
              data-store-url="{{ route("{$dashboardKey}.staff.store") }}"
              data-catalog-url="{{ route("{$dashboardKey}.staff.catalog-list-visibility") }}"
              data-role-pages-url="{{ url("/{$dashboardKey}/staff/role-pages") }}"
              data-fixed-role-id="{{ $role?->id }}"
              data-edit-allowed-pages='@json($editUser?->allowed_pages ?? [])'
              data-edit-user-id="{{ $editUser?->id }}"
              data-add-title="➕ إضافة موظف"
              data-edit-title="✏️ تعديل موظف"
              action="{{ $editUser ? route("{$dashboardKey}.staff.update", $editUser) : route("{$dashboardKey}.staff.store") }}">
            @csrf
            @if ($editUser)
                @method('PUT')
            @endif
            <input type="hidden" name="form" value="employee">
            <input type="hidden" name="role_id" value="{{ $role?->id }}">
            <input type="hidden" name="access_tier" value="department_staff">
            <input type="hidden" id="employeeRoleSelect" value="{{ $role?->id }}">

            <div class="catalog-modal-header">
                <div>
                    <h3 id="employeeModalTitle">{{ $editUser ? '✏️ تعديل موظف' : '➕ إضافة موظف' }}</h3>
                </div>
                <button type="button" class="catalog-modal-close" id="closeEmployeeModal" aria-label="إغلاق">&times;</button>
            </div>

            <div class="catalog-modal-body employee-modal-body">
                <div class="employee-modal-grid">
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الاسم <span style="color:#dc2626">*</span></label>
                        <input type="text" name="name" class="form-control"
                               data-v-rules="required,min:2,max:255" maxlength="255"
                               value="{{ old('name', $editUser?->name) }}"
                               style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم المستخدم <span style="color:#dc2626">*</span></label>
                        <input type="text" name="username" class="form-control"
                               data-v-rules="required,username,max:50" maxlength="50"
                               value="{{ old('username', $editUser?->username) }}"
                               style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                        <small style="display:block;margin-top:4px;font-size:11px;color:var(--text-muted);">عربي أو إنجليزي — أرقام و _ و - مسموح</small>
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">
                            كلمة المرور
                            <span id="employeePasswordRequired" style="color:#dc2626;{{ $editUser ? 'display:none;' : '' }}">*</span>
                            <small id="employeePasswordHint" style="font-weight:400;color:var(--text-muted);{{ $editUser ? '' : 'display:none;' }}">(اتركها فارغة للإبقاء)</small>
                        </label>
                        <input type="password" name="password" class="form-control"
                               data-v-rules="{{ $editUser ? 'password' : 'required,password' }}"
                               style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">تأكيد كلمة المرور</label>
                        <input type="password" name="password_confirmation" class="form-control"
                               data-v-rules="password"
                               style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الحالة</label>
                        <select name="status"
                                style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                            <option value="active" @selected(old('status', $editUser?->status ?? 'active') === 'active')>نشط</option>
                            <option value="inactive" @selected(old('status', $editUser?->status) === 'inactive')>غير نشط</option>
                        </select>
                    </div>
                    <div class="form-group form-group-full employee-access-tier-block" id="employeeAccessTierBlock">
                        <label style="display:block;font-size:13px;font-weight:800;margin-bottom:8px;">📋 الصفحات التي يرى هذا الموظف</label>
                        <p class="employee-catalog-visibility-hint">فعّل الصفحات المسموح بها قبل الحفظ.</p>
                        <input type="hidden" name="allowed_pages" id="employeeAllowedPagesInput" value="">
                        <div id="employeeAllowedPagesWrap" class="employee-catalog-visibility-wrap"></div>
                    </div>
                    <div class="form-group form-group-full employee-catalog-visibility-block" id="employeeCatalogVisibilityBlock">
                        <label style="display:block;font-size:13px;font-weight:800;margin-bottom:8px;">
                            📋 قوائم الأصناف — ماذا يرى هذا الموظف؟
                        </label>
                        <p class="employee-catalog-visibility-hint">
                            فعّل القوائم والأعمدة قبل الحفظ — خاصة بهذا الموظف.
                        </p>
                        <input type="hidden" name="catalog_list_visibility" id="employeeCatalogVisibilityInput" value="">
                        <div id="employeeCatalogVisibilityWrap" class="employee-catalog-visibility-wrap"></div>
                        <div id="employeeCatalogVisibilityLoading" class="employee-catalog-visibility-loading" style="display:none;">
                            جاري تحميل القوائم...
                        </div>
                    </div>
                </div>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelEmployeeModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ</button>
            </div>
        </form>
    </div>
</div>

<div class="catalog-modal-overlay" id="employeePasswordResetModal" role="dialog" aria-modal="true">
    <div class="catalog-modal employee-modal" style="width:min(480px,92vw);" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="employeePasswordResetTitle">🔑 إعادة تعيين كلمة المرور</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeEmployeePasswordResetModal" aria-label="إغلاق">&times;</button>
        </div>
        <form id="employeePasswordResetForm" data-validate-form>
            <div class="catalog-modal-body" style="padding:24px 32px;">
                <p id="employeePasswordResetHint" style="margin:0 0 16px;font-size:13px;color:var(--text-muted);"></p>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">كلمة المرور الجديدة <span style="color:#dc2626">*</span></label>
                    <input type="password" name="password" class="form-control" data-v-rules="required,password"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">تأكيد كلمة المرور <span style="color:#dc2626">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" data-v-rules="required,password"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelEmployeePasswordResetModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ كلمة المرور</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.__DEPT_STAFF = @json([
        'dashboard' => $dashboardKey,
        'baseUrl' => $staffBase,
        'staffRoute' => route("{$dashboardKey}.staff"),
    ]);
</script>
