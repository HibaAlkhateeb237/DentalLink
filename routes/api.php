<?php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'app'    => config('app.name'),
    ]);
});
