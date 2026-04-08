<?php

namespace App\Http\Controllers;

use App\Models\Tamu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class AbsensiController extends Controller
{
    public function scanner()
    {
        return view('scanner');
    }

    public function absen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tamu_id' => 'required|integer|exists:tamus,id',
        ]);

        $tamu = Tamu::findOrFail($validated['tamu_id']);

        if ($tamu->absen) {
            return response()->json([
                'success' => false,
                'message' => 'Tamu sudah absen sebelumnya',
                'data' => [
                    'nama' => $tamu->nama,
                    'kapan_diabsen' => $tamu->kapan_diabsen,
                ],
            ], 409);
        }

        $tamu->update([
            'absen' => true,
            'kapan_diabsen' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absen berhasil',
            'data' => [
                'id' => $tamu->id,
                'nama' => $tamu->nama,
                'absen' => $tamu->absen,
                'kapan_diabsen' => $tamu->kapan_diabsen,
            ],
        ]);
    }

    public function validateTamu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'nama' => 'required|string',
        ]);

        $tamu = Tamu::where('id', $validated['id'])
            ->where('nama', $validated['nama'])
            ->first();

        if (!$tamu) {
            return response()->json([
                'valid' => false,
                'message' => 'Data tamu tidak valid',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'data' => [
                'id' => $tamu->id,
                'nama' => $tamu->nama,
                'absen' => $tamu->absen,
                'kapan_diabsen' => $tamu->kapan_diabsen,
            ],
        ]);
    }

    public function bulkDownloadQR(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['message' => 'Tidak ada tamu yang dipilih'], 400);
        }

        $tamus = Tamu::whereIn('id', $ids)->get();

        if ($tamus->isEmpty()) {
            return response()->json(['message' => 'Tamu tidak ditemukan'], 404);
        }

        $zipFileName = 'qr-codes-' . now()->format('Y-m-d-His') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Ensure temp directory exists
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($tamus as $tamu) {
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($tamu->qr_code);
            $qrImage = file_get_contents($qrUrl);

            // Clean filename: remove special chars, keep alphanumeric and spaces
            $cleanName = preg_replace('/[^a-zA-Z0-9\s]/', '', $tamu->nama);
            $cleanName = trim($cleanName);
            $fileName = $cleanName . '.png';

            // Handle duplicate names
            $counter = 1;
            $originalName = $fileName;
            while ($zip->locateName($fileName) !== false) {
                $fileName = pathinfo($originalName, PATHINFO_FILENAME) . '_' . $counter . '.png';
                $counter++;
            }

            $zip->addFromString($fileName, $qrImage);
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
