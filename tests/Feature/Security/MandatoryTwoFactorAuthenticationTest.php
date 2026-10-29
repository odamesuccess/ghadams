<?php

use App\Models\User;

function enableConfirmedTwoFactorFor(User $user): void
{
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
}

test('authenticated users without confirmed two factor are redirected to setup', function () {
    $user = User::factory()->create([
        'force_password_change' => false,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('two-factor.show'));
});

test('users with confirmed two factor can continue past mandatory two factor middleware', function () {
    $user = User::factory()->create([
        'force_password_change' => false,
    ]);

    enableConfirmedTwoFactorFor($user);

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertStatus(200);
});

test('mandatory two factor does not block the setup page', function () {
    $user = User::factory()->create([
        'force_password_change' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertOk();
});

test('two factor cannot be disabled when it is mandatory', function () {
    $user = User::factory()->create([
        'force_password_change' => false,
    ]);

    enableConfirmedTwoFactorFor($user);

    $this->actingAs($user)
        ->delete('/user/two-factor-authentication')
        ->assertForbidden();
});
