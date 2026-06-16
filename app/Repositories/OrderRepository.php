<?php

namespace App\Repositories;

use App\Http\Repositories\LabPricingRepository;
use App\Models\DentalCompensationTypePrice;
use App\Models\Lab;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\User;
use App\Support\OrderStatus;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderRepository
{
    public function __construct(
        private LabPricingRepository $labPricingRepository,
    ) {}

    /**
     * Create order and its teeth inside a transaction.
     */
    public function createOrder(User $doctor, array $data): Order
    {
        return DB::transaction(function () use ($doctor, $data): Order {
            $lab = Lab::query()->findOrFail($data['lab_id']);
            $receivedAt = CarbonImmutable::now();

            $selectedPrice = DentalCompensationTypePrice::query()
                ->where('dental_compensation_type_id', $data['dental_compensation_type_id'])
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', now()->toDateString())
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            $pricingSetting = $this->labPricingRepository->getActiveSettingForLab($lab);
            $caseType = $data['case_type'] ?? 'normal';

            $order = Order::query()->create([
                'user_id' => $doctor->id,
                'lab_id' => $data['lab_id'],
                'patient_name' => $data['patient_name'] ?? null,
                'qr_code' => (string) Str::uuid(),
                'priority' => $data['priority'],
                'status' => OrderStatus::NEW,
                'order_type' => $data['order_type'] ?? 'digital',
                'case_type' => $caseType,
                'notes' => $data['notes'] ?? null,
                'price' => 0,
                'remaining_amount' => 0,
                'serial_number' => null,
                'received_at' => $receivedAt,
                'delivered_at' => $receivedAt->addDays($data['priority'] === 'urgent' ? 2 : 3),
                'tooth_shade_id' => $data['tooth_shade_id'],
                'dental_compensation_type_price_id' => $selectedPrice?->id,
            ]);

            $order->orderTeeth()->createMany(array_map(static function (array $tooth): array {
                return [
                    'tooth_number' => $tooth['tooth_number'],
                    'notes' => $tooth['notes'] ?? null,
                ];
            }, $data['teeth']));

            $urgentMultiplier = (float) ($pricingSetting?->vip_urgent_multiplier ?? 1.25);

            $toothCount = is_array($data['teeth']) ? count($data['teeth']) : 0;
            $pricePerTooth = 0;
            if ($selectedPrice !== null) {
                $pricePerTooth = (float) $selectedPrice->base_price;
            }

            $addonPerTooth = match ($caseType) {
                'implant' => (float) ($pricingSetting?->implant_addon ?? 2.50),
                'bridge' => (float) ($pricingSetting?->long_bridge_or_high_addon ?? 3.50),
                default => 0.0,
            };

            $totalPrice = round(($pricePerTooth * $toothCount) + ($addonPerTooth * $toothCount), 2);

            if ($data['priority'] === 'urgent') {
                $totalPrice = $totalPrice * $urgentMultiplier;
            }
            $totalPrice = round($totalPrice, 2);

            $order->price = $totalPrice;
            $order->remaining_amount = $totalPrice;
            $order->serial_number = sprintf('ORD-%06d', $order->id);
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

                        $originalName = $file->getClientOriginalName();

                        $originalExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                        if (empty($originalExtension) || $originalExtension === 'bin') {

                            $originalExtension = 'stl';
                        }

                        $fileName = (string) Str::uuid().'.'.$originalExtension;

                        $stored = Storage::disk('public')->putFileAs(
                            'orders/'.$order->qr_code,
                            $file,
                            $fileName
                        );

                        $type = in_array($originalExtension, ['stl', 'zip'], true) ? 'stl' : 'image';

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
                'lab',
                'toothShade',
                'dentalCompensationTypePrice.dentalCompensationType',
                'orderTeeth',
            ]);
        });
    }
}
