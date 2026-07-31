<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Basic',
                'description' => 'Ideal for small dental labs just getting started. Includes essential features for order management and basic reporting.',
                'duration_days' => 30,
                'price' => 49.99,
                'is_active' => true,
            ],
            [
                'name' => 'Standard',
                'description' => 'Perfect for growing labs. Includes order management, task tracking, QR-based workflow, and priority support.',
                'duration_days' => 30,
                'price' => 99.99,
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'description' => 'For established labs needing advanced analytics, multi-department support, and dedicated account management.',
                'duration_days' => 30,
                'price' => 199.99,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Full-featured package for large labs. Unlimited departments, custom integrations, SLA guarantees, and 24/7 support.',
                'duration_days' => 30,
                'price' => 399.99,
                'is_active' => true,
            ],
            [
                'name' => 'Trial',
                'description' => 'Free 14-day trial with access to all Professional features. No credit card required.',
                'duration_days' => 14,
                'price' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($packages as $package) {
            Package::query()->updateOrCreate(
                ['name' => $package['name']],
                [
                    'description' => $package['description'],
                    'duration_days' => $package['duration_days'],
                    'price' => $package['price'],
                    'is_active' => $package['is_active'],
                ]
            );
        }
    }
}
