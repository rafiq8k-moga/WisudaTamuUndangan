<?php

use App\Http\Controllers\AbsensiController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AbsensiController::class, 'scanner']);
Route::get('/scanner', [AbsensiController::class, 'scanner']);
