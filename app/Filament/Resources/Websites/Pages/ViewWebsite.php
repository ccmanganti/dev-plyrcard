<?php

namespace App\Filament\Resources\Websites\Pages;

use App\Filament\Resources\Websites\WebsiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\EditAction;

class ViewWebsite extends ViewRecord
{
    protected static string $resource = WebsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit'),

            Actions\RestoreAction::make()
                ->visible(fn (Model $record): bool => method_exists($record, 'trashed') && $record->trashed()),

            Actions\DeleteAction::make()
                ->label('Delete')
                ->requiresConfirmation(),

            Actions\ForceDeleteAction::make()
                ->label('Force Delete')
                ->color('danger')
                ->visible(fn (Model $record): bool => method_exists($record, 'trashed') && $record->trashed())
                ->requiresConfirmation(),
        ];
    }
}
