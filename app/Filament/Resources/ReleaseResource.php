<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReleaseResource\Pages\CreateRelease;
use App\Filament\Resources\ReleaseResource\Pages\EditRelease;
use App\Filament\Resources\ReleaseResource\Pages\ListReleases;
use App\Models\Release;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'Versiones / Releases';

    protected static ?string $modelLabel = 'Release';

    protected static ?string $pluralModelLabel = 'Releases';

    protected static ?int $navigationSort = 2;

    // ─────────────────────────────────────────────────────────────────────────
    // FORMULARIO (Filament v5: Schema con ->components())
    // ─────────────────────────────────────────────────────────────────────────
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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

            Select::make('channel')
                ->label('Canal de Distribución')
                ->options([
                    'beta'   => '🐛 Desarrollo (Beta - Invisible para clientes)',
                    'stable' => '🚀 Producción (Stable - Público)',
                ])
                ->required()
                ->default('beta'),

            TextInput::make('download_url')
                ->label('URL de Descarga (ZIP)')
                ->placeholder('https://pub-xxxx.r2.dev/releases/update_v1.3.0.zip')
                ->required()
                ->url()
                ->maxLength(500)
                ->columnSpanFull(),

            // ── Campo clave: Changelog editable manualmente por el Release Manager ──
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
    // TABLA (Filament v5: recordActions / toolbarActions)
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

                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stable'  => 'success',
                        'beta'    => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stable'  => '🚀 Stable',
                        'beta'    => '🐛 Beta',
                        default   => $state,
                    }),

                IconColumn::make('is_critical')
                    ->label('Crítica')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)
                    ->falseIcon(Heroicon::OutlinedCheckCircle)
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
            ->recordActions([
                EditAction::make()->label('Editar Changelog'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PÁGINAS
    // ─────────────────────────────────────────────────────────────────────────
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListReleases::route('/'),
            'create' => CreateRelease::route('/create'),
            'edit'   => EditRelease::route('/{record}/edit'),
        ];
    }
}
