<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('radar:sync-lots')
    ->dailyAt('05:20')
    ->timezone('America/Sao_Paulo');

Schedule::command('radar:dispatch-alerts --skip-sync')
    ->dailyAt('05:30')
    ->timezone('America/Sao_Paulo');

Schedule::command('radar:dispatch-auction-reminders')
    ->everyTenMinutes()
    ->timezone('America/Sao_Paulo');
