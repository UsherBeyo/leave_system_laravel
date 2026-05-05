<?php

namespace App\Http\Controllers;

use App\Models\LeaveCancellation;
use App\Models\LeaveRequest;
use App\Services\LeaveCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeaveCancellationController extends Controller
{
    public function __construct(private LeaveCancellationService $cancellationService)
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $role = (string) $user->role;
        $today = now()->toDateString();
        $isPersonnelReviewer = in_array($role, ['personnel', 'hr', 'admin'], true);
        $employee = $user->employee;

        $cancellableLeaves = collect();
        $myCancellations = collect();
        $reviewCancellations = collect();

        if ($employee) {
            $cancellableLeaves = LeaveRequest::query()
                ->with(['leaveTypeRelation', 'pendingCancellation'])
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '>', $today)
                ->whereDoesntHave('pendingCancellation')
                ->orderBy('start_date')
                ->orderBy('id')
                ->get();

            $myCancellations = LeaveCancellation::query()
                ->with(['leaveRequest.leaveTypeRelation', 'attachments', 'reviewedBy'])
                ->where('employee_id', $employee->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
        }

        if ($isPersonnelReviewer) {
            $reviewCancellations = LeaveCancellation::query()
                ->with(['leaveRequest.leaveTypeRelation', 'employee.user', 'requestedBy', 'reviewedBy', 'attachments'])
                ->when($request->query('status'), function ($query, $status) {
                    $query->where('status', (string) $status);
                })
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();
        }

        return view('leaves.cancellations', compact(
            'role',
            'employee',
            'isPersonnelReviewer',
            'cancellableLeaves',
            'myCancellations',
            'reviewCancellations'
        ));
    }

    public function store(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $this->cancellationService->requestCancellation(
                Auth::user(),
                $leave,
                (string) $data['reason'],
                $request->file('attachments', [])
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Leave cancellation request submitted to Personnel.');
    }

    public function action(Request $request, LeaveCancellation $cancellation): RedirectResponse
    {
        $user = Auth::user();
        abort_if(!in_array((string) $user->role, ['personnel', 'hr', 'admin'], true), 403);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'comments' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            if ($data['action'] === 'approve') {
                $this->cancellationService->approve($cancellation, $user, (string) ($data['comments'] ?? ''));
                return back()->with('success', 'Leave cancellation approved and balances restored when applicable.');
            }

            $this->cancellationService->reject($cancellation, $user, (string) ($data['comments'] ?? ''));
            return back()->with('success', 'Leave cancellation rejected.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
