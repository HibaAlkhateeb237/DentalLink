<?php

namespace Database\Seeders;

use App\Models\DeliveryTask;
use App\Models\DentalCompensationType;
use App\Models\DentalCompensationTypePrice;
use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Favorite;
use App\Models\Lab;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\OrderTooth;
use App\Models\Payment;
use App\Models\PortfolioCase;
use App\Models\RegistrationOtp;
use App\Models\Review;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskWorkSession;
use App\Models\ToothShade;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\DeliveryStatus;
use App\Support\DeliveryTaskDirection;
use App\Support\OrderStatus;
use App\Support\TaskStatus;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Pre-created Stripe Connected accounts assigned to each seeded lab, in lab creation order.
     *
     * @var array<int, string>
     */
    private const STRIPE_ACCOUNT_IDS = [
        'acct_1Tu5f32F7fiRM0kt',
        'acct_1U1hVTCBpoWAMxEt',
        'acct_1U1hVbFr48catWJK',
        'acct_1U1hVfC6NE5EioXM',
        'acct_1U1hVjCFjCYiY5H3',
        'acct_1U1hVnC59piAVsvx',
        'acct_1U1hVr2FR143pRH8',
        'acct_1U1hVvCKm6COTOM5',
        'acct_1U1hVyCQn5Sdfwxb',
        'acct_1U1hW1FmxWaxvHxR',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class]);

        $this->clearTables();

        $labs = $this->seedLabs();
        $this->call([LabPricingBulletinSeeder::class]);
        $this->call([ToothShadeSeeder::class]);
        $usersByRole = $this->seedUsers($labs);
        $departmentsByLab = $this->seedDepartmentsAndCompensationTypes($labs);

        // Fill time_allowed (hours) for demo departments where it's NULL
        Department::query()
            ->whereNull('time_allowed')
            ->get(['id'])
            ->each(function (Department $department): void {
                $department->update([
                    'time_allowed' => random_int(1, 5),
                ]);
            });

        $technicianIdsByDepartment = $this->seedDepartmentAssignments($usersByRole, $departmentsByLab, $labs);

        $orders = $this->seedOrders($usersByRole['doctor'], $labs);
        $this->seedOrderDetails($orders);
        $this->seedTasksAndWorkSessions($orders, $departmentsByLab, $technicianIdsByDepartment, $labs[0]->id ?? null);
        $this->seedPayments($orders);
        $this->seedDeliveryTasks($orders, $usersByRole['delivery']);
        $this->seedReviews($orders);
        $this->seedFavorites($usersByRole['doctor'], $labs);
        $this->seedPortfolioCases($orders);
        $this->seedRegistrationOtps();
        $this->seedNotificationsAndTokens($usersByRole);
    }

    private function clearTables(): void
    {
        // Disable foreign key checks to allow clean deletes across DB drivers
        Schema::disableForeignKeyConstraints();

        Storage::disk('public')->deleteDirectory('orders');

        DB::table('personal_access_tokens')->delete();
        DB::table('notifications')->delete();
        RegistrationOtp::query()->delete();
        PortfolioCase::query()->delete();
        Transaction::query()->delete();
        Wallet::query()->delete();
        DB::table('payment_order')->delete();
        Payment::query()->delete();
        TaskWorkSession::query()->delete();
        Task::query()->delete();
        DeliveryTask::query()->delete();
        Review::query()->delete();
        Favorite::query()->delete();
        OrderFile::query()->delete();
        OrderTooth::query()->delete();
        Order::query()->delete();
        DentalCompensationType::query()->delete();
        DepartmentUserRole::query()->delete();
        Department::query()->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        // delete users before labs to avoid FK issues when users reference labs
        User::query()->delete();
        Lab::query()->delete();

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Convert a western number to Arabic-Indic digits (e.g. 12 → ١٢).
     */
    private function arabicNumber(int $number): string
    {
        static $digits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        $arabic = '';
        foreach (str_split((string) $number) as $digit) {
            $arabic .= $digits[(int) $digit];
        }

        return $arabic;
    }

    /**
     * Generate a random date in 2026 up to current month (August).
     * Returns CarbonImmutable instance.
     */
    private function randomDate2026(?int $month = null, int $dayMin = 1, int $dayMax = 28): CarbonImmutable
    {
        $currentMonth = 8; // August 2026
        $targetMonth = $month ?? random_int(1, $currentMonth);
        $day = random_int($dayMin, $dayMax);
        $hour = random_int(8, 18);
        $minute = random_int(0, 59);

        return CarbonImmutable::create(2026, $targetMonth, $day, $hour, $minute);
    }

    /**
     * Generate a date range for an order: received_at and delivered_at.
     * Ensures delivered_at is after received_at and both in 2026.
     *
     * @return array{received_at: CarbonImmutable, delivered_at: CarbonImmutable}
     */
    private function generateOrderDates(?int $preferredMonth = null, bool $isUrgent = false): array
    {
        $receivedAt = $this->randomDate2026($preferredMonth);
        $daysToAdd = $isUrgent ? random_int(1, 3) : random_int(3, 7);
        $deliveredAt = $receivedAt->addDays($daysToAdd);

        // Ensure delivered_at doesn't go past end of 2026
        if ($deliveredAt->year > 2026) {
            $deliveredAt = CarbonImmutable::create(2026, 12, 28, 17, 0);
        }

        return ['received_at' => $receivedAt, 'delivered_at' => $deliveredAt];
    }

    /**
     * @return array<int, Lab>
     */
    private function seedLabs(): array
    {
        $labs = [];

        $labsData = [
            [
                'name' => 'مختبر شام لطب الأسنان',
                'description' => 'مختبر رائد في طب الأسنان، متخصص في التيجان والجسور وحلول طب الأسنان الرقمي عالية الجودة.',
                'address' => 'المزة، دمشق',
                'latitude' => 33.5102,
                'longitude' => 36.2384,
                'photo' => 'labs/lab1.png',
            ],
            [
                'name' => 'مختبر إيليت لطب الأسنان',
                'description' => 'تعويضات سنية فاخرة تُصنع بأحدث التقنيات ودقة جمالية عالية.',
                'address' => 'أبو رمانة، دمشق',
                'latitude' => 33.5138,
                'longitude' => 36.2765,
                'photo' => 'labs/lab2.png',
            ],
            [
                'name' => 'مختبر سمايل تك',
                'description' => 'متخصص في أجهزة تقويم الأسنان المبتكرة والابتسامات التجميلية العصرية.',
                'address' => 'كفرسوسة، دمشق',
                'latitude' => 33.4862,
                'longitude' => 36.2921,
                'photo' => 'labs/lab3.png',
            ],
            [
                'name' => 'مختبر التاج الذهبي',
                'description' => 'تعويضات سنية موثوقة ومتينة وبأسعار مناسبة لعيادات دمشق.',
                'address' => 'برامكة، دمشق',
                'latitude' => 33.5077,
                'longitude' => 36.2788,
                'photo' => 'labs/lab4.png',
            ],
            [
                'name' => 'مختبر المستقبل لطب الأسنان',
                'description' => 'شريكك في تصميم الابتسامة الرقمي (DSD) وإعادة تأهيل الزرعات الكاملة.',
                'address' => 'باب توما، دمشق',
                'latitude' => 33.5130,
                'longitude' => 36.3062,
                'photo' => 'labs/lab5.png',
            ],
            [
                'name' => 'مختبر الابتسامة المشرقة',
                'description' => 'فنيون خبراء في التركيبات المتحركة والجزئية المرنة.',
                'address' => 'الميدان، دمشق',
                'latitude' => 33.4973,
                'longitude' => 36.3005,
                'photo' => 'labs/lab6.png',
            ],
            [
                'name' => 'مختبر الأسنان المتقدم',
                'description' => 'مجهز بأحدث أنظمة الطحن CAD/CAM لضمان دقة متناهية وسرعة في التسليم.',
                'address' => 'دمر، دمشق',
                'latitude' => 33.5444,
                'longitude' => 36.2321,
                'photo' => 'labs/lab7.png',
            ],
            [
                'name' => 'مختبر اللؤلؤة لطب الأسنان',
                'description' => 'متخصص في تركيبات الزيركون الطبيعية والعدسات الخزفية الراقية.',
                'address' => 'جرمانا، دمشق',
                'latitude' => 33.4771,
                'longitude' => 36.3387,
                'photo' => 'labs/lab8.png',
            ],

            // Labs inactive
            [
                'name' => 'مختبر غير نشط ١',
                'description' => 'هذا المختبر مغلق حالياً لأعمال الصيانة وتحديث التجهيزات.',
                'address' => 'قدسيا، دمشق',
                'latitude' => 33.5480,
                'longitude' => 36.2145,
                'photo' => 'labs/lab9.png',
                'is_active' => 0,
            ],
            [
                'name' => 'مختبر غير نشط ٢',
                'description' => 'غير نشط مؤقتاً بسبب إجراءات إدارية وتجديد الترخيص.',
                'address' => 'حرستا، دمشق',
                'latitude' => 33.5583,
                'longitude' => 36.3656,
                'photo' => 'labs/lab10.png',
                'is_active' => 0,
            ],

        ];

        foreach ($labsData as $index => $labData) {
            $lab = Lab::query()->create([
                'name' => $labData['name'],
                'description' => $labData['description'] ?? null,
                'phone' => '+9631100'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'address' => $labData['address'],
                'latitude' => $labData['latitude'],
                'longitude' => $labData['longitude'],
                'photo' => $labData['photo'],
                'is_active' => $labData['is_active'] ?? 1,
                'license_number' => sprintf('LAB-%s-%04d', now()->format('Ymd'), $index + 1),
            ]);

            $lab->wallet()->create(['balance' => 0, 'currency' => 'USD']);

            $lab->update(['stripe_account_id' => self::STRIPE_ACCOUNT_IDS[$index]]);

            $labs[] = $lab;
        }

        return $labs;
    }

    /**
     * @param  array<int, Lab>  $labs
     * @return array<string, array<int, User>>
     */
    private function seedUsers(array $labs): array
    {
        $firstLabName = $labs[0]->name ?? null;

        /** @var array<string, Role> $roles */
        $roles = Role::query()->where('guard_name', 'sanctum')->get()->keyBy('name')->all();

        $usersByRole = [
            'system_admin' => [],
            'lab_manager' => [],
            'doctor' => [],
            'receptionist' => [],
            'department_manager' => [],
            'lab_technician' => [],
            'delivery' => [],
        ];

        $admin = User::query()->create([
            'name' => 'مسؤول النظام',
            'email' => 'system.admin@dentalink.local',
            'phone' => '0999000000',
            'password' => 'Admin@123456',
            'location' => 'دمشق',
            'location_lat' => 33.5138000,
            'location_lng' => 36.2765000,
        ]);
        $usersByRole['system_admin'][] = $admin;

        foreach ($labs as $index => $lab) {
            $manager = User::query()->create([
                'name' => 'مدير مختبر '.$this->arabicNumber($index + 1),
                'email' => 'lab.manager'.($index + 1).'@demo.local',
                'phone' => '09991'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
                'location_lat' => $lab->latitude,
                'location_lng' => $lab->longitude,
                'lab_name' => $lab->name,
            ]);

            $usersByRole['lab_manager'][] = $manager;
        }

        for ($index = 1; $index <= 12; $index++) {
            $usersByRole['doctor'][] = User::query()->create([
                'name' => 'دكتور '.$this->arabicNumber($index),
                'email' => 'doctor'.$index.'@demo.local',
                'phone' => '09992'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
                'location' => 'عيادة '.$this->arabicNumber($index),
                'location_lat' => 33.5000000 + ($index * 0.0010000),
                'location_lng' => 36.2500000 + ($index * 0.0010000),
            ]);
        }

        for ($index = 1; $index <= 3; $index++) {
            $usersByRole['receptionist'][] = User::query()->create([
                'name' => 'موظف استقبال '.$this->arabicNumber($index),
                'email' => 'receptionist'.$index.'@demo.local',
                'phone' => '09993'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
                'lab_name' => $firstLabName,
            ]);
        }

        for ($index = 1; $index <= 20; $index++) {
            $usersByRole['department_manager'][] = User::query()->create([
                'name' => 'مدير قسم '.$this->arabicNumber($index),
                'email' => 'department.manager'.$index.'@demo.local',
                'phone' => '09994'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
            ]);
        }

        foreach ($labs as $labIndex => $lab) {
            for ($slot = 1; $slot <= 5; $slot++) {
                $technicianNumber = ($labIndex * 5) + $slot;

                $usersByRole['lab_technician'][] = User::query()->create([
                    'name' => 'فني مختبر '.$this->arabicNumber($technicianNumber),
                    'email' => 'technician'.$technicianNumber.'@demo.local',
                    'phone' => '09995'.str_pad((string) $technicianNumber, 5, '0', STR_PAD_LEFT),
                    'password' => 'Password@123',
                    'lab_name' => $lab->name,
                ]);
            }
        }

        for ($index = 1; $index <= 10; $index++) {

            $correspondingLab = $labs[($index - 1) % count($labs)];

            $usersByRole['delivery'][] = User::query()->create([
                'name' => 'موظف توصيل '.$this->arabicNumber($index),
                'email' => 'delivery'.$index.'@demo.local',
                'phone' => '09996'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
                'lab_name' => $correspondingLab->name,
            ]);
        }

        foreach ($usersByRole as $roleName => $users) {
            $roleId = $roles[$roleName]->id ?? null;

            if ($roleId === null) {
                continue;
            }

            foreach ($users as $user) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }
        }

        if (! empty($usersByRole['receptionist'])) {
            $permissionId = DB::table('permissions')
                ->where('name', 'payments.view')
                ->where('guard_name', 'sanctum')
                ->value('id');

            if ($permissionId !== null) {
                $usersByRole['receptionist'][0]->permissions()->syncWithoutDetaching([(int) $permissionId]);
            }
        }

        return $usersByRole;
    }

    /**
     * @param  array<int, Lab>  $labs
     * @return array<int, array<int, Department>>
     */
    private function seedDepartmentsAndCompensationTypes(array $labs): array
    {
        $departmentsByLab = [];

        $departmentNames = ['الجص', 'الحواف', 'التصميم', 'السيراميك', 'التلميع'];

        $compensationTypes = [
            ['name' => 'تاج زركون', 'code' => 'zircon_crown'],
            ['name' => 'عدسة E-Max', 'code' => 'e_max_veneer'],
            ['name' => 'دعامة زرعة', 'code' => 'implant_abutment'],
            ['name' => 'تاج مؤقت', 'code' => 'temporary_crown'],
        ];

        $firstLabId = $labs[0]->id ?? null;

        foreach ($labs as $lab) {
            $departmentsByLab[$lab->id] = [];
            $sortOrder = 1;

            // إنشاء الأقسام التشغيلية
            foreach ($departmentNames as $name) {
                $departmentsByLab[$lab->id][] = Department::query()->create([
                    'lab_id' => $lab->id,
                    'name' => $name,
                    'description' => 'قسم '.$name.' في '.$lab->name,
                    'sort_order' => $sortOrder++,
                ]);
            }

            if ($firstLabId !== null && $lab->id === $firstLabId) {
                $departmentsByLab[$lab->id][] = Department::query()->create([
                    'lab_id' => $lab->id,
                    'name' => 'الاستقبال',
                    'description' => 'قسم استقبال الطلبات في '.$lab->name,
                    'sort_order' => 0,
                ]);
            }

            $departmentsByLab[$lab->id][] = Department::query()->create([
                'lab_id' => $lab->id,
                'name' => 'التوصيل',
                'description' => 'قسم التوصيل واللوجستيات في '.$lab->name,
                'sort_order' => 0,
            ]);

            // إنشاء قسم الإدارة لمدرّاء المخابر
            Department::query()->create([
                'lab_id' => $lab->id,
                'name' => 'الإدارة',
                'is_management' => true,
                'sort_order' => 0, // قيمة ثابتة تدل على أنه ليس جزءاً من مسار الحركة التصنيعية
            ]);

            foreach ($compensationTypes as $index => $type) {
                $code = $type['code'];
                $typeName = $type['name'];
                $compensation = DentalCompensationType::query()->updateOrCreate(
                    [
                        'lab_id' => $lab->id,
                        'code' => $code,
                    ],
                    [
                        'name' => $typeName,
                        'category' => 'other', // default category
                        'description' => $typeName.' — تسعيرة مرجعية',
                    ],
                );

                $price = 150 + (($index % 7) * 45);

                DentalCompensationTypePrice::query()->updateOrCreate(
                    [
                        'dental_compensation_type_id' => $compensation->id,
                        'effective_from' => now()->toDateString(),
                    ],
                    [
                        'base_price' => $price,
                        'is_active' => true,
                    ],
                );
            }
        }

        return $departmentsByLab;
    }

    /**
     * @param  array<string, array<int, User>>  $usersByRole
     * @param  array<int, array<int, Department>>  $departmentsByLab
     * @return array<int, int>
     */
    private function seedDepartmentAssignments(array $usersByRole, array $departmentsByLab, array $labs): array
    {
        $managerRoleId = Role::query()->where('name', 'department_manager')->where('guard_name', 'sanctum')->value('id');
        $technicianRoleId = Role::query()->where('name', 'lab_technician')->where('guard_name', 'sanctum')->value('id');
        $receptionistRoleId = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->value('id');
        $deliveryRoleId = Role::query()->where('name', 'delivery')->where('guard_name', 'sanctum')->value('id');

        if ($managerRoleId === null || $technicianRoleId === null) {
            return [];
        }

        $managers = array_values($usersByRole['department_manager']);
        $technicians = array_values($usersByRole['lab_technician']);
        $receptionists = array_values($usersByRole['receptionist']);
        $deliveries = array_values($usersByRole['delivery']);

        $labsById = collect($labs)->keyBy('id');

        $techniciansByLab = [];

        foreach ($technicians as $technician) {
            if ($technician->lab_name === null) {
                continue;
            }

            $techniciansByLab[$technician->lab_name][] = $technician;
        }

        $deliveriesByLab = [];
        foreach ($deliveries as $delivery) {
            if ($delivery->lab_name === null) {
                continue;
            }
            $deliveriesByLab[$delivery->lab_name][] = $delivery;
        }

        $technicianIdsByDepartment = [];

        $labIds = array_keys($departmentsByLab);
        sort($labIds);
        $firstLabId = $labIds[0] ?? null;

        $receptionistCounter = 0;

        // عداد لمعرفة المخبر الحالي لسحب مدراء جدد من المصفوفة
        $labCounter = 0;

        foreach ($departmentsByLab as $labId => $departments) {
            $labName = $labsById[$labId]->name ?? null;
            $labTechnicians = $labName !== null ? ($techniciansByLab[$labName] ?? []) : [];

            $labDeliveries = $labName !== null ? ($deliveriesByLab[$labName] ?? []) : [];
            $technicianCounter = 0;

            // حجز مديرين اثنين فريدين لهذا المخبر تحديداً بناءً على ترتيب المخبر
            $manager1 = $managers[($labCounter * 2) % count($managers)];
            $manager2 = $managers[(($labCounter * 2) + 1) % count($managers)];

            // عداد للأقسام التشغيلية الفعلية داخل المخبر الحالي لتطبيق قاعدة (كل 2 لمدير)
            $operationalDeptIndex = 0;

            foreach ($departments as $department) {
                // تخطي قسم الاستقبال
                if ($firstLabId !== null && $labId === $firstLabId && $department->name === 'الاستقبال') {
                    if ($receptionistRoleId !== null && ! empty($receptionists)) {
                        $receptionistCount = min(2, count($receptionists));

                        for ($offset = 0; $offset < $receptionistCount; $offset++) {
                            $receptionist = $receptionists[($receptionistCounter + $offset) % count($receptionists)];
                            DepartmentUserRole::query()->firstOrCreate([
                                'user_id' => $receptionist->id,
                                'role_id' => $receptionistRoleId,
                                'department_id' => $department->id,
                            ]);
                        }

                        $receptionistCounter += $receptionistCount;
                    }

                    continue;
                }

                // الإضافة المباشرة لقسم التوصيل لإسناد الموظف المخصص لهذا المخبر
                if ($department->name === 'التوصيل') {
                    if ($deliveryRoleId !== null && ! empty($labDeliveries)) {
                        // إسناد موظف التوصيل الثابت المخصص لهذا المختبر
                        $assignedDelivery = $labDeliveries[0];

                        DepartmentUserRole::query()->firstOrCreate([
                            'user_id' => $assignedDelivery->id,
                            'role_id' => $deliveryRoleId,
                            'department_id' => $department->id,
                        ]);
                    }

                    continue;
                }

                // تخطي قسم الإدارة العامة لأنه مخصص لمدير المختبر وليس لمدير القسم
                if ($department->is_management) {
                    continue;
                }

                // تطبيق قاعدة قسمين لكل مدير:
                // أول قسمين (0 و 1) يأخذهما المدير الأول. القسم الثالث (2) يأخذه المدير الثاني.
                $assignedManager = ($operationalDeptIndex < 2) ? $manager1 : $manager2;

                DepartmentUserRole::query()->firstOrCreate([
                    'user_id' => $assignedManager->id,
                    'role_id' => $managerRoleId,
                    'department_id' => $department->id,
                ]);

                // زيادة عداد الأقسام التشغيلية لتوزيع القسم التالي على المدير الصحيح
                $operationalDeptIndex++;

                // إسناد الفنيين للأقسام كما هي دون تغيير
                $technician = $labTechnicians[$technicianCounter] ?? null;

                if ($technician !== null) {
                    DepartmentUserRole::query()->firstOrCreate([
                        'user_id' => $technician->id,
                        'role_id' => $technicianRoleId,
                        'department_id' => $department->id,
                    ]);

                    $technicianIdsByDepartment[$department->id] = $technician->id;
                }

                $technicianCounter++;
            }

            // الانتقال للمخبر القادم وزيادة العداد لسحب طاقم مدراء جديد بالكامل
            $labCounter++;
        }

        // إسناد مدراء المخابر لأقسام الإدارة الأساسية
        $labManagerRoleId = Role::query()->where('name', 'lab_manager')->where('guard_name', 'sanctum')->value('id');
        if ($labManagerRoleId !== null && ! empty($usersByRole['lab_manager'])) {
            $managementDepartments = Department::query()
                ->where('is_management', true)
                ->get()
                ->keyBy('lab_id');

            $labManagers = array_values($usersByRole['lab_manager']);

            foreach ($labs as $index => $lab) {
                $manager = $labManagers[$index % count($labManagers)] ?? null;

                if ($manager === null) {
                    continue;
                }

                $managementDept = $managementDepartments[$lab->id] ?? null;

                if ($managementDept === null) {
                    continue;
                }

                DepartmentUserRole::query()->firstOrCreate([
                    'user_id' => $manager->id,
                    'role_id' => $labManagerRoleId,
                    'department_id' => $managementDept->id,
                ]);
            }
        }

        return $technicianIdsByDepartment;
    }

    /**
     * @param  array<int, User>  $doctors
     * @param  array<int, Lab>  $labs
     * @return array<int, Order>
     */
    private function seedOrders(array $doctors, array $labs): array
    {
        $statuses = OrderStatus::ALL;
        $priorities = ['normal', 'urgent'];
        $types = ['digital', 'physical'];
        $caseTypes = ['normal', 'implant', 'bridge'];
        $patientNames = ['علي', 'أحمد', 'عمر', 'ليلى', 'نور', 'يوسف', 'سامر', 'رنا', 'كريم', 'هدى'];

        $orders = [];
        $orderIndex = 0;

        // Target: ~60-80 orders spread across 12 months
        // ~5-7 orders per month per lab (8 active labs) = ~40-56 orders/month total
        // We'll create ~48 orders from doctors (4 per doctor * 12 doctors) + 8 lab orders = 56 base
        // Plus additional orders to fill out each month

        // First, create base orders from each doctor (4 each = 48 orders)
        foreach ($doctors as $doctor) {
            for ($count = 0; $count < 4; $count++) {
                $lab = $labs[$orderIndex % count($labs)];
                $status = $statuses[$orderIndex % count($statuses)];
                $priority = $priorities[$orderIndex % count($priorities)];
                $price = 180 + (($orderIndex % 10) * 50); // 180-630 range
                $isCompleted = $status === OrderStatus::COMPLETED;
                $remainingAmount = $isCompleted ? (random_int(0, 2) === 0 ? 0 : random_int(20, 80)) : $price;

                // Distribute across months: each doctor gets orders in different months
                $month = (($orderIndex + $count) % 12) + 1;
                $dates = $this->generateOrderDates($month, $priority === 'urgent');
                $receivedAt = $dates['received_at'];
                $deliveredAt = $dates['delivered_at'];

                // For non-completed orders, delivered_at should be in future or null
                if (! $isCompleted) {
                    $deliveredAt = $receivedAt->addDays($priority === 'urgent' ? random_int(2, 4) : random_int(4, 10));
                    if ($deliveredAt->year > 2026) {
                        $deliveredAt = CarbonImmutable::create(2026, 12, 28, 17, 0);
                    }
                }

                $order = Order::query()->create([
                    'user_id' => $doctor->id,
                    'lab_id' => $lab->id,
                    'patient_name' => $patientNames[$orderIndex % count($patientNames)],
                    'qr_code' => (string) Str::uuid(),
                    'priority' => $priority,
                    'status' => $status,
                    'order_type' => $types[$orderIndex % count($types)],
                    'case_type' => $caseTypes[$orderIndex % count($caseTypes)],
                    'notes' => 'طلب تجريبي رقم '.$this->arabicNumber($orderIndex + 1),
                    'price' => $price,
                    'remaining_amount' => $remainingAmount,
                    'serial_number' => null,
                    'received_at' => $receivedAt,
                    'delivered_at' => $isCompleted ? $deliveredAt : null,
                ]);

                $order->serial_number = sprintf('ORD-%06d', $order->id);
                $order->save();

                $this->seedOrderQrImage($order);
                $orders[] = $order->fresh();

                $orderIndex++;
            }
        }

        // Additional orders per lab per month to ensure good monthly distribution (Jan-Aug only)
        $additionalOrdersPerLabPerMonth = 2;

        foreach ($labs as $lab) {
            if (! $lab->is_active) {
                continue;
            }

            for ($month = 1; $month <= 8; $month++) {
                for ($i = 0; $i < $additionalOrdersPerLabPerMonth; $i++) {
                    $doctor = $doctors[array_rand($doctors)];
                    $priority = $priorities[array_rand($priorities)];
                    $price = 180 + random_int(0, 9) * 50;

                    // Weight statuses: more completed/delivered for past months, more pending/new for future months
                    $currentMonth = 8; // August 2026
                    if ($month < $currentMonth) {
                        // Past months: mostly completed
                        $status = $this->weightedStatus(['completed' => 60, 'try_on' => 10, 'resend_wrong_impression' => 5, 'in_progress' => 15, 'new' => 5, 'pending' => 5]);
                    } elseif ($month === $currentMonth) {
                        // Current month: mix
                        $status = $this->weightedStatus(['completed' => 25, 'in_progress' => 30, 'try_on' => 15, 'new' => 15, 'pending' => 10, 'resend_wrong_impression' => 5]);
                    } else {
                        // Future months: mostly new/pending
                        $status = $this->weightedStatus(['new' => 40, 'pending' => 30, 'in_progress' => 20, 'completed' => 5, 'try_on' => 3, 'resend_wrong_impression' => 2]);
                    }

                    $isCompleted = in_array($status, [OrderStatus::COMPLETED], true);
                    $remainingAmount = $isCompleted ? (random_int(0, 3) === 0 ? 0 : random_int(10, 60)) : $price;

                    $dates = $this->generateOrderDates($month, $priority === 'urgent');
                    $receivedAt = $dates['received_at'];
                    $deliveredAt = $dates['delivered_at'];

                    if (! $isCompleted) {
                        $deliveredAt = $receivedAt->addDays($priority === 'urgent' ? random_int(2, 4) : random_int(4, 10));
                        if ($deliveredAt->year > 2026) {
                            $deliveredAt = CarbonImmutable::create(2026, 12, 28, 17, 0);
                        }
                    }

                    $order = Order::query()->create([
                        'user_id' => $doctor->id,
                        'lab_id' => $lab->id,
                        'patient_name' => $patientNames[array_rand($patientNames)],
                        'qr_code' => (string) Str::uuid(),
                        'priority' => $priority,
                        'status' => $status,
                        'order_type' => $types[array_rand($types)],
                        'case_type' => $caseTypes[array_rand($caseTypes)],
                        'notes' => 'طلب إضافي لشهر '.$month.' - '.$lab->name,
                        'price' => $price,
                        'remaining_amount' => $remainingAmount,
                        'serial_number' => null,
                        'received_at' => $receivedAt,
                        'delivered_at' => $isCompleted ? $deliveredAt : null,
                    ]);

                    $order->serial_number = sprintf('ORD-%06d', $order->id);
                    $order->save();

                    $this->seedOrderQrImage($order);
                    $orders[] = $order->fresh();
                }
            }
        }

        // Also add the original "new orders per lab" (8 orders)
        foreach ($labs as $lab) {
            if (! $lab->is_active) {
                continue;
            }

            $doctor = $doctors[array_rand($doctors)];
            $patientName = $patientNames[array_rand($patientNames)];
            $type = $types[array_rand($types)];
            $caseType = $caseTypes[array_rand($caseTypes)];
            $price = 180 + random_int(0, 9) * 50;

            // These are current month orders with NEW status
            $receivedAt = $this->randomDate2026(8, 1, 15); // Early August
            $deliveredAt = $receivedAt->addDays(3);

            $order = Order::query()->create([
                'user_id' => $doctor->id,
                'lab_id' => $lab->id,
                'patient_name' => $patientName,
                'qr_code' => (string) Str::uuid(),
                'priority' => 'normal',
                'status' => OrderStatus::NEW,
                'order_type' => $type,
                'case_type' => $caseType,
                'notes' => 'طلب جديد تجريبي لمختبر '.$lab->name,
                'price' => $price,
                'remaining_amount' => $price,
                'serial_number' => null,
                'received_at' => $receivedAt,
                'delivered_at' => null,
            ]);

            $order->serial_number = sprintf('ORD-%06d', $order->id);
            $order->save();

            $this->seedOrderQrImage($order);
            $orders[] = $order->fresh();
        }

        return $orders;
    }

    /**
     * Pick a status based on weights.
     */
    private function weightedStatus(array $weights): string
    {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $status => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return OrderStatus::NEW;
    }

    private function seedOrderQrImage(Order $order): void
    {
        try {
            $result = Builder::create()
                ->writer(new PngWriter)
                ->data(route('orders.show-qr', ['qr' => $order->qr_code]))
                ->size(300)
                ->build();

            $path = 'orders/'.$order->qr_code.'/qr.png';
            Storage::disk('public')->put($path, $result->getString());

            $order->forceFill([
                'qr_image_path' => $path,
            ])->save();
        } catch (\Throwable $throwable) {
        }
    }

    /**
     * @param  array<int, Order>  $orders
     */
    private function seedOrderDetails(array $orders): void
    {
        $shadeIds = ToothShade::query()
            ->whereIn('code', ['A2', 'B1'])
            ->pluck('id', 'code');

        foreach ($orders as $index => $order) {
            $priceIds = DentalCompensationTypePrice::query()
                ->select('dental_compensation_type_prices.id')
                ->join('dental_compensation_types', 'dental_compensation_types.id', '=', 'dental_compensation_type_prices.dental_compensation_type_id')
                ->where('dental_compensation_types.lab_id', $order->lab_id)
                ->orderBy('dental_compensation_type_prices.id')
                ->pluck('dental_compensation_type_prices.id')
                ->values();

            $order->update([
                'tooth_shade_id' => $shadeIds->get('A2'),
                'dental_compensation_type_price_id' => $priceIds->get(0),
            ]);

            $beforeSeed = 'seed-files/before-scan-'.(($index % 3) + 1).'.jpg';
            $beforePath = 'orders/'.$order->qr_code.'/scan-before.jpg';
            Storage::disk('public')->copy($beforeSeed, $beforePath);

            OrderFile::query()->create([
                'order_id' => $order->id,
                'file_path' => $beforePath,
                'file_type' => 'image',
                'uploaded_at' => $order->created_at,
            ]);

            $afterSeed = 'seed-files/after-scan-'.(($index % 3) + 1).'.jpg';
            $afterPath = 'orders/'.$order->qr_code.'/scan-after.jpg';
            Storage::disk('public')->copy($afterSeed, $afterPath);

            OrderFile::query()->create([
                'order_id' => $order->id,
                'file_path' => $afterPath,
                'file_type' => 'image',
                'uploaded_at' => $order->created_at?->addDay(),
            ]);

            $firstTooth = 11 + ($index % 8);
            $secondTooth = 21 + ($index % 8);

            OrderTooth::query()->create([
                'order_id' => $order->id,
                'tooth_number' => $firstTooth,
                'notes' => 'Main treatment tooth',
            ]);

            OrderTooth::query()->create([
                'order_id' => $order->id,
                'tooth_number' => $secondTooth,
                'notes' => 'Companion treatment tooth',
            ]);
        }
    }

    /**
     * @param  array<int, Order>  $orders
     * @param  array<int, array<int, Department>>  $departmentsByLab
     * @param  array<int, int>  $technicianIdsByDepartment
     */
    private function seedTasksAndWorkSessions(array $orders, array $departmentsByLab, array $technicianIdsByDepartment, ?int $firstLabId = null): void
    {
        // 1. Get first lab departments for showcase orders (fixed order: الجص, الحواف, التصميم)
        $firstLabDepartments = $firstLabId !== null ? ($departmentsByLab[$firstLabId] ?? []) : [];

        // 2. Get first 5 orders from first lab for showcase
        $firstLabOrders = $firstLabId !== null
            ? collect($orders)->where('lab_id', $firstLabId)->take(5)->values()
            : collect();

        // Ensure we have enough departments and orders
        if (count($firstLabDepartments) < 3 || $firstLabOrders->count() < 5) {
            return;
        }

        $gypsum = $firstLabDepartments[0];
        $edges = $firstLabDepartments[1];
        $design = $firstLabDepartments[2];

        $techGypsum = $technicianIdsByDepartment[$gypsum->id] ?? null;
        $techEdges = $technicianIdsByDepartment[$edges->id] ?? null;
        $techDesign = $technicianIdsByDepartment[$design->id] ?? null;

        // --- [Showcase Order 1: Pending assignment in Gypsum] ---
        $order1 = $firstLabOrders[0];
        $order1->update(['status' => OrderStatus::PENDING]);
        Task::query()->create([
            'order_id' => $order1->id,
            'department_id' => $gypsum->id,
            'user_id' => null,
            'status' => TaskStatus::PENDING_ASSIGNMENT,
        ]);

        // --- [Showcase Order 2: Assigned but not started] ---
        $order2 = $firstLabOrders[1];
        $order2->update(['status' => OrderStatus::IN_PROGRESS]);
        Task::query()->create([
            'order_id' => $order2->id,
            'department_id' => $gypsum->id,
            'user_id' => $techGypsum,
            'status' => TaskStatus::ASSIGNED,
        ]);

        // --- [Showcase Order 3: In progress by Gypsum technician] ---
        $order3 = $firstLabOrders[2];
        $order3->update(['status' => OrderStatus::IN_PROGRESS]);
        $task3 = Task::query()->create([
            'order_id' => $order3->id,
            'department_id' => $gypsum->id,
            'user_id' => $techGypsum,
            'status' => TaskStatus::IN_PROGRESS,
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task3->id,
            'start_time' => now()->subHours(1),
            'end_time' => null,
            'status' => 'active',
            'note' => 'العمل جاري على تلبسية الزيركون',
        ]);

        // --- [Showcase Order 4: Completed, pending review] ---
        $order4 = $firstLabOrders[3];
        $order4->update(['status' => OrderStatus::IN_PROGRESS]);
        $task4 = Task::query()->create([
            'order_id' => $order4->id,
            'department_id' => $gypsum->id,
            'user_id' => $techGypsum,
            'status' => TaskStatus::PENDING_REVIEW,
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task4->id,
            'start_time' => now()->subHours(4),
            'end_time' => now()->subHours(1),
            'status' => 'completed',
            'note' => 'تم إنهاء النحت والتجهيز بالكامل ميكانيكياً',
        ]);

        // --- [Showcase Order 5: Fully completed through all departments] ---
        $order5 = $firstLabOrders[4];
        $order5->update(['status' => OrderStatus::COMPLETED]);

        // Use order's received_at as base for work sessions (ensure immutable)
        $baseTime5 = $order5->received_at ? CarbonImmutable::instance($order5->received_at) : now()->subDays(3);

        // 1. Gypsum task completed
        $gypsumStart = $baseTime5->addHours(1);
        $gypsumEnd = $gypsumStart->addHours(3);
        $task5 = Task::query()->create([
            'order_id' => $order5->id,
            'department_id' => $gypsum->id,
            'user_id' => $techGypsum,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => $gypsumEnd,
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task5->id,
            'start_time' => $gypsumStart,
            'end_time' => $gypsumEnd,
            'status' => 'completed',
            'note' => 'تم الانتهاء من صب ونحت الخزف بنجاح',
        ]);

        // 2. Edges task completed
        $edgesStart = $gypsumEnd->addHours(2);
        $edgesEnd = $edgesStart->addHours(3);
        $task6 = Task::query()->create([
            'order_id' => $order5->id,
            'department_id' => $edges->id,
            'user_id' => $techEdges,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => $edgesEnd,
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task6->id,
            'start_time' => $edgesStart,
            'end_time' => $edgesEnd,
            'status' => 'completed',
            'note' => 'تم تجهيز أسلاك وتقويم الحالة بالكامل',
        ]);

        // 3. Design task completed
        $designStart = $edgesEnd->addHours(2);
        $designEnd = $designStart->addHours(4);
        $task7 = Task::query()->create([
            'order_id' => $order5->id,
            'department_id' => $design->id,
            'user_id' => $techDesign,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => $designEnd,
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task7->id,
            'start_time' => $designStart,
            'end_time' => $designEnd,
            'status' => 'completed',
            'note' => 'إنهاء تحضير دعم الزرعة النهائي وإرسالها للمدير للتقييم الأخير',
        ]);

        $handledIds = $firstLabOrders->pluck('id')->toArray();

        // Seed tasks for all remaining orders across all labs
        foreach ($orders as $order) {
            if (in_array($order->id, $handledIds, true)) {
                continue;
            }

            $labDepts = collect($departmentsByLab[$order->lab_id] ?? [])
                ->reject(fn (Department $d) => $d->is_management || in_array($d->name, ['الاستقبال', 'Reception']))
                ->values();

            if ($labDepts->isEmpty()) {
                continue;
            }

            // Use order's received_at as the base timeline (ensure immutable)
            $baseTime = $order->received_at ? CarbonImmutable::instance($order->received_at) : now();
            $isCompleted = $order->status === OrderStatus::COMPLETED;

            // Use ALL departments for each order to ensure every department gets workload
            $deptsToUse = $labDepts;
            $currentTime = $baseTime;

            // Get all technicians for this lab to distribute work
            $labTechnicians = User::query()
                ->whereHas('roles', fn (EloquentBuilder $q) => $q->where('name', 'lab_technician'))
                ->whereHas('departmentUserRoles', fn (EloquentBuilder $q) => $q->whereIn('department_id', function ($query) use ($order) {
                    $query->select('id')->from('departments')->where('lab_id', $order->lab_id);
                }))
                ->get()
                ->pluck('id')
                ->toArray();

            $techIndex = 0;

            // Create tasks for ALL departments - use round-robin to distribute work evenly
            foreach ($deptsToUse as $deptIndex => $dept) {
                // Use department-assigned technician if available, otherwise cycle through lab technicians
                $techId = $technicianIdsByDepartment[$dept->id] ?? ($labTechnicians[$techIndex % count($labTechnicians)] ?? null);
                if (! empty($labTechnicians)) {
                    $techIndex++;
                }

                // Create task for every department, but with different statuses based on order status
                match ($order->status) {
                    OrderStatus::PENDING => Task::query()->create([
                        'order_id' => $order->id,
                        'department_id' => $dept->id,
                        'status' => $deptIndex === 0 ? TaskStatus::PENDING_ASSIGNMENT : TaskStatus::ASSIGNED,
                        'approved_at' => $currentTime->startOfDay()->addHours(random_int(8, 20)),
                    ]),
                    OrderStatus::IN_PROGRESS => Task::query()->create([
                        'order_id' => $order->id,
                        'department_id' => $dept->id,
                        'user_id' => $techId,
                        'status' => rand(0, 1) ? TaskStatus::IN_PROGRESS : TaskStatus::ASSIGNED,
                        'approved_at' => $currentTime->startOfDay()->addHours(random_int(8, 20)),
                    ]),
                    OrderStatus::TRY_ON, OrderStatus::RESEND_WRONG_IMPRESSION => Task::query()->create([
                        'order_id' => $order->id,
                        'department_id' => $dept->id,
                        'user_id' => $techId,
                        'status' => rand(0, 1) ? TaskStatus::IN_PROGRESS : TaskStatus::ASSIGNED,
                        'approved_at' => $currentTime->startOfDay()->addHours(random_int(8, 20)),
                    ]),
                    OrderStatus::COMPLETED => $this->createCompletedTaskWithWorkSession($order, $dept, ['dept' => $techId], $currentTime, $deptIndex),
                    default => Task::query()->create([
                        'order_id' => $order->id,
                        'department_id' => $dept->id,
                        'status' => TaskStatus::PENDING_ASSIGNMENT,
                        'approved_at' => $currentTime->startOfDay()->addHours(random_int(8, 20)),
                    ]),
                };

                // Add small time gap between departments
                $currentTime = $currentTime->addHours(random_int(1, 4));
            }
        }
    }

    /**
     * Create in-progress task with work session.
     */
    private function createInProgressTask(Order $order, Department $dept, array $techData, $baseTime): void
    {
        $techId = $techData['dept'] ?? null;
        $isAssigned = rand(0, 1);

        // Use safe hours (8-20) to avoid DST issues
        $safeTime = $baseTime->startOfDay()->addHours(random_int(8, 20));

        $task = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $dept->id,
            'user_id' => $isAssigned ? $techId : null,
            'status' => $isAssigned ? TaskStatus::IN_PROGRESS : TaskStatus::ASSIGNED,
            'approved_at' => $safeTime,
        ]);

        if ($isAssigned) {
            // Start 1-6 hours after safe time
            $startTime = $safeTime->addHours(random_int(1, 6));
            TaskWorkSession::query()->create([
                'task_id' => $task->id,
                'start_time' => $startTime,
                'end_time' => null,
                'status' => 'active',
                'note' => 'العمل جاري على الطلب',
            ]);
        }
    }

    /**
     * Create a single completed task with work session.
     */
    private function createCompletedTaskWithWorkSession(Order $order, Department $dept, array $techData, $baseTime, int $deptIndex): void
    {
        $techId = $techData['dept'] ?? null;

        // Each department takes 3-6 hours of work
        // Use hours 8-20 to avoid DST transition issues
        $hourOffset = random_int(8, 20);
        $workStart = $baseTime->startOfDay()->addHours($hourOffset);
        $workDuration = random_int(3, 6);
        $workEnd = $workStart->addHours($workDuration);

        $task = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $dept->id,
            'user_id' => $techId,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => $workEnd,
        ]);

        TaskWorkSession::query()->create([
            'task_id' => $task->id,
            'start_time' => $workStart,
            'end_time' => $workEnd,
            'status' => 'completed',
            'note' => 'تم إنجاز المهمة بنجاح في قسم '.$dept->name,
        ]);
    }

    /**
     * @param  array<int, Order>  $orders
     */
    private function seedPayments(array $orders): void
    {
        $methods = ['cash', 'card', 'bank_transfer', 'wallet'];

        foreach ($orders as $index => $order) {
            if (in_array($order->status, ['pending', 'cancelled'], true)) {
                continue;
            }

            $paidAmount = max((float) $order->price - (float) $order->remaining_amount, 0);

            if ($paidAmount <= 0) {
                continue;
            }

            // Determine payment date: for completed orders, around delivered_at or received_at
            // For in-progress orders, sometime after received_at
            $baseTime = $order->received_at ?? now();
            $paidAt = $baseTime->addDays(random_int(0, $order->status === OrderStatus::COMPLETED ? 10 : 30));

            // Cap at end of 2026
            if ($paidAt->year > 2026) {
                $paidAt = CarbonImmutable::create(2026, 12, 28, 17, 0);
            }

            $payment = Payment::query()->create([
                'user_id' => $order->user_id,
                'amount' => $paidAmount,
                'payment_method' => $methods[$index % count($methods)],
                'payment_status' => 'paid',
                'currency' => 'USD',
                'paid_at' => $paidAt,
            ]);

            DB::table('payment_order')->insert([
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $paidAmount,
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);

            $order->update(['remaining_amount' => 0]);

            if ($order->lab_id) {
                $wallet = Wallet::query()->firstOrCreate(
                    ['lab_id' => $order->lab_id],
                    ['currency' => 'USD']
                );

                $wallet->increment('balance', $paidAmount);
                $wallet->refresh();

                Transaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'type' => 'order_payment_credit',
                    'amount' => $paidAmount,
                    'balance_after' => $wallet->balance,
                    'currency' => 'USD',
                    'description' => 'تم استلام دفعة عن الطلب #'.$order->serial_number,
                    'payable_type' => Order::class,
                    'payable_id' => $order->id,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                    'metadata' => [
                        'order_id' => $order->id,
                        'order_serial' => $order->serial_number,
                    ],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  array<int, Order>  $orders
     * @param  array<int, User>  $deliveryUsers
     */
    private function seedDeliveryTasks(array $orders, array $deliveryUsers): void
    {
        $deliveryStatuses = DeliveryStatus::ALL;

        foreach ($orders as $index => $order) {
            if (! in_array($order->status, ['completed', 'delivered'], true)) {
                continue;
            }

            // Weight status: most completed orders should be DELIVERED
            $status = $this->weightedStatus([
                DeliveryStatus::DELIVERED => 70,
                DeliveryStatus::ON_THE_WAY_TO_DOCTOR => 15,
                DeliveryStatus::RECEIVED => 10,
                DeliveryStatus::EMPTY => 5,
            ]);
            $deliveryUser = $deliveryUsers[$index % count($deliveryUsers)];

            $direction = match ($order->status) {
                OrderStatus::COMPLETED => DeliveryTaskDirection::TO_DOCTOR,
                default => DeliveryTaskDirection::TO_LAB,
            };

            // Base delivery timeline on order's delivered_at
            $baseTime = $order->delivered_at ?? $order->received_at ?? now();

            $pickedAt = null;
            $deliveredAt = null;

            if (in_array($status, DeliveryStatus::PICKED_STATUSES, true)) {
                // Picked up 0-2 days after order delivered/completed
                $pickedAt = $baseTime->addDays(random_int(0, 2));
            }

            if ($status === DeliveryStatus::DELIVERED) {
                // Delivered 1-3 days after pickup, or 1-5 days after base if no pickup
                if ($pickedAt) {
                    $deliveredAt = $pickedAt->addDays(random_int(1, 3));
                } else {
                    $deliveredAt = $baseTime->addDays(random_int(1, 5));
                }
            }

            // Cap dates at end of 2026
            if ($pickedAt && $pickedAt->year > 2026) {
                $pickedAt = CarbonImmutable::create(2026, 12, 28, 17, 0);
            }
            if ($deliveredAt && $deliveredAt->year > 2026) {
                $deliveredAt = CarbonImmutable::create(2026, 12, 28, 17, 0);
            }

            DeliveryTask::query()->create([
                'order_id' => $order->id,
                'user_id' => $deliveryUser->id,
                'status' => $status,
                'direction' => $direction,
                'picked_at' => $pickedAt,
                'delivered_at' => $deliveredAt,
            ]);
        }
    }

    /**
     * @param  array<int, Order>  $orders
     */
    private function seedReviews(array $orders): void
    {
        foreach ($orders as $index => $order) {
            if (! in_array($order->status, ['completed', 'delivered'], true)) {
                continue;
            }

            if (($index % 3) === 0) {
                continue;
            }

            Review::query()->create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'rating' => 3 + ($index % 3),
                'comment' => 'مراجعة تجريبية للطلب رقم '.$order->id,
            ]);
        }
    }

    /**
     * @param  array<int, User>  $doctors
     * @param  array<int, Lab>  $labs
     */
    private function seedFavorites(array $doctors, array $labs): void
    {
        foreach ($doctors as $index => $doctor) {
            $firstLab = $labs[$index % count($labs)];
            $secondLab = $labs[($index + 1) % count($labs)];

            Favorite::query()->firstOrCreate([
                'user_id' => $doctor->id,
                'lab_id' => $firstLab->id,
            ]);

            Favorite::query()->firstOrCreate([
                'user_id' => $doctor->id,
                'lab_id' => $secondLab->id,
            ]);
        }
    }

    /**
     * @param  array<int, Order>  $orders
     */
    private function seedPortfolioCases(array $orders): void
    {
        foreach ($orders as $index => $order) {
            if (! in_array($order->status, ['completed', 'delivered'], true)) {
                continue;
            }

            if (($index % 2) === 0) {
                continue;
            }

            PortfolioCase::query()->create([
                'order_id' => $order->id,
                'case_name' => 'حالة من أعمال المختبر #'.$order->id,
                'before_image_path' => 'labs/portfolio/order-'.$order->id.'-before.jpg',
                'after_image_path' => 'labs/portfolio/order-'.$order->id.'-after.jpg',
                'duration_minutes' => 90 + (($index % 4) * 20),
                'is_published' => ($index % 5) !== 0,
            ]);
        }
    }

    private function seedRegistrationOtps(): void
    {
        for ($index = 1; $index <= 8; $index++) {
            RegistrationOtp::query()->create([
                'email' => 'pending.user'.$index.'@demo.local',
                'otp_hash' => bcrypt((string) (100000 + $index)),
                'expires_at' => now()->addMinutes(15 + $index),
                'verify_attempts' => $index % 2,
                'last_sent_at' => now()->subMinutes($index),
                'verified_at' => null,
                'verification_token' => null,
                'verification_token_expires_at' => null,
                'consumed_at' => null,
            ]);
        }
    }

    /**
     * @param  array<string, array<int, User>>  $usersByRole
     */
    private function seedNotificationsAndTokens(array $usersByRole): void
    {
        $notifiableUsers = array_merge(
            $usersByRole['doctor'],
            $usersByRole['lab_manager'],
            $usersByRole['delivery']
        );

        foreach (array_slice($notifiableUsers, 0, 12) as $index => $user) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\DemoNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => 'إشعار تجريبي',
                    'message' => 'إشعار رقم '.$this->arabicNumber($index + 1).' للمستخدم '.$user->email,
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => $index % 2 === 0 ? now()->subDay() : null,
                'created_at' => now()->subHours($index + 1),
                'updated_at' => now()->subHours($index + 1),
            ]);
        }

        $tokenUsers = [
            $usersByRole['system_admin'][0] ?? null,
            $usersByRole['doctor'][0] ?? null,
            $usersByRole['lab_manager'][0] ?? null,
        ];

        foreach ($tokenUsers as $tokenUser) {
            if ($tokenUser === null) {
                continue;
            }

            $tokenUser->createToken('demo-token-'.Str::lower(str_replace(' ', '-', $tokenUser->name)), ['*']);
        }
    }
}
