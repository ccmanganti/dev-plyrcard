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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $leagues = League::query()->orderBy('name')->get();
        $clubs = Club::query()->with('league')->orderBy('name')->get();
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

        $resolvedPlan = $this->resolvePlanFromRequest($request);

        return view('public.player-intake', [
            'schools' => $schools,
            'nationalTeams' => $nationalTeams,
            'states' => $states,
            'countryOptions' => $countryOptions,
            'sportPositions' => $this->sportPositions,
            'genderOptions' => $this->genderOptions,
            'detectedCountry' => $detectedCountry,
            'packageLabel' => $resolvedPlan,
            'selectedPlan' => $resolvedPlan,
            'stepFieldMap' => $this->stepFieldMap(),

            'leagueDirectory' => $leagues->map(function (League $league) {
                $gender = filled($league->gender) ? strtolower((string) $league->gender) : null;
                $sport = filled($league->sport) ? strtolower((string) $league->sport) : null;

                return [
                    'id' => (string) $league->id,
                    'name' => $league->name,
                    'gender' => $gender,
                    'gender_label' => $this->genderOptions[$gender] ?? null,
                    'sport' => $sport,
                    'sport_label' => $sport ? Str::of($sport)->replace('_', ' ')->title()->toString() : null,
                ];
            })->values(),

            'clubDirectory' => $clubs->map(function (Club $club) {
                $gender = filled($club->league?->gender) ? strtolower((string) $club->league->gender) : null;
                $sport = filled($club->league?->sport) ? strtolower((string) $club->league->sport) : null;

                return [
                    'id' => (string) $club->id,
                    'name' => $club->name,
                    'league_id' => (string) $club->league_id,
                    'league_name' => $club->league?->name,
                    'logo_url' => filled($club->logo) ? Storage::disk('public')->url($club->logo) : null,
                    'sport' => $sport,
                    'gender' => $gender,
                    'gender_label' => $this->genderOptions[$gender] ?? null,
                    'sport_label' => $sport ? Str::of($sport)->replace('_', ' ')->title()->toString() : null,
                ];
            })->values(),

            'teamDirectory' => $teams->map(function (Team $team) {
                $gender = filled($team->club?->league?->gender) ? strtolower((string) $team->club->league->gender) : null;
                $sport = filled($team->club?->league?->sport) ? strtolower((string) $team->club->league->sport) : null;

                return [
                    'id' => (string) $team->id,
                    'name' => $team->name,
                    'club_id' => (string) $team->club_id,
                    'league_id' => (string) ($team->club?->league_id),
                    'league_name' => $team->club?->league?->name,
                    'club_name' => $team->club?->name,
                    'club_logo_url' => filled($team->club?->logo) ? Storage::disk('public')->url($team->club->logo) : null,
                    'sport' => $sport,
                    'gender' => $gender,
                    'gender_label' => $this->genderOptions[$gender] ?? null,
                    'sport_label' => $sport ? Str::of($sport)->replace('_', ' ')->title()->toString() : null,
                ];
            })->values(),
        ]);
    }

    protected function resolvePlanFromRequest(Request $request): string
    {
        $rawPlan = trim((string) (
            $request->input('selected_plan')
            ?? $request->query('utm_plan')
            ?? $request->query('plan')
            ?? $request->query('package')
            ?? $request->query('package_name')
            ?? ''
        ));

        $normalized = Str::lower(str_replace(['-', '_'], ' ', $rawPlan));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return match ($normalized) {
            'my journey', 'myjourney' => 'My Journey',
            'plyr' => 'Plyr',
            'free' => 'Free',
            default => 'Free',
        };
    }

    protected function applyUserPlanRole(User $user, string $plan): void
    {
        $plan = in_array($plan, ['Free', 'Plyr', 'My Journey'], true) ? $plan : 'Free';

        if (method_exists($user, 'syncRoles')) {
            $user->syncRoles([$plan]);
            return;
        }

        if (method_exists($user, 'assignRole')) {
            if (method_exists($user, 'getRoleNames') && ! $user->getRoleNames()->contains($plan)) {
                if (method_exists($user, 'syncRoles')) {
                    $user->syncRoles([$plan]);
                } else {
                    $user->assignRole($plan);
                }
            }
            return;
        }

        if (\Schema::hasColumn($user->getTable(), 'role')) {
            $user->role = $plan;
            $user->save();
        }
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
            // Silent fail.
        }

        return '';
    }

    protected function normalizePhone(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        return $hasPlus ? '+' . $digits : $digits;
    }

    public function store(Request $request): RedirectResponse
{
    $request->merge([
        'selected_plan' => $this->resolvePlanFromRequest($request),
        'phone' => $this->normalizePhone($request->input('phone')),
        'parent_phone' => $this->normalizePhone($request->input('parent_phone')),
        'sec_parent_phone' => $this->normalizePhone($request->input('sec_parent_phone')),
        'club_coach_phone' => $this->normalizePhone($request->input('club_coach_phone')),
        'natl_coach_phone' => $this->normalizePhone($request->input('natl_coach_phone')),
        'tech_trainer_phone' => $this->normalizePhone($request->input('tech_trainer_phone')),
        'snc_trainer_phone' => $this->normalizePhone($request->input('snc_trainer_phone')),
    ]);

    $validated = $request->validate([
        'selected_plan' => ['nullable', 'in:Free,Plyr,My Journey'],
        'first_name' => ['required', 'string', 'max:255'],
        'middle_name' => ['nullable', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'personal_email' => ['required', 'email', 'max:255'],
        'phone' => [
            'nullable',
            'string',
            'max:50',
            Rule::unique('users', 'phone'),
        ],
        'gender' => ['required', 'in:' . implode(',', array_keys($this->genderOptions))],
        'sport' => ['required', 'string', 'in:' . implode(',', array_keys($this->sportPositions))],
        'position' => ['nullable', 'array'],
        'position.*' => ['string', 'max:255'],

        'birth' => ['nullable', 'date'],
        'year' => ['nullable', 'string', 'max:50'],
        'gpa' => ['nullable', 'string', 'max:50'],
        'height' => ['nullable', 'string', 'max:50'],
        'weight' => ['nullable', 'string', 'max:50'],
        'jersey_number' => ['nullable', 'string', 'max:50'],
        'vertical_jump' => ['nullable', 'string', 'max:50'],
        'max_speed' => ['nullable', 'string', 'max:50'],
        'dominant_foot' => ['nullable', 'in:left,right,both'],

        'country' => ['nullable', 'string', 'max:255'],
        'country_other' => ['nullable', 'string', 'max:255'],
        'state' => ['nullable', 'string', 'max:255'],
        'city' => ['nullable', 'string', 'max:255'],
        'street' => ['nullable', 'string', 'max:255'],

        'school_id' => ['nullable', 'string'],
        'school_other' => ['nullable', 'string', 'max:255'],

        'league_id' => ['nullable', 'string'],
        'club_id' => ['nullable', 'string'],
        'team_id' => ['nullable', 'string'],
        'league_other' => ['nullable', 'string', 'max:255'],
        'club_other' => ['nullable', 'string', 'max:255'],
        'team_other' => ['nullable', 'string', 'max:255'],

        'natl_team_exp' => ['nullable', 'in:0,1'],
        'national_team_period' => ['nullable', 'string', 'max:255'],
        'national_team_id' => ['nullable', 'string'],
        'national_team_other' => ['nullable', 'string', 'max:255'],

        'ig_handle' => ['nullable', 'url', 'max:255'],
        'x_handle' => ['nullable', 'url', 'max:255'],
        'yt_url' => ['nullable', 'url', 'max:500'],
        'featured_video_url' => ['nullable', 'url', 'max:500'],
        'use_custom_highlights' => ['nullable', 'boolean'],
        'featured_video_urls' => ['nullable', 'string'],
        'player_bio' => ['nullable', 'string'],
        'academic_accolades' => ['nullable', 'string'],
        'sports_accolades' => ['nullable', 'string'],
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

        'action_images' => ['nullable', 'array'],
        'action_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        'portrait_images' => ['nullable', 'array'],
        'portrait_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        'national_team_images' => ['nullable', 'array'],
        'national_team_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        'team_images' => ['nullable', 'array'],
        'team_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
    ], [
        'phone.unique' => 'This phone number is already being used by another account.',
    ]);

    $selectedPlan = $validated['selected_plan'] ?? 'Free';

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

    if (($validated['country'] ?? null) === '__other__') {
        if (blank($validated['country_other'] ?? null)) {
            return back()->withErrors(['country_other' => 'Please enter a country name.'])->withInput();
        }

        $validated['country'] = trim((string) $validated['country_other']);
    }

    if (($validated['country'] ?? null) === 'USA' && filled($validated['state'] ?? null)) {
        $validated['state'] = strtoupper(trim((string) $validated['state']));
    }

    $sport = strtolower((string) ($validated['sport'] ?? ''));
    $selectedGender = strtolower((string) ($validated['gender'] ?? ''));

    $allowedPositions = array_keys($this->sportPositions[$sport] ?? []);
    $submittedPositions = $validated['position'] ?? [];

    foreach ($submittedPositions as $position) {
        if (! in_array($position, $allowedPositions, true)) {
            return back()
                ->withErrors(['position' => 'One or more selected positions do not match the chosen sport.'])
                ->withInput();
        }
    }

    if (empty($submittedPositions)) {
        return back()
            ->withErrors(['position' => 'Please select at least one position.'])
            ->withInput();
    }

    if ($sport !== 'soccer') {
        $validated['dominant_foot'] = null;
    }

    $selectedLeagueId = $validated['league_id'] ?? null;
    $selectedClubId = $validated['club_id'] ?? null;
    $selectedTeamId = $validated['team_id'] ?? null;

    if ($selectedLeagueId === '__other__') {
        if (blank($validated['league_other'] ?? null)) {
            return back()->withErrors(['league_other' => 'Please enter the new league name.'])->withInput();
        }

        if (blank($validated['club_other'] ?? null)) {
            return back()->withErrors(['club_other' => 'Please enter the new club name.'])->withInput();
        }

        if (blank($validated['team_other'] ?? null)) {
            return back()->withErrors(['team_other' => 'Please enter the new team name.'])->withInput();
        }
    } else {
        $league = null;
        $club = null;
        $team = null;

        if (filled($selectedLeagueId)) {
            $league = League::query()->find($selectedLeagueId);

            if (! $league) {
                return back()->withErrors(['league_id' => 'The selected league is invalid.'])->withInput();
            }

            if (! $this->isLeagueGenderCompatible($league->gender, $selectedGender)) {
                return back()->withErrors(['league_id' => 'The selected league does not match the athlete gender.'])->withInput();
            }

            if (! $this->isLeagueSportCompatible($league->sport, $sport)) {
                return back()->withErrors(['league_id' => 'The selected league does not match the athlete sport.'])->withInput();
            }
        }

        if (filled($selectedClubId)) {
            if (! $league) {
                return back()->withErrors(['club_id' => 'Please select a valid league first.'])->withInput();
            }

            $club = Club::query()
                ->with('league')
                ->where('id', $selectedClubId)
                ->where('league_id', $league->id)
                ->first();

            if (! $club) {
                return back()->withErrors(['club_id' => 'The selected club does not belong to the selected league.'])->withInput();
            }

            if (! $this->isLeagueGenderCompatible($club->league?->gender, $selectedGender)) {
                return back()->withErrors(['club_id' => 'The selected club does not match the athlete gender.'])->withInput();
            }

            if (! $this->isLeagueSportCompatible($club->league?->sport, $sport)) {
                return back()->withErrors(['club_id' => 'The selected club does not match the athlete sport.'])->withInput();
            }
        }

        if (filled($selectedTeamId)) {
            if (! $club) {
                return back()->withErrors(['team_id' => 'Please select a valid club first.'])->withInput();
            }

            $team = Team::query()
                ->with('club.league')
                ->where('id', $selectedTeamId)
                ->where('club_id', $club->id)
                ->first();

            if (! $team) {
                return back()->withErrors(['team_id' => 'The selected team does not belong to the selected club.'])->withInput();
            }

            if (! $this->isLeagueGenderCompatible($team->club?->league?->gender, $selectedGender)) {
                return back()->withErrors(['team_id' => 'The selected team does not match the athlete gender.'])->withInput();
            }

            if (! $this->isLeagueSportCompatible($team->club?->league?->sport, $sport)) {
                return back()->withErrors(['team_id' => 'The selected team does not match the athlete sport.'])->withInput();
            }
        }
    }

    $useCustomHighlights = $request->boolean('use_custom_highlights');
    $manualVideoUrls = $this->normalizeVideoUrls($validated['featured_video_urls'] ?? null);

    if ($useCustomHighlights && empty($manualVideoUrls)) {
        throw ValidationException::withMessages([
            'featured_video_urls' => 'Please add at least one highlight video URL or turn off "Pick My Own Videos".',
        ]);
    }

    $useCustomHighlights = $request->boolean('use_custom_highlights');
    $manualVideoUrls = $this->normalizeVideoUrls($validated['featured_video_urls'] ?? null);
    $ghlResult = null;

    if ($useCustomHighlights && empty($manualVideoUrls)) {
        throw ValidationException::withMessages([
            'featured_video_urls' => 'Please add at least one highlight video URL or turn off "Pick My Own Videos".',
        ]);
    }

    try {
        $user = DB::transaction(function () use (
            $request,
            $validated,
            $useCustomHighlights,
            $manualVideoUrls,
            $sport,
            $selectedPlan,
            &$ghlResult
        ) {
            $school = $this->resolveSchool($validated);
            [$league, $club, $team] = $this->resolveLeagueClubAndTeam($validated);
            $nationalTeam = $this->resolveNationalTeam($validated);

            $user = User::withTrashed()
                ->where('personal_email', $validated['personal_email'])
                ->first();

            if (! $user) {
                $user = new User();
                $user->password = Hash::make('WelcomePLYR');
            }

            if (method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }

            $hasNationalTeamExperience = isset($validated['natl_team_exp']) ? (bool) $validated['natl_team_exp'] : false;

            $user->fill([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['personal_email'],
                'personal_email' => $validated['personal_email'],
                'phone' => $validated['phone'] ?? null,

                'gender' => $validated['gender'],
                'sport' => $validated['sport'],
                'position' => $validated['position'] ?? [],

                'birth' => $validated['birth'] ?? null,
                'year' => $validated['year'] ?? null,
                'gpa' => $validated['gpa'] ?? null,
                'height' => $validated['height'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'jersey_number' => $validated['jersey_number'] ?? null,
                'vertical_jump' => $validated['vertical_jump'] ?? null,
                'max_speed' => $validated['max_speed'] ?? null,
                'dominant_foot' => $sport === 'soccer' ? ($validated['dominant_foot'] ?? null) : null,

                'country' => $validated['country'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'street' => $validated['street'] ?? null,

                'natl_team_exp' => $hasNationalTeamExperience,
                'national_team_period' => $hasNationalTeamExperience ? ($validated['national_team_period'] ?? null) : null,

                'school_id' => $school?->id,
                'league_id' => $league?->id,
                'club_id' => $club?->id,
                'team_name' => $team?->name,
                'national_team_id' => $hasNationalTeamExperience ? $nationalTeam?->id : null,

                'ig_handle' => $validated['ig_handle'] ?? null,
                'x_handle' => $validated['x_handle'] ?? null,
                'yt_url' => $validated['yt_url'] ?? null,
                'featured_video_url' => $validated['featured_video_url'] ?? null,
                'featured_video_urls' => $useCustomHighlights ? implode("\n", $manualVideoUrls) : null,

                'player_bio' => $validated['player_bio'] ?? null,
                'academic_accolades' => $validated['academic_accolades'] ?? null,
                'sports_accolades' => $validated['sports_accolades'] ?? null,
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

                'domain' => null,
            ]);

            $userImageUploads = $this->storeUserImageUploads($request);

            if (! empty($userImageUploads['raw_player_images'])) {
                $user->raw_player_images = $userImageUploads['raw_player_images'];
            }

            $user->save();

            $this->applyUserPlanRole($user, $selectedPlan);

            $ghlResult = $this->upsertGhlContact(
                $user,
                $validated,
                $selectedPlan,
                $league ?? null,
                $club ?? null,
                $team ?? null,
                $nationalTeam ?? null
            );

            $uploads = $this->storeHeroUploads($request);

            $this->createWebsiteIfSupported($user, $validated, $uploads);

            return $user;
        });
    } catch (\Illuminate\Database\QueryException $e) {
        if (str_contains(strtolower($e->getMessage()), 'users.phone')) {
            return back()
                ->withErrors(['phone' => 'This phone number is already being used by another account.'])
                ->withInput();
        }

        report($e);

        return back()
            ->withErrors(['form' => 'We could not submit the intake form right now. Please review the form and try again.'])
            ->withInput();
    }

    return redirect()
        ->route('public.player-intake.create', ['utm_plan' => $selectedPlan])
        ->with('success', 'Player intake submitted successfully for ' . $user->first_name . '.')
        ->with('ghl_result', $ghlResult);
}

    protected function stepFieldMap(): array
    {
        return [
            1 => [
                'first_name', 'middle_name', 'last_name', 'personal_email', 'phone', 'gender',
                'sport', 'position', 'position.*', 'birth', 'year', 'gpa', 'height', 'weight',
                'jersey_number', 'vertical_jump', 'max_speed', 'dominant_foot',
                'country', 'country_other', 'state', 'city', 'street',
                'natl_team_exp', 'national_team_period',
            ],
            2 => [
                'school_id', 'school_other',
                'league_id', 'club_id', 'team_id',
                'league_other', 'club_other', 'team_other',
                'national_team_id', 'national_team_other',
            ],
            3 => [
                'ig_handle', 'x_handle', 'yt_url',
                'featured_video_url', 'use_custom_highlights', 'featured_video_urls',
                'player_bio', 'academic_accolades', 'sports_accolades', 'press',
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
                ['name' => trim((string) $validated['school_other'])],
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

    protected function resolveLeagueClubAndTeam(array $validated): array
    {
        $league = null;
        $club = null;
        $team = null;

        $leagueId = $validated['league_id'] ?? null;
        $clubId = $validated['club_id'] ?? null;
        $teamId = $validated['team_id'] ?? null;
        $gender = strtolower((string) ($validated['gender'] ?? ''));
        $sport = strtolower((string) ($validated['sport'] ?? ''));

        if ($leagueId === '__other__') {
            $league = League::firstOrCreate([
                'name' => trim((string) $validated['league_other']),
                'gender' => $gender,
                'sport' => $sport,
            ]);

            $club = Club::firstOrCreate([
                'name' => trim((string) $validated['club_other']),
                'league_id' => $league->id,
            ]);

            $team = Team::firstOrCreate([
                'name' => trim((string) $validated['team_other']),
                'club_id' => $club->id,
            ]);

            return [$league, $club, $team];
        }

        if (filled($leagueId)) {
            $league = League::find($leagueId);
        }

        if (filled($clubId)) {
            $club = Club::query()
                ->with('league')
                ->when($league, fn ($query) => $query->where('league_id', $league->id))
                ->find($clubId);
        }

        if (filled($teamId)) {
            $team = Team::query()
                ->with('club.league')
                ->when($club, fn ($query) => $query->where('club_id', $club->id))
                ->find($teamId);

            if (! $club) {
                $club = $team?->club;
            }

            if (! $league) {
                $league = $club?->league;
            }
        }

        return [$league, $club, $team];
    }

    protected function resolveNationalTeam(array $validated): ?NationalTeam
    {
        $nationalTeamId = $validated['national_team_id'] ?? null;

        if ($nationalTeamId === '__other__') {
            if (filled($validated['national_team_other'] ?? null)) {
                return NationalTeam::firstOrCreate([
                    'name' => trim((string) $validated['national_team_other']),
                ]);
            }

            return null;
        }

        if (filled($nationalTeamId)) {
            return NationalTeam::find($nationalTeamId);
        }

        return null;
    }

    protected function isLeagueGenderCompatible(?string $leagueGender, ?string $userGender): bool
    {
        $leagueGender = filled($leagueGender) ? strtolower(trim((string) $leagueGender)) : null;
        $userGender = filled($userGender) ? strtolower(trim((string) $userGender)) : null;

        if (! $leagueGender || ! $userGender) {
            return true;
        }

        if ($userGender === 'coed') {
            return $leagueGender === 'coed';
        }

        return in_array($leagueGender, [$userGender, 'coed'], true);
    }

    protected function isLeagueSportCompatible(?string $leagueSport, ?string $userSport): bool
    {
        $leagueSport = filled($leagueSport) ? strtolower(trim((string) $leagueSport)) : null;
        $userSport = filled($userSport) ? strtolower(trim((string) $userSport)) : null;

        if (! $leagueSport || ! $userSport) {
            return true;
        }

        return $leagueSport === $userSport;
    }

    protected function storeHeroUploads(Request $request): array
    {
        return [];
    }

    protected function createWebsiteIfSupported(User $user, array $validated, array $uploads): ?Website
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
                'domain' => null,
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
            'domain' => null,
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

    protected function syncGhlContactAndPlan(
        User $user,
        array $validated,
        string $selectedPlan = 'Free',
        ?League $league = null,
        ?Club $club = null,
        ?Team $team = null,
        ?NationalTeam $nationalTeam = null
    ): array
    {
        $token = config('services.ghl.token');
        $locationId = config('services.ghl.location_id');

        if (blank($token) || blank($locationId)) {
            return [
                'success' => false,
                'status' => null,
                'message' => 'GHL skipped: missing token or location ID.',
                'contact_id' => null,
                'subscription_found' => false,
                'plan_applied' => null,
                'response' => null,
            ];
        }

        $firstName = trim((string) ($validated['first_name'] ?? $user->first_name ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? $user->last_name ?? ''));
        $email = trim((string) ($validated['personal_email'] ?? $user->personal_email ?? $user->email ?? ''));

        if ($email === '') {
            return [
                'success' => false,
                'status' => null,
                'message' => 'GHL skipped: missing email.',
                'contact_id' => null,
                'subscription_found' => false,
                'plan_applied' => null,
                'response' => null,
            ];
        }

        $sport = strtolower((string) ($validated['sport'] ?? $user->sport ?? ''));

        $positions = collect($validated['position'] ?? [])
            ->map(fn ($position) => $this->sportPositions[$sport][$position] ?? $position)
            ->filter()
            ->values()
            ->all();

        $customFields = array_values(array_filter([
            [
                'key' => 'selected_plan',
                'field_value' => $selectedPlan,
            ],
            [
                'key' => 'sport',
                'field_value' => $validated['sport'] ?? $user->sport ?? null,
            ],
            [
                'key' => 'gender',
                'field_value' => $validated['gender'] ?? $user->gender ?? null,
            ],
            [
                'key' => 'positions',
                'field_value' => ! empty($positions) ? implode(', ', $positions) : null,
            ],
            [
                'key' => 'league',
                'field_value' => $league?->name,
            ],
            [
                'key' => 'club',
                'field_value' => $club?->name,
            ],
            [
                'key' => 'team',
                'field_value' => $team?->name,
            ],
            [
                'key' => 'national_team',
                'field_value' => $nationalTeam?->name,
            ],
        ], fn ($field) => filled($field['field_value'] ?? null)));

        $payload = [
            'locationId' => $locationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'name' => trim($firstName . ' ' . $lastName),
            'email' => $email,
            'phone' => $validated['phone'] ?? $user->phone ?? null,
            'address1' => $validated['street'] ?? $user->street ?? null,
            'city' => $validated['city'] ?? $user->city ?? null,
            'state' => $validated['state'] ?? $user->state ?? null,
            'country' => ($validated['country'] ?? null) === 'USA'
                ? 'US'
                : ($validated['country'] ?? $user->country ?? null),
            'tags' => array_values(array_filter([
                'player-intake',
                filled($sport) ? 'sport-' . \Illuminate\Support\Str::slug($sport) : null,
            ])),
            'customFields' => $customFields,
        ];

        try {
            $client = \Illuminate\Support\Facades\Http::withToken($token)
                ->acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    'Version' => '2021-07-28',
                ]);

            // 1) Upsert or update contact
            $upsertResponse = $client->post(
                'https://services.leadconnectorhq.com/contacts/upsert',
                $payload
            );

            $upsertBody = $upsertResponse->json();
            if (! is_array($upsertBody)) {
                $upsertBody = ['raw' => $upsertResponse->body()];
            }

            if ($upsertResponse->failed()) {
                return [
                    'success' => false,
                    'status' => $upsertResponse->status(),
                    'message' => 'GHL contact upsert failed.',
                    'contact_id' => null,
                    'subscription_found' => false,
                    'plan_applied' => null,
                    'response' => $upsertBody,
                ];
            }

            // 2) Try to get contact ID from upsert response
            $contactId = data_get($upsertBody, 'contact.id')
                ?? data_get($upsertBody, 'contactId')
                ?? data_get($upsertBody, 'id');

            // 3) Fallback: search duplicate by email if contact ID was not returned
            if (blank($contactId)) {
                $duplicateResponse = $client->get(
                    'https://services.leadconnectorhq.com/contacts/search/duplicate',
                    [
                        'locationId' => $locationId,
                        'email' => $email,
                    ]
                );

                $duplicateBody = $duplicateResponse->json();
                if (! is_array($duplicateBody)) {
                    $duplicateBody = ['raw' => $duplicateResponse->body()];
                }

                if ($duplicateResponse->successful()) {
                    $contactId = data_get($duplicateBody, 'contact.id')
                        ?? data_get($duplicateBody, 'contactId')
                        ?? data_get($duplicateBody, 'id');
                }
            }

            if (blank($contactId)) {
                return [
                    'success' => true,
                    'status' => $upsertResponse->status(),
                    'message' => 'GHL contact upsert succeeded, but contact ID could not be resolved.',
                    'contact_id' => null,
                    'subscription_found' => false,
                    'plan_applied' => null,
                    'response' => $upsertBody,
                ];
            }

            // 4) Check if the contact already has any subscription
            $subscriptionResponse = $client->get(
                'https://services.leadconnectorhq.com/payments/subscriptions',
                [
                    'locationId' => $locationId,
                    'contact' => $contactId,
                    'limit' => 20,
                ]
            );

            $subscriptionBody = $subscriptionResponse->json();
            if (! is_array($subscriptionBody)) {
                $subscriptionBody = ['raw' => $subscriptionResponse->body()];
            }

            $subscriptions = data_get($subscriptionBody, 'data')
                ?? data_get($subscriptionBody, 'subscriptions')
                ?? [];

            $hasSubscription = is_array($subscriptions) && count($subscriptions) > 0;

            if ($hasSubscription) {
                return [
                    'success' => true,
                    'status' => $subscriptionResponse->status(),
                    'message' => 'GHL contact synced. Existing subscription found; no free plan fallback applied.',
                    'contact_id' => $contactId,
                    'subscription_found' => true,
                    'plan_applied' => null,
                    'response' => [
                        'upsert' => $upsertBody,
                        'subscriptions' => $subscriptionBody,
                    ],
                ];
            }

            // 5) No subscription found: mark as Free and let GHL workflows handle the rest
            $fallbackPayload = [
                'locationId' => $locationId,
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'name' => trim($firstName . ' ' . $lastName),
                'tags' => array_values(array_filter([
                    'player-intake',
                    'free',
                    'plan-free',
                ])),
                'customFields' => [
                    [
                        'key' => 'selected_plan',
                        'field_value' => 'Free',
                    ],
                ],
            ];

            $fallbackResponse = $client->post(
                'https://services.leadconnectorhq.com/contacts/upsert',
                $fallbackPayload
            );

            $fallbackBody = $fallbackResponse->json();
            if (! is_array($fallbackBody)) {
                $fallbackBody = ['raw' => $fallbackResponse->body()];
            }

            return [
                'success' => $fallbackResponse->successful(),
                'status' => $fallbackResponse->status(),
                'message' => $fallbackResponse->successful()
                    ? 'GHL contact synced. No subscription found, so Free plan fallback was applied.'
                    : 'GHL contact synced, but Free plan fallback failed.',
                'contact_id' => $contactId,
                'subscription_found' => false,
                'plan_applied' => 'Free',
                'response' => [
                    'upsert' => $upsertBody,
                    'subscriptions' => $subscriptionBody,
                    'fallback' => $fallbackBody,
                ],
            ];
        } catch (\Throwable $e) {
            \Log::error('GHL sync exception.', [
                'user_id' => $user->id,
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => null,
                'message' => 'GHL sync exception: ' . $e->getMessage(),
                'contact_id' => null,
                'subscription_found' => false,
                'plan_applied' => null,
                'response' => null,
            ];
        }
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

        if (! empty($storedFiles)) {
            $paths['raw_player_images'] = $storedFiles;
        }

        return $paths;
    }

}
