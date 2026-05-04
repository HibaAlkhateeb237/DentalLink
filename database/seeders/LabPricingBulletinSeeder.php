<?php

namespace Database\Seeders;

use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Lab;
use App\Models\LabPricingSetting;
use Illuminate\Database\Seeder;

class LabPricingBulletinSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveFrom = '2026-04-15';

        $items = [
            ['code' => 'full_zircon_standard', 'name' => 'فل زيركون عادي', 'category' => 'zircon', 'price' => 10.50],
            ['code' => 'full_zircon_multilayer', 'name' => 'فل زيركون ملتي لاير', 'category' => 'zircon', 'price' => 14.00],
            ['code' => 'porcelain_on_zircon', 'name' => 'خزف على زيركون', 'category' => 'ceramics', 'price' => 20.00],
            ['code' => 'empress_crown_or_veneer', 'name' => 'امبرس تاج أو فينير', 'category' => 'ceramics', 'price' => 23.00],
            ['code' => 'porcelain_on_metal_implant_bridge_gingiva_or_high', 'name' => 'خزف على معدن جسور على زرع مع لثة أو ببعد عمودي كبير', 'category' => 'metal', 'price' => 13.00],
            ['code' => 'emax_inlay', 'name' => 'حشوات ايماكس', 'category' => 'inlay', 'price' => 19.00],
            ['code' => 'zircon_inlay_lisi_connect', 'name' => 'حشوات زيركون (Lisi connect)', 'category' => 'inlay', 'price' => 15.50],
            ['code' => 'hybrid_restoration', 'name' => 'تعويض هجين', 'category' => 'hybrid', 'price' => 25.00],
            ['code' => 'temporary_resin_print', 'name' => 'التعويض المؤقت: طباعة ريزين', 'category' => 'temporary', 'price' => 2.00],
            ['code' => 'temporary_pmma_milling', 'name' => 'التعويض المؤقت: خراطة (PMMA)', 'category' => 'temporary', 'price' => 4.00],
            ['code' => 'temporary_zircon', 'name' => 'التعويض المؤقت: مؤقت زيركون', 'category' => 'temporary', 'price' => 5.00],
            ['code' => 'wax_try_in', 'name' => 'التعويض المؤقت: تجربة Wax شمع', 'category' => 'temporary', 'price' => 1.20],
        ];

        foreach (Lab::query()->select(['id'])->cursor() as $lab) {
            LabPricingSetting::query()->updateOrCreate(
                [
                    'lab_id' => $lab->id,
                    'effective_from' => $effectiveFrom,
                ],
                [
                    'currency' => 'USD',
                    'implant_addon' => 2.50,
                    'long_bridge_or_high_addon' => 3.50,
                    'lisi_connect_etching_addon' => 2.00,
                    'intraoral_print_fee' => 8.00,
                    'vip_urgent_multiplier' => 1.25,
                    'student_discount_note' => 'Discount is agreed per patient capability.',
                    'is_active' => true,
                ]
            );

            foreach ($items as $item) {
                $type = DentalCompensationType::query()->updateOrCreate(
                    [
                        'lab_id' => $lab->id,
                        'code' => $item['code'],
                    ],
                    [
                        'name' => $item['name'],
                        'category' => $item['category'],
                        'description' => null,
                    ],
                );

                DentalCompensationTypePrice::query()->updateOrCreate(
                    [
                        'dental_compensation_type_id' => $type->id,
                        'effective_from' => $effectiveFrom,
                    ],
                    [
                        'base_price' => $item['price'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
