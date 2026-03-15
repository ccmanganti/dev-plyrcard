<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\HeroTemplate;
use App\Models\League;
use App\Models\School;
use App\Models\SiteTemplate;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    public function create(): View
    {
        $schools = School::query()->orderBy('name')->get();
        $leagues = League::query()->orderBy('name')->get();
        $clubs = Club::query()->orderBy('name')->get();

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

        return view('public.player-intake', [
            'schools' => $schools,
            'leagues' => $leagues,
            'clubs' => $clubs,
            'states' => $states,
            'sportPositions' => $this->sportPositions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'personal_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],

            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],

            'gpa' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'string', 'max:50'],
            'birth' => ['nullable', 'date'],
            'height' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'string', 'max:50'],
            'jersey_number' => ['nullable', 'string', 'max:50'],

            'sport' => ['required', 'string', 'in:' . implode(',', array_keys($this->sportPositions))],
            'position' => ['nullable', 'array'],
            'position.*' => ['string', 'max:255'],

            'academic_accolades' => ['nullable', 'string'],
            'sports_accolades' => ['nullable', 'string'],
            'natl_team_exp' => ['nullable', 'in:0,1'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'ig_handle' => ['nullable', 'string', 'max:255'],
            'x_handle' => ['nullable', 'string', 'max:255'],
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

            'player_card_image' => ['nullable', 'image', 'max:5120'],
            'mobile_view_image' => ['nullable', 'image', 'max:5120'],
        ]);

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

        $user = DB::transaction(function () use ($request, $validated) {
            $school = $this->resolveSchool($validated);
            $league = $this->resolveLeague($validated);
            $club = $this->resolveClub($validated, $league?->id);

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
            $generatedDomain = $nameDomainStem . '.plyrcard.com';

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
            ]);

            $user->save();

            $uploads = $this->storeUploads($request, $user);

            $this->createWebsiteIfSupported($user, $validated, $uploads, $generatedDomain);

            return $user;
        });

        return redirect()
            ->route('public.player-intake.create')
            ->with('success', 'Player intake submitted successfully for ' . $user->first_name . '.');
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

    protected function resolveLeague(array $validated): ?League
    {
        if (($validated['league_id'] ?? null) === '__other__' && filled($validated['league_other'] ?? null)) {
            $attributes = ['name' => trim($validated['league_other'])];
            $values = [];

            if (Schema::hasColumn('leagues', 'gender')) {
                $values['gender'] = null;
            }

            return League::firstOrCreate($attributes, $values);
        }

        if (! empty($validated['league_id']) && $validated['league_id'] !== '__other__') {
            return League::find($validated['league_id']);
        }

        return null;
    }

    protected function resolveClub(array $validated, ?int $leagueId = null): ?Club
    {
        if (($validated['club_id'] ?? null) === '__other__' && filled($validated['club_other'] ?? null)) {
            if (! $leagueId) {
                return null;
            }

            return Club::firstOrCreate(
                [
                    'name' => trim($validated['club_other']),
                    'league_id' => $leagueId,
                ]
            );
        }

        if (! empty($validated['club_id']) && $validated['club_id'] !== '__other__') {
            return Club::find($validated['club_id']);
        }

        return null;
    }

    protected function storeUploads(Request $request, User $user): array
    {
        $fields = [
            'player_card_image',
            'mobile_view_image',
        ];

        $paths = [];

        foreach ($fields as $field) {
            if ($request->hasFile($field)) {
                $paths[$field] = $request->file($field)->store(
                    'player-intake/' . $user->id,
                    'public'
                );
            }
        }

        return $paths;
    }

    protected function createWebsiteIfSupported(User $user, array $validated, array $uploads, string $generatedDomain): ?Website
    {
        $siteTemplateId = $this->resolveSiteTemplateId($validated['sport']);
        $heroTemplateId = $this->resolveHeroTemplateId($validated['sport']);

        if (! $siteTemplateId || ! $heroTemplateId) {
            return null;
        }

        $siteTemplate = SiteTemplate::find($siteTemplateId);
        $heroTemplate = HeroTemplate::find($heroTemplateId);

        if (! $siteTemplate || ! $heroTemplate) {
            return null;
        }

        $websiteName = trim($user->first_name . ' ' . $user->last_name);
        $slugBase = Str::slug($websiteName ?: ('player-' . $user->id));
        $slug = $this->generateUniqueWebsiteSlug($slugBase);

        return Website::create([
            'user_id' => $user->id,
            'site_template_id' => $siteTemplate->id,
            'hero_template_id' => $heroTemplate->id,
            'name' => $websiteName,
            'slug' => $slug,
            'domain' => $generatedDomain,
            'is_active' => true,
            'is_published' => false,
            'project_json' => ! empty($uploads) ? json_encode(['uploads' => $uploads]) : null,
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
    }

    protected function resolveSiteTemplateId(string $sport): ?int
    {
        $map = [
            'basketball' => 1,
            'soccer' => 2,
        ];

        $templateId = $map[$sport] ?? null;

        if (! $templateId) {
            return null;
        }

        $template = SiteTemplate::find($templateId);

        if (! $template || ! $template->is_active) {
            return null;
        }

        if (method_exists($template, 'supportsSport') && ! $template->supportsSport($sport)) {
            return null;
        }

        return $templateId;
    }

    protected function resolveHeroTemplateId(string $sport): ?int
    {
        $map = [
            'basketball' => 1,
            'soccer' => 2,
        ];

        $templateId = $map[$sport] ?? null;

        if (! $templateId) {
            return null;
        }

        $template = HeroTemplate::find($templateId);

        if (! $template || ! $template->is_active) {
            return null;
        }

        if (! blank($template->sports) && ! in_array($sport, $template->sports, true)) {
            return null;
        }

        return $templateId;
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
}