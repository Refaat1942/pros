@extends('layouts.app')

@section('title', config('dashboards.home.title'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/index.css') }}">
@endpush

@section('content')
    @include('partials.home-content')
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/pages/index.js') }}"></script>
@endpush
