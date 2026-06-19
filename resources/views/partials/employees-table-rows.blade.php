@foreach ($employees as $employee)
    <tr data-role="{{ $employee->role?->slug ?? '' }}" data-status="{{ $employee->status }}">
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
        <td style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="{{ route('admin.employees', ['edit' => $employee->id]) }}" class="btn-action" title="تعديل">تعديل</a>
            <form method="POST" action="{{ route('admin.employees.toggle', $employee) }}" style="display:inline;">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn-action" title="تبديل الحالة">
                    {{ $employee->status === \App\Models\User::STATUS_ACTIVE ? 'تعطيل' : 'تفعيل' }}
                </button>
            </form>
        </td>
    </tr>
@endforeach
