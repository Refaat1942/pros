@extends('layouts.dashboard-page', [
    'dashboardKey' => $dashboardKey,
    'activePage' => $activePage,
    'pageTitle' => $pageTitle,
    'pageLabel' => $pageLabel ?? $pageTitle,
])

@section('page-content')
    @if (($activePage ?? '') === 'notifications')
        @include('notifications.pages.inbox')
    @elseif (($activePage ?? '') === 'document-template-edit')
        @include('admin.pages.document-template-edit')
    @else
        @include("{$dashboardKey}.pages.{$activePage}")
    @endif
@endsection
