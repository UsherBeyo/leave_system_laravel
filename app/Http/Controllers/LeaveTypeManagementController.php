<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveTypeManagementController extends Controller
{
    private const BOOLEAN_FIELDS = [
        'deduct_balance',
        'requires_approval',
        'auto_approve',
        'allow_emergency',
        'requires_documents',
        'requires_medical_certificate',
        'requires_affidavit_if_no_medcert',
        'requires_travel_details',
        'requires_affidavit_if_no_medical',
        'requires_proof_of_pregnancy',
        'requires_marriage_certificate',
        'requires_child_delivery_proof',
        'requires_solo_parent_id',
        'requires_police_report',
        'requires_barangay_protection_order',
        'requires_medical_report',
        'requires_letter_request',
        'requires_dswd_proof',
        'allow_emergency_filing',
        'allow_half_day',
        'with_pay_default',
    ];

    private function authorizeRole(): void
    {
        abort_unless(in_array((string) Auth::user()->role, ['admin', 'hr'], true), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeRole();

        $search = trim((string) $request->query('q', ''));
        $types = LeaveType::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('law_title', 'like', '%' . $search . '%')
                        ->orWhere('rules_text', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('leave-types.index', compact('types', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRole();
        $data = $this->validatedData($request);
        LeaveType::query()->create($data);

        return redirect()->route('manage-leave-types')->with('success', 'Leave type created.');
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->authorizeRole();
        $data = $this->validatedData($request, $leaveType->id);
        $leaveType->update($data);

        return redirect()->route('manage-leave-types', request()->only('q', 'page'))->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $this->authorizeRole();
        $leaveType->delete();
        return redirect()->route('manage-leave-types', request()->only('q', 'page'))->with('success', 'Leave type removed.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->ignore($ignoreId)],
            'law_title' => ['nullable', 'string', 'max:255'],
            'law_text' => ['nullable', 'string'],
            'max_days_per_year' => ['nullable', 'numeric', 'min:0'],
            'balance_bucket' => ['nullable', 'string', 'max:30'],
            'deduct_behavior' => ['nullable', 'string', 'max:30'],
            'max_days' => ['nullable', 'integer', 'min:0'],
            'min_days_notice' => ['nullable', 'integer', 'min:0'],
            'details_schema_json' => ['nullable', 'string'],
            'rules_text' => ['nullable', 'string'],
            'min_days_advance' => ['nullable', 'integer', 'min:0'],
            'max_duration_days' => ['nullable', 'numeric', 'min:0'],
            'special_rules_text' => ['nullable', 'string'],
        ]);

        foreach (self::BOOLEAN_FIELDS as $field) {
            $data[$field] = $request->boolean($field);
        }

        $data['balance_bucket'] = trim((string) ($data['balance_bucket'] ?? 'annual')) ?: 'annual';
        $data['deduct_behavior'] = trim((string) ($data['deduct_behavior'] ?? 'deduct_full')) ?: 'deduct_full';
        $data['law_title'] = $this->nullableString($data['law_title'] ?? null);
        $data['law_text'] = $this->nullableString($data['law_text'] ?? null);
        $data['details_schema_json'] = $this->nullableString($data['details_schema_json'] ?? null);
        $data['rules_text'] = $this->nullableString($data['rules_text'] ?? null);
        $data['special_rules_text'] = $this->nullableString($data['special_rules_text'] ?? null);

        return $data;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
