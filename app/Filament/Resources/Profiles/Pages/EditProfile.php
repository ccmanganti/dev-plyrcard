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
use App\Models\User;
use App\Models\Website;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ProfileResource::class;

    protected string $view = 'filament.resources.profiles.pages.edit-profile';

    public ?User $user = null;

    public ?Website $website = null;

    public array $data = [];

    public function mount(): void
    {
        $this->user = auth()->user();

        abort_unless($this->user, 403);

        $this->user->loadMissing('roles', 'nationalTeam');

        $this->website = $this->user->websites()->first();

        $this->form->fill([
            ...$this->user->toArray(),

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

    protected static function getSchoolOptions(): array
    {
        return ['__new__' => 'Add New'] + School::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getClubOptions(): array
    {
        return ['__new__' => 'Add New'] + Club::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getLeagueOptions(): array
    {
        return ['__new__' => 'Add New'] + League::query()
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

    protected static function resolvePreviewImageUrl($template): ?string
    {
        if (! $template) {
            return null;
        }

        foreach ([
            'preview_image_url',
            'preview_image',
            'image_url',
            'image',
            'thumbnail_url',
            'thumbnail',
        ] as $field) {
            $value = data_get($template, $field);

            if (blank($value)) {
                continue;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            return Storage::url($value);
        }

        return null;
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
                                            ->label('State')
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
                                            ->live(),

                                        Select::make('position')
                                            ->prefixIcon('heroicon-m-rectangle-group')
                                            ->label('Position')
                                            ->placeholder('Select position')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->options(fn (callable $get): array => static::getPositionOptions($get('sport')))
                                            ->disabled(fn (callable $get): bool => blank($get('sport')))
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
                                            ->nullable(),

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
                                            ->visible(fn (callable $get) => $get('school_id') === '__new__')
                                            ->required(fn (callable $get) => $get('school_id') === '__new__'),
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
                                            ->visible(fn (callable $get) => $get('sport') === 'soccer')
                                            ->required(fn (callable $get) => $get('sport') === 'soccer'),
                                    ]),

                                Section::make('Experience')
                                    ->icon('heroicon-m-flag')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('club_id')
                                            ->prefixIcon('heroicon-m-shield-check')
                                            ->label('Club')
                                            ->placeholder('Select club')
                                            ->options(fn () => static::getClubOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        Select::make('league_id')
                                            ->prefixIcon('heroicon-m-squares-2x2')
                                            ->label('League')
                                            ->placeholder('Select league')
                                            ->options(fn () => static::getLeagueOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        Select::make('national_team_id')
                                            ->prefixIcon('heroicon-m-flag')
                                            ->label('National Team Experience')
                                            ->placeholder('Select national team')
                                            ->options(fn () => static::getNationalTeamOptions())
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        TextInput::make('team_name')
                                            ->prefixIcon('heroicon-m-users')
                                            ->label('Team')
                                            ->placeholder('Enter team name')
                                            ->maxLength(255),

                                        TextInput::make('new_club_name')
                                            ->prefixIcon('heroicon-m-plus-circle')
                                            ->label('New Club Name')
                                            ->placeholder('Enter club name')
                                            ->maxLength(255)
                                            ->visible(fn (callable $get) => $get('club_id') === '__new__')
                                            ->required(fn (callable $get) => $get('club_id') === '__new__'),

                                        FileUpload::make('new_club_logo')
                                            ->label('New Club Logo')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('club-logos')
                                            ->visibility('public')
                                            ->helperText('Optional.')
                                            ->visible(fn (callable $get) => $get('club_id') === '__new__'),

                                        TextInput::make('new_league_name')
                                            ->prefixIcon('heroicon-m-plus-circle')
                                            ->label('New League Name')
                                            ->placeholder('Enter league name')
                                            ->maxLength(255)
                                            ->visible(fn (callable $get) => $get('league_id') === '__new__')
                                            ->required(fn (callable $get) => $get('league_id') === '__new__'),

                                        FileUpload::make('new_league_logo')
                                            ->label('New League Logo')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('league-logos')
                                            ->visibility('public')
                                            ->helperText('Optional.')
                                            ->visible(fn (callable $get) => $get('league_id') === '__new__'),

                                        TextInput::make('new_national_team_name')
                                            ->prefixIcon('heroicon-m-plus-circle')
                                            ->label('New National Team Name')
                                            ->placeholder('Enter national team name')
                                            ->maxLength(255)
                                            ->visible(fn (callable $get) => $get('national_team_id') === '__new__')
                                            ->required(fn (callable $get) => $get('national_team_id') === '__new__'),

                                        FileUpload::make('new_national_team_logo')
                                            ->label('New National Team Logo')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('national-team-logos')
                                            ->visibility('public')
                                            ->helperText('Optional.')
                                            ->visible(fn (callable $get) => $get('national_team_id') === '__new__'),
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
                                    ->icon('heroicon-m-play-circle')
                                    ->description('Embed your game highlights, recruiting videos, and performance reels directly on your PLYRCard.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('featured_video_url')
                                            ->prefixIcon('heroicon-m-link')
                                            ->label('Featured Video URL')
                                            ->placeholder('https://youtube.com/watch?v=...')
                                            ->url()
                                            ->columnSpanFull(),

                                        Textarea::make('featured_video_urls')
                                            ->label('Featured Video URLs')
                                            ->placeholder("https://youtube.com/watch?v=...\nhttps://youtube.com/watch?v=...")
                                            ->rows(5)
                                            ->helperText('Enter one video URL per line.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Social')
                            ->icon('heroicon-m-share')
                            ->schema([
                                Section::make('Social Profiles')
                                    ->icon('heroicon-m-share')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('ig_handle')
                                            ->label('Instagram Handle')
                                            ->prefixIcon('heroicon-m-camera')
                                            ->prefix('@')
                                            ->placeholder('yourhandle')
                                            ->maxLength(255),

                                        TextInput::make('x_handle')
                                            ->label('X Handle')
                                            ->prefixIcon('heroicon-m-chat-bubble-left-right')
                                            ->prefix('@')
                                            ->placeholder('yourhandle')
                                            ->maxLength(255),

                                        TextInput::make('yt_url')
                                            ->label('YouTube URL')
                                            ->prefixIcon('heroicon-m-link')
                                            ->placeholder('https://youtube.com/@yourchannel')
                                            ->url()
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
                                            ->maxLength(255)
                                            ->nullable(),

                                        Toggle::make('website_is_published')
                                            ->label('Website Published')
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
            'website_name',
            'site_template_id',
            'hero_template_id',
            'website_is_active',
            'website_is_published',
            'primary_color',
            'secondary_color',
            'accent_color',
            'background_color',
            'surface_color',
            'text_primary_color',
            'text_secondary_color',
        ];

        $websiteData = [
            'name' => $data['website_name'] ?? null,
            'site_template_id' => $data['site_template_id'] ?? null,
            'hero_template_id' => $data['hero_template_id'] ?? null,
            'is_active' => $data['website_is_active'] ?? true,
            'is_published' => $data['website_is_published'] ?? false,
            'primary_color' => $data['primary_color'] ?? null,
            'secondary_color' => $data['secondary_color'] ?? null,
            'accent_color' => $data['accent_color'] ?? null,
            'background_color' => $data['background_color'] ?? null,
            'surface_color' => $data['surface_color'] ?? null,
            'text_primary_color' => $data['text_primary_color'] ?? null,
            'text_secondary_color' => $data['text_secondary_color'] ?? null,
        ];

        $userData = $data;

        foreach ($websiteKeys as $key) {
            unset($userData[$key]);
        }

        $userData = $this->mutateProfileData($userData);

        $this->user->update($userData);

        $website = $this->user->websites()->first();

        if (! $website) {
            $website = new Website();
            $website->user_id = $this->user->id;
        }

        $website->fill($websiteData);
        $website->save();

        $this->website = $website;
        $this->user->refresh()->loadMissing('roles', 'nationalTeam');

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
        return 'My Profile';
    }

    public function getCurrentPlanKey(): string
    {
        if (! $this->user) {
            return 'rookie';
        }

        if (method_exists($this->user, 'hasRole')) {
            if ($this->user->hasRole('My Journey')) {
                return 'my_journey';
            }

            if ($this->user->hasRole('Rookie Plus')) {
                return 'rookie_plus';
            }

            if ($this->user->hasRole('Rookie')) {
                return 'rookie';
            }
        }

        return 'rookie';
    }

    public function getPlanName(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'my_journey' => 'MY JOURNEY',
            'rookie_plus' => 'ROOKIE PLUS',
            default => 'ROOKIE',
        };
    }

    public function getPlanHeadline(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'my_journey' => "YOU'RE ON MY JOURNEY",
            'rookie_plus' => "YOU'RE ON ROOKIE PLUS",
            default => "YOU'RE ON ROOKIE",
        };
    }

    public function getPlanDescription(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'my_journey' => 'You are on the highest plan. Your full PLYRCard experience is unlocked.',
            'rookie_plus' => 'You have expanded access and features. Upgrade to My Journey to unlock the full PLYRCard experience.',
            default => 'Social links, YouTube highlights, and additional media slots are locked. Upgrade to unlock more of the PLYRCard experience.',
        };
    }

    public function canUpgradePlan(): bool
    {
        return $this->getCurrentPlanKey() !== 'my_journey';
    }

    public function getUpgradeButtonLabel(): string
    {
        return match ($this->getCurrentPlanKey()) {
            'rookie_plus' => 'Upgrade to My Journey',
            default => 'Upgrade Now',
        };
    }

    public function getUpgradeUrl(): string
    {
        return '#';
    }

    public function getPlanTheme(): string
    {
        return $this->getCurrentPlanKey() === 'my_journey'
            ? 'success'
            : 'warning';
    }

    public function getProfileInitials(): string
    {
        $first = strtoupper(substr((string) $this->user?->first_name, 0, 1));
        $last = strtoupper(substr((string) $this->user?->last_name, 0, 1));

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
        $domain = trim((string) ($this->user?->domain ?? ''));

        if (blank($domain)) {
            return null;
        }

        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        return 'https://' . ltrim($domain, '/');
    }
}