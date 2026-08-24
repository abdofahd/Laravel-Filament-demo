<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Role name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('For example: manager, editor, viewer.'),

                Section::make('Permissions')
                    ->description('Tick everything this role is allowed to do.')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->hiddenLabel()
                            ->relationship('permissions', 'name')
                            // Human-readable text comes from config/permissions.php,
                            // so the labels here and the catalogue never drift apart.
                            ->descriptions(self::descriptions())
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable(),
                    ]),
            ]);
    }

    /**
     * Permission name => description, flattened from the configured groups.
     *
     * @return array<string, string>
     */
    private static function descriptions(): array
    {
        return collect(config('permissions.groups', []))
            ->collapse()
            ->all();
    }
}
