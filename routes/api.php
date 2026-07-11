<?php

use App\Http\Controllers\Api\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

Route::get('attendance-records/forecast', [AttendanceRecordController::class, 'forecast']);

Route::apiResource('attendance-records', AttendanceRecordController::class)
    ->only(['index', 'store', 'destroy']);
