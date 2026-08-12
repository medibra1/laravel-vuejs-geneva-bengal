<?php

namespace App\Http\Controllers\Public;

use App\Enums\GalleryType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\SharesHeroSlides;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    use SharesHeroSlides;

    public function index(): Response
    {
        // Explicitly scoped to Gallery — the galleries table also now holds
        // hero slider slides and homepage social tiles (see Admin\GalleryController),
        // which this public gallery page must never mix in.
        $galleries = Gallery::query()->ofType(GalleryType::Gallery)->with('media')->orderBy('position')->get();

        return Inertia::render('Public/Galerie', [
            'galleries' => GalleryResource::collection($galleries)->resolve(),
            'heroSlides' => $this->heroSlides(),
        ]);
    }
}
