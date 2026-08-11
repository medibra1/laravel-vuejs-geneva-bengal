<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // backend.yml (Pest) and frontend.yml (npm run build) are
        // deliberately decoupled CI jobs — backend tests must not depend
        // on public/build/manifest.json existing.
        $this->withoutVite();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Locale-aware requests
|--------------------------------------------------------------------------
|
| No-op kept for call-site compatibility across ~13 Feature test files.
| The old mcamara/laravel-localization package built its locale-prefixed
| routes at application boot time from the incoming request, which the
| testing harness bootstraps before any request exists — this helper used
| to force-rebuild the app with the target locale via env so /fr or /en
| would resolve instead of 404ing.
|
| niels-numbers/laravel-localizer registers both locale variants
| (with_locale.*, without_locale.*) as static routes once, unconditionally,
| at boot — every test already visits a literal path like $this->get('/fr/...')
| rather than route('home'), so /fr and /en resolve correctly with no
| per-test setup at all. Left in place rather than touched across every
| call site in the same pass as the package swap.
|
*/

function refreshApplicationWithLocale(string $locale): void
{
    //
}
