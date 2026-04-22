<?php

namespace App\Services;

use App\Models\BudgetHistory;
use App\Models\DepartmentHeadAssignment;
use App\Models\Employee;
use App\Models\LeaveAttachment;
use App\Models\LeaveBalanceLog;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class LeaveWorkflowService
{
    public function __construct(private LeavePolicyService $policyService, private LeaveCalculatorService $calculator) {}

    public function apply(Employee $employee, LeaveType $leaveType, array $payload, array $files = [], ?int $applicantUserId = null, string $applicantRole = 'employee'): LeaveRequest
    {
        $policy = $this->policyService->policyFromLeaveType($leaveType);
        $start = (string) $payload['start_date']; $end = (string) $payload['end_date']; $filingDate = (string)($payload['filing_date'] ?? now()->toDateString());
        $breakdown = $this->calculator->calculateDaysBreakdown($start, $end);
        if (!($breakdown['valid'] ?? false) || ($breakdown['days'] ?? 0) <= 0) throw new RuntimeException((string)($breakdown['message'] ?? 'Invalid leave date range.'));
        $days = (float)$breakdown['days'];

        $overlap = LeaveRequest::query()->where('employee_id', $employee->id)->whereIn('status', ['approved','pending'])->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        if ($overlap) throw new RuntimeException('Overlapping leave exists.');

        if (($policy['min_days_notice'] ?? 0) > 0) {
            $filing = Carbon::createFromFormat('Y-m-d', $filingDate); $startDate = Carbon::createFromFormat('Y-m-d', $start);
            if ($filing->diffInDays($startDate, false) < (int)$policy['min_days_notice']) throw new RuntimeException('This leave must be filed at least '.$policy['min_days_notice'].' day(s) before the start date.');
        }
        if (!empty($policy['max_days']) && $days > (float)$policy['max_days']) throw new RuntimeException('This leave type allows up to '.$policy['max_days'].' day(s) per application.');
        if (!empty($policy['max_days_per_year'])) {
            $already = LeaveRequest::query()->where('employee_id', $employee->id)->where('leave_type_id', $leaveType->id)->whereYear('start_date', Carbon::parse($start)->year)->whereIn('status', ['approved','pending'])->sum('total_days');
            if (($already + $days) > (float)$policy['max_days_per_year']) throw new RuntimeException('Applying would exceed the annual maximum for '.$leaveType->name.'.');
        }

        $details = $payload['details'] ?? []; $details['force_balance_only'] = !empty($details['force_balance_only']) ? 1 : 0;
        $selectedDocuments = array_keys(array_filter($payload['supporting_documents'] ?? []));
        $validUploads = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile && $file->isValid()));
        $requiredDocCount = (int)($policy['required_doc_count'] ?? 0); $uploadedCount = count($validUploads);
        if ($requiredDocCount > 0 && $uploadedCount < $requiredDocCount) throw new RuntimeException('This leave type requires supporting document attachment(s). Please upload the required file(s).');

        $normalizedType = $this->policyService->normalizeLeaveTypeKey($leaveType->name);
        if ($normalizedType === 'sick leave' && $days > 5 && empty($payload['medical_certificate_attached']) && $uploadedCount < 1) throw new RuntimeException('A medical certificate is required for sick leave covering more than five (5) continuous working days.');

        $this->guardBalance($employee, $leaveType, $policy, $days, !empty($details['force_balance_only']));

        $departmentHeadUserId = $this->resolveDepartmentHeadUserId($employee);
        $workflowStatus = 'pending_department_head'; $departmentHeadApprovedAt = null;
        if (!$departmentHeadUserId || in_array($applicantRole, ['manager','department_head','admin'], true) || ($applicantUserId && $departmentHeadUserId === $applicantUserId)) {
            $workflowStatus = 'pending_personnel'; $departmentHeadApprovedAt = now(); if (in_array($applicantRole, ['manager','department_head','admin'], true)) $departmentHeadUserId = $applicantUserId ?: $departmentHeadUserId;
        }
        if (!$departmentHeadUserId && !in_array($applicantRole, ['manager','department_head','admin'], true)) throw new RuntimeException('Cannot submit leave: No department head assigned to your department.');

        $status = !empty($policy['auto_approve']) ? 'approved' : 'pending'; if ($status === 'approved') $workflowStatus = 'finalized';

        $leave = DB::transaction(function () use ($employee, $leaveType, $payload, $details, $selectedDocuments, $filingDate, $start, $end, $days, $status, $workflowStatus, $departmentHeadUserId, $departmentHeadApprovedAt, $validUploads) {
            $leave = LeaveRequest::create([
                'employee_id' => $employee->id, 'department_id' => $employee->department_id, 'leave_type' => $leaveType->name, 'leave_type_id' => $leaveType->id,
                'leave_subtype' => $payload['leave_subtype'] ?? null, 'details_json' => !empty($details) ? json_encode($details) : null, 'filing_date' => $filingDate,
                'start_date' => $start, 'end_date' => $end, 'total_days' => $days, 'reason' => $payload['reason'] ?? '', 'status' => $status, 'workflow_status' => $workflowStatus,
                'department_head_user_id' => $departmentHeadUserId, 'department_head_approved_at' => $departmentHeadApprovedAt,
                'snapshot_annual_balance' => $employee->annual_balance, 'snapshot_sick_balance' => $employee->sick_balance, 'snapshot_force_balance' => $employee->force_balance,
                'commutation' => $payload['commutation'] ?? null, 'supporting_documents_json' => !empty($selectedDocuments) ? json_encode(array_values($selectedDocuments)) : null,
                'medical_certificate_attached' => !empty($payload['medical_certificate_attached']), 'affidavit_attached' => !empty($payload['affidavit_attached']), 'emergency_case' => !empty($payload['emergency_case']),
            ]);
            if (!empty($validUploads)) $this->storeAttachments($leave, $validUploads, (int)($employee->user_id ?? 0));
            return $leave;
        });

        if ($status === 'approved') {
            $this->finalApprove($leave, null, '', []);
            $leave->refresh();
        } else {
            $this->sendSubmissionReviewEmail($leave);
        }

        return $leave;
    }

    public function departmentHeadApprove(LeaveRequest $leave, int $userId, string $comments = ''): LeaveRequest
    {
        $leave->status='pending';
        $leave->workflow_status='pending_personnel';
        $leave->department_head_user_id = $leave->department_head_user_id ?: $userId;
        $leave->department_head_comments = $comments;
        $leave->department_head_approved_at = now();
        $leave->save();

        $this->sendApplicantWorkflowEmail(
            $leave,
            'Your leave request moved to personnel review',
            'Your {leave_type} leave request from {start_date} to {end_date} was approved by the Department Head and is now pending personnel review.'
        );

        return $leave;
    }

    public function finalApprove(LeaveRequest $leave, ?int $managerId, string $comments = '', array $options = []): LeaveRequest
    {
        $approvedLeave = DB::transaction(function () use ($leave, $managerId, $comments, $options) {
            $leave->refresh();
            if (in_array((string) $leave->workflow_status, ['finalized'], true) || (string) $leave->status === 'approved') {
                throw new RuntimeException('This leave request has already been finalized.');
            }
            $employee = $leave->employee()->lockForUpdate()->firstOrFail(); $leaveType = $leave->leaveTypeRelation ?: LeaveType::query()->firstWhere('name', $leave->leave_type);
            $policy = $this->policyService->policyFromLeaveType($leaveType?->toArray() ?: ['name' => $leave->leave_type]); $typeName = (string)($leaveType?->name ?: $leave->leave_type); $typeKey = $this->policyService->normalizeLeaveTypeKey($typeName);
            $days=(float)$leave->total_days; $details=$leave->details_meta; $forceOnly=!empty($details['force_balance_only']);
            $hasApprovedWithPay = array_key_exists('approved_with_pay', $options) && $options['approved_with_pay'] !== null && $options['approved_with_pay'] !== '';
            $hasApprovedWithoutPay = array_key_exists('approved_without_pay', $options) && $options['approved_without_pay'] !== null && $options['approved_without_pay'] !== '';
            $hasDeductDays = array_key_exists('deduct_days', $options) && $options['deduct_days'] !== null && $options['deduct_days'] !== '';
            $daysWithPay = $hasApprovedWithPay ? (float) $options['approved_with_pay'] : $days;
            $daysWithoutPay = $hasApprovedWithoutPay ? (float) $options['approved_without_pay'] : 0.0;
            $deductDays = $hasDeductDays ? (float) $options['deduct_days'] : $days;
            $isLateSick=$typeKey==='sick leave' && $this->isLateSickFiling((string)$leave->filing_date,(string)$leave->end_date); if($isLateSick && !$hasDeductDays){ $daysWithPay=0.0; $daysWithoutPay=$days; $deductDays=0.0; }
            if (empty($policy['deduct_balance'])) { $daysWithPay=$days; $daysWithoutPay=0.0; $deductDays=0.0; }
            $oldAnnual=(float)$employee->annual_balance; $oldSick=(float)$employee->sick_balance; $oldForce=(float)$employee->force_balance; $vacLess=0.0; $sickLess=0.0;
            if (!empty($policy['deduct_balance']) && $deductDays > 0) {
                if ($typeKey === 'mandatory/forced leave') {
                    $employee->force_balance = max(0, (float) $employee->force_balance - $deductDays);
                    $this->logBudgetChange($employee->id, $typeName, $oldForce, $employee->force_balance, 'deduction', $leave->id, $forceOnly ? 'Leave approved (force balance only deduction)' : 'Leave approved (mandatory/forced leave dual deduction - force side)');
                    if ($forceOnly) {
                        $this->logLeaveBalanceChange($employee->id, -1 * $deductDays, 'deduction_force_leave_only', $leave->id);
                    } else {
                        $employee->annual_balance = max(0, (float) $employee->annual_balance - $deductDays);
                        $this->logBudgetChange($employee->id, $typeName, $oldAnnual, $employee->annual_balance, 'deduction', $leave->id, 'Leave approved (mandatory/forced leave dual deduction - vacational side)');
                        $this->logLeaveBalanceChange($employee->id, -1 * $deductDays, 'deduction_force_leave_dual', $leave->id);
                    }
                } elseif ($typeKey === 'sick leave') {
                    $employee->sick_balance = max(0, (float) $employee->sick_balance - $deductDays); $sickLess = $deductDays; $this->logBudgetChange($employee->id, $typeName, $oldSick, $employee->sick_balance, 'deduction', $leave->id, 'Leave approved');
                } else {
                    $employee->annual_balance = max(0, (float) $employee->annual_balance - $deductDays); $vacLess = $deductDays; $this->logBudgetChange($employee->id, $typeName, $oldAnnual, $employee->annual_balance, 'deduction', $leave->id, 'Leave approved');
                }
                $employee->leave_balance = (float)$employee->annual_balance + (float)$employee->sick_balance; $employee->save();
            }
            $leave->status='approved'; $leave->workflow_status='finalized'; $leave->approved_by=$managerId; $leave->manager_comments=$comments; $leave->personnel_user_id=$managerId; $leave->personnel_comments=$comments; $leave->personnel_checked_at=now(); $leave->finalized_at=now(); $leave->print_status='pending_print'; $leave->save();
            $this->upsertLeaveRequestFormApprovalData($leave->id,$oldAnnual,$vacLess,(float)$employee->annual_balance,$oldSick,$sickLess,(float)$employee->sick_balance,$daysWithPay,$daysWithoutPay);
            return $leave;
        });

        $this->sendApplicantWorkflowEmail(
            $approvedLeave,
            $managerId === null ? 'Your leave has been approved' : 'Your leave request approved',
            $managerId === null
                ? 'Your {leave_type} leave from {start_date} to {end_date} was auto-approved.'
                : 'Your {leave_type} leave from {start_date} to {end_date} has been fully approved.'
        );

        return $approvedLeave;
    }

    public function previewApprovalImpact(LeaveRequest $leave): array
    {
        $employee = $leave->employee;
        $leaveType = $leave->leaveTypeRelation ?: LeaveType::query()->firstWhere('name', $leave->leave_type);
        $policy = $this->policyService->policyFromLeaveType($leaveType?->toArray() ?: ['name' => $leave->leave_type]);
        $typeName = (string)($leaveType?->name ?: $leave->leave_type);
        $typeKey = $this->policyService->normalizeLeaveTypeKey($typeName);
        $details = $leave->details_meta;
        $forceOnly = !empty($details['force_balance_only']);
        $days = max(0.0, (float) $leave->total_days);

        $liveAnnual = (float) ($employee?->annual_balance ?? 0);
        $liveSick = (float) ($employee?->sick_balance ?? 0);
        $liveForce = (float) ($employee?->force_balance ?? 0);

        $current = [
            'annual' => $liveAnnual,
            'sick' => $liveSick,
            'force' => $liveForce,
        ];
        $after = $current;
        $before = $current;
        $deductions = [
            'annual' => 0.0,
            'sick' => 0.0,
            'force' => 0.0,
        ];

        $mode = (($leave->workflow_status === 'finalized') || ($leave->status === 'approved')) ? 'finalized' : 'projected';
        $daysWithPay = $days;
        $daysWithoutPay = 0.0;
        $deductDays = $days;
        $notes = [];
        $highlight = [];

        if (empty($policy['deduct_balance'])) {
            $deductDays = 0.0;
            $notes[] = 'This leave type does not deduct from any balance bucket.';
        }

        if ($typeKey === 'sick leave' && $this->isLateSickFiling((string) $leave->filing_date, (string) $leave->end_date)) {
            $daysWithPay = 0.0;
            $daysWithoutPay = $days;
            $deductDays = 0.0;
            $notes[] = 'Late-filed sick leave defaults to without pay unless personnel overrides it.';
        }

        if ($mode === 'finalized') {
            $form = $leave->form;
            $after = [
                'annual' => (float) ($form?->cert_vacation_balance ?? $leave->snapshot_annual_balance ?? 0),
                'sick' => (float) ($form?->cert_sick_balance ?? $leave->snapshot_sick_balance ?? 0),
                'force' => (float) ($leave->snapshot_force_balance ?? 0),
            ];

            $deductions['annual'] = (float) ($form?->cert_vacation_less_this_application ?? 0);
            $deductions['sick'] = (float) ($form?->cert_sick_less_this_application ?? 0);
            if ($typeKey === 'mandatory/forced leave') {
                $deductions['force'] = $deductDays;
                if (!$forceOnly && $deductions['annual'] <= 0) {
                    $deductions['annual'] = $deductDays;
                }
            }
            if ($typeKey === 'sick leave' && $deductions['sick'] <= 0) {
                $deductions['sick'] = $deductDays;
            }
            if (!in_array($typeKey, ['sick leave', 'mandatory/forced leave'], true) && $deductions['annual'] <= 0) {
                $deductions['annual'] = $deductDays;
            }

            $before = [
                'annual' => $after['annual'] + $deductions['annual'],
                'sick' => $after['sick'] + $deductions['sick'],
                'force' => $after['force'] + $deductions['force'],
            ];
            $current = $before;
            $notes[] = 'Finalized requests show the employee balance before deduction and the recorded final balance after approval.';
        } else {
            if ($deductDays > 0) {
                if ($typeKey === 'mandatory/forced leave') {
                    $after['force'] = max(0, $liveForce - $deductDays);
                    $deductions['force'] = $deductDays;
                    $highlight[] = 'force';
                    if ($forceOnly) {
                        $notes[] = 'This request is flagged as Force Balance only.';
                    } else {
                        $after['annual'] = max(0, $liveAnnual - $deductDays);
                        $deductions['annual'] = $deductDays;
                        $highlight[] = 'annual';
                        $notes[] = 'Standard force leave deducts from both Force and Vacational balances.';
                    }
                } elseif ($typeKey === 'sick leave') {
                    $after['sick'] = max(0, $liveSick - $deductDays);
                    $deductions['sick'] = $deductDays;
                    $highlight[] = 'sick';
                } else {
                    $after['annual'] = max(0, $liveAnnual - $deductDays);
                    $deductions['annual'] = $deductDays;
                    $highlight[] = 'annual';
                }
            }
            $before = $current;
        }

        return [
            'mode' => $mode,
            'type_key' => $typeKey,
            'current' => $current,
            'before' => $before,
            'after' => $after,
            'deductions' => $deductions,
            'days_with_pay' => $daysWithPay,
            'days_without_pay' => $daysWithoutPay,
            'deduct_days' => $deductDays,
            'highlight' => array_values(array_unique($highlight)),
            'notes' => $notes,
            'left_label' => $mode === 'finalized' ? 'Before Approval' : 'Current Balance',
            'right_label' => $mode === 'finalized' ? 'Recorded Final' : 'After Approval',
        ];
    }

    public function reject(LeaveRequest $leave, int $userId, string $role, string $comments = ''): LeaveRequest
    {
        $departmentHeadStage = in_array($role,['department_head','manager','admin'],true) && ($leave->workflow_status==='pending_department_head' || blank($leave->workflow_status) || $leave->workflow_status==='returned_by_personnel');
        if ($departmentHeadStage) {
            $leave->workflow_status='rejected_department_head';
            $leave->department_head_user_id=$leave->department_head_user_id?:$userId;
            $leave->department_head_comments=$comments;
            $leave->department_head_approved_at=now();
        } else {
            $leave->workflow_status='rejected_personnel';
            $leave->personnel_user_id=$userId;
            $leave->personnel_comments=$comments;
            $leave->personnel_checked_at=now();
        }
        $leave->status='rejected';
        $leave->approved_by=$userId;
        $leave->manager_comments=$comments;
        $leave->save();

        $this->sendApplicantWorkflowEmail(
            $leave,
            'Your leave request was rejected',
            $departmentHeadStage
                ? 'Your {leave_type} leave from {start_date} to {end_date} was rejected by the Department Head. Reason: {comments}'
                : 'Your {leave_type} leave from {start_date} to {end_date} was rejected by Personnel. Reason: {comments}',
            ['comments' => $comments !== '' ? $comments : 'No reason provided.']
        );

        return $leave;
    }

    public function returnToDepartmentHead(LeaveRequest $leave, int $userId, string $comments = ''): LeaveRequest
    {
        $leave->status='pending';
        $leave->workflow_status='returned_by_personnel';
        $leave->personnel_user_id=$userId;
        $leave->personnel_comments=$comments;
        $leave->personnel_checked_at=now();
        $leave->save();

        $this->sendApplicantWorkflowEmail(
            $leave,
            'Your leave request was returned by personnel',
            'Your {leave_type} leave from {start_date} to {end_date} was not finalized by personnel. Reason: {comments}',
            ['comments' => $comments !== '' ? $comments : 'No reason provided.']
        );

        return $leave;
    }

    private function sendSubmissionReviewEmail(LeaveRequest $leave): void
    {
        $leave->loadMissing(['employee.user', 'leaveTypeRelation']);

        $subject = '';
        $body = '';
        $recipients = [];

        if ((string) $leave->workflow_status === 'pending_department_head') {
            $recipients = $this->departmentHeadRecipientEmails($leave);
            $subject = 'New leave request awaiting department-head approval';
            $body = '{employee_name} filed {leave_type} leave from {start_date} to {end_date} and it is waiting for department-head approval.';
        } elseif ((string) $leave->workflow_status === 'pending_personnel') {
            $recipients = $this->personnelRecipientEmails();
            $subject = 'New leave request awaiting personnel review';
            $body = '{employee_name} filed {leave_type} leave from {start_date} to {end_date} and it is now pending personnel review.';
        }

        if ($subject === '' || $body === '' || empty($recipients)) {
            return;
        }

        $body = $this->replaceWorkflowTokens($leave, $body);
        foreach ($recipients as $email) {
            $this->sendEmailSafely($email, $subject, $body, $leave->id);
        }
    }

    private function sendApplicantWorkflowEmail(LeaveRequest $leave, string $subject, string $bodyTemplate, array $extraTokens = []): void
    {
        $leave->loadMissing(['employee.user', 'leaveTypeRelation']);
        $email = trim((string) ($leave->employee?->user?->email ?? ''));
        if ($email === '') {
            return;
        }

        $body = $this->replaceWorkflowTokens($leave, $bodyTemplate, $extraTokens);
        $this->sendEmailSafely($email, $subject, $body, $leave->id);
    }

    private function replaceWorkflowTokens(LeaveRequest $leave, string $bodyTemplate, array $extraTokens = []): string
    {
        $leave->loadMissing(['employee.user', 'leaveTypeRelation']);
        $employeeName = trim((string) ($leave->employee?->full_name ?? ''));
        if ($employeeName === '') {
            $employeeName = 'The employee';
        }

        $mappedExtras = [];
        foreach ($extraTokens as $key => $value) {
            $mappedExtras['{'.$key.'}'] = (string) $value;
        }

        $tokens = array_merge([
            '{employee_name}' => $employeeName,
            '{leave_type}' => (string) $leave->leave_type_name,
            '{start_date}' => $leave->start_date?->toDateString() ?: (string) $leave->start_date,
            '{end_date}' => $leave->end_date?->toDateString() ?: (string) $leave->end_date,
            '{comments}' => '',
        ], $mappedExtras);

        return strtr($bodyTemplate, $tokens);
    }

    private function departmentHeadRecipientEmails(LeaveRequest $leave): array
    {
        $emails = [];
        $userId = (int) ($leave->department_head_user_id ?: 0);
        if ($userId > 0) {
            $email = trim((string) User::query()->whereKey($userId)->value('email'));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        if (empty($emails) && $leave->department_id) {
            $assignment = DepartmentHeadAssignment::query()->where('department_id', $leave->department_id)->where('is_active', 1)->first();
            if ($assignment) {
                $headEmployee = Employee::query()->with('user')->find($assignment->employee_id);
                $email = trim((string) ($headEmployee?->user?->email ?? ''));
                if ($email !== '') {
                    $emails[] = $email;
                }
            }
        }

        return array_values(array_unique($emails));
    }

    private function personnelRecipientEmails(): array
    {
        return User::query()
            ->whereIn('role', ['personnel', 'hr', 'admin'])
            ->where('is_active', 1)
            ->pluck('email')
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function sendEmailSafely(string $to, string $subject, string $body, ?int $leaveId = null): void
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Leave workflow email was not sent.', [
                'leave_request_id' => $leaveId,
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function guardBalance(Employee $employee, LeaveType $leaveType, array $policy, float $days, bool $forceOnly): void
    {
        if (empty($policy['deduct_balance'])) return; $typeKey=$this->policyService->normalizeLeaveTypeKey($leaveType->name);
        if ($typeKey === 'mandatory/forced leave') { if((float)$employee->force_balance < $days) throw new RuntimeException('Insufficient '.$leaveType->name.' leave balance.'); if(!$forceOnly && (float)$employee->annual_balance < $days) throw new RuntimeException('Insufficient '.$leaveType->name.' leave balance.'); return; }
        if ($typeKey === 'sick leave' && (float)$employee->sick_balance < $days) throw new RuntimeException('Insufficient '.$leaveType->name.' leave balance.');
        if ($typeKey !== 'sick leave' && (float)$employee->annual_balance < $days) throw new RuntimeException('Insufficient '.$leaveType->name.' leave balance.');
    }

    private function resolveDepartmentHeadUserId(Employee $employee): ?int
    {
        if (!$employee->department_id) return null; $assignment=DepartmentHeadAssignment::query()->where('department_id',$employee->department_id)->where('is_active',1)->first(); if(!$assignment) return null; $headEmployee=Employee::query()->find($assignment->employee_id); return $headEmployee?->user_id ? (int)$headEmployee->user_id : null;
    }

    private function isLateSickFiling(string $filingDate, string $endDate): bool
    {
        try { $filing=Carbon::createFromFormat('Y-m-d',$filingDate); $end=Carbon::createFromFormat('Y-m-d',$endDate)->addMonth(); } catch (\Throwable $e) { return false; }
        return $filing->gt($end);
    }

    private function upsertLeaveRequestFormApprovalData(int $leaveId, float $vacTotal, float $vacLess, float $vacBalance, float $sickTotal, float $sickLess, float $sickBalance, float $daysWithPay, float $daysWithoutPay): void
    {
        if (!Schema::hasTable('leave_request_forms')) return;
        DB::table('leave_request_forms')->updateOrInsert(['leave_request_id'=>$leaveId],[
            'cert_vacation_total_earned'=>$vacTotal,'cert_vacation_less_this_application'=>$vacLess,'cert_vacation_balance'=>$vacBalance,
            'cert_sick_total_earned'=>$sickTotal,'cert_sick_less_this_application'=>$sickLess,'cert_sick_balance'=>$sickBalance,
            'approved_for_days_with_pay'=>$daysWithPay,'approved_for_days_without_pay'=>$daysWithoutPay,'updated_at'=>now(),'created_at'=>now(),
        ]);
    }

    private function logBudgetChange(int $employeeId, string $leaveType, float $old, float $new, string $action, ?int $leaveId, string $notes=''): void
    {
        if (!Schema::hasTable('budget_history')) return;
        $payload = [
            'employee_id' => $employeeId,
            'leave_type' => $leaveType,
            'action' => $action,
            'old_balance' => $old,
            'new_balance' => $new,
            'notes' => $notes,
            'created_at' => now(),
        ];
        if (Schema::hasColumn('budget_history', 'leave_request_id')) {
            $payload['leave_request_id'] = $leaveId;
        } elseif (Schema::hasColumn('budget_history', 'leave_id')) {
            $payload['leave_id'] = $leaveId;
        }
        if (Schema::hasColumn('budget_history', 'trans_date')) {
            $payload['trans_date'] = now()->toDateString();
        }
        BudgetHistory::query()->create($payload);
    }

    private function logLeaveBalanceChange(int $employeeId, float $changeAmount, string $reason, ?int $leaveId): void
    {
        if (!Schema::hasTable('leave_balance_logs')) return;

        $payload = [
            'employee_id' => $employeeId,
            'change_amount' => $changeAmount,
            'reason' => $reason,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('leave_balance_logs', 'leave_id')) {
            $payload['leave_id'] = $leaveId;
        }

        LeaveBalanceLog::query()->create($payload);
    }

    private function storeAttachments(LeaveRequest $leave, array $files, int $userId): void
    {
        $allowed = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];

        $relative = 'uploads/leave_attachments/' . now()->format('Y/m');
        File::ensureDirectoryExists(public_path($relative));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach (array_slice($files, 0, 5) as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $originalName = $this->sanitizeAttachmentOriginalName($file->getClientOriginalName());
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!isset($allowed[$ext])) {
                throw new RuntimeException($originalName . ' has an unsupported file type.');
            }

            $tempPath = $file->getRealPath();
            if (!$tempPath || !is_file($tempPath)) {
                throw new RuntimeException($originalName . ' could not be read from the temporary upload location.');
            }

            $size = (int) ($file->getSize() ?: filesize($tempPath) ?: 0);
            if ($size <= 0 || $size > (10 * 1024 * 1024)) {
                throw new RuntimeException($originalName . ' exceeds the 10MB limit.');
            }

            $mime = (string) ($finfo->file($tempPath) ?: $file->getClientMimeType() ?: $file->getMimeType() ?: '');
            if (!in_array($mime, $allowed[$ext], true)) {
                throw new RuntimeException($originalName . ' failed file validation.');
            }

            $stored = Str::uuid()->toString() . '.' . $ext;
            $file->move(public_path($relative), $stored);

            LeaveAttachment::query()->create([
                'leave_request_id' => $leave->id,
                'original_name' => $originalName,
                'stored_name' => $stored,
                'file_path' => $relative . '/' . $stored,
                'mime_type' => $mime,
                'file_size' => $size,
                'document_type' => 'supporting_document',
                'uploaded_by_user_id' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    private function sanitizeAttachmentOriginalName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?? 'attachment';
        $name = preg_replace('/\s+/', ' ', $name) ?? 'attachment';
        $name = trim($name);
        return substr($name !== '' ? $name : 'attachment', 0, 180);
    }
}
