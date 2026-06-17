<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id'        => null,
            'name'             => fake()->name(),
            'email'            => fake()->unique()->safeEmail(),
            'phone'            => fake()->optional()->phoneNumber(),
            'email_verified_at' => now(),
            'password'         => static::$password ??= Hash::make('password'),
            'remember_token'   => Str::random(10),
            'user_type'        => 'customer',
            'wallet_balance'   => 0,
            'referral_code'    => strtoupper(Str::random(8)),
            'is_active'        => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['user_type' => 'customer']);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['user_type' => 'staff']);
    }

    public function owner(): static
    {
        return $this->state(fn () => ['user_type' => 'business_owner']);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'user_type' => 'super_admin',
            'tenant_id' => null,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }

    public function withWallet(float $balance): static
    {
        return $this->state(fn () => ['wallet_balance' => $balance]);
    }
}
