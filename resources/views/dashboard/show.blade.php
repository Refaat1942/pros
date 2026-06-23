@extends('layouts.dashboard-page', [
    'dashboardKey' => $dashboardKey,
    'activePage' => $activePage,
    'pageTitle' => $pageTitle,
])

@section('page-content')
    @if (($activePage ?? '') === 'notifications')
        @include('notifications.pages.inbox')
    @else
        @include("{$dashboardKey}.pages.{$activePage}")
    @endif
@endsection
