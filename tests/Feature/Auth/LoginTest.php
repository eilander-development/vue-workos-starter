<?php

use App\Models\User;

test('login page is visible for guests', function () {
    $this->get('/login')->assertOk();
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
