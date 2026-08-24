<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Context only. Accounts are created with
                // `php artisan make:filament-user`; this screen manages roles.
                TextInput::make('name')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('email')
                    ->label('Email address')
                    ->disabled()
                    ->dehydrated(false),

                Section::make('Roles')
                    ->description('The permissions a user holds come from the roles ticked here.')
                    ->schema([
                        CheckboxList::make('roles')
                            ->hiddenLabel()
                            ->relationship('roles', 'name')
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable(),
                    ]),
            ]);
    }
}
