<?php

/*
|--------------------------------------------------------------------------
| Test bootstrap — pin the test environment before Laravel reads its env
|--------------------------------------------------------------------------
|
| docker-compose injects APP_ENV, DB_, QUEUE_, MAIL_ and STRIPE_ variables
| as real container environment variables so a fresh `docker compose up`
| works without manual setup. Those land in $_SERVER/$_ENV, and Laravel's
| repository reads $_SERVER *first* (ServerConst/EnvConst adapters, then
| Putenv) and is immutable once built.
|
| PHPUnit's <env> — even with force="true" — only writes putenv()/$_ENV,
| which loses to $_SERVER. So every phpunit.xml value that docker-compose
| also defines was silently ignored, with two consequences:
|
|   - DB_CONNECTION stayed "mysql", so the RefreshDatabase-backed Feature
|     suite truncated the MySQL *development* database on every run.
|   - APP_ENV stayed "local", so PreventRequestForgery kept enforcing CSRF
|     (runningUnitTests() was false) and every POST test failed with 419.
|
| Overwriting the superglobals here — before vendor/autoload.php, and
| therefore before any env() call builds the repository — is what actually
| makes these settings take effect. Keep new test-only env here, not in
| phpunit.xml, for anything docker-compose might also set.
|
*/

$testEnvironment = [
    // Must be "testing": several framework middlewares (notably CSRF
    // protection) branch on runningUnitTests().
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',

    // Isolated in-memory database — never the MySQL dev database.
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'DB_HOST' => '',
    'DB_PORT' => '',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',

    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',

    // Fake, not a real Stripe secret: StripeClient's constructor rejects
    // an empty string outright, and it's built eagerly by
    // AppServiceProvider's PaymentGateway binding on every request that
    // type-hints the interface — including tests that never call the
    // Stripe API. Forced over the container's real sk_test_ key so the
    // suite can never reach Stripe.
    'STRIPE_KEY' => 'pk_test_fake_key_for_test_suite',
    'STRIPE_SECRET' => 'sk_test_fake_key_for_test_suite',
    'STRIPE_WEBHOOK_SECRET' => 'whsec_fake_key_for_test_suite',

    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($testEnvironment as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
