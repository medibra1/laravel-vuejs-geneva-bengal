<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        $pages = Page::query()->orderBy('menu_group')->orderBy('order')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Pages/Index', [
            'pages' => $pages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Form');
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        Page::create($request->validated());

        return redirect()->route('admin.pages.index')->with('success', __('Page created.'));
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Form', [
            'page' => $this->transform($page),
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $page->update($request->validated());

        return redirect()->route('admin.pages.index')->with('success', __('Page updated.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', __('Page deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Page $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'menu_group' => $page->menu_group,
            'order' => $page->order,
            'title' => $page->getTranslations('title'),
            'body' => $page->getTranslations('body'),
            'meta_title' => $page->getTranslations('meta_title'),
            'meta_description' => $page->getTranslations('meta_description'),
            'is_published' => $page->is_published,
        ];
    }
}
