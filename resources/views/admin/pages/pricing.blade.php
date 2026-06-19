@php
    use App\Enums\PricingRequestStatus;
    $requests = $pricing_requests ?? collect();
    $awaiting = $requests->filter(fn ($r) => $r->status_key === PricingRequestStatus::AwaitingAdminApproval)->count();
    $sent = $requests->filter(fn ($r) => $r->status_key === PricingRequestStatus::SentToReception)->count();
@endphp
<div class="section-view" id="section-pricing">
    <div id="analytics-pricing">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '⏳', 'label' => 'انتظار موافقة الأدمن', 'value' => (string) $awaiting, 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '✅', 'label' => 'جاهز لعرض السعر', 'value' => (string) $sent, 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '📋', 'label' => 'إجمالي الطلبات', 'value' => (string) $requests->count(), 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'قيمة معلقة', 'value' => number_format($requests->sum('computed_total'), 0), 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
    ]])</div>
    <div class="panel">
        <div class="panel-header">
            <h3>✅ اعتماد طلبات التسعير</h3>
            <span class="badge" id="pricingApprovalBadge">{{ $requests->count() }}</span>
        </div>
        <div class="data-toolbar">
            <input type="text" id="pricingApprovalSearch" placeholder="🔍 بحث برقم الطلب أو اسم المريض...">
            <select id="pricingApprovalFilter">
                <option value="awaiting_admin_approval">بانتظار الاعتماد</option>
                <option value="sent_to_reception">تم الإرسال للاستقبال</option>
                <option value="processing">جاري الاحتساب</option>
                <option value="insufficient">غير كافٍ</option>
                <option value="all">الكل</option>
            </select>
            <span class="toolbar-count" id="pricingApprovalCount">{{ $requests->count() }} طلب</span>
        </div>
        <div class="panel-body">
            <table data-paginate="10">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم الطلب</th>
                        <th>المريض</th>
                        <th>التاريخ</th>
                        <th>البنود</th>
                        <th>التقدير</th>
                        <th>الحالة</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="pricingApprovalTable" data-server-rendered="1">
                    @forelse ($requests as $pr)
                        @php
                            $status = $pr->status_key instanceof PricingRequestStatus
                                ? $pr->status_key
                                : PricingRequestStatus::from((string) $pr->status_key);
                        @endphp
                        <tr data-status="{{ $status->value }}"
                            data-search="{{ $pr->request_no }} {{ $pr->patient_name }} {{ $pr->order_ref }}">
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $pr->request_no }}</strong></td>
                            <td>{{ $pr->patient_name }}</td>
                            <td>{{ $pr->request_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $pr->items_count }}</td>
                            <td>{{ number_format((float) $pr->computed_total, 2) }}</td>
                            <td><span class="{{ $status->badgeClass() }}">{{ $status->label() }}</span></td>
                            <td>
                                @if ($status->isApprovable())
                                    <form method="POST" action="{{ route('admin.pricing.approve', $pr) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn-action approve">✅ اعتماد</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--text-muted);padding:24px;">
                                لا توجد طلبات تسعير معلقة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
