<?php

namespace App\Filament\Resources\HeroTemplates\Pages;

use App\Filament\Resources\HeroTemplates\HeroTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHeroTemplate extends ViewRecord
{
    protected static string $resource = HeroTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
