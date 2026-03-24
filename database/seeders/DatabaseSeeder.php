<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = trim((string) env('BLOGFUEL_ADMIN_EMAIL', 'admin@example.com'));
        $adminPassword = (string) env('BLOGFUEL_ADMIN_PASSWORD', 'change-me-admin-password');

        User::query()
            ->where('email', 'test@example.com')
            ->delete();

        $admin = User::updateOrCreate([
            'name' => 'admin',
        ], [
            'email' => $adminEmail,
            'password' => $adminPassword,
            'generated_posts_count' => 0,
        ]);

        $admin->forceFill([
            'email_verified_at' => now(),
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'stripe_price_id' => null,
            'subscription_status' => null,
            'subscription_plan' => null,
            'subscription_ends_at' => null,
        ])->save();
    }
}
