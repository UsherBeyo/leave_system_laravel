<?php

namespace App\Support;

use Carbon\Carbon;

class LeaveFormat
{
    public static function date(?string $value): string
    {
        if (!$value) return '—';
        try {
            return Carbon::parse($value)->format('M d, Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public static function dateRange(?string $start, ?string $end): string
    {
        if (!$start && !$end) return '—';
        if ($start && $end && $start === $end) {
            return self::date($start);
        }
        if ($start && $end) {
            return self::date($start).' - '.self::date($end);
        }
        return self::date($start ?: $end);
    }

    public static function days($value): string
    {
        return number_format((float) $value, 3);
    }

    public static function statusLabel(?string $status, ?string $workflow = null): string
    {
        $status = strtolower(trim((string) $status));
        $workflow = strtolower(trim((string) $workflow));

        return match (true) {
            $workflow === 'pending_personnel' => 'Pending Personnel',
            $workflow === 'pending_department_head' => 'Pending Dept Head',
            $workflow === 'finalized', $status === 'approved' => 'Approved',
            str_contains($workflow, 'rejected'), $status === 'rejected' => 'Rejected',
            default => ucfirst($status ?: 'pending'),
        };
    }

    public static function statusClass(?string $status, ?string $workflow = null): string
    {
        $status = strtolower(trim((string) $status));
        $workflow = strtolower(trim((string) $workflow));

        return match (true) {
            $workflow === 'finalized', $status === 'approved' => 'badge-approved',
            str_contains($workflow, 'rejected'), $status === 'rejected' => 'badge-rejected',
            default => 'badge-pending',
        };
    }
}
