<?php

class SimpleXlsxExport
{
    private static function xmlEscape($value): string
    {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function colName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }
        return $name;
    }

    private static function isNumericCell($value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }
        if (preg_match('/^0\d+$/', $trimmed)) {
            return false;
        }
        return preg_match('/^-?\d+(\.\d+)?$/', $trimmed) === 1;
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD3D3D3"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE0E0E0"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0 default
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 1 section
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 2 table header
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/></xf>' // 3 label
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/></xf>' // 4 bordered text
            . '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"/></xf>' // 5 number 0.00
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"/></xf>' // 6 text border
            . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"/></xf>' // 7 soft fill
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function buildSheetXml(array $rows, array $options = []): string
    {
        $styleMap = $options['style_map'] ?? [];
        $mergeCells = $options['merge_cells'] ?? [];
        $columnWidths = $options['column_widths'] ?? [];
        $freeze = $options['freeze'] ?? null;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if (!empty($columnWidths)) {
            $xml .= '<cols>';
            foreach ($columnWidths as $index => $width) {
                $i = $index + 1;
                $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . ((float)$width) . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetViews><sheetView workbookViewId="0">';
        if ($freeze && !empty($freeze['cell'])) {
            $xml .= '<pane state="frozen" topLeftCell="' . self::xmlEscape($freeze['cell']) . '" activePane="bottomLeft" ySplit="' . ((int)($freeze['rows'] ?? 1)) . '"/>';
        }
        $xml .= '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $r = $rowIndex + 1;
            $xml .= '<row r="' . $r . '">';
            $colIndex = 1;
            foreach ($row as $cellValue) {
                $ref = self::colName($colIndex) . $r;
                $styleId = $styleMap[$ref] ?? null;
                $rowStyle = $styleMap['row:' . $r] ?? null;
                if ($styleId === null && $rowStyle !== null) {
                    $styleId = $rowStyle;
                }
                $styleAttr = $styleId !== null ? ' s="' . ((int)$styleId) . '"' : '';

                if ($cellValue === null || $cellValue === '') {
                    $xml .= '<c r="' . $ref . '"' . $styleAttr . ' t="inlineStr"><is><t></t></is></c>';
                } elseif (self::isNumericCell($cellValue) && (($styleId ?? 0) !== 6)) {
                    $xml .= '<c r="' . $ref . '"' . $styleAttr . '><v>' . self::xmlEscape($cellValue) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '"' . $styleAttr . ' t="inlineStr"><is><t>' . self::xmlEscape($cellValue) . '</t></is></c>';
                }
                $colIndex++;
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        if (!empty($mergeCells)) {
            $xml .= '<mergeCells count="' . count($mergeCells) . '">';
            foreach ($mergeCells as $range) {
                $xml .= '<mergeCell ref="' . self::xmlEscape($range) . '"/>';
            }
            $xml .= '</mergeCells>';
        }
        $xml .= '</worksheet>';
        return $xml;
    }

    private static function outputCsvFallback(string $filename, array $rows): void
    {
        $csvName = preg_replace('/\.xlsx$/i', '.csv', $filename);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csvName . '"');
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    public static function download(string $filename, array $rows, string $sheetName = 'Sheet1', array $options = []): void
    {
        if (!class_exists('ZipArchive')) {
            self::outputCsvFallback($filename, $rows);
        }

        $filename = preg_replace('/\.xlsx$/i', '', $filename) . '.xlsx';
        $sheetName = trim($sheetName) !== '' ? trim($sheetName) : 'Sheet1';
        $sheetName = preg_replace('/[\\\/?*\[\]:]/', '', $sheetName);
        if ($sheetName === '') {
            $sheetName = 'Sheet1';
        }
        $sheetName = mb_substr($sheetName, 0, 31);

        $sheetXml = self::buildSheetXml($rows, $options);
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $tempFile = tempnam(sys_get_temp_dir(), 'lsx_');
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            self::outputCsvFallback($filename, $rows);
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>');

        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>OpenAI</dc:creator><cp:lastModifiedBy>OpenAI</cp:lastModifiedBy></cp:coreProperties>');

        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Microsoft Excel</Application>'
            . '</Properties>');

        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: max-age=0');
        readfile($tempFile);
        @unlink($tempFile);
        exit;
    }
}
