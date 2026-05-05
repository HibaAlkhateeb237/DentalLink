<?php

namespace Database\Seeders;

use App\Models\ToothShade;
use Illuminate\Database\Seeder;

class ToothShadeSeeder extends Seeder
{
    public function run(): void
    {
        $shades = [
            ['code' => 'A1', 'color_hex' => '#D4A574'],
            ['code' => 'A2', 'color_hex' => '#D4A05F'],
            ['code' => 'A3', 'color_hex' => '#D4984A'],
            ['code' => 'A3.5', 'color_hex' => '#D49045'],
            ['code' => 'A4', 'color_hex' => '#C49858'],
            ['code' => 'B1', 'color_hex' => '#D9C4A0'],
            ['code' => 'B2', 'color_hex' => '#D4B896'],
            ['code' => 'B3', 'color_hex' => '#C4A890'],
            ['code' => 'B4', 'color_hex' => '#B8956C'],
            ['code' => 'C1', 'color_hex' => '#C8A882'],
            ['code' => 'C2', 'color_hex' => '#C4987A'],
            ['code' => 'C3', 'color_hex' => '#B48A6F'],
            ['code' => 'C4', 'color_hex' => '#9C7A5E'],
            ['code' => 'D2', 'color_hex' => '#C89066'],
            ['code' => 'D3', 'color_hex' => '#B87C5C'],
            ['code' => 'D4', 'color_hex' => '#A86A50'],
        ];

        foreach ($shades as $index => $shade) {
            ToothShade::query()->updateOrCreate(
                ['code' => $shade['code']],
                [
                    'name' => $shade['code'],
                    'color_hex' => $shade['color_hex'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
