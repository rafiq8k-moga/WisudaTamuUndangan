<?php

use App\Http\Controllers\AbsensiController;
use Illuminate\Support\Facades\Route;

Route::post('/absen', [AbsensiController::class, 'absen']);
Route::get('/validate-tamu', [AbsensiController::class, 'validateTamu']);
Route::get('/bulk-download-qr', [AbsensiController::class, 'bulkDownloadQR']);
Route::post('/bulk-download-qr', [AbsensiController::class, 'bulkDownloadQR']);
