<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::middleware('auth:api')->group(function () {
    Route::get('pusher/beams-auth', [AuthController::class, 'beamsToken']);
});
