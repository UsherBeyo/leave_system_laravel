<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveCalculatorService
{
    public function calculateDaysBreakdown(string $start, string $end): array
    {
        try { $startDate = Carbon::createFromFormat('Y-m-d', trim($start))->startOfDay(); $endDate = Carbon::createFromFormat('Y-m-d', trim($end))->startOfDay(); }
        catch (\Throwable $e) { return ['valid'=>false,'days'=>0,'calendar_days'=>0,'weekend_days'=>0,'holiday_days'=>0,'message'=>'Please provide valid start and end dates.']; }
        if ($endDate->lt($startDate)) return ['valid'=>false,'days'=>0,'calendar_days'=>0,'weekend_days'=>0,'holiday_days'=>0,'message'=>'End date cannot be earlier than start date.'];
        $holidaySet = DB::table('holidays')->whereBetween('holiday_date', [$startDate->toDateString(), $endDate->toDateString()])->pluck('holiday_date')->map(fn($d)=>(string)$d)->flip();
        $cursor=$startDate->copy(); $days=$calendar=$weekend=$holiday=0;
        while($cursor->lte($endDate)){ $calendar++; $isWeekend=$cursor->isWeekend(); $date=$cursor->toDateString(); $isHoliday=isset($holidaySet[$date]); if($isWeekend)$weekend++; if($isHoliday)$holiday++; if(!$isWeekend && !$isHoliday)$days++; $cursor->addDay(); }
        return ['valid'=>true,'days'=>$days,'calendar_days'=>$calendar,'weekend_days'=>$weekend,'holiday_days'=>$holiday,'message'=>$days>0?'':'The selected range contains no deductible working days.'];
    }
}
