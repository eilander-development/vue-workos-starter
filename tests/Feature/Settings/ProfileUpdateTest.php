<?php

use App\Models\User;

test('legacy profile settings redirect to spa', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertRedirect('/instellingen/profiel');
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('user.name', 'Updated Name');

    expect($user->fresh()->name)->toBe('Updated Name');
});

test('user can delete their account', function () {
    $this->markTestSkipped('Account deletion triggers external WorkOS API call and requires integration test setup.');
});
