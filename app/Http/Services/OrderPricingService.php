<?php

namespace App\Http\Services;

use App\Http\Repositories\LabPricingRepository;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPricingService
{
    public function __construct(
        private LabPricingRepository $labPricingRepository,
    ) {}

    /**
     * @param  array{compensation_code:string,units?:int,case_type?:string,is_implant?:bool,is_long_bridge_or_high?:bool,include_lisi_connect_etching?:bool,include_intraoral_print_examples?:bool,is_vip?:bool,apply_student_discount?:bool,student_discount_percent?:numeric,persist?:bool}  $validated
     * @return array<string, mixed>
     */
    public function calculate(Order $order, array $validated): array
    {
        $order->loadMissing('lab', 'orderTeeth:id,order_id');

        $effectiveAt = CarbonImmutable::now();
        $lab = $order->lab;

        $settings = $this->labPricingRepository->getActiveSettingForLab($lab, $effectiveAt);
        $priceItem = $this->labPricingRepository->findActivePriceByCode($lab, $validated['compensation_code'], $effectiveAt);

        if ($priceItem === null) {
            throw ValidationException::withMessages([
                'compensation_code' => [__('pricing.invalid_compensation_code')],
            ]);
        }

        $type = $priceItem->dentalCompensationType;

        $units = (int) ($validated['units'] ?? max(1, $order->orderTeeth->count()));

        $baseUnitPrice = (float) $priceItem->base_price;
        $baseSubtotal = round($baseUnitPrice * $units, 2);

        $addons = [];
        $addonsTotal = 0.0;

        $caseType = $validated['case_type'] ?? null;

        $includeLisi = (bool) ($validated['include_lisi_connect_etching'] ?? false);
        if ($includeLisi) {
            $lisiAddon = (float) ($settings?->lisi_connect_etching_addon ?? 2.00);
            $amount = round($lisiAddon * $units, 2);
            $addons[] = [
                'code' => 'lisi_connect_etching_addon',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $isImplant = (bool) ($validated['is_implant'] ?? false);
        if ($caseType === 'implant') {
            $isImplant = true;
        }

        if ($isImplant) {
            $implantAddon = (float) ($settings?->implant_addon ?? 2.50);
            $amount = round($implantAddon * $units, 2);
            $addons[] = [
                'code' => 'implant_addon',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $isLongBridgeOrHigh = (bool) ($validated['is_long_bridge_or_high'] ?? false);
        if ($caseType === 'bridge') {
            $isLongBridgeOrHigh = true;
        }

        if ($isLongBridgeOrHigh) {
            $longBridgeAddon = (float) ($settings?->long_bridge_or_high_addon ?? 3.50);
            $amount = round($longBridgeAddon * $units, 2);
            $addons[] = [
                'code' => 'long_bridge_or_high_addon',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $includeIntraoral = (bool) ($validated['include_intraoral_print_examples'] ?? false);
        if ($includeIntraoral) {
            $fee = (float) ($settings?->intraoral_print_fee ?? 8.00);
            $amount = round($fee, 2);
            $addons[] = [
                'code' => 'intraoral_print_fee',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $subtotalBeforeMultiplier = round($baseSubtotal + $addonsTotal, 2);

        $isVip = (bool) ($validated['is_vip'] ?? false);
        $applyVipUrgent = $isVip || $order->priority === 'urgent';

        $multiplier = $applyVipUrgent
            ? (float) ($settings?->vip_urgent_multiplier ?? 1.25)
            : 1.0;

        $subtotalAfterMultiplier = round($subtotalBeforeMultiplier * $multiplier, 2);

        $discountAmount = 0.0;
        $finalTotal = $subtotalAfterMultiplier;

        $applyStudent = (bool) ($validated['apply_student_discount'] ?? false);
        $studentPercent = $validated['student_discount_percent'] ?? null;

        if ($applyStudent && $studentPercent !== null) {
            $percent = max(0.0, min(100.0, (float) $studentPercent));
            $amount = round($finalTotal * ($percent / 100.0), 2);
            $discountAmount += $amount;
            $finalTotal = round($finalTotal - $amount, 2);
        }

        $currency = $settings?->currency ?? 'USD';

        return [
            'currency' => $currency,
            'effective_from' => $priceItem->effective_from?->toDateString(),
            'compensation' => [
                'code' => $type?->code,
                'name' => $type?->name,
                'base_unit_price' => number_format($baseUnitPrice, 2, '.', ''),
                'units' => $units,
                'base_subtotal' => number_format($baseSubtotal, 2, '.', ''),
            ],
            'addons' => $addons,
            'subtotal_before_multiplier' => number_format($subtotalBeforeMultiplier, 2, '.', ''),
            'multiplier' => number_format($multiplier, 4, '.', ''),
            'subtotal_after_multiplier' => number_format($subtotalAfterMultiplier, 2, '.', ''),
            'discount' => [
                'amount' => number_format($discountAmount, 2, '.', ''),
            ],
            'total' => number_format($finalTotal, 2, '.', ''),
        ];
    }

    /**
     * @param  array{compensation_code:string,units?:int,case_type?:string,is_implant?:bool,is_long_bridge_or_high?:bool,include_lisi_connect_etching?:bool,include_intraoral_print_examples?:bool,is_vip?:bool,apply_student_discount?:bool,student_discount_percent?:numeric}  $validated
     */
    public function apply(Order $order, array $validated): Order
    {
        return DB::transaction(function () use ($order, $validated): Order {
            $breakdown = $this->calculate($order, $validated);
            $total = (float) $breakdown['total'];

            $paid = (float) DB::table('payment_order')
                ->where('order_id', $order->id)
                ->sum('amount');

            $remaining = max(0.0, round($total - $paid, 2));

            $order->fill([
                'price' => $total,
                'remaining_amount' => $remaining,
            ]);
            $order->save();

            return $order->fresh();
        });
    }
}
