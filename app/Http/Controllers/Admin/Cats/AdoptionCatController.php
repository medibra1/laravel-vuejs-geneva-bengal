<?php

namespace App\Http\Controllers\Admin\Cats;

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cats\StoreAdoptionCatRequest;
use App\Http\Requests\Admin\Cats\UpdateAdoptionCatRequest;
use App\Http\Resources\CatResource;
use App\Models\Cat;
use App\Models\Color;
use App\Models\Owner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Kittens and cats up for adoption — everything except Breeder cats, which
 * live in BreederCatController instead. See CLAUDE.md: same Cat model,
 * split admin sections because the two have almost nothing in common in
 * the UI (status/price/availability here, litter links there).
 */
class AdoptionCatController extends Controller
{
    private const TYPES = [CatType::Kitten, CatType::Cat];

    public function index(): Response
    {
        $cats = QueryBuilder::for(Cat::query()->whereIn('type', self::TYPES))
            ->allowedFilters('name', 'type', AllowedFilter::exact('color_id'))
            ->with(['color', 'statuses', 'media'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $cats->through(fn (Cat $cat) => CatResource::make($cat)->resolve());

        return Inertia::render('Admin/Cats/Adoption/Index', [
            'cats' => $cats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Cats/Adoption/Form', [
            'colors' => Color::orderBy('name')->get(['id', 'name', 'hex_code']),
        ]);
    }

    public function store(StoreAdoptionCatRequest $request): RedirectResponse
    {
        // Forced server-side, never accepted from the form — same as
        // BreederCatController::store() does for CatType::Breeder. This
        // section only ever creates kittens; an existing "chat"-type
        // record is legacy data, still editable here, just not creatable.
        $cat = Cat::create([
            ...$request->safe()->except(['photos', 'status']),
            'type' => CatType::Kitten->value,
        ]);

        $cat->setStatus($request->validated('status') ?? CatStatus::Available->value);

        foreach ($request->file('photos', []) as $photo) {
            $cat->addMedia($photo)->toMediaCollection('photos');
        }

        return redirect()->route('admin.cats.adoption.index')->with('success', __('Cat created.'));
    }

    public function edit(Cat $cat): Response
    {
        $this->ensureAdoptionType($cat);

        $cat->load([
            'color', 'secondColor', 'media', 'statuses',
            'deposits' => fn ($query) => $query->latest()->with('owner'),
        ]);

        return Inertia::render('Admin/Cats/Adoption/Form', [
            'cat' => CatResource::make($cat),
            'colors' => Color::orderBy('name')->get(['id', 'name', 'hex_code']),
            // For CatAdoptionPanel.vue's "finalize" owner picker — only
            // needed when a paid deposit has no owner_id yet, same as
            // Admin\DepositController::index()/create().
            'owners' => Owner::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email', 'phone']),
        ]);
    }

    public function update(UpdateAdoptionCatRequest $request, Cat $cat): RedirectResponse
    {
        $this->ensureAdoptionType($cat);

        $cat->update($request->safe()->except(['photos', 'status']));

        $status = $request->validated('status');

        if ($status && $status !== $cat->status) {
            $cat->setStatus($status);
        }

        foreach ($request->file('photos', []) as $photo) {
            $cat->addMedia($photo)->toMediaCollection('photos');
        }

        return redirect()->route('admin.cats.adoption.index')->with('success', __('Cat updated.'));
    }

    public function destroy(Cat $cat): RedirectResponse
    {
        $this->ensureAdoptionType($cat);

        $cat->delete();

        return redirect()->route('admin.cats.adoption.index')->with('success', __('Cat deleted.'));
    }

    public function destroyPhoto(Cat $cat, Media $media): RedirectResponse
    {
        $this->ensureAdoptionType($cat);

        abort_unless($media->model_type === Cat::class && $media->model_id === $cat->id, 404);

        $media->delete();

        return back()->with('success', __('Photo deleted.'));
    }

    /**
     * Guards against a Breeder cat's id being edited/updated/deleted
     * through the adoption section's URLs — the two sections' route model
     * binding alone can't tell them apart.
     */
    private function ensureAdoptionType(Cat $cat): void
    {
        abort_unless(in_array($cat->type, self::TYPES, true), 404);
    }
}
