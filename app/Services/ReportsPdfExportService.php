<?php

namespace App\Services;

use App\Models\Employee;
use Symfony\Component\HttpFoundation\Response;

class ReportsPdfExportService
{
    public function downloadBalance(iterable $employees, ?string $departmentFilter = null): Response
    {
        $headers = ['ID', 'First Name', 'Last Name', 'Department', 'Vacational Balance', 'Sick Balance', 'Force Balance'];
        $rows = [];

        foreach ($employees as $employee) {
            $rows[] = [
                (string) ($employee->id ?? ''),
                (string) ($employee->first_name ?? ''),
                (string) ($employee->last_name ?? ''),
                (string) ($employee->department ?: 'Unassigned'),
                $this->trunc3($employee->annual_balance ?? 0),
                $this->trunc3($employee->sick_balance ?? 0),
                $this->trunc3($employee->force_balance ?? 0),
            ];
        }

        return $this->downloadTable(
            title: 'Leave Balance Report',
            filename: 'balance_' . now()->format('Y-m-d') . '.pdf',
            headers: $headers,
            rows: $rows,
            metaRows: [
                ['Department', $departmentFilter ?: 'All Departments'],
                ['Generated', now()->format('F j, Y g:i A')],
            ],
            columnWidths: [5, 14, 14, 20, 16, 14, 14],
            landscape: true
        );
    }

    public function downloadUsage(array $usageRows, ?string $departmentFilter = null): Response
    {
        $headers = ['Department', 'Leave Type', 'Request Count', 'Total Days'];
        $rows = [];

        foreach ($usageRows as $row) {
            $rows[] = [
                (string) ($row['department'] ?? ''),
                (string) ($row['leave_type'] ?? ''),
                (string) ($row['count'] ?? 0),
                $this->trunc3($row['total_days'] ?? 0),
            ];
        }

        return $this->downloadTable(
            title: 'Leave Usage Report',
            filename: 'usage_' . now()->format('Y-m-d') . '.pdf',
            headers: $headers,
            rows: $rows,
            metaRows: [
                ['Department', $departmentFilter ?: 'All Departments'],
                ['Generated', now()->format('F j, Y g:i A')],
            ],
            columnWidths: [24, 32, 14, 14],
            landscape: true
        );
    }

    public function downloadLeaveCard(Employee $employee, array $leaveCardRows): Response
    {
        $headers = ['Date', 'Particulars', 'Vac Earned', 'Vac Deducted', 'Vac Balance', 'Sick Earned', 'Sick Deducted', 'Sick Balance', 'Status'];
        $rows = [];

        foreach ($leaveCardRows as $row) {
            $rows[] = [
                $this->formatDate($row['date'] ?? ''),
                (string) ($row['particulars'] ?? ''),
                (float) ($row['vac_earned'] ?? 0) != 0.0 ? $this->trunc3($row['vac_earned']) : '',
                (float) ($row['vac_deducted'] ?? 0) != 0.0 ? $this->trunc3($row['vac_deducted']) : '',
                ($row['vac_balance'] ?? '') === '' ? '' : $this->trunc3($row['vac_balance']),
                (float) ($row['sick_earned'] ?? 0) != 0.0 ? $this->trunc3($row['sick_earned']) : '',
                (float) ($row['sick_deducted'] ?? 0) != 0.0 ? $this->trunc3($row['sick_deducted']) : '',
                ($row['sick_balance'] ?? '') === '' ? '' : $this->trunc3($row['sick_balance']),
                (string) ($row['status'] ?? ''),
            ];
        }

        $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: $employee->fullName();

        return $this->downloadTable(
            title: 'Leave Card',
            filename: $this->safeFilename('Leave Card - ' . ($employeeName ?: 'Employee ' . $employee->id)) . '.pdf',
            headers: $headers,
            rows: $rows,
            metaRows: [
                ['Employee', $employeeName ?: 'Employee ' . $employee->id],
                ['Department', (string) ($employee->department ?: 'Unassigned')],
                ['Generated', now()->format('F j, Y g:i A')],
            ],
            columnWidths: [15, 26, 12, 13, 13, 12, 13, 13, 15],
            landscape: true
        );
    }

