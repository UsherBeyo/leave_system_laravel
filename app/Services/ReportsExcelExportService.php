<?php

namespace App\Services;

use App\Models\Employee;
use App\Support\StyledXlsxExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ReportsExcelExportService
{
    /**
     * @param  Collection<int, Employee>  $employees
     */
    public function downloadBalance(Collection $employees, string $departmentFilter = ''): Response
    {
        $tableRows = [];
        foreach ($employees as $employee) {
            $tableRows[] = [
                (int) $employee->id,
                (string) ($employee->first_name ?? ''),
                (string) ($employee->last_name ?? ''),
                (string) ($employee->department ?: '—'),
                $this->trunc3($employee->annual_balance),
                $this->trunc3($employee->sick_balance),
                $this->trunc3($employee->force_balance),
            ];
        }

        return StyledXlsxExport::downloadResponse([
            'filename' => 'balance_' . now()->format('Y-m-d'),
            'sheet_title' => 'Leave Balance',
            'info_title' => 'Report Information',
            'employee_info_rows' => $this->reportInfoRows('Leave Balance Report', $departmentFilter),
            'table_title' => 'LEAVE BALANCE REPORT',
            'table_headers' => ['ID', 'First Name', 'Last Name', 'Department', 'Vacational Balance', 'Sick Balance', 'Force Balance'],
            'table_rows' => $tableRows,
            'column_widths' => [10, 18, 18, 24, 18, 16, 16],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $usageRows
     */
    public function downloadUsage(array $usageRows, string $departmentFilter = ''): Response
    {
        $tableRows = [];
        foreach ($usageRows as $row) {
            $tableRows[] = [
                (string) ($row['department'] ?? ''),
                (string) ($row['leave_type'] ?? ''),
                (int) ($row['count'] ?? 0),
                $this->trunc3($row['total_days'] ?? 0),
            ];
        }

        return StyledXlsxExport::downloadResponse([
            'filename' => 'usage_' . now()->format('Y-m-d'),
            'sheet_title' => 'Leave Usage',
            'info_title' => 'Report Information',
            'employee_info_rows' => $this->reportInfoRows('Leave Usage Report', $departmentFilter),
            'table_title' => 'LEAVE USAGE REPORT',
            'table_headers' => ['Department', 'Leave Type', 'Request Count', 'Total Days'],
            'table_rows' => $tableRows,
            'column_widths' => [24, 28, 16, 14],
        ]);
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function reportInfoRows(string $reportTitle, string $departmentFilter = ''): array
    {
        return [
            [
                ['ref' => 'A', 'role' => 'label', 'value' => 'Report'],
                ['ref' => 'B', 'role' => 'value', 'value' => $reportTitle],
                ['ref' => 'D', 'role' => 'label', 'value' => 'Generated'],
                ['ref' => 'E', 'role' => 'value', 'value' => now()->format('F j, Y g:i A')],
            ],
            [
                ['ref' => 'A', 'role' => 'label', 'value' => 'Department'],
                ['ref' => 'B', 'role' => 'value', 'value' => $departmentFilter !== '' ? $departmentFilter : 'All Departments'],
            ],
        ];
    }

    private function trunc3(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;
        $truncated = $number >= 0 ? floor($number * 1000) / 1000 : ceil($number * 1000) / 1000;
        return number_format($truncated, 3, '.', '');
    }
}
