<?php

namespace App\Services;

use App\Models\BudgetHistory;
use App\Models\Employee;
use App\Models\LeaveBalanceLog;
use App\Models\LeaveCancellation;
use App\Models\LeaveCancellationAttachment;
use App\Models\LeaveRequest;
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

class LeaveCancellationService
{
    public function __construct(private LeavePolicyService $policyService)
    {
    }

    public function requestCancellation(User $user, LeaveRequest $leave, string $reason, array $files = []): LeaveCancellation
    {
        $employee = $user->employee;
        if (!$employee || (int) $employee->id !== (int) $leave->employee_id) {
            throw new RuntimeException('You can only request cancellation for your own leave request.');
        }

        $this->guardLeaveCanBeCancelled($leave);

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Please state the reason for cancellation.');
        }

        $validUploads = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile && $file->isValid()));

        $cancellation = DB::transaction(function () use ($leave, $employee, $user, $reason, $validUploads) {
            $existing = LeaveCancellation::query()
                ->where('leave_request_id', $leave->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new RuntimeException('This leave already has a pending cancellation request.');
            }

            $cancellation = LeaveCancellation::query()->create([
                'leave_request_id' => $leave->id,
                'employee_id' => $employee->id,
                'requested_by_user_id' => $user->id,
                'reason' => $reason,
                'status' => 'pending',
            ]);

            if (!empty($validUploads)) {
                $this->storeAttachments($cancellation, $validUploads, (int) $user->id);
            }

            return $cancellation;
        });

        $this->sendPersonnelCancellationEmail($cancellation);

        return $cancellation;
    }

    public function approve(LeaveCancellation $cancellation, User $reviewer, string $comments = ''): LeaveCancellation
    {
        return DB::transaction(function () use ($cancellation, $reviewer, $comments) {
            $cancellation = LeaveCancellation::query()->lockForUpdate()->with(['leaveRequest', 'employee'])->findOrFail($cancellation->id);
            if ((string) $cancellation->status !== 'pending') {
                throw new RuntimeException('This cancellation request has already been reviewed.');
            }

            $leave = LeaveRequest::query()->lockForUpdate()->with(['leaveTypeRelation', 'form'])->findOrFail($cancellation->leave_request_id);
            $employee = Employee::query()->lockForUpdate()->findOrFail($cancellation->employee_id);

            $wasApproved = ((string) $leave->status === 'approved' || (string) $leave->workflow_status === 'finalized');
            $restored = $wasApproved ? $this->restoreApprovedLeaveBalances($leave, $employee, $cancellation) : [];

            $leave->status = 'cancelled';
            $leave->workflow_status = $wasApproved ? 'cancelled_after_approval' : 'cancelled_before_approval';
            $leave->personnel_user_id = $reviewer->id;
            $leave->personnel_comments = trim($comments) !== '' ? trim($comments) : $leave->personnel_comments;
            $leave->personnel_checked_at = now();
            $leave->save();

            if (!$wasApproved) {
                $this->logCancellationCardEntry($employee, $leave, $cancellation, 0.0, 0.0, 'No balance deduction was restored because the leave was not finalized yet.');
            }

            $cancellation->status = 'approved';
            $cancellation->reviewed_by_user_id = $reviewer->id;
            $cancellation->personnel_comments = trim($comments) !== '' ? trim($comments) : null;
            $cancellation->reviewed_at = now();
            $cancellation->save();

            $this->sendApplicantCancellationEmail($cancellation, 'Your leave cancellation was approved', $wasApproved
                ? 'Your leave cancellation request was approved. The deducted paid balance was restored where applicable.'
                : 'Your leave cancellation request was approved. No balance restoration was needed because the leave was not finalized yet.');

            return $cancellation->fresh(['leaveRequest', 'employee', 'attachments']);
        });
    }

    public function reject(LeaveCancellation $cancellation, User $reviewer, string $comments = ''): LeaveCancellation
    {
        $comments = trim($comments);
        if ($comments === '') {
            throw new RuntimeException('Please add a reason before rejecting the cancellation request.');
        }

        $cancellation = DB::transaction(function () use ($cancellation, $reviewer, $comments) {
            $cancellation = LeaveCancellation::query()->lockForUpdate()->findOrFail($cancellation->id);
            if ((string) $cancellation->status !== 'pending') {
                throw new RuntimeException('This cancellation request has already been reviewed.');
            }

            $cancellation->status = 'rejected';
            $cancellation->reviewed_by_user_id = $reviewer->id;
            $cancellation->personnel_comments = $comments;
            $cancellation->reviewed_at = now();
            $cancellation->save();

            return $cancellation->fresh(['leaveRequest', 'employee', 'attachments']);
        });

        $this->sendApplicantCancellationEmail($cancellation, 'Your leave cancellation was rejected', 'Your leave cancellation request was rejected. Reason: '.$comments);

        return $cancellation;
    }

    private function guardLeaveCanBeCancelled(LeaveRequest $leave): void
    {
        $status = strtolower((string) $leave->status);
        $workflow = strtolower((string) $leave->workflow_status);
        $allowed = in_array($status, ['pending', 'approved'], true) || in_array($workflow, ['pending_department_head', 'pending_personnel', 'returned_by_personnel', 'finalized'], true);
        if (!$allowed) {
            throw new RuntimeException('Only pending or approved leave requests can be requested for cancellation.');
        }

        if (!$leave->start_date || !$leave->start_date->gt(now()->startOfDay())) {
            throw new RuntimeException('Only leave requests with a future start date can be cancelled here.');
        }
    }

    private function restoreApprovedLeaveBalances(LeaveRequest $leave, Employee $employee, LeaveCancellation $cancellation): array
    {
        $deductionRows = BudgetHistory::query()
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($leave) {
                if (Schema::hasColumn('budget_history', 'leave_request_id')) {
                    $query->where('leave_request_id', $leave->id);
                }
                if (Schema::hasColumn('budget_history', 'leave_id')) {
                    $query->orWhere('leave_id', $leave->id);
                }
            })
            ->where('action', 'deduction')
            ->orderBy('id')
            ->get();

        $restored = [];
        if ($deductionRows->isEmpty()) {
            $fallback = $this->fallbackRestorationFromForm($leave);
            foreach ($fallback as $bucket => $amount) {
                if ($amount > 0) {
                    $restored[] = $this->restoreBucket($employee, $leave, $cancellation, $bucket, $amount, 'Restored from leave form approval values.');
                }
            }
            return $restored;
        }

        foreach ($deductionRows as $row) {
            $amount = max(0.0, (float) $row->old_balance - (float) $row->new_balance);
            if ($amount <= 0) {
                continue;
            }
            $bucket = $this->bucketFromBudgetRow($row);
            $restored[] = $this->restoreBucket($employee, $leave, $cancellation, $bucket, $amount, 'Restored from original approval deduction log #'.$row->id.'.');
        }

        return $restored;
    }

    private function fallbackRestorationFromForm(LeaveRequest $leave): array
    {
        $typeKey = $this->policyService->normalizeLeaveTypeKey((string) $leave->leave_type_name);
        $form = $leave->form;
        $vacation = (float) ($form?->cert_vacation_less_this_application ?? 0);
        $sick = (float) ($form?->cert_sick_less_this_application ?? 0);
        $days = (float) $leave->total_days;

        if ($typeKey === 'sick leave') {
            return ['sick' => $sick > 0 ? $sick : $days];
        }
        if ($typeKey === 'mandatory/forced leave') {
            $details = $leave->details_meta;
            $forceOnly = !empty($details['force_balance_only']);
            return $forceOnly
                ? ['force' => $days]
                : ['force' => $days, 'annual' => $vacation > 0 ? $vacation : $days];
        }
        if ($typeKey === 'wellness leave') {
            return ['wellness' => $days];
        }
        if ($typeKey === 'special privilege leave') {
            return ['spl' => $days];
        }

        return ['annual' => $vacation > 0 ? $vacation : $days];
    }

    private function bucketFromBudgetRow(BudgetHistory $row): string
    {
        $typeKey = $this->policyService->normalizeLeaveTypeKey((string) $row->leave_type);
        $notes = strtolower((string) $row->notes);

        if ($typeKey === 'sick leave') {
            return 'sick';
        }
        if ($typeKey === 'wellness leave') {
            return 'wellness';
        }
        if ($typeKey === 'special privilege leave') {
            return 'spl';
        }
        if ($typeKey === 'mandatory/forced leave') {
            if (str_contains($notes, 'force side') || str_contains($notes, 'force balance only')) {
                return 'force';
            }
            return 'annual';
        }

        return 'annual';
    }

    private function restoreBucket(Employee $employee, LeaveRequest $leave, LeaveCancellation $cancellation, string $bucket, float $amount, string $note): array
    {
        $amount = $this->trunc($amount);
        if ($amount <= 0) {
            return ['bucket' => $bucket, 'amount' => 0.0];
        }

        $column = match ($bucket) {
            'sick' => 'sick_balance',
            'force' => 'force_balance',
            'wellness' => 'wellness_balance',
            'spl' => 'spl_balance',
            default => 'annual_balance',
        };

        $old = (float) ($employee->{$column} ?? 0);
        $new = $this->trunc($old + $amount);
        $employee->{$column} = $new;
        $employee->leave_balance = $this->trunc((float) $employee->annual_balance + (float) $employee->sick_balance);
        $employee->save();

        $bucketLabel = match ($bucket) {
            'sick' => 'Sick Leave',
            'force' => 'Mandatory/Forced Leave',
            'wellness' => 'Wellness Leave',
            'spl' => 'Special Privilege Leave',
            default => 'Vacation Leave',
        };

        $this->logCancellationCardEntry($employee, $leave, $cancellation, $old, $new, $note.' BUCKET='.$bucket.';RESTORED='.$amount, $bucketLabel);

        if (in_array($bucket, ['annual', 'force'], true) && Schema::hasTable('leave_balance_logs')) {
            LeaveBalanceLog::query()->create([
                'employee_id' => $employee->id,
                'change_amount' => $amount,
                'reason' => 'leave_cancellation_restore',
                'leave_id' => $leave->id,
                'created_at' => now(),
            ]);
        }

        return ['bucket' => $bucket, 'amount' => $amount, 'old' => $old, 'new' => $new];
    }

    private function logCancellationCardEntry(Employee $employee, LeaveRequest $leave, LeaveCancellation $cancellation, float $old, float $new, string $note, string $leaveType = 'Leave Cancellation'): void
    {
        if (!Schema::hasTable('budget_history')) {
            return;
        }

        $dates = $leave->start_date?->format('M d') ?: '';
        if ($leave->end_date && $leave->end_date->toDateString() !== optional($leave->start_date)->toDateString()) {
            $dates .= ' - '.$leave->end_date->format('M d');
        }

        BudgetHistory::query()->create([
            'employee_id' => $employee->id,
            'leave_type' => $leaveType,
            'action' => 'cancellation_restore',
            'old_balance' => $this->trunc($old),
            'new_balance' => $this->trunc($new),
            'notes' => 'CANCELLED_LEAVE_ID='.$leave->id.';CANCELLATION_ID='.$cancellation->id.';DATES='.$dates.';ORIGINAL_TYPE='.$leave->leave_type_name.';'.$note,
            'trans_date' => now()->toDateString(),
            'created_at' => now(),
        ]);
    }

    private function storeAttachments(LeaveCancellation $cancellation, array $files, int $userId): void
    {
        $allowed = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];

        $relative = 'uploads/leave_cancellations/'.now()->format('Y/m');
        File::ensureDirectoryExists(public_path($relative));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach (array_slice($files, 0, 5) as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $originalName = $this->sanitizeAttachmentOriginalName($file->getClientOriginalName());
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!isset($allowed[$ext])) {
                throw new RuntimeException($originalName.' has an unsupported file type.');
            }

            $tempPath = $file->getRealPath();
            if (!$tempPath || !is_file($tempPath)) {
                throw new RuntimeException($originalName.' could not be read from the temporary upload location.');
            }

            $size = (int) ($file->getSize() ?: filesize($tempPath) ?: 0);
            if ($size <= 0 || $size > (10 * 1024 * 1024)) {
                throw new RuntimeException($originalName.' exceeds the 10MB limit.');
            }

            $mime = (string) ($finfo->file($tempPath) ?: $file->getClientMimeType() ?: $file->getMimeType() ?: '');
            if (!in_array($mime, $allowed[$ext], true)) {
                throw new RuntimeException($originalName.' failed file validation.');
            }

            $stored = Str::uuid()->toString().'.'.$ext;
            $file->move(public_path($relative), $stored);

            LeaveCancellationAttachment::query()->create([
                'leave_cancellation_id' => $cancellation->id,
                'original_name' => $originalName,
                'stored_name' => $stored,
                'file_path' => $relative.'/'.$stored,
                'mime_type' => $mime,
                'file_size' => $size,
                'uploaded_by_user_id' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    private function sendPersonnelCancellationEmail(LeaveCancellation $cancellation): void
    {
        $cancellation->loadMissing(['leaveRequest.leaveTypeRelation', 'employee.user']);
        $employeeName = trim((string) ($cancellation->employee?->full_name ?? 'Employee'));
        $leave = $cancellation->leaveRequest;
        $body = $employeeName.' requested cancellation of '.$leave?->leave_type_name.' from '.($leave?->start_date?->toDateString() ?: '—').' to '.($leave?->end_date?->toDateString() ?: '—').'. Reason: '.$cancellation->reason;

        foreach ($this->personnelRecipientEmails() as $email) {
            $this->sendEmailSafely($email, 'Leave cancellation request pending review', $body, $cancellation->id);
        }
    }

    private function sendApplicantCancellationEmail(LeaveCancellation $cancellation, string $subject, string $body): void
    {
        $cancellation->loadMissing(['employee.user']);
        $email = trim((string) ($cancellation->employee?->user?->email ?? ''));
        if ($email === '') {
            return;
        }
        $this->sendEmailSafely($email, $subject, $body, $cancellation->id);
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

    private function sendEmailSafely(string $to, string $subject, string $body, ?int $cancellationId = null): void
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Leave cancellation email was not sent.', [
                'leave_cancellation_id' => $cancellationId,
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
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

    private function trunc(float|int|string|null $value): float
    {
        $n = (float) ($value ?? 0);
        return $n >= 0 ? floor($n * 1000) / 1000 : ceil($n * 1000) / 1000;
    }
}
