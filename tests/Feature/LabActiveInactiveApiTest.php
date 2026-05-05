<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabActiveInactiveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_labs_index_returns_only_active_labs(): void
    {
        $user = User::query()->create([
            'name' => 'Doctor One',
            'email' => 'doctor.one@example.com',
            'password' => 'Secret1234',
        ]);

        Sanctum::actingAs($user);

        $activeLab = Lab::query()->create([
            'name' => 'Active Lab',
            'license_number' => 'LIC-ACTIVE-001',
            'phone' => '0930000001',
            'address' => 'Damascus',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'is_active' => true,
        ]);

        Lab::query()->create([
            'name' => 'Inactive Lab',
            'license_number' => 'LIC-INACTIVE-001',
            'phone' => '0930000002',
            'address' => 'Aleppo',
            'latitude' => 36.2021,
            'longitude' => 37.1343,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/auth/labs?per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $activeLab->id)
            ->assertJsonPath('data.data.0.name', 'Active Lab');
    }

    public function test_inactive_labs_endpoint_returns_only_inactive_labs(): void
    {
        $user = User::query()->create([
            'name' => 'Doctor Two',
            'email' => 'doctor.two@example.com',
            'password' => 'Secret1234',
        ]);

        Sanctum::actingAs($user);

        Lab::query()->create([
            'name' => 'Active Lab Two',
            'license_number' => 'LIC-ACTIVE-002',
            'phone' => '0930000003',
            'address' => 'Homs',
            'latitude' => 34.7300,
            'longitude' => 36.7099,
            'is_active' => true,
        ]);

        $inactiveLab = Lab::query()->create([
            'name' => 'Inactive Lab Two',
            'license_number' => 'LIC-INACTIVE-002',
            'phone' => '0930000004',
            'address' => 'Latakia',
            'latitude' => 35.5317,
            'longitude' => 35.7901,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/auth/labs/inactive?per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $inactiveLab->id)
            ->assertJsonPath('data.data.0.name', 'Inactive Lab Two');
    }

    public function test_new_labs_default_to_inactive(): void
    {
        $lab = Lab::query()->create([
            'name' => 'Default State Lab',
            'license_number' => 'LIC-DEFAULT-001',
            'phone' => '0930000005',
            'address' => 'Tartus',
            'latitude' => 34.8890,
            'longitude' => 35.8866,
        ]);

        $this->assertFalse((bool) $lab->is_active);
    }
}
