<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentHeadAssignment;
use App\Models\LeaveRequest;
use App\Models\SystemSignatory;
use App\Services\LeavePolicyService;
use App\Services\LeaveWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveWorkflowService $workflow, private LeavePolicyService $policyService) {}

    public function index(Request $request): View
    {
        $user=Auth::user(); $role=(string)$user->role;
        $allowedTabs=$role==='personnel'?['pending','approved','rejected']:(($role==='department_head')?['all','pending','approved','rejected']:['all','pending','approved','rejected','archived']);
        $tab=(string)$request->query('tab',$role==='personnel'?'pending':'all'); if(!in_array($tab,$allowedTabs,true)) $tab=$role==='personnel'?'pending':'all';
        $query=LeaveRequest::query()->with(['employee.user','leaveTypeRelation','attachments','form']);
        if($role==='department_head'){ $deptIds=DepartmentHeadAssignment::query()->where('employee_id',$user->employee?->id)->where('is_active',1)->pluck('department_id'); if($deptIds->isEmpty() && $user->employee?->department_id) $deptIds=collect([$user->employee->department_id]); $query->whereIn('department_id',$deptIds); }
        if($tab==='pending'){ if(in_array($role,['personnel','hr'],true)) $query->where('workflow_status','pending_personnel')->where('status','pending'); elseif(in_array($role,['department_head','manager'],true)) $query->whereIn('workflow_status',['pending_department_head','returned_by_personnel'])->where('status','pending'); else $query->where('status','pending'); }
        elseif($tab==='approved') $query->where(function($q){$q->where('workflow_status','finalized')->orWhere('status','approved');});
        elseif($tab==='rejected') $query->where('status','rejected');
        elseif($tab==='archived') $query->whereIn('workflow_status',['finalized','rejected_department_head','rejected_personnel']);
        $month=max(0,min(12,(int)$request->query('month',0))); $year=max(0,(int)$request->query('year',0)); $departmentId=max(0,(int)$request->query('department_id',0));
        if($month>0)$query->whereMonth('start_date',$month); if($year>0)$query->whereYear('start_date',$year); if($departmentId>0 && in_array($role,['admin','personnel','hr'],true))$query->where('department_id',$departmentId);
        $sortBy=(string)$request->query('sort_by','leave'); $direction=strtolower((string)$request->query('direction','desc'))==='asc'?'asc':'desc';
        if($sortBy==='submitted')$query->orderBy('created_at',$direction); elseif($sortBy==='forwarded')$query->orderBy('department_head_approved_at',$direction)->orderBy('id',$direction); elseif($sortBy==='approved')$query->orderByRaw('COALESCE(finalized_at, personnel_checked_at, department_head_approved_at, created_at) '.$direction); else $query->orderByRaw('COALESCE(start_date, DATE(created_at)) '.$direction)->orderBy('id',$direction);
        $rows=$query->paginate(15)->withQueryString(); $departments=Department::query()->where('is_active',1)->orderBy('name')->get();
        $leavePolicies = [];
        $approvalImpacts = [];
        foreach ($rows as $row) {
            $leavePolicies[$row->id] = $this->policyService->uiPreset($row->leave_type_name);
            $approvalImpacts[$row->id] = $this->workflow->previewApprovalImpact($row);
        }
        $signatories = SystemSignatory::query()->orderBy('id')->get()->keyBy('key_name');
        return view('leaves.requests',compact('rows','tab','role','departments','leavePolicies','approvalImpacts','signatories'));
    }

    public function action(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user=Auth::user(); $data=$request->validate(['action'=>['required','in:approve,reject,return,mark_printed'],'comments'=>['nullable','string'],'approved_with_pay'=>['nullable','numeric'],'approved_without_pay'=>['nullable','numeric'],'deduct_days'=>['nullable','numeric']]);
        try {
            switch($data['action']){
                case 'approve':
                    if(($leave->workflow_status==='pending_department_head' || blank($leave->workflow_status) || $leave->workflow_status==='returned_by_personnel') && in_array($user->role,['department_head','manager','admin'],true)){ $this->workflow->departmentHeadApprove($leave,$user->id,(string)($data['comments']??'')); $message='Leave approved by Department Head and forwarded to Personnel'; }
                    elseif(in_array($user->role,['personnel','hr','admin'],true)){
                        $approvalOptions = [];
                        foreach (['approved_with_pay', 'approved_without_pay', 'deduct_days'] as $field) {
                            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                                $approvalOptions[$field] = $data[$field];
                            }
                        }
                        $this->workflow->finalApprove($leave,$user->id,(string)($data['comments']??''),$approvalOptions); $message='Leave request finalized and approved'; }
                    else abort(403); break;
                case 'reject': if(!in_array($user->role,['department_head','manager','personnel','hr','admin'],true)) abort(403); $this->workflow->reject($leave,$user->id,(string)$user->role,(string)($data['comments']??'')); $message='Leave request rejected'; break;
                case 'return': if(!in_array($user->role,['personnel','hr','admin'],true)) abort(403); $this->workflow->returnToDepartmentHead($leave,$user->id,(string)($data['comments']??'')); $message='Leave request returned to Department Head'; break;
                case 'mark_printed': if(!in_array($user->role,['personnel','hr','admin'],true)) abort(403); $this->workflow->markPrinted($leave); $message='Leave request marked as printed'; break;
                default: $message='No action performed';
            }
        } catch (\RuntimeException $e) { return back()->with('error',$e->getMessage()); }
        return back()->with('success',$message);
    }
}
