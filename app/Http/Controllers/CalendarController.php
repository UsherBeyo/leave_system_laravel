<?php

namespace App\Http\Controllers;

use App\Models\DepartmentHeadAssignment;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $role = (string) $user->role;
        $employee = $user->employee;

        $month = max(1, min(12, (int) $request->query('m', now()->month)));
        $year = max(2000, min(2100, (int) $request->query('y', now()->year)));

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $today = now()->startOfDay();
        $monthLabel = $start->format('F Y');

        $showFullCalendarLeaves = in_array($role, ['admin', 'personnel', 'hr'], true);
        $showSnapshotDetails = in_array($role, ['admin', 'personnel', 'hr'], true);

        $accessibleDepartmentIds = collect();
        if (in_array($role, ['department_head', 'manager'], true) && $employee) {
            $accessibleDepartmentIds = DepartmentHeadAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', 1)
                ->pluck('department_id');

            if ($accessibleDepartmentIds->isEmpty() && $employee->department_id) {
                $accessibleDepartmentIds = collect([$employee->department_id]);
            }
        }

        $leaveQuery = LeaveRequest::query()
            ->with(['employee.user'])
            ->whereIn('status', ['approved', 'pending']);

        if (! $showFullCalendarLeaves) {
            if (in_array($role, ['department_head', 'manager'], true)) {
                if ($accessibleDepartmentIds->isNotEmpty()) {
                    $leaveQuery->whereIn('department_id', $accessibleDepartmentIds->all());
                } else {
                    $leaveQuery->whereRaw('1 = 0');
                }
            } else {
                $leaveQuery->where('employee_id', $employee?->id ?: 0);
            }
        }

        $monthLeaves = (clone $leaveQuery)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $upcomingLeaves = (clone $leaveQuery)
            ->whereDate('end_date', '>=', $today->toDateString())
            ->orderBy('start_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(6)
            ->get();

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('holiday_date')
            ->get();

        $upcomingEvents = Holiday::query()
            ->whereDate('holiday_date', '>=', $today->toDateString())
            ->orderBy('holiday_date')
            ->limit(6)
            ->get();

        $events = [];
        $monthApprovedCount = 0;
        $monthPendingCount = 0;

        foreach ($holidays as $holiday) {
            $date = $holiday->holiday_date?->toDateString() ?: (string) $holiday->holiday_date;
            $events[$date][] = [
                'type' => 'holiday',
                'status' => 'holiday',
                'title' => $holiday->description ?: 'Holiday',
                'desc' => $holiday->type ?: 'Holiday',
                'meta' => $this->formatDate($date),
            ];
        }

        foreach ($monthLeaves as $leave) {
            $status = strtolower(trim((string) $leave->status));
            if ($status === 'approved') {
                $monthApprovedCount++;
            }
            if ($status === 'pending') {
                $monthPendingCount++;
            }

            $displayTitle = $leave->employee?->fullName() ?: 'Employee Leave';
            $displayDesc = $leave->leave_type_name ?: ($leave->leave_type ?: 'Leave Request');
            $displayMeta = $this->formatDateRange((string) $leave->start_date?->toDateString(), (string) $leave->end_date?->toDateString());

            $cursor = Carbon::parse($leave->start_date)->startOfDay();
            $leaveEnd = Carbon::parse($leave->end_date)->startOfDay();
            while ($cursor->lte($leaveEnd)) {
                $date = $cursor->toDateString();
                if ($cursor->betweenIncluded($start, $end)) {
                    $events[$date][] = [
                        'type' => 'leave',
                        'status' => $status,
                        'title' => $displayTitle,
                        'desc' => $displayDesc,
                        'meta' => $displayMeta,
                    ];
                }
                $cursor->addDay();
            }
        }

        ksort($events);

        $daysWithEvents = count($events);
        $totalMonthRequests = $monthLeaves->count();
        $totalMonthHolidays = $holidays->count();
        $firstDow = (int) $start->isoWeekday();
        $daysInMonth = (int) $start->daysInMonth;

        return view('calendar.index', compact(
            'month',
            'year',
            'start',
            'end',
            'today',
            'monthLabel',
            'events',
            'upcomingLeaves',
            'upcomingEvents',
            'daysWithEvents',
            'totalMonthRequests',
            'totalMonthHolidays',
            'monthApprovedCount',
            'monthPendingCount',
            'firstDow',
            'daysInMonth',
            'showSnapshotDetails'
        ));
    }

    private function formatDate(?string $date): string
    {
        if (! $date) {
            return '—';
        }

        return Carbon::parse($date)->format('F j, Y');
    }

    private function formatDateRange(?string $start, ?string $end): string
    {
        if (! $start && ! $end) {
            return '—';
        }
        if ($start && $end && $start === $end) {
            return $this->formatDate($start);
        }
        return trim($this->formatDate($start) . ' - ' . $this->formatDate($end));
    }
}
