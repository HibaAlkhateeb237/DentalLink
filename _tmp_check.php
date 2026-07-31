<?php

use App\Models\DentalCompensationType;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$c = DentalCompensationType::first();
echo json_encode([
    'id' => $c->id,
    'name' => $c->name,
    'price' => optional($c->prices()->where('is_active', true)->first())->base_price,
]).PHP_EOL;
