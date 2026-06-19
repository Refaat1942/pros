<div id="analytics-employees">
    @include('partials.dashboard-analytics-empty', ['stats' => $employee_stats ?? []])
</div>
<div class="panel">
    <div class="panel-header">
        <h3>👥 إدارة الموظفين</h3>
        <span class="badge">{{ $employees->count() }} موظف</span>
    </div>
    <div class="data-toolbar">
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
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
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
