<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HolidayController extends Controller
{
    private function authorizeRole(): void
    {
        abort_unless(in_array((string) Auth::user()->role, ['admin', 'manager', 'hr', 'personnel'], true), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeRole();

        $search = trim((string) $request->query('q', ''));
        $holidays = Holiday::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('description', 'like', '%' . $search . '%')
                        ->orWhere('type', 'like', '%' . $search . '%')
                        ->orWhere('holiday_date', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('holiday_date')
            ->paginate(10)
            ->withQueryString();

        return view('holidays.index', compact('holidays', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
        ]);

        Holiday::query()->create([
            'holiday_date' => $data['date'],
            'description' => trim((string) ($data['description'] ?? '')),
            'type' => trim((string) $data['type']),
        ]);

        return redirect()->route('holidays')->with('success', 'Holiday added.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->ignore($holiday->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
        ]);

        $holiday->update([
            'holiday_date' => $data['date'],
            'description' => trim((string) ($data['description'] ?? '')),
            'type' => trim((string) $data['type']),
        ]);

        return redirect()->route('holidays', request()->only('q', 'page'))->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $this->authorizeRole();
        $holiday->delete();
        return redirect()->route('holidays', request()->only('q', 'page'))->with('success', 'Holiday removed.');
    }
}
