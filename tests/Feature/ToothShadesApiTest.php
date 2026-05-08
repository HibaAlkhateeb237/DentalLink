<?php

namespace Tests\Feature;

use App\Models\ToothShade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToothShadesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_active_shades_ordered(): void
    {
        ToothShade::query()->create([
            'code' => 'A1', 'name' => 'A1', 'color_hex' => '#fff1', 'sort_order' => 2, 'is_active' => true,
        ]);

        ToothShade::query()->create([
            'code' => 'A2', 'name' => 'A2', 'color_hex' => '#fff2', 'sort_order' => 1, 'is_active' => true,
        ]);

        ToothShade::query()->create([
            'code' => 'X1', 'name' => 'X1', 'color_hex' => '#0000', 'sort_order' => 3, 'is_active' => false,
        ]);

        $response = $this->getJson('/api/tooth-shades');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'A2')
            ->assertJsonPath('data.1.code', 'A1');
    }
}
