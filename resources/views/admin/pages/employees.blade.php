@php
    use App\Models\Role;
    use App\Models\User;
    $editUser = $edit_user ?? null;
    $openModal = $editUser || old('form') === 'employee';
    $isAdminEdit = in_array($editUser?->role?->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true);
    $assignableRoles = ($roles ?? collect())->filter(fn ($r) => $r->slug !== Role::SLUG_SUPER_ADMIN);
@endphp
<div class="panel">
    <div class="panel-header">
        <h3>👥 إدارة الموظفين</h3>
        <button type="button" class="btn-add-rank" id="btnAddEmployee">➕ إضافة موظف</button>
    </div>
    <div class="data-toolbar">
        @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'employeesBulkBar'])
        <input type="text" id="empSearch" placeholder="🔍 بحث بالاسم...">
        <select id="empRoleFilter">
            <option value="all">كل الأدوار</option>
            @foreach ($roles as $role)
                <option value="{{ $role->slug }}">{{ $role->label_ar }}</option>
            @endforeach
        </select>
        <select id="empStatusFilter">
            <option value="all">كل الحالات</option>
            <option value="active">نشط</option>
            <option value="inactive">غير نشط</option>
        </select>
        <span class="toolbar-count" id="empCount">{{ $employees->count() }} موظف</span>
    </div>
    <div class="panel-body">
        <table class="bulk-select-table" data-bulk-bar="employeesBulkBar" data-bulk-delete-base="/admin/employees" data-paginate="10">
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th>الاسم</th>
                    <th>اسم المستخدم</th>
                    <th>الدور</th>
                    <th>الحالة</th>
                    <th>آخر دخول</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="employeesTableFull" data-server-rendered="1">
                @include('partials.employees-table-rows', ['employees' => $employees])
            </tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay {{ $openModal ? 'open' : '' }}" id="employeeModal" role="dialog" aria-modal="true">
    <div class="catalog-modal employee-modal" style="max-width:760px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="employeeModalTitle">{{ $editUser ? '✏️ تعديل موظف' : '➕ إضافة موظف' }}</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="closeEmployeeModal" aria-label="إغلاق">&times;</button>
        </div>
        <form method="POST"
              id="employeeForm"
              data-validate-form
              data-store-url="{{ route('admin.employees.store') }}"
              data-catalog-url="{{ route('admin.employees.catalog-list-visibility') }}"
              data-edit-user-id="{{ $editUser?->id }}"
              data-add-title="➕ إضافة موظف"
              data-edit-title="✏️ تعديل موظف"
              action="{{ $editUser ? route('admin.employees.update', $editUser) : route('admin.employees.store') }}">
            @csrf
            @if ($editUser)
                @method('PUT')
            @endif
            <input type="hidden" name="form" value="employee">
            <div class="catalog-modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الاسم <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control"
                           data-v-rules="required,min:2,max:255" maxlength="255"
                           value="{{ old('name', $editUser?->name) }}"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">اسم المستخدم <span style="color:#dc2626">*</span></label>
                    <input type="text" name="username" class="form-control"
                           data-v-rules="required,username,max:50" maxlength="50"
                           value="{{ old('username', $editUser?->username) }}"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;"
                           dir="ltr">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">
                        كلمة المرور
                        <span id="employeePasswordRequired" style="color:#dc2626;{{ $editUser ? 'display:none;' : '' }}">*</span>
                        <small id="employeePasswordHint" style="font-weight:400;color:var(--text-muted);{{ $editUser ? '' : 'display:none;' }}">(اتركها فارغة للإبقاء)</small>
                    </label>
                    <input type="password" name="password" class="form-control"
                           data-v-rules="{{ $editUser ? 'password' : 'required,password' }}"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation" class="form-control"
                           data-v-rules="password"
                           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الدور <span style="color:#dc2626">*</span></label>
                    @if ($isAdminEdit)
                        <div id="employeeRoleLockedWrap">
                            <input type="hidden" name="role_id" value="{{ $editUser->role_id }}">
                            <div class="employee-role-locked">
                                <span class="role-badge admin">{{ $editUser->role->label_ar }}</span>
                                <p class="employee-role-locked-hint">دور مسؤول النظام ثابت — لا يمكن تغييره من هنا.</p>
                            </div>
                        </div>
                        <div id="employeeRoleSelectWrap" style="display:none;">
                            <select id="employeeRoleSelect" disabled data-v-rules="required,select"
                                    style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                                <option value="">— اختر الدور —</option>
                                @foreach ($assignableRoles as $role)
                                    <option value="{{ $role->id }}">{{ $role->label_ar }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div id="employeeRoleSelectWrap">
                            <select name="role_id" id="employeeRoleSelect" data-v-rules="required,select"
                                    style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                                <option value="">— اختر الدور —</option>
                                @foreach ($assignableRoles as $role)
                                    <option value="{{ $role->id }}"
                                        @selected((string) old('role_id', $editUser?->role_id) === (string) $role->id)>
                                        {{ $role->label_ar }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div id="employeeRoleLockedWrap" style="display:none;"></div>
                    @endif
                </div>
                @unless ($isAdminEdit)
                <div class="form-group employee-catalog-visibility-block" id="employeeCatalogVisibilityBlock" style="margin-bottom:14px;display:none;">
                    <label style="display:block;font-size:13px;font-weight:800;margin-bottom:8px;">
                        📋 قوائم الأصناف — ماذا يرى هذا الموظف؟
                    </label>
                    <p class="employee-catalog-visibility-hint">
                        اختر الدور أولاً، ثم فعّل القوائم والأعمدة قبل الحفظ. هذه الإعدادات خاصة بهذا الموظف —
                        مستقلة عن باقي نفس الدور.
                    </p>
                    <input type="hidden" name="catalog_list_visibility" id="employeeCatalogVisibilityInput" value="">
                    <div id="employeeCatalogVisibilityWrap" class="employee-catalog-visibility-wrap"></div>
                    <div id="employeeCatalogVisibilityLoading" class="employee-catalog-visibility-loading" style="display:none;">
                        جاري تحميل القوائم...
                    </div>
                </div>
                @endunless
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">الحالة</label>
                    @if ($isAdminEdit)
                        <input type="hidden" name="status" value="{{ User::STATUS_ACTIVE }}">
                        <div class="employee-role-locked">
                            <span class="status-dot active">نشط</span>
                            <p class="employee-role-locked-hint">حساب مسؤول النظام يبقى نشطاً دائماً.</p>
                        </div>
                    @else
                        <select name="status"
                                style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
                            <option value="active" @selected(old('status', $editUser?->status ?? 'active') === 'active')>نشط</option>
                            <option value="inactive" @selected(old('status', $editUser?->status) === 'inactive')>غير نشط</option>
                        </select>
                    @endif
                </div>
            </div>
            <div class="catalog-modal-footer">
                <button type="button" class="btn-action" id="cancelEmployeeModal">إلغاء</button>
                <button type="submit" class="btn-action success">💾 حفظ</button>
            </div>
        </form>
    </div>
</div>

<style>
    .employee-catalog-visibility-hint {
        margin: 0 0 10px;
        font-size: 12px;
        line-height: 1.55;
        color: var(--text-muted, #64748b);
    }
    .employee-catalog-visibility-wrap {
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        overflow: hidden;
        background: #fafbff;
        max-height: 320px;
        overflow-y: auto;
    }
    .employee-catalog-visibility-loading {
        padding: 12px;
        font-size: 12px;
        color: var(--text-muted, #64748b);
    }
    .employee-catalog-visibility-wrap .catalog-list-settings-section__head,
    .employee-catalog-visibility-wrap .catalog-list-settings-profile {
        padding: 10px 12px;
    }
    .employee-catalog-visibility-wrap .catalog-list-settings-columns {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 6px;
    }
    .employee-catalog-visibility-wrap .catalog-list-settings-col {
        padding: 6px 8px;
        font-size: 12px;
    }
</style>

@push('scripts')
    <script src="{{ asset('assets/js/pages/employee-catalog-visibility.js') }}?v={{ filemtime(public_path('assets/js/pages/employee-catalog-visibility.js')) }}"></script>
@endpush
