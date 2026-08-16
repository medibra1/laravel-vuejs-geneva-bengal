<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');
});

test('bare /admin redirects authenticated staff to the dashboard', function () {
    Role::findOrCreate('admin');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user)->get('/admin');

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('bare /admin redirects guests to login', function () {
    $response = $this->get('/admin');
    $response->assertRedirect(route('dashboard', absolute: false));

    // The redirect to /admin/dashboard is itself auth-protected — a guest
    // is bounced to /login on this second hop.
    $response = $this->get($response->headers->get('Location'));
    $response->assertRedirect('/login');
});
