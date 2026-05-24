<?php

namespace App\Repositories;

use App\Models\DentalCompensationTypePrice;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderRepository
{
    /**
     * Create order and its teeth inside a transaction.
     */
    public function createOrder(User $doctor, array $data): Order
    {
        return DB::transaction(function () use ($doctor, $data): Order {
            $selectedPrice = DentalCompensationTypePrice::query()
                ->where('dental_compensation_type_id', $data['dental_compensation_type_id'])
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', now()->toDateString())
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            $order = Order::query()->create([
                'user_id' => $doctor->id,
                'lab_id' => $data['lab_id'],
                'qr_code' => (string) Str::uuid(),
                'priority' => $data['priority'],
                'status' => 'pending',
                'order_type' => $data['order_type'] ?? 'digital',
                'notes' => $data['notes'] ?? null,
                'price' => 0,
                'remaining_amount' => 0,
                'tooth_shade_id' => $data['tooth_shade_id'],
                'dental_compensation_type_price_id' => $selectedPrice?->id,
            ]);

            $order->orderTeeth()->createMany(array_map(static function (array $tooth): array {
                return [
                    'tooth_number' => $tooth['tooth_number'],
                    'notes' => $tooth['notes'] ?? null,
                ];
            }, $data['teeth']));

            $toothCount = is_array($data['teeth']) ? count($data['teeth']) : 0;
            $pricePerTooth = 0;
            if ($selectedPrice !== null) {
                $pricePerTooth = (float) $selectedPrice->base_price;
            }

            $totalPrice = round($pricePerTooth * $toothCount, 2);

            $order->price = $totalPrice;
            $order->remaining_amount = $totalPrice;
            $order->save();

            try {
                $qrData = route('orders.show-qr', ['qr' => $order->qr_code]);

                $result = Builder::create()
                    ->writer(new PngWriter)
                    ->data($qrData)
                    ->size(300)
                    ->build();

                $png = $result->getString();
                $path = 'orders/'.$order->qr_code.'/qr.png';
                Storage::disk('public')->put($path, $png);

                $order->qr_image_path = $path;
                $order->save();
            } catch (\Throwable $e) {
                // QR code generation failed; skip to allow order creation to proceed
            }

            // Handle file uploads (stl / images)
            if (! empty($data['files']) && is_array($data['files'])) {
                foreach ($data['files'] as $file) {
                    try {
                        if (! is_object($file)) {
                            continue;
                        }

                        $stored = Storage::disk('public')->putFile('orders/'.$order->qr_code, $file);

                        $extension = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
                        $type = in_array($extension, ['stl', 'zip'], true) ? 'stl' : 'image';

                        OrderFile::query()->create([
                            'order_id' => $order->id,
                            'file_path' => $stored,
                            'file_type' => $type,
                            'uploaded_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        // swallow per-file errors to avoid breaking order creation
                    }
                }
            }

            return $order->fresh()->load([
                'toothShade',
                'dentalCompensationTypePrice.dentalCompensationType',
                'orderTeeth',
            ]);
        });
    }
}
