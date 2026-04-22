<?php

namespace App\Http\Controllers;

use App\Models\BudgetHistory;
use App\Models\DepartmentHeadAssignment;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $employee = $user->employee;
        $today = today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $yearStart = $today->copy()->startOfYear()->toDateString();
        $yearEnd = $today->copy()->endOfYear()->toDateString();
        $data = ['role'=>(string)$user->role,'employee'=>$employee,'userName'=>$employee?->fullName() ?: $user->email,'employeeDashboard'=>null,'departmentHeadDashboard'=>null,'personnelDashboard'=>null];

        if ($employee && $user->role === 'employee') {
            $own = LeaveRequest::query()->with('leaveTypeRelation')->where('employee_id', $employee->id)->orderByRaw('COALESCE(start_date, DATE(created_at)) DESC')->orderByDesc('id')->get();
            $annualUsed=0.0;$sickUsed=0.0;$forceUsed=0.0;
            foreach (BudgetHistory::query()->where('employee_id',$employee->id)->whereRaw("COALESCE(trans_date, DATE(created_at)) BETWEEN ? AND ?",[$monthStart,$monthEnd])->where('action','like','deduction%')->get() as $row){ $type=strtolower(trim((string)$row->leave_type)); $delta=max(0.0,(float)$row->old_balance-(float)$row->new_balance); if($delta<=0)continue; if(str_contains($type,'sick')){$sickUsed+=$delta;continue;} if(str_contains($type,'force')||str_contains($type,'mandatory'))continue; $annualUsed+=$delta; }
            foreach (BudgetHistory::query()->where('employee_id',$employee->id)->whereRaw("COALESCE(trans_date, DATE(created_at)) BETWEEN ? AND ?",[$yearStart,$yearEnd])->where('action','like','deduction%')->get() as $row){ $type=strtolower(trim((string)$row->leave_type)); $delta=max(0.0,(float)$row->old_balance-(float)$row->new_balance); if($delta>0 && (str_contains($type,'force')||str_contains($type,'mandatory'))) $forceUsed+=$delta; }
            $pending = $own->filter(fn($r)=>strtolower((string)$r->status)==='pending')->values();
            $approvedThisMonth = $own->filter(fn($r)=>strtolower((string)$r->status)==='approved' && $r->start_date && $r->start_date->toDateString()>=$monthStart && $r->start_date->toDateString()<=$monthEnd)->count();
            $data['employeeDashboard'] = ['annual'=>(float)$employee->annual_balance,'sick'=>(float)$employee->sick_balance,'force'=>(float)$employee->force_balance,'annual_used_this_month'=>round($annualUsed,3),'sick_used_this_month'=>round($sickUsed,3),'force_used_this_year'=>round($forceUsed,3),'pending_count'=>$pending->count(),'approved_this_month'=>$approvedThisMonth,'pending_requests'=>$pending->take(6),'recent_requests'=>$own->take(6)];
        }

        if ($user->role === 'department_head') {
            $deptIds = DepartmentHeadAssignment::query()->where('employee_id',$employee?->id)->where('is_active',1)->pluck('department_id');
            if ($deptIds->isEmpty() && $employee?->department_id) $deptIds = collect([$employee->department_id]);
            if ($deptIds->isNotEmpty()) $data['departmentHeadDashboard'] = ['pending_count'=>LeaveRequest::query()->where('workflow_status','pending_department_head')->where('status','pending')->whereIn('department_id',$deptIds)->count(),'approved_this_month'=>LeaveRequest::query()->whereNotNull('department_head_approved_at')->whereBetween('department_head_approved_at',[$monthStart.' 00:00:00',$monthEnd.' 23:59:59'])->whereIn('department_id',$deptIds)->count(),'returned_count'=>LeaveRequest::query()->whereIn('workflow_status',['returned_by_personnel','rejected_department_head'])->whereIn('department_id',$deptIds)->count(),'upcoming_count'=>LeaveRequest::query()->whereIn('status',['pending','approved'])->whereDate('start_date','>=',$today)->whereIn('department_id',$deptIds)->count()];
        }

        if (in_array($user->role,['personnel','hr','admin','manager'],true)) {
            $data['personnelDashboard'] = ['pending_personnel_count'=>LeaveRequest::query()->where('workflow_status','pending_personnel')->where('status','pending')->count(),'approved_count'=>LeaveRequest::query()->where('workflow_status','finalized')->where('status','approved')->count(),'rejected_count'=>LeaveRequest::query()->where('status','rejected')->count(),'pending_print_count'=>LeaveRequest::query()->where('print_status','pending_print')->count()];
        }

        return view('dashboard.index',$data);
    }
}
