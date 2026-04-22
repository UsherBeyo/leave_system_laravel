<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentHeadAssignment;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentManagementController extends Controller
{
    private function authorizeRole(): void
    {
        abort_unless((string) Auth::user()->role === 'admin', 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeRole();

        $search = trim((string) $request->query('q', ''));
        $departments = Department::query()
            ->withCount([
                'employees as employees_count',
                'activeHeadAssignments as head_assignments_count',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('departments.index', compact('departments', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')],
        ]);

        Department::query()->create([
            'name' => trim((string) $data['name']),
            'is_active' => 1,
        ]);

        return redirect()->route('manage-departments')->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department->id)],
        ]);

        $oldName = (string) $department->name;
        $newName = trim((string) $data['name']);

        DB::transaction(function () use ($department, $oldName, $newName) {
            $department->update(['name' => $newName]);
            Employee::query()->where('department_id', $department->id)->update(['department' => $newName]);
            Employee::query()->whereNull('department_id')->where('department', $oldName)->update(['department' => $newName]);
        });

        return redirect()->route('manage-departments', request()->only('q', 'page'))->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorizeRole();

        $employeeCount = Employee::query()
            ->where('department_id', $department->id)
            ->orWhere(function ($query) use ($department) {
                $query->whereNull('department_id')->where('department', $department->name);
            })
            ->count();

        $headAssignmentCount = DepartmentHeadAssignment::query()->where('department_id', $department->id)->count();

        if ($employeeCount > 0 || $headAssignmentCount > 0) {
            return redirect()->route('manage-departments', request()->only('q', 'page'))
                ->with('error', 'Cannot delete department in use.');
        }

        $department->delete();

        return redirect()->route('manage-departments', request()->only('q', 'page'))->with('success', 'Department deleted.');
    }
}
