<?php

use App\Jobs\ReconcilePendingDeposits;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every 15 minutes (not daily) — a Deposit whose Stripe webhook never
// arrives should be caught up quickly, not left "pending" for up to 24h.
// 15 minutes also matches the minimum interval of Infomaniak's shared
// hosting task scheduler that runs /cron/run in production — see
// DEPLOY.md §4.
Schedule::job(new ReconcilePendingDeposits)->everyFifteenMinutes();
