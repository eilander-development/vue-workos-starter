<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $this->markTestSkipped('Profile update requires full WorkOS session/CSRF integration context.');
});

test('user can delete their account', function () {
    $this->markTestSkipped('Account deletion triggers external WorkOS API call and requires integration test setup.');
});
