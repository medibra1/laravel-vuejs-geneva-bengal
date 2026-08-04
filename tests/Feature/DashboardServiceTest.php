<?php

use App\Enums\CatStatus;
use App\Enums\ContactStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Color;
use App\Models\ContactRequest;
use App\Models\Deposit;
use App\Services\DashboardService;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

function thisMonthPeriod(): CarbonPeriod
{
    return CarbonPeriod::create(now()->startOfMonth(), now()->endOfMonth());
}

it('counts only cats currently available in the KPI, regardless of history', function () {
    $available = Cat::factory()->create();
    $available->setStatus(CatStatus::Available->value);

    $adopted = Cat::factory()->create();
    $adopted->setStatus(CatStatus::Available->value);
    $adopted->setStatus(CatStatus::Adopted->value);

    $stats = (new DashboardService)->getStats(thisMonthPeriod());

    expect($stats['kpis']['available_cats'])->toBe(1);
});

it('counts adoptions from status history within the period, not the current status column', function () {
    $adoptedThisMonth = Cat::factory()->create();
    $adoptedThisMonth->setStatus(CatStatus::Adopted->value);

    $adoptedLastYear = Cat::factory()->create();
    $adoptedLastYear->setStatus(CatStatus::Adopted->value);
    $adoptedLastYear->statuses()->latest('id')->first()->forceFill(['created_at' => now()->subYear()])->save();

    $stats = (new DashboardService)->getStats(thisMonthPeriod());

    expect($stats['kpis']['adoptions_in_period'])->toBe(1);
});

it('sums only paid deposits inside the period for revenue', function () {
    Deposit::factory()->paid()->create(['amount' => 50000, 'paid_at' => now()]);
    Deposit::factory()->paid()->create(['amount' => 30000, 'paid_at' => now()->subYear()]);
    Deposit::factory()->create(['status' => DepositStatus::Pending, 'amount' => 99999]);

    $stats = (new DashboardService)->getStats(thisMonthPeriod());

    expect($stats['kpis']['deposit_revenue_in_period'])->toBe(50000);
});

it('counts only new contact requests as pending', function () {
    ContactRequest::factory()->create(['status' => ContactStatus::New]);
    ContactRequest::factory()->create(['status' => ContactStatus::New]);
    ContactRequest::factory()->create(['status' => ContactStatus::Processed]);

    $stats = (new DashboardService)->getStats(thisMonthPeriod());

    expect($stats['kpis']['pending_contact_requests'])->toBe(2);
});

it('builds a zero-filled monthly series for adoptions, not just months with activity', function () {
    $period = CarbonPeriod::create(Carbon::parse('2026-01-01'), Carbon::parse('2026-03-31'));

    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Adopted->value);
    $cat->statuses()->latest('id')->first()->forceFill(['created_at' => Carbon::parse('2026-02-15')])->save();

    $chart = (new DashboardService)->getStats($period)['charts']['adoptionsByMonth'];

    expect($chart['labels'])->toHaveCount(3);
    expect($chart['datasets'][0]['data'])->toBe([0, 1, 0]);
});

it('breaks cats down by their current status for the donut chart', function () {
    $available = Cat::factory()->create();
    $available->setStatus(CatStatus::Available->value);

    $pending = Cat::factory()->create();
    $pending->setStatus(CatStatus::Pending->value);

    $chart = (new DashboardService)->getStats(thisMonthPeriod())['charts']['catsByStatus'];

    expect($chart['labels'])->toBe(['Disponible', 'En attente', 'Adopté']);
    expect($chart['datasets'][0]['data'])->toBe([1, 1, 0]);
});

it('breaks cats down by color for the bar chart', function () {
    $silver = Color::factory()->create(['name' => 'Silver']);
    $brown = Color::factory()->create(['name' => 'Brown']);
    Cat::factory()->count(2)->create(['color_id' => $silver->id]);
    Cat::factory()->create(['color_id' => $brown->id]);

    $chart = (new DashboardService)->getStats(thisMonthPeriod())['charts']['catsByColor'];

    expect(array_combine($chart['labels'], $chart['datasets'][0]['data']))
        ->toBe(['Silver' => 2, 'Brown' => 1]);
});
