<?php

namespace App\Filament\Resources\Licenses\Pages;

use App\Filament\Resources\Licenses\LicenseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLicense extends EditRecord
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('reset_hardware_lock')
                ->label('Liberar Hardware')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('¿Liberar candado de hardware?')
                ->modalDescription('Esto vaciará el Installation ID. La licencia quedará libre para ser usada en una computadora diferente. ¿Deseas continuar?')
                ->action(function () {
                    $this->record->update(['installation_id' => null]);
                    $this->refreshFormData(['installation_id']);
                    \Filament\Notifications\Notification::make()
                        ->title('Candado de hardware liberado')
                        ->success()
                        ->send();
                })
                ->visible(fn () => !empty($this->record->installation_id)),
            DeleteAction::make(),
        ];
    }
}
