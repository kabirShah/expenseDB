<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\RoutineSchedulerService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('recurring:process')->daily();
Schedule::call(function () {
    app(RoutineSchedulerService::class)->run();
})->daily();
Schedule::command('budgets:check-alerts')->hourly();
Schedule::command('aa:sync-transactions')->daily();
