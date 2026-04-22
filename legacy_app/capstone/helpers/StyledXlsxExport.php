<?php

class StyledXlsxExport
{
    public static function outputWorkbook(array $config): void
    {
        $filename = $config['filename'] ?? 'export';
        $sheetTitle = self::sanitizeSheetTitle($config['sheet_title'] ?? 'Sheet1');
        $employeeInfoRows = $config['employee_info_rows'] ?? [];
        $tableTitle = (string)($config['table_title'] ?? 'DATA');
        $tableHeaders = $config['table_headers'] ?? [];
        $tableRows = $config['table_rows'] ?? [];
        $columnWidths = $config['column_widths'] ?? [];

        $sheetXml = self::buildWorksheetXml($employeeInfoRows, $tableTitle, $tableHeaders, $tableRows, $columnWidths);
        $files = [
            '[Content_Types].xml' => self::contentTypesXml(),
            '_rels/.rels' => self::rootRelsXml(),
            'docProps/app.xml' => self::appXml($sheetTitle),
            'docProps/core.xml' => self::coreXml($filename),
            'xl/workbook.xml' => self::workbookXml($sheetTitle),
            'xl/_rels/workbook.xml.rels' => self::workbookRelsXml(),
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        $xlsx = self::zipFiles($files);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
        exit();
    }

    private static function buildWorksheetXml(array $infoRows, string $tableTitle, array $headers, array $tableRows, array $columnWidths): string
    {
        $rowsXml = [];
        $mergeRefs = [];
        $maxCol = max(9, count($headers));
        $lastCol = self::colLetters($maxCol);
        $currentRow = 1;

        $rowsXml[] = self::rowXml($currentRow, [
            ['ref' => 'A' . $currentRow, 'value' => 'Employee Information', 'type' => 's', 'style' => 1],
        ]);
        $mergeRefs[] = 'A1:' . $lastCol . '1';
        $currentRow++;

        foreach ($infoRows as $infoRow) {
            $cells = [];
            foreach ($infoRow as $cell) {
                $ref = $cell['ref'] . $currentRow;
                $style = $cell['style'] ?? (($cell['role'] ?? 'value') === 'label' ? 2 : 3);
                $type = ($cell['type'] ?? 's');
                $cells[] = ['ref' => $ref, 'value' => $cell['value'] ?? '', 'type' => $type, 'style' => $style];
            }
            $rowsXml[] = self::rowXml($currentRow, $cells);
            $currentRow++;
        }

        $rowsXml[] = self::rowXml($currentRow, []);
        $currentRow++;

        $rowsXml[] = self::rowXml($currentRow, [
            ['ref' => 'A' . $currentRow, 'value' => $tableTitle, 'type' => 's', 'style' => 1],
        ]);
        $mergeRefs[] = 'A' . $currentRow . ':' . $lastCol . $currentRow;
        $currentRow++;

        $headerCells = [];
        foreach ($headers as $index => $header) {
            $col = self::colLetters($index + 1);
            $headerCells[] = ['ref' => $col . $currentRow, 'value' => $header, 'type' => 's', 'style' => 4];
        }
        if (!empty($headerCells)) {
            $rowsXml[] = self::rowXml($currentRow, $headerCells);
            $currentRow++;
        }

        foreach ($tableRows as $row) {
            $cells = [];
            foreach (array_values($row) as $index => $value) {
                $col = self::colLetters($index + 1);
                if ($value === null || $value === '') {
                    $cells[] = ['ref' => $col . $currentRow, 'value' => '', 'type' => 's', 'style' => 5];
                    continue;
                }
                if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value))) {
                    $cells[] = ['ref' => $col . $currentRow, 'value' => (string)$value, 'type' => 'n', 'style' => 6];
                } else {
                    $cells[] = ['ref' => $col . $currentRow, 'value' => (string)$value, 'type' => 's', 'style' => 5];
                }
            }
            $rowsXml[] = self::rowXml($currentRow, $cells);
            $currentRow++;
        }

        $colsXml = '';
        if (!empty($columnWidths)) {
            $parts = [];
            foreach ($columnWidths as $i => $width) {
                $idx = $i + 1;
                $parts[] = '<col min="' . $idx . '" max="' . $idx . '" width="' . self::xml((string)$width) . '" customWidth="1"/>';
            }
            $colsXml = '<cols>' . implode('', $parts) . '</cols>';
        }

        $mergeXml = '';
        if (!empty($mergeRefs)) {
            $mergeXml = '<mergeCells count="' . count($mergeRefs) . '">';
            foreach ($mergeRefs as $ref) {
                $mergeXml .= '<mergeCell ref="' . $ref . '"/>';
            }
            $mergeXml .= '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . $colsXml
            . '<sheetData>' . implode('', $rowsXml) . '</sheetData>'
            . $mergeXml
            . '</worksheet>';
    }

    private static function rowXml(int $rowNum, array $cells): string
    {
        $xml = '<row r="' . $rowNum . '">';
        foreach ($cells as $cell) {
            $ref = $cell['ref'];
            $style = (int)($cell['style'] ?? 0);
            $type = $cell['type'] ?? 's';
            $value = $cell['value'] ?? '';
            if ($type === 'n' && $value !== '') {
                $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . self::xml((string)$value) . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . self::xml((string)$value) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        return $xml;
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAEAEA"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function workbookXml(string $sheetTitle): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml($sheetTitle) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function coreXml(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::xml($title) . '</dc:title>'
            . '<dc:creator>IEP</dc:creator>'
            . '<cp:lastModifiedBy>IEP</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private static function appXml(string $sheetTitle): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Microsoft Excel</Application>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . self::xml($sheetTitle) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company></Company><AppVersion>16.0300</AppVersion>'
            . '</Properties>';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function sanitizeSheetTitle(string $title): string
    {
        $title = preg_replace('~[\\/?*\[\]:]~', '', $title);
        $title = trim($title);
        if ($title === '') {
            $title = 'Sheet1';
        }
        return substr($title, 0, 31);
    }

    private static function colLetters(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }
        return $letters;
    }

    private static function zipFiles(array $files): string
    {
        $data = '';
        $central = '';
        $offset = 0;
        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($content);
            if ($crc < 0) {
                $crc += 4294967296;
            }
            $compressed = gzdeflate($content);
            $compressedSize = strlen($compressed);
            $uncompressedSize = strlen($content);
            $nameLen = strlen($name);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $nameLen, 0);
            $data .= $localHeader . $name . $compressed;
            $centralHeader = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $nameLen, 0, 0, 0, 0, 32, $offset);
            $central .= $centralHeader . $name;
            $offset = strlen($data);
        }
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($data), 0);
        return $data . $central . $end;
    }
}
