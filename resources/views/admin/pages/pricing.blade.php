@php
    use App\Enums\PricingRequestStatus;
    $requests = $pricing_requests ?? collect();
    $awaiting = $requests->filter(fn ($r) => $r->status_key === PricingRequestStatus::AwaitingAdminApproval)->count();
@endphp
<div class="section-view" id="section-pricing">
    <div class="panel">
        <div class="panel-header">
            <h3>✅ اعتماد طلبات التسعير</h3>
            <span class="badge" id="pricingApprovalBadge">{{ $awaiting }} بانتظار</span>
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
            <span class="toolbar-count" id="pricingApprovalCount">{{ $awaiting }} طلب</span>
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
                        @php
                            $isMilitaryAutoApproved = $pr->patient_type === 'military'
                                && $status === PricingRequestStatus::SentToReception
                                && str_contains((string) ($pr->approved_by ?? ''), 'تلقائي');
                        @endphp
                        <tr data-status="{{ $status->value }}"
                            data-search="{{ $pr->request_no }} {{ $pr->patient_name }} {{ $pr->order_ref }}"
                            @if($isMilitaryAutoApproved) style="background:rgba(79,70,229,0.04);" @endif>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $pr->request_no }}</strong>
                                @if($isMilitaryAutoApproved)
                                    <span style="display:inline-block;margin-right:4px;padding:1px 6px;font-size:10px;font-weight:700;background:#ede9fe;color:#4f46e5;border-radius:4px;">🪖 تلقائي</span>
                                @endif
                            </td>
                            <td>{{ $pr->patient_name }}</td>
                            <td>{{ $pr->request_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $pr->items_count }}</td>
                            <td>
                                <span class="{{ $status->badgeClass() }}">{{ $status->label() }}</span>
                                @if($isMilitaryAutoApproved)
                                    <span style="display:block;font-size:10px;color:#4f46e5;margin-top:2px;">مسار عسكري — تجاوز الاعتماد</span>
                                @endif
                            </td>
                            <td>
                                <div class="approval-actions">
                                    <button type="button" class="btn-action" onclick="openPricingApprovalModal({{ $pr->id }})">عرض</button>
                                    @if ($status->isApprovable())
                                        <form method="POST" action="{{ route('admin.pricing.approve', $pr) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn-action approve">✅ اعتماد</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:24px;">
                                لا توجد طلبات تسعير معلقة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
