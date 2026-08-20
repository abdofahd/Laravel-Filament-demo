<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});
