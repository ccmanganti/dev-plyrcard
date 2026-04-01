<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\HeroTemplate;
use App\Models\League;
use App\Models\School;
use App\Models\SiteTemplate;
use App\Models\User;
use App\Models\Website;
use App\Models\HeroTemplateField;
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
        $schools = School::query()
            ->orderBy('name')
            ->get();

        $clubs = Club::query()
            ->orderBy('name')
            ->get();

        $leagues = League::query()
            ->orderBy('name')
            ->get();

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
            'states' => $states,
            'countryOptions' => $countryOptions,
            'sportPositions' => $this->sportPositions,
            'genderOptions' => $this->genderOptions,
            'detectedCountry' => $detectedCountry,
        ]);
    }

        protected function detectCountryCode(Request $request): string
        {
            $candidates = [
                $request->header('CF-IPCountry'),
                $request->header('CloudFront-Viewer-Country'),
                $request->header('X-Country-Code'),
                $request->server('GEOIP_COUNTRY_CODE'),
                $request->server('HTTP_CF_IPCOUNTRY'),
            ];

            foreach ($candidates as $country) {
                $country = strtoupper((string) $country);

                if (preg_match('/^[A-Z]{2}$/', $country)) {
                    return $country;
                }
            }

            return 'US';
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
            'max_speed' => ['nullable', 'string', 'max:50'],

            'sport' => ['required', 'string', 'in:' . implode(',', array_keys($this->sportPositions))],
            'position' => ['nullable', 'array'],
            'position.*' => ['string', 'max:255'],

            'academic_accolades' => ['nullable', 'string'],
            'sports_accolades' => ['nullable', 'string'],
            'natl_team_exp' => ['nullable', 'in:0,1'],
            'team_name' => ['nullable', 'string', 'max:255'],
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

            'league_id' => ['nullable', 'string'],
            'league_other' => ['nullable', 'string', 'max:255'],

            'club_id' => ['nullable', 'string'],
            'club_other' => ['nullable', 'string', 'max:255'],

            'player_image' => ['nullable', 'array', 'max:20'],
            'player_image.*' => ['image', 'mimes:png', 'max:5120'],

            'player_bio' => ['nullable', 'string'],
            'featured_video_url' => ['nullable', 'url', 'max:500'],
            'use_custom_highlights' => ['nullable', 'boolean'],
            'featured_video_urls' => ['nullable', 'string'],
        ]);

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
            if (! in_array($position, $allowedPositions, true)) {
                return back()
                    ->withErrors(['position' => 'One or more selected positions do not match the chosen sport.'])
                    ->withInput();
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
            [$league, $club] = $this->resolveClubAndLeague($validated);

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

            if (! $user) {
                $user = new User();
                $user->password = Hash::make(Str::random(40));
            }

            if (method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }

            $user->fill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'personal_email' => $validated['personal_email'],
                'email' => $generatedEmail,
                'phone' => $validated['phone'] ?? null,
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
                'max_speed' => $validated['max_speed'] ?? null,
                'sport' => $validated['sport'],
                'position' => $validated['position'] ?? [],
                'academic_accolades' => $validated['academic_accolades'] ?? null,
                'sports_accolades' => $validated['sports_accolades'] ?? null,
                'natl_team_exp' => isset($validated['natl_team_exp']) ? (bool) $validated['natl_team_exp'] : false,
                'team_name' => $validated['team_name'] ?? null,
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
                'domain' => $generatedDomain,
                'player_bio' => $validated['player_bio'] ?? null,
                'featured_video_url' => $validated['featured_video_url'] ?? null,
                'featured_video_urls' => $useCustomHighlights ? implode("\n", $manualVideoUrls) : null,
            ]);

            $userImageUploads = $this->storeUserImageUploads($request);

            if (! empty($userImageUploads['raw_player_images'])) {
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

        if (! empty($validated['school_id']) && $validated['school_id'] !== '__other__') {
            return School::find($validated['school_id']);
        }

        return null;
    }

    protected function resolveClubAndLeague(array $validated): array
    {
        $league = null;
        $club = null;

        $leagueId = $validated['league_id'] ?? null;
        $clubId = $validated['club_id'] ?? null;

        if ($leagueId === '__other__') {
            if (filled($validated['league_other'] ?? null) && filled($validated['gender'] ?? null)) {
                $league = League::firstOrCreate(
                    ['name' => trim($validated['league_other'])],
                    ['gender' => $validated['gender']]
                );
            }
        } elseif (filled($leagueId)) {
            $league = League::find($leagueId);
        }

        if ($clubId === '__other__') {
            if (filled($validated['club_other'] ?? null)) {
                $club = Club::firstOrCreate([
                    'name' => trim($validated['club_other']),
                ]);
            }
        } elseif (filled($clubId)) {
            $club = Club::find($clubId);
        }

        return [$league, $club];
    }

    protected function storeHeroUploads(Request $request): array
    {
        return [];
    }

    protected function createWebsiteIfSupported(User $user, array $validated, array $uploads, string $generatedDomain): ?Website
    {
        $siteTemplate = $this->resolveSiteTemplate($validated['sport']);
        $heroTemplate = $this->resolveHeroTemplate($validated['sport']);

        if (! $siteTemplate || ! $heroTemplate) {
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
                'project_json' => ! empty($uploads) ? json_encode(['hero_uploads' => $uploads]) : $existingWebsite->project_json,
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
            'project_json' => ! empty($uploads) ? json_encode(['hero_uploads' => $uploads]) : null,
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

    protected function resolveHeroTemplate(string $sport): ?HeroTemplate
    {
        $exactMatch = HeroTemplate::query()
            ->where('is_active', true)
            ->whereJsonContains('sports', $sport)
            ->orderBy('id')
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        return HeroTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('sports')
                    ->orWhereJsonLength('sports', 0);
            })
            ->orderBy('id')
            ->first();
    }

    protected function attachHeroFieldUploads(Website $website, array $uploads): void
    {
        if (empty($uploads) || ! $website->hero_template_id) {
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

            if (! $path) {
                continue;
            }

            $templateField = HeroTemplateField::query()
                ->where('hero_template_id', $website->hero_template_id)
                ->whereIn('name', $candidateNames)
                ->first();

            if (! $templateField) {
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

        if ($request->hasFile('player_image')) {
            $files = array_filter($request->file('player_image') ?? []);

            $paths['raw_player_images'] = collect($files)
                ->filter(fn ($file) => $file && $file->isValid())
                ->take(20)
                ->map(fn ($file) => $file->store('user-player-images', 'public'))
                ->values()
                ->all();
        }

        return $paths;
    }
}