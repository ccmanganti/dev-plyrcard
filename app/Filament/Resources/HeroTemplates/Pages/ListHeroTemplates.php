<?php

namespace App\Filament\Resources\HeroTemplates\Pages;

use App\Filament\Resources\HeroTemplates\HeroTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHeroTemplates extends ListRecords
{
    protected static string $resource = HeroTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
