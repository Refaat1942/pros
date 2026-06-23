@php
    /** @var \Illuminate\Support\Collection $perm_roles */
    /** @var \Illuminate\Support\Collection $perm_permissions */ // grouped by group
    /** @var \Illuminate\Support\Collection $perm_matrix */      // [role_id => [slug,...]]
    $roles = $perm_roles ?? collect();
    $groups = $perm_permissions ?? collect();
    $matrix = $perm_matrix ?? collect();

    $groupLabels = [
        'financial' => '💰 المالية والتكاليف',
        'inventory' => '📦 المخزون',
        'printing'  => '🖨️ الطباعة',
        'clinical'  => '🩺 الإكلينيكي',
        'admin'     => '⚙️ الإدارة',
        'general'   => '🔹 عام',
    ];
@endphp
<div class="panel">
    <div class="panel-header">
        <h3>🛡️ مصفوفة الصلاحيات التفصيلية</h3>
        <span style="font-size:13px;color:var(--text-muted)">
            مسؤول النظام (الأدمن) يملك كل الصلاحيات تلقائياً ولا يظهر في الجدول.
        </span>
    </div>

    @if (session('status'))
        <div style="margin:12px 16px;padding:10px 14px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;font-size:13px;">
            ✅ {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.permissions.update') }}">
        @csrf
        <div class="panel-body" style="overflow-x:auto;">
            @forelse ($groups as $group => $permissions)
                <h4 style="margin:18px 0 8px;font-size:14px;font-weight:800;color:var(--text);">
                    {{ $groupLabels[$group] ?? $group }}
                </h4>
                <table class="data-table" style="width:100%;border-collapse:collapse;margin-bottom:8px;">
                    <thead>
                        <tr>
                            <th style="text-align:right;padding:10px;min-width:260px;">الصلاحية</th>
                            @foreach ($roles as $role)
                                <th style="padding:10px;text-align:center;white-space:nowrap;">{{ $role->label_ar }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $permission)
                            <tr style="border-top:1px solid var(--border);">
                                <td style="padding:10px;">
                                    <strong style="font-size:13px;">{{ $permission->label_ar }}</strong>
                                    <div style="font-size:11px;color:var(--text-muted);direction:ltr;text-align:right;">{{ $permission->slug }}</div>
                                </td>
                                @foreach ($roles as $role)
                                    @php $checked = in_array($permission->slug, (array) ($matrix[$role->id] ?? []), true); @endphp
                                    <td style="padding:10px;text-align:center;">
                                        <input type="checkbox"
                                               name="matrix[{{ $role->id }}][]"
                                               value="{{ $permission->id }}"
                                               style="width:18px;height:18px;cursor:pointer;"
                                               {{ $checked ? 'checked' : '' }}>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @empty
                <p style="text-align:center;color:var(--text-muted);padding:24px;">
                    لا توجد صلاحيات مُعرّفة. شغّل seeder الأدوار والصلاحيات.
                </p>
            @endforelse
        </div>

        <div class="catalog-modal-footer" style="padding:16px;border-top:1px solid var(--border);">
            <button type="submit" class="btn-action success">💾 حفظ مصفوفة الصلاحيات</button>
        </div>
    </form>
</div>