    private function downloadTable(string $title, string $filename, array $headers, array $rows, array $metaRows, array $columnWidths, bool $landscape = false): Response
    {
        $pdf = $this->buildPdf($title, $headers, $rows, $metaRows, $columnWidths, $landscape);
        $safeFilename = $this->safeFilename($filename, 'report.pdf');
        if (!str_ends_with(strtolower($safeFilename), '.pdf')) {
            $safeFilename .= '.pdf';
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function buildPdf(string $title, array $headers, array $rows, array $metaRows, array $columnWidths, bool $landscape): string
    {
        $pageWidth = $landscape ? 842.0 : 595.0;
        $pageHeight = $landscape ? 595.0 : 842.0;
        $margin = 28.0;
        $lineHeight = 12.0;
        $tableFontSize = 7.4;
        $titleFontSize = 15.0;
        $pageLineLimit = (int) floor(($pageHeight - 120) / $lineHeight);

        $tableLines = [];
        $tableLines[] = $this->fixedWidthLine($headers, $columnWidths);
        $tableLines[] = $this->separatorLine($columnWidths);

        foreach ($rows as $row) {
            $tableLines[] = $this->fixedWidthLine($row, $columnWidths);
        }

        if (count($rows) === 0) {
            $tableLines[] = 'No records found.';
        }

        $pages = [];
        $chunks = array_chunk($tableLines, max(1, $pageLineLimit));
        foreach ($chunks as $pageIndex => $lines) {
            $content = '';
            $y = $pageHeight - $margin;

            if ($pageIndex === 0) {
                $content .= $this->textCommand($margin, $y, $title, 'F2', $titleFontSize);
                $y -= 18;

                foreach ($metaRows as [$label, $value]) {
                    $content .= $this->textCommand($margin, $y, $label . ': ' . $value, 'F1', 9.0);
                    $y -= 12;
                }
                $y -= 8;
            } else {
                $content .= $this->textCommand($margin, $y, $title . ' (continued)', 'F2', 12.0);
                $y -= 20;
            }

            foreach ($lines as $lineIndex => $line) {
                $font = ($pageIndex === 0 && $lineIndex === 0) ? 'F2' : 'F1';
                $size = ($pageIndex === 0 && $lineIndex === 0) ? 7.6 : $tableFontSize;
                $content .= $this->textCommand($margin, $y, $line, $font, $size);
                $y -= $lineHeight;
            }

            $pages[] = $content;
        }

        return $this->assemblePdf($pages, $pageWidth, $pageHeight);
    }

    private function fixedWidthLine(array $cells, array $widths): string
    {
        $parts = [];
        foreach ($widths as $index => $width) {
            $value = $this->singleLine((string) ($cells[$index] ?? ''));
            $parts[] = str_pad($this->truncateText($value, $width), $width);
        }
        return implode(' | ', $parts);
    }

    private function separatorLine(array $widths): string
    {
        return implode('-+-', array_map(fn ($width) => str_repeat('-', (int) $width), $widths));
    }

    private function textCommand(float $x, float $y, string $text, string $font, float $size): string
    {
        return "BT /{$font} " . $this->num($size) . " Tf " . $this->num($x) . ' ' . $this->num($y) . ' Td (' . $this->pdfText($text) . ") Tj ET\n";
    }

    private function assemblePdf(array $pageContents, float $pageWidth, float $pageHeight): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageObjectNumbers = [];
        foreach ($pageContents as $content) {
            $pageObjectNumber = count($objects) + 1;
            $contentObjectNumber = $pageObjectNumber + 1;
            $pageObjectNumbers[] = $pageObjectNumber;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->num($pageWidth) . ' ' . $this->num($pageHeight) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObjectNumber . ' 0 R >>';
            $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        $objects[1] = '<< /Type /Pages /Count ' . count($pageObjectNumbers) . ' /Kids [' . implode(' ', array_map(fn ($number) => $number . ' 0 R', $pageObjectNumbers)) . '] >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        $text = strtr($text, [
            '—' => '-',
            '–' => '-',
            '’' => "'",
            '‘' => "'",
            '“' => '"',
            '”' => '"',
            '•' => '-',
        ]);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = $ascii;
        }

        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function singleLine(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        return preg_replace('/\s+/', ' ', trim($text)) ?? '';
    }

    private function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        if ($length <= 3) {
            return substr($text, 0, $length);
        }
        return substr($text, 0, $length - 3) . '...';
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

    private function safeFilename(string $filename, string $fallback = 'report.pdf'): string
    {
        $filename = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $filename);
        $filename = preg_replace('/\s+/', ' ', trim((string) $filename)) ?? '';
        return $filename !== '' ? $filename : $fallback;
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
