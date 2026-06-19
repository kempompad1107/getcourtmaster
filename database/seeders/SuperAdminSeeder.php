<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@courtmaster.app'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('K3mp0mp@dp!ckl3'),
                'user_type' => 'super_admin',
                'email_verified_at' => now(),
                'is_active' => true,
                'referral_code' => 'SUPER001',
            ]
        );

        $admin->assignRole('super_admin');
    }
}
