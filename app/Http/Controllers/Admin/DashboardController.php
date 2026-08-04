<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): Response
    {
        $period = $this->resolvePeriod($request);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $dashboard->getStats($period),
            'period' => $this->periodProp($period),
        ]);
    }

    /**
     * Lightweight JSON endpoint the period filter hits on change — not a
     * full Inertia page visit, so switching presets/custom ranges stays
     * fluid. See CLAUDE.md.
     */
    public function stats(Request $request, DashboardService $dashboard): JsonResponse
    {
        $period = $this->resolvePeriod($request);

        return response()->json([
            'stats' => $dashboard->getStats($period),
            'period' => $this->periodProp($period),
        ]);
    }

    /**
     * @return array{from: string, to: string}
     */
    private function periodProp(CarbonPeriod $period): array
    {
        return [
            'from' => $period->getStartDate()->toDateString(),
            'to' => $period->getEndDate()->toDateString(),
        ];
    }

    private function resolvePeriod(Request $request): CarbonPeriod
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return CarbonPeriod::create($from, $to);
    }
}
