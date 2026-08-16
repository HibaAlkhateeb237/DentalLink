<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')
    ->weeklyOn(0, '14:00')
    ->timezone('Asia/Damascus')
    ->name('weekly-backup')
    ->withoutOverlapping()
    ->onOneServer();
