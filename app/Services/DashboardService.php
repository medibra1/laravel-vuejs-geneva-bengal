<?php

namespace App\Services;

use App\Enums\CatStatus;
use App\Enums\ContactStatus;
use App\Enums\DepositStatus;
use App\Models\Cat;
use App\Models\Color;
use App\Models\ContactRequest;
use App\Models\Deposit;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\ModelStatus\Status;

/**
 * Pure aggregation, no HTTP concerns — Admin\DashboardController shapes
 * the request into a CarbonPeriod and renders the result. See CLAUDE.md.
 */
class DashboardService
{
    /**
     * @return array{kpis: array<string, int>, charts: array<string, array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}>}
     */
    public function getStats(CarbonPeriod $period): array
    {
        return [
            'kpis' => $this->kpis($period),
            'charts' => [
                'adoptionsByMonth' => $this->adoptionsByMonth($period),
                'depositRevenueByMonth' => $this->depositRevenueByMonth($period),
                'catsByStatus' => $this->catsByStatus(),
                'catsByColor' => $this->catsByColor(),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function kpis(CarbonPeriod $period): array
    {
        $cats = Cat::query()->with('statuses')->get();

        return [
            'available_cats' => $cats->filter(fn (Cat $cat) => $cat->status === CatStatus::Available->value)->count(),
            'adoptions_in_period' => $this->adoptedStatusesQuery($period)->count(),
            'deposit_revenue_in_period' => (int) Deposit::query()
                ->where('status', DepositStatus::Paid)
                ->whereBetween('paid_at', [$period->getStartDate(), $period->getEndDate()])
                ->sum('amount'),
            'pending_contact_requests' => ContactRequest::query()->where('status', ContactStatus::New)->count(),
        ];
    }

    /**
     * @return Builder<Status>
     */
    private function adoptedStatusesQuery(CarbonPeriod $period)
    {
        return Status::query()
            ->where('model_type', Cat::class)
            ->where('name', CatStatus::Adopted->value)
            ->whereBetween('created_at', [$period->getStartDate(), $period->getEndDate()]);
    }

    /**
     * Counted from the status history (when a Cat's status was set to
     * "adopte"), not the current status column — a Cat adopted last month
     * and somehow re-listed still shows in the month it was actually
     * adopted. This is the entire reason spatie/laravel-model-status is
     * used instead of a plain enum column. See CLAUDE.md.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int>}>}
     */
    private function adoptionsByMonth(CarbonPeriod $period): array
    {
        $counts = $this->adoptedStatusesQuery($period)
            ->get()
            ->groupBy(fn (Status $status) => $status->created_at->format('Y-m'))
            ->map->count();

        return $this->monthlyChartData($period, $counts->all(), 'Adoptions');
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>}
     */
    private function depositRevenueByMonth(CarbonPeriod $period): array
    {
        $revenueByMonth = Deposit::query()
            ->where('status', DepositStatus::Paid)
            ->whereBetween('paid_at', [$period->getStartDate(), $period->getEndDate()])
            ->get()
            ->groupBy(fn (Deposit $deposit) => $deposit->paid_at->format('Y-m'))
            ->map(fn (Collection $deposits) => $deposits->sum('amount') / 100);

        return $this->monthlyChartData($period, $revenueByMonth->all(), 'Revenus des acomptes (CHF)');
    }

    /**
     * Builds one bucket per calendar month in the period, even for months
     * with zero activity, so the chart reads as a continuous timeline
     * rather than skipping gaps.
     *
     * @param  array<string, int|float>  $countsByYearMonth  keyed "Y-m"
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function monthlyChartData(CarbonPeriod $period, array $countsByYearMonth, string $label): array
    {
        $months = CarbonPeriod::create(
            $period->getStartDate()->copy()->startOfMonth(),
            '1 month',
            $period->getEndDate()->copy()->startOfMonth(),
        );

        $labels = [];
        $data = [];

        foreach ($months as $month) {
            $labels[] = ucfirst($month->translatedFormat('M Y'));
            $data[] = $countsByYearMonth[$month->format('Y-m')] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => $label, 'data' => $data],
            ],
        ];
    }

    /**
     * Current snapshot, not period-filtered — "how many cats are in each
     * status right now" doesn't have a meaningful date range.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int>}>}
     */
    private function catsByStatus(): array
    {
        $labels = [
            CatStatus::Available->value => 'Disponible',
            CatStatus::Pending->value => 'En attente',
            CatStatus::Adopted->value => 'Adopté',
        ];

        $counts = Cat::query()->with('statuses')->get()
            ->groupBy(fn (Cat $cat) => $cat->status)
            ->map->count();

        return [
            'labels' => array_values($labels),
            'datasets' => [
                [
                    'label' => 'Chats par statut',
                    'data' => collect($labels)->keys()->map(fn (string $status) => $counts->get($status, 0))->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int>}>}
     */
    private function catsByColor(): array
    {
        $counts = Color::query()
            ->withCount('cats')
            ->orderByDesc('cats_count')
            ->get();

        return [
            'labels' => $counts->pluck('name')->all(),
            'datasets' => [
                ['label' => 'Chats par couleur', 'data' => $counts->pluck('cats_count')->all()],
            ],
        ];
    }
}
