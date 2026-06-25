@include('partials.admin-patient-track-panel', [
    'patient_tracks' => $patient_tracks ?? collect(),
    'track_search'   => $track_search ?? '',
])
