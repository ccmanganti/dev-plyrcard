<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\EditAction;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

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