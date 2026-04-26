<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReleaseResource\Pages;
use App\Models\Release;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteBulkAction;

class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Versiones / Releases';

    protected static ?string $modelLabel = 'Release';

    protected static ?string $pluralModelLabel = 'Releases';

    protected static ?int $navigationSort = 2;

    // ─────────────────────────────────────────────────────────────────────────
    // FORMULARIO
    // ─────────────────────────────────────────────────────────────────────────
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('version')
                ->label('Versión (SemVer)')
                ->placeholder('Ej: 1.3.0')
                ->required()
                ->maxLength(20),

            Select::make('component')
                ->label('Componente')
                ->options([
                    'frontend' => '🖥️ Frontend (App de Caja)',
                    'backend'  => '⚙️ Backend (Servidor Local)',
                ])
                ->required()
                ->default('frontend'),

            TextInput::make('download_url')
                ->label('URL de Descarga (ZIP)')
                ->placeholder('https://pub-xxxx.r2.dev/releases/update_v1.3.0.zip')
                ->required()
                ->url()
                ->maxLength(500)
                ->columnSpanFull(),

            // ── CHANGELOG: Campo clave. Texto libre para el Release Manager. ──
            Textarea::make('changelog')
                ->label('📝 Novedades (Changelog para el cliente)')
                ->helperText('Este texto se muestra en el diálogo "Actualización Disponible" del POS. Podés usar emojis y saltos de línea.')
                ->placeholder(
                    "🚀 Nuevas Funcionalidades\n" .
                    "- Aumento Masivo de Precios por categoría o marca\n\n" .
                    "🛠️ Mejoras\n" .
                    "- Actualizador con limpieza de caché automática\n\n" .
                    "🐛 Fixes\n" .
                    "- Corregido error en auditoría de ventas"
                )
                ->rows(14)
                ->required()
                ->columnSpanFull(),

            Toggle::make('is_critical')
                ->label('🚨 Actualización Crítica')
                ->helperText('Si está activo, el cliente NO puede cerrar el diálogo sin actualizar.')
                ->default(false),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLA
    // ─────────────────────────────────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->label('Versión')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('component')
                    ->label('Componente')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'backend'  => 'warning',
                        'frontend' => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'backend'  => '⚙️ Backend',
                        'frontend' => '🖥️ Frontend',
                        default    => $state,
                    }),

                IconColumn::make('is_critical')
                    ->label('Crítica')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('changelog')
                    ->label('Novedades (preview)')
                    ->limit(80)
                    ->tooltip(fn (Release $record): string => $record->changelog ?? ''),

                TextColumn::make('created_at')
                    ->label('Publicado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make()->label('Editar Changelog'),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PÁGINAS
    // ─────────────────────────────────────────────────────────────────────────
    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReleases::route('/'),
            'create' => Pages\CreateRelease::route('/create'),
            'edit'   => Pages\EditRelease::route('/{record}/edit'),
        ];
    }
}
