<?php

namespace App\Http\Controllers;

use App\Models\SystemSignatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SignatorySettingsController extends Controller
{
    public function index(): View
    {
        abort_unless(in_array((string) Auth::user()->role, ['personnel', 'admin', 'hr'], true), 403);
        $rows = SystemSignatory::query()->orderBy('id')->get();
        return view('settings.signatories', compact('rows'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(in_array((string) Auth::user()->role, ['personnel', 'admin', 'hr'], true), 403);
        $data = $request->validate([
            'id' => ['required', 'array'],
            'id.*' => ['required', 'integer'],
            'name' => ['required', 'array'],
            'name.*' => ['required', 'string'],
            'position' => ['required', 'array'],
            'position.*' => ['required', 'string'],
        ]);

        $count = min(count($data['id']), count($data['name']), count($data['position']));
        for ($i = 0; $i < $count; $i++) {
            SystemSignatory::query()->whereKey((int) $data['id'][$i])->update([
                'name' => trim((string) $data['name'][$i]),
                'position' => trim((string) $data['position'][$i]),
            ]);
        }

        return back()->with('success', 'Signatories updated successfully.');
    }
}
