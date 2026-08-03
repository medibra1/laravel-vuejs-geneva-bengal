<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PageController extends Controller
{
    /**
     * CMS page by slug. Deliberately NOT a route-model-bound wildcard
     * (`/{page:slug}`): when mcamara/laravel-localization's boot-time
     * locale resolution comes up empty (happens outside a real /fr or
     * /en request — e.g. console, or any test that doesn't force a
     * locale), the locale group's prefix collapses to nothing, and a
     * wildcard segment route would then greedily match literally any
     * single-segment path — /login, /register, /profile included —
     * before Laravel ever reaches those real routes. Explicit literal
     * routes (see routes/web.php) can't have that problem: they only
     * ever match their own exact string.
     */
    public function show(string $slug): Response
    {
        $page = Page::query()->where('slug', $slug)->firstOrFail();

        abort_if(! $page->is_published, SymfonyResponse::HTTP_NOT_FOUND);

        $props = [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'body' => $page->body,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ],
        ];

        if ($page->slug === 'a-propos') {
            $props['testimonials'] = Testimonial::query()
                ->where('is_published', true)
                ->orderBy('order')
                ->get(['id', 'author_name', 'quote', 'rating']);
        }

        if ($page->slug === 'contact') {
            $props['settings'] = [
                'address' => SiteSetting::get('address'),
                'social_facebook' => SiteSetting::get('social_facebook'),
                'social_instagram' => SiteSetting::get('social_instagram'),
                'social_youtube' => SiteSetting::get('social_youtube'),
                'social_pinterest' => SiteSetting::get('social_pinterest'),
            ];
        }

        return Inertia::render('Public/Page', $props);
    }
}
