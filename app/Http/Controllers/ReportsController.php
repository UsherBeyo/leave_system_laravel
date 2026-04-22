<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentHeadAssignment;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(private LeaveLedgerService $ledger)
    {
    }

    public function index(Request $request): View|StreamedResponse
    {
        $user = Auth::user();
        $employee = $user->employee;
        abort_unless(in_array($user->role, ['admin', 'hr', 'personnel', 'department_head'], true), 403);

        [$employees, $departments, $departmentNames] = $this->scopedEmployees($user->role, $employee?->id, $employee?->department_id, $employee?->department);

        $reportType = (string) $request->query('type', 'summary');
        if (!in_array($reportType, ['summary', 'balance', 'usage', 'leave_card'], true)) {
            $reportType = 'summary';
        }

        $departmentFilter = (string) $request->query('dept', '');
        if ($departmentFilter !== '' && !in_array($departmentFilter, $departmentNames, true)) {
            $departmentFilter = '';
        }

        $filteredEmployees = $employees;
        if ($departmentFilter !== '') {
            $filteredEmployees = $filteredEmployees->filter(fn ($row) => (string) $row->department === $departmentFilter)->values();
        }

        $employeeId = (int) $request->query('employee_id', 0);
        $selectedEmployee = $employeeId ? $filteredEmployees->firstWhere('id', $employeeId) : null;
        if ($request->has('employee_id') && !$selectedEmployee) {
            $employeeId = 0;
        }

        $summary = [
            'totalEmployees' => $filteredEmployees->count(),
            'pendingRequests' => LeaveRequest::query()->whereIn('employee_id', $filteredEmployees->pluck('id'))->where('status', 'pending')->count(),
            'approvedRequests' => LeaveRequest::query()->whereIn('employee_id', $filteredEmployees->pluck('id'))->where(function ($q) { $q->where('status', 'approved')->orWhere('workflow_status', 'finalized'); })->count(),
            'avgAnnualBalance' => $filteredEmployees->count() ? $filteredEmployees->avg('annual_balance') : 0,
        ];

        $reportData = [];
        if ($reportType === 'balance') {
            $reportData = $filteredEmployees->sortBy([['department', 'asc'], ['first_name', 'asc'], ['last_name', 'asc']])->values();
        } elseif ($reportType === 'usage') {
            $reportData = $this->ledger->usageRows($filteredEmployees, $departmentFilter ?: null);
        } elseif ($reportType === 'leave_card' && $selectedEmployee) {
            $reportData = $this->ledger->buildLeaveCardRows($selectedEmployee->id);
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($reportType, $reportData, $selectedEmployee);
        }

        return view('reports.index', [
            'role' => $user->role,
            'summary' => $summary,
            'reportType' => $reportType,
            'departmentFilter' => $departmentFilter,
            'departments' => $departments,
            'employees' => $filteredEmployees,
            'selectedEmployee' => $selectedEmployee,
            'reportData' => $reportData,
        ]);
    }

    private function scopedEmployees(string $role, ?int $employeeId, ?int $departmentId, ?string $departmentName): array
    {
        if ($role === 'department_head') {
            $deptIds = DepartmentHeadAssignment::query()
                ->where('employee_id', $employeeId)
                ->where('is_active', 1)
                ->pluck('department_id')
                ->filter()
                ->values();

            $query = Employee::query();
            if ($deptIds->isNotEmpty()) {
                $query->whereIn('department_id', $deptIds);
            } elseif ($departmentId) {
                $query->where('department_id', $departmentId);
            } elseif ($departmentName) {
                $query->where('department', $departmentName);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query = Employee::query();
        }

        $employees = $query->get();
        $departments = $employees->pluck('department')->filter()->unique()->sort()->values();
        return [$employees, $departments, $departments->all()];
    }

    private function exportCsv(string $reportType, mixed $reportData, ?Employee $selectedEmployee): StreamedResponse
    {
        if ($reportType === 'leave_card' && $selectedEmployee) {
            $filename = 'Leave Card - '.trim($selectedEmployee->fullName()).'.csv';
            $headers = ['Date','Particulars','Vac Earned','Vac Deducted','Vac Balance','Sick Earned','Sick Deducted','Sick Balance','Status'];
            $rows = $reportData;
        } elseif ($reportType === 'usage') {
            $filename = 'Leave Usage Report.csv';
            $headers = ['Department','Leave Type','Request Count','Total Days'];
            $rows = $reportData;
        } else {
            $filename = 'Leave Balance Report.csv';
            $headers = ['Name','Department','Vacational Balance','Sick Balance','Force Balance'];
            $rows = $reportData;
        }

        return response()->streamDownload(function () use ($headers, $rows, $reportType) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                if ($reportType === 'leave_card') {
                    fputcsv($out, [
                        $row['date'] ?? '', $row['particulars'] ?? '', $row['vac_earned'] ?? '', $row['vac_deducted'] ?? '', $row['vac_balance'] ?? '',
                        $row['sick_earned'] ?? '', $row['sick_deducted'] ?? '', $row['sick_balance'] ?? '', $row['status'] ?? '',
                    ]);
                } elseif ($reportType === 'usage') {
                    fputcsv($out, [$row['department'] ?? '', $row['leave_type'] ?? '', $row['count'] ?? 0, $row['total_days'] ?? 0]);
                } else {
                    fputcsv($out, [
                        trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                        $row->department,
                        $row->annual_balance,
                        $row->sick_balance,
                        $row->force_balance,
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
