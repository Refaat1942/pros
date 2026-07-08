@php
    $pathwayConfig = app(\App\Services\PathwayConfigService::class);
    $civilianSteps = $pathwayConfig->steps(\App\Models\PathwayStep::PATHWAY_CIVILIAN, activeOnly: true);
    $militarySteps = $pathwayConfig->steps(\App\Models\PathwayStep::PATHWAY_MILITARY, activeOnly: true);
@endphp
{{-- خط سير المسار المدني والعسكري — نافذة مرجعية بسيطة --}}
<div class="modal-overlay workflow-path-modal" id="workflowPathModal" hidden aria-hidden="true">
    <div class="workflow-path-dialog" role="dialog" aria-labelledby="workflowPathTitle">
        <div class="workflow-path-head">
            <h3 id="workflowPathTitle">🧭 خط سير المسار</h3>
            <button type="button" class="workflow-path-close" id="btnCloseWorkflowPath" aria-label="إغلاق">&times;</button>
        </div>
        <div class="workflow-path-body">
            <section class="workflow-path-section">
                <h4>🌐 المسار المدني (متعاقد / نقدي)</h4>
                <ol class="workflow-steps">
                    @foreach ($civilianSteps as $step)
                        <li>
                            <strong>{{ $step['label'] }}</strong>
                            @if (! empty($step['description']))
                                — {{ $step['description'] }}
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
            <section class="workflow-path-section">
                <h4>🪖 المسار العسكري</h4>
                <ol class="workflow-steps">
                    @foreach ($militarySteps as $step)
                        <li>
                            <strong>{{ $step['label'] }}</strong>
                            @if (! empty($step['description']))
                                — {{ $step['description'] }}
                            @endif
                        </li>
                    @endforeach
                </ol>
                <p class="workflow-path-note" style="margin-top:12px;"><em>التكلفة تُسجَّل صامتاً كمديونية سيادية</em></p>
            </section>
            <p class="workflow-path-note">أمر الشغل (WO) يصدر من <strong>مكتب التشغيل</strong> فقط — مو من الاستقبال.</p>
        </div>
    </div>
</div>

@once
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/workflow-path.css') }}?v={{ filemtime(public_path('assets/css/workflow-path.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('assets/js/shared/workflow-path.js') }}?v={{ filemtime(public_path('assets/js/shared/workflow-path.js')) }}"></script>
@endpush
@endonce
