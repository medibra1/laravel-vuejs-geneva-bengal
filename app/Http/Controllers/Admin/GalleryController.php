<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GalleryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryRequest;
use App\Http\Requests\Admin\UpdateGalleryRequest;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $this->resolveType($request->query('type'));

        $galleries = Gallery::query()
            ->ofType($type)
            ->with('media')
            ->orderBy('position')
            ->paginate(20)
            ->withQueryString();

        $galleries->through(fn (Gallery $gallery) => GalleryResource::make($gallery)->resolve());

        return Inertia::render('Admin/Galleries/Index', [
            'galleries' => $galleries,
            'type' => $type->value,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Galleries/Form', [
            'type' => $this->resolveType($request->query('type'))->value,
        ]);
    }

    public function store(StoreGalleryRequest $request): RedirectResponse
    {
        $gallery = Gallery::create($request->safe()->except('image'));

        $gallery->addMedia($request->file('image'))->toMediaCollection('image');

        return redirect()
            ->route('admin.galleries.index', ['type' => $gallery->type->value])
            ->with('success', 'Photo ajoutée.');
    }

    public function edit(Gallery $gallery): Response
    {
        return Inertia::render('Admin/Galleries/Form', [
            'gallery' => GalleryResource::make($gallery->load('media')),
            'type' => $gallery->type->value,
        ]);
    }

    public function update(UpdateGalleryRequest $request, Gallery $gallery): RedirectResponse
    {
        $gallery->update($request->safe()->except('image'));

        if ($request->hasFile('image')) {
            $gallery->addMedia($request->file('image'))->toMediaCollection('image');
        }

        return redirect()
            ->route('admin.galleries.index', ['type' => $gallery->type->value])
            ->with('success', 'Photo mise à jour.');
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $type = $gallery->type;

        $gallery->delete();

        return redirect()
            ->route('admin.galleries.index', ['type' => $type->value])
            ->with('success', 'Photo supprimée.');
    }

    private function resolveType(?string $value): GalleryType
    {
        return GalleryType::tryFrom($value ?? '') ?? GalleryType::Gallery;
    }
}
