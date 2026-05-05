@extends('layouts.app')

@section('title', 'Leave Cancellations')

@section('content')
    <div class="page-header" style="margin-bottom:22px;">
        <div>
            <h2>Leave Cancellations</h2>
            <p class="help-text">Request and review cancellation of pending or approved future leave requests.</p>
        </div>
    </div>

    @if($employee)
        <section class="section-card" style="margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px;">
                <div>
                    <h3 style="margin:0 0 6px;">Available Leaves for Cancellation</h3>
                    <p class="help-text" style="margin:0;">Only your pending or approved leaves with a future start date appear here.</p>
                </div>
            </div>

            @if($cancellableLeaves->isEmpty())
                <p class="help-text" style="margin:0;">No cancellable future leave requests found.</p>
            @else
                <div style="overflow-x:auto;">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Dates</th>
                                <th>Total Days</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cancellableLeaves as $leave)
                                @php $modalId = 'cancelLeaveModal'.$leave->id; @endphp
                                <tr>
                                    <td>{{ $leave->leave_type_name }}</td>
                                    <td>{{ optional($leave->start_date)->format('M d, Y') }} to {{ optional($leave->end_date)->format('M d, Y') }}</td>
                                    <td>{{ number_format((float) $leave->total_days, 3) }}</td>
                                    <td>{{ ucfirst((string) $leave->status) }}</td>
                                    <td><button type="button" class="btn btn-danger" onclick="openCancellationModal('{{ $modalId }}')">Request Cancellation</button></td>
                                </tr>

                                <div id="{{ $modalId }}" class="cancel-modal" onclick="if(event.target===this) closeCancellationModal('{{ $modalId }}')">
                                    <div class="cancel-modal-dialog">
                                        <button type="button" class="cancel-modal-close" onclick="closeCancellationModal('{{ $modalId }}')">&times;</button>
                                        <h3 style="margin:0 0 8px;">Request Leave Cancellation</h3>
                                        <p class="help-text" style="margin:0 0 16px;">{{ $leave->leave_type_name }} • {{ optional($leave->start_date)->format('M d, Y') }} to {{ optional($leave->end_date)->format('M d, Y') }}</p>
                                        <form method="POST" action="{{ route('leave.cancellations.store', $leave) }}" enctype="multipart/form-data">
                                            @csrf
                                            <div class="field" style="margin-bottom:14px;">
                                                <label>Reason for cancellation</label>
                                                <textarea name="reason" required placeholder="State why you want to cancel this leave request.">{{ old('reason') }}</textarea>
                                            </div>
                                            <div class="field" style="margin-bottom:16px;">
                                                <label>Attachment/s (optional)</label>
                                                <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp">
                                                <small class="help-text">Accepted: PDF, JPG, PNG, WEBP. Maximum 5 files, 10MB each.</small>
                                            </div>
                                            <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                                                <button type="button" class="btn btn-secondary" onclick="closeCancellationModal('{{ $modalId }}')">Close</button>
                                                <button type="submit" class="btn btn-danger">Submit Cancellation Request</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="section-card" style="margin-bottom:24px;">
            <h3 style="margin:0 0 12px;">My Cancellation Requests</h3>
            @if($myCancellations->isEmpty())
                <p class="help-text" style="margin:0;">You have not submitted any leave cancellation request yet.</p>
            @else
                <div style="overflow-x:auto;">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Leave Dates</th>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Personnel Remarks</th>
                                <th>Attachment/s</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myCancellations as $item)
                                <tr>
                                    <td>{{ $item->leaveRequest?->leave_type_name ?? '—' }}</td>
                                    <td>{{ optional($item->leaveRequest?->start_date)->format('M d, Y') }} to {{ optional($item->leaveRequest?->end_date)->format('M d, Y') }}</td>
                                    <td>{{ optional($item->created_at)->format('M d, Y h:i A') }}</td>
                                    <td>{{ ucfirst((string) $item->status) }}</td>
                                    <td>{{ $item->personnel_comments ?: '—' }}</td>
                                    <td>
                                        @forelse($item->attachments as $attachment)
                                            <a href="{{ asset($attachment->file_path) }}" target="_blank" rel="noopener">{{ $attachment->original_name }}</a>@if(!$loop->last)<br>@endif
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if($isPersonnelReviewer)
        <section class="section-card">
            <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px;">
                <div>
                    <h3 style="margin:0 0 6px;">Personnel Review</h3>
                    <p class="help-text" style="margin:0;">Approve or reject submitted leave cancellation requests.</p>
                </div>
                <div class="tab-links" style="margin-bottom:0;">
                    <a href="{{ route('leave.cancellations') }}" class="{{ request('status') ? '' : 'active' }}">All</a>
                    <a href="{{ route('leave.cancellations', ['status' => 'pending']) }}" class="{{ request('status') === 'pending' ? 'active' : '' }}">Pending</a>
                    <a href="{{ route('leave.cancellations', ['status' => 'approved']) }}" class="{{ request('status') === 'approved' ? 'active' : '' }}">Approved</a>
                    <a href="{{ route('leave.cancellations', ['status' => 'rejected']) }}" class="{{ request('status') === 'rejected' ? 'active' : '' }}">Rejected</a>
                </div>
            </div>

            @if($reviewCancellations->isEmpty())
                <p class="help-text" style="margin:0;">No leave cancellation requests found.</p>
            @else
                <div class="cancellation-list">
                    @foreach($reviewCancellations as $item)
                        @php
                            $leave = $item->leaveRequest;
                            $employeeName = $item->employee?->full_name ?: 'Employee';
                        @endphp
                        <div class="request-card" style="margin-bottom:16px;">
                            <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;">
                                <div>
                                    <h4 style="margin:0 0 6px;">{{ $employeeName }}</h4>
                                    <p class="help-text" style="margin:0;">{{ $leave?->leave_type_name ?? 'Leave Request' }} • {{ optional($leave?->start_date)->format('M d, Y') }} to {{ optional($leave?->end_date)->format('M d, Y') }} • {{ number_format((float) ($leave?->total_days ?? 0), 3) }} day/s</p>
                                </div>
                                <span class="request-chip request-chip-muted">{{ ucfirst((string) $item->status) }}</span>
                            </div>

                            <div class="request-detail-grid" style="margin-top:16px;">
                                <div class="request-detail-panel">
                                    <h4>Cancellation Reason</h4>
                                    <p style="white-space:pre-wrap;margin:0;">{{ $item->reason }}</p>
                                </div>
                                <div class="request-detail-panel">
                                    <h4>Request Details</h4>
                                    <div class="request-kv"><span>Original Status</span><strong>{{ ucfirst((string) ($leave?->status ?? '—')) }}</strong></div>
                                    <div class="request-kv"><span>Workflow</span><strong>{{ str_replace('_', ' ', (string) ($leave?->workflow_status ?? '—')) }}</strong></div>
                                    <div class="request-kv"><span>Submitted</span><strong>{{ optional($item->created_at)->format('M d, Y h:i A') }}</strong></div>
                                    <div class="request-kv"><span>Reviewed By</span><strong>{{ $item->reviewedBy?->email ?: '—' }}</strong></div>
                                </div>
                                <div class="request-detail-panel">
                                    <h4>Attachment/s</h4>
                                    @forelse($item->attachments as $attachment)
                                        <p style="margin:0 0 8px;"><a href="{{ asset($attachment->file_path) }}" target="_blank" rel="noopener">{{ $attachment->original_name }}</a></p>
                                    @empty
                                        <p class="help-text" style="margin:0;">No attachment uploaded.</p>
                                    @endforelse
                                </div>
                            </div>

                            @if($item->personnel_comments)
                                <div class="request-detail-panel" style="margin-top:14px;">
                                    <h4>Personnel Remarks</h4>
                                    <p style="white-space:pre-wrap;margin:0;">{{ $item->personnel_comments }}</p>
                                </div>
                            @endif

                            @if($item->status === 'pending')
                                <div class="action-row" style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;">
                                    <form method="POST" action="{{ route('leave.cancellations.action', $item) }}" style="flex:1 1 280px;">
                                        @csrf
                                        <input type="hidden" name="action" value="approve">
                                        <div class="field" style="margin-bottom:10px;">
                                            <label>Approval comments</label>
                                            <input type="text" name="comments" placeholder="Optional comments for the employee">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Approve Cancellation</button>
                                    </form>
                                    <form method="POST" action="{{ route('leave.cancellations.action', $item) }}" style="flex:1 1 280px;">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <div class="field" style="margin-bottom:10px;">
                                            <label>Rejection reason</label>
                                            <input type="text" name="comments" placeholder="Required when rejecting">
                                        </div>
                                        <button type="submit" class="btn btn-danger">Reject Cancellation</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                {{ $reviewCancellations->links('vendor.pagination.clean') }}
            @endif
        </section>
    @endif

    <style>
        .cancel-modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:10000;align-items:center;justify-content:center;padding:18px;}
        .cancel-modal.is-open{display:flex;}
        .cancel-modal-dialog{background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(15,23,42,.28);max-width:560px;width:100%;padding:24px;position:relative;}
        .cancel-modal-close{position:absolute;right:16px;top:12px;border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;color:#475569;}
        .request-chip{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;}
        .request-chip-muted{background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;}
        .request-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;}
        .request-detail-panel{border:1px solid var(--border);border-radius:14px;padding:14px;background:#f8fafc;}
        .request-detail-panel h4{margin:0 0 10px;font-size:14px;}
        .request-kv{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #e2e8f0;padding:7px 0;}
        .request-kv:last-child{border-bottom:0;}
        .request-kv span{color:var(--muted);font-size:13px;}
        .request-kv strong{font-size:13px;text-align:right;}
    </style>
@endsection

@push('scripts')
<script>
function openCancellationModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('is-open');
}
function closeCancellationModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('is-open');
}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.cancel-modal.is-open').forEach(function(modal) {
            modal.classList.remove('is-open');
        });
    }
});
</script>
@endpush
