<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(): Response
    {
        $galleries = Gallery::query()->with('media')->orderBy('position')->get();

        return Inertia::render('Public/Galerie', [
            'galleries' => GalleryResource::collection($galleries)->resolve(),
        ]);
    }
}
