<?php

namespace App\Filament\Resources\SiteTemplates\Pages;

use App\Filament\Resources\SiteTemplates\SiteTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSiteTemplate extends ViewRecord
{
    protected static string $resource = SiteTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
