<?php

namespace App\Filament\Resources\Licenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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

                Select::make('business_type')
                    ->label('Tipo de Negocio')
                    ->options([
                        'retail'         => '🛒 Retail (Minimercado)',
                        'hardware_store' => '🔨 Ferretería / Ind. Maderera',
                    ])
                    ->required()
                    ->default('retail'),

                TextInput::make('api_key')
                    ->label('API Key')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Generated automatically on creation.')
                    ->columnSpan(['default' => 2]),

                Select::make('plan')
                    ->label('Nivel de Acceso')
                    ->options([
                        'basic'      => 'Basic',
                        'pro'        => 'Pro',
                        'enterprise' => 'Enterprise',
                    ])
                    ->required()
                    ->default('basic'),

                Select::make('plan_type')
                    ->label('Modelo de Facturación')
                    ->options([
                        'saas'     => 'SaaS (Suscripción)',
                        'lifetime' => 'Lifetime (Pago Único)',
                    ])
                    ->required()
                    ->default('saas')
                    ->live(),

                DatePicker::make('expiration_date')
                    ->label('Expiration Date')
                    ->required(fn (callable $get) => $get('plan_type') === 'saas')
                    ->hidden(fn (callable $get) => $get('plan_type') === 'lifetime')
                    ->nullable(),

                TextInput::make('installation_id')
                    ->label('Installation ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Se vincula automáticamente cuando el cliente activa la licencia por primera vez.')
                    ->columnSpanFull(),

                Select::make('allowed_addons')
                    ->label('Módulos Habilitados')
                    ->multiple()
                    ->options([
                        'fast_pos'          => 'Caja Rápida',
                        'z_reports'         => 'Reportes Z',
                        'quotes'            => 'Presupuestos (PDF/WA)',
                        'current_accounts'  => 'Cuentas Corrientes (Fiado)',
                        'multiple_prices'   => 'Listas de Precios (Mayorista/Tarjeta)',
                        'multi_caja'        => 'Múltiples Cajas / Terminales',
                    ])
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }
}
