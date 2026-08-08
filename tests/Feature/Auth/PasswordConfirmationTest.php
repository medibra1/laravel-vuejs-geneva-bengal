<?php

use App\Models\User;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertStatus(200);
});

test('password can be confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});

// --- JSON mode: used by resources/js/Composables/useConfirmsPassword.ts
// to check/confirm without an Inertia visit navigating the page away. ----

test('reports unconfirmed over JSON when there is no prior confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/confirm-password');

    $response->assertOk();
    $response->assertJson(['confirmed' => false]);
});

test('reports confirmed over JSON within the password_timeout window', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson('/confirm-password');

    $response->assertOk();
    $response->assertJson(['confirmed' => true]);
});

test('reports unconfirmed over JSON once the password_timeout window has passed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time() - config('auth.password_timeout') - 1])
        ->getJson('/confirm-password');

    $response->assertOk();
    $response->assertJson(['confirmed' => false]);
});

test('confirming the password over JSON returns confirmed instead of redirecting', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertOk();
    $response->assertJson(['confirmed' => true]);
});

test('an invalid password over JSON returns a 422 with a password error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
});
