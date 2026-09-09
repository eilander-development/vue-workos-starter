<?php

test('user can change password from settings', function () {
    $user = \App\Models\User::factory()->create([
        'password' => 'old-password',
    ]);

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertOk();

    $this->post('/logout');
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'new-password',
    ])->assertRedirect('/');
});
