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
     * List available kittens (excludes cats already marked as adopted).
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
            ->reject(fn (Cat $cat) => $cat->status === CatStatus::Adopted->value)
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
}
