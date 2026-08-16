<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController extends Controller
{
    public function index(): Response
    {
        $activities = QueryBuilder::for(Activity::class)
            ->allowedFilters(
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('causer_id'),
                // Matches the from/to shape used by DepositController's own
                // period filter.
                AllowedFilter::callback(
                    'from',
                    fn ($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()),
                ),
                AllowedFilter::callback(
                    'to',
                    fn ($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()),
                ),
            )
            ->with('causer:id,name,email')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ActivityLog/Index', [
            'activities' => $activities,
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name'),
            'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'causers' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
