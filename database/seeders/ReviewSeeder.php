<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::query()
            ->select(['id', 'user_id', 'lab_id'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        Review::query()->delete();

        $labIds = $orders->pluck('lab_id')->unique()->values();

        $ratingPatterns = [
            [5, 5, 4, 5, 4],
            [5, 4, 4, 5, 4],
            [4, 4, 3, 4, 3],
            [4, 3, 3, 2, 3],
            [5, 4, 5, 4, 5],
        ];

        $patternsByLabId = [];
        foreach ($labIds as $index => $labId) {
            $patternsByLabId[$labId] = $ratingPatterns[$index % count($ratingPatterns)];
        }

        $patternOffsets = [];
        $now = now();
        $records = [];

        foreach ($orders as $index => $order) {
            if ((($index + 1) % 5) === 0) {
                continue;
            }

            $pattern = $patternsByLabId[$order->lab_id];
            $patternOffset = $patternOffsets[$order->lab_id] ?? 0;
            $rating = $pattern[$patternOffset % count($pattern)];
            $patternOffsets[$order->lab_id] = $patternOffset + 1;

            $records[] = [
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'rating' => $rating,
                'comment' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 100) as $chunk) {
            Review::query()->insert($chunk);
        }
    }
}
