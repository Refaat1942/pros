@foreach ($employees as $employee)
    <tr data-role="{{ $employee->role?->slug ?? '' }}" data-status="{{ $employee->status }}">
        <td><strong>{{ $employee->name }}</strong></td>
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
            <button type="button" class="btn-action" disabled title="قريباً">تعديل</button>
        </td>
    </tr>
@endforeach
