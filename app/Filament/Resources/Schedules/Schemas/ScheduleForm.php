<?php

namespace App\Filament\Resources\Schedules\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required(),
                TextInput::make('title'),
                TextInput::make('opponent'),
                DatePicker::make('game_date'),
                TimePicker::make('game_time'),
                TextInput::make('location'),
                TextInput::make('venue'),
                TextInput::make('status'),
                Toggle::make('is_home'),
                TextInput::make('result'),
                TextInput::make('score'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
