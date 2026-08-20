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
                'name' => 'الأساسية',
                'description' => 'مناسبة للمختبرات الصغيرة التي بدأت للتو. تتضمن الميزات الأساسية لإدارة الطلبات والتقارير البسيطة.',
                'duration_days' => 30,
                'price' => 49.99,
                'is_active' => true,
            ],
            [
                'name' => 'القياسية',
                'description' => 'مثالية للمختبرات النامية. تتضمن إدارة الطلبات وتتبع المهام وسير العمل عبر رمز QR والدعم ذو الأولوية.',
                'duration_days' => 30,
                'price' => 99.99,
                'is_active' => true,
            ],
            [
                'name' => 'الاحترافية',
                'description' => 'للمختبرات الراسخة التي تحتاج إلى تحليلات متقدمة ودعم متعدد الأقسام وإدارة حساب مخصصة.',
                'duration_days' => 30,
                'price' => 199.99,
                'is_active' => true,
            ],
            [
                'name' => 'المؤسسية',
                'description' => 'باقة شاملة للمختبرات الكبيرة. أقسام غير محدودة وتكاملات مخصصة وضمانات مستوى الخدمة ودعم على مدار الساعة.',
                'duration_days' => 30,
                'price' => 399.99,
                'is_active' => true,
            ],
            [
                'name' => 'التجريبية',
                'description' => 'فترة تجريبية مجانية لمدة 14 يوماً مع إمكانية الوصول إلى جميع ميزات الباقة الاحترافية. بدون الحاجة إلى بطاقة ائتمان.',
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
