<?php

namespace App\Filament\Resources\Clubs\RelationManagers;

use App\Mail\CoachAccountCreatedMail;
use App\Models\ClubLeague;
use App\Models\TeamManagerAssignment;
use App\Models\User;
use App\Support\PhoneFormatter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Actions\Impersonate;

class CoachesRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Coaches';

    protected static ?string $modelLabel = 'Coach';

    protected static ?string $pluralModelLabel = 'Coaches';

    protected ?string $managerRole = 'Club Manager';

    protected array $assignedTeamKeys = [];

    protected ?string $plainPassword = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasRole('Superadmin') ?? false;
    }

    protected function ageGroupOptions(): array
    {
        return collect(config('plyrcard.age_groups', [
            'u13' => 'U13',
            'u14' => 'U14',
            'u15' => 'U15',
            'u16' => 'U16',
            'u17' => 'U17',
            'u18' => 'U18',
            'u19' => 'U19',
        ]))->mapWithKeys(fn ($label) => [(string) $label => (string) $label])->all();
    }

    protected function programLogicalKey(ClubLeague $program): string
    {
        $leagueName = str((string) ($program->league?->name ?: ''))->lower()->squish()->toString();
        $sport = str((string) ($program->sport ?: $program->league?->sport ?: ''))->lower()->replace('_', ' ')->squish()->toString();

        $genders = collect($program->genders ?? $program->league?->genders ?? [])
            ->map(fn ($gender) => strtolower(trim((string) $gender)))
            ->map(fn ($gender) => match ($gender) {
                'girls', 'girl', 'women', 'woman', 'female' => 'female',
                'boys', 'boy', 'men', 'man', 'male' => 'male',
                default => $gender,
            })
            ->filter()
            ->unique()
            ->sort()
            ->implode('/');

        return implode('|', [$leagueName, $sport, $genders]);
    }

    protected function programLabel(ClubLeague $program): string
    {
        $sport = str((string) ($program->sport ?: $program->league?->sport ?: ''))->replace('_', ' ')->lower()->squish()->toString();

        $genders = collect($program->genders ?? $program->league?->genders ?? [])
            ->map(fn ($gender) => strtolower(trim((string) $gender)))
            ->map(fn ($gender) => match ($gender) {
                'girls', 'girl', 'women', 'woman', 'female' => 'female',
                'boys', 'boy', 'men', 'man', 'male' => 'male',
                default => $gender,
            })
            ->filter()
            ->unique()
            ->sort()
            ->implode('/');

        return collect([$program->league?->name, filled($sport) ? $sport : null, filled($genders) ? $genders : null])
            ->filter()
            ->implode(' • ');
    }

    protected function programOptions(): array
    {
        return ClubLeague::query()
            ->with('league')
            ->where('club_id', $this->getOwnerRecord()->getKey())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ClubLeague $program): bool => filled($program->league?->name))
            ->unique(fn (ClubLeague $program): string => $this->programLogicalKey($program))
            ->values()
            ->mapWithKeys(fn (ClubLeague $program): array => [
                (string) $program->id => $this->programLabel($program),
            ])
            ->all();
    }

    protected function teamAssignmentOptions(array $selectedClubLeagueIds): array
    {
        $selectedClubLeagueIds = collect($selectedClubLeagueIds)->filter()->map(fn ($value) => (string) $value)->values();

        if ($selectedClubLeagueIds->isEmpty()) {
            return [];
        }

        return ClubLeague::query()
            ->with('league')
            ->where('club_id', $this->getOwnerRecord()->getKey())
            ->where('is_active', true)
            ->whereIn('id', $selectedClubLeagueIds->all())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ClubLeague $program): bool => filled($program->league?->name))
            ->unique(fn (ClubLeague $program): string => $this->programLogicalKey($program))
            ->values()
            ->mapWithKeys(function (ClubLeague $program): array {
                return [
                    $this->programLabel($program) => collect($this->ageGroupOptions())
                        ->mapWithKeys(fn (string $ageGroup): array => [
                            $program->id . '|' . strtoupper($ageGroup) => strtoupper($ageGroup),
                        ])
                        ->all(),
                ];
            })
            ->all();
    }

    protected function currentAssignmentKeys(User $record): array
    {
        return TeamManagerAssignment::query()
            ->where('user_id', $record->id)
            ->where('club_id', $this->getOwnerRecord()->getKey())
            ->get()
            ->map(fn (TeamManagerAssignment $assignment): string => $assignment->club_league_id . '|' . strtoupper((string) $assignment->team_name))
            ->unique()
            ->values()
            ->all();
    }

    protected function currentProgramIds(User $record): array
    {
        return collect($this->currentAssignmentKeys($record))
            ->map(fn (string $key): ?string => explode('|', $key, 2)[0] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('plain_password')->dehydrated(false),

            Section::make('Account Credentials')
                ->description('Coaches are actual user accounts. Passwords are stored hashed, so existing passwords cannot be shown.')
                ->columns(2)
                ->schema([
                    TextInput::make('email')
                        ->label('Login Email')
                        ->email()
                        ->required()
                        ->rules(fn (?User $record): array => ['unique:users,email' . ($record?->id ? ',' . $record->id : '')])
                        ->maxLength(255),

                    TextInput::make('password')
                        ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'New Password')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->minLength(8)
                        ->helperText(fn (string $operation): string => $operation === 'create'
                            ? 'This password will be emailed to the coach.'
                            : 'Leave blank to keep the existing password.'),
                ]),

            Section::make('Coach Details')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')->label('First Name')->required()->maxLength(255),
                    TextInput::make('last_name')->label('Last Name')->required()->maxLength(255),
                    TextInput::make('title')->label('Title')->placeholder('Head Coach, Assistant Coach, Director, etc.')->maxLength(255),
                    TextInput::make('phone')
                        ->label('Phone')
                        ->tel()
                        ->placeholder('(555) 000-0000')
                        ->regex('/^([0-9\-\+\(\)\s]{7,20})$/')
                        ->validationMessages(['regex' => 'Enter a valid phone number.'])
                        ->maxLength(255),
                ]),

            Section::make('Access')
                ->columns(2)
                ->schema([
                    Select::make('manager_role')
                        ->label('Access Level')
                        ->options(['Club Manager' => 'Club Manager', 'Team Manager' => 'Team Manager'])
                        ->default('Club Manager')
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Select $component, ?User $record): void {
                            if ($record) {
                                $component->state($record->hasRole('Team Manager') ? 'Team Manager' : 'Club Manager');
                            }
                        })
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state !== 'Team Manager') {
                                $set('assigned_club_league_ids', []);
                                $set('assigned_team_keys', []);
                            }
                        }),

                    Select::make('assigned_club_league_ids')
                        ->label('Program / League')
                        ->multiple()
                        ->options(fn (): array => $this->programOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->visible(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                        ->required(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                        ->dehydrated(false)
                        ->helperText('Duplicate programs are hidden.')
                        ->afterStateHydrated(function (Select $component, ?User $record): void {
                            if ($record) {
                                $component->state($this->currentProgramIds($record));
                            }
                        })
                        ->afterStateUpdated(fn (Set $set): mixed => $set('assigned_team_keys', [])),

                    Select::make('assigned_team_keys')
                        ->label('Teams / Age Groups')
                        ->multiple()
                        ->options(fn (Get $get): array => $this->teamAssignmentOptions($get('assigned_club_league_ids') ?? []))
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                        ->required(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                        ->disabled(fn (Get $get): bool => blank($get('assigned_club_league_ids')))
                        ->placeholder(fn (Get $get): string => blank($get('assigned_club_league_ids')) ? 'Select programs first' : 'Select teams under each program')
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Select $component, ?User $record): void {
                            if ($record) {
                                $component->state($this->currentAssignmentKeys($record));
                            }
                        }),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['roles'])
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                    'Club Manager',
                    'Team Manager',
                ]))
                ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'Superadmin')))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (User $record): string => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: $record->email)
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable(['first_name', 'last_name']),

                TextColumn::make('title')->label('Title')->placeholder('-')->searchable(),
                TextColumn::make('roles.name')->label('Access')->badge()->separator(','),
                TextColumn::make('email')->label('Login Email')->copyable()->searchable(),
                TextColumn::make('phone')->label('Phone')->copyable()->placeholder('-'),

                TextColumn::make('assignments')
                    ->label('Teams Managed')
                    ->state(function (User $record): string {
                        if ($record->hasRole('Club Manager')) {
                            return 'All teams';
                        }

                        return TeamManagerAssignment::query()
                            ->with(['clubLeague.league'])
                            ->where('user_id', $record->id)
                            ->where('club_id', $this->getOwnerRecord()->getKey())
                            ->get()
                            ->groupBy(fn (TeamManagerAssignment $assignment): string => $assignment->clubLeague ? $this->programLabel($assignment->clubLeague) : 'Program')
                            ->map(function ($items, string $programLabel): string {
                                $teams = $items->pluck('team_name')->filter()->map(fn ($team) => strtoupper((string) $team))->unique()->implode(', ');
                                return $programLabel . ($teams ? ': ' . $teams : '');
                            })
                            ->values()
                            ->implode(' | ') ?: '-';
                    })
                    ->wrap(),

                TextColumn::make('coach_account_credentials_sent_at')
                    ->label('Credentials Sent')
                    ->since()
                    ->placeholder('Not sent'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Coach')
                    ->modalWidth('5xl')
                    ->mutateDataUsing(function (array $data): array {
                        $this->managerRole = $data['manager_role'] ?? 'Club Manager';
                        $this->assignedTeamKeys = $data['assigned_team_keys'] ?? [];
                        $this->plainPassword = $data['password'] ?? null;

                        $data['club_id'] = $this->getOwnerRecord()->getKey();
                        $data['password'] = Hash::make($data['password']);
                        $data['phone'] = PhoneFormatter::normalize($data['phone'] ?? null);
                        $data['club_manager_created_at'] = now();

                        unset($data['plain_password'], $data['manager_role'], $data['assigned_club_league_ids'], $data['assigned_team_keys']);

                        return $data;
                    })
                    ->after(function (User $record): void {
                        Role::findOrCreate($this->managerRole);
                        $record->syncRoles([$this->managerRole]);

                        $this->syncAssignments($record, $this->managerRole, $this->assignedTeamKeys);

                        if ($this->plainPassword) {
                            Mail::to($record->email)->send(new CoachAccountCreatedMail(
                                coach: $record,
                                plainPassword: $this->plainPassword,
                                loginUrl: url('/admin/login'),
                                accessTitle: $this->managerRole,
                            ));

                            $record->forceFill([
                                'coach_account_credentials_sent_at' => now(),
                            ])->save();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('5xl')
                    ->slideOver()
                    ->mutateDataUsing(function (array $data): array {
                        $this->managerRole = $data['manager_role'] ?? 'Club Manager';
                        $this->assignedTeamKeys = $data['assigned_team_keys'] ?? [];

                        if (filled($data['password'] ?? null)) {
                            $data['password'] = Hash::make($data['password']);
                        } else {
                            unset($data['password']);
                        }

                        $data['phone'] = PhoneFormatter::normalize($data['phone'] ?? null);

                        unset($data['plain_password'], $data['manager_role'], $data['assigned_club_league_ids'], $data['assigned_team_keys']);

                        return $data;
                    })
                    ->after(function (User $record): void {
                        Role::findOrCreate($this->managerRole);
                        $record->syncRoles([$this->managerRole]);
                        $this->syncAssignments($record, $this->managerRole, $this->assignedTeamKeys);
                    }),

                Action::make('sendPasswordEmail')
                    ->label('Send Password')
                    ->icon('heroicon-m-envelope')
                    ->form([
                        TextInput::make('password')->label('New Password')->password()->revealable()->required()->minLength(8),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->forceFill(['password' => Hash::make($data['password'])])->save();

                        Mail::to($record->email)->send(new CoachAccountCreatedMail(
                            coach: $record,
                            plainPassword: $data['password'],
                            loginUrl: url('/admin/login'),
                            accessTitle: $record->roles->pluck('name')->implode(', ') ?: 'Coach',
                        ));

                        $record->forceFill(['coach_account_credentials_sent_at' => now()])->save();
                    }),

                Impersonate::make()
                    ->iconButton()
                    ->tooltip('Impersonate')
                    ->visible(fn (User $record) => auth()->id() !== $record->id && auth()->user()?->hasRole('Superadmin'))
                    ->redirectTo('/admin'),

                DeleteAction::make()
                    ->before(fn (User $record): mixed => TeamManagerAssignment::query()->where('user_id', $record->id)->delete()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records): void {
                            foreach ($records as $record) {
                                TeamManagerAssignment::query()->where('user_id', $record->id)->delete();
                            }
                        }),
                ]),
            ]);
    }

    protected function syncAssignments(User $record, string $role, array $assignmentKeys): void
    {
        TeamManagerAssignment::query()->where('user_id', $record->id)->delete();

        if ($role !== 'Team Manager') {
            return;
        }

        foreach (collect($assignmentKeys)->filter()->unique() as $assignmentKey) {
            [$clubLeagueId, $teamName] = array_pad(explode('|', (string) $assignmentKey, 2), 2, null);

            if (blank($clubLeagueId) || blank($teamName)) {
                continue;
            }

            $clubLeague = ClubLeague::query()
                ->where('club_id', $this->getOwnerRecord()->getKey())
                ->whereKey($clubLeagueId)
                ->where('is_active', true)
                ->first();

            if (! $clubLeague) {
                continue;
            }

            TeamManagerAssignment::updateOrCreate([
                'user_id' => $record->id,
                'club_id' => $this->getOwnerRecord()->getKey(),
                'club_league_id' => $clubLeague->id,
                'team_name' => strtoupper(trim($teamName)),
            ], [
                'league_id' => $clubLeague->league_id,
            ]);
        }
    }
}