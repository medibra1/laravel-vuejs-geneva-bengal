<?php

namespace App\Http\Controllers\Public;

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatResource;
use App\Models\Cat;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatController extends Controller
{
    /**
     * List available kittens. Filtered explicitly on `disponible` — not
     * just "not adopted" — so a kitten currently `en_attente` (an
     * in-progress or paid deposit already holding it) doesn't show up as
     * bookable to a second visitor.
     * Optionally filtered to a single color via `?color_id=`, matching
     * either the primary or secondary color (Bengals are often two-tone).
     */
    public function index(Request $request): Response
    {
        $colorId = $request->integer('color_id') ?: null;

        $cats = Cat::query()
            ->where('type', CatType::Kitten)
            ->when($colorId, fn ($query) => $query->where(fn ($q) => $q
                ->where('color_id', $colorId)
                ->orWhere('second_color_id', $colorId)))
            ->with(['color', 'secondColor', 'media', 'statuses'])
            ->get()
            ->filter(fn (Cat $cat) => $cat->status === CatStatus::Available->value)
            ->values();

        return Inertia::render('Public/ChatonsDisponibles', [
            'cats' => CatResource::collection($cats),
            'colorId' => $colorId,
        ]);
    }

    public function show(Cat $cat): Response
    {
        $cat->load(['color', 'secondColor', 'media', 'statuses']);

        return Inertia::render('Public/ChatonDetail', [
            'cat' => CatResource::make($cat),
            'depositAmount' => SiteSetting::get('deposit_amount', 50000),
        ]);
    }

    /**
     * Breeding cats aren't for adoption — no status/price filtering, just
     * the showcase. ChatonDetail.vue (reused for the "en savoir plus" link)
     * hides its adoption-specific sections when cat.type isn't "chaton".
     */
    public function breeders(): Response
    {
        $cats = Cat::query()
            ->where('type', CatType::Breeder)
            ->with(['color', 'secondColor', 'media', 'statuses'])
            ->get();

        return Inertia::render('Public/Reproducteurs', [
            'cats' => CatResource::collection($cats),
        ]);
    }
}
