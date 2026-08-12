<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\SharesHeroSlides;
use App\Models\Cat;
use App\Models\Litter;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LitterController extends Controller
{
    use SharesHeroSlides;

    /**
     * "Portées prévues" — only litters not yet born, soonest first. Past
     * litters belong in the cats/kittens listing once born, not here.
     */
    public function index(): Response
    {
        $litters = Litter::query()
            ->where('expected_date', '>=', Carbon::today())
            ->with(['sire.color', 'sire.media', 'dam.color', 'dam.media'])
            ->orderBy('expected_date')
            ->get()
            ->map(fn (Litter $litter) => [
                'id' => $litter->id,
                'expected_date' => $litter->expected_date?->toDateString(),
                'notes' => $litter->notes,
                'sire' => $this->parentSummary($litter->sire),
                'dam' => $this->parentSummary($litter->dam),
            ]);

        return Inertia::render('Public/PorteesPrevues', [
            'litters' => $litters,
            'heroSlides' => $this->heroSlides(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parentSummary(?Cat $cat): ?array
    {
        if (! $cat) {
            return null;
        }

        return [
            'id' => $cat->id,
            'slug' => $cat->slug,
            'name' => $cat->name,
            'color' => $cat->color?->name,
            'photo_url' => $cat->getFirstMediaUrl('photos') ?: null,
        ];
    }
}
