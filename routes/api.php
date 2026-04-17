<?php

// routes/api.php
use App\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

Route::get('/labs', [LabController::class, 'index'])->name('labs.index');
Route::post('/labs/search', [LabController::class, 'search'])->name('labs.search');


Route::get('/labs/top-rated', [LabController::class, 'topRated'])->name('labs.top-rated');
Route::get('/labs/nearby', [LabController::class, 'nearby'])->name('labs.nearby');
Route::get('/labs/suggested', [LabController::class, 'suggested'])->name('labs.suggested');
Route::get('/labs/most-ordered', [LabController::class, 'mostOrdered'])->name('labs.most-ordered');
Route::get('/labs/{lab}', [LabController::class, 'show'])->name('labs.show');
