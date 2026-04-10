<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\HeroTemplate;
use App\Models\HeroTemplateField;
use App\Models\League;
use App\Models\NationalTeam;
use App\Models\School;
use App\Models\SiteTemplate;
use App\Models\Team;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHeroFieldValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicPlayerIntakeController extends Controller
{
    protected array $sportPositions = [
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
    ];

    protected array $genderOptions = [
        'male' => 'Male',
        'female' => 'Female',
        'coed' => 'Coed',
    ];

    public function create(Request $request): View
    {
        $schools = School::query()->orderBy('name')->get();
        $clubs = Club::query()->with('league')->orderBy('name')->get();
        $leagues = League::query()->orderBy('name')->get();
        $teams = Team::query()->with(['club.league'])->orderBy('name')->get();
        $nationalTeams = NationalTeam::query()->orderBy('name')->get();

        $states = [
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
            'DC' => 'District of Columbia',
        ];

        $countryOptions = [
            'USA' => 'United States',
            'Austria' => 'Austria',
            'Belgium' => 'Belgium',
            'Bulgaria' => 'Bulgaria',
            'Croatia' => 'Croatia',
            'Cyprus' => 'Cyprus',
            'Czech Republic' => 'Czech Republic',
            'Denmark' => 'Denmark',
            'Estonia' => 'Estonia',
            'Finland' => 'Finland',
            'France' => 'France',
            'Germany' => 'Germany',
            'Greece' => 'Greece',
            'Hungary' => 'Hungary',
            'Iceland' => 'Iceland',
            'Ireland' => 'Ireland',
            'Italy' => 'Italy',
            'Latvia' => 'Latvia',
            'Lithuania' => 'Lithuania',
            'Luxembourg' => 'Luxembourg',
            'Malta' => 'Malta',
            'Netherlands' => 'Netherlands',
            'Norway' => 'Norway',
            'Poland' => 'Poland',
            'Portugal' => 'Portugal',
            'Romania' => 'Romania',
            'Slovakia' => 'Slovakia',
            'Slovenia' => 'Slovenia',
            'Spain' => 'Spain',
            'Sweden' => 'Sweden',
            'Switzerland' => 'Switzerland',
            'United Kingdom' => 'United Kingdom',
            '__other__' => 'Other',
        ];

        $detectedCountry = $this->detectCountryCode($request);

        return view('public.player-intake', [
            'schools' => $schools,
            'clubs' => $clubs,
            'leagues' => $leagues,
            'teams' => $teams,
            'nationalTeams' => $nationalTeams,
            'states' => $states,
            'countryOptions' => $countryOptions,
            'sportPositions' => $this->sportPositions,
            'genderOptions' => $this->genderOptions,
            'detectedCountry' => $detectedCountry,
            'stepFieldMap' => $this->stepFieldMap(),
        ]);
    }

    protected function detectCountryCode(Request $request): string
    {
        $headerCandidates = [
            $request->header('CF-IPCountry'),
            $request->server('HTTP_CF_IPCOUNTRY'),
            $request->header('CloudFront-Viewer-Country'),
            $request->header('X-Country-Code'),
            $request->server('GEOIP_COUNTRY_CODE'),
        ];

        foreach ($headerCandidates as $country) {
            $country = strtoupper(trim((string) $country));

            if (preg_match('/^[A-Z]{2}$/', $country)) {
                return $country;
            }
        }

        try {
            $ip = $request->ip();
            $location = geoip()->getLocation($ip);
            $countryCode = strtoupper(trim((string) ($location->country_code2 ?? '')));

            if (preg_match('/^[A-Z]{2}$/', $countryCode)) {
                return $countryCode;
            }
        } catch (\Throwable $e) {
            // Fail silently.
        }

        return '';
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'personal_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:' . implode(',', array_keys($this->genderOptions))],

            'country' => ['nullable', 'string', 'max:255'],
            'country_other' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],

            'gpa' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'string', 'max:50'],
            'birth' => ['nullable', 'date'],
            'height' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'string', 'max:50'],
            'jersey_number' => ['nullable', 'string', 'max:50'],
            'vertical_jump' => ['nullable', 'string', 'max:50'],
            'dominant_foot' => ['nullable', 'in:left,right,both'],
            'max_speed' => ['nullable', 'string', 'max:50'],

            'sport' => ['required', 'string', 'in:' . implode(',', array_keys($this->sportPositions))],
            'position' => ['nullable', 'array'],
            'position.*' => ['string', 'max:255'],

            'academic_accolades' => ['nullable', 'string'],
            'sports_accolades' => ['nullable', 'string'],
            'natl_team_exp' => ['nullable', 'in:0,1'],
            'national_team_period' => ['nullable', 'string', 'max:255'],

            'team_id' => ['nullable', 'string'],
            'team_other' => ['nullable', 'string', 'max:255'],

            'ig_handle' => ['nullable', 'url', 'max:255'],
            'x_handle' => ['nullable', 'url', 'max:255'],
            'yt_url' => ['nullable', 'url', 'max:500'],
            'press' => ['nullable', 'string'],

            'parent' => ['nullable', 'string', 'max:255'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'sec_parent' => ['nullable', 'string', 'max:255'],
            'sec_parent_email' => ['nullable', 'email', 'max:255'],
            'sec_parent_phone' => ['nullable', 'string', 'max:50'],

            'club_coach' => ['nullable', 'string', 'max:255'],
            'club_coach_email' => ['nullable', 'email', 'max:255'],
            'club_coach_phone' => ['nullable', 'string', 'max:50'],
            'natl_coach' => ['nullable', 'string', 'max:255'],
            'natl_coach_email' => ['nullable', 'email', 'max:255'],
            'natl_coach_phone' => ['nullable', 'string', 'max:50'],
            'tech_trainer' => ['nullable', 'string', 'max:255'],
            'tech_trainer_email' => ['nullable', 'email', 'max:255'],
            'tech_trainer_phone' => ['nullable', 'string', 'max:50'],
            'snc_trainer' => ['nullable', 'string', 'max:255'],
            'snc_trainer_email' => ['nullable', 'email', 'max:255'],
            'snc_trainer_phone' => ['nullable', 'string', 'max:50'],

            'school_id' => ['nullable', 'string'],
            'school_other' => ['nullable', 'string', 'max:255'],

            'league_other' => ['nullable', 'string', 'max:255'],
            'club_other' => ['nullable', 'string', 'max:255'],

            'national_team_id' => ['nullable', 'string'],
            'national_team_other' => ['nullable', 'string', 'max:255'],

            'action_images' => ['nullable', 'array'],
            'action_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],

            'portrait_images' => ['nullable', 'array'],
            'portrait_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],

            'national_team_images' => ['nullable', 'array'],
            'national_team_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],

            'team_images' => ['nullable', 'array'],
            'team_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],

            'player_bio' => ['nullable', 'string'],
            'featured_video_url' => ['nullable', 'url', 'max:500'],
            'use_custom_highlights' => ['nullable', 'boolean'],
            'featured_video_urls' => ['nullable', 'string'],
        ]);

        $totalRawImages =
            count($request->file('action_images', [])) +
            count($request->file('portrait_images', [])) +
            count($request->file('national_team_images', [])) +
            count($request->file('team_images', []));

        if ($totalRawImages > 20) {
            throw ValidationException::withMessages([
                'action_images' => 'You may upload a combined maximum of 20 raw images across Action, Portrait, National Team, and Team images.',
            ]);
        }

        if (($validated['country'] ?? null) === '__other__' && filled($validated['country_other'] ?? null)) {
            $validated['country'] = trim($validated['country_other']);
        }

        if (($validated['country'] ?? null) === '__other__' && blank($validated['country_other'] ?? null)) {
            return back()
                ->withErrors(['country_other' => 'Please enter a country name.'])
                ->withInput();
        }

        if (($validated['country'] ?? null) === 'USA' && filled($validated['state'] ?? null)) {
            $validated['state'] = strtoupper(trim($validated['state']));
        }

        $sport = $validated['sport'];
        $allowedPositions = array_keys($this->sportPositions[$sport] ?? []);
        $submittedPositions = $validated['position'] ?? [];

        foreach ($submittedPositions as $position) {
            if (!in_array($position, $allowedPositions, true)) {
                return back()
                    ->withErrors(['position' => 'One or more selected positions do not match the chosen sport.'])
                    ->withInput();
            }
        }

        if ($sport === 'soccer' && blank($validated['dominant_foot'] ?? null)) {
            return back()
                ->withErrors(['dominant_foot' => 'Dominant foot is required for soccer.'])
                ->withInput();
        }

        if ($sport !== 'soccer') {
            $validated['dominant_foot'] = null;
        }

        if (($validated['team_id'] ?? null) === '__other__') {
            if (blank($validated['league_other'] ?? null)) {
                return back()->withErrors(['league_other' => 'Please enter the new league name.'])->withInput();
            }

            if (blank($validated['club_other'] ?? null)) {
                return back()->withErrors(['club_other' => 'Please enter the new club name.'])->withInput();
            }

            if (blank($validated['team_other'] ?? null)) {
                return back()->withErrors(['team_other' => 'Please enter the new team name.'])->withInput();
            }
        }

        $useCustomHighlights = $request->boolean('use_custom_highlights');
        $manualVideoUrls = $this->normalizeVideoUrls($validated['featured_video_urls'] ?? null);

        if ($useCustomHighlights && empty($manualVideoUrls)) {
            throw ValidationException::withMessages([
                'featured_video_urls' => 'Please add at least one highlight video URL or turn off "Pick My Own Videos".',
            ]);
        }

        $user = DB::transaction(function () use ($request, $validated, $useCustomHighlights, $manualVideoUrls) {
            $school = $this->resolveSchool($validated);
            [$league, $club, $team] = $this->resolveLeagueClubAndTeam($validated);
            $nationalTeam = $this->resolveNationalTeam($validated);

            $fullNameNoSpaces = $this->fullNameNoSpaces(
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name']
            );

            $firstNameSlug = Str::lower(Str::ascii($validated['first_name']));
            $firstNameSlug = preg_replace('/[^a-z0-9]/', '', $firstNameSlug) ?: 'player';

            $nameDomainStem = Str::lower(Str::ascii($fullNameNoSpaces));
            $nameDomainStem = preg_replace('/[^a-z0-9]/', '', $nameDomainStem) ?: ('player' . time());

            $generatedEmail = $firstNameSlug . '@' . $nameDomainStem . '.com';
            $generatedDomain = $nameDomainStem . '.com';

            $user = User::withTrashed()
                ->where('personal_email', $validated['personal_email'])
                ->orWhere('email', $generatedEmail)
                ->first();

            if (!$user) {
                $user = new User();
                $user->password = Hash::make(Str::random(40));
            }

            if (method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }

            $hasNationalTeamExperience = isset($validated['natl_team_exp']) ? (bool) $validated['natl_team_exp'] : false;

            $user->fill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'personal_email' => $validated['personal_email'],
                'email' => $generatedEmail,
                'phone' => $validated['phone'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'country' => $validated['country'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'street' => $validated['street'] ?? null,
                'gpa' => $validated['gpa'] ?? null,
                'year' => $validated['year'] ?? null,
                'birth' => $validated['birth'] ?? null,
                'height' => $validated['height'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'jersey_number' => $validated['jersey_number'] ?? null,
                'vertical_jump' => $validated['vertical_jump'] ?? null,
                'dominant_foot' => ($validated['sport'] ?? null) === 'soccer'
                    ? ($validated['dominant_foot'] ?? null)
                    : null,
                'max_speed' => $validated['max_speed'] ?? null,
                'sport' => $validated['sport'],
                'position' => $validated['position'] ?? [],
                'academic_accolades' => $validated['academic_accolades'] ?? null,
                'sports_accolades' => $validated['sports_accolades'] ?? null,
                'natl_team_exp' => $hasNationalTeamExperience,
                'national_team_period' => $hasNationalTeamExperience ? ($validated['national_team_period'] ?? null) : null,
                'team_name' => $team?->name,
                'ig_handle' => $validated['ig_handle'] ?? null,
                'x_handle' => $validated['x_handle'] ?? null,
                'yt_url' => $validated['yt_url'] ?? null,
                'press' => $validated['press'] ?? null,
                'parent' => $validated['parent'] ?? null,
                'parent_email' => $validated['parent_email'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
                'sec_parent' => $validated['sec_parent'] ?? null,
                'sec_parent_email' => $validated['sec_parent_email'] ?? null,
                'sec_parent_phone' => $validated['sec_parent_phone'] ?? null,
                'club_coach' => $validated['club_coach'] ?? null,
                'club_coach_email' => $validated['club_coach_email'] ?? null,
                'club_coach_phone' => $validated['club_coach_phone'] ?? null,
                'natl_coach' => $validated['natl_coach'] ?? null,
                'natl_coach_email' => $validated['natl_coach_email'] ?? null,
                'natl_coach_phone' => $validated['natl_coach_phone'] ?? null,
                'tech_trainer' => $validated['tech_trainer'] ?? null,
                'tech_trainer_email' => $validated['tech_trainer_email'] ?? null,
                'tech_trainer_phone' => $validated['tech_trainer_phone'] ?? null,
                'snc_trainer' => $validated['snc_trainer'] ?? null,
                'snc_trainer_email' => $validated['snc_trainer_email'] ?? null,
                'snc_trainer_phone' => $validated['snc_trainer_phone'] ?? null,
                'school_id' => $school?->id,
                'club_id' => $club?->id,
                'league_id' => $league?->id,
                'national_team_id' => $hasNationalTeamExperience ? $nationalTeam?->id : null,
                'domain' => $generatedDomain,
                'player_bio' => $validated['player_bio'] ?? null,
                'featured_video_url' => $validated['featured_video_url'] ?? null,
                'featured_video_urls' => $useCustomHighlights ? implode("\n", $manualVideoUrls) : null,
            ]);

            $userImageUploads = $this->storeUserImageUploads($request);

            if (!empty($userImageUploads['raw_player_images'])) {
                $user->raw_player_images = $userImageUploads['raw_player_images'];
            }

            $user->save();

            $uploads = $this->storeHeroUploads($request);

            $this->createWebsiteIfSupported($user, $validated, $uploads, $generatedDomain);

            return $user;
        });

        return redirect()
            ->route('public.player-intake.create')
            ->with('success', 'Player intake submitted successfully for ' . $user->first_name . '.');
    }

    protected function stepFieldMap(): array
    {
        return [
            1 => [
                'first_name', 'middle_name', 'last_name', 'personal_email', 'phone', 'gender',
                'birth', 'year', 'sport', 'jersey_number', 'vertical_jump', 'gpa', 'height',
                'weight', 'max_speed', 'dominant_foot', 'position', 'position.*',
                'natl_team_exp', 'national_team_period',
            ],
            2 => [
                'country', 'country_other', 'state', 'city', 'street',
                'school_id', 'school_other', 'team_id', 'team_other', 'league_other',
                'club_other', 'national_team_id', 'national_team_other',
            ],
            3 => [
                'ig_handle', 'x_handle', 'yt_url', 'featured_video_url',
                'use_custom_highlights', 'featured_video_urls', 'player_bio',
                'academic_accolades', 'sports_accolades', 'press',
            ],
            4 => [
                'parent', 'parent_email', 'parent_phone',
                'sec_parent', 'sec_parent_email', 'sec_parent_phone',
                'club_coach', 'club_coach_email', 'club_coach_phone',
                'natl_coach', 'natl_coach_email', 'natl_coach_phone',
                'tech_trainer', 'tech_trainer_email', 'tech_trainer_phone',
                'snc_trainer', 'snc_trainer_email', 'snc_trainer_phone',
            ],
            5 => [
                'action_images', 'action_images.*',
                'portrait_images', 'portrait_images.*',
                'national_team_images', 'national_team_images.*',
                'team_images', 'team_images.*',
            ],
        ];
    }

    protected function normalizeVideoUrls(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveSchool(array $validated): ?School
    {
        if (($validated['school_id'] ?? null) === '__other__' && filled($validated['school_other'] ?? null)) {
            return School::firstOrCreate(
                ['name' => trim($validated['school_other'])],
                [
                    'state' => $validated['state'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'street' => $validated['street'] ?? null,
                    'zipcode' => null,
                ]
            );
        }

        if (!empty($validated['school_id']) && $validated['school_id'] !== '__other__') {
            return School::find($validated['school_id']);
        }

        return null;
    }

    protected function resolveLeagueClubAndTeam(array $validated): array
    {
        $league = null;
        $club = null;
        $team = null;

        $teamId = $validated['team_id'] ?? null;

        if ($teamId === '__other__') {
            $league = League::firstOrCreate(
                ['name' => trim($validated['league_other'])],
                ['gender' => $validated['gender'] ?? null]
            );

            $club = Club::firstOrCreate(
                [
                    'name' => trim($validated['club_other']),
                    'league_id' => $league->id,
                ]
            );

            $team = Team::firstOrCreate(
                [
                    'name' => trim($validated['team_other']),
                    'club_id' => $club->id,
                ]
            );

            return [$league, $club, $team];
        }

        if (filled($teamId)) {
            $team = Team::with('club.league')->find($teamId);
            $club = $team?->club;
            $league = $club?->league;

            return [$league, $club, $team];
        }

        return [null, null, null];
    }

    protected function resolveNationalTeam(array $validated): ?NationalTeam
    {
        $nationalTeamId = $validated['national_team_id'] ?? null;

        if ($nationalTeamId === '__other__') {
            if (filled($validated['national_team_other'] ?? null)) {
                return NationalTeam::firstOrCreate([
                    'name' => trim($validated['national_team_other']),
                ]);
            }

            return null;
        }

        if (filled($nationalTeamId)) {
            return NationalTeam::find($nationalTeamId);
        }

        return null;
    }

    protected function storeHeroUploads(Request $request): array
    {
        return [];
    }

    protected function createWebsiteIfSupported(User $user, array $validated, array $uploads, string $generatedDomain): ?Website
    {
        $siteTemplate = $this->resolveSiteTemplate($validated['sport']);
        $heroTemplate = $this->resolveHeroTemplate($validated['sport']);

        if (!$siteTemplate || !$heroTemplate) {
            return null;
        }

        $existingWebsite = Website::query()
            ->where('user_id', $user->id)
            ->first();

        $websiteName = trim($user->first_name . ' ' . $user->last_name);
        $slugBase = Str::slug($websiteName ?: ('player-' . $user->id));

        if ($existingWebsite) {
            $existingWebsite->fill([
                'site_template_id' => $siteTemplate->id,
                'hero_template_id' => $heroTemplate->id,
                'name' => $websiteName,
                'domain' => $generatedDomain,
                'is_active' => true,
                'is_published' => false,
                'project_json' => !empty($uploads) ? json_encode(['hero_uploads' => $uploads]) : $existingWebsite->project_json,
            ]);

            if (blank($existingWebsite->slug)) {
                $existingWebsite->slug = $this->generateUniqueWebsiteSlug($slugBase);
            }

            $existingWebsite->save();

            $this->attachHeroFieldUploads($existingWebsite, $uploads);

            return $existingWebsite;
        }

        $slug = $this->generateUniqueWebsiteSlug($slugBase);

        $website = Website::create([
            'user_id' => $user->id,
            'site_template_id' => $siteTemplate->id,
            'hero_template_id' => $heroTemplate->id,
            'name' => $websiteName,
            'slug' => $slug,
            'domain' => $generatedDomain,
            'is_active' => true,
            'is_published' => false,
            'project_json' => !empty($uploads) ? json_encode(['hero_uploads' => $uploads]) : null,
            'html' => null,
            'css' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'accent_color' => null,
            'background_color' => null,
            'surface_color' => null,
            'text_primary_color' => null,
            'text_secondary_color' => null,
        ]);

        $this->attachHeroFieldUploads($website, $uploads);

        return $website;
    }

    protected function resolveSiteTemplate(string $sport): ?SiteTemplate
    {
        return SiteTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('sports')
                    ->orWhereJsonLength('sports', 0);
            })
            ->orderBy('id')
            ->first();
    }

    protected function resolveHeroTemplate(string $sport = null): ?HeroTemplate
    {
        return HeroTemplate::query()
            ->where('is_active', true)
            ->where('blade_view', 'hero.hero_template_free')
            ->orderBy('id')
            ->first();
    }

    protected function attachHeroFieldUploads(Website $website, array $uploads): void
    {
        if (empty($uploads) || !$website->hero_template_id) {
            return;
        }

        $heroFieldMapByTemplate = [
            1 => [
                'mobile_view_image' => ['hero_mobile_image'],
            ],
            2 => [
                'mobile_view_image' => ['hero_mobile_image'],
            ],
            4 => [
                'mobile_view_image' => ['hero_mobile_image'],
            ],
        ];

        $fieldMap = $heroFieldMapByTemplate[$website->hero_template_id] ?? [
            'mobile_view_image' => ['hero_mobile_image'],
        ];

        foreach ($fieldMap as $requestField => $candidateNames) {
            $path = $uploads[$requestField] ?? null;

            if (!$path) {
                continue;
            }

            $templateField = HeroTemplateField::query()
                ->where('hero_template_id', $website->hero_template_id)
                ->whereIn('name', $candidateNames)
                ->first();

            if (!$templateField) {
                continue;
            }

            WebsiteHeroFieldValue::updateOrCreate(
                [
                    'website_id' => $website->id,
                    'hero_template_field_id' => $templateField->id,
                ],
                [
                    'value' => $path,
                    'meta' => [
                        'disk' => 'public',
                        'type' => 'image',
                        'source' => 'public_player_intake',
                    ],
                ]
            );
        }
    }

    protected function generateUniqueWebsiteSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (Website::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected function fullNameNoSpaces(string $firstName, ?string $middleName, string $lastName): string
    {
        return collect([$firstName, $middleName, $lastName])
            ->filter()
            ->map(fn ($value) => trim($value))
            ->implode('');
    }

    protected function storeUserImageUploads(Request $request): array
    {
        $paths = [];

        $groupedFiles = collect([
            ...array_filter($request->file('action_images') ?? []),
            ...array_filter($request->file('portrait_images') ?? []),
            ...array_filter($request->file('national_team_images') ?? []),
            ...array_filter($request->file('team_images') ?? []),
        ]);

        $storedFiles = $groupedFiles
            ->filter(fn ($file) => $file && $file->isValid())
            ->take(20)
            ->map(function ($file) {
                return $file->store('user-player-images', 'public');
            })
            ->values()
            ->all();

        if (!empty($storedFiles)) {
            $paths['raw_player_images'] = $storedFiles;
        }

        return $paths;
    }
}