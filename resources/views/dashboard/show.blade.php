@extends('layouts.dashboard-page', [
    'dashboardKey' => $dashboardKey,
    'activePage' => $activePage,
    'pageTitle' => $pageTitle,
])

@section('page-content')
    @include("{$dashboardKey}.pages.{$activePage}")
@endsection
