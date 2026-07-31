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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

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
        $this->seedTasksAndWorkSessions($orders, $departmentsByLab, $technicianIdsByDepartment);
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
     * @return array<int, Lab>
     */
    private function seedLabs(): array
    {
        $labs = [];

        $labsData = [
            [
                'name' => 'Sham Dental Lab',
                'description' => 'A leading dental laboratory specializing in high-quality crowns, bridges, and digital dentistry solutions.',
                'address' => 'Mazzeh, Damascus',
                'latitude' => 33.5102,
                'longitude' => 36.2384,
                'photo' => 'labs/lab1.png',
            ],
            [
                'name' => 'Elite Dental Lab',
                'description' => 'Premium dental prosthetics crafted with advanced technologies and aesthetic precision.',
                'address' => 'Abu Rummaneh, Damascus',
                'latitude' => 33.5138,
                'longitude' => 36.2765,
                'photo' => 'labs/lab2.png',
            ],
            [
                'name' => 'Smile Tech Lab',
                'description' => 'Specialized in innovative orthodontic appliances and modern cosmetic smile makeovers.',
                'address' => 'Kafar Souseh, Damascus',
                'latitude' => 33.4862,
                'longitude' => 36.2921,
                'photo' => 'labs/lab3.png',
            ],
            [
                'name' => 'Golden Crown Lab',
                'description' => 'Providing reliable, durable, and affordable dental restorations for clinics across Damascus.',
                'address' => 'Baramkeh, Damascus',
                'latitude' => 33.5077,
                'longitude' => 36.2788,
                'photo' => 'labs/lab4.png',
            ],
            [
                'name' => 'Future Dental Lab',
                'description' => 'Your partner in digital smile design (DSD) and full-arch implant rehabilitations.',
                'address' => 'Bab Touma, Damascus',
                'latitude' => 33.5130,
                'longitude' => 36.3062,
                'photo' => 'labs/lab5.png',
            ],
            [
                'name' => 'Bright Smile Lab',
                'description' => 'Expert technicians focusing on removable dentures and flexible partials.',
                'address' => 'Midan, Damascus',
                'latitude' => 33.4973,
                'longitude' => 36.3005,
                'photo' => 'labs/lab6.png',
            ],
            [
                'name' => 'Advanced Dental Lab',
                'description' => 'Equipped with the latest CAD/CAM milling systems to ensure micro-precision and rapid delivery.',
                'address' => 'Dummar, Damascus',
                'latitude' => 33.5444,
                'longitude' => 36.2321,
                'photo' => 'labs/lab7.png',
            ],
            [
                'name' => 'Pearl Dental Lab',
                'description' => 'Dedicated to natural-looking zirconia restorations and high-end porcelain veneers.',
                'address' => 'Jaramana, Damascus',
                'latitude' => 33.4771,
                'longitude' => 36.3387,
                'photo' => 'labs/lab8.png',
            ],

            // Labs inactive
            [
                'name' => 'Inactive Dental Lab 1',
                'description' => 'This laboratory is currently closed for maintenance and upgrading facilities.',
                'address' => 'Qudsaya, Damascus',
                'latitude' => 33.5480,
                'longitude' => 36.2145,
                'photo' => 'labs/lab9.png',
                'is_active' => 0,
            ],
            [
                'name' => 'Inactive Dental Lab 2',
                'description' => 'Temporary inactive due to administrative and relicensing procedures.',
                'address' => 'Harasta, Damascus',
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

            if ($index === 0) {
                $lab->update(['stripe_account_id' => 'acct_1Tu5f32F7fiRM0kt']);
            }

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
            'name' => 'System Admin',
            'email' => 'system.admin@dentalink.local',
            'phone' => '0999000000',
            'password' => 'Admin@123456',
            'location' => 'Damascus',
            'location_lat' => 33.5138000,
            'location_lng' => 36.2765000,
        ]);
        $usersByRole['system_admin'][] = $admin;

        foreach ($labs as $index => $lab) {
            $manager = User::query()->create([
                'name' => 'Lab Manager '.($index + 1),
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
                'name' => 'Doctor '.$index,
                'email' => 'doctor'.$index.'@demo.local',
                'phone' => '09992'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
                'location' => 'Clinic '.$index,
                'location_lat' => 33.5000000 + ($index * 0.0010000),
                'location_lng' => 36.2500000 + ($index * 0.0010000),
            ]);
        }

        for ($index = 1; $index <= 3; $index++) {
            $usersByRole['receptionist'][] = User::query()->create([
                'name' => 'Receptionist '.$index,
                'email' => 'receptionist'.$index.'@demo.local',
                'phone' => '09993'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
                'lab_name' => $firstLabName,
            ]);
        }

        for ($index = 1; $index <= 20; $index++) {
            $usersByRole['department_manager'][] = User::query()->create([
                'name' => 'Department Manager '.$index,
                'email' => 'department.manager'.$index.'@demo.local',
                'phone' => '09994'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'password' => 'Password@123',
            ]);
        }

        foreach ($labs as $labIndex => $lab) {
            for ($slot = 1; $slot <= 3; $slot++) {
                $technicianNumber = ($labIndex * 3) + $slot;

                $usersByRole['lab_technician'][] = User::query()->create([
                    'name' => 'Technician '.$technicianNumber,
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
                'name' => 'Delivery '.$index,
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

        $departmentNames = ['Gypsum', 'Edges', 'Design', 'Cermics', 'Polishing'];
        $compensationTypes = ['Zircon Crown', 'E-Max Veneer', 'Implant Abutment', 'Temporary Crown'];
        $firstLabId = $labs[0]->id ?? null;

        foreach ($labs as $lab) {
            $departmentsByLab[$lab->id] = [];
            $sortOrder = 1;

            // Create operational departments
            foreach ($departmentNames as $name) {
                $departmentsByLab[$lab->id][] = Department::query()->create([
                    'lab_id' => $lab->id,
                    'name' => $name,
                    'description' => $name.' department for '.$lab->name,
                    'sort_order' => $sortOrder++,
                ]);
            }

            if ($firstLabId !== null && $lab->id === $firstLabId) {
                $departmentsByLab[$lab->id][] = Department::query()->create([
                    'lab_id' => $lab->id,
                    'name' => 'Reception',
                    'description' => 'Reception department for '.$lab->name,
                    'sort_order' => 0,
                ]);
            }

            $departmentsByLab[$lab->id][] = Department::query()->create([
                'lab_id' => $lab->id,
                'name' => 'Delivery',
                'description' => 'Delivery and Logistics department for '.$lab->name,
                'sort_order' => 0,
            ]);

            // Create Management department for lab manager
            Department::query()->create([
                'lab_id' => $lab->id,
                'name' => 'Management',
                'is_management' => true,
                'sort_order' => 0, // قيمة ثابتة تدل على أنه ليس جزءاً من مسار الحركة التصنيعية
            ]);

            foreach ($compensationTypes as $index => $typeName) {
                $code = Str::slug($typeName, '_');
                $compensation = DentalCompensationType::query()->updateOrCreate(
                    [
                        'lab_id' => $lab->id,
                        'code' => $code,
                    ],
                    [
                        'name' => $typeName,
                        'category' => 'other', // default category
                        'description' => $typeName.' reference pricing',
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
                if ($firstLabId !== null && $labId === $firstLabId && $department->name === 'Reception') {
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

                // 🔥 5. الإضافة المباشرة لقسم التوصيل (Delivery) لإسناد الموظف المخصص لهذا المخبر
                if ($department->name === 'Delivery') {
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

                // تخطي قسم الإدارة العامة لأنه مخصص لـ Lab Manager وليس لمدير القسم
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

                // إسناد الفنيين (Technicians) للأقسام كما هي دون تغيير
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

        // إسناد مدراء المخابر لأقسام الـ Management الأساسية (باقي الكود كما هو)
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
        $patientNames = ['Ali', 'Ahmad', 'Omar', 'Laila', 'Nour', 'Yousef'];

        $orders = [];
        $index = 0;

        foreach ($doctors as $doctor) {
            for ($count = 0; $count < 4; $count++) {
                $lab = $labs[($index + $count) % count($labs)];
                $status = $statuses[$index % count($statuses)];
                $priority = $priorities[$index % count($priorities)];
                $price = 150 + (($index % 7) * 45);
                $remainingAmount = in_array($status, ['completed'], true)
                    ? ($index % 3 === 0 ? 0 : 35)
                    : $price;

                $receivedAt = CarbonImmutable::now()->subHours($index % 6);
                $deliveredAt = $receivedAt->addDays($priority === 'urgent' ? 2 : 3);

                $order = Order::query()->create([
                    'user_id' => 12,
                        //$doctor->id,
                    'lab_id' => $lab->id,
                    'patient_name' => $patientNames[$index % count($patientNames)],
                    'qr_code' => (string) Str::uuid(),
                    'priority' => $priority,
                    'status' => $status,
                    'order_type' => $types[$index % count($types)],

                    'case_type' => $caseTypes[$index % count($caseTypes)],
                    'notes' => 'Demo order #'.($index + 1),

                    'price' => $price,
                    'remaining_amount' => $remainingAmount,
                    'serial_number' => null,
                    'received_at' => $receivedAt,
                    'delivered_at' => $deliveredAt,
                ]);

                $order->serial_number = sprintf('ORD-%06d', $order->id);
                $order->save();

                $this->seedOrderQrImage($order);
                $orders[] = $order->fresh();

                $index++;
            }
        }

        foreach ($labs as $lab) {
            $doctor = $doctors[array_rand($doctors)];
            $patientName = $patientNames[array_rand($patientNames)];
            $type = $types[array_rand($types)];
            $caseType = $caseTypes[array_rand($caseTypes)];
            $price = 150 + random_int(0, 5) * 45;

            $order = Order::query()->create([
                'user_id' => $doctor->id,
                'lab_id' => $lab->id,
                'patient_name' => $patientName,
                'qr_code' => (string) Str::uuid(),
                'priority' => 'normal',
                'status' => OrderStatus::NEW,
                'order_type' => $type,
                'case_type' => $caseType,
                'notes' => 'New demo order for '.$lab->name,
                'price' => $price,
                'remaining_amount' => $price,
                'serial_number' => null,
                'received_at' => now(),
                'delivered_at' => now()->addDays(3),
            ]);

            $order->serial_number = sprintf('ORD-%06d', $order->id);
            $order->save();

            $this->seedOrderQrImage($order);
            $orders[] = $order->fresh();
        }

        return $orders;
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
    private function seedTasksAndWorkSessions(array $orders, array $departmentsByLab, array $technicianIdsByDepartment): void
    {
        // 1. جلب أقسام المخبر الأول فقط لضمان الترتيب الثابت (1: Gypsum, 2: Edges, 3: Design)
        $firstLabDepartments = $departmentsByLab[1] ?? [];

        // 2. فلترة المصفوفة أو جلب أول 5 طلبات تابعة للمخبر الأول حصراً (lab_id = 1)
        $firstLabOrders = collect($orders)->where('lab_id', 1)->take(5)->values();

        // تأكدي من وجود الأقسام والطلبات الكافية لتجنب خطأ الـ Index Undefined
        if (count($firstLabDepartments) < 3 || $firstLabOrders->count() < 5) {
            return;
        }

        $gypsum = $firstLabDepartments[0];
        $edges = $firstLabDepartments[1];
        $design = $firstLabDepartments[2];

        // جلب معرّفات الفنيين لكل قسم في المخبر الأول
        $techGypsum = $technicianIdsByDepartment[$gypsum->id] ?? null;
        $techEdges = $technicianIdsByDepartment[$edges->id] ?? null;
        $techDesign = $technicianIdsByDepartment[$design->id] ?? null;

        // --- [الطلب الأول: بانتظار الإسناد في قسم Gypsum] ---
        $order1 = $firstLabOrders[0];
        $order1->update(['status' => OrderStatus::PENDING]);
        Task::query()->create([
            'order_id' => $order1->id,
            'department_id' => $gypsum->id,
            'user_id' => null, // لم يُسند بعد ليعبئ تبويب "مهام للإسناد"
            'status' => TaskStatus::PENDING_ASSIGNMENT,
        ]);

        // --- [الطلب الثاني: تم الإسناد للفني ولكن لم يبدأ بعد] ---
        $order2 = $firstLabOrders[1];
        $order2->update(['status' => OrderStatus::IN_PROGRESS]);
        Task::query()->create([
            'order_id' => $order2->id,
            'department_id' => $gypsum->id,
            'user_id' => $techGypsum,
            'status' => TaskStatus::ASSIGNED,
        ]);

        // --- [الطلب الثالث: قيد العمل عليه من قبل فني Gypsum] ---
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

        // --- [الطلب الرابع: انتهى الفني وينتظر تقييم المدير (شاشتكِ المرفقة)] ---
        $order4 = $firstLabOrders[3];
        $order4->update(['status' => OrderStatus::IN_PROGRESS]);
        $task4 = Task::query()->create([
            'order_id' => $order4->id,
            'department_id' => $gypsum->id,
            'user_id' => $techGypsum,
            'status' => TaskStatus::PENDING_REVIEW, // يظهر في تبويب "بحاجة لتقييم"
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task4->id,
            'start_time' => now()->subHours(4),
            'end_time' => now()->subHours(1),
            'status' => 'completed',
            'note' => 'تم إنهاء النحت والتجهيز بالكامل ميكانيكياً',
        ]);

        // --- [الطلب الخامس: مرّ بالأقسام بالكامل وهو الآن منتهٍ ومكتمل] ---
        $order5 = $firstLabOrders[4];
        $order5->update(['status' => OrderStatus::COMPLETED]);

        // 1. مهمة Gypsum المنتهية تاريخياً
        $task5 = Task::query()->create([
            'order_id' => $order5->id,
            'department_id' => $gypsum->id,
            'user_id' => $techGypsum,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now()->subDays(2),
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task5->id,
            'start_time' => now()->subDays(2)->subHours(5),
            'end_time' => now()->subDays(2)->subHours(2),
            'status' => 'completed',
            'note' => 'تم الانتهاء من صب ونحت الخزف بنجاح',
        ]);

        // 2. مهمة Edges المنتهية تاريخياً
        $task6 = Task::query()->create([
            'order_id' => $order5->id,
            'department_id' => $edges->id,
            'user_id' => $techEdges,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now()->subDays(1),
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task6->id,
            'start_time' => now()->subDays(1)->subHours(4),
            'end_time' => now()->subDays(1)->subHours(1),
            'status' => 'completed',
            'note' => 'تم تجهيز أسلاك وتقويم الحالة بالكامل',
        ]);

        // 3. مهمة Design المنتهية تاريخياً (آخر قسم)
        $task7 = Task::query()->create([
            'order_id' => $order5->id,
            'department_id' => $design->id,
            'user_id' => $techDesign,
            'status' => TaskStatus::COMPLETED,
            'approved_at' => now()->subHours(5),
        ]);
        TaskWorkSession::query()->create([
            'task_id' => $task7->id,
            'start_time' => now()->subHours(9),
            'end_time' => now()->subHours(5),
            'status' => 'completed',
            'note' => 'إنهاء تحضير دعم الزرعة النهائي وإرسالها للمدير للتقييم الأخير',
        ]);

        // Seed tasks for all remaining orders across all labs
        $handledIds = $firstLabOrders->pluck('id')->toArray();

        foreach ($orders as $order) {
            if (in_array($order->id, $handledIds, true)) {
                continue;
            }

            $labDepts = collect($departmentsByLab[$order->lab_id] ?? [])
                ->reject(fn (Department $d) => $d->is_management || in_array($d->name, ['Reception', 'Delivery']))
                ->values();

            if ($labDepts->isEmpty()) {
                continue;
            }

            $firstDept = $labDepts[0];

            match ($order->status) {
                OrderStatus::PENDING => Task::query()->create([
                    'order_id' => $order->id,
                    'department_id' => $firstDept->id,
                    'status' => TaskStatus::PENDING_ASSIGNMENT,
                ]),
                OrderStatus::IN_PROGRESS => Task::query()->create([
                    'order_id' => $order->id,
                    'department_id' => $firstDept->id,
                    'user_id' => $technicianIdsByDepartment[$firstDept->id] ?? null,
                    'status' => rand(0, 1) ? TaskStatus::ASSIGNED : TaskStatus::IN_PROGRESS,
                ]),
                OrderStatus::TRY_ON, OrderStatus::RESEND_WRONG_IMPRESSION => Task::query()->create([
                    'order_id' => $order->id,
                    'department_id' => $firstDept->id,
                    'user_id' => $technicianIdsByDepartment[$firstDept->id] ?? null,
                    'status' => rand(0, 1) ? TaskStatus::ASSIGNED : TaskStatus::IN_PROGRESS,
                ]),
                OrderStatus::COMPLETED => collect([$labDepts[0], $labDepts->get(1), $labDepts->get(2)])
                    ->filter()
                    ->each(fn (Department $dept) => Task::query()->create([
                        'order_id' => $order->id,
                        'department_id' => $dept->id,
                        'user_id' => $technicianIdsByDepartment[$dept->id] ?? null,
                        'status' => TaskStatus::COMPLETED,
                        'approved_at' => now()->subHours(rand(1, 48)),
                    ])),
                default => Task::query()->create([
                    'order_id' => $order->id,
                    'department_id' => $firstDept->id,
                    'status' => TaskStatus::PENDING_ASSIGNMENT,
                ]),
            };
        }
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

            $payment = Payment::query()->create([
                'user_id' => $order->user_id,
                'amount' => $paidAmount,
                'payment_method' => $methods[$index % count($methods)],
                'paid_at' => now()->subDays($index % 20),
            ]);

            DB::table('payment_order')->insert([
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $paidAmount,
                'created_at' => now(),
                'updated_at' => now(),
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
                    'description' => "Payment received for Order #{$order->serial_number}",
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

            $status = $deliveryStatuses[$index % count($deliveryStatuses)];
            $deliveryUser = $deliveryUsers[$index % count($deliveryUsers)];

            $direction = match ($order->status) {
                OrderStatus::COMPLETED => DeliveryTaskDirection::TO_DOCTOR,
                default => DeliveryTaskDirection::TO_LAB,
            };

            DeliveryTask::query()->create([
                'order_id' => $order->id,
                'user_id' => 77 ,
                //$deliveryUser->id,
                'status' => $status,
                'direction' => $direction,
                'picked_at' => in_array($status, DeliveryStatus::PICKED_STATUSES, true)
                    ? now()->subDays(($index % 10) + 1)
                    : null,
                'delivered_at' => $status === DeliveryStatus::DELIVERED
                    ? now()->subDays($index % 7)
                    : null,
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
                'comment' => 'Demo review for order #'.$order->id,
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
                'case_name' => 'Portfolio Case #'.$order->id,
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
                    'title' => 'Demo notification',
                    'message' => 'Notification #'.($index + 1).' for '.$user->email,
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
