<?php

namespace App\Filament\Resources\SiteTemplates\Pages;

use App\Filament\Resources\SiteTemplates\SiteTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteTemplates extends ListRecords
{
    protected static string $resource = SiteTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
