<?php

namespace App\Filament\Resources\Licenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LicenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('client_name')
                    ->label('Client Name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(['default' => 2]),

                TextInput::make('api_key')
                    ->label('API Key')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Generated automatically on creation.')
                    ->columnSpan(['default' => 2]),

                Select::make('plan_type')
                    ->label('Plan')
                    ->options([
                        'basic'      => 'Basic',
                        'pro'        => 'Pro',
                        'enterprise' => 'Enterprise',
                    ])
                    ->required()
                    ->default('basic'),

                DatePicker::make('expiration_date')
                    ->label('Expiration Date')
                    ->nullable(),

                TextInput::make('installation_id')
                    ->label('Installation ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Se vincula automáticamente cuando el cliente activa la licencia por primera vez.')
                    ->columnSpanFull(),

                TagsInput::make('allowed_addons')
                    ->label('Módulos')
                    ->placeholder('Ej: cuentas_corrientes')
                    ->suggestions([
                        'cuentas_corrientes',
                    ])
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }
}
