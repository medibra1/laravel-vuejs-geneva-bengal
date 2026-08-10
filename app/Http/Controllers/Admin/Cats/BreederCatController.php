<?php

namespace App\Http\Controllers\Admin\Cats;

use App\Enums\CatType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cats\StoreBreederCatRequest;
use App\Http\Requests\Admin\Cats\UpdateBreederCatRequest;
use App\Http\Resources\CatResource;
use App\Models\Cat;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Breeding cats — sires and dams, not up for adoption. See CLAUDE.md and
 * AdoptionCatController's doc comment for why this is a separate admin
 * section rather than a shared list with a type column.
 */
class BreederCatController extends Controller
{
    public function index(): Response
    {
        // No `type`/`status` filters here, unlike AdoptionCatController:
        // the base query already scopes to CatType::Breeder, and breeders
        // never get a status (see CLAUDE.md — no price/status/availability
        // for this section at all).
        $cats = QueryBuilder::for(Cat::query()->where('type', CatType::Breeder))
            ->allowedFilters(
                'name',
                AllowedFilter::exact('color_id'),
                AllowedFilter::callback('search', fn ($query, $value) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$value}%")->orWhere('eye_color', 'like', "%{$value}%")
                )),
            )
            ->allowedSorts('name', 'created_at', 'birth_date')
            ->defaultSort('-created_at')
            ->withCount(['sireLitters', 'damLitters'])
            ->with(['color', 'media'])
            ->paginate(20)
            ->withQueryString();

        $cats->through(fn (Cat $cat) => CatResource::make($cat)->resolve());

        return Inertia::render('Admin/Cats/Breeders/Index', [
            'cats' => $cats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Cats/Breeders/Form', [
            'colors' => Color::orderBy('name')->get(['id', 'name', 'hex_code']),
        ]);
    }

    public function store(StoreBreederCatRequest $request): RedirectResponse
    {
        $cat = Cat::create([
            ...$request->safe()->except('photos'),
            'type' => CatType::Breeder->value,
        ]);

        foreach ($request->file('photos', []) as $photo) {
            $cat->addMedia($photo)->toMediaCollection('photos');
        }

        return redirect()->route('admin.cats.breeders.index')->with('success', __('Cat created.'));
    }

    public function edit(Cat $cat): Response
    {
        $this->ensureBreederType($cat);

        $cat->load(['color', 'secondColor', 'media']);

        return Inertia::render('Admin/Cats/Breeders/Form', [
            'cat' => CatResource::make($cat),
            'colors' => Color::orderBy('name')->get(['id', 'name', 'hex_code']),
        ]);
    }

    public function update(UpdateBreederCatRequest $request, Cat $cat): RedirectResponse
    {
        $this->ensureBreederType($cat);

        $cat->update($request->safe()->except('photos'));

        foreach ($request->file('photos', []) as $photo) {
            $cat->addMedia($photo)->toMediaCollection('photos');
        }

        return redirect()->route('admin.cats.breeders.index')->with('success', __('Cat updated.'));
    }

    public function destroy(Cat $cat): RedirectResponse
    {
        $this->ensureBreederType($cat);

        $cat->delete();

        return redirect()->route('admin.cats.breeders.index')->with('success', __('Cat deleted.'));
    }

    public function destroyPhoto(Cat $cat, Media $media): RedirectResponse
    {
        $this->ensureBreederType($cat);

        abort_unless($media->model_type === Cat::class && $media->model_id === $cat->id, 404);

        $media->delete();

        return back()->with('success', __('Photo deleted.'));
    }

    /**
     * Guards against an adoption cat's id being edited/updated/deleted
     * through the breeders section's URLs — the two sections' route model
     * binding alone can't tell them apart.
     */
    private function ensureBreederType(Cat $cat): void
    {
        abort_unless($cat->type === CatType::Breeder, 404);
    }
}
