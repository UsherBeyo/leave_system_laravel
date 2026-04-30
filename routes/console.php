<?php

use App\Services\MonthlyAccrualService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('accruals:run-automatic {--force : Run even when disabled or not yet due}', function () {
    $result = app(MonthlyAccrualService::class)->runAutomaticIfDue(null, (bool) $this->option('force'));
    $this->info($result['message']);

    return $result['ran'] ? self::SUCCESS : self::SUCCESS;
})->purpose('Run automatic month-end leave accrual when enabled and due');

Schedule::command('accruals:run-automatic')->dailyAt('23:59');
