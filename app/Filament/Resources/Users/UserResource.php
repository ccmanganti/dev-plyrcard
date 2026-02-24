<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\FusedGroup;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'People';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema)->schema([

            // ==============================
            // BASIC INFO
            // ==============================
            Section::make('Basic Information')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')->required(),
                    TextInput::make('last_name')->required(),
                    TextInput::make('personal_email')->email(),
                    TextInput::make('email')->label('PlyrCard Email')->email(),
                    TextInput::make('phone')->columnSpan(2),

                    FusedGroup::make([
                        TextInput::make('street')->placeholder('Street'),
                        TextInput::make('city')->placeholder('City'),
                        TextInput::make('state')->placeholder('State'),
                        TextInput::make('country')->placeholder('Country'),
                    ])->columnSpanFull(),
                ]),

                
            // ==============================
            // ACADEMICS & ATHLETICS
            // ==============================
            Section::make('Athletic & Academic Info')
                ->columns(2)
                ->schema([
                    TextInput::make('gpa')->numeric(),
                    TextInput::make('year')->label('Graduation Year')->numeric(),
                    DatePicker::make('birth'),

                    TextInput::make('jersey_number')->numeric(),
                    TextInput::make('team_name')->columnSpan(2),
                    Checkbox::make('natl_team_exp')->label('National Team Experience')->columnSpan(2),

                    Textarea::make('accolades')
                        ->columnSpanFull()->label('Accolades & Honors'),
                ]),

            // ==============================
            // SOCIAL
            // ==============================
            Section::make('Social & Media')
                ->columns(2)
                ->schema([
                    TextInput::make('ig_handle')->prefix('@'),
                    TextInput::make('x_handle')->prefix('@'),
                    TextInput::make('yt_url')->url()->columnSpan(2),

                    Textarea::make('press')
                        ->columnSpanFull(),
                ]),


            // ==============================
            // COACHES
            // ==============================
            Section::make('Coaches')
                ->schema([
                    FusedGroup::make([
                        TextInput::make('club_coach')->placeholder('Club Coach'),
                        TextInput::make('club_coach_email')->email()->placeholder('Email'),
                        TextInput::make('club_coach_phone')->placeholder('Phone'),
                    ]),

                    FusedGroup::make([
                        TextInput::make('natl_coach')->placeholder('National Coach'),
                        TextInput::make('natl_coach_email')->email()->placeholder('Email'),
                        TextInput::make('natl_coach_phone')->placeholder('Phone'),
                    ]),
                ]),

            // ==============================
            // TRAINERS
            // ==============================
            Section::make('Trainers')
                ->schema([
                    FusedGroup::make([
                        TextInput::make('tech_trainer')->placeholder('Technical Trainer'),
                        TextInput::make('tech_trainer_email')->email()->placeholder('Email'),
                        TextInput::make('tech_trainer_phone')->placeholder('Phone'),
                    ]),

                    FusedGroup::make([
                        TextInput::make('snc_trainer')->placeholder('Strength & Conditioning'),
                        TextInput::make('snc_trainer_email')->email()->placeholder('Email'),
                        TextInput::make('snc_trainer_phone')->placeholder('Phone'),
                    ]),
                ]),

            // ==============================
            // WEBSITE
            // ==============================
            Section::make('Website')
                ->schema([
                    TextInput::make('domain')
                        ->label('Custom Domain')
                        ->prefix('https://')
                        ->helperText('Enter without www')
                        ->unique(ignoreRecord: true),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table)->recordActions([
            // You may add these actions to your table if you're using a simple
            // resource, or you just want to be able to delete records without
            // leaving the table.
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            // ...
        ])->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
                // ...
            ]),
        ])->filters([
            TrashedFilter::make(),
            // ...
        ])
        ->columns([
            TextColumn::make('first_name'),
            TextColumn::make('last_name'),
            TextColumn::make('updated_at')
                ->since()
                ->label('Updated'),
            TextColumn::make('roles.name')
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
