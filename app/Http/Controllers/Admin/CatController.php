<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CatStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCatRequest;
use App\Http\Requests\Admin\UpdateCatRequest;
use App\Models\Cat;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CatController extends Controller
{
    public function index(): Response
    {
        $cats = QueryBuilder::for(Cat::class)
            ->allowedFilters('name', 'type', AllowedFilter::exact('color_id'))
            ->with(['color', 'statuses'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Cats/Index', [
            'cats' => $cats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Cats/Form', [
            'colors' => Color::orderBy('name')->get(['id', 'name', 'hex_code']),
        ]);
    }

    public function store(StoreCatRequest $request): RedirectResponse
    {
        $cat = Cat::create($request->safe()->except(['photos', 'status']));

        $cat->setStatus($request->validated('status') ?? CatStatus::Available->value);

        foreach ($request->file('photos', []) as $photo) {
            $cat->addMedia($photo)->toMediaCollection('photos');
        }

        return redirect()->route('admin.cats.index')->with('success', __('Cat created.'));
    }

    public function edit(Cat $cat): Response
    {
        $cat->load(['color', 'secondColor', 'media', 'statuses']);

        return Inertia::render('Admin/Cats/Form', [
            'cat' => $cat,
            'colors' => Color::orderBy('name')->get(['id', 'name', 'hex_code']),
        ]);
    }

    public function update(UpdateCatRequest $request, Cat $cat): RedirectResponse
    {
        $cat->update($request->safe()->except(['photos', 'status']));

        $status = $request->validated('status');

        if ($status && $status !== $cat->status) {
            $cat->setStatus($status);
        }

        foreach ($request->file('photos', []) as $photo) {
            $cat->addMedia($photo)->toMediaCollection('photos');
        }

        return redirect()->route('admin.cats.index')->with('success', __('Cat updated.'));
    }

    public function destroy(Cat $cat): RedirectResponse
    {
        $cat->delete();

        return redirect()->route('admin.cats.index')->with('success', __('Cat deleted.'));
    }
}
