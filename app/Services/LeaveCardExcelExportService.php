<?php

namespace App\Services;

use App\Models\Employee;
use App\Support\StyledXlsxExport;
use Symfony\Component\HttpFoundation\Response;

class LeaveCardExcelExportService
{
    public function download(Employee $employee, array $rows): Response
    {
        $employeeFullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
        if ($employeeFullName === '') {
            $employeeFullName = $employee->fullName() ?: 'Employee ' . $employee->id;
        }

        $leaveCardFilename = $this->safeExportFilename('Leave Card - ' . $employeeFullName);

        $employeeInfoRows = [
            [
                ['ref' => 'A', 'value' => 'Employee ID', 'role' => 'label'],
                ['ref' => 'B', 'value' => (string) $employee->id],
                ['ref' => 'C', 'value' => 'Name', 'role' => 'label'],
                ['ref' => 'D', 'value' => trim(($employee->first_name . ' ' . $employee->last_name) ?: $employee->fullName())],
                ['ref' => 'E', 'value' => 'Position', 'role' => 'label'],
                ['ref' => 'F', 'value' => (string) ($employee->position ?? '')],
                ['ref' => 'G', 'value' => 'Department', 'role' => 'label'],
                ['ref' => 'H', 'value' => (string) ($employee->department ?? '')],
                ['ref' => 'I', 'value' => ''],
            ],
            [
                ['ref' => 'A', 'value' => 'Status', 'role' => 'label'],
                ['ref' => 'B', 'value' => (string) ($employee->status ?? '')],
                ['ref' => 'C', 'value' => 'Civil Status', 'role' => 'label'],
                ['ref' => 'D', 'value' => (string) ($employee->civil_status ?? '')],
                ['ref' => 'E', 'value' => 'Entrance to Duty', 'role' => 'label'],
                ['ref' => 'F', 'value' => $this->dateString($employee->entrance_to_duty) ?: '0000-00-00'],
                ['ref' => 'G', 'value' => 'Unit', 'role' => 'label'],
                ['ref' => 'H', 'value' => (string) ($employee->unit ?? '')],
                ['ref' => 'I', 'value' => ''],
            ],
        ];

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $this->formatDate($row['period'] ?? ($row['date'] ?? '')),
                (string) ($row['particulars'] ?? ''),
                (float) ($row['vac_earned'] ?? 0) != 0.0 ? $this->trunc3($row['vac_earned']) : '',
                (float) ($row['vac_with_pay'] ?? 0) != 0.0 ? $this->trunc3($row['vac_with_pay']) : '',
                ($row['vac_balance'] ?? '') === '' ? '' : $this->trunc3($row['vac_balance']),
                (float) ($row['vac_without_pay'] ?? 0) != 0.0 ? $this->trunc3($row['vac_without_pay']) : '',
                (float) ($row['sick_earned'] ?? 0) != 0.0 ? $this->trunc3($row['sick_earned']) : '',
                (float) ($row['sick_with_pay'] ?? 0) != 0.0 ? $this->trunc3($row['sick_with_pay']) : '',
                ($row['sick_balance'] ?? '') === '' ? '' : $this->trunc3($row['sick_balance']),
                (float) ($row['sick_without_pay'] ?? 0) != 0.0 ? $this->trunc3($row['sick_without_pay']) : '',
                (string) ($row['remarks'] ?? ($row['status'] ?? '')),
            ];
        }

        return StyledXlsxExport::downloadResponse([
            'filename' => $leaveCardFilename,
            'sheet_title' => $leaveCardFilename,
            'employee_info_rows' => $employeeInfoRows,
            'table_title' => 'LEAVE CARD TRANSACTIONS',
            'table_headers' => [
                'Period',
                'Particulars',
                'Vac Earned',
                "Vac Absence\nUndertime\nW/ Pay",
                'Vac Balance',
                "Vac Absence\nUndertime\nW/o Pay",
                'Sick Earned',
                "Sick Absence\nUndertime\nW/ Pay",
                'Sick Balance',
                "Sick Absence\nUndertime\nW/o Pay",
                'Remarks',
            ],
            'table_rows' => $tableRows,
            'table_header_height' => 48,
            'column_widths' => [16, 28, 11, 15, 13, 15, 11, 15, 13, 15, 18],
        ]);
    }

    private function safeExportFilename(string $name, string $fallback = 'Leave Card'): string
    {
        $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $name);
        $name = preg_replace('/\s+/', ' ', trim((string) $name));
        return $name !== '' ? $name : $fallback;
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

    private function formatDate(mixed $date): string
    {
        $date = trim((string) $this->dateString($date));
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }
        $timestamp = strtotime($date);
        return $timestamp === false ? $date : date('F j, Y', $timestamp);
    }

    private function dateString(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        return trim((string) ($date ?? ''));
    }
}
