<?php

namespace App\Filament\Resources\Attachments\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttachmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                Section::make('Files')
                    ->description('Images, PDFs, documents and archives. Up to 10 MB each.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('files')
                            ->hiddenLabel()
                            ->collection('files')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            // Filament randomises names by default. Media
                            // library gives each file its own numbered folder,
                            // so keeping the original name is safe and makes
                            // downloads meaningful.
                            ->preserveFilenames()
                            // Keep this in step with media-library.max_file_size
                            // (10 MB); Filament expects kilobytes.
                            ->maxSize(10 * 1024)
                            ->maxFiles(20)
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                                'image/webp',
                                'image/svg+xml',
                                'application/pdf',
                                'text/plain',
                                'text/csv',
                                'application/zip',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->helperText('Drag files in, or click to browse.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
