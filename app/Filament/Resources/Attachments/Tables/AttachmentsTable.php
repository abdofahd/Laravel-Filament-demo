<?php

namespace App\Filament\Resources\Attachments\Tables;

use App\Models\Attachment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttachmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('preview')
                    ->label('Preview')
                    ->collection('files')
                    ->limit(3)
                    ->limitedRemainingText()
                    ->circular(false),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('media_count')
                    ->label('Files')
                    ->counts('media')
                    ->badge(),

                TextColumn::make('size')
                    ->label('Total size')
                    ->state(fn (Attachment $record): string => Attachment::formatSize($record->totalSize()))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
