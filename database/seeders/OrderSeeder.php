<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $targetOrderCount = 50;
        $statusCycle = ['pending', 'accepted', 'in_progress', 'completed', 'delivered'];
        $priorityCycle = ['normal', 'normal', 'urgent'];
        $orderTypeCycle = ['digital', 'physical', 'hybrid'];

        $labIds = Lab::query()->orderBy('id')->limit(5)->pluck('id')->values();

        if ($labIds->isEmpty()) {
            return;
        }

        $userIds = User::query()->orderBy('id')->pluck('id')->values();

        if ($userIds->isEmpty()) {
            $userIds = User::factory()->count(5)->create()->pluck('id')->values();
        }

        Order::query()->delete();

        $distribution = [14, 12, 10, 8, 6];
        $records = [];
        $createdAt = now();
        $userCount = $userIds->count();
        $globalIndex = 0;

        foreach ($labIds as $labIndex => $labId) {
            $ordersForCurrentLab = $distribution[$labIndex] ?? 0;

            for ($index = 0; $index < $ordersForCurrentLab; $index++) {
                $records[] = [
                    'user_id' => $userIds[$globalIndex % $userCount],
                    'lab_id' => $labId,
                    'qr_code' => (string) Str::uuid(),
                    'priority' => $priorityCycle[$globalIndex % count($priorityCycle)],
                    'status' => $statusCycle[$globalIndex % count($statusCycle)],
                    'order_type' => $orderTypeCycle[$globalIndex % count($orderTypeCycle)],
                    'notes' => null,
                    'price' => 0,
                    'remaining_amount' => 0,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $globalIndex++;
            }
        }

        foreach (array_chunk($records, 50) as $chunk) {
            Order::query()->insert($chunk);
        }

        if ($globalIndex < $targetOrderCount) {
            $fallbackLabId = $labIds->last();

            while ($globalIndex < $targetOrderCount) {
                Order::query()->create([
                    'user_id' => $userIds[$globalIndex % $userCount],
                    'lab_id' => $fallbackLabId,
                    'qr_code' => (string) Str::uuid(),
                    'priority' => $priorityCycle[$globalIndex % count($priorityCycle)],
                    'status' => $statusCycle[$globalIndex % count($statusCycle)],
                    'order_type' => $orderTypeCycle[$globalIndex % count($orderTypeCycle)],
                    'notes' => null,
                    'price' => 0,
                    'remaining_amount' => 0,
                ]);

                $globalIndex++;
            }
        }
    }
}
