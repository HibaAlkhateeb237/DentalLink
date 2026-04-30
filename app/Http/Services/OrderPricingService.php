<?php

namespace App\Http\Services;

use App\Http\Repositories\LabPricingRepository;
use App\Models\Order;
use App\Support\Pricing\JsonLogicEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPricingService
{
    public function __construct(
        private LabPricingRepository $labPricingRepository,
    ) {}

    /**
     * @param  array{compensation_code:string,units?:int,is_implant?:bool,is_long_bridge_or_high?:bool,include_lisi_connect_etching?:bool,include_intraoral_print_examples?:bool,is_vip?:bool,apply_student_discount?:bool,student_discount_percent?:numeric,persist?:bool}  $validated
     * @return array<string, mixed>
     */
    public function calculate(Order $order, array $validated): array
    {
        $order->loadMissing('lab', 'orderTeeth:id,order_id');

        $effectiveAt = CarbonImmutable::now();
        $lab = $order->lab;

        $settings = $this->labPricingRepository->getActiveSettingForLab($lab, $effectiveAt);
        $rules = $this->labPricingRepository->getActiveRulesForLab($lab, $effectiveAt);
        $ruleCodes = $rules->pluck('code')->all();
        $evaluator = new JsonLogicEvaluator;
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

        $context = [
            'order' => [
                'id' => $order->id,
                'priority' => $order->priority,
                'status' => $order->status,
            ],
            'type' => [
                'code' => $type?->code,
                'category' => $type?->category,
            ],
            'units' => $units,
            'is_vip' => (bool) ($validated['is_vip'] ?? false),
            'is_implant' => (bool) ($validated['is_implant'] ?? false),
            'is_long_bridge_or_high' => (bool) ($validated['is_long_bridge_or_high'] ?? false),
            'include_lisi_connect_etching' => (bool) ($validated['include_lisi_connect_etching'] ?? false),
            'include_intraoral_print_examples' => (bool) ($validated['include_intraoral_print_examples'] ?? false),
        ];

        $includeLisi = (bool) ($validated['include_lisi_connect_etching'] ?? false);
        if ($includeLisi && ! in_array('lisi_connect_etching_addon', $ruleCodes, true)) {
            $lisiAddon = (float) ($settings?->lisi_connect_etching_addon ?? 2.00);
            $amount = round($lisiAddon * $units, 2);
            $addons[] = [
                'code' => 'lisi_connect_etching_addon',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $isImplant = (bool) ($validated['is_implant'] ?? false);
        if ($isImplant && ! in_array('implant_addon', $ruleCodes, true)) {
            $implantAddon = (float) ($settings?->implant_addon ?? 2.50);
            $amount = round($implantAddon * $units, 2);
            $addons[] = [
                'code' => 'implant_addon',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $isLongBridgeOrHigh = (bool) ($validated['is_long_bridge_or_high'] ?? false);
        if ($isLongBridgeOrHigh && ! in_array('long_bridge_or_high_addon', $ruleCodes, true)) {
            $longBridgeAddon = (float) ($settings?->long_bridge_or_high_addon ?? 3.50);
            $amount = round($longBridgeAddon * $units, 2);
            $addons[] = [
                'code' => 'long_bridge_or_high_addon',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $includeIntraoral = (bool) ($validated['include_intraoral_print_examples'] ?? false);
        if ($includeIntraoral && ! in_array('intraoral_print_fee', $ruleCodes, true)) {
            $fee = (float) ($settings?->intraoral_print_fee ?? 8.00);
            $amount = round($fee, 2);
            $addons[] = [
                'code' => 'intraoral_print_fee',
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        // Apply dynamic addons defined by lab manager.
        foreach ($rules as $rule) {
            if ($rule->kind !== 'fixed_addon') {
                continue;
            }

            if (! in_array($rule->applies_to, ['order', 'item'], true)) {
                continue;
            }

            $matches = $evaluator->evaluate($rule->condition, $context);
            if (! $matches) {
                continue;
            }

            $mult = $rule->per_unit ? $units : 1;
            $amount = round(((float) $rule->value) * $mult, 2);
            if ($amount <= 0) {
                continue;
            }

            $addons[] = [
                'code' => $rule->code,
                'amount' => $amount,
            ];
            $addonsTotal += $amount;
        }

        $subtotalBeforeMultiplier = round($baseSubtotal + $addonsTotal, 2);

        $isVip = (bool) ($validated['is_vip'] ?? false);
        $applyVipUrgent = $isVip || $order->priority === 'urgent';

        $multiplier = (! in_array('vip_urgent_multiplier', $ruleCodes, true) && $applyVipUrgent)
            ? (float) ($settings?->vip_urgent_multiplier ?? 1.25)
            : 1.0;

        // Apply dynamic multipliers.
        foreach ($rules as $rule) {
            if ($rule->kind !== 'multiplier') {
                continue;
            }

            if ($rule->applies_to !== 'order') {
                continue;
            }

            $matches = $evaluator->evaluate($rule->condition, $context);
            if (! $matches) {
                continue;
            }

            $factor = (float) $rule->value;
            if ($factor <= 0) {
                continue;
            }

            $multiplier *= $factor;
        }

        $subtotalAfterMultiplier = round($subtotalBeforeMultiplier * $multiplier, 2);

        $discountAmount = 0.0;
        $finalTotal = $subtotalAfterMultiplier;

        // Apply dynamic percent discounts after multiplier.
        foreach ($rules as $rule) {
            if ($rule->kind !== 'percent_discount') {
                continue;
            }

            if ($rule->applies_to !== 'order') {
                continue;
            }

            $matches = $evaluator->evaluate($rule->condition, $context);
            if (! $matches) {
                continue;
            }

            $percent = max(0.0, min(100.0, (float) $rule->value));
            if ($percent <= 0) {
                continue;
            }

            $amount = round($finalTotal * ($percent / 100.0), 2);
            $discountAmount += $amount;
            $finalTotal = round($finalTotal - $amount, 2);
        }

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
     * @param  array{compensation_code:string,units?:int,is_implant?:bool,is_long_bridge_or_high?:bool,include_lisi_connect_etching?:bool,include_intraoral_print_examples?:bool,is_vip?:bool,apply_student_discount?:bool,student_discount_percent?:numeric}  $validated
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
