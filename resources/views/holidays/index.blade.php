@extends('layouts.app')
@section('title', 'Manage Holidays - Leave System')

@section('content')
    @include('partials.page-header', [
        'title' => 'Manage Holidays',
        'subtitle' => 'Configure holiday dates used by the leave calendar',
        'actions' => ['<a href="'.route('calendar').'" class="btn btn-action-green" style="background:#10b981;border-color:#10b981;color:#fff;">Open Calendar</a>']
    ])

    <div class="ui-card holidays-card">
        <div class="fragment-toolbar holidays-toolbar">
            <form method="GET" action="{{ route('holidays') }}" class="holidays-search-form" id="holidayLiveSearchForm">
                <div class="search-input">
                    <input class="form-control" type="text" name="q" id="holidayLiveSearchInput" value="{{ $search }}" placeholder="Search date, description, or type..." autocomplete="off">
                </div>
                <button type="submit" class="btn btn-search-submit">Search</button>
                <a href="{{ route('holidays') }}" class="btn btn-ghost" id="holidayLiveSearchClear" style="{{ $search !== '' ? '' : 'display:none;' }}">Clear</a>
                <span class="live-search-status" id="holidayLiveSearchStatus" aria-live="polite"></span>
            </form>
            <div class="fragment-summary" id="holidayLiveSearchSummary">Showing {{ $holidays->firstItem() ?? 0 }}–{{ $holidays->lastItem() ?? 0 }} of {{ $holidays->total() }} holiday entries.</div>
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
            <div class="holidays-form-group">
                <label class="inline-check" style="font-weight:700;gap:8px;display:flex;align-items:center;margin-bottom:9px;">
                    <input type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring'))> Annually
                </label>
                <div class="help-text">Use this for holidays that repeat every year on the same month and day.</div>
            </div>
            <div class="holidays-create-actions">
                <button style="padding: 8px 12px; margin-bottom: 20px;" type="submit" class="btn btn-primary">Add Holiday</button>
            </div>
        </form>

        <div class="table-wrap" id="holidayLiveSearchResults" style="margin-top:24px;">
            <table class="ui-table">
                <thead>
                    <tr><th>Date</th><th>Description</th><th>Type</th><th>Annually</th><th>Action</th></tr>
                </thead>
                <tbody>
                @forelse($holidays as $holiday)
                    <tr>
                        <td>{{ optional($holiday->holiday_date)->format('Y-m-d') }}</td>
                        <td>{{ $holiday->description }}</td>
                        <td>{{ $holiday->type ?: 'Other' }}</td>
                        <td>{{ $holiday->is_recurring ? 'Yes' : 'No' }}</td>
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
                                    <label class="inline-check" style="gap:6px;white-space:nowrap;"><input type="checkbox" name="is_recurring" value="1" @checked($holiday->is_recurring)> Annually</label>
                                    <button style="padding: 8px 12px; margin-bottom: 12px;" type="submit" class="btn btn-secondary">Update</button>
                                </form>
                                <form method="POST" action="{{ route('holidays.destroy', $holiday) }}?{{ http_build_query(request()->only('q','page')) }}" class="holiday-delete-form" onsubmit="return confirm('Delete this holiday?');">
                                    @csrf
                                    @method('DELETE')
                                    <button style="padding: 8px 12px; margin-bottom: 12px;" type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="help-text">{{ $search !== '' ? 'No matching holidays found for this search.' : 'No holidays found.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div id="holidayLiveSearchPagination" style="margin-top:18px;">
            @if($holidays->hasPages())
                {{ $holidays->links('vendor.pagination.clean') }}
            @endif
        </div>
    </div>
@endsection

@push('head')
<style>
.holidays-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}.holidays-search-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.holidays-search-form .search-input{min-width:280px;flex:1}.holidays-create-form{display:grid;grid-template-columns:1fr 220px 1.2fr 180px auto;gap:14px;align-items:end}.holidays-form-group{display:flex;flex-direction:column;gap:8px}.holidays-form-group label{font-size:13px;font-weight:700;color:var(--text)}.holidays-create-actions{display:flex;justify-content:flex-start;gap:8px;margin-top:4px}.holiday-action-cell{padding:8px 0}.holiday-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.holiday-update-form{display:flex;gap:6px;align-items:center;flex:1}.holiday-update-form input[type="date"]{width:140px;padding:8px 10px}.holiday-update-form input[type="text"]{flex:1;min-width:180px;padding:8px 10px}.holiday-update-form select{width:170px;padding:8px 10px}.holiday-delete-form{flex-shrink:0}.live-search-status{font-size:12px;color:var(--muted);min-width:74px}.live-search-loading{opacity:.55;pointer-events:none;transition:opacity .15s ease}@media (max-width:1024px){.holidays-create-form{grid-template-columns:1fr 1fr}.holidays-create-actions{grid-column:1 / -1}}@media (max-width:720px){.holidays-create-form{grid-template-columns:1fr}.holiday-actions,.holiday-update-form{flex-direction:column;align-items:stretch}.holiday-update-form input,.holiday-update-form select{width:100% !important}}
</style>
@endpush

@push('scripts')
<script>
(function(){
    var searchForm = document.getElementById('holidayLiveSearchForm');
    var searchInput = document.getElementById('holidayLiveSearchInput');
    var clearBtn = document.getElementById('holidayLiveSearchClear');
    var statusText = document.getElementById('holidayLiveSearchStatus');
    var results = document.getElementById('holidayLiveSearchResults');
    var summary = document.getElementById('holidayLiveSearchSummary');
    var pagination = document.getElementById('holidayLiveSearchPagination');
    var searchTimer = null;
    var activeController = null;

    function setLoading(isLoading){
        results.classList.toggle('live-search-loading', isLoading);
        statusText.textContent = isLoading ? 'Searching...' : '';
    }

    function buildSearchUrl(pageUrl){
        var url = new URL(pageUrl || searchForm.action, window.location.origin);
        var query = searchInput.value.trim();
        if(query){ url.searchParams.set('q', query); } else { url.searchParams.delete('q'); }
        if(!pageUrl){ url.searchParams.delete('page'); }
        return url;
    }

    function fetchHolidays(pageUrl){
        if(activeController){ activeController.abort(); }
        activeController = new AbortController();
        var url = buildSearchUrl(pageUrl);
        setLoading(true);
        fetch(url.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}, signal: activeController.signal})
            .then(function(response){ return response.text(); })
            .then(function(html){
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newResults = doc.getElementById('holidayLiveSearchResults');
                var newSummary = doc.getElementById('holidayLiveSearchSummary');
                var newPagination = doc.getElementById('holidayLiveSearchPagination');
                if(newResults) results.innerHTML = newResults.innerHTML;
                if(newSummary) summary.innerHTML = newSummary.innerHTML;
                pagination.innerHTML = newPagination ? newPagination.innerHTML : '';
                clearBtn.style.display = searchInput.value.trim() ? '' : 'none';
                window.history.replaceState({}, '', url.pathname + url.search);
            })
            .catch(function(error){ if(error.name !== 'AbortError') statusText.textContent = 'Search failed.'; })
            .finally(function(){ setLoading(false); });
    }

    searchInput.addEventListener('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function(){ fetchHolidays(); }, 250);
    });
    searchForm.addEventListener('submit', function(e){ e.preventDefault(); fetchHolidays(); });
    clearBtn.addEventListener('click', function(e){ e.preventDefault(); searchInput.value = ''; fetchHolidays(); searchInput.focus(); });
    pagination.addEventListener('click', function(e){
        var link = e.target.closest('a');
        if(!link) return;
        e.preventDefault();
        fetchHolidays(link.href);
        searchInput.focus();
    });
})();
</script>
@endpush
