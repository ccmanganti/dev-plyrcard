<?php

namespace App\Http\Controllers;

use App\Models\AdditionalServiceRequest;
use App\Models\BillingInformation;
use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\League;
use App\Models\LockerRoomReferral;
use App\Models\LockerRoomSupportRequest;
use App\Models\NationalTeam;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Website;
use App\Services\GoHighLevelService;
use App\Services\BillingProfileService;
use App\Services\LockerRoomDataService;
use App\Services\LockerRoomReferralEmailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LockerRoomController extends Controller
{
    public function data(Request $request, LockerRoomDataService $dataService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        return response()->json([
            'success' => true,
            'data' => $dataService->snapshot($user),
        ]);
    }

    public function dashboardActivity(Request $request, LockerRoomDataService $dataService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($dataService->hasPremiumLockerRoomAccess($user), 403);

        $validated = $request->validate([
            'metric' => ['required', Rule::in([
                'profile_views',
                'email_clicks',
                'email_opens',
                'social_clicks',
                'emails_sent',
                'coach_replies',
                'schools_engaged',
            ])],
        ]);

        return response()->json([
            'success' => true,
            'data' => $dataService->dashboardActivity($user, (string) $validated['metric']),
        ]);
    }

    public function dashboardSchool(Request $request, string $school, LockerRoomDataService $dataService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($dataService->hasPremiumLockerRoomAccess($user), 403);

        return response()->json([
            'success' => true,
            'data' => $dataService->dashboardSchool($user, $school),
        ]);
    }

    public function profileOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        $user->loadMissing(['school', 'league', 'club', 'nationalTeam']);

        $filters = $request->validate([
            'type' => ['required', Rule::in(['school', 'league', 'club', 'national_team', 'age_group'])],
            'sport' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'league_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $type = (string) $filters['type'];
        $search = trim((string) ($filters['search'] ?? ''));
        $sport = trim((string) ($filters['sport'] ?? $user->sport ?? ''));
        $gender = $this->normalizeGender($filters['gender'] ?? $user->gender ?? null);
        $leagueId = $filters['league_id'] ?? $user->league_id;
        $rows = collect();

        if ($type === 'school') {
            $rows = School::query()
                ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%' . $search . '%'))
                ->orderBy('name')
                ->limit(120)
                ->get(['id', 'name'])
                ->map(fn (School $school): array => ['value' => (string) $school->id, 'label' => (string) $school->name]);

            if ($user->school && ! $rows->contains('value', (string) $user->school->id)) {
                $rows->prepend(['value' => (string) $user->school->id, 'label' => (string) $user->school->name]);
            }
        } elseif ($type === 'national_team') {
            $rows = NationalTeam::query()
                ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%' . $search . '%'))
                ->orderBy('name')
                ->limit(120)
                ->get(['id', 'name'])
                ->map(fn (NationalTeam $team): array => ['value' => (string) $team->id, 'label' => (string) $team->name]);

            if ($user->nationalTeam && ! $rows->contains('value', (string) $user->nationalTeam->id)) {
                $rows->prepend(['value' => (string) $user->nationalTeam->id, 'label' => (string) $user->nationalTeam->name]);
            }
        } elseif ($type === 'league') {
            if ($sport !== '' && $gender !== null) {
                $rows = League::query()
                    ->where(function (Builder $query) use ($gender): Builder {
                        return $query
                            ->whereJsonContains('genders', $gender)
                            ->orWhere('gender', $gender)
                            ->orWhere('gender', ucfirst($gender))
                            ->orWhere('gender', $gender === 'female' ? 'Girls' : 'Boys')
                            ->orWhere('gender', $gender === 'female' ? 'Female' : 'Male');
                    })
                    ->where(function (Builder $query) use ($sport): Builder {
                        return $query->whereNull('sport')->orWhere('sport', $sport);
                    })
                    ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%' . $search . '%'))
                    ->orderBy('name')
                    ->limit(100)
                    ->get(['id', 'name'])
                    ->map(fn (League $league): array => ['value' => (string) $league->id, 'label' => (string) $league->name]);
            }

            if ($user->league && ! $rows->contains('value', (string) $user->league->id)) {
                $rows->prepend(['value' => (string) $user->league->id, 'label' => (string) $user->league->name]);
            }
        } elseif ($type === 'club') {
            if ($leagueId) {
                $rows = Club::query()
                    ->whereHas('clubLeagues', function (Builder $query) use ($leagueId, $gender, $sport): Builder {
                        return $query
                            ->where('league_id', $leagueId)
                            ->where('is_active', true)
                            ->when($gender !== null, fn (Builder $query): Builder => $query->whereJsonContains('genders', $gender))
                            ->when($sport !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($sport): Builder {
                                return $query->whereNull('sport')->orWhere('sport', $sport);
                            }));
                    })
                    ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%' . $search . '%'))
                    ->orderBy('name')
                    ->limit(100)
                    ->get(['id', 'name'])
                    ->map(fn (Club $club): array => ['value' => (string) $club->id, 'label' => (string) $club->name]);
            }

            if ($user->club && ! $rows->contains('value', (string) $user->club->id)) {
                $rows->prepend(['value' => (string) $user->club->id, 'label' => (string) $user->club->name]);
            }
        } else {
            $rows = collect(config('plyrcard.age_groups', [
                'u13' => 'U13', 'u14' => 'U14', 'u15' => 'U15', 'u16' => 'U16',
                'u17' => 'U17', 'u18' => 'U18', 'u19' => 'U19',
            ]))->values()->map(fn ($label): array => ['value' => (string) $label, 'label' => (string) $label]);
        }

        return response()->json([
            'success' => true,
            'options' => $rows->unique('value')->values()->all(),
        ]);
    }

    public function updateProfile(Request $request, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            'sport' => ['required', 'string', 'max:255'],
            'position' => ['required', 'array', 'min:1'],
            'position.*' => ['required', 'string', 'max:255'],
            'jersey_number' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth' => ['nullable', 'date'],
            'gpa' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'height' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'string', 'max:255'],
            'dominant_foot' => ['nullable', Rule::in(['left', 'right', 'both'])],
            'ncaa_field_id' => ['nullable', 'string', 'max:255'],
            'max_speed' => ['nullable', 'numeric', 'min:0'],
            'school_id' => ['nullable', 'integer', Rule::exists('schools', 'id')],
            'league_id' => ['nullable', 'integer', Rule::exists('leagues', 'id')],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
            'team_name' => ['nullable', 'string', 'max:255'],
            'national_team_id' => ['nullable', 'integer', Rule::exists('national_teams', 'id')],
            'national_team_period' => ['nullable', 'string', 'max:255'],
            'pro_club_name' => ['nullable', 'string', 'max:255'],
            'pro_club_logo' => ['nullable', 'image', 'max:5120'],

            'player_bio' => ['nullable', 'string', 'max:10000'],
            'academic_accolades' => ['nullable', 'string', 'max:10000'],
            'sports_accolades' => ['nullable', 'string', 'max:10000'],

            'ig_handle' => ['nullable', 'string', 'max:255'],
            'x_handle' => ['nullable', 'string', 'max:255'],
            'yt_url' => ['nullable', 'url', 'max:2048'],
            'featured_video_url' => ['nullable', 'url', 'max:2048'],
            'featured_video_urls' => ['nullable', 'string', 'max:10000'],

            'parent' => ['nullable', 'string', 'max:255'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:255'],
            'sec_parent' => ['nullable', 'string', 'max:255'],
            'sec_parent_email' => ['nullable', 'email', 'max:255'],
            'sec_parent_phone' => ['nullable', 'string', 'max:255'],
            'club_coach' => ['nullable', 'string', 'max:255'],
            'club_coach_email' => ['nullable', 'email', 'max:255'],
            'club_coach_phone' => ['nullable', 'string', 'max:255'],
            'natl_coach' => ['nullable', 'string', 'max:255'],
            'natl_coach_email' => ['nullable', 'email', 'max:255'],
            'natl_coach_phone' => ['nullable', 'string', 'max:255'],
            'tech_trainer' => ['nullable', 'string', 'max:255'],
            'tech_trainer_email' => ['nullable', 'email', 'max:255'],
            'tech_trainer_phone' => ['nullable', 'string', 'max:255'],
            'snc_trainer' => ['nullable', 'string', 'max:255'],
            'snc_trainer_email' => ['nullable', 'email', 'max:255'],
            'snc_trainer_phone' => ['nullable', 'string', 'max:255'],

            'raw_player_images_existing' => ['nullable', 'array', 'max:20'],
            'raw_player_images_existing.*' => ['nullable', 'string', 'max:2048'],
            'raw_player_images_new' => ['nullable', 'array', 'max:20'],
            'raw_player_images_new.*' => ['image', 'max:5120'],
        ]);

        $data['position'] = collect($data['position'] ?? [])
            ->map(fn ($position) => trim((string) $position))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data['club_league_id'] = $this->resolveClubLeagueId(
            $data['club_id'] ?? null,
            $data['league_id'] ?? null,
            $data['gender'] ?? null,
            $data['sport'] ?? null,
        );

        if (filled($data['club_id'] ?? null) && filled($data['league_id'] ?? null) && ! $data['club_league_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'club_id' => ['The selected club does not match the selected league, sport, and sex.'],
            ]);
        }

        if (filled($data['team_name'] ?? null)) {
            $data['team_name'] = strtoupper(trim((string) $data['team_name']));
        } elseif (blank($data['club_id'] ?? null)) {
            $data['team_name'] = null;
        }

        if ($request->hasFile('pro_club_logo')) {
            $data['pro_club_logo'] = $request->file('pro_club_logo')->store('pro-club-logos', 'public');
        } else {
            unset($data['pro_club_logo']);
        }

        $currentRawImages = collect(is_array($user->raw_player_images) ? $user->raw_player_images : [])
            ->map(fn ($path): string => trim((string) $path))
            ->filter()
            ->unique()
            ->values();

        $keptRawImages = $request->has('raw_player_images_existing')
            ? collect($data['raw_player_images_existing'] ?? [])
                ->map(fn ($path): string => trim((string) $path))
                ->filter(fn (string $path): bool => $currentRawImages->contains($path))
                ->unique()
                ->values()
            : $currentRawImages->values();

        $newRawFiles = collect($request->file('raw_player_images_new', []))->filter();
        if (($keptRawImages->count() + $newRawFiles->count()) > 20) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'raw_player_images_new' => ['You can keep up to 20 raw player images.'],
            ]);
        }

        $removedRawImages = $currentRawImages->diff($keptRawImages);
        foreach ($removedRawImages as $removedPath) {
            if (str_starts_with($removedPath, 'user-player-images/raw/')) {
                Storage::disk('public')->delete($removedPath);
            }
        }

        foreach ($newRawFiles as $file) {
            $keptRawImages->push($file->store('user-player-images/raw', 'public'));
        }

        $data['raw_player_images'] = $keptRawImages->unique()->values()->all();
        unset($data['raw_player_images_existing'], $data['raw_player_images_new']);

        if (! $this->hasPremiumAccess($user)) {
            unset(
                $data['ig_handle'],
                $data['x_handle'],
                $data['yt_url'],
                $data['featured_video_url'],
                $data['featured_video_urls']
            );
        }

        $user->forceFill($data)->save();
        $user->refresh();

        return $this->success($request, 'Profile saved.', [
            'data' => $dataService->snapshot($user),
        ]);
    }

    public function uploadPhotos(Request $request, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'category' => ['required', Rule::in(['player', 'plyrcard'])],
            'photos' => ['required', 'array', 'min:1', 'max:30'],
            'photos.*' => ['required', 'image', 'max:5120'],
        ]);

        $category = (string) $data['category'];
        if (! $this->canManagePhotoCategory($user, $category)) {
            return $this->failure($request, 'Only a PLYRCARD administrator can manage PLYRCARD Images.', 403);
        }

        $paths = $this->lockerRoomPhotoPaths($user, $category);
        $max = $category === 'plyrcard' ? 30 : 20;
        if (($paths->count() + count($data['photos'])) > $max) {
            return $this->failure($request, "This gallery can contain up to {$max} photos.");
        }

        $directory = $category === 'plyrcard' ? 'user-player-images/plyrcard' : 'user-player-images/raw';
        foreach ($data['photos'] as $photo) {
            $paths->push($photo->store($directory, 'public'));
        }

        $this->saveLockerRoomPhotoPaths($user, $category, $paths->all());

        return $this->success($request, 'Photos uploaded.', [
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function replacePhoto(Request $request, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'category' => ['required', Rule::in(['player', 'plyrcard'])],
            'index' => ['required', 'integer', 'min:0'],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $category = (string) $data['category'];
        if (! $this->canManagePhotoCategory($user, $category)) {
            return $this->failure($request, 'Only a PLYRCARD administrator can manage PLYRCARD Images.', 403);
        }

        $paths = $this->lockerRoomPhotoPaths($user, $category);
        $index = (int) $data['index'];
        if (! $paths->has($index)) {
            return $this->failure($request, 'That photo is no longer available.', 404);
        }

        $oldPath = (string) $paths->get($index);
        $directory = $category === 'plyrcard' ? 'user-player-images/plyrcard' : 'user-player-images/raw';
        $newPath = $data['photo']->store($directory, 'public');
        $paths->put($index, $newPath);
        $this->saveLockerRoomPhotoPaths($user, $category, $paths->values()->all());
        $this->deleteLockerRoomManagedPhoto($oldPath, $category);

        return $this->success($request, 'Photo replaced.', [
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function deletePhoto(Request $request, string $category, int $index, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $category = strtolower(trim($category)) === 'plyrcard' ? 'plyrcard' : 'player';

        if (! $this->canManagePhotoCategory($user, $category)) {
            return $this->failure($request, 'Only a PLYRCARD administrator can manage PLYRCARD Images.', 403);
        }

        $paths = $this->lockerRoomPhotoPaths($user, $category);
        if (! $paths->has($index)) {
            return $this->failure($request, 'That photo is no longer available.', 404);
        }

        $oldPath = (string) $paths->get($index);
        $paths->forget($index);
        $this->saveLockerRoomPhotoPaths($user, $category, $paths->values()->all());
        $this->deleteLockerRoomManagedPhoto($oldPath, $category);

        return $this->success($request, 'Photo removed.', [
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function reorderPhotos(Request $request, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'category' => ['required', Rule::in(['player', 'plyrcard'])],
            'index' => ['required', 'integer', 'min:0'],
            'direction' => ['required', Rule::in(['left', 'right'])],
        ]);

        $category = (string) $data['category'];
        if (! $this->canManagePhotoCategory($user, $category)) {
            return $this->failure($request, 'Only a PLYRCARD administrator can manage PLYRCARD Images.', 403);
        }

        $rows = $this->lockerRoomPhotoPaths($user, $category)->values()->all();
        $index = (int) $data['index'];
        $target = $index + ($data['direction'] === 'left' ? -1 : 1);
        if (! isset($rows[$index], $rows[$target])) {
            return $this->success($request, 'Photo order unchanged.', [
                'data' => $dataService->snapshot($user->fresh()),
            ]);
        }

        [$rows[$index], $rows[$target]] = [$rows[$target], $rows[$index]];
        $this->saveLockerRoomPhotoPaths($user, $category, $rows);

        return $this->success($request, 'Photo order updated.', [
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function storeSchedule(Request $request, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->ensureScheduleAccess($user);

        $data = $this->validatedScheduleData($request);
        $data['created_by_user_id'] = $user->id;

        $schedule = Schedule::create($data);
        $schedule->users()->syncWithoutDetaching([$user->id]);

        return $this->success($request, 'Schedule added.', [
            'schedule' => $schedule->fresh(),
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function updateSchedule(Request $request, Schedule $schedule, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->ensureScheduleAccess($user);

        if ((int) $schedule->created_by_user_id !== (int) $user->id) {
            return $this->failure($request, 'You can view this team schedule, but only its creator can edit it.', 403);
        }

        $schedule->fill($this->validatedScheduleData($request))->save();

        return $this->success($request, 'Schedule updated.', [
            'schedule' => $schedule->fresh(),
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function deleteSchedule(Request $request, Schedule $schedule, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->ensureScheduleAccess($user);

        if ((int) $schedule->created_by_user_id !== (int) $user->id) {
            return $this->failure($request, 'You can view this team schedule, but only its creator can remove it.', 403);
        }

        $schedule->delete();

        return $this->success($request, 'Schedule removed.', [
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function updateBilling(Request $request, BillingProfileService $billingProfiles, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        // One canonical save path is used by Admin Settings, Locker Room, and
        // upgrade-preparation. This writes the billing profile locally first, then
        // creates/updates the payer contact in PLYRCARD's billing subaccount.
        // Raw card numbers/CVC are never accepted by this endpoint.
        $data = $request->validate($billingProfiles->rules());
        $billing = $billingProfiles->update($user, $data);
        $user->refresh();

        $subscriberContactId = trim((string) ($user->ghl_subscriber_contact_id ?: $billing->ghl_contact_id));

        return $this->success($request, 'Billing information saved.', [
            'billing_ready' => $billingProfiles->isComplete($billing) && $subscriberContactId !== '',
            'missing_fields' => $billingProfiles->missingRequiredFields($billing),
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    public function updateSettings(Request $request, GoHighLevelService $ghl, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $defaults = [
            'profile_views' => true,
            'instagram_clicks' => true,
            'youtube_clicks' => true,
            'x_clicks' => true,
            'email_opens' => true,
            'coach_replies' => true,
            'weekly_digest' => false,
            'product_news' => false,
        ];

        $data = $request->validate([
            'notifications' => ['nullable', 'array'],
            'notifications.profile_views' => ['nullable', 'boolean'],
            'notifications.instagram_clicks' => ['nullable', 'boolean'],
            'notifications.youtube_clicks' => ['nullable', 'boolean'],
            'notifications.x_clicks' => ['nullable', 'boolean'],
            'notifications.email_opens' => ['nullable', 'boolean'],
            'notifications.coach_replies' => ['nullable', 'boolean'],
            'notifications.weekly_digest' => ['nullable', 'boolean'],
            'notifications.product_news' => ['nullable', 'boolean'],
            'article_section_type' => ['nullable', Rule::in(['follow_me', 'calendar'])],
        ]);

        $stored = Cache::get('coach-database:notification-settings:' . $user->id, []);
        $settings = array_merge($defaults, is_array($stored) ? $stored : []);

        foreach (($data['notifications'] ?? []) as $key => $value) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = (bool) $value;
            }
        }

        if (filled($data['article_section_type'] ?? null)) {
            if (! $this->hasPremiumAccess($user)) {
                return $this->failure($request, 'Website display controls are available with My Journey.', 403);
            }

            $website = Website::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->latest('updated_at')
                ->first();

            if (! $website) {
                return $this->failure($request, 'Your PLYRCARD website is still being prepared.', 422);
            }

            if ($data['article_section_type'] === 'follow_me') {
                $website->forceFill([
                    'article_section_type' => 'follow_me',
                    'ghl_calendar_id' => null,
                    'ghl_calendar_name' => null,
                    'ghl_calendar_embed_url' => null,
                ])->save();
            } else {
                $calendarAlreadyReady = $website->article_section_type === 'calendar'
                    && (filled($website->ghl_calendar_id) || filled($website->ghl_calendar_embed_url));

                if (! $calendarAlreadyReady) {
                    if (! $this->workspaceReady($user)) {
                        return $this->failure($request, 'We are still preparing your PLYRCARD. Calendar controls will become available when setup is complete.', 422);
                    }

                    try {
                        $sync = $ghl->syncFirstActivePersonalCalendarForWebsite($website);
                    } catch (\Throwable $exception) {
                        report($exception);
                        $sync = ['ok' => false];
                    }

                    if (! ($sync['ok'] ?? false)) {
                        return $this->failure($request, 'Your booking calendar is not ready yet. Please try again after your PLYRCARD setup is complete.', 422);
                    }
                }

                $website->forceFill(['article_section_type' => 'calendar'])->save();
            }
        }

        // Commit notification preferences only after any requested PLYRCARD-display
        // update succeeds, so the response is never a partial save.
        Cache::put('coach-database:notification-settings:' . $user->id, $settings, now()->addYear());

        return $this->success($request, 'Settings saved.', [
            'data' => $dataService->snapshot($user->fresh()),
        ]);
    }

    /** Backwards-compatible route for older Locker Room forms. */
    public function updateWebsiteSettings(Request $request, GoHighLevelService $ghl, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        return $this->updateSettings($request, $ghl, $dataService);
    }

    public function refreshWebsiteCalendar(Request $request, GoHighLevelService $ghl, LockerRoomDataService $dataService): RedirectResponse|JsonResponse
    {
        $request->merge(['article_section_type' => 'calendar']);
        return $this->updateSettings($request, $ghl, $dataService);
    }

    public function updatePasswordFromOverlay(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        $request->session()->forget('plyrcard_show_password_overlay');

        return $this->success($request, 'Password updated.');
    }

    public function storeSupport(Request $request, GoHighLevelService $ghl): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'concern' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $support = LockerRoomSupportRequest::create([
            'user_id' => $user->id,
            'concern' => $data['concern'],
            'details' => $data['details'],
            'status' => 'open',
        ]);

        try {
            $sync = $ghl->upsertContact($user, [], [], 'PlyrCard Support Request');
            $note = $ghl->addContactNote($sync['contact_id'] ?? null, "Support request: {$data['concern']}\n\n{$data['details']}");

            $support->forceFill([
                'ghl_contact_id' => $sync['contact_id'] ?? null,
                'ghl_sync_status' => ($sync['ok'] ?? false) ? 'synced' : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
                'ghl_sync_response' => ['contact' => $sync, 'note' => $note],
                'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : null,
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->mailSupportCopy(
            $user,
            'Locker Room support request',
            "Support request from {$user->first_name} {$user->last_name} ({$user->email})\n\nConcern: {$data['concern']}\n\n{$data['details']}"
        );

        return $this->success($request, 'Support request submitted.', ['support_request' => $support->fresh()]);
    }

    public function storeReferral(
        Request $request,
        GoHighLevelService $ghl,
        LockerRoomReferralEmailService $referralEmail,
    ): RedirectResponse|JsonResponse {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'friend_name' => ['required', 'string', 'max:255'],
            'friend_email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $referral = LockerRoomReferral::create([
            'user_id' => $user->id,
            'friend_name' => $data['friend_name'],
            'friend_email' => $data['friend_email'],
            'friend_phone' => null,
            'message' => $data['message'] ?? null,
            'status' => 'new',
        ]);

        try {
            $nameParts = preg_split('/\s+/', trim($data['friend_name']), 2);
            $sync = $ghl->upsertContact($user, [
                'firstName' => $nameParts[0] ?? $data['friend_name'],
                'lastName' => $nameParts[1] ?? null,
                'name' => $data['friend_name'],
                'email' => $data['friend_email'],
            ], [], 'PlyrCard Referral');

            $note = $ghl->addContactNote($sync['contact_id'] ?? null, "Referred by {$user->first_name} {$user->last_name} ({$user->email}).\n\nMessage: " . ($data['message'] ?? '-'));

            $referral->forceFill([
                'ghl_contact_id' => $sync['contact_id'] ?? null,
                'ghl_sync_status' => ($sync['ok'] ?? false) ? 'synced' : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
                'ghl_sync_response' => ['contact' => $sync, 'note' => $note],
                'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : null,
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);
        }

        $emailResult = $referralEmail->send(
            $user,
            (string) $data['friend_name'],
            (string) $data['friend_email'],
            $data['message'] ?? null,
        );

        if (! ($emailResult['success'] ?? false)) {
            return $this->failure(
                $request,
                'The referral was saved, but the invitation email could not be sent. Please try again.',
                502,
                [
                    'referral_saved' => true,
                    'email_sent' => false,
                    'email_error' => $emailResult['error'] ?? null,
                    'referral' => $referral->fresh(),
                ],
            );
        }

        return $this->success($request, 'Invitation email sent to ' . $data['friend_email'] . '.', [
            'email_sent' => true,
            'recipient' => $emailResult['recipient'] ?? $data['friend_email'],
            'referral' => $referral->fresh(),
        ]);
    }

    public function storeAdditionalService(Request $request, GoHighLevelService $ghl): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $services = [
            'upgraded_site_design' => 'Upgraded Site Design',
            'starting_graphics_bundle' => 'Starting Graphics Bundle',
            'individual_graphic' => 'Individual Graphic',
            'domain' => 'Domain',
            'custom' => 'Custom / Other',
        ];

        $data = $request->validate([
            'service_key' => ['required', Rule::in(array_keys($services))],
            'service_name' => ['nullable', 'string', 'max:255'],
            'listed_price' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $serviceName = $data['service_name'] ?: $services[$data['service_key']];
        $serviceRequest = AdditionalServiceRequest::create([
            'user_id' => $user->id,
            'service_key' => $data['service_key'],
            'service_name' => $serviceName,
            'listed_price' => $data['listed_price'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'new',
        ]);

        try {
            $sync = $ghl->upsertContact($user, [], [], 'PlyrCard Additional Service');
            $note = $ghl->addContactNote($sync['contact_id'] ?? null, "Additional service request: {$serviceName}\nListed price: " . ($data['listed_price'] ?? '-') . "\n\nNotes: " . ($data['notes'] ?? '-'));
            $serviceRequest->forceFill([
                'ghl_contact_id' => $sync['contact_id'] ?? null,
                'ghl_sync_status' => ($sync['ok'] ?? false) ? 'synced' : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
                'ghl_sync_response' => ['contact' => $sync, 'note' => $note],
                'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : null,
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $this->success($request, 'Request submitted.', ['additional_service_request' => $serviceRequest->fresh()]);
    }

    protected function lockerRoomPhotoPaths($user, string $category): \Illuminate\Support\Collection
    {
        $category = strtolower(trim($category)) === 'plyrcard' ? 'plyrcard' : 'player';
        $value = $category === 'plyrcard' ? ($user->plyrcard_images ?? []) : ($user->raw_player_images ?? []);

        return collect(is_array($value) ? $value : [])
            ->map(fn ($path): string => trim((string) $path))
            ->filter()
            ->unique()
            ->values();
    }

    protected function saveLockerRoomPhotoPaths($user, string $category, array $paths): void
    {
        $category = strtolower(trim($category)) === 'plyrcard' ? 'plyrcard' : 'player';
        $column = $category === 'plyrcard' ? 'plyrcard_images' : 'raw_player_images';
        $user->forceFill([
            $column => array_values(array_unique(array_filter(array_map('strval', $paths)))),
        ])->save();
    }

    protected function canManagePhotoCategory($user, string $category): bool
    {
        if ($category !== 'plyrcard') {
            return true;
        }

        try {
            if (method_exists($user, 'isSuperadminOrImpersonating')) {
                return (bool) $user->isSuperadminOrImpersonating();
            }

            return method_exists($user, 'hasRole') && (
                $user->hasRole('superadmin') || $user->hasRole('Superadmin') || $user->hasRole('Super Admin')
            );
        } catch (\Throwable) {
            return false;
        }
    }

    protected function deleteLockerRoomManagedPhoto(string $path, string $category): void
    {
        $path = trim($path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return;
        }

        $path = ltrim($path, '/');
        $prefix = $category === 'plyrcard' ? 'user-player-images/plyrcard/' : 'user-player-images/raw/';
        if (str_starts_with($path, $prefix)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function normalizeGender(?string $gender): ?string
    {
        $gender = strtolower(trim((string) $gender));

        return match (true) {
            str_contains($gender, 'female'), str_contains($gender, 'girl'), str_contains($gender, 'women') => 'female',
            str_contains($gender, 'male'), str_contains($gender, 'boy'), str_contains($gender, 'men') => 'male',
            default => filled($gender) ? $gender : null,
        };
    }

    protected function resolveClubLeagueId(?int $clubId, ?int $leagueId, ?string $gender, ?string $sport = null): ?int
    {
        if (! $clubId || ! $leagueId) {
            return null;
        }

        $gender = $this->normalizeGender($gender);

        return ClubLeague::query()
            ->where('club_id', $clubId)
            ->where('league_id', $leagueId)
            ->where('is_active', true)
            ->when($gender !== null, fn (Builder $query): Builder => $query->whereJsonContains('genders', $gender))
            ->when(filled($sport), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($sport): Builder {
                return $query->whereNull('sport')->orWhere('sport', $sport);
            }))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');
    }

    protected function validatedScheduleData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'opponent' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['upcoming', 'completed', 'cancelled', 'postponed'])],
            'game_date' => ['required', 'date'],
            'game_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'string', 'max:255'],
            'score' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_home' => ['nullable', 'boolean'],
        ]);

        $data['status'] = $data['status'] ?? 'upcoming';
        $data['is_home'] = $request->boolean('is_home');

        return $data;
    }

    protected function ensureScheduleAccess($user): void
    {
        if (! $this->hasPremiumAccess($user)) {
            abort(403, 'Schedule is available with My Journey.');
        }
    }

    protected function hasPremiumAccess($user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            if (method_exists($user, 'hasRole') && ($user->hasRole('My Journey') || $user->hasRole('Amplify'))) {
                return true;
            }
        } catch (\Throwable) {
        }

        $plan = strtolower(trim((string) optional($user->billingInformation)->plan_key));
        return in_array($plan, ['my-journey', 'my_journey', 'amplify'], true);
    }

    protected function workspaceReady($user): bool
    {
        $apiKey = trim((string) ($user->getRawOriginal('ghl_api_key') ?? ''));
        $locationId = trim((string) ($user->getRawOriginal('ghl_location_id') ?? ''));
        $missing = ['', 'null', 'none', 'pending', 'not set', 'n/a'];

        return ! in_array(strtolower($apiKey), $missing, true)
            && ! in_array(strtolower($locationId), $missing, true);
    }

    private function mailSupportCopy($user, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to('support@plyrcard.com')->subject($subject);

                if ($user?->email) {
                    $message->replyTo($user->email);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function failure(Request $request, string $message, int $status = 422, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge([
                'success' => false,
                'message' => $message,
            ], $extra), $status);
        }

        return back()->withErrors(['locker_room' => $message]);
    }

    private function success(Request $request, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
            ], $extra));
        }

        return back()->with('success', $message);
    }
}