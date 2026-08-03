<?php

namespace App\Http\Controllers\Public;

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatResource;
use App\Models\Cat;
use Inertia\Inertia;
use Inertia\Response;

class CatController extends Controller
{
    /**
     * List available kittens (excludes cats already marked as adopted).
     */
    public function index(): Response
    {
        $cats = Cat::query()
            ->where('type', CatType::Kitten)
            ->with(['color', 'secondColor', 'media', 'statuses'])
            ->get()
            ->reject(fn (Cat $cat) => $cat->status === CatStatus::Adopted->value)
            ->values();

        return Inertia::render('Public/ChatonsDisponibles', [
            'cats' => CatResource::collection($cats),
        ]);
    }

    public function show(Cat $cat): Response
    {
        $cat->load(['color', 'secondColor', 'media', 'statuses']);

        return Inertia::render('Public/ChatonDetail', [
            'cat' => CatResource::make($cat),
        ]);
    }
}
