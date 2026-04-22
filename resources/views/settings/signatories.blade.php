@extends('layouts.app')
@section('title', 'Signatories Settings - Leave System')

@section('content')
    @include('partials.page-header', ['title' => 'Signatories Settings', 'subtitle' => 'Set the names and positions used in official forms', 'actions' => []])

    <div class="ui-card">
        <p class="help-text" style="margin:0 0 16px;">These default signatories will auto-fill the print form modal for Personnel, HR, and Admin.</p>
        <form method="POST" action="{{ route('signatories-settings.update') }}">
            @csrf
            <div class="table-wrap">
                <table class="ui-table">
                    <thead>
                    <tr>
                        <th>Section</th>
                        <th>Name</th>
                        <th>Position</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $labels = [
                            'certification' => '7.A Certification of Leave Credits',
                            'final_approver' => '7.C Final Approver',
                        ];
                    @endphp
                    @foreach($rows as $row)
                        <tr>
                            <td>
                                {{ $labels[$row->key_name] ?? $row->key_name }}
                                <input type="hidden" name="id[]" value="{{ $row->id }}">
                            </td>
                            <td><input class="form-control" type="text" name="name[]" value="{{ old('name.'.$loop->index, $row->name) }}" required></td>
                            <td><input class="form-control" type="text" name="position[]" value="{{ old('position.'.$loop->index, $row->position) }}" required></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:18px;"><button type="submit" class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>
@endsection
