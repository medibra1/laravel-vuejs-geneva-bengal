<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CatStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCatRequest;
use App\Http\Requests\Admin\UpdateCatRequest;
use App\Http\Resources\CatResource;
use App\Models\Cat;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CatController extends Controller
{
    public function index(): Response
    {
        $cats = QueryBuilder::for(Cat::class)
            ->allowedFilters('name', 'type', AllowedFilter::exact('color_id'))
            ->with(['color', 'statuses', 'media'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // CatResource::collection($cats) would silently drop pagination
        // meta/links here: that wrapping only applies when a resource is
        // returned as the outermost HTTP response, not when embedded in
        // an Inertia props array. ->through() keeps the paginator itself
        // (which always serializes its meta) and just resource-shapes
        // each item.
        $cats->through(fn (Cat $cat) => CatResource::make($cat)->resolve());

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
            'cat' => CatResource::make($cat),
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

    /**
     * Only the store()/update() actions could add photos — there was no
     * way to remove a single bad one short of deleting the whole cat.
     */
    public function destroyPhoto(Cat $cat, Media $media): RedirectResponse
    {
        abort_unless($media->model_type === Cat::class && $media->model_id === $cat->id, 404);

        $media->delete();

        return back()->with('success', __('Photo deleted.'));
    }
}
