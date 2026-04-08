<?php

namespace App\Console\Commands;

use App\Models\Tamu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateAllQR extends Command
{
    protected $signature = 'qr:generate-all';
    protected $description = 'Generate all QR codes and save to zip file in storage';

    public function handle()
    {
        $this->info('Generating QR codes...');
        
        $tamus = Tamu::all();
        
        if ($tamus->isEmpty()) {
            $this->error('No tamu data found!');
            return 1;
        }
        
        $this->info("Found {$tamus->count()} tamu records");
        
        $zipFileName = 'all-qr-codes-' . now()->format('Y-m-d-His') . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);
        
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        
        $processed = 0;
        $failed = 0;
        
        foreach ($tamus as $tamu) {
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($tamu->qr_code);
            
            $ch = curl_init($qrUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $qrImage = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || $qrImage === false) {
                $this->error("Failed to generate QR for: {$tamu->nama}");
                $failed++;
                continue;
            }
            
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
            $processed++;
            
            if ($processed % 10 === 0) {
                $this->info("Processed {$processed}/{$tamus->count()}...");
            }
        }
        
        $zip->close();
        
        $this->info("\n=====================================");
        $this->info("✓ QR Generation Complete!");
        $this->info("=====================================");
        $this->info("Total: {$tamus->count()}");
        $this->info("Success: {$processed}");
        $this->info("Failed: {$failed}");
        $this->info("\nFile saved to: storage/app/public/{$zipFileName}");
        $this->info("Download URL: /storage/{$zipFileName}");
        
        return 0;
    }
}
