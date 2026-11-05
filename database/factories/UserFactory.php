<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => fn () => static::defaultUnitId(),
            'user_status' => 'Active',
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Every user must belong to a unit. Reuse one if it exists, otherwise create a
     * minimal organization and unit (plain inserts, so model observers don't run).
     */
    protected static function defaultUnitId(): int
    {
        $existing = DB::table('units')->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $organizationId = DB::table('organizations')->insertGetId([
            'organizations_name' => 'Test Organization',
            'organization_abbreviation' => 'TST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('units')->insertGetId([
            'name' => 'Test Unit',
            'unit_abbreviation' => 'TU',
            'email' => 'unit@example.com',
            'phonenumber' => '0000000000',
            'locationname' => 'Head Office',
            'location_abbreviation' => 'HO',
            'officenumber' => '1',
            'organization_id' => $organizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
