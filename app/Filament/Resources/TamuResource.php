<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TamuResource\Pages;
use App\Models\Tamu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TamuResource extends Resource
{
    protected static ?string $model = Tamu::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'Tamu';
    protected static ?string $pluralModelLabel = 'Daftar Tamu';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->label('Nama Tamu / Keluarga')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('absen')
                    ->label('Sudah Absen')
                    ->boolean(),
                Tables\Columns\TextColumn::make('kapan_diabsen')
                    ->label('Waktu Absen')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                Tables\Columns\ImageColumn::make('qr_code_url')
                    ->label('QR Code')
                    ->getStateUsing(fn (Tamu $record): string => "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($record->qr_code))
                    ->size(60),
            ])
            ->defaultSort('id', 'asc')
            ->poll('3s')
            ->filters([
                Tables\Filters\TernaryFilter::make('absen')
                    ->label('Status Absen'),
            ])
            ->actions([
                Action::make('downloadQR')
                    ->label('Download QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->url(fn (Tamu $record): string => "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . urlencode($record->qr_code))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('downloadQR')
                        ->label('Download QR')
                        ->icon('heroicon-o-qr-code')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $ids = $records->pluck('id')->toArray();
                            $idsParam = implode(',', $ids);
                            
                            return redirect("/api/bulk-download-qr?ids={$idsParam}");
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTamus::route('/'),
            'create' => Pages\CreateTamu::route('/create'),
            'edit' => Pages\EditTamu::route('/{record}/edit'),
        ];
    }
}
