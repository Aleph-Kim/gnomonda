<?php

use App\Http\Controllers\AccessController;
use Illuminate\Support\Facades\Route;

Route::get('/password', [AccessController::class, 'form'])->name('access.form');
Route::post('/password', [AccessController::class, 'verify'])->name('access.verify');

Route::get('/', function () {
    return view('attendance.index');
})->middleware('site.access')->name('home');
