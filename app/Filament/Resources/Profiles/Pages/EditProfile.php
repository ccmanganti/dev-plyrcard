<?php

namespace App\Filament\Resources\Profiles\Pages;

use App\Filament\Resources\Profiles\ProfileResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Club;
use App\Models\HeroTemplate;
use App\Models\League;
use App\Models\NationalTeam;
use App\Models\School;
use App\Models\SiteTemplate;
use App\Models\Team;
use App\Models\User;
use App\Models\Website;
use App\Support\ProfilePlanInfo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ProfileResource::class;

    protected string $view = 'filament.resources.profiles.pages.edit-profile';

    public ?User $user = null;

    public ?Website $website = null;

    public array $data = [];

    public bool $showLockedFeatureModal = false;

    public string $lockedFeatureTitle = 'UNLOCK SOCIAL & VIDEO LINKS';

    public string $lockedFeatureMessage = 'This feature is available on Plyr and My Journey. Upgrade now to take your PLYRCard to the next level.';

    public bool $showPreviewAccessModal = false;

    public string $previewAccessModalType = 'complete_profile';

    public string $previewAccessModalTitle = 'COMPLETE <span>YOUR PROFILE</span>';

    public string $previewAccessModalMessage = 'Complete at least 75% of your profile before previewing your card.';

    public ?string $previewAccessModalActionUrl = null;

    public string $previewAccessModalActionLabel = 'Complete Profile';

    protected ?ProfilePlanInfo $planInfoCache = null;

    public function mount(): void
    {
        $this->user = auth()->user();

        abort_unless($this->user, 403);

        $this->user->loadMissing('roles', 'nationalTeam');

        $this->website = $this->user->websites()->first();

        $teamId = $this->user->team_id ?? null;

        if (blank($teamId) && filled($this->user->team_name ?? null) && filled($this->user->club_id ?? null)) {
            $teamId = Team::query()
                ->where('club_id', $this->user->club_id)
                ->where('name', $this->user->team_name)
                ->value('id');
        }

        $this->form->fill([
            ...$this->user->toArray(),
            'team_id' => $teamId,
            'website_name' => $this->website?->name,
            'site_template_id' => $this->website?->site_template_id,
            'hero_template_id' => $this->website?->hero_template_id,
            'website_is_active' => $this->website?->is_active ?? true,
            'website_is_published' => $this->website?->is_published ?? false,
            'primary_color' => $this->website?->primary_color,
            'secondary_color' => $this->website?->secondary_color,
            'accent_color' => $this->website?->accent_color,
            'background_color' => $this->website?->background_color,
            'surface_color' => $this->website?->surface_color,
            'text_primary_color' => $this->website?->text_primary_color,
            'text_secondary_color' => $this->website?->text_secondary_color,
        ]);
    }

    public function getPlanInfo(): ?ProfilePlanInfo
    {
        if (! $this->user) {
            return null;
        }

        return $this->planInfoCache ??= ProfilePlanInfo::for($this->user);
    }

    protected static function getSchoolOptions(): array
    {
        return ['__new__' => 'Add New'] + School::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getNationalTeamOptions(): array
    {
        return ['__new__' => 'Add New'] + NationalTeam::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getSiteTemplateOptions(?string $sport): array
    {
        $query = SiteTemplate::query()
            ->where('is_active', true);

        if (blank($sport)) {
            $query->where(function ($q) {
                $q->whereNull('sports')
                    ->orWhereJsonLength('sports', 0);
            });
        } else {
            $query->where(function ($q) use ($sport) {
                $q->whereNull('sports')
                    ->orWhereJsonLength('sports', 0)
                    ->orWhereJsonContains('sports', $sport);
            });
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function getHeroTemplateOptions(?string $sport): array
    {
        $query = HeroTemplate::query()
            ->where('is_active', true);

        if (blank($sport)) {
            $query->where(function ($q) {
                $q->whereNull('sports')
                    ->orWhereJsonLength('sports', 0);
            });
        } else {
            $query->where(function ($q) use ($sport) {
                $q->whereNull('sports')
                    ->orWhereJsonLength('sports', 0)
                    ->orWhereJsonContains('sports', $sport);
            });
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function getPositionOptions(?string $sport): array
    {
        return match ($sport) {
            'basketball' => [
                'point_guard' => 'Point Guard',
                'shooting_guard' => 'Shooting Guard',
                'small_forward' => 'Small Forward',
                'power_forward' => 'Power Forward',
                'center' => 'Center',
            ],
            'volleyball' => [
                'setter' => 'Setter',
                'outside_hitter' => 'Outside Hitter',
                'opposite_hitter' => 'Opposite Hitter',
                'middle_blocker' => 'Middle Blocker',
                'libero' => 'Libero',
                'defensive_specialist' => 'Defensive Specialist',
            ],
            'football' => [
                'quarterback' => 'Quarterback',
                'running_back' => 'Running Back',
                'wide_receiver' => 'Wide Receiver',
                'tight_end' => 'Tight End',
                'offensive_line' => 'Offensive Line',
                'defensive_line' => 'Defensive Line',
                'linebacker' => 'Linebacker',
                'cornerback' => 'Cornerback',
                'safety' => 'Safety',
                'kicker' => 'Kicker',
                'punter' => 'Punter',
            ],
            'baseball' => [
                'pitcher' => 'Pitcher',
                'catcher' => 'Catcher',
                'first_base' => 'First Base',
                'second_base' => 'Second Base',
                'third_base' => 'Third Base',
                'shortstop' => 'Shortstop',
                'left_field' => 'Left Field',
                'center_field' => 'Center Field',
                'right_field' => 'Right Field',
                'designated_hitter' => 'Designated Hitter',
            ],
            'softball' => [
                'pitcher' => 'Pitcher',
                'catcher' => 'Catcher',
                'first_base' => 'First Base',
                'second_base' => 'Second Base',
                'third_base' => 'Third Base',
                'shortstop' => 'Shortstop',
                'left_field' => 'Left Field',
                'center_field' => 'Center Field',
                'right_field' => 'Right Field',
            ],
            'soccer' => [
                'goalkeeper' => 'Goalkeeper',
                'defender' => 'Defender',
                'center_back' => 'Center Back',
                'full_back' => 'Full Back',
                'wing_back' => 'Wing Back',
                'midfielder' => 'Midfielder',
                'defensive_midfielder' => 'Defensive Midfielder',
                'central_midfielder' => 'Central Midfielder',
                'attacking_midfielder' => 'Attacking Midfielder',
                'winger' => 'Winger',
                'forward' => 'Forward',
                'striker' => 'Striker',
            ],
            'tennis' => [
                'singles' => 'Singles',
                'doubles' => 'Doubles',
            ],
            'badminton' => [
                'singles' => 'Singles',
                'doubles' => 'Doubles',
                'mixed_doubles' => 'Mixed Doubles',
            ],
            'table_tennis' => [
                'singles' => 'Singles',
                'doubles' => 'Doubles',
                'mixed_doubles' => 'Mixed Doubles',
            ],
            'track_and_field' => [
                'sprinter' => 'Sprinter',
                'middle_distance' => 'Middle Distance',
                'long_distance' => 'Long Distance',
                'hurdler' => 'Hurdler',
                'jumper' => 'Jumper',
                'thrower' => 'Thrower',
                'relay_runner' => 'Relay Runner',
                'decathlete' => 'Decathlete',
                'heptathlete' => 'Heptathlete',
            ],
            'swimming' => [
                'freestyle' => 'Freestyle',
                'backstroke' => 'Backstroke',
                'breaststroke' => 'Breaststroke',
                'butterfly' => 'Butterfly',
                'individual_medley' => 'Individual Medley',
                'relay' => 'Relay',
            ],
            'boxing' => [
                'flyweight' => 'Flyweight',
                'bantamweight' => 'Bantamweight',
                'featherweight' => 'Featherweight',
                'lightweight' => 'Lightweight',
                'welterweight' => 'Welterweight',
                'middleweight' => 'Middleweight',
                'light_heavyweight' => 'Light Heavyweight',
                'heavyweight' => 'Heavyweight',
            ],
            'martial_arts' => [
                'lightweight' => 'Lightweight',
                'welterweight' => 'Welterweight',
                'middleweight' => 'Middleweight',
                'heavyweight' => 'Heavyweight',
                'striker' => 'Striker',
                'grappler' => 'Grappler',
                'all_rounder' => 'All-Rounder',
            ],
            default => [],
        };
    }

    protected static function applyGenderAndSportFilter(
        Builder $query,
        ?string $gender,
        ?string $sport,
        string $genderColumn = 'gender',
        string $sportColumn = 'sport',
    ): Builder {
        return $query
            ->when(filled($gender), fn (Builder $q) => $q->where($genderColumn, $gender))
            ->when(filled($sport), fn (Builder $q) => $q->where($sportColumn, $sport));
    }

    protected static function buildLogoOptionLabel(string $name, ?string $logoPath): string
    {
        $safeName = e($name);

        if (blank($logoPath)) {
            return <<<HTML
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:9999px;background:#f3f4f6;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6b7280;">
                        C
                    </div>
                    <span>{$safeName}</span>
                </div>
            HTML;
        }

        $logoUrl = Str::startsWith($logoPath, ['http://', 'https://'])
            ? $logoPath
            : Storage::disk('public')->url($logoPath);

        $safeUrl = e($logoUrl);

        return <<<HTML
            <div style="display:flex;align-items:center;gap:10px;">
                <img src="{$safeUrl}" alt="{$safeName}" style="width:28px;height:28px;border-radius:9999px;object-fit:cover;border:1px solid #e5e7eb;">
                <span>{$safeName}</span>
            </div>
        HTML;
    }

    protected static function getLeagueOptions(?string $gender, ?string $sport, ?string $search = null): array
    {
        $query = League::query();

        static::applyGenderAndSportFilter($query, $gender, $sport);

        $query->when(
            filled($search),
            fn (Builder $q) => $q->where('name', 'like', '%' . trim($search) . '%')
        );

        return $query
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getClubOptions(?string $leagueId, ?string $gender, ?string $sport, ?string $search = null): array
    {
        $query = Club::query();

        if (filled($leagueId)) {
            $query->where('league_id', $leagueId);
        } else {
            return [];
        }

        $query->when(
            filled($search),
            fn (Builder $q) => $q->where('name', 'like', '%' . trim($search) . '%')
        );

        return $query
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'logo'])
            ->mapWithKeys(function (Club $club) {
                return [
                    (string) $club->id => static::buildLogoOptionLabel($club->name, $club->logo),
                ];
            })
            ->all();
    }

    protected static function getClubSearchLabels(?string $leagueId, ?string $gender, ?string $sport, ?string $search = null): array
    {
        $query = Club::query();

        if (filled($leagueId)) {
            $query->where('league_id', $leagueId);
        } else {
            return [];
        }

        $query->when(
            filled($search),
            fn (Builder $q) => $q->where('name', 'like', '%' . trim($search) . '%')
        );

        return $query
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getSingleClubOptionLabel(?string $clubId): ?string
    {
        if (blank($clubId)) {
            return null;
        }

        $club = Club::query()->find($clubId);

        if (! $club) {
            return null;
        }

        return static::buildLogoOptionLabel($club->name, $club->logo);
    }

    protected static function getTeamOptions(?string $clubId, ?string $gender, ?string $sport, ?string $search = null): array
    {
        if (blank($clubId)) {
            return [];
        }

        $query = Team::query()
            ->where('club_id', $clubId);

        $query->when(
            filled($search),
            fn (Builder $q) => $q->where('name', 'like', '%' . trim($search) . '%')
        );

        return $query
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected function mutateProfileData(array $data): array
    {
        if (($data['school_id'] ?? null) === '__new__' && filled($data['new_school_name'] ?? null)) {
            $school = School::create([
                'name' => trim($data['new_school_name']),
            ]);

            $data['school_id'] = $school->id;
        } elseif (($data['school_id'] ?? null) === '__new__') {
            $data['school_id'] = null;
        }

        $data = UserResource::mutateUserFormData($data);

        unset($data['new_school_name']);

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('profile_tabs')
                    ->tabs([
                        Tab::make('Basic Info')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Section::make('Personal Information')
                                    ->icon('heroicon-m-user')
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('First Name')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Enter first name')
                                            ->required()
                                            ->columnSpan(2)
                                            ->maxLength(255),

                                        TextInput::make('last_name')
                                            ->label('Last Name')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Enter last name')
                                            ->columnSpan(2)
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('personal_email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->label('Personal Email')
                                            ->placeholder('name@example.com')
                                            ->columnSpan(2)
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->label('PlyrCard Email')
                                            ->placeholder('plyrcard login email')
                                            ->email()
                                            ->columnSpan(2)
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('phone')
                                            ->label('Phone')
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('+1 (555) 000-0000')
                                            ->columnSpan(2)
                                            ->tel()
                                            ->maxLength(255),

                                        TextInput::make('password')
                                            ->label('Password')
                                            ->prefixIcon('heroicon-m-lock-closed')
                                            ->placeholder('Leave blank to keep current password')
                                            ->columnSpan(1)
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->same('password_confirmation')
                                            ->nullable()
                                            ->helperText('Leave blank to keep the current password.'),

                                        TextInput::make('password_confirmation')
                                            ->label('Confirm Password')
                                            ->prefixIcon('heroicon-m-lock-closed')
                                            ->placeholder('Re-enter new password')
                                            ->columnSpan(1)
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(false)
                                            ->nullable(),
                                    ]),

                                Section::make('Address')
                                    ->icon('heroicon-m-map-pin')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('street')
                                            ->prefixIcon('heroicon-m-map-pin')
                                            ->label('Street Address')
                                            ->placeholder('123 Main Street')
                                            ->maxLength(255),

                                        TextInput::make('city')
                                            ->prefixIcon('heroicon-m-building-office-2')
                                            ->label('City')
                                            ->placeholder('City')
                                            ->maxLength(255),

                                        TextInput::make('state')
                                            ->prefixIcon('heroicon-m-map')
                                            ->label('State / Province')
                                            ->placeholder('State / Province')
                                            ->maxLength(255),

                                        TextInput::make('country')
                                            ->prefixIcon('heroicon-m-globe-alt')
                                            ->label('Country')
                                            ->placeholder('Country')
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tab::make('Athlete Info')
                            ->icon('heroicon-m-user-circle')
                            ->schema([
                                Section::make('Sport Details')
                                    ->icon('heroicon-m-cog-6-tooth')
                                    ->columns(3)
                                    ->schema([
                                        Select::make('sport')
                                            ->prefixIcon('heroicon-m-trophy')
                                            ->label('Sport')
                                            ->placeholder('Select sport')
                                            ->options(UserResource::getSportOptions())
                                            ->required()
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('league_id', null);
                                                $set('club_id', null);
                                                $set('team_id', null);
                                            }),

                                        Select::make('position')
                                            ->prefixIcon('heroicon-m-rectangle-group')
                                            ->label('Position')
                                            ->placeholder('Select position')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->options(fn (Get $get): array => static::getPositionOptions($get('sport')))
                                            ->disabled(fn (Get $get): bool => blank($get('sport')))
                                            ->helperText('Select one or more positions based on the chosen sport.'),

                                        TextInput::make('jersey_number')
                                            ->prefixIcon('heroicon-m-hashtag')
                                            ->label('Roster Number')
                                            ->placeholder('e.g. 19')
                                            ->numeric(),

                                        TextInput::make('year')
                                            ->prefixIcon('heroicon-m-academic-cap')
                                            ->label('Graduation Year')
                                            ->placeholder('e.g. 2026')
                                            ->numeric()
                                            ->minValue(2000)
                                            ->maxValue(2100),

                                        Select::make('gender')
                                            ->prefixIcon('heroicon-m-user')
                                            ->label('Sex')
                                            ->placeholder('Select sex')
                                            ->options(UserResource::getGenderOptions())
                                            ->searchable()
                                            ->nullable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('league_id', null);
                                                $set('club_id', null);
                                                $set('team_id', null);
                                            }),

                                        DatePicker::make('birth')
                                            ->prefixIcon('heroicon-m-calendar-days')
                                            ->label('Birth Date')
                                            ->native(false)
                                            ->closeOnDateSelection(),

                                        TextInput::make('gpa')
                                            ->prefixIcon('heroicon-m-calculator')
                                            ->label('GPA')
                                            ->placeholder('e.g. 3.8')
                                            ->numeric()
                                            ->step('0.01'),

                                        Select::make('school_id')
                                            ->prefixIcon('heroicon-m-building-library')
                                            ->label('School')
                                            ->placeholder('Select school')
                                            ->options(fn () => static::getSchoolOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->nullable(),

                                        TextInput::make('new_school_name')
                                            ->prefixIcon('heroicon-m-plus-circle')
                                            ->label('New School Name')
                                            ->placeholder('Enter school name')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get) => $get('school_id') === '__new__')
                                            ->required(fn (Get $get) => $get('school_id') === '__new__'),
                                    ]),

                                Section::make('Physical Stats')
                                    ->columns(2)
                                    ->icon('heroicon-m-chart-bar-square')
                                    ->schema([
                                        TextInput::make('height')
                                            ->prefixIcon('heroicon-m-arrows-up-down')
                                            ->label('Height')
                                            ->maxLength(255)
                                            ->placeholder('e.g. 6\'2" or 188 cm'),

                                        TextInput::make('weight')
                                            ->prefixIcon('heroicon-m-scale')
                                            ->label('Weight')
                                            ->maxLength(255)
                                            ->placeholder('e.g. 185 lbs or 84 kg'),

                                        Select::make('dominant_foot')
                                            ->prefixIcon('heroicon-m-hand-raised')
                                            ->label('Dominant Foot')
                                            ->placeholder('Select dominant foot')
                                            ->options([
                                                'left' => 'Left',
                                                'right' => 'Right',
                                                'both' => 'Both',
                                            ])
                                            ->visible(fn (Get $get) => $get('sport') === 'soccer')
                                            ->required(fn (Get $get) => $get('sport') === 'soccer'),

                                        TextInput::make('max_speed')
                                            ->prefixIcon('heroicon-m-bolt')
                                            ->label('Max Speed')
                                            ->placeholder('e.g. 19.00')
                                            ->numeric()
                                            ->step('0.01'),
                                    ]),

                                Section::make('Experience')
                                    ->icon('heroicon-m-flag')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('league_id')
                                            ->prefixIcon('heroicon-m-squares-2x2')
                                            ->label('League')
                                            ->placeholder(fn (Get $get) => blank($get('sport')) || blank($get('gender'))
                                                ? 'Select sport and sex first'
                                                : 'Search league')
                                            ->searchable()
                                            ->live()
                                            ->preload(false)
                                            ->options(fn (Get $get): array => static::getLeagueOptions(
                                                $get('gender'),
                                                $get('sport'),
                                            ))
                                            ->getSearchResultsUsing(fn (string $search, Get $get): array => static::getLeagueOptions(
                                                $get('gender'),
                                                $get('sport'),
                                                $search,
                                            ))
                                            ->getOptionLabelUsing(function ($value): ?string {
                                                if (blank($value)) {
                                                    return null;
                                                }

                                                return League::query()->whereKey($value)->value('name');
                                            })
                                            ->disabled(fn (Get $get): bool => blank($get('sport')) || blank($get('gender')))
                                            ->helperText('Filtered by the selected sport and sex.')
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('club_id', null);
                                                $set('team_id', null);
                                            }),

                                        Select::make('club_id')
                                            ->prefixIcon('heroicon-m-shield-check')
                                            ->label('Club')
                                            ->placeholder(fn (Get $get) => blank($get('league_id'))
                                                ? 'Select league first'
                                                : 'Search club')
                                            ->searchable()
                                            ->live()
                                            ->allowHtml()
                                            ->preload(false)
                                            ->options(fn (Get $get): array => static::getClubOptions(
                                                $get('league_id'),
                                                $get('gender'),
                                                $get('sport'),
                                            ))
                                            ->getSearchResultsUsing(fn (string $search, Get $get): array => static::getClubOptions(
                                                $get('league_id'),
                                                $get('gender'),
                                                $get('sport'),
                                                $search,
                                            ))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::getSingleClubOptionLabel($value))
                                            ->getOptionLabelsUsing(fn (array $values): array => collect($values)
                                                ->mapWithKeys(fn ($value) => [$value => static::getSingleClubOptionLabel($value)])
                                                ->all())
                                            ->disabled(fn (Get $get): bool => blank($get('league_id')))
                                            ->helperText('Filtered by the selected league. Club logo is shown when available.')
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('team_id', null);
                                            }),

                                        Select::make('team_id')
                                            ->prefixIcon('heroicon-m-users')
                                            ->label('Team')
                                            ->placeholder(fn (Get $get) => blank($get('club_id'))
                                                ? 'Select club first'
                                                : 'Search team')
                                            ->searchable()
                                            ->live()
                                            ->preload(false)
                                            ->options(fn (Get $get): array => static::getTeamOptions(
                                                $get('club_id'),
                                                $get('gender'),
                                                $get('sport'),
                                            ))
                                            ->getSearchResultsUsing(fn (string $search, Get $get): array => static::getTeamOptions(
                                                $get('club_id'),
                                                $get('gender'),
                                                $get('sport'),
                                                $search,
                                            ))
                                            ->getOptionLabelUsing(function ($value): ?string {
                                                if (blank($value)) {
                                                    return null;
                                                }

                                                return Team::query()->whereKey($value)->value('name');
                                            })
                                            ->disabled(fn (Get $get): bool => blank($get('club_id')))
                                            ->helperText('Filtered by the selected club.')
                                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                if (blank($state)) {
                                                    return;
                                                }

                                                $team = Team::query()->find($state);

                                                if (! $team) {
                                                    $set('team_id', null);

                                                    return;
                                                }

                                                if (blank($get('club_id'))) {
                                                    $set('club_id', $team->club_id);
                                                }
                                            })
                                            ->rule(function (Get $get) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    if (blank($value)) {
                                                        return;
                                                    }

                                                    $exists = Team::query()
                                                        ->whereKey($value)
                                                        ->when(
                                                            filled($get('club_id')),
                                                            fn ($query) => $query->where('club_id', $get('club_id'))
                                                        )
                                                        ->exists();

                                                    if (! $exists) {
                                                        $fail('The selected team is invalid.');
                                                    }
                                                };
                                            }),

                                        Select::make('national_team_id')
                                            ->prefixIcon('heroicon-m-flag')
                                            ->label('National Team Experience')
                                            ->placeholder('Select national team')
                                            ->options(fn () => static::getNationalTeamOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        TextInput::make('national_team_period')
                                            ->prefixIcon('heroicon-m-calendar')
                                            ->label('National Team Period')
                                            ->placeholder('e.g. 2025-2026')
                                            ->maxLength(255),

                                        TextInput::make('new_national_team_name')
                                            ->prefixIcon('heroicon-m-plus-circle')
                                            ->label('New National Team Name')
                                            ->placeholder('Enter national team name')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get) => $get('national_team_id') === '__new__')
                                            ->required(fn (Get $get) => $get('national_team_id') === '__new__'),

                                        FileUpload::make('new_national_team_logo')
                                            ->label('New National Team Logo')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('national-team-logos')
                                            ->visibility('public')
                                            ->helperText('Optional.')
                                            ->visible(fn (Get $get) => $get('national_team_id') === '__new__'),
                                    ]),
                            ]),

                        Tab::make('Bio & Accolades')
                            ->icon('heroicon-m-list-bullet')
                            ->schema([
                                Section::make('Player Bio')
                                    ->icon('heroicon-m-pencil-square')
                                    ->description('Write your athlete story — who you are, your playing style, and what drives you.')
                                    ->schema([
                                        Textarea::make('player_bio')
                                            ->label('Player Bio')
                                            ->placeholder('Write your athlete story here...')
                                            ->rows(8)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Academic Accolades')
                                    ->icon('heroicon-m-academic-cap')
                                    ->description('Honors, dean’s list, certifications — anything that showcases your academic excellence.')
                                    ->schema([
                                        Textarea::make('academic_accolades')
                                            ->label('Academic Accolades')
                                            ->placeholder("Dean's List\nHonor Roll\nAP Scholar")
                                            ->rows(6)
                                            ->helperText('Enter one accolade per line.')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Sports Accolades')
                                    ->icon('heroicon-m-trophy')
                                    ->schema([
                                        Textarea::make('sports_accolades')
                                            ->label('Sports Accolades')
                                            ->placeholder("Team Captain\nAll-State Selection\nTournament MVP")
                                            ->rows(5)
                                            ->helperText('Enter one accolade per line.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Media')
                            ->icon('heroicon-m-photo')
                            ->schema([
                                Section::make('Profile & Hero Images')
                                    ->icon('heroicon-m-photo')
                                    ->description('Shared player images used across your card and website.')
                                    ->columns(3)
                                    ->schema([
                                        FileUpload::make('plyrcard_image')
                                            ->label('PlyrCard')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('user-player-images')
                                            ->visibility('public')
                                            ->helperText('Upload the card-style PNG image used across templates.'),

                                        FileUpload::make('player_image')
                                            ->label('Player Image')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('user-player-images')
                                            ->visibility('public')
                                            ->helperText('Upload the half-body player PNG image used across templates.'),

                                        FileUpload::make('mobile_hero_image')
                                            ->label('Vertical Hero Image')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('user-player-images')
                                            ->visibility('public')
                                            ->helperText('Upload the vertical/mobile hero image used for responsive hero layouts.'),

                                        FileUpload::make('youtube_thumbnail')
                                            ->label('YouTube Thumbnail')
                                            ->columnSpan(3)
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('user-player-images')
                                            ->visibility('public')
                                            ->helperText('Used for highlights thumbnail, social sharing image, and SEO preview image.'),

                                        FileUpload::make('raw_player_images')
                                            ->label('Raw Player Images')
                                            ->image()
                                            ->multiple()
                                            ->reorderable()
                                            ->appendFiles()
                                            ->maxFiles(20)
                                            ->disk('public')
                                            ->directory('user-player-images/raw')
                                            ->visibility('public')
                                            ->columnSpanFull()
                                            ->helperText('Upload up to 20 raw player images from the intake form. These are stored separately from the main Player Image.'),
                                    ]),

                                Section::make('YouTube Highlights')
                                    ->icon(($this->getPlanInfo()?->hasPremiumAccess() ?? false) ? 'heroicon-m-play-circle' : 'heroicon-m-lock-closed')
                                    ->description(
                                        ($this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                            ? 'Embed your game highlights, recruiting videos, and performance reels directly on your PLYRCard.'
                                            : 'Locked on Free. Upgrade to Plyr or My Journey to unlock this feature.'
                                    )
                                    ->schema([
                                        Placeholder::make('youtube_lock_overlay')
                                            ->hidden(fn () => $this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                            ->content(new HtmlString('
                                                <div class="pc-inline-lock">
                                                    <div class="pc-inline-lock__icon">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.875a4.5 4.5 0 10-9 0V10.5m-.75 0h10.5A2.25 2.25 0 0119.5 12.75v6A2.25 2.25 0 0117.25 21h-10.5A2.25 2.25 0 014.5 18.75v-6A2.25 2.25 0 016.75 10.5z" />
                                                        </svg>
                                                    </div>
                                                    <div class="pc-inline-lock__content">
                                                        <h4>Unlock Social &amp; Video Links</h4>
                                                        <p>This feature is available on Plyr and My Journey.</p>
                                                    </div>
                                                    <button type="button" wire:click="openLockedFeatureModal" class="pc-inline-lock__button">See Plans</button>
                                                </div>
                                            ')),

                                        TextInput::make('featured_video_url')
                                            ->prefixIcon(($this->getPlanInfo()?->hasPremiumAccess() ?? false) ? 'heroicon-m-link' : 'heroicon-m-lock-closed')
                                            ->label('Featured Video URL')
                                            ->placeholder('https://youtube.com/watch?v=...')
                                            ->url()
                                            ->disabled(fn () => ! ($this->getPlanInfo()?->hasPremiumAccess() ?? false))
                                            ->dehydrated(fn () => $this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                            ->columnSpanFull(),

                                        Textarea::make('featured_video_urls')
                                            ->label('Featured Video URLs')
                                            ->placeholder("https://youtube.com/watch?v=...\nhttps://youtube.com/watch?v=...")
                                            ->rows(5)
                                            ->disabled(fn () => ! ($this->getPlanInfo()?->hasPremiumAccess() ?? false))
                                            ->dehydrated(fn () => $this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                            ->helperText(fn () => ! ($this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                                ? 'This section is locked on Free.'
                                                : 'Enter one video URL per line.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Social')
                            ->icon(($this->getPlanInfo()?->hasPremiumAccess() ?? false) ? 'heroicon-m-share' : 'heroicon-m-lock-closed')
                            ->schema([
                                Section::make('Social Profiles')
                                    ->icon(($this->getPlanInfo()?->hasPremiumAccess() ?? false) ? 'heroicon-m-share' : 'heroicon-m-lock-closed')
                                    ->description(
                                        ($this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                            ? 'Add your social links and YouTube channel to your PLYRCard.'
                                            : 'Locked on Free. Upgrade to Plyr or My Journey to unlock this feature.'
                                    )
                                    ->columns(2)
                                    ->schema([
                                        Placeholder::make('social_lock_overlay')
                                            ->hidden(fn () => $this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                            ->columnSpanFull()
                                            ->content(new HtmlString('
                                                <div class="pc-inline-lock">
                                                    <div class="pc-inline-lock__icon">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.875a4.5 4.5 0 10-9 0V10.5m-.75 0h10.5A2.25 2.25 0 0119.5 12.75v6A2.25 2.25 0 0117.25 21h-10.5A2.25 2.25 0 014.5 18.75v-6A2.25 2.25 0 016.75 10.5z" />
                                                        </svg>
                                                    </div>
                                                    <div class="pc-inline-lock__content">
                                                        <h4>Unlock Social &amp; Video Links</h4>
                                                        <p>This feature is available on Plyr and My Journey.</p>
                                                    </div>
                                                    <button type="button" wire:click="openLockedFeatureModal" class="pc-inline-lock__button">See Plans</button>
                                                </div>
                                            ')),

                                        TextInput::make('ig_handle')
                                            ->label('Instagram Handle')
                                            ->prefixIcon(($this->getPlanInfo()?->hasPremiumAccess() ?? false) ? 'heroicon-m-camera' : 'heroicon-m-lock-closed')
                                            ->prefix('@')
                                            ->placeholder('yourhandle')
                                            ->maxLength(255)
                                            ->disabled(fn () => ! ($this->getPlanInfo()?->hasPremiumAccess() ?? false))
                                            ->dehydrated(fn () => $this->getPlanInfo()?->hasPremiumAccess() ?? false),

                                        TextInput::make('x_handle')
                                            ->label('X Handle')
                                            ->prefixIcon(($this->getPlanInfo()?->hasPremiumAccess() ?? false) ? 'heroicon-m-chat-bubble-left-right' : 'heroicon-m-lock-closed')
                                            ->prefix('@')
                                            ->placeholder('yourhandle')
                                            ->maxLength(255)
                                            ->disabled(fn () => ! ($this->getPlanInfo()?->hasPremiumAccess() ?? false))
                                            ->dehydrated(fn () => $this->getPlanInfo()?->hasPremiumAccess() ?? false),

                                        TextInput::make('yt_url')
                                            ->label('YouTube URL')
                                            ->prefixIcon(($this->getPlanInfo()?->hasPremiumAccess() ?? false) ? 'heroicon-m-link' : 'heroicon-m-lock-closed')
                                            ->placeholder('https://youtube.com/@yourchannel')
                                            ->url()
                                            ->disabled(fn () => ! ($this->getPlanInfo()?->hasPremiumAccess() ?? false))
                                            ->dehydrated(fn () => $this->getPlanInfo()?->hasPremiumAccess() ?? false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('People')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Section::make('Parents / Guardians')
                                    ->icon('heroicon-m-heart')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('parent')
                                            ->label('Primary Parent')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Full name')
                                            ->maxLength(255),

                                        TextInput::make('parent_email')
                                            ->label('Primary Parent Email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->placeholder('parent@example.com')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('parent_phone')
                                            ->label('Primary Parent Phone')
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('+1 (555) 000-0000')
                                            ->tel()
                                            ->maxLength(255),

                                        TextInput::make('sec_parent')
                                            ->label('Secondary Parent')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Full name')
                                            ->maxLength(255),

                                        TextInput::make('sec_parent_email')
                                            ->label('Secondary Parent Email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->placeholder('parent2@example.com')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('sec_parent_phone')
                                            ->label('Secondary Parent Phone')
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('+1 (555) 000-0000')
                                            ->tel()
                                            ->maxLength(255),
                                    ]),

                                Section::make('Coaches')
                                    ->icon('heroicon-m-megaphone')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('club_coach')
                                            ->label('Club Coach')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Coach name')
                                            ->maxLength(255),

                                        TextInput::make('club_coach_email')
                                            ->label('Club Coach Email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->placeholder('coach@example.com')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('club_coach_phone')
                                            ->label('Club Coach Phone')
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('+1 (555) 000-0000')
                                            ->tel()
                                            ->maxLength(255),

                                        TextInput::make('natl_coach')
                                            ->label('National Team Coach')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Coach name')
                                            ->maxLength(255),

                                        TextInput::make('natl_coach_email')
                                            ->label('National Team Coach Email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->placeholder('coach@example.com')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('natl_coach_phone')
                                            ->label('National Team Coach Phone')
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('+1 (555) 000-0000')
                                            ->tel()
                                            ->maxLength(255),
                                    ]),

                                Section::make('Trainers')
                                    ->icon('heroicon-m-bolt')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('tech_trainer')
                                            ->label('Technical Trainer')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Trainer name')
                                            ->maxLength(255),

                                        TextInput::make('tech_trainer_email')
                                            ->label('Technical Trainer Email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->placeholder('trainer@example.com')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('tech_trainer_phone')
                                            ->label('Technical Trainer Phone')
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('+1 (555) 000-0000')
                                            ->tel()
                                            ->maxLength(255),

                                        TextInput::make('snc_trainer')
                                            ->label('Strength & Conditioning Trainer')
                                            ->prefixIcon('heroicon-m-user')
                                            ->placeholder('Trainer name')
                                            ->maxLength(255),

                                        TextInput::make('snc_trainer_email')
                                            ->label('Strength & Conditioning Trainer Email')
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->placeholder('trainer@example.com')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('snc_trainer_phone')
                                            ->label('Strength & Conditioning Trainer Phone')
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('+1 (555) 000-0000')
                                            ->tel()
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tab::make('Website')
                            ->icon('heroicon-m-globe-alt')
                            ->schema([
                                Section::make('Website Settings')
                                    ->icon('heroicon-m-globe-alt')
                                    ->description('Configure your personal athlete website.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('domain')
                                            ->label('Custom Domain')
                                            ->prefixIcon('heroicon-m-link')
                                            ->helperText('Enter without https://')
                                            ->placeholder('yourdomain.com')
                                            ->columnSpan(2)
                                            ->disabled()
                                            ->maxLength(255)
                                            ->nullable(),

                                        Toggle::make('website_is_published')
                                            ->label('Website Published')
                                            ->disabled()
                                            ->default(false),
                                    ]),
                            ]),
                    ])
                    ->contained(true),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $websiteKeys = [
            'website_is_published',
        ];

        $websiteData = [
            'is_published' => (bool) ($data['website_is_published'] ?? false),
        ];

        $userData = $data;

        foreach ($websiteKeys as $key) {
            unset($userData[$key]);
        }

        if (! ($this->getPlanInfo()?->hasPremiumAccess() ?? false)) {
            unset(
                $userData['ig_handle'],
                $userData['x_handle'],
                $userData['yt_url'],
                $userData['featured_video_url'],
                $userData['featured_video_urls']
            );
        }

        $userData = $this->mutateProfileData($userData);

        $this->user->update($userData);

        $website = $this->user->websites()->first();

        if ($website) {
            $website->update($websiteData);
            $this->website = $website->fresh();
        }

        $this->user->refresh()->loadMissing('roles', 'nationalTeam');
        $this->planInfoCache = null;

        Notification::make()
            ->title('Profile saved successfully.')
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getProfileProgressPercentage(): int
    {
        if (! $this->user) {
            return 0;
        }

        $checks = [
            filled($this->user->first_name),
            filled($this->user->last_name),
            filled($this->user->email),
            filled($this->user->personal_email),
            filled($this->user->phone),
            filled($this->user->sport),
            filled($this->user->position),
            filled($this->user->gender),
            filled($this->user->year),
            filled($this->user->birth),
            filled($this->user->school_id),
            filled($this->user->height),
            filled($this->user->weight),
            filled($this->user->player_bio),
            filled($this->user->city),
            filled($this->user->state),
            filled($this->user->country),
            filled($this->user->player_image) || filled($this->user->plyrcard_image),
            filled($this->user->league_id),
            filled($this->user->club_id),
            filled($this->user->team_id ?? $this->user->team_name),
        ];

        $total = count($checks);
        $completed = collect($checks)->filter()->count();

        return (int) round(($completed / max($total, 1)) * 100);
    }

    public function isWebsitePublished(): bool
    {
        if ($this->website) {
            return (bool) $this->website->is_published;
        }

        return (bool) data_get($this->data, 'website_is_published', false);
    }

    public function canOpenPreviewCard(): bool
    {
        return $this->getProfileProgressPercentage() >= 75
            && $this->isWebsitePublished()
            && filled($this->getPreviewUrl());
    }

    public function handlePreviewCardClick(): void
    {
        if ($this->canOpenPreviewCard()) {
            return;
        }

        $progress = $this->getProfileProgressPercentage();
        $published = $this->isWebsitePublished();

        if ($progress < 75 && ! $published) {
            $this->previewAccessModalType = 'complete_profile';
            $this->previewAccessModalTitle = 'COMPLETE <span>YOUR PROFILE</span>';
            $this->previewAccessModalMessage = 'Your profile is currently ' . $progress . '% complete. Complete at least 75% of your profile before previewing your card.';
            $this->previewAccessModalActionUrl = url('/admin/profile');
            $this->previewAccessModalActionLabel = 'Complete Profile';
            $this->showPreviewAccessModal = true;

            return;
        }

        if ($progress >= 75 && ! $published) {
            $this->previewAccessModalType = 'under_review';
            $this->previewAccessModalTitle = 'SITE <span>UNDER REVIEW</span>';
            $this->previewAccessModalMessage = 'Your profile is complete enough for launch, and your website is currently under review. We\'ll make it available once it has been approved and published.';
            $this->previewAccessModalActionUrl = url('/admin/profile');
            $this->previewAccessModalActionLabel = 'Back to Profile';
            $this->showPreviewAccessModal = true;

            return;
        }

        if ($progress < 75) {
            $this->previewAccessModalType = 'complete_profile';
            $this->previewAccessModalTitle = 'COMPLETE <span>YOUR PROFILE</span>';
            $this->previewAccessModalMessage = 'Your profile is currently ' . $progress . '% complete. Complete at least 75% of your profile before previewing your card.';
            $this->previewAccessModalActionUrl = url('/profile');
            $this->previewAccessModalActionLabel = 'Complete Profile';
            $this->showPreviewAccessModal = true;
        }
    }

    public function getProfileInitials(): string
    {
        $first = strtoupper(substr((string) ($this->user?->first_name ?? ''), 0, 1));
        $last = strtoupper(substr((string) ($this->user?->last_name ?? ''), 0, 1));

        return trim($first . $last) ?: 'PC';
    }

    public function getProfileFullName(): string
    {
        return trim(collect([
            $this->user?->first_name,
            $this->user?->last_name,
        ])->filter()->implode(' '));
    }

    public function getProfileSportLabel(): ?string
    {
        $sport = $this->user?->sport;

        if (blank($sport)) {
            return null;
        }

        return str($sport)->replace('_', ' ')->title()->toString();
    }

    public function getProfileLocationLabel(): ?string
    {
        $city = trim((string) ($this->user?->city ?? ''));
        $state = trim((string) ($this->user?->state ?? ''));

        $parts = array_filter([$city, $state]);

        return count($parts) ? implode(', ', $parts) : null;
    }

    public function getProfileGraduationLabel(): ?string
    {
        return filled($this->user?->year)
            ? 'Class of ' . $this->user->year
            : null;
    }

    public function getNationalTeamLabel(): ?string
    {
        if ($this->user?->relationLoaded('nationalTeam') && $this->user?->nationalTeam?->name) {
            return $this->user->nationalTeam->name;
        }

        if ($this->user?->national_team_id) {
            return optional(NationalTeam::find($this->user->national_team_id))->name;
        }

        return null;
    }

    public function getNationalTeamBadgeLabel(): ?string
    {
        return filled($this->getNationalTeamLabel()) ? 'NATIONAL TEAM EXPERIENCE' : null;
    }

    public function getJerseyBadgeLabel(): ?string
    {
        return filled($this->user?->jersey_number)
            ? '#' . $this->user->jersey_number
            : null;
    }

    public function getProfileImageUrl(): ?string
    {
        $image = $this->user?->player_image ?: $this->user?->plyrcard_image;

        if (blank($image)) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return Storage::url($image);
    }

    protected function getPreviewUrl(): ?string
    {
        if (! $this->user) {
            return null;
        }

        $slugUrl = url(Str::slug($this->user->first_name . '-' . $this->user->last_name));

        if ($this->user->hasRole('Free')) {
            return $slugUrl;
        }

        $domain = trim((string) ($this->user->domain ?? ''));

        if (blank($domain)) {
            return $slugUrl;
        }

        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        return 'https://' . ltrim($domain, '/');
    }

    public function openLockedFeatureModal(): void
    {
        $this->lockedFeatureTitle = 'UNLOCK SOCIAL & VIDEO LINKS';
        $this->lockedFeatureMessage = 'This feature is available on Plyr and My Journey. Upgrade now to take your PLYRCard to the next level.';
        $this->showLockedFeatureModal = true;
    }

    public function closeLockedFeatureModal(): void
    {
        $this->showLockedFeatureModal = false;
    }

    public function closePreviewAccessModal(): void
    {
        $this->showPreviewAccessModal = false;
    }
}