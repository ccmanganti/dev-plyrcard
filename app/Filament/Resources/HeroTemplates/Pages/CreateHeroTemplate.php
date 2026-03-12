<?php

namespace App\Filament\Resources\HeroTemplates\Pages;

use App\Filament\Resources\HeroTemplates\HeroTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHeroTemplate extends CreateRecord
{
    protected static string $resource = HeroTemplateResource::class;
}
