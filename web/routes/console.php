<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('radar:sync-lots')
    ->everyFifteenMinutes()
    ->timezone('America/Sao_Paulo');

Schedule::command('radar:dispatch-alerts --skip-sync')
    ->hourly()
    ->timezone('America/Sao_Paulo');

Schedule::command('radar:dispatch-auction-reminders')
    ->everyTenMinutes()
    ->timezone('America/Sao_Paulo');
