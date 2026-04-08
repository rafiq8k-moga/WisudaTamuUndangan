<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tamu extends Model
{
    protected $fillable = [
        'nama',
        'absen',
        'kapan_diabsen',
        'qr_code',
    ];

    protected $casts = [
        'absen' => 'boolean',
        'kapan_diabsen' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($tamu) {
            $tamu->qr_code = json_encode([
                'id' => 0,
                'nama' => $tamu->nama,
            ]);
        });

        static::created(function ($tamu) {
            $tamu->qr_code = json_encode([
                'id' => $tamu->id,
                'nama' => $tamu->nama,
            ]);
            $tamu->saveQuietly();
        });

        static::updating(function ($tamu) {
            if ($tamu->isDirty('nama')) {
                $tamu->qr_code = json_encode([
                    'id' => $tamu->id,
                    'nama' => $tamu->nama,
                ]);
            }
        });
    }
}
