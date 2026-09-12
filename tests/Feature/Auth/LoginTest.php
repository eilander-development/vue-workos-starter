<?php

use App\Models\User;

test('login page is visible for guests', function () {
    $this->get('/login')->assertOk();
});

test('lege database toont setup in plaats van login', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Nog geen account')
        ->assertSee('Backup zetten')
        ->assertDontSee('Inloggen');
});

test('login form verschijnt zodra er een gebruiker is', function () {
    User::factory()->create();

    $this->get('/login')
        ->assertOk()
        ->assertSee('Inloggen')
        ->assertDontSee('Backup zetten');
});

test('user can log in with database credentials', function () {
    $user = User::factory()->create([
        'email' => 'mark@example.test',
        'password' => 'secret-pass',
    ]);

    $this->post('/login', [
        'email' => 'mark@example.test',
        'password' => 'secret-pass',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('invalid password is rejected', function () {
    User::factory()->create([
        'email' => 'mark@example.test',
        'password' => 'secret-pass',
    ]);

    $this->post('/login', [
        'email' => 'mark@example.test',
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
