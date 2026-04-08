<?php

namespace App\Filament\Resources\TamuResource\Pages;

use App\Filament\Resources\TamuResource;
use App\Models\Tamu;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListTamus extends ListRecords
{
    protected static string $resource = TamuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('importCSV')
                ->label('Import CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('warning')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('File CSV')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                        ->helperText('Upload file CSV dengan format: nama (header di baris pertama)')
                        ->disk('public')
                        ->directory('csv-imports')
                        ->maxSize(5120), // 5MB
                ])
                ->action(function (array $data) {
                    try {
                        $csvContent = Storage::disk('public')->get($data['csv_file']);
                        $lines = explode("\n", $csvContent);
                        
                        $importedCount = 0;
                        $skippedCount = 0;
                        
                        // Skip header (first line)
                        array_shift($lines);
                        
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            
                            // Handle different CSV formats
                            $data = str_getcsv($line, ',', '"');
                            if (count($data) == 1) {
                                $data = str_getcsv($line, ';', '"');
                            }
                            if (count($data) == 1) {
                                // If still only one column, line itself is the name
                                $nama = $line;
                            } else {
                                $nama = trim($data[0] ?? '');
                            }
                            
                            if (!empty($nama)) {
                                // Check if already exists
                                $exists = Tamu::where('nama', $nama)->exists();
                                if (!$exists) {
                                    Tamu::create(['nama' => $nama]);
                                    $importedCount++;
                                } else {
                                    $skippedCount++;
                                }
                            }
                        }
                        
                        // Delete uploaded file
                        Storage::disk('public')->delete($data['csv_file']);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Import CSV Berhasil')
                            ->body("Berhasil import {$importedCount} tamu baru. {$skippedCount} data dilewati karena sudah ada.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Import CSV Gagal')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('downloadAllQR')
                ->label('Download All QR')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->action(function () {
                    $tamus = Tamu::all();
                    
                    if ($tamus->isEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Tidak Ada Data')
                            ->body('Belum ada data tamu untuk di-download.')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    // Show progress notification
                    \Filament\Notifications\Notification::make()
                        ->title('Memproses QR Code')
                        ->body("Sedang generate {$tamus->count()} QR code. Mohon tunggu...")
                        ->info()
                        ->send();
                    
                    $zipFileName = 'all-qr-codes-' . now()->format('Y-m-d-His') . '.zip';
                    $zipPath = storage_path('app/temp/' . $zipFileName);
                    
                    if (!is_dir(storage_path('app/temp'))) {
                        mkdir(storage_path('app/temp'), 0755, true);
                    }
                    
                    $zip = new \ZipArchive();
                    $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                    
                    foreach ($tamus as $tamu) {
                        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($tamu->qr_code);
                        
                        // Add timeout and error handling
                        $context = stream_context_create([
                            'http' => [
                                'timeout' => 10,
                                'method' => 'GET'
                            ]
                        ]);
                        $qrImage = @file_get_contents($qrUrl, false, $context);
                        
                        if ($qrImage === false) {
                            // Skip if QR generation fails
                            continue;
                        }
                    $zip = new \ZipArchive();
                    $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                    
                    foreach ($tamus as $tamu) {
                        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($tamu->qr_code);
                        $qrImage = file_get_contents($qrUrl);
                        
                        $cleanName = preg_replace('/[^a-zA-Z0-9\s]/', '', $tamu->nama);
                        $cleanName = trim($cleanName);
                        $fileName = "({$tamu->id})_" . $cleanName . '.png';
                        
                        $counter = 1;
                        $originalName = $fileName;
                        while ($zip->locateName($fileName) !== false) {
                            $fileName = pathinfo($originalName, PATHINFO_FILENAME) . '_' . $counter . '.png';
                            $counter++;
                        }
                        
                        $zip->addFromString($fileName, $qrImage);
                    }
                    
                    $zip->close();
                    
                    $successCount = $zip->numFiles;
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Download QR Berhasil')
                        ->body("Berhasil generate {$successCount} QR code. File ZIP sedang di-download.")
                        ->success()
                        ->send();
                    
                    return response()->download($zipPath, $zipFileName, [
                        'Content-Type' => 'application/zip',
                    ])->deleteFileAfterSend(true);
                }),
        ];
    }
}
