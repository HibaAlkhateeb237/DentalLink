<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Lab;
use App\Models\Order;
use App\Models\PortfolioCase;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskWorkSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabPortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_published_portfolio_cases_for_a_lab(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $doctorRole = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$doctorRole->id]);

        Sanctum::actingAs($user);

        $lab = Lab::query()->create([
            'name' => 'Dental Art Lab',
            'phone' => '0999999999',
            'address' => 'Damascus',
            'latitude' => 33.5138,
            'is_active' => true,
            'longitude' => 36.2765,
        ]);

        $orderOne = $this->createOrder($lab, $user, 'completed');
        $orderTwo = $this->createOrder($lab, $user, 'completed');

        PortfolioCase::query()->create([
            'order_id' => $orderOne->id,
            'case_name' => 'Zircon Crown',
            'before_image_path' => 'labs/portfolio/before-1.jpg',
            'after_image_path' => 'labs/portfolio/after-1.jpg',
            'duration_minutes' => 120,
            'is_published' => true,
        ]);

        PortfolioCase::query()->create([
            'order_id' => $orderTwo->id,
            'case_name' => 'Hidden Case',
            'before_image_path' => 'labs/portfolio/before-2.jpg',
            'after_image_path' => 'labs/portfolio/after-2.jpg',
            'duration_minutes' => 70,
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/auth/labs/'.$lab->id.'/portfolio');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('lab_portfolio.retrieved_successfully'));

        $cases = $response->json('data.data');

        if (! is_array($cases)) {
            $cases = $response->json('data');
        }

        $this->assertIsArray($cases);
        $this->assertCount(1, $cases);
        $this->assertSame('Zircon Crown', $cases[0]['case_name']);
    }

    public function test_receptionist_can_view_and_create_portfolio(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Reception Lab',
            'phone' => '0999111222',
            'address' => 'Homs',
            'latitude' => 34.7,
            'longitude' => 36.7,
            'is_active' => true,
        ]);

        $doctor = User::factory()->create();
        $order = $this->createOrder($lab, $doctor, 'completed');

        $receptionist = User::factory()->create();
        $role = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail();
        $receptionist->roles()->syncWithoutDetaching([$role->id]);

        Sanctum::actingAs($receptionist);

        $this->getJson('/api/auth/labs/'.$lab->id.'/portfolio')->assertOk();

        Storage::fake('public');

        $response = $this->postJson('/api/auth/labs/'.$lab->id.'/portfolio', [
            'order_id' => $order->id,
            'case_name' => 'Reception Case',
            'before_image' => UploadedFile::fake()->image('before.jpg'),
            'after_image' => UploadedFile::fake()->image('after.jpg'),
            'is_published' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201);
    }

    public function test_lab_manager_can_create_portfolio_case_and_duration_is_calculated_from_work_sessions(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Smile Lab',
            'phone' => '0999000000',
            'address' => 'Aleppo',
            'latitude' => 36.2021,
            'is_active' => true,
            'longitude' => 37.1343,
        ]);

        $manager = User::factory()->create([
            'lab_name' => $lab->name,
        ]);

        $labManagerRoleId = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($labManagerRoleId !== null) {
            $manager->roles()->syncWithoutDetaching([$labManagerRoleId]);
        }

        Sanctum::actingAs($manager);

        $doctor = User::factory()->create();
        $order = $this->createOrder($lab, $doctor, 'completed');

        $department = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Ceramics',
            'description' => null,
        ]);

        $task = Task::query()->create([
            'order_id' => $order->id,
            'department_id' => $department->id,
            'user_id' => null,
            'status' => 'completed',
            'approved_at' => now(),
        ]);

        TaskWorkSession::query()->create([
            'task_id' => $task->id,
            'start_time' => now()->subHours(5),
            'end_time' => now()->subHours(4),
            'status' => 'completed',
            'note' => null,
        ]);

        TaskWorkSession::query()->create([
            'task_id' => $task->id,
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHours(2)->subMinutes(30),
            'status' => 'completed',
            'note' => null,
        ]);

        $response = $this->postJson('/api/auth/labs/'.$lab->id.'/portfolio', [
            'order_id' => $order->id,
            'case_name' => 'E-Max Veneer',
            'before_image' => UploadedFile::fake()->image('before.jpg'),
            'after_image' => UploadedFile::fake()->image('after.jpg'),
            'is_published' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 201)
            ->assertJsonPath('message', __('lab_portfolio.created_successfully'));

        $caseData = $response->json('data.data');

        if (! is_array($caseData)) {
            $caseData = $response->json('data');
        }

        $this->assertIsArray($caseData);
        $this->assertSame('E-Max Veneer', $caseData['case_name']);
        $this->assertSame(90, $caseData['duration_minutes']);

        $portfolioCase = PortfolioCase::query()->first();

        $this->assertNotNull($portfolioCase);
        $this->assertNotNull($portfolioCase?->before_image_path);
        $this->assertNotNull($portfolioCase?->after_image_path);

        if ($portfolioCase !== null) {
            $this->assertTrue(Storage::disk('public')->exists($portfolioCase->before_image_path));
            $this->assertTrue(Storage::disk('public')->exists($portfolioCase->after_image_path));
        }
    }

    public function test_receptionist_can_update_portfolio_case(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Update Lab',
            'phone' => '0999222333',
            'address' => 'Latakia',
            'latitude' => 35.5,
            'longitude' => 35.8,
            'is_active' => true,
        ]);

        $doctor = User::factory()->create();
        $order = $this->createOrder($lab, $doctor, 'completed');

        $portfolioCase = PortfolioCase::query()->create([
            'order_id' => $order->id,
            'case_name' => 'Original Case',
            'before_image_path' => 'labs/portfolio/before-orig.jpg',
            'after_image_path' => 'labs/portfolio/after-orig.jpg',
            'duration_minutes' => 60,
            'is_published' => true,
        ]);

        $receptionist = User::factory()->create();
        $role = Role::query()->where('name', 'receptionist')->where('guard_name', 'sanctum')->firstOrFail();
        $receptionist->roles()->syncWithoutDetaching([$role->id]);
        $receptionist->lab_name = $lab->name;
        $receptionist->save();

        Sanctum::actingAs($receptionist);

        Storage::fake('public');

        $response = $this->putJson('/api/auth/labs/'.$lab->id.'/portfolio/'.$portfolioCase->id, [
            'case_name' => 'Updated Case Name',
            'is_published' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('lab_portfolio.updated_successfully'));

        $data = $response->json('data.data');
        if (! is_array($data)) {
            $data = $response->json('data');
        }

        $this->assertSame('Updated Case Name', $data['case_name']);
        $this->assertFalse($data['is_published']);
    }

    public function test_lab_manager_can_update_portfolio_case(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $lab = Lab::query()->create([
            'name' => 'Manager Update Lab',
            'phone' => '0999333444',
            'address' => 'Hama',
            'latitude' => 35.1,
            'longitude' => 36.7,
            'is_active' => true,
        ]);

        $manager = User::factory()->create([
            'lab_name' => $lab->name,
        ]);

        $labManagerRoleId = Role::query()
            ->where('name', 'lab_manager')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($labManagerRoleId !== null) {
            $manager->roles()->syncWithoutDetaching([$labManagerRoleId]);
        }

        $doctor = User::factory()->create();
        $order = $this->createOrder($lab, $doctor, 'completed');

        $portfolioCase = PortfolioCase::query()->create([
            'order_id' => $order->id,
            'case_name' => 'Manager Original',
            'before_image_path' => 'labs/portfolio/before-mgr.jpg',
            'after_image_path' => 'labs/portfolio/after-mgr.jpg',
            'duration_minutes' => 45,
            'is_published' => false,
        ]);

        Sanctum::actingAs($manager);

        Storage::fake('public');

        $response = $this->putJson('/api/auth/labs/'.$lab->id.'/portfolio/'.$portfolioCase->id, [
            'case_name' => 'Manager Updated',
            'is_published' => true,
            'before_image' => UploadedFile::fake()->image('before-new.jpg'),
            'after_image' => UploadedFile::fake()->image('after-new.jpg'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', __('lab_portfolio.updated_successfully'));

        $data = $response->json('data.data');
        if (! is_array($data)) {
            $data = $response->json('data');
        }

        $this->assertSame('Manager Updated', $data['case_name']);
        $this->assertTrue($data['is_published']);
        $this->assertStringContainsString('before-new', $data['before_image']);
        $this->assertStringContainsString('after-new', $data['after_image_path']);
    }

    private function createOrder(Lab $lab, User $doctor, string $status): Order
    {
        return Order::query()->create([
            'user_id' => $doctor->id,
            'lab_id' => $lab->id,
            'qr_code' => (string) Str::uuid(),
            'priority' => 'normal',
            'status' => $status,
            'order_type' => 'digital',
            'notes' => null,
            'price' => 100,
            'remaining_amount' => 0,
        ]);
    }
}
