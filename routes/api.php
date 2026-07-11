<?php

use App\Http\Controllers\Api\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

Route::apiResource('attendance-records', AttendanceRecordController::class)
    ->only(['index', 'store', 'destroy']);
