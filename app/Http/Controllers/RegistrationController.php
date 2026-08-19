<?php

namespace App\Http\Controllers;

use App\Models\BillingInformation;
use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\League;
use App\Models\School;
use App\Models\User;
use App\Models\Website;
use App\Services\GoHighLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationController extends PublicPlayerIntakeController
{
    public function show(Request $request): View
    {
        $planKey = $this->normalizePlanKey(
            $request->query('utm_plan', $request->query('plan', 'free'))
        );

        $leagues = $this->canonicalLeagueQuery()->orderBy('name')->get();
        $programs = $this->activeClubLeagueQuery()->with(['club', 'league'])->get();

        return view('pages.registration', [
            'planKey' => $planKey,
            'plan' => $this->planConfig($planKey),
            'isPaidPlan' => $planKey !== 'free',
            'sportPositions' => $this->sportPositions,
            'states' => $this->states(),
            'ageGroups' => $this->getAgeGroupOptions(),
            'leagueDirectory' => $leagues->map(function (League $league) {
                $genders = collect($league->genders ?: [$league->gender])
                    ->map(fn ($value) => $this->normalizeGender($value))
                    ->filter()->unique()->values()->all();
                return [
                    'id' => (string) $league->id,
                    'name' => $league->name,
                    'genders' => $genders,
                    'sport' => filled($league->sport) ? strtolower((string) $league->sport) : null,
                ];
            })->values(),
            'clubDirectory' => $programs->map(function (ClubLeague $program) {
                $genders = collect($program->genders ?: $program->league?->genders ?: [$program->league?->gender])
                    ->map(fn ($value) => $this->normalizeGender($value))
                    ->filter()->unique()->values()->all();
                return [
                    'id' => (string) $program->club_id,
                    'name' => $program->club?->name,
                    'league_id' => (string) $program->league_id,
                    'club_league_id' => (string) $program->id,
                    'genders' => $genders,
                    'sport' => filled($program->sport) ? strtolower((string) $program->sport) : (filled($program->league?->sport) ? strtolower((string) $program->league->sport) : null),
                ];
            })->filter(fn ($row) => filled($row['id']) && filled($row['name']))->values(),
            'trackingParams' => array_filter($request->only([
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
                'utm_club_id', 'utm_league_id', 'utm_team_name',
            ]), fn ($value) => filled($value)),
        ]);
    }

    public function checkHandle(Request $request): JsonResponse
    {
        $handle = $this->normalizeHandle((string) $request->query('handle', ''));
        $reserved = $this->reservedHandles();

        if (strlen($handle) < 3) {
            return response()->json([
                'available' => false,
                'handle' => $handle,
                'message' => 'Use at least 3 letters or numbers.',
            ]);
        }

        if (in_array($handle, $reserved, true)) {
            return response()->json([
                'available' => false,
                'handle' => $handle,
                'message' => 'That PLYRSITE link is reserved.',
            ]);
        }

        $exists = Website::query()->whereRaw('LOWER(slug) = ?', [strtolower($handle)])->exists()
            || BillingInformation::query()
                ->whereRaw('LOWER(requested_handle) = ?', [strtolower($handle)])
                ->whereIn('payment_status', ['pending', 'payment_form_ready', 'paid', 'not_required'])
                ->exists();

        return response()->json([
            'available' => ! $exists,
            'handle' => $handle,
            'url' => url('/' . $handle),
            'message' => $exists
                ? 'That PLYRSITE link is already taken.'
                : 'This PLYRSITE link is available.',
        ]);
    }

    public function checkDomain(Request $request): JsonResponse
    {
        $domain = $this->normalizeDomain((string) $request->query('domain', ''));

        if (! $this->domainLooksValid($domain)) {
            return response()->json([
                'available' => false,
                'status' => 'invalid',
                'domain' => $domain,
                'message' => 'Enter a valid domain such as firstnamelastname.com.',
                'rdap_verified' => false,
                'registrar_verified' => false,
            ]);
        }

        $claimedLocally = Website::query()
            ->whereNotNull('domain')
            ->whereRaw('LOWER(domain) = ?', [strtolower($domain)])
            ->exists();

        $requestedLocally = BillingInformation::query()
            ->whereRaw('LOWER(requested_domain) = ?', [strtolower($domain)])
            ->whereIn('payment_status', ['pending', 'payment_form_ready', 'paid'])
            ->exists();

        if ($claimedLocally || $requestedLocally) {
            return response()->json([
                'available' => false,
                'status' => 'reserved_locally',
                'domain' => $domain,
                'message' => 'That domain is already attached to or requested by another PLYRCARD account.',
                'rdap_verified' => false,
                'registrar_verified' => false,
            ]);
        }

        /** @var \App\Services\DomainAvailabilityService $domainAvailability */
        $domainAvailability = app(\App\Services\DomainAvailabilityService::class);
        $lookup = $domainAvailability->lookup($domain);

        return response()->json([
            'available' => (bool) ($lookup['available'] ?? false),
            'status' => $lookup['status'] ?? 'unknown',
            'domain' => $domain,
            'message' => match ($lookup['status'] ?? 'unknown') {
                'available' => 'This domain appears available.',
                'registered' => 'That domain is already registered.',
                default => $lookup['message'] ?? 'We could not verify this domain right now. Please try again.',
            },
            'rdap_verified' => (bool) ($lookup['verified'] ?? false),
            // RDAP verifies whether registration data exists; actual purchasability
            // is still confirmed by the registrar during provisioning.
            'registrar_verified' => false,
            'lookup_source' => 'rdap',
        ]);
    }

    public function store(Request $request)
    {
        // Keep this signature compatible with PublicPlayerIntakeController::store(Request $request).
        // Resolve the registration-only services through Laravel's container instead of
        // adding parameters to the overridden parent method.
        $ghl = app(GoHighLevelService::class);
        $planKey = $this->normalizePlanKey(
            $request->query('utm_plan', $request->input('plan_key', 'free'))
        );
        $plan = $this->planConfig($planKey);
        $isPaid = $planKey !== 'free';

        $request->merge([
            'plan_key' => $planKey,
            'phone' => $this->normalizePhone($request->input('phone')),
            'billing_phone' => $this->normalizePhone($request->input('billing_phone')),
            'requested_handle' => $this->normalizeHandle((string) $request->input('requested_handle')),
            'requested_domain' => $this->normalizeDomain((string) $request->input('requested_domain')),
        ]);

        $rules = [
            'plan_key' => ['required', Rule::in(['free', 'my-journey', 'amplify'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('users', 'personal_email'),
            ],
            'phone' => [
                $isPaid ? 'required' : 'nullable',
                'string',
                'max:50',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'is_minor' => ['nullable', 'boolean'],
            'guardian_name' => [Rule::requiredIf($request->boolean('is_minor')), 'nullable', 'string', 'max:255'],
            'guardian_email' => [Rule::requiredIf($request->boolean('is_minor')), 'nullable', 'email:rfc', 'max:255'],

            'sport' => ['required', Rule::in(array_keys($this->sportPositions))],
            'gender' => ['required', Rule::in(['girls', 'boys', 'female', 'male'])],
            'year' => ['required', 'integer', 'min:' . now()->year, 'max:' . (now()->year + 8)],
            'gpa' => ['nullable', 'string', 'max:50'],
            'position' => ['required', 'array', 'min:1', 'max:3'],
            'position.*' => ['required', 'string', 'max:100'],
            'high_school' => ['nullable', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:100'],
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
            'club_id' => ['required', 'string', 'max:50'],
            'club_other' => ['nullable', 'string', 'max:255'],
            'team_name' => ['required', Rule::in($this->getAgeGroupOptions())],
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:99'],
            'club_coach' => ['nullable', 'string', 'max:255'],
            'club_coach_email' => ['nullable', 'email:rfc', 'max:255'],

            'terms' => ['accepted'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_club_id' => ['nullable', 'integer'],
            'utm_league_id' => ['nullable', 'integer'],
            'utm_team_name' => ['nullable', 'string', 'max:255'],
        ];

        if ($isPaid) {
            $rules = array_merge($rules, [
                'requested_domain' => ['required', 'string', 'max:255'],

                'billing_name' => ['required', 'string', 'max:255'],
                'billing_email' => ['required', 'email:rfc', 'max:255'],
                'billing_phone' => ['required', 'string', 'max:50'],
                'billing_address_1' => ['required', 'string', 'max:255'],
                'billing_city' => ['required', 'string', 'max:255'],
                'billing_state' => ['required', 'string', 'max:255'],
                'billing_postal_code' => ['required', 'string', 'max:30'],
                'billing_country' => ['required', 'string', 'max:100'],
            ]);
        }

        $validated = $request->validate($rules, [
            'password.regex' => 'Password must include at least one capital letter and one number.',
        ]);

        $gender = $this->normalizeRegistrationGender($validated['gender']);
        $sport = $validated['sport'];
        $positions = array_values(array_unique($validated['position']));
        $allowedPositions = array_keys($this->sportPositions[$sport] ?? []);

        foreach ($positions as $position) {
            if (! in_array($position, $allowedPositions, true)) {
                throw ValidationException::withMessages([
                    'position' => 'One or more positions do not match the selected sport.',
                ]);
            }
        }

        $domainLookup = null;

        if ($isPaid) {
            if (! $this->domainLooksValid($validated['requested_domain'])) {
                throw ValidationException::withMessages([
                    'requested_domain' => 'Please enter a valid domain name.',
                ]);
            }

            $domainConflict = Website::query()
                ->whereNotNull('domain')
                ->whereRaw('LOWER(domain) = ?', [strtolower($validated['requested_domain'])])
                ->exists()
                || BillingInformation::query()
                    ->whereRaw('LOWER(requested_domain) = ?', [strtolower($validated['requested_domain'])])
                    ->whereIn('payment_status', ['pending', 'payment_form_ready', 'paid'])
                    ->exists();

            if ($domainConflict) {
                throw ValidationException::withMessages([
                    'requested_domain' => 'That domain is already attached to or requested by another PLYRCARD account.',
                ]);
            }

            // The athlete already selected an available domain in Step 1.
            // Do not perform a second external lookup here because a transient
            // registry/network response must not invalidate a previously selected name.
            // The final submit only protects against a local PLYRCARD collision.
            $domainLookup = [
                'available' => true,
                'status' => 'selected_available',
                'verified' => true,
            ];
        } else {
            $validated['requested_handle'] = $this->generateAutomaticHandle(
                $validated['first_name'],
                $validated['last_name'],
            );
        }

        [$user, $billing] = DB::transaction(function () use (
            $validated,
            $plan,
            $planKey,
            $isPaid,
            $gender,
            $sport,
            $positions,
            $domainLookup,
        ) {
            $school = $this->resolveRegistrationSchool($validated['high_school'] ?? null, $validated['state']);
            [$league, $club, $clubLeague, $teamName] = $this->resolveRegistrationProgram(
                (int) $validated['league_id'],
                $validated['club_id'],
                $validated['club_other'] ?? null,
                $validated['team_name'],
                $gender,
                $sport,
            );

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'personal_email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'gender' => $gender,
                'sport' => $sport,
                'position' => $positions,
                'year' => (string) $validated['year'],
                'gpa' => $validated['gpa'] ?? null,
                'jersey_number' => isset($validated['jersey_number']) ? (string) $validated['jersey_number'] : null,
                'state' => $validated['state'],
                'country' => 'USA',
                'school_id' => $school?->id,
                'league_id' => $league?->id,
                'club_id' => $club?->id,
                'club_league_id' => $clubLeague?->id,
                'team_name' => $teamName,
                'club_coach' => $validated['club_coach'] ?? null,
                'club_coach_email' => $validated['club_coach_email'] ?? null,
                'parent' => $validated['guardian_name'] ?? null,
                'parent_email' => $validated['guardian_email'] ?? null,
                'registration_source' => 'native-registration',
                'utm_club_id' => $validated['utm_club_id'] ?? null,
                'utm_league_id' => $validated['utm_league_id'] ?? null,
                'utm_team_name' => $validated['utm_team_name'] ?? null,
                'must_change_password' => false,
            ]);

            $registrationRole = (string) ($plan['role_after_registration'] ?? 'Free');
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$registrationRole]);
            }

            $website = $this->createWebsiteIfSupported($user, ['sport' => $sport], []);
            if (! $isPaid && $website) {
                $website->forceFill(['slug' => $validated['requested_handle']])->save();
            }

            if ($isPaid) {
                $selectedDomain = $validated['requested_domain'];

                // Save the selected domain directly on the player's website/profile.
                if ($website) {
                    $website->forceFill(['domain' => $selectedDomain])->save();
                }

                // Some existing PLYRCARD databases also carry domain on users.
                // Populate it when that column exists without requiring the User
                // model's fillable list to change.
                if (Schema::hasColumn($user->getTable(), 'domain')) {
                    $user->forceFill(['domain' => $selectedDomain])->saveQuietly();
                }
            }

            $registrationMeta = [
                'source_utm_plan' => $planKey,
                'utm' => array_filter([
                    'source' => $validated['utm_source'] ?? null,
                    'medium' => $validated['utm_medium'] ?? null,
                    'campaign' => $validated['utm_campaign'] ?? null,
                    'content' => $validated['utm_content'] ?? null,
                    'term' => $validated['utm_term'] ?? null,
                    'club_id' => $validated['utm_club_id'] ?? null,
                    'league_id' => $validated['utm_league_id'] ?? null,
                    'team_name' => $validated['utm_team_name'] ?? null,
                ], fn ($value) => filled($value)),
                'high_school' => $validated['high_school'] ?? null,
                'league_name' => $league?->name,
                'club_name' => $club?->name,
                'team_name' => $teamName,
                'domain_rdap_verified' => $isPaid ? (bool) ($domainLookup['verified'] ?? false) : false,
                'domain_rdap_status' => $isPaid ? ($domainLookup['status'] ?? null) : null,
                'domain_lookup_source' => $isPaid ? 'selected-domain' : null,
                'domain_registrar_verified' => false,
            ];

            $recurring = (int) ($plan['recurring_amount_cents'] ?? 0);
            $setup = (int) ($plan['setup_fee_cents'] ?? 0);
            $chargeFirstMonthUpfront = (bool) ($plan['charge_first_month_upfront'] ?? true);
            $initialAmount = $setup + ($chargeFirstMonthUpfront ? $recurring : 0);

            $billing = BillingInformation::create([
                'user_id' => $user->id,
                'billing_name' => $isPaid
                    ? $validated['billing_name']
                    : trim($validated['first_name'] . ' ' . $validated['last_name']),
                'billing_email' => $isPaid ? $validated['billing_email'] : $validated['email'],
                'billing_phone' => $isPaid ? $validated['billing_phone'] : ($validated['phone'] ?? null),
                'billing_address_1' => $validated['billing_address_1'] ?? null,
                'billing_city' => $validated['billing_city'] ?? null,
                'billing_state' => $validated['billing_state'] ?? ($validated['state'] ?? null),
                'billing_postal_code' => $validated['billing_postal_code'] ?? null,
                'billing_country' => $validated['billing_country'] ?? 'US',
                'plan_key' => $planKey,
                'billing_cycle' => $isPaid ? 'monthly' : null,
                'currency' => 'USD',
                'recurring_amount_cents' => $recurring,
                'setup_fee_cents' => $setup,
                'initial_amount_cents' => $initialAmount,
                'payment_status' => $isPaid ? 'pending' : 'not_required',
                'subscription_status' => $isPaid ? 'pending' : 'free',
                'payment_provider' => $isPaid ? 'ghl_survey' : null,
                'payment_type' => $isPaid ? 'card' : null,
                'requested_domain' => $isPaid ? $validated['requested_domain'] : null,
                'requested_handle' => ! $isPaid ? $validated['requested_handle'] : null,
                'registration_meta' => $registrationMeta,
                'ghl_location_id' => config('ghl.location_id'),
                'ghl_sync_status' => 'pending',
            ]);

            return [$user, $billing];
        });

        $ghlContactId = null;
        $paymentFormUrl = null;

        try {
            $ghlContactId = $ghl->upsertContact([
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'phone' => $user->phone,
                'state' => $user->state,
                'country' => 'US',
                'tags' => [
                    'player-registration',
                    'registration-' . $planKey,
                    $isPaid ? 'payment-pending' : 'free-player',
                ],
                // Reuse the custom field already used by the current intake flow.
                // Payment/domain state remains authoritative in billing_information so
                // registration does not depend on new GHL custom fields existing first.
                'customFields' => [
                    ['key' => 'selected_plan', 'field_value' => $plan['label']],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Native registration GHL contact sync threw an exception.', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        if ($ghlContactId) {
            $locationId = config('ghl.location_id');

            $user->forceFill([
                'ghl_contact_id' => $ghlContactId,
                'ghl_location_id' => $locationId,
            ])->save();

            $billing->forceFill([
                'ghl_contact_id' => $ghlContactId,
                'ghl_location_id' => $locationId,
                'ghl_sync_status' => $isPaid ? 'payment_form_ready' : 'synced',
                'payment_status' => $isPaid ? 'payment_form_ready' : $billing->payment_status,
                'ghl_sync_response' => ['contact_id' => $ghlContactId],
                'ghl_synced_at' => now(),
            ])->save();
        } else {
            $billing->forceFill([
                'ghl_sync_status' => 'contact_sync_error',
                'ghl_sync_response' => ['message' => 'GHL contact could not be created or resolved.'],
                'ghl_synced_at' => now(),
            ])->save();
        }

        if ($isPaid) {
            $paymentFormUrl = $this->buildRegistrationPaymentFormUrl(
                $planKey,
                $plan,
                $user,
                $billing,
                $ghlContactId,
            );

            if (! $paymentFormUrl) {
                $billing->forceFill([
                    'payment_status' => 'payment_form_error',
                    'ghl_sync_status' => 'payment_form_error',
                ])->save();
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        try {
            $verificationUrl = URL::temporarySignedRoute(
                'registration.verify-email',
                now()->addHours(72),
                ['user' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            );
            Mail::to($user->email)->send(new \App\Mail\RegistrationVerificationMail($user, $verificationUrl));
            if (Schema::hasColumn('users', 'email_verification_sent_at')) {
                $user->forceFill(['email_verification_sent_at' => now()])->saveQuietly();
            }
        } catch (\Throwable $exception) {
            Log::warning('Registration verification email could not be sent.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        $billing->refresh();

        return response()->json([
            'success' => true,
            'plan_key' => $planKey,
            'paid_plan' => $isPaid,
            'billing_id' => $billing->id,
            'payment_status' => $billing->payment_status,
            'subscription_status' => $billing->subscription_status,
            'payment_form_url' => $paymentFormUrl,
            // Keep payment_url for compatibility with older registration JavaScript.
            'payment_url' => $paymentFormUrl,
            'redirect_url' => url('/admin/my-profile'),
            'message' => $isPaid
                ? ($paymentFormUrl
                    ? 'Your account is ready. Enter your card details below to complete payment.'
                    : 'Your account was created, but the payment form could not be opened. Please try again.')
                : 'Your free PLYRCARD account is ready. Check your email to verify your address.',
        ]);
    }

    public function paymentStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $billing = BillingInformation::query()->where('user_id', $user->id)->first();

        if (! $billing) {
            return response()->json(['message' => 'Billing record not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'plan_key' => $billing->plan_key,
            'payment_status' => $billing->payment_status,
            'subscription_status' => $billing->subscription_status,
            'paid' => $billing->payment_status === 'paid',
            'redirect_url' => url('/admin/my-profile'),
        ]);
    }

    protected function normalizePlanKey(mixed $value): string
    {
        $value = Str::of((string) $value)
            ->lower()
            ->trim()
            ->replace(['_', ' '], '-')
            ->replaceMatches('/-+/', '-')
            ->toString();

        return match ($value) {
            'myjourney', 'my-journey', 'journey' => 'my-journey',
            'amplify', 'power-4', 'power4' => 'amplify',
            default => 'free',
        };
    }

    protected function planConfig(string $planKey): array
    {
        $configuredPlan = config('plyrcard-registration.plans.' . $planKey);

        if (is_array($configuredPlan)) {
            return $configuredPlan;
        }

        $configuredFreePlan = config('plyrcard-registration.plans.free');

        if (is_array($configuredFreePlan)) {
            return $configuredFreePlan;
        }

        // Defensive fallback so registration can still render even if the
        // registration config file was not deployed or Laravel has stale config cache.
        return match ($planKey) {
            'my-journey' => [
                'label' => 'My Journey',
                'recurring_amount_cents' => 4900,
                'setup_fee_cents' => 0,
                'charge_first_month_upfront' => true,
                'role_after_registration' => 'Free',
                'role_after_payment' => 'My Journey',
                'payment_form_url' => 'https://systems.plyrcard.com/widget/survey/82L4a2pfvspbMYWeD0zo?notrack=true',
            ],
            'amplify' => [
                'label' => 'Amplify',
                'recurring_amount_cents' => 4900,
                'setup_fee_cents' => 50000,
                'charge_first_month_upfront' => false,
                'role_after_registration' => 'Free',
                'role_after_payment' => 'My Journey',
                'payment_form_url' => 'https://systems.plyrcard.com/widget/survey/FPx6oTagczUr0jH1X0ES?notrack=true',
            ],
            default => [
                'label' => 'Free',
                'recurring_amount_cents' => 0,
                'setup_fee_cents' => 0,
                'charge_first_month_upfront' => false,
                'role_after_registration' => 'Free',
                'role_after_payment' => 'Free',
            ],
        };
    }

    protected function buildRegistrationPaymentFormUrl(
        string $planKey,
        array $plan,
        User $user,
        BillingInformation $billing,
        ?string $contactId = null,
    ): ?string {
        $baseUrl = trim((string) ($plan['payment_form_url'] ?? ''));

        if ($baseUrl === '') {
            $baseUrl = match ($planKey) {
                'my-journey' => 'https://systems.plyrcard.com/widget/survey/82L4a2pfvspbMYWeD0zo?notrack=true',
                'amplify' => 'https://systems.plyrcard.com/widget/survey/FPx6oTagczUr0jH1X0ES?notrack=true',
                default => '',
            };
        }

        if ($baseUrl === '') {
            return null;
        }

        $label = (string) ($plan['label'] ?? Str::headline($planKey));
        $email = $billing->billing_email ?: $user->email;
        $phone = $billing->billing_phone ?: $user->phone;

        $params = array_filter([
            'notrack' => 'true',
            'utm_plan' => $planKey,
            'selected_plan' => $label,
            'plan' => $planKey,
            'first_name' => $user->first_name,
            'firstName' => $user->first_name,
            'contact.first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'lastName' => $user->last_name,
            'contact.last_name' => $user->last_name,
            'email' => $email,
            'contact.email' => $email,
            'phone' => $phone,
            'contact.phone' => $phone,
            'billing_name' => $billing->billing_name,
            'billing_email' => $email,
            'billing_phone' => $phone,
            'billing_address_1' => $billing->billing_address_1,
            'billing_city' => $billing->billing_city,
            'billing_state' => $billing->billing_state,
            'billing_postal_code' => $billing->billing_postal_code,
            'billing_country' => $billing->billing_country,
            'requested_domain' => $billing->requested_domain,
            'user_id' => $user->id,
            'contact_id' => $contactId,
            'app_url' => url('/admin/my-profile'),
        ], fn ($value) => filled($value));

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($params);
    }

    protected function normalizeRegistrationGender(string $gender): string
    {
        return match (strtolower(trim($gender))) {
            'girls', 'female' => 'female',
            'boys', 'male' => 'male',
            default => 'female',
        };
    }

    protected function normalizeHandle(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->trim()
            ->replaceMatches('/[^a-z0-9\s-]/', '')
            ->replaceMatches('/\s+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->limit(80, '')
            ->toString();
    }

    protected function normalizeDomain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^https?://#i', '', $value) ?: $value;
        $value = preg_replace('#/.*$#', '', $value) ?: $value;
        $value = preg_replace('/:\d+$/', '', $value) ?: $value;

        return trim($value, '. ');
    }

    protected function domainLooksValid(string $domain): bool
    {
        return strlen($domain) <= 253
            && (bool) preg_match('/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain);
    }

    protected function reservedHandles(): array
    {
        return [
            'admin', 'about', 'pricing', 'podcast', 'book-demo', 'registration',
            'player-intake', 'player-intake-app', 'preview', 'login', 'logout',
            'register', 'password-reset', 'forgot-password', 'email-verification',
            'livewire', 'filament', 'storage', 'api', 'locker-room', 'clubs',
            'teams', 'recruiting', 'track', 'support', 'terms', 'privacy',
        ];
    }

    protected function resolveRegistrationSchool(?string $name, ?string $state): ?School
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return School::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->first()
            ?: School::create(['name' => $name, 'state' => $state]);
    }

    protected function resolveRegistrationProgram(
        int $leagueId,
        string $clubId,
        ?string $clubOther,
        string $teamName,
        string $gender,
        string $sport,
    ): array {
        $league = $this->canonicalLeagueQuery()->findOrFail($leagueId);

        if (! $league->supportsGender($gender) || ! $this->isLeagueSportCompatible($league->sport, $sport)) {
            throw ValidationException::withMessages(['league_id' => 'The selected league does not match the athlete gender and sport.']);
        }

        if ($clubId === '__other__') {
            $clubName = trim((string) $clubOther);
            if ($clubName === '') {
                throw ValidationException::withMessages(['club_other' => 'Enter the club name.']);
            }
            $club = Club::firstOrCreate(['name' => $clubName]);
            $program = ClubLeague::firstOrCreate(
                ['club_id' => $club->id, 'league_id' => $league->id],
                ['genders' => [$gender], 'sport' => $sport, 'is_active' => true, 'sort_order' => 0],
            );
        } else {
            $club = Club::query()->find($clubId);
            $program = $club ? $this->activeClubLeagueQuery()
                ->where('club_id', $club->id)
                ->where('league_id', $league->id)
                ->where(function ($query) use ($sport) { $query->whereNull('sport')->orWhere('sport', $sport); })
                ->first() : null;
            if ($program && ! $this->gendersContain($program->genders ?: $league->genders ?: [$league->gender], $gender)) {
                $program = null;
            }
            if (! $club || ! $program) {
                throw ValidationException::withMessages(['club_id' => 'The selected club does not match the selected gender, sport, and league.']);
            }
        }

        $teamName = strtoupper(trim($teamName));
        if (! in_array($teamName, $this->getAgeGroupOptions(), true)) {
            throw ValidationException::withMessages([
                'team_name' => 'Select a valid team age group.',
            ]);
        }

        return [$league, $club, $program, $teamName];
    }

    protected function generateAutomaticHandle(string $firstName, string $lastName): string
    {
        $base = Str::slug(trim($firstName . ' ' . $lastName)) ?: 'player';
        if (in_array($base, $this->reservedHandles(), true)) {
            $base .= '-player';
        }
        $slug = $base;
        $counter = 2;
        while (
            Website::query()->whereRaw('LOWER(slug) = ?', [strtolower($slug)])->exists()
            || BillingInformation::query()->whereRaw('LOWER(requested_handle) = ?', [strtolower($slug)])->whereIn('payment_status', ['pending','payment_form_ready','paid','not_required'])->exists()
        ) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    protected function states(): array
    {
        return [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
            'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
            'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
            'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
            'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
            'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
            'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
            'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
            'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
            'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        ];
    }
}