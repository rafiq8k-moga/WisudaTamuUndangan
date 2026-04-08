<?php

use App\Http\Controllers\AbsensiController;
use Illuminate\Support\Facades\Route;

Route::post('/absen', [AbsensiController::class, 'absen']);
Route::get('/validate-tamu', [AbsensiController::class, 'validateTamu']);
Route::post('/bulk-download-qr', [AbsensiController::class, 'bulkDownloadQR']);
