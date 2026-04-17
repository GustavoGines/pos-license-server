<?php

namespace App\Filament\Resources\Licenses\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LicensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('business_type')
                    ->label('Rubro')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'retail'         => 'success',
                        'hardware_store' => 'warning',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'retail'         => '🛒 Retail',
                        'hardware_store' => '🔨 Ferretería',
                        default          => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('api_key')
                    ->label('API Key')
                    ->searchable()
                    ->limit(30)
                    ->fontFamily('mono')
                    ->tooltip(fn (string $state): string => $state),

                TextColumn::make('plan')
                    ->label('Nivel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'basico'  => 'gray',
                        'premium' => 'success',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'basico'  => 'Básico',
                        'premium' => 'Premium',
                        default   => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('plan_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'saas'     => 'primary',
                        'lifetime' => 'success',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('expiration_date')
                    ->label('Expira')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($state, $record): ?string => $record->expiration_date?->isPast() ? 'danger' : null),

                TextColumn::make('installation_id')
                    ->label('Installation ID')
                    ->searchable()
                    ->limit(15)
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('allowed_addons')
                    ->label('Módulos')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan')
                    ->label('Nivel')
                    ->options([
                        'basico'  => 'Básico',
                        'premium' => 'Premium',
                    ]),

                SelectFilter::make('plan_type')
                    ->label('Tipo (Facturación)')
                    ->options([
                        'saas'     => 'SaaS',
                        'lifetime' => 'Lifetime',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas')
                    ->native(false),
            ])
            ->recordActions([
                Action::make('copy_api_key')
                    ->label('')
                    ->icon(Heroicon::OutlinedClipboard)
                    ->color('gray')
                    ->tooltip('Copiar API Key')
                    ->action(function ($record) {
                        Notification::make()
                            ->title('¡API Key copiada!')
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(fn ($record) => [
                        'x-on:click.stop' => "navigator.clipboard.writeText('" . addslashes($record->api_key) . "')",
                    ]),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
