<?php

namespace App\Http\Controllers\Public;

use App\Enums\CatType;
use App\Http\Controllers\Controller;
use App\Models\Cat;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL as UrlFacade;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn () => $this->build()->render());

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    private function build(): Sitemap
    {
        $sitemap = Sitemap::create();
        $locales = array_keys(LaravelLocalization::getSupportedLocales());

        foreach (['', '/chatons-disponibles', '/a-propos', '/contact', '/galerie', '/nos-chats-reproducteurs', '/portees-prevues'] as $path) {
            $this->addLocalizedEntry($sitemap, $path, $locales);
        }

        Cat::query()
            ->whereIn('type', [CatType::Kitten, CatType::Breeder])
            ->get(['slug'])
            ->each(fn (Cat $cat) => $this->addLocalizedEntry($sitemap, "/chatons-disponibles/{$cat->slug}", $locales));

        Page::query()
            ->where('is_published', true)
            ->whereNotIn('slug', ['a-propos', 'contact'])
            ->get(['slug'])
            ->each(fn (Page $page) => $this->addLocalizedEntry($sitemap, "/pages/{$page->slug}", $locales));

        return $sitemap;
    }

    /**
     * One <url> entry per locale for the given path, each cross-linking to
     * the others via hreflang alternates — routes/web.php doesn't
     * translate paths per locale (just a plain /fr, /en prefix), so
     * building these directly is simpler and more reliable than going
     * through LaravelLocalization's current-request URL translation,
     * which assumes there's an actual page being visited right now.
     *
     * @param  array<int, string>  $locales
     */
    private function addLocalizedEntry(Sitemap $sitemap, string $path, array $locales): void
    {
        foreach ($locales as $locale) {
            $entry = Url::create(UrlFacade::to("/{$locale}{$path}"));

            foreach ($locales as $alternateLocale) {
                $entry->addAlternate(UrlFacade::to("/{$alternateLocale}{$path}"), $alternateLocale);
            }

            $sitemap->add($entry);
        }
    }
}
