@extends('layouts.app')
@section('title', 'Manage Holidays - Leave System')

@section('content')
    @include('partials.page-header', [
        'title' => 'Manage Holidays',
        'subtitle' => 'Configure holiday dates used by the leave calendar',
        'actions' => ['<a href="'.route('calendar').'" class="btn btn-secondary">Open Calendar</a>']
    ])

    <div class="ui-card holidays-card">
        <div class="fragment-toolbar holidays-toolbar">
            <form method="GET" action="{{ route('holidays') }}" class="holidays-search-form">
                <div class="search-input">
                    <input class="form-control" type="text" name="q" value="{{ $search }}" placeholder="Search date, description, or type...">
                </div>
                <button type="submit" class="btn btn-secondary">Search</button>
                @if($search !== '')<a href="{{ route('holidays') }}" class="btn btn-ghost">Clear</a>@endif
            </form>
            <div class="fragment-summary">Showing {{ $holidays->firstItem() ?? 0 }}–{{ $holidays->lastItem() ?? 0 }} of {{ $holidays->total() }} holiday entries.</div>
        </div>

        <form method="POST" action="{{ route('holidays.store') }}" class="holidays-create-form">
            @csrf
            <div class="holidays-form-group">
                <label>Date</label>
                <input type="date" name="date" required class="form-control" value="{{ old('date') }}">
            </div>
            <div class="holidays-form-group">
                <label>Type</label>
                <select name="type" class="form-select">
                    @foreach(['Non-working Holiday','Special Working Holiday','Company Event','Other'] as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="holidays-form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}">
            </div>
            <div class="holidays-create-actions">
                <button type="submit" class="btn btn-primary">Add Holiday</button>
            </div>
        </form>

        <div class="table-wrap" style="margin-top:24px;">
            <table class="ui-table">
                <thead>
                    <tr><th>Date</th><th>Description</th><th>Type</th><th>Action</th></tr>
                </thead>
                <tbody>
                @forelse($holidays as $holiday)
                    <tr>
                        <td>{{ optional($holiday->holiday_date)->format('Y-m-d') }}</td>
                        <td>{{ $holiday->description }}</td>
                        <td>{{ $holiday->type ?: 'Other' }}</td>
                        <td class="holiday-action-cell">
                            <div class="holiday-actions">
                                <form method="POST" action="{{ route('holidays.update', $holiday) }}?{{ http_build_query(request()->only('q','page')) }}" class="holiday-update-form">
                                    @csrf
                                    @method('PUT')
                                    <input type="date" name="date" value="{{ optional($holiday->holiday_date)->format('Y-m-d') }}" required>
                                    <input type="text" name="description" value="{{ $holiday->description }}">
                                    <select name="type">
                                        @foreach(['Non-working Holiday','Special Working Holiday','Company Event','Other'] as $type)
                                            <option value="{{ $type }}" @selected(($holiday->type ?: 'Other') === $type)>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary">Update</button>
                                </form>
                                <form method="POST" action="{{ route('holidays.destroy', $holiday) }}?{{ http_build_query(request()->only('q','page')) }}" class="holiday-delete-form" onsubmit="return confirm('Delete this holiday?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="help-text">No holidays found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($holidays->hasPages())
            <div style="margin-top:18px;">{{ $holidays->links('vendor.pagination.clean') }}</div>
        @endif
    </div>
@endsection

@push('head')
<style>
.holidays-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}.holidays-search-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.holidays-search-form .search-input{min-width:280px;flex:1}.holidays-create-form{display:grid;grid-template-columns:1fr 220px 1.2fr auto;gap:14px;align-items:end}.holidays-form-group{display:flex;flex-direction:column;gap:8px}.holidays-form-group label{font-size:13px;font-weight:700;color:var(--text)}.holidays-create-actions{display:flex;justify-content:flex-start;gap:8px;margin-top:4px}.holiday-action-cell{padding:8px 0}.holiday-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.holiday-update-form{display:flex;gap:6px;align-items:center;flex:1}.holiday-update-form input[type="date"]{width:140px;padding:8px 10px}.holiday-update-form input[type="text"]{flex:1;min-width:180px;padding:8px 10px}.holiday-update-form select{width:170px;padding:8px 10px}.holiday-delete-form{flex-shrink:0}@media (max-width:1024px){.holidays-create-form{grid-template-columns:1fr 1fr}.holidays-create-actions{grid-column:1 / -1}}@media (max-width:720px){.holidays-create-form{grid-template-columns:1fr}.holiday-actions,.holiday-update-form{flex-direction:column;align-items:stretch}.holiday-update-form input,.holiday-update-form select{width:100% !important}}
</style>
@endpush
