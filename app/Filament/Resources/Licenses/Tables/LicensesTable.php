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
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('api_key')
                    ->label('API Key')
                    ->searchable()
                    ->limit(30)
                    ->fontFamily('mono')
                    ->tooltip(fn (string $state): string => $state),

                TextColumn::make('plan_type')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'basic'      => 'gray',
                        'pro'        => 'info',
                        'enterprise' => 'success',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('expiration_date')
                    ->label('Expires')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($state, $record): ?string => $record->expiration_date?->isPast() ? 'danger' : null),

                TextColumn::make('installation_id')
                    ->label('Installation ID')
                    ->searchable()
                    ->limit(15)
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('addons')
                    ->label('Addons')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan_type')
                    ->label('Plan')
                    ->options([
                        'basic'      => 'Basic',
                        'pro'        => 'Pro',
                        'enterprise' => 'Enterprise',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
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
