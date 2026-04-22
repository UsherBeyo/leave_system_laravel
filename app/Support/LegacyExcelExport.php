<?php

namespace App\Support;

use Illuminate\Http\Response;

class LegacyExcelExport
{
    /**
     * @param array<int,array<int|string,mixed>> $headers
     * @param array<int,array<int|string,mixed>> $rows
     * @param array<int,array<int,array{label:string,value:mixed}>> $infoRows
     */
    public static function table(string $filename, string $title, array $headers, array $rows, array $infoRows = []): Response
    {
        $html = '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:Arial,sans-serif;font-size:12px;color:#111827;}'
            . 'table{border-collapse:collapse;width:100%;}'
            . 'th,td{border:1px solid #cbd5e1;padding:6px 8px;vertical-align:top;}'
            . 'th{background:#e2e8f0;font-weight:700;}'
            . '.sheet-title{font-size:16px;font-weight:700;margin-bottom:12px;}'
            . '.meta td{border:1px solid #e5e7eb;}'
            . '.meta-label{background:#f8fafc;font-weight:700;width:160px;}'
            . '</style></head><body>';

        $html .= '<div class="sheet-title">' . e($title) . '</div>';

        if ($infoRows !== []) {
            $html .= '<table class="meta" style="margin-bottom:14px;">';
            foreach ($infoRows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $label = (string) ($cell['label'] ?? '');
                    $value = $cell['value'] ?? '';
                    $html .= '<td class="meta-label">' . e($label) . '</td>';
                    $html .= '<td>' . e((string) $value) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        $html .= '<table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . e((string) $header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $value = is_scalar($cell) || $cell === null ? (string) $cell : json_encode($cell);
                $html .= '<td>' . e($value) . '</td>';
            }
            $html .= '</tr>';
        }

        if ($rows === []) {
            $html .= '<tr><td colspan="' . max(1, count($headers)) . '">No records found.</td></tr>';
        }

        $html .= '</tbody></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
