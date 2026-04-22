@php
    $prefix = $mode === 'edit' ? 'edit_' : '';
@endphp
<input type="hidden" name="form_mode" value="{{ $mode }}">
<div class="employee-grid">
    <div class="field">
        <label for="{{ $prefix }}email">Email</label>
        <input class="form-control" type="email" id="{{ $prefix }}email" name="email" value="{{ old('form_mode') === $mode ? old('email') : '' }}" required>
    </div>
    <div class="field">
        <label for="{{ $prefix }}role">Role</label>
        <select class="form-select" id="{{ $prefix }}role" name="role" required>
            @foreach($roles as $role)
                <option value="{{ $role }}">{{ ucfirst(str_replace('_',' ', $role)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="{{ $prefix }}password">{{ $mode === 'edit' ? 'New Password (optional)' : 'Password' }}</label>
        <input class="form-control" type="password" id="{{ $prefix }}password" name="password" {{ $mode === 'create' ? 'required' : '' }} minlength="6">
    </div>

    <div class="field">
        <label for="{{ $prefix }}first_name">First Name</label>
        <input class="form-control" type="text" id="{{ $prefix }}first_name" name="first_name" value="{{ old('form_mode') === $mode ? old('first_name') : '' }}" required>
    </div>
    <div class="field">
        <label for="{{ $prefix }}middle_name">Middle Name</label>
        <input class="form-control" type="text" id="{{ $prefix }}middle_name" name="middle_name" value="{{ old('form_mode') === $mode ? old('middle_name') : '' }}">
    </div>
    <div class="field">
        <label for="{{ $prefix }}last_name">Last Name</label>
        <input class="form-control" type="text" id="{{ $prefix }}last_name" name="last_name" value="{{ old('form_mode') === $mode ? old('last_name') : '' }}" required>
    </div>

    <div class="field">
        <label for="{{ $prefix }}department_id">Department</label>
        <select class="form-select" id="{{ $prefix }}department_id" name="department_id">
            <option value="">Select Department</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="{{ $prefix }}manager_id">Assign Manager / Head</label>
        <select class="form-select" id="{{ $prefix }}manager_id" name="manager_id">
            <option value="">None</option>
            @foreach($managers as $manager)
                <option value="{{ $manager->id }}">{{ $manager->fullName() }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="{{ $prefix }}profile_pic">Profile Picture</label>
        <input class="form-control" type="file" id="{{ $prefix }}profile_pic" name="profile_pic" accept="image/*">
    </div>

    <div class="field">
        <label for="{{ $prefix }}position">Position</label>
        <input class="form-control" type="text" id="{{ $prefix }}position" name="position">
    </div>
    <div class="field">
        <label for="{{ $prefix }}salary">Salary</label>
        <input class="form-control" type="number" step="0.01" id="{{ $prefix }}salary" name="salary">
    </div>
    <div class="field">
        <label for="{{ $prefix }}status">Status</label>
        <input class="form-control" type="text" id="{{ $prefix }}status" name="status" placeholder="e.g. Active, Regular">
    </div>

    <div class="field">
        <label for="{{ $prefix }}civil_status">Civil Status</label>
        <input class="form-control" type="text" id="{{ $prefix }}civil_status" name="civil_status">
    </div>
    <div class="field">
        <label for="{{ $prefix }}entrance_to_duty">Entrance to Duty</label>
        <input class="form-control" type="date" id="{{ $prefix }}entrance_to_duty" name="entrance_to_duty">
    </div>
    <div class="field">
        <label for="{{ $prefix }}unit">Unit</label>
        <input class="form-control" type="text" id="{{ $prefix }}unit" name="unit">
    </div>

    <div class="field">
        <label for="{{ $prefix }}gsis_policy_no">GSIS Policy No.</label>
        <input class="form-control" type="text" id="{{ $prefix }}gsis_policy_no" name="gsis_policy_no">
    </div>
    <div class="field two">
        <label for="{{ $prefix }}national_reference_card_no">National Reference Card No.</label>
        <input class="form-control" type="text" id="{{ $prefix }}national_reference_card_no" name="national_reference_card_no">
    </div>

    <div class="field">
        <label for="{{ $prefix }}annual_balance">Vacational Balance</label>
        <input class="form-control" type="number" step="0.001" id="{{ $prefix }}annual_balance" name="annual_balance" value="{{ $mode === 'create' ? '0.000' : '' }}">
    </div>
    <div class="field">
        <label for="{{ $prefix }}sick_balance">Sick Balance</label>
        <input class="form-control" type="number" step="0.001" id="{{ $prefix }}sick_balance" name="sick_balance" value="{{ $mode === 'create' ? '0.000' : '' }}">
    </div>
    <div class="field">
        <label for="{{ $prefix }}force_balance">Force Balance</label>
        <input class="form-control" type="number" step="1" id="{{ $prefix }}force_balance" name="force_balance" value="{{ $mode === 'create' ? '5' : '' }}">
    </div>

    <div class="field full">
        <label class="inline-check" style="font-weight:600;">
            <input type="checkbox" id="{{ $prefix }}is_active" name="is_active" value="1" checked>
            Keep account active after save
        </label>
    </div>
</div>
