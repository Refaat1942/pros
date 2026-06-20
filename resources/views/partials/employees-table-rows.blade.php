@php
    use App\Models\Role;
@endphp
@foreach ($employees as $employee)
    @php
        $isAdminUser = ($employee->role?->slug ?? '') === Role::SLUG_ADMIN;
    @endphp
    <tr data-role="{{ $employee->role?->slug ?? '' }}" data-status="{{ $employee->status }}" data-id="{{ $employee->id }}">
        @if ($show_bulk ?? true)
            @include('admin.partials.bulk-select-td', [
                'id' => $employee->id,
                'disabled' => auth()->id() === $employee->id || $isAdminUser,
                'disabledTitle' => $isAdminUser
                    ? 'لا يمكن حذف مسؤول النظام'
                    : 'لا يمكن حذف حسابك الحالي',
            ])
        @endif
        <td><strong>{{ $employee->name }}</strong></td>
        <td>{{ $employee->email }}</td>
        <td>
            <span class="role-badge {{ $employee->role?->slug ?? 'unknown' }}">
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
                <a href="{{ route('admin.employees', ['edit' => $employee->id]) }}" class="btn-action" title="تعديل">✏️ تعديل</a>
                @unless ($isAdminUser)
                    <form method="POST" action="{{ route('admin.employees.toggle', $employee) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-action" title="تبديل الحالة">
                            {{ $employee->status === \App\Models\User::STATUS_ACTIVE ? 'تعطيل' : 'تفعيل' }}
                        </button>
                    </form>
                @endunless
                @if (auth()->id() !== $employee->id && ! $isAdminUser)
                    <button type="button"
                            class="btn-action danger"
                            title="حذف الموظف"
                            onclick="deleteEmployee({{ $employee->id }}, {{ json_encode($employee->name) }})">
                        🗑️ حذف
                    </button>
                @endif
            </div>
        </td>
    </tr>
@endforeach
