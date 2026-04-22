<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Services\LeaveCalculatorService;
use App\Services\LeavePolicyService;
use App\Services\LeaveWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeaveApplicationController extends Controller
{
    public function __construct(private LeavePolicyService $policyService, private LeaveCalculatorService $calculator, private LeaveWorkflowService $workflow) {}

    public function create(): View|RedirectResponse
    {
        $user = Auth::user(); $employee = $user->employee; if(!$employee) return redirect()->route('dashboard')->with('error','No employee profile is linked to this account.');
        $leaveTypes = LeaveType::query()->orderBy('name')->get(); $policies=[];
        foreach($leaveTypes as $leaveType){ $usage=[]; $rows=$employee->leaveRequests()->selectRaw('YEAR(start_date) as yr, SUM(total_days) as total')->where('leave_type_id',$leaveType->id)->whereIn('status',['approved','pending'])->groupByRaw('YEAR(start_date)')->pluck('total','yr'); foreach($rows as $yr=>$total){$usage[(string)$yr]=(float)$total;} $preset=$this->policyService->uiPreset($leaveType->name,$usage); $preset['name']=$leaveType->name; $preset['id']=$leaveType->id; $policies[$leaveType->id]=$preset; }
        return view('leaves.apply',['employee'=>$employee,'leaveTypes'=>$leaveTypes,'policyMap'=>$policies]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user=Auth::user(); $employee=$user->employee; if(!$employee) return back()->with('error','No employee profile is linked to this account.');
        $validated=$request->validate(['leave_type_id'=>['required','integer','exists:leave_types,id'],'filing_date'=>['required','date'],'start_date'=>['required','date'],'end_date'=>['required','date'],'reason'=>['nullable','string'],'leave_subtype'=>['nullable','string','max:100'],'details'=>['nullable','array'],'details.*'=>['nullable'],'supporting_documents'=>['nullable','array'],'supporting_documents.*'=>['nullable'],'medical_certificate_attached'=>['nullable'],'affidavit_attached'=>['nullable'],'emergency_case'=>['nullable'],'commutation'=>['nullable','string','max:50'],'attachments.*'=>['nullable','file','max:10240']]);
        $leaveType=LeaveType::query()->findOrFail((int)$validated['leave_type_id']);
        try { $leave=$this->workflow->apply($employee,$leaveType,$validated,$request->file('attachments',[]),$user->id,(string)$user->role); }
        catch (\Throwable $e) { return back()->withInput()->with('error',$e->getMessage()); }
        return redirect()->route('dashboard')->with('success','Leave submitted successfully. Request #'.$leave->id.' created.');
    }

    public function calculate(Request $request): JsonResponse
    {
        $data=$request->validate(['start_date'=>['required','date'],'end_date'=>['required','date']]);
        return response()->json($this->calculator->calculateDaysBreakdown($data['start_date'],$data['end_date']));
    }
}
