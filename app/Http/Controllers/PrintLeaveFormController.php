<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestForm;
use App\Models\SystemSignatory;
use App\Services\LeavePolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PrintLeaveFormController extends Controller
{
    public function __construct(private LeavePolicyService $policyService)
    {
    }

    public function show(LeaveRequest $leave): View
    {
        $leave->load(['employee.user', 'leaveTypeRelation', 'attachments', 'form']);
        $user = Auth::user();
        $employee = $leave->employee;

        $allowed = in_array((string) $user->role, ['admin', 'hr', 'personnel', 'department_head', 'manager'], true)
            || ($employee && $user->employee && $user->employee->id === $employee->id);
        abort_unless($allowed, 403);
        abort_unless(strtolower((string) $leave->status) === 'approved' || strtolower((string) $leave->workflow_status) === 'finalized', 404);

        $form = $leave->form ?: LeaveRequestForm::query()->firstWhere('leave_request_id', $leave->id);
        $signatories = SystemSignatory::query()->get()->keyBy('key_name');
        $details = json_decode((string) ($leave->details_json ?: '{}'), true);
        $details = is_array($details) ? $details : [];
        $selectedDocs = json_decode((string) ($leave->supporting_documents_json ?: '[]'), true);
        $selectedDocs = is_array($selectedDocs) ? $selectedDocs : [];
        $policy = $this->policyService->policyFromLeaveType($leave->leaveTypeRelation ?: $leave->leave_type_name);
        $uiPreset = $policy['preset'] ?? [];

        $deptHeadEmployee = null;
        if ($leave->department_head_user_id) {
            $deptHeadEmployee = Employee::query()->firstWhere('user_id', $leave->department_head_user_id);
        }

        return view('print.leave-form', [
            'leave' => $leave,
            'employee' => $employee,
            'form' => $form,
            'details' => $details,
            'selectedDocs' => $selectedDocs,
            'uiPreset' => $uiPreset,
            'signatories' => $signatories,
            'departmentHeadEmployee' => $deptHeadEmployee,
        ]);
    }

    public function saveSignatories(Request $request, LeaveRequest $leave): RedirectResponse
    {
        abort_unless(in_array((string) Auth::user()->role, ['personnel', 'hr', 'admin'], true), 403);
        $data = $request->validate([
            'name_a' => ['required', 'string'],
            'position_a' => ['required', 'string'],
            'name_c' => ['required', 'string'],
            'position_c' => ['required', 'string'],
        ]);

        LeaveRequestForm::query()->updateOrCreate(
            ['leave_request_id' => $leave->id],
            [
                'personnel_signatory_name_a' => trim((string) $data['name_a']),
                'personnel_signatory_position_a' => trim((string) $data['position_a']),
                'personnel_signatory_name_c' => trim((string) $data['name_c']),
                'personnel_signatory_position_c' => trim((string) $data['position_c']),
            ]
        );

        return redirect()->route('leave.print', ['leave' => $leave->id]);
    }
}
