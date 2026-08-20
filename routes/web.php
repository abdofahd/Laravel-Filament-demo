<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/health', function () {
    DB::connection()->getPdo();

    return response()->json([
        'status' => 'ok',
        'database' => 'connected',
    ]);
});
