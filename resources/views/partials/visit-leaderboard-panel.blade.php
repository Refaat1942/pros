@php
    $boards = $visit_leaderboards ?? [];
@endphp
@if (!empty($boards))
<div class="panel visit-leaderboard-panel" id="visitLeaderboardPanel">
    <div class="panel-header">
        <h3>📊 أكثر المرضى زيارة — حسب نوع الزيارة</h3>
        <span class="badge">{{ count($boards) }} نوع زيارة</span>
    </div>
    <div class="visit-leaderboard-grid">
        @foreach ($boards as $board)
            <div class="visit-leaderboard-card">
                <div class="visit-leaderboard-card__head">
                    <strong>{{ $board['visit_type'] }}</strong>
                    <span class="visit-leaderboard-total">{{ $board['total_visits'] }} زيارة</span>
                </div>
                <ol class="visit-leaderboard-list">
                    @foreach ($board['patients'] as $index => $patient)
                        <li>
                            <span class="visit-leaderboard-rank">{{ $index + 1 }}</span>
                            <span class="visit-leaderboard-name">{{ $patient['name'] }}</span>
                            <span class="patient-type-badge {{ $patient['patient_type'] === 'military' ? 'military' : 'civilian' }}">
                                {{ $patient['patient_type'] === 'military' ? '🪖' : '🌐' }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
</div>
@endif
