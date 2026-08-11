<?php

use App\Http\Controllers\Api\AttendanceRecordController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\SlackCommandController;
use Illuminate\Support\Facades\Route;

Route::post('slack/commands', [SlackCommandController::class, 'handle'])
    ->middleware('slack.verify');

Route::middleware(['web', 'site.access'])->group(function () {
    Route::get('holidays', [HolidayController::class, 'index']);

    Route::get('attendance-records/forecast', [AttendanceRecordController::class, 'forecast']);
    Route::get('attendance-records/forecast-range', [AttendanceRecordController::class, 'forecastRange']);

    Route::apiResource('attendance-records', AttendanceRecordController::class)
        ->only(['index', 'store', 'destroy']);
});
