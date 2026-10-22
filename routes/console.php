<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Log API lebih dari 90 hari dibersihkan setiap hari.
Schedule::command('model:prune', ['--model' => [\App\Models\ApiRequestLog::class]])->daily();
