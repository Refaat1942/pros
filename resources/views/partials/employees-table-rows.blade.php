@php
    use App\Models\Role;
<<<<<<< HEAD
    $actorIsSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
=======
    $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
>>>>>>> origin/master
    $staffMode = $staff_mode ?? 'admin';
    $dashboardKey = $dashboard_key ?? 'admin';
@endphp
@foreach ($employees as $employee)
    @php
        $roleSlug = $employee->role?->slug ?? '';
        $isLimitedAdmin = $roleSlug === Role::SLUG_ADMIN;
        $isSuperAdminUser = $roleSlug === Role::SLUG_SUPER_ADMIN;
        $canManageLimitedAdmin = $actorIsSuperAdmin && $isLimitedAdmin;
        $bulkDeleteDisabled = auth()->id() === $employee->id
            || $isSuperAdminUser
            || ($isLimitedAdmin && ! $actorIsSuperAdmin);
        $bulkDeleteTitle = $isSuperAdminUser
            ? 'لا يمكن حذف السوبر أدمن'
            : ($isLimitedAdmin && ! $actorIsSuperAdmin
                ? 'لا يمكن حذف مسؤول النظام — السوبر أدمن فقط'
                : (auth()->id() === $employee->id ? 'لا يمكن حذف حسابك الحالي' : ''));
        $canDeleteEmployee = auth()->id() !== $employee->id
            && ! $isSuperAdminUser
            && (! $isLimitedAdmin || $actorIsSuperAdmin);
        $canToggleEmployee = ! $isSuperAdminUser
            && (! $isLimitedAdmin || $actorIsSuperAdmin);
    @endphp
    <tr data-role="{{ $roleSlug }}" data-status="{{ $employee->status }}" data-id="{{ $employee->id }}">
        @if ($show_bulk ?? true)
            @include('admin.partials.bulk-select-td', [
                'id' => $employee->id,
                'disabled' => $bulkDeleteDisabled,
                'disabledTitle' => $bulkDeleteTitle,
            ])
        @endif
        <td><strong>{{ $employee->name }}</strong></td>
        <td>{{ $employee->username }}</td>
        <td>
            <span class="role-badge {{ $roleSlug ?: 'unknown' }}">
                {{ $employee->role?->label_ar ?? '—' }}
            </span>
        </td>
        <td>
            <span class="status-dot {{ $employee->status }}">
                {{ $employee->status === \App\Models\User::STATUS_ACTIVE ? 'نشط' : 'غير نشط' }}
            </span>
        </td>
        <td>{{ $employee->last_login_at?->format('Y-m-d H:i') ?? '—' }}</td>
        <td>
            <div class="table-actions">
                @if ($staffMode === 'department')
                    <a href="{{ route("{$dashboardKey}.staff", ['edit' => $employee->id]) }}" class="btn-action" title="تعديل">✏️ تعديل</a>
                    <button type="button"
                            class="btn-action"
                            title="إعادة تعيين كلمة المرور"
                            onclick="resetEmployeePassword({{ $employee->id }}, {{ json_encode($employee->name) }})">
                        🔑 كلمة المرور
                    </button>
                    <form method="POST" action="{{ route("{$dashboardKey}.staff.toggle", $employee) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-action" title="تبديل الحالة">
                            {{ $employee->status === \App\Models\User::STATUS_ACTIVE ? 'تعطيل' : 'تفعيل' }}
                        </button>
                    </form>
                    <button type="button"
                            class="btn-action danger"
                            title="حذف الموظف"
                            onclick="deleteEmployee({{ $employee->id }}, {{ json_encode($employee->name) }})">
                        🗑️ حذف
                    </button>
                @else
                <a href="{{ route('admin.employees', ['edit' => $employee->id]) }}" class="btn-action" title="تعديل">✏️ تعديل</a>
<<<<<<< HEAD
                @if ($canToggleEmployee)
=======
                @if ($isSuperAdmin && auth()->id() !== $employee->id)
                    <button type="button"
                            class="btn-action"
                            title="إعادة تعيين كلمة المرور"
                            onclick="resetEmployeePassword({{ $employee->id }}, {{ json_encode($employee->name) }})">
                        🔑 كلمة المرور
                    </button>
                @endif
                @unless ($isAdminUser)
>>>>>>> origin/master
                    <form method="POST" action="{{ route('admin.employees.toggle', $employee) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-action" title="تبديل الحالة">
                            {{ $employee->status === \App\Models\User::STATUS_ACTIVE ? 'تعطيل' : 'تفعيل' }}
                        </button>
                    </form>
                @endif
                @if ($canDeleteEmployee)
                    <button type="button"
                            class="btn-action danger"
                            title="{{ $canManageLimitedAdmin ? 'حذف مسؤول النظام (سوبر أدمن)' : 'حذف الموظف' }}"
                            onclick="deleteEmployee({{ $employee->id }}, {{ json_encode($employee->name) }})">
                        🗑️ حذف
                    </button>
                @endif
                @endif
            </div>
        </td>
    </tr>
@endforeach
