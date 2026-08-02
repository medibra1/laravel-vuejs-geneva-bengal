<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mcamara\LaravelLocalization\LaravelLocalization;
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
| mcamara/laravel-localization builds its locale-prefixed routes at
| application boot time from the incoming request, which the testing
| harness bootstraps before any request exists. Refreshing the app with
| the target locale forced via env lets route-dependent feature tests
| resolve /fr or /en correctly instead of 404ing. See vendor README
| "Testing" section for the upstream-documented pattern. Only the public
| routes (currently just "/") are locale-prefixed — see routes/web.php.
|
*/

function refreshApplicationWithLocale(string $locale): void
{
    /** @var TestCase $test */
    $test = test();

    $test->tearDown();
    putenv(LaravelLocalization::ENV_ROUTE_KEY.'='.$locale);
    $test->setUp();
}

pest()->afterEach(function () {
    putenv(LaravelLocalization::ENV_ROUTE_KEY);
});
