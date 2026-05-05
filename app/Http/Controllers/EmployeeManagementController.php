<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentHeadAssignment;
use App\Models\Employee;
use App\Models\User;
use App\Support\BalanceLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeManagementController extends Controller
{
    private const ROLES = ['admin', 'employee', 'manager', 'hr', 'department_head', 'personnel'];

    private function authorizeRole(): void
    {
        abort_unless((string) Auth::user()->role === 'admin', 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeRole();

        $search = trim((string) $request->query('q', ''));
        $employees = Employee::query()
            ->with(['user', 'departmentRelation', 'manager'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereRaw("concat_ws(' ', first_name, middle_name, last_name) like ?", ['%' . $search . '%'])
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%' . $search . '%')->orWhere('role', 'like', '%' . $search . '%'))
                        ->orWhere('department', 'like', '%' . $search . '%')
                        ->orWhere('position', 'like', '%' . $search . '%')
                        ->orWhere('status', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(12)
            ->withQueryString();

        $departments = Department::query()->where('is_active', 1)->orderBy('name')->get();
        $managers = Employee::query()->with('user')
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['manager', 'department_head']))
            ->orderBy('first_name')->orderBy('last_name')->get();

        $editEmployee = null;
        if ($request->filled('edit')) {
            $editEmployee = Employee::query()->with('user')->find((int) $request->query('edit'));
        }

        return view('employees.index', [
            'employees' => $employees,
            'search' => $search,
            'departments' => $departments,
            'managers' => $managers,
            'roles' => self::ROLES,
            'editEmployee' => $editEmployee,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRole();
        $data = $this->validatedData($request, true);

        DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'is_active' => $data['is_active'],
                'can_approve_leave_requests' => $data['can_approve_leave_requests'],
                'activation_token' => null,
                'created_at' => now(),
            ]);

            $departmentName = $this->resolveDepartmentName($data['department_id']);
            $profilePic = $this->storeProfilePicture($request);

            $employee = Employee::query()->create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'department' => $departmentName,
                'department_id' => $data['department_id'],
                'manager_id' => $data['manager_id'],
                'position' => $data['position'],
                'salary' => $data['salary'],
                'status' => $data['status'],
                'civil_status' => $data['civil_status'],
                'entrance_to_duty' => $data['entrance_to_duty'],
                'unit' => $data['unit'],
                'gsis_policy_no' => $data['gsis_policy_no'],
                'national_reference_card_no' => $data['national_reference_card_no'],
                'annual_balance' => $data['annual_balance'],
                'sick_balance' => $data['sick_balance'],
                'force_balance' => $data['force_balance'],
                'wellness_balance' => $data['wellness_balance'],
                'spl_balance' => $data['spl_balance'],
                'cto_balance' => $data['cto_balance'],
                'cto_first_earned_at' => $data['cto_first_earned_at'],
                'profile_pic' => $profilePic,
            ]);

            $this->syncDepartmentHeadAssignment($employee, $data['role'], $data['department_id']);
        });

        return redirect()->route('manage-employees')->with('success', 'Employee created successfully.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeRole();
        $data = $this->validatedData($request, false, $employee);

        DB::transaction(function () use ($data, $request, $employee) {
            $oldAnnual = (float) $employee->annual_balance;
            $oldSick = (float) $employee->sick_balance;
            $oldForce = (float) $employee->force_balance;
            $oldWellness = (float) ($employee->wellness_balance ?? 0);
            $oldSpl = (float) ($employee->spl_balance ?? 0);
            $oldCto = (float) ($employee->cto_balance ?? 0);

            $user = $employee->user;
            if ($user) {
                $user->email = $data['email'];
                $user->role = $data['role'];
                $user->is_active = $data['is_active'];
                $user->can_approve_leave_requests = $data['can_approve_leave_requests'];
                if (!empty($data['password'])) {
                    $user->password = $data['password'];
                }
                $user->save();
            }

            $departmentName = $this->resolveDepartmentName($data['department_id']);
            $profilePic = $this->storeProfilePicture($request, $employee->profile_pic);

            $employee->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'department' => $departmentName,
                'department_id' => $data['department_id'],
                'manager_id' => $data['manager_id'],
                'position' => $data['position'],
                'salary' => $data['salary'],
                'status' => $data['status'],
                'civil_status' => $data['civil_status'],
                'entrance_to_duty' => $data['entrance_to_duty'],
                'unit' => $data['unit'],
                'gsis_policy_no' => $data['gsis_policy_no'],
                'national_reference_card_no' => $data['national_reference_card_no'],
                'annual_balance' => $data['annual_balance'],
                'sick_balance' => $data['sick_balance'],
                'force_balance' => $data['force_balance'],
                'wellness_balance' => $data['wellness_balance'],
                'spl_balance' => $data['spl_balance'],
                'cto_balance' => $data['cto_balance'],
                'cto_first_earned_at' => $data['cto_first_earned_at'],
                'profile_pic' => $profilePic,
            ]);

            if ($this->balancesDiffer($oldAnnual, (float) $data['annual_balance'])) {
                BalanceLedger::logBudgetChange($employee->id, 'Annual', $oldAnnual, (float) $data['annual_balance'], 'adjustment', null, 'Admin/personnel manual adjustment');
            }

            if ($this->balancesDiffer($oldSick, (float) $data['sick_balance'])) {
                BalanceLedger::logBudgetChange($employee->id, 'Sick', $oldSick, (float) $data['sick_balance'], 'adjustment', null, 'Admin/personnel manual adjustment');
            }

            if ($this->balancesDiffer($oldForce, (float) $data['force_balance'])) {
                BalanceLedger::logBudgetChange($employee->id, 'Force', $oldForce, (float) $data['force_balance'], 'adjustment', null, 'Admin/personnel manual adjustment');
            }

            if ($this->balancesDiffer($oldWellness, (float) $data['wellness_balance'])) {
                BalanceLedger::logBudgetChange($employee->id, 'Wellness', $oldWellness, (float) $data['wellness_balance'], 'adjustment', null, 'Admin/personnel manual adjustment');
            }

            if ($this->balancesDiffer($oldSpl, (float) $data['spl_balance'])) {
                BalanceLedger::logBudgetChange($employee->id, 'SPL', $oldSpl, (float) $data['spl_balance'], 'adjustment', null, 'Admin/personnel manual adjustment');
            }

            if ($this->balancesDiffer($oldCto, (float) $data['cto_balance'])) {
                BalanceLedger::logBudgetChange($employee->id, 'CTO', $oldCto, (float) $data['cto_balance'], 'adjustment', null, 'Admin/personnel manual adjustment');
            }

            $this->syncDepartmentHeadAssignment($employee->fresh(), $data['role'], $data['department_id']);
        });

        return redirect()->route('manage-employees', request()->only('q', 'page'))->with('success', 'Employee updated successfully.');
    }

    private function validatedData(Request $request, bool $isCreate, ?Employee $employee = null): array
    {
        $rules = [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'role' => ['required', Rule::in(self::ROLES)],
            'position' => ['nullable', 'string', 'max:128'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:64'],
            'civil_status' => ['nullable', 'string', 'max:64'],
            'entrance_to_duty' => ['nullable', 'date'],
            'unit' => ['nullable', 'string', 'max:128'],
            'gsis_policy_no' => ['nullable', 'string', 'max:128'],
            'national_reference_card_no' => ['nullable', 'string', 'max:128'],
            'annual_balance' => ['nullable', 'numeric'],
            'sick_balance' => ['nullable', 'numeric'],
            'force_balance' => ['nullable', 'numeric'],
            'wellness_balance' => ['nullable', 'numeric', 'min:0'],
            'spl_balance' => ['nullable', 'numeric', 'min:0'],
            'cto_balance' => ['nullable', 'numeric', 'min:0', 'max:15'],
            'cto_first_earned_at' => ['nullable', 'date'],
            'can_approve_leave_requests' => ['nullable', 'boolean'],
            'profile_pic' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($isCreate) {
            $rules['password'] = ['required', 'string', 'min:6'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:6'];
        }

        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['can_approve_leave_requests'] = $request->boolean('can_approve_leave_requests') || in_array((string) ($data['role'] ?? ''), ['admin', 'manager', 'department_head'], true);
        $data['department_id'] = $data['department_id'] ?: null;
        $data['manager_id'] = $data['manager_id'] ?: null;
        $data['middle_name'] = $this->nullableString($data['middle_name'] ?? null);
        $data['position'] = $this->nullableString($data['position'] ?? null);
        $data['status'] = $this->nullableString($data['status'] ?? null);
        $data['civil_status'] = $this->nullableString($data['civil_status'] ?? null);
        $data['unit'] = $this->nullableString($data['unit'] ?? null);
        $data['gsis_policy_no'] = $this->nullableString($data['gsis_policy_no'] ?? null);
        $data['national_reference_card_no'] = $this->nullableString($data['national_reference_card_no'] ?? null);
        $data['salary'] = $data['salary'] === null ? null : (float) $data['salary'];
        $data['annual_balance'] = isset($data['annual_balance']) ? round((float) $data['annual_balance'], 3) : 0.0;
        $data['sick_balance'] = isset($data['sick_balance']) ? round((float) $data['sick_balance'], 3) : 0.0;
        $data['force_balance'] = isset($data['force_balance']) ? round((float) $data['force_balance'], 3) : 5.0;
        $data['wellness_balance'] = isset($data['wellness_balance']) ? round((float) $data['wellness_balance'], 3) : 5.0;
        $data['spl_balance'] = isset($data['spl_balance']) ? round((float) $data['spl_balance'], 3) : 3.0;
        $data['cto_balance'] = min(15.0, isset($data['cto_balance']) ? round((float) $data['cto_balance'], 3) : 0.0);
        $data['cto_first_earned_at'] = $this->nullableString($data['cto_first_earned_at'] ?? null);
        return $data;
    }

    private function resolveDepartmentName(?int $departmentId): ?string
    {
        if (!$departmentId) {
            return null;
        }
        return Department::query()->where('id', $departmentId)->value('name');
    }

    private function syncDepartmentHeadAssignment(Employee $employee, string $role, ?int $departmentId): void
    {
        if ($role === 'department_head' && $departmentId) {
            DepartmentHeadAssignment::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                ['department_id' => $departmentId, 'is_active' => 1, 'assigned_at' => now()]
            );
            return;
        }

        DepartmentHeadAssignment::query()->where('employee_id', $employee->id)->delete();
    }

    private function storeProfilePicture(Request $request, ?string $existingPath = null): ?string
    {
        if (!$request->hasFile('profile_pic')) {
            return $existingPath;
        }

        $file = $request->file('profile_pic');
        if (!$file || !$file->isValid()) {
            return $existingPath;
        }

        $uploadDir = public_path('uploads');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = 'profile_' . Str::uuid()->toString() . '.' . $extension;
        $file->move($uploadDir, $filename);

        return 'uploads/' . $filename;
    }

    private function balancesDiffer(float $old, float $new): bool
    {
        return number_format($old, 3, '.', '') !== number_format($new, 3, '.', '');
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
