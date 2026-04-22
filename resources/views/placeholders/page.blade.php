@extends('layouts.app')

@section('title', $title . ' - Leave System')

@section('content')
    @include('partials.page-header', ['title' => $title, 'subtitle' => 'This page is queued for the next pure Laravel batch while preserving the same flow and rules from your original system.', 'actions' => ['<a href="'.route('dashboard').'" class="btn btn-secondary">Back to Dashboard</a>']])

    <div class="ui-card">
        <h3 style="margin-top:0;">{{ $title }}</h3>
        <p class="help-text" style="font-size:14px;">This route is already wired into Laravel and styled to match the rest of the converted system. Its full native Laravel functionality will be carried into the next batch without changing your existing flow.</p>
    </div>
@endsection
