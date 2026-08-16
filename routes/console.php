<?php

use App\Jobs\ReconcileCheckouts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every 15 minutes (not daily) — a PaymentIntent whose Stripe webhook
// never arrived, or a paid Deposit whose confirmation email failed to
// send, should both be caught up quickly. 15 minutes also matches the
// minimum interval of Infomaniak's shared hosting task scheduler that
// runs /cron/run in production — see DEPLOY.md §4.
Schedule::job(new ReconcileCheckouts)->everyFifteenMinutes();

// Prevents the activity_log table (see Admin\ActivityLogController) from
// growing forever — config('activitylog.delete_records_older_than_days')
// is 365 by default, this is just what actually enforces it. Monthly is
// plenty: unlike ReconcileCheckouts, nothing downstream depends on this
// running promptly. --force skips ConfirmableTrait's interactive prompt,
// which would otherwise block every run in production.
Schedule::command('activitylog:clean --force')->monthly();
