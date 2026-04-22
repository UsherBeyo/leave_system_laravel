@extends('layouts.app')
@section('title', 'Change Password - Leave System')

@section('content')
@include('partials.page-header', [
    'title' => 'Change Password',
    'subtitle' => 'Update your login credentials securely.',
])

<div class="ui-card" style="max-width:760px;">
    <form method="POST" action="{{ route('change-password.update') }}" class="form-grid" style="grid-template-columns:1fr;max-width:520px;">
        @csrf
        <div class="field">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm New Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6" autocomplete="new-password">
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
    </form>
</div>
@endsection
