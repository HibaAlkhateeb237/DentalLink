<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = App\Models\DentalCompensationType::first();
echo json_encode([
    'id' => $c->id,
    'name' => $c->name,
    'price' => optional($c->prices()->where('is_active', true)->first())->base_price,
]).PHP_EOL;
