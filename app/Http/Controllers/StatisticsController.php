<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    private function authorizeRole(): void
    {
        abort_unless((string) Auth::user()->role === 'admin', 403);
    }

    public function index(): View
    {
        $this->authorizeRole();

        $totalEmployees = Employee::query()->count();
        $departmentStats = Employee::query()
            ->selectRaw('COALESCE(NULLIF(department, ""), "Unassigned") as department_name, COUNT(*) as count')
            ->groupBy('department_name')
            ->orderBy('department_name')
            ->get();

        $roleStats = User::query()
            ->selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->orderBy('role')
            ->get();

        $activeUsers = User::query()->where('is_active', 1)->count();
        $inactiveUsers = User::query()->where('is_active', 0)->count();
        $averageAnnual = (float) Employee::query()->avg('annual_balance');

        return view('statistics.index', compact(
            'totalEmployees',
            'departmentStats',
            'roleStats',
            'activeUsers',
            'inactiveUsers',
            'averageAnnual'
        ));
    }
}
