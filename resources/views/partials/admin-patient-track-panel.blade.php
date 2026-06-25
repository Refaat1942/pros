@php
    $tracks = collect($patient_tracks ?? []);
    $trackSearch = $track_search ?? '';
@endphp
<script>
window.__patientTracksById = @json($tracks->keyBy('id')->all());
</script>
<div class="panel patient-track-panel" id="patientTrackPanel">
    <div class="panel-header">
        <h3>📍 مسار المرضى — تتبع المراحل</h3>
        <div class="patient-track-actions">
            <span class="patient-track-count" id="patientTrackBadge">{{ $tracks->count() }} مريض نشط</span>
            <button type="button" class="patient-track-refresh" id="patientTrackRefresh" title="تحديث" aria-label="تحديث">↺</button>
        </div>
    </div>
    <div class="data-toolbar patient-track-toolbar">
        <input type="text"
               id="patientTrackSearch"
               value="{{ $trackSearch }}"
               placeholder="🔍 بحث بالاسم أو الهاتف أو الرقم القومي..."
               autocomplete="off">
        <span class="toolbar-count" id="patientTrackFilterCount">{{ $tracks->count() }} مريض</span>
    </div>
    <div class="panel-body patient-track-table-wrap">
        <table data-paginate="10" class="patient-track-table">
            <thead>
                <tr>
                    <th>المريض</th>
                    <th>النوع</th>
                    <th>التواصل</th>
                    <th>المرحلة الحالية</th>
                    <th class="col-actions">إجراء</th>
                </tr>
            </thead>
            <tbody id="patientTrackTableBody">
                @forelse ($tracks as $track)
                    <tr class="patient-track-row" data-search="{{ $track['search_hay'] ?? '' }}">
                        <td>
                            <strong>{{ $track['name'] }}</strong>
                            @if (! empty($track['case_no']))
                                <div class="patient-track-cell-sub">{{ $track['case_no'] }}</div>
                            @endif
                            @if (! empty($track['company_name']))
                                <div class="patient-track-cell-sub">{{ $track['company_name'] }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="patient-type-badge {{ $track['pathway'] === 'military' ? 'military' : 'civilian' }}">
                                {{ $track['pathway'] === 'military' ? '🪖 عسكري' : '🌐 مدني' }}
                            </span>
                        </td>
                        <td class="patient-track-contact">
                            @if (! empty($track['phone']))
                                <span dir="ltr">{{ $track['phone'] }}</span>
                            @endif
                            @if (! empty($track['national_id']))
                                <span dir="ltr">{{ $track['national_id'] }}</span>
                            @endif
                            @if (empty($track['phone']) && empty($track['national_id']))
                                —
                            @endif
                        </td>
                        <td>
                            <span class="patient-track-stage-inline">{{ $track['stage_label'] }}</span>
                            <span class="patient-track-percent-inline">{{ $track['progress_percent'] }}%</span>
                        </td>
                        <td class="col-actions">
                            <button type="button"
                                    class="btn-action primary btn-view-patient-track"
                                    data-track-id="{{ $track['id'] }}">
                                📍 عرض المسار
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="patient-track-empty">
                            ✅ لا يوجد مرضى نشطون في المسار حالياً — تظهر هنا الحالات غير المُسلَّمة ومواعيد الاستقبال والعيادة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="patientTrackModal" role="dialog" aria-modal="true" aria-labelledby="patientTrackModalTitle">
    <div class="catalog-modal patient-track-modal" onclick="event.stopPropagation()" style="max-width:720px;">
        <div class="catalog-modal-header">
            <div>
                <h3 id="patientTrackModalTitle">📍 مسار المريض</h3>
                <div class="modal-code" id="patientTrackModalMeta">—</div>
            </div>
            <button type="button" class="catalog-modal-close" id="closePatientTrackModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body patient-track-modal-body">
            <div class="patient-track-modal-head">
                <div class="patient-track-identity">
                    <strong id="patientTrackModalName">—</strong>
                    <span class="patient-type-badge" id="patientTrackModalBadge">—</span>
                </div>
                <span class="patient-track-percent" id="patientTrackModalPercent">0%</span>
            </div>
            <p class="patient-track-stage" id="patientTrackModalStage">—</p>
            <div class="patient-track-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div class="patient-track-bar-fill" id="patientTrackModalBar" style="width:0%"></div>
            </div>
            <div class="patient-track-steps" id="patientTrackModalSteps" aria-hidden="true"></div>
            <p class="patient-track-pathway-note" id="patientTrackModalPathNote"></p>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="btnClosePatientTrackModal">إغلاق</button>
        </div>
    </div>
</div>
