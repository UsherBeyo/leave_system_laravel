<?php
if (!function_exists("app_format_date")) {
    function app_format_date(?string $date): string {
        $date = trim((string)$date);
        if ($date === '' || $date === '0000-00-00') return '';
        $ts = strtotime($date);
        if ($ts === false) return $date;
        return date('F j, Y', $ts);
    }
}

if (!function_exists("app_format_datetime")) {
    function app_format_datetime(?string $datetime): string {
        $datetime = trim((string)$datetime);
        if ($datetime === '' || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') return '';
        $ts = strtotime($datetime);
        if ($ts === false) return $datetime;
        return date('F j, Y', $ts);
    }
}

if (!function_exists("app_format_date_range")) {
    function app_format_date_range(?string $start, ?string $end): string {
        $s = app_format_date($start);
        $e = app_format_date($end);
        if ($s !== '' && $e !== '') return $s . ' to ' . $e;
        return $s !== '' ? $s : $e;
    }
}

if (!function_exists("app_format_month_ref")) {
    function app_format_month_ref(?string $monthRef): string {
        $monthRef = trim((string)$monthRef);
        if ($monthRef === '') return '';
        if (preg_match('/^(\d{4})-(\d{2})$/', $monthRef)) {
            $ts = strtotime($monthRef . '-01');
            if ($ts !== false) return date('F Y', $ts);
        }
        return $monthRef;
    }
}
