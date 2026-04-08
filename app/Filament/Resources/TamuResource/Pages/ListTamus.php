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
                                // If still only one column, the line itself is the name
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
        ];
    }
}
