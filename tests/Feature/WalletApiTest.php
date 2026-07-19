<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentUserRole;
use App\Models\Lab;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createLabManagerWithLab(): array
    {
        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $managementDept = Department::query()->create([
            'lab_id' => $lab->id,
            'name' => 'Management',
            'is_management' => true,
        ]);

        $manager = User::factory()->create();
        $role = Role::query()->where('name', 'lab_manager')->where('guard_name', 'sanctum')->firstOrFail();

        DepartmentUserRole::query()->create([
            'user_id' => $manager->id,
            'role_id' => $role->id,
            'department_id' => $managementDept->id,
        ]);

        return ['lab' => $lab, 'manager' => $manager, 'department' => $managementDept];
    }

    public function test_lab_manager_can_view_wallet(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        Wallet::query()->create([
            'lab_id' => $lab->id,
            'balance' => 150.00,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/wallet');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.balance', '150.00')
            ->assertJsonPath('data.lab_id', $lab->id);
    }

    public function test_wallet_not_found_returns_404(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/wallet');

        $response->assertNotFound();
    }

    public function test_doctor_cannot_view_wallet(): void
    {
        $this->seedRoles();

        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        Wallet::query()->create([
            'lab_id' => $lab->id,
            'balance' => 100.00,
        ]);

        $doctor = User::factory()->create();
        $role = Role::query()->where('name', 'doctor')->where('guard_name', 'sanctum')->firstOrFail();
        $doctor->roles()->syncWithoutDetaching([$role->id]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/auth/lab/wallet');

        $response->assertForbidden();
    }

    public function test_wallet_transactions_are_paginated(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        $wallet = Wallet::query()->create([
            'lab_id' => $lab->id,
            'balance' => 500.00,
        ]);

        Transaction::query()->insert(
            collect(range(1, 20))->map(fn ($i) => [
                'wallet_id' => $wallet->id,
                'type' => 'order_payment_credit',
                'amount' => 25.00,
                'balance_after' => 25.00 * $i,
                'currency' => 'USD',
                'description' => "Transaction #{$i}",
                'created_at' => now()->subDays(20 - $i),
                'updated_at' => now()->subDays(20 - $i),
            ])->toArray()
        );

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/wallet/transactions?per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data');

        $meta = $response->json('data');
        $this->assertCount(10, $meta);
    }

    public function test_wallet_transactions_are_sorted_newest_first(): void
    {
        $this->seedRoles();
        ['lab' => $lab, 'manager' => $manager] = $this->createLabManagerWithLab();

        $wallet = Wallet::query()->create([
            'lab_id' => $lab->id,
            'balance' => 300.00,
        ]);

        $t1 = Transaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'order_payment_credit',
            'amount' => 100.00,
            'balance_after' => 100.00,
            'description' => 'First',
            'created_at' => now()->subDays(2),
        ]);

        $t2 = Transaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'order_payment_credit',
            'amount' => 200.00,
            'balance_after' => 300.00,
            'description' => 'Second',
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/auth/lab/wallet/transactions');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals($t2->id, $data[0]['id']);
        $this->assertEquals($t1->id, $data[1]['id']);
    }

    public function test_system_admin_can_view_any_wallet(): void
    {
        $this->seedRoles();

        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        Wallet::query()->create([
            'lab_id' => $lab->id,
            'balance' => 999.99,
        ]);

        $admin = User::factory()->create();
        $role = Role::query()->where('name', 'system_admin')->where('guard_name', 'sanctum')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/auth/lab/wallet?lab_id={$lab->id}");

        $response->assertOk()
            ->assertJsonPath('data.balance', '999.99')
            ->assertJsonPath('data.lab_id', $lab->id);
    }

    public function test_wallet_balance_updates_on_credit(): void
    {
        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $wallet = Wallet::query()->create([
            'lab_id' => $lab->id,
            'balance' => 0,
        ]);

        $this->assertEquals('0.00', $wallet->balance);

        $wallet->increment('balance', 150.75);
        $wallet->refresh();

        $this->assertEquals('150.75', $wallet->balance);

        $wallet->increment('balance', 200.25);
        $wallet->refresh();

        $this->assertEquals('351.00', $wallet->balance);
    }

    public function test_transaction_is_immutable(): void
    {
        $wallet = Wallet::query()->create([
            'lab_id' => Lab::query()->create([
                'name' => 'Test Lab',
                'phone' => '1111111',
                'address' => 'Address',
                'latitude' => 33.5138070,
                'longitude' => 36.2765279,
            ])->id,
            'balance' => 100.00,
        ]);

        $transaction = Transaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'order_payment_credit',
            'amount' => 100.00,
            'balance_after' => 100.00,
            'description' => 'Test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transactions are immutable.');
        $transaction->update(['amount' => 999.00]);
    }

    public function test_transaction_cannot_be_deleted(): void
    {
        $wallet = Wallet::query()->create([
            'lab_id' => Lab::query()->create([
                'name' => 'Test Lab',
                'phone' => '1111111',
                'address' => 'Address',
                'latitude' => 33.5138070,
                'longitude' => 36.2765279,
            ])->id,
            'balance' => 100.00,
        ]);

        $transaction = Transaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'order_payment_credit',
            'amount' => 100.00,
            'balance_after' => 100.00,
            'description' => 'Test',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transactions cannot be deleted.');
        $transaction->delete();
    }

    public function test_balance_after_matches_running_total(): void
    {
        $lab = Lab::query()->create([
            'name' => 'Test Lab',
            'phone' => '1111111',
            'address' => 'Address',
            'latitude' => 33.5138070,
            'longitude' => 36.2765279,
        ]);

        $wallet = Wallet::query()->create([
            'lab_id' => $lab->id,
            'balance' => 0,
        ]);

        $amounts = [50.00, 120.50, 30.25];
        $runningTotal = 0;

        foreach ($amounts as $amount) {
            $runningTotal += $amount;
            $wallet->increment('balance', $amount);
            $wallet->refresh();

            Transaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'order_payment_credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => "Credit of {$amount}",
            ]);
        }

        $transactions = $wallet->transactions()->orderBy('id')->get();

        foreach ($transactions as $index => $txn) {
            $expected = array_sum(array_slice($amounts, 0, $index + 1));
            $this->assertEquals(number_format($expected, 2, '.', ''), $txn->balance_after);
        }

        $this->assertEquals(number_format($runningTotal, 2, '.', ''), $wallet->fresh()->balance);
    }
}
