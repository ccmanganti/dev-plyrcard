@php
    // v10.43: the public website navigation/Locker Room UI must never render
    // inside Filament/admin. This guard wraps the entire partial, including its
    // assets, styles, header markup, pull-up control, and scripts.
    $plyrSuppressPublicNavigation = request()->is('admin') || request()->is('admin/*');
@endphp

@if (! $plyrSuppressPublicNavigation)
  @once
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  @endonce


  @php
      $plyrUser = auth()->user();
      $plyrLoggedIn = auth()->check();

      $plyrFirstName = 'Player';
      if ($plyrLoggedIn && $plyrUser) {
          $rawFirstName = $plyrUser->first_name
              ?? $plyrUser->firstname
              ?? $plyrUser->given_name
              ?? null;

          $plyrFirstName = $rawFirstName
              ? trim($rawFirstName)
              : \Illuminate\Support\Str::of($plyrUser->name ?? 'Player')->trim()->explode(' ')->first();
      }

      $plyrRoleNames = collect();
      if ($plyrLoggedIn && $plyrUser && method_exists($plyrUser, 'getRoleNames')) {
          $plyrRoleNames = $plyrUser->getRoleNames()
              ->map(fn ($role) => strtolower(trim((string) $role)))
              ->values();
      }

      $plyrHasMyJourneyRole = $plyrRoleNames->contains('my journey');
      $plyrHasPlyrPlusRole = $plyrRoleNames->contains('plyr plus') || $plyrRoleNames->contains('plyr+') || $plyrRoleNames->contains('rookie plus');
      $plyrHasPlyrRole = $plyrRoleNames->contains('plyr');
      $plyrHasFreeRole = $plyrRoleNames->contains('free');

      $plyrPlanName = match (true) {
          $plyrHasMyJourneyRole => 'My Journey',
          $plyrHasPlyrPlusRole => 'Plyr Plus',
          $plyrHasPlyrRole => 'Plyr',
          $plyrHasFreeRole => 'Free',
          default => 'Free',
      };

      $plyrHasPremiumFeatures = $plyrHasPlyrPlusRole || $plyrHasMyJourneyRole;

      $plyrActivePage = $activePage ?? null;
      $plyrCurrentPath = trim(request()->path(), '/');
      $plyrOnRegistrationPage = $plyrActivePage === 'registration'
          || request()->is('registration')
          || request()->is('registration/*')
          || $plyrCurrentPath === 'registration'
          || str_starts_with($plyrCurrentPath, 'registration/');
      $plyrCurrentHost = request()->getHost();
      $plyrCurrentHostNormalized = strtolower(preg_replace('/:\d+$/', '', $plyrCurrentHost));
      $plyrCurrentHostBase = preg_replace('/^www\./i', '', $plyrCurrentHostNormalized);
      $plyrRequestUrl = rtrim(request()->url(), '/');

      $plyrNormalizeDomain = function ($value) {
          $domain = strtolower(trim((string) $value));
          $domain = preg_replace('#^https?://#i', '', $domain);
          $domain = preg_replace('#/.*$#', '', $domain);
          $domain = preg_replace('/:\d+$/', '', $domain);
          return rtrim($domain, '/');
      };

      $plyrDomainBase = function ($value) use ($plyrNormalizeDomain) {
          return preg_replace('/^www\./i', '', $plyrNormalizeDomain($value));
      };

      $plyrReservedPaths = ['', '/', 'about', 'pricing', 'podcast', 'book-demo', 'registration', 'login', 'admin'];
      $plyrMainHosts = array_filter(array_map('strtolower', [
          'plyrcard.com',
          'www.plyrcard.com',
          parse_url(config('app.url'), PHP_URL_HOST),
          '127.0.0.1',
          'localhost',
      ]));
      $plyrMainHostBases = array_values(array_unique(array_filter(array_map(fn ($host) => preg_replace('/^www\./i', '', (string) $host), $plyrMainHosts))));

      $plyrOnAdmin = request()->is('admin') || request()->is('admin/*') || $plyrActivePage === 'admin';
      $plyrOnMainPlyrSite = in_array($plyrCurrentHostNormalized, $plyrMainHosts, true)
          || in_array($plyrCurrentHostBase, $plyrMainHostBases, true);

      $plyrDomainMatchesHost = function ($domain) use ($plyrNormalizeDomain, $plyrDomainBase, $plyrCurrentHostNormalized, $plyrCurrentHostBase) {
          $normalizedDomain = $plyrNormalizeDomain($domain);
          $baseDomain = $plyrDomainBase($normalizedDomain);

          if (blank($normalizedDomain) || blank($baseDomain)) {
              return false;
          }

          return $normalizedDomain === $plyrCurrentHostNormalized
              || $normalizedDomain === 'www.' . $plyrCurrentHostBase
              || $baseDomain === $plyrCurrentHostBase
              || 'www.' . $baseDomain === $plyrCurrentHostNormalized;
      };

      $plyrWebsiteMatchesCurrentRequest = function (Website $website) use ($plyrDomainMatchesHost, $plyrCurrentPath) {
          if (! blank($website->domain) && $plyrDomainMatchesHost($website->domain)) {
              return true;
          }

          $pathSlug = strtolower(trim((string) $plyrCurrentPath, '/'));

          if ($pathSlug === '') {
              return false;
          }

          $websiteSlug = strtolower(trim((string) $website->slug, '/'));
          $websiteNameSlug = \Illuminate\Support\Str::slug((string) $website->name);

          return ($websiteSlug && $websiteSlug === $pathSlug)
              || ($websiteNameSlug && $websiteNameSlug === $pathSlug);
      };

      /*
      * If this partial is included inside a rendered player website template,
      * PublicWebsiteController already passes the active Website as $website.
      * Trust that variable first so custom domains and /slug player cards always
      * hide the normal header navigation, even if request/domain matching changes.
      */
      $plyrRenderedWebsite = (isset($website) && $website instanceof Website) ? $website : null;
      $plyrControllerSaysOwnerCanSeeLocker = (bool) ($showPlyrNavigation ?? false);

      $plyrOwnedWebsites = collect();
      $plyrWebsite = null;
      $plyrWebsiteUrl = null;

      if ($plyrLoggedIn && $plyrUser && class_exists(\App\Models\Website::class)) {
          try {
              if (method_exists($plyrUser, 'websites')) {
                  $relationshipResult = $plyrUser->websites()
                      ->where('is_active', true)
                      ->where('is_published', true)
                      ->latest('updated_at')
                      ->get();

                  $plyrOwnedWebsites = collect($relationshipResult);
              } elseif (method_exists($plyrUser, 'website')) {
                  $relationshipResult = $plyrUser->website();

                  if (method_exists($relationshipResult, 'where')) {
                      $relationshipResult = $relationshipResult
                          ->where('is_active', true)
                          ->where('is_published', true)
                          ->latest('updated_at')
                          ->get();
                  } elseif (method_exists($relationshipResult, 'getResults')) {
                      $relationshipResult = $relationshipResult->getResults();
                  }

                  $plyrOwnedWebsites = $relationshipResult instanceof \Illuminate\Support\Collection
                      ? $relationshipResult
                      : collect($relationshipResult ? [$relationshipResult] : []);
              }
          } catch (\Throwable $e) {
              $plyrOwnedWebsites = collect();
          }

          if ($plyrOwnedWebsites->isEmpty()) {
              $plyrOwnedWebsites = \App\Models\Website::query()
                  ->where('user_id', $plyrUser->id)
                  ->where('is_active', true)
                  ->where('is_published', true)
                  ->latest('updated_at')
                  ->get();
          }

          $plyrWebsite = $plyrOwnedWebsites->first(fn (Website $website) => $plyrWebsiteMatchesCurrentRequest($website))
              ?: $plyrOwnedWebsites->first();

          if ($plyrWebsite) {
              if (! blank($plyrWebsite->domain)) {
                  $domain = $plyrNormalizeDomain($plyrWebsite->domain);
                  $plyrWebsiteUrl = $domain ? 'https://' . $domain : null;
              } elseif (! blank($plyrWebsite->slug)) {
                  $plyrWebsiteUrl = url('/' . ltrim($plyrWebsite->slug, '/'));
              } elseif (! blank($plyrWebsite->name)) {
                  $plyrWebsiteUrl = url('/' . \Illuminate\Support\Str::slug($plyrWebsite->name));
              }
          }
      }

      $plyrViewedWebsite = $plyrRenderedWebsite;

      if (class_exists(\App\Models\Website::class)) {
          // First detect the logged-in player's own website from their User -> Website relationship.
          // This is the important custom-domain path for player-owned domains such as selinpehlivan.com.
          if ($plyrLoggedIn && $plyrOwnedWebsites->isNotEmpty()) {
              $plyrViewedWebsite = $plyrOwnedWebsites->first(fn (Website $website) => $plyrWebsiteMatchesCurrentRequest($website));
          }

          // Custom-domain player site detection for public visits and other players' domains.
          if (! $plyrViewedWebsite && ! $plyrOnMainPlyrSite) {
              $plyrViewedWebsite = \App\Models\Website::query()
                  ->where('is_active', true)
                  ->where('is_published', true)
                  ->whereNotNull('domain')
                  ->get()
                  ->first(fn (Website $website) => $plyrDomainMatchesHost($website->domain));
          }

          // Path-based player site detection for main-domain URLs like /selin-pehlivan.
          if (! $plyrViewedWebsite && ! $plyrOnAdmin && $plyrCurrentPath !== '' && ! in_array($plyrCurrentPath, $plyrReservedPaths, true)) {
              $pathSlug = strtolower($plyrCurrentPath);
              $plyrViewedWebsite = \App\Models\Website::query()
                  ->where('is_active', true)
                  ->where('is_published', true)
                  ->where(function ($query) use ($pathSlug) {
                      $query->whereRaw('LOWER(slug) = ?', [$pathSlug]);
                  })
                  ->first();

              if (! $plyrViewedWebsite) {
                  $plyrViewedWebsite = \App\Models\Website::query()
                      ->where('is_active', true)
                      ->where('is_published', true)
                      ->get()
                      ->first(function (Website $website) use ($pathSlug) {
                          return \Illuminate\Support\Str::slug($website->name) === $pathSlug;
                      });
              }
          }
      }

      $plyrOnPlayerWebsite = in_array($plyrActivePage, ['website', 'player', 'player-website'], true)
          || (bool) $plyrViewedWebsite
          || (bool) $plyrRenderedWebsite;

      // v10.38: ownership is determined from the actual Website record only.
      // Do not let a generic controller flag make Locker Room appear on another
      // player's public PLYRCARD.
      $plyrOwnsViewedWebsite = ($plyrLoggedIn && $plyrUser && $plyrViewedWebsite && ((int) $plyrViewedWebsite->user_id === (int) $plyrUser->id))
          || ($plyrLoggedIn && $plyrUser && $plyrRenderedWebsite && ((int) $plyrRenderedWebsite->user_id === (int) $plyrUser->id));

      // Final fallback for custom-domain templates where activePage is passed but the request was not matched earlier.
      if (! $plyrOwnsViewedWebsite && $plyrOnPlayerWebsite && $plyrLoggedIn && $plyrOwnedWebsites->isNotEmpty()) {
          $plyrOwnsViewedWebsite = (bool) $plyrOwnedWebsites->first(fn (Website $website) => $plyrWebsiteMatchesCurrentRequest($website));
      }

      // Player websites use only the pull-up control; Admin must never render it.
      $plyrPullUpOnly = $plyrPullUpOnly ?? $plyrOnPlayerWebsite;
      $plyrHideHeaderNavigation = $plyrOnPlayerWebsite;

      /*
      * v10.39 visibility rules:
      * - /admin and /admin/* are the only places where the pull-up is suppressed.
      * - Main PLYRCARD pages always render the pull-up: Locker Room when signed in,
      *   Get Started when signed out.
      * - A player website renders Locker Room only for the authenticated owner of THAT website.
      * - Another player's website must not expose the signed-in player's Locker Room.
      */
      if ($plyrOnAdmin) {
          $plyrShouldRenderPullup = false;
      } elseif ($plyrOnPlayerWebsite) {
          $plyrShouldRenderPullup = $plyrLoggedIn && $plyrOwnsViewedWebsite;
      } else {
          $plyrShouldRenderPullup = true;
      }


      /*
      * Club / Team landing pages for Locker Room.
      * Club uses the authenticated player's club_id relationship.
      * Team uses users.team_name + club_id to stay compatible with the current schema.
      */
      $plyrClub = $plyrLoggedIn && $plyrUser && method_exists($plyrUser, 'club')
          ? $plyrUser->club
          : null;

      $plyrClubLandingUrl = null;
      $plyrClubLandingName = $plyrClub?->name ?: 'My Club';

      if (
          $plyrClub
          && ($plyrClub->has_landing_page ?? false)
          && ($plyrClub->landing_page_is_published ?? false)
          && filled($plyrClub->landing_page_slug ?? null)
          && \Illuminate\Support\Facades\Route::has('clubs.landing')
      ) {
          $plyrClubLandingUrl = route('clubs.landing', $plyrClub->landing_page_slug);
      }

      $plyrTeam = null;
      $plyrTeamLandingUrl = null;
      $plyrTeamLandingName = 'My Team';

      if ($plyrLoggedIn && $plyrUser && filled($plyrUser->team_name ?? null) && class_exists(\App\Models\Team::class)) {
          try {
              $plyrTeam = \App\Models\Team::query()
                  ->where('name', $plyrUser->team_name)
                  ->when($plyrUser->club_id ?? null, fn ($query) => $query->where('club_id', $plyrUser->club_id))
                  ->first();

              $plyrTeamLandingName = $plyrTeam?->name ?: $plyrUser->team_name ?: 'My Team';

              if (
                  $plyrTeam
                  && ($plyrTeam->has_landing_page ?? false)
                  && ($plyrTeam->landing_page_is_published ?? false)
                  && filled($plyrTeam->landing_page_slug ?? null)
                  && \Illuminate\Support\Facades\Route::has('teams.landing')
              ) {
                  $plyrTeamLandingUrl = route('teams.landing', $plyrTeam->landing_page_slug);
              }
          } catch (\Throwable $e) {
              $plyrTeam = null;
              $plyrTeamLandingUrl = null;
              $plyrTeamLandingName = $plyrUser->team_name ?: 'My Team';
          }
      }

      $plyrTabLabel = $plyrLoggedIn ? 'Locker Room' : 'GET STARTED';
      $plyrWebsiteActionLabel = 'Visit my Website';
      $plyrWebsiteOwnerVisitUrl = $plyrWebsiteUrl;

      // Custom domains cannot see the auth cookie from plyrcard.com/admin directly.
      // This owner-visit route creates a short-lived bridge token, redirects to the custom domain,
      // and signs the user in on that custom-domain host so Locker Room can render there too.
      if ($plyrLoggedIn && $plyrWebsite && \Illuminate\Support\Facades\Route::has('locker-room.website.visit')) {
          $plyrWebsiteOwnerVisitUrl = route('locker-room.website.visit', $plyrWebsite);
      }

      $plyrWebsiteActionHref = $plyrWebsiteOwnerVisitUrl ?: '#';
      $plyrWebsiteActionTarget = null;
      $plyrWebsiteActionDisabled = ! $plyrWebsiteUrl;

      $plyrSupportEmail = 'support@plyrcard.com';
      $plyrPhoneDisplay = '+1 571-888-0852';
      $plyrPhoneHref = '+15718880852';
      $plyrMainShareUrl = 'https://plyrcard.com';
      $plyrWebsiteShareUrl = $plyrWebsiteUrl ?: null;
      $plyrPlayerCardShareUrl = $plyrWebsiteUrl;
      if ($plyrOnPlayerWebsite && $plyrOwnsViewedWebsite) {
          $plyrPlayerCardShareUrl = $plyrRequestUrl;
      }
      $plyrHasShareablePlyrCard = filled($plyrPlayerCardShareUrl);
      $plyrLogoutAction = url('/admin/logout');

      $normalizePlayerUrl = function ($value, ?string $prefix = null) {
          $value = trim((string) $value);
          if ($value === '') {
              return null;
          }

          if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
              return $value;
          }

          $value = ltrim($value, '@/');

          return $prefix ? rtrim($prefix, '/') . '/' . $value : 'https://' . $value;
      };

      $plyrFacebookUrl = $normalizePlayerUrl($plyrUser->facebook_url ?? $plyrUser->facebook ?? '', 'https://www.facebook.com') ?: null;
      $plyrInstagramUrl = $normalizePlayerUrl($plyrUser->instagram_url ?? $plyrUser->instagram ?? $plyrUser->ig_handle ?? '', 'https://www.instagram.com') ?: null;
      $plyrXUrl = $normalizePlayerUrl($plyrUser->x_url ?? $plyrUser->twitter_url ?? $plyrUser->twitter ?? $plyrUser->x_handle ?? '', 'https://x.com') ?: null;
      $plyrYouTubeUrl = $normalizePlayerUrl($plyrUser->youtube_url ?? $plyrUser->youtube ?? $plyrUser->yt_url ?? '', 'https://www.youtube.com') ?: null;

      $plyrCompanyFacebookUrl = 'https://www.facebook.com/plyrcard';
      $plyrCompanyInstagramUrl = 'https://www.instagram.com/plyrcard/';
      $plyrCompanyXUrl = 'https://x.com/plyrcard';
      $plyrCompanyYouTubeUrl = 'https://www.youtube.com/@plyrcard';

      $plyrProfileUpdateAction = \Illuminate\Support\Facades\Route::has('locker-room.profile.update')
          ? route('locker-room.profile.update')
          : '#';
      $plyrScheduleStoreAction = \Illuminate\Support\Facades\Route::has('locker-room.schedule.store')
          ? route('locker-room.schedule.store')
          : '#';
      $plyrSettingsUpdateAction = \Illuminate\Support\Facades\Route::has('locker-room.settings.update')
          ? route('locker-room.settings.update')
          : '#';
      $plyrSupportStoreAction = \Illuminate\Support\Facades\Route::has('locker-room.support.store')
          ? route('locker-room.support.store')
          : '#';
      $plyrReferralStoreAction = \Illuminate\Support\Facades\Route::has('locker-room.referral.store')
          ? route('locker-room.referral.store')
          : '#';
      $plyrBillingUpdateAction = \Illuminate\Support\Facades\Route::has('locker-room.billing.update')
          ? route('locker-room.billing.update')
          : '#';
      $plyrAdditionalServiceStoreAction = \Illuminate\Support\Facades\Route::has('locker-room.additional-service.store')
          ? route('locker-room.additional-service.store')
          : '#';
      $plyrWebsiteSettingsUpdateAction = \Illuminate\Support\Facades\Route::has('locker-room.website-settings.update')
          ? route('locker-room.website-settings.update')
          : '#';
      $plyrPasswordUpdateAction = \Illuminate\Support\Facades\Route::has('locker-room.password.update')
          ? route('locker-room.password.update')
          : '#';

      $plyrArticleSectionType = old('article_section_type', $plyrWebsite->article_section_type ?? 'follow_me');
      $plyrArticleSectionType = in_array($plyrArticleSectionType, ['follow_me', 'calendar'], true)
          ? $plyrArticleSectionType
          : 'follow_me';
      $plyrShouldShowPasswordOverlay = $plyrLoggedIn && $plyrUser && (bool) (($plyrUser->password_change_required ?? false) || session('plyrcard_show_password_overlay'));
      $plyrDrawerLoginAction = \Illuminate\Support\Facades\Route::has('plyrcard.drawer-login')
          ? route('plyrcard.drawer-login')
          : url('/admin/login');
      $plyrPasswordResetRequestAction = \Illuminate\Support\Facades\Route::has('locker-room.password-reset.request')
          ? route('locker-room.password-reset.request')
          : url('/admin/password-reset/request');

      $plyrEditableProfileFields = [
          'first_name', 'last_name', 'phone', 'street', 'city', 'state', 'country',
          'sport', 'position', 'jersey_number', 'year', 'gender', 'birth', 'gpa', 'height', 'weight',
          'player_bio', 'academic_accolades', 'sports_accolades', 'ig_handle', 'x_handle', 'yt_url',
          'featured_video_url', 'featured_video_urls', 'press', 'parent', 'parent_phone',
          'sec_parent', 'sec_parent_phone', 'club_coach', 
          'club_coach_phone', 'natl_coach', 'natl_coach_phone', 'tech_trainer',
          'tech_trainer_phone', 'snc_trainer', 'snc_trainer_phone',
      ];

      $plyrSportOptions = [
          'basketball' => 'Basketball',
          'volleyball' => 'Volleyball',
          'football' => 'Football',
          'baseball' => 'Baseball',
          'softball' => 'Softball',
          'soccer' => 'Soccer',
          'tennis' => 'Tennis',
          'badminton' => 'Badminton',
          'table_tennis' => 'Table Tennis',
          'track_and_field' => 'Track and Field',
          'swimming' => 'Swimming',
          'boxing' => 'Boxing',
          'martial_arts' => 'Martial Arts',
      ];

      $plyrPositionOptionsBySport = [
          'basketball' => ['point_guard' => 'Point Guard', 'shooting_guard' => 'Shooting Guard', 'small_forward' => 'Small Forward', 'power_forward' => 'Power Forward', 'center' => 'Center'],
          'volleyball' => ['setter' => 'Setter', 'outside_hitter' => 'Outside Hitter', 'opposite_hitter' => 'Opposite Hitter', 'middle_blocker' => 'Middle Blocker', 'libero' => 'Libero', 'defensive_specialist' => 'Defensive Specialist'],
          'football' => ['quarterback' => 'Quarterback', 'running_back' => 'Running Back', 'wide_receiver' => 'Wide Receiver', 'tight_end' => 'Tight End', 'offensive_line' => 'Offensive Line', 'defensive_line' => 'Defensive Line', 'linebacker' => 'Linebacker', 'cornerback' => 'Cornerback', 'safety' => 'Safety', 'kicker' => 'Kicker', 'punter' => 'Punter'],
          'baseball' => ['pitcher' => 'Pitcher', 'catcher' => 'Catcher', 'first_base' => 'First Base', 'second_base' => 'Second Base', 'third_base' => 'Third Base', 'shortstop' => 'Shortstop', 'left_field' => 'Left Field', 'center_field' => 'Center Field', 'right_field' => 'Right Field', 'designated_hitter' => 'Designated Hitter'],
          'softball' => ['pitcher' => 'Pitcher', 'catcher' => 'Catcher', 'first_base' => 'First Base', 'second_base' => 'Second Base', 'third_base' => 'Third Base', 'shortstop' => 'Shortstop', 'left_field' => 'Left Field', 'center_field' => 'Center Field', 'right_field' => 'Right Field'],
          'soccer' => ['goalkeeper' => 'Goalkeeper', 'defender' => 'Defender', 'center_back' => 'Center Back', 'full_back' => 'Full Back', 'wing_back' => 'Wing Back', 'midfielder' => 'Midfielder', 'defensive_midfielder' => 'Defensive Midfielder', 'central_midfielder' => 'Central Midfielder', 'attacking_midfielder' => 'Attacking Midfielder', 'winger' => 'Winger', 'forward' => 'Forward', 'striker' => 'Striker'],
          'tennis' => ['singles' => 'Singles', 'doubles' => 'Doubles'],
          'badminton' => ['singles' => 'Singles', 'doubles' => 'Doubles', 'mixed_doubles' => 'Mixed Doubles'],
          'table_tennis' => ['singles' => 'Singles', 'doubles' => 'Doubles', 'mixed_doubles' => 'Mixed Doubles'],
          'track_and_field' => ['sprinter' => 'Sprinter', 'middle_distance' => 'Middle Distance', 'long_distance' => 'Long Distance', 'hurdler' => 'Hurdler', 'jumper' => 'Jumper', 'thrower' => 'Thrower', 'relay_runner' => 'Relay Runner', 'decathlete' => 'Decathlete', 'heptathlete' => 'Heptathlete'],
          'swimming' => ['freestyle' => 'Freestyle', 'backstroke' => 'Backstroke', 'breaststroke' => 'Breaststroke', 'butterfly' => 'Butterfly', 'individual_medley' => 'Individual Medley', 'relay' => 'Relay'],
          'boxing' => ['flyweight' => 'Flyweight', 'bantamweight' => 'Bantamweight', 'featherweight' => 'Featherweight', 'lightweight' => 'Lightweight', 'welterweight' => 'Welterweight', 'middleweight' => 'Middleweight', 'light_heavyweight' => 'Light Heavyweight', 'heavyweight' => 'Heavyweight'],
          'martial_arts' => ['lightweight' => 'Lightweight', 'welterweight' => 'Welterweight', 'middleweight' => 'Middleweight', 'heavyweight' => 'Heavyweight', 'striker' => 'Striker', 'grappler' => 'Grappler', 'all_rounder' => 'All-Rounder'],
      ];

      $plyrSelectedPositions = $plyrLoggedIn && $plyrUser
          ? (is_array($plyrUser->position ?? null)
              ? $plyrUser->position
              : collect(explode(',', (string) ($plyrUser->position ?? '')))->map(fn ($item) => trim($item))->filter()->values()->all())
          : [];

      $plyrPositionLabelToValue = collect($plyrPositionOptionsBySport)
          ->flatMap(fn ($positionOptions) => collect($positionOptions)->mapWithKeys(fn ($label, $value) => [\Illuminate\Support\Str::lower($label) => $value]))
          ->all();

      $plyrSelectedPositions = collect($plyrSelectedPositions)
          ->map(function ($position) use ($plyrPositionLabelToValue) {
              $position = trim((string) $position);

              return $plyrPositionLabelToValue[\Illuminate\Support\Str::lower($position)] ?? $position;
          })
          ->filter()
          ->unique()
          ->values()
          ->all();


      $plyrCompletedProfileFields = 0;
      if ($plyrLoggedIn && $plyrUser) {
          foreach ($plyrEditableProfileFields as $field) {
              $value = $plyrUser->{$field} ?? null;
              $hasValue = is_array($value)
                  ? count(array_filter($value, fn ($item) => filled($item))) > 0
                  : filled($value);

              if ($hasValue) {
                  $plyrCompletedProfileFields++;
              }
          }
      }

      $plyrProfileCompletion = $plyrLoggedIn && count($plyrEditableProfileFields)
          ? min(100, (int) round(($plyrCompletedProfileFields / count($plyrEditableProfileFields)) * 100))
          : 0;

      $plyrProfileCompletionLabel = match (true) {
          $plyrProfileCompletion >= 100 => 'Outstanding — your PlyrCard is fully complete.',
          $plyrProfileCompletion >= 85 => 'Almost there — your PlyrCard is nearly complete.',
          $plyrProfileCompletion >= 60 => 'Great progress — keep building your PlyrCard.',
          $plyrProfileCompletion >= 30 => 'Good start — add more details to strengthen your PlyrCard.',
          default => 'Let’s get started — complete your PlyrCard to stand out.',
      };

      $plyrWebsiteActionDisabled = ! $plyrWebsiteUrl || $plyrProfileCompletion < 75;
      $plyrVisitWebsitePrompt = 'COMPLETE YOUR PROFILE Your profile is currently ' . $plyrProfileCompletion . '% complete. Complete at least 75% of your profile before previewing your card.';



      $plyrDashboardSections = [
          'Basic Information' => ['first_name' => 'First name', 'last_name' => 'Last name', 'phone' => 'Phone', 'birth' => 'Birth date', 'gender' => 'Sex'],
          'Location' => ['street' => 'Street', 'city' => 'City', 'state' => 'State / Province', 'country' => 'Country'],
          'Athlete Details' => ['sport' => 'Sport', 'position' => 'Position', 'jersey_number' => 'Roster #', 'year' => 'Graduation year', 'gpa' => 'GPA', 'height' => 'Height', 'weight' => 'Weight'],
          'Story & Accolades' => ['player_bio' => 'Player bio', 'academic_accolades' => 'Academic accolades', 'sports_accolades' => 'Sports accolades'],
          'Social & Media' => ['ig_handle' => 'Instagram', 'x_handle' => 'X', 'yt_url' => 'YouTube', 'featured_video_url' => 'Featured video', 'press' => 'Press'],
          'People' => ['parent' => 'Primary parent', 'parent_phone' => 'Parent phone', 'club_coach' => 'Club coach', 'club_coach_phone' => 'Club coach phone'],
      ];

      $plyrMissingProfileSections = [];
      $plyrMissingProfileTotal = 0;
      if ($plyrLoggedIn && $plyrUser) {
          foreach ($plyrDashboardSections as $sectionName => $fields) {
              $missingItems = [];
              foreach ($fields as $field => $label) {
                  $value = $plyrUser->{$field} ?? null;
                  $hasValue = is_array($value)
                      ? count(array_filter($value, fn ($item) => filled($item))) > 0
                      : filled($value);

                  if (! $hasValue) {
                      $missingItems[$field] = $label;
                      $plyrMissingProfileTotal++;
                  }
              }

              if (count($missingItems)) {
                  $plyrMissingProfileSections[$sectionName] = $missingItems;
              }
          }
      }

      $plyrAchievements = [
          ['name' => 'Starter', 'threshold' => 25, 'icon' => 'fa-trophy'],
          ['name' => 'Rising Talent', 'threshold' => 50, 'icon' => 'fa-lock'],
          ['name' => 'Scouted Ready', 'threshold' => 75, 'icon' => 'fa-lock'],
          ['name' => 'PlyrCard Complete', 'threshold' => 100, 'icon' => 'fa-lock'],
      ];
      $plyrUnlockedAchievements = collect($plyrAchievements)->filter(fn ($achievement) => $plyrProfileCompletion >= $achievement['threshold'])->count();

      $plyrCardViews = $plyrUser->card_views ?? $plyrUser->profile_views ?? 0;
      $plyrCardScore = $plyrUser->card_score ?? $plyrProfileCompletion;
      $plyrScheduleCount = 0;
      $plyrUpcomingScheduleCount = 0;
      $plyrSchedules = collect();

      if ($plyrLoggedIn && $plyrUser && class_exists(\App\Models\Schedule::class)) {
          try {
              $scheduleQuery = \App\Models\Schedule::query()
                  ->where(function ($query) use ($plyrUser) {
                      $query->where('created_by_user_id', $plyrUser->id)
                          ->orWhereHas('users', fn ($q) => $q->whereKey($plyrUser->id));
                  });

              $plyrScheduleCount = (clone $scheduleQuery)->count();
              $plyrUpcomingScheduleCount = (clone $scheduleQuery)->where('status', 'upcoming')->count();
              $plyrSchedules = (clone $scheduleQuery)
                  ->latest('game_date')
                  ->latest('game_time')
                  ->limit(8)
                  ->get();
          } catch (\Throwable $e) {
              $plyrScheduleCount = 0;
              $plyrUpcomingScheduleCount = 0;
              $plyrSchedules = collect();
          }
      }

      $plyrBillingInfo = null;
      if ($plyrLoggedIn && $plyrUser && class_exists(\App\Models\BillingInformation::class)) {
          try {
              $plyrBillingInfo = \App\Models\BillingInformation::query()
                  ->where('user_id', $plyrUser->id)
                  ->first();
          } catch (\Throwable $e) {
              $plyrBillingInfo = null;
          }
      }

      $plyrBillingValue = function (string $field, $fallback = '') use ($plyrBillingInfo, $plyrUser) {
          if ($plyrBillingInfo && filled($plyrBillingInfo->{$field} ?? null)) {
              return $plyrBillingInfo->{$field};
          }

          return $fallback;
      };

  @endphp

  <style>
      /* Existing header/navigation styles kept separate so desktop does not get touched by the drawer. */
      :root {
        --plyrcard-nav-height: calc(var(--header-h, 76px) + var(--safe-top, 0px));
        --plyr-accent: #FF5C35;
        --plyr-font: 'Antonio', 'Arial Narrow', Impact, sans-serif;
      }

      #site-header.plyrcard-site-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 9999 !important;
        height: var(--plyrcard-nav-height) !important;
        min-height: calc(76px + var(--safe-top, 0px)) !important;
        padding: var(--safe-top, 0px) 24px 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        background: transparent !important;
        border-bottom: 1px solid transparent !important;
        transition: background 0.3s var(--ease-out, ease), border-color 0.3s var(--ease-out, ease), backdrop-filter 0.3s var(--ease-out, ease) !important;
      }

      #site-header.plyrcard-site-header.is-pullup-only,
      #mobile-nav.plyrcard-mobile-nav.is-pullup-only {
        display: none !important;
      }

      /* Hide only the normal header/nav on player website pages like /player-name.
        The pull-up Locker Room drawer/tab remains unchanged. */
      #site-header.plyrcard-site-header.is-player-website-header-hidden,
      #mobile-nav.plyrcard-mobile-nav.is-player-website-header-hidden {
        display: none !important;
      }

      /* Extra guard for player-card pages rendered by PublicWebsiteController. */
      body:has(#plyrcard-action-drawer) #site-header.plyrcard-site-header.is-player-website-header-hidden,
      body:has(#plyrcard-action-drawer) #mobile-nav.plyrcard-mobile-nav.is-player-website-header-hidden {
        display: none !important;
      }

      #site-header.plyrcard-site-header.scrolled {
        background: rgba(13,13,13,0.92) !important;
        border-bottom-color: rgba(255,255,255,0.07) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
      }

      #site-header.plyrcard-site-header .logo-wrap {
        display: flex !important;
        align-items: center !important;
        height: 50px !important;
        flex: 0 0 auto !important;
        gap: 0 !important;
      }

      #site-header.plyrcard-site-header .logo-wrap img {
        height: 32px !important;
        width: auto !important;
        object-fit: contain !important;
        display: block !important;
      }

      #site-header.plyrcard-site-header .desktop-nav {
        margin-left: auto !important;
        display: none !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: clamp(30px, 3vw, 48px) !important;
        font-family: var(--font-display, var(--plyr-font)) !important;
        font-size: clamp(22px, 1.45vw, 30px) !important;
        line-height: 1 !important;
        font-weight: 800 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        white-space: nowrap !important;
      }

      #site-header.plyrcard-site-header .desktop-nav a {
        font: inherit !important;
        line-height: 1 !important;
        letter-spacing: inherit !important;
        text-transform: inherit !important;
        color: rgba(255,255,255,0.72) !important;
        text-decoration: none !important;
        padding: 8px 0 !important;
        margin: 0 !important;
        white-space: nowrap !important;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        text-shadow: 0 2px 18px rgba(0,0,0,0.28) !important;
        transition: color 0.2s ease, transform 0.2s ease, background 0.2s ease !important;
      }

      #site-header.plyrcard-site-header .desktop-nav a:hover,
      #site-header.plyrcard-site-header .desktop-nav a.active { color: var(--white, #fff) !important; }

      #site-header.plyrcard-site-header .desktop-nav .desktop-nav-cta {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 58px !important;
        padding: 18px 28px !important;
        border-radius: var(--radius-btn, 9999px) !important;
        background: var(--accent, #ff5c35) !important;
        color: var(--white, #fff) !important;
        box-shadow: 0 14px 34px rgba(255,92,53,0.28) !important;
      }

      #site-header.plyrcard-site-header .menu-btn {
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 5px !important;
        width: 44px !important;
        height: 44px !important;
        margin: 0 -6px 0 0 !important;
        padding: 10px 6px !important;
        background: transparent !important;
        border: 0 !important;
        cursor: pointer !important;
      }

      #site-header.plyrcard-site-header .menu-btn span {
        display: block !important;
        width: 24px !important;
        height: 2px !important;
        background: var(--white, #fff) !important;
        border-radius: 2px !important;
      }

      #mobile-nav.plyrcard-mobile-nav {
        position: fixed !important;
        top: var(--plyrcard-nav-height) !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 9990 !important;
        background: rgba(13,13,13,0.98) !important;
        padding: 22px 24px calc(24px + var(--safe-bottom, 0px)) !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 16px !important;
        opacity: 0 !important;
        transform: translateY(-120%) !important;
        pointer-events: none !important;
        transition: transform 0.32s var(--ease-out, ease), opacity 0.25s ease !important;
      }

      #mobile-nav.plyrcard-mobile-nav.open {
        opacity: 1 !important;
        transform: translateY(0) !important;
        pointer-events: auto !important;
      }

      #mobile-nav.plyrcard-mobile-nav a,
      #mobile-nav.plyrcard-mobile-nav button {
        font-family: var(--font-display, var(--plyr-font)) !important;
        font-size: 18px !important;
        font-weight: 800 !important;
        line-height: 1 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        color: rgba(255,255,255,0.76) !important;
        text-decoration: none !important;
        padding: 7px 0 !important;
        background: transparent !important;
        border: 0 !important;
        text-align: left !important;
      }

      #mobile-nav.plyrcard-mobile-nav .nav-cta-pill {
        margin-top: 6px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: var(--radius-btn, 9999px) !important;
        background: var(--accent, #ff5c35) !important;
        color: var(--white, #fff) !important;
        padding: 15px 18px !important;
      }

      @media (min-width: 960px) {
        #site-header.plyrcard-site-header .desktop-nav { display: flex !important; }
        #site-header.plyrcard-site-header .menu-btn { display: none !important; }
        #mobile-nav.plyrcard-mobile-nav { display: none !important; }

        /* Keep Locker Room available on desktop everywhere except /admin and /admin/*. */
        @if($plyrOnAdmin)
          #plyrcard-action-drawer,
          .plyrcard-action-drawer {
            display: none !important;
          }
        @endif
      }

      @media (max-width: 767px) {
        #site-header.plyrcard-site-header {
          min-height: calc(64px + var(--safe-top, 0px)) !important;
          padding-left: 20px !important;
          padding-right: 20px !important;
        }
        #site-header.plyrcard-site-header .logo-wrap { height: 46px !important; }
      }

      /* Pull-up navigation only. */
      .plyrcard-action-drawer,
      .plyrcard-action-drawer * {
        box-sizing: border-box !important;
        font-family: var(--plyr-font) !important;
      }

      .plyrcard-action-drawer .fa-solid,
      .plyrcard-action-drawer .fas { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; }
      .plyrcard-action-drawer .fa-regular,
      .plyrcard-action-drawer .far { font-family: "Font Awesome 6 Free" !important; font-weight: 400 !important; }
      .plyrcard-action-drawer .fa-brands,
      .plyrcard-action-drawer .fab { font-family: "Font Awesome 6 Brands" !important; font-weight: 400 !important; }

      .plyrcard-action-drawer {
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        z-index: 100000 !important;
        color: #fff !important;
        pointer-events: none !important;
      }

      .plyrcard-action-drawer.is-open { pointer-events: auto !important; }

      .plyrcard-drawer-scrim {
        position: fixed !important;
        inset: 0 !important;
        background: rgba(0,0,0,.44) !important;
        backdrop-filter: blur(3px) !important;
        -webkit-backdrop-filter: blur(3px) !important;
        opacity: 0 !important;
        pointer-events: none !important;
        transition: opacity .2s ease !important;
      }

      .plyrcard-action-drawer.is-open .plyrcard-drawer-scrim { opacity: 1 !important; pointer-events: auto !important; }

      .plyrcard-drawer-panel {
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        margin: 0 !important;
        padding: 0 !important;
        max-height: min(82dvh, 620px) !important;
        background: #050505 !important;
        border-radius: 17px 17px 0 0 !important;
        overflow: hidden !important;
        box-shadow: 0 -18px 46px rgba(0,0,0,.5) !important;
        transform: translateY(100%) !important;
        transition: transform .28s cubic-bezier(.2,.8,.2,1) !important;
        pointer-events: auto !important;
      }

      .plyrcard-action-drawer.is-open .plyrcard-drawer-panel { transform: translateY(0) !important; }

      .plyrcard-drawer-handle {
        position: absolute !important;
        top: 8px !important;
        left: 50% !important;
        width: 58px !important;
        height: 5px !important;
        border-radius: 999px !important;
        background: rgba(0,0,0,.22) !important;
        transform: translateX(-50%) !important;
        z-index: 2 !important;
      }

      .plyrcard-drawer-head {
        min-height: 56px !important;
        padding: 16px 12px 10px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 8px !important;
        background: #fff !important;
        color: #050505 !important;
        border-radius: 17px 17px 0 0 !important;
      }

      .plyrcard-drawer-title-row,
      .plyrcard-user-line,
      .plyrcard-drawer-actions {
        display: flex !important;
        align-items: center !important;
        gap: 7px !important;
        min-width: 0 !important;
      }

      .plyrcard-main-title,
      .plyrcard-section-title {
        margin: 0 !important;
        font-size: 15px !important;
        line-height: 1 !important;
        font-weight: 900 !important;
        color: #050505 !important;
        white-space: nowrap !important;
      }

      .plyrcard-plan-badge {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: 20px !important;
        padding: 0 7px !important;
        border-radius: 999px !important;
        background: var(--plyr-accent) !important;
        color: #fff !important;
        font-size: 9px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        text-transform: uppercase !important;
        max-width: 78px !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
      }

      .plyrcard-signout-form { margin: 0 !important; display: inline-flex !important; }

      .plyrcard-signout-btn,
      .plyrcard-drawer-close,
      .plyrcard-drawer-back {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: 0 !important;
        cursor: pointer !important;
        text-decoration: none !important;
        line-height: 1 !important;
      }

      .plyrcard-signout-btn {
        gap: 5px !important;
        height: 26px !important;
        padding: 0 8px !important;
        border-radius: 999px !important;
        background: #050505 !important;
        color: #fff !important;
        font-size: 11px !important;
        font-weight: 900 !important;
      }

      .plyrcard-drawer-close,
      .plyrcard-drawer-back {
        min-width: 28px !important;
        height: 28px !important;
        padding: 0 !important;
        background: transparent !important;
        color: #050505 !important;
        font-size: 19px !important;
      }

      .plyrcard-drawer-back { gap: 5px !important; min-width: auto !important; font-size: 17px !important; font-weight: 900 !important; }

      .plyrcard-drawer-body {
        padding: 8px 10px 76px !important;
        max-height: calc(min(82dvh, 620px) - 56px) !important;
        overflow-y: auto !important;
        background: #050505 !important;
        color: #fff !important;
      }

      .plyrcard-drawer-view {
        display: none !important;
        opacity: 0 !important;
        transform: translateY(10px) scale(.985) !important;
        transform-origin: top center !important;
      }
      .plyrcard-drawer-view.is-active {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        transform: none !important;
        filter: none !important;
        animation: plyrcardViewIn .26s cubic-bezier(.2,.8,.2,1) both !important;
      }
      .plyrcard-drawer-view.is-active .plyrcard-nav-group {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      .plyrcard-drawer-view.is-active .plyrcard-drawer-grid {
        display: grid !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      .plyrcard-drawer-view.is-active .plyrcard-drawer-card {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      .plyrcard-drawer-view.is-active .plyrcard-drawer-card.is-disabled,
      .plyrcard-drawer-view.is-active .plyrcard-drawer-card[aria-disabled="true"] {
        opacity: .46 !important;
      }
      .plyrcard-drawer-panel.is-switching .plyrcard-drawer-view.is-active {
        animation: plyrcardViewIn .26s cubic-bezier(.2,.8,.2,1) both !important;
      }
      @keyframes plyrcardViewIn {
        from { opacity: 0; transform: translateY(12px) scale(.985); filter: blur(2px); }
        to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
      }
      @keyframes plyrcardCardIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
      }
      .plyrcard-drawer-view.is-active .plyrcard-form-card,
      .plyrcard-drawer-view.is-active .plyrcard-mini-panel,
      .plyrcard-drawer-view.is-active .plyrcard-offer-card {
        animation: plyrcardCardIn .3s cubic-bezier(.2,.8,.2,1) both !important;
      }

      .plyrcard-nav-group + .plyrcard-nav-group { margin-top: 8px !important; }
      .plyrcard-nav-group-title {
        display: block !important;
        margin: 0 0 4px !important;
        color: rgba(255,255,255,.62) !important;
        font-size: 12px !important;
        line-height: 1 !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        letter-spacing: .03em !important;
      }

      .plyrcard-drawer-grid {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 7px !important;
      }

      .plyrcard-drawer-card {
        min-width: 0 !important;
        min-height: 58px !important;
        padding: 7px 5px 6px !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 5px !important;
        border: 0 !important;
        border-radius: 7px !important;
        background: #fff !important;
        color: #050505 !important;
        box-shadow: 0 4px 10px rgba(0,0,0,.24) !important;
        text-align: center !important;
        text-decoration: none !important;
        cursor: pointer !important;
        font: inherit !important;
      }

      .plyrcard-drawer-card.is-accent { background: var(--plyr-accent) !important; color: #fff !important; }
      .plyrcard-drawer-card.is-disabled,
      .plyrcard-drawer-card[aria-disabled="true"] { opacity: .46 !important; pointer-events: none !important; cursor: not-allowed !important; }
      .plyrcard-drawer-card.is-active-page { background: rgba(255,255,255,.42) !important; color: #050505 !important; }

      .plyrcard-menu-icon { font-size: 15px !important; line-height: 1 !important; color: currentColor !important; }
      .plyrcard-drawer-card span { display: block !important; color: currentColor !important; font-size: 11px !important; line-height: .98 !important; font-weight: 850 !important; }
      .plyrcard-drawer-card[data-plyrcard-section="support"] {
        pointer-events: auto !important;
        position: relative !important;
        z-index: 3 !important;
      }
      .plyrcard-drawer-card[data-plyrcard-section="support"] * {
        pointer-events: none !important;
      }

      .plyrcard-drawer-tab {
        position: fixed !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 210px !important;
        height: 60px !important;
        padding: 0 16px 0 48px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 9px !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: var(--plyr-accent) !important;
        color: #fff !important;
        font-size: 21px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        cursor: pointer !important;
        pointer-events: auto !important;
        clip-path: polygon(36px 0, 100% 0, 100% 100%, 0 100%) !important;
      }

      .plyrcard-drawer-tab i { font-size: 14px !important; transition: transform .25s ease !important; }
      .plyrcard-action-drawer.is-open .plyrcard-drawer-tab i { transform: rotate(180deg) !important; }

      .plyrcard-drawer-section-divider { margin: 13px 0 12px !important; height: 1px !important; background: rgba(255,255,255,.16) !important; }
      .plyrcard-social-row { display: flex !important; align-items: center !important; gap: 22px !important; color: #fff !important; }
      .plyrcard-social-label { font-size: 21px !important; font-weight: 850 !important; line-height: 1 !important; }
      .plyrcard-social-row a { color: #fff !important; text-decoration: none !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }
      .plyrcard-social-row i { font-size: 23px !important; }

      .plyrcard-form-card,
      .plyrcard-mini-panel {
        border-radius: 16px !important;
        background: linear-gradient(180deg, #ffffff 0%, #f7f7f7 100%) !important;
        color: #111 !important;
        padding: 14px !important;
        box-shadow: 0 10px 26px rgba(0,0,0,.26) !important;
        border: 1px solid rgba(255,255,255,.75) !important;
      }

      .plyrcard-form-stack { display: grid !important; gap: 9px !important; }
      .plyrcard-input-label { display: grid !important; gap: 6px !important; color: rgba(0,0,0,.52) !important; font-size: 11px !important; font-weight: 900 !important; text-transform: uppercase !important; letter-spacing: .035em !important; }
      .plyrcard-input-wrap { position: relative !important; display: block !important; }
      .plyrcard-input-wrap > i { position: absolute !important; left: 12px !important; top: 50% !important; transform: translateY(-50%) !important; color: rgba(0,0,0,.8) !important; font-size: 13px !important; }
      .plyrcard-input-wrap.textarea > i { top: 15px !important; transform: none !important; }

      .plyrcard-drawer-input,
      .plyrcard-drawer-textarea,
      .plyrcard-drawer-select {
        width: 100% !important;
        min-height: 40px !important;
        border-radius: 12px !important;
        border: 1px solid rgba(0,0,0,.075) !important;
        background: #fff !important;
        color: #111 !important;
        padding: 9px 11px 9px 35px !important;
        font-size: 13px !important;
        font-weight: 750 !important;
        outline: none !important;
        box-shadow: inset 0 1px 0 rgba(0,0,0,.02), 0 1px 0 rgba(255,255,255,.75) !important;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease !important;
      }
      .plyrcard-drawer-input:focus,
      .plyrcard-drawer-textarea:focus,
      .plyrcard-drawer-select:focus {
        border-color: rgba(255,92,53,.55) !important;
        box-shadow: 0 0 0 3px rgba(255,92,53,.12) !important;
      }

      .plyrcard-drawer-textarea { min-height: 84px !important; resize: vertical !important; padding-top: 11px !important; }
      .plyrcard-clean-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; flex-wrap: wrap !important; }
      .plyrcard-text-link { border: 0 !important; background: transparent !important; color: #111 !important; padding: 0 !important; font: inherit !important; text-decoration: underline !important; cursor: pointer !important; }
      .plyrcard-subsection-lead { margin: 0 0 12px !important; color: rgba(255,255,255,.72) !important; font-size: 13px !important; line-height: 1.35 !important; font-weight: 650 !important; }
      .plyrcard-mini-title { margin: 0 0 6px !important; color: #111 !important; font-size: 18px !important; line-height: 1 !important; font-weight: 950 !important; }
      .plyrcard-mini-copy { margin: 0 0 13px !important; color: rgba(0,0,0,.58) !important; font-size: 13px !important; line-height: 1.35 !important; font-weight: 650 !important; }

      .plyrcard-toggle-choice-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 8px !important;
        width: 100% !important;
      }
      .plyrcard-toggle-choice {
        min-height: 76px !important;
        border: 1px solid rgba(0,0,0,.08) !important;
        border-radius: 14px !important;
        background: #fff !important;
        color: #111 !important;
        padding: 11px 10px !important;
        display: grid !important;
        grid-template-columns: auto 1fr !important;
        gap: 10px !important;
        align-items: center !important;
        text-align: left !important;
        cursor: pointer !important;
        font: inherit !important;
        box-shadow: 0 6px 16px rgba(0,0,0,.08) !important;
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease, background .16s ease !important;
      }
      .plyrcard-toggle-choice:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 10px 22px rgba(0,0,0,.12) !important;
      }
      .plyrcard-toggle-choice.is-active {
        border-color: rgba(255,92,53,.72) !important;
        background: linear-gradient(180deg, rgba(255,92,53,.13), rgba(255,255,255,1)) !important;
        box-shadow: 0 0 0 3px rgba(255,92,53,.12), 0 10px 22px rgba(0,0,0,.12) !important;
      }
      .plyrcard-toggle-choice-icon {
        width: 34px !important;
        height: 34px !important;
        border-radius: 11px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: rgba(0,0,0,.07) !important;
        color: #111 !important;
        font-size: 15px !important;
      }
      .plyrcard-toggle-choice.is-active .plyrcard-toggle-choice-icon {
        background: var(--plyr-accent) !important;
        color: #fff !important;
      }
      .plyrcard-toggle-choice-title {
        display: block !important;
        color: #111 !important;
        font-size: 13px !important;
        line-height: 1 !important;
        font-weight: 950 !important;
        text-transform: uppercase !important;
      }
      .plyrcard-toggle-choice-copy {
        display: block !important;
        margin-top: 5px !important;
        color: rgba(0,0,0,.56) !important;
        font-size: 11px !important;
        line-height: 1.2 !important;
        font-weight: 700 !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
      }
      @media (max-width: 390px) {
        .plyrcard-toggle-choice-grid { grid-template-columns: 1fr !important; }
      }

      .plyrcard-submit-btn,
      .plyrcard-secondary-btn,
      .plyrcard-copy-btn {
        min-height: 40px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        border: 0 !important;
        border-radius: 10px !important;
        padding: 0 14px !important;
        background: var(--plyr-accent) !important;
        color: #fff !important;
        font-size: 15px !important;
        font-weight: 900 !important;
        text-decoration: none !important;
        cursor: pointer !important;
      }

      .plyrcard-secondary-btn { background: #111 !important; }
      .plyrcard-copy-line { display: grid !important; grid-template-columns: 1fr auto !important; gap: 8px !important; }
      .plyrcard-copy-line input { padding-left: 12px !important; }

      .plyrcard-offer-list { display: grid !important; gap: 9px !important; }
      .plyrcard-offer-card { display: grid !important; grid-template-columns: 52px 1fr auto !important; align-items: center !important; gap: 10px !important; min-height: 74px !important; padding: 10px 14px 10px 10px !important; border-radius: 9px !important; background: #fff !important; color: #050505 !important; text-decoration: none !important; }
      .plyrcard-offer-icon { width: 42px !important; height: 42px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 9px !important; background: #fff !important; box-shadow: 0 3px 9px rgba(0,0,0,.14) !important; }
      .plyrcard-offer-icon i { font-size: 20px !important; color: #050505 !important; }
      .plyrcard-offer-title { margin: 0 0 4px !important; font-size: 18px !important; line-height: 1 !important; font-weight: 900 !important; color: #050505 !important; }
      .plyrcard-offer-copy { margin: 0 !important; color: #303746 !important; font-size: 13px !important; line-height: 1.2 !important; font-weight: 600 !important; }
      .plyrcard-offer-price { text-align: right !important; color: #168bff !important; font-size: 24px !important; font-weight: 950 !important; line-height: .9 !important; white-space: nowrap !important; }
      .plyrcard-offer-price small { display: block !important; margin-top: 5px !important; color: #4d5565 !important; font-size: 10px !important; letter-spacing: .06em !important; text-transform: uppercase !important; }

      .plyrcard-booking-wrap { height: calc(min(82dvh, 620px) - 70px) !important; border-radius: 12px !important; overflow: hidden !important; background: #fff !important; }
      .plyrcard-booking-wrap iframe { display: block !important; width: 100% !important; min-height: 100% !important; border: 0 !important; }

      .plyrcard-drawer-panel.is-support-fullscreen,
      .plyrcard-drawer-panel.is-expanded.is-support-fullscreen {
        height: 100dvh !important;
        max-height: 100dvh !important;
        border-radius: 17px 17px 0 0 !important;
      }

      .plyrcard-drawer-panel.is-support-fullscreen .plyrcard-drawer-body {
        height: calc(100dvh - 56px) !important;
        max-height: calc(100dvh - 56px) !important;
        padding-bottom: 68px !important;
      }

      .plyrcard-support-ticket-wrap {
        height: calc(100dvh - 56px - 76px) !important;
        min-height: 620px !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        background: #fff !important;
        box-shadow: 0 10px 26px rgba(0,0,0,.26) !important;
        border: 1px solid rgba(255,255,255,.65) !important;
      }
      .plyrcard-support-ticket-wrap iframe {
        display: block !important;
        width: 100% !important;
        height: 100% !important;
        min-height: 912px !important;
        border: 0 !important;
        background: #fff !important;
      }
      .plyrcard-support-ticket-loading {
        height: 100% !important;
        min-height: 320px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 10px !important;
        color: #050505 !important;
        font-size: 14px !important;
        font-weight: 900 !important;
        letter-spacing: .04em !important;
        text-transform: uppercase !important;
      }

      .plyrcard-qr-wrap { display: grid !important; gap: 12px !important; place-items: center !important; text-align: center !important; }
      .plyrcard-qr-wrap img { width: 170px !important; height: 170px !important; border-radius: 12px !important; background: #fff !important; padding: 8px !important; }
      .plyrcard-share-options { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px !important; width: 100% !important; }



      .plyrcard-alert {
        display: none !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        margin-bottom: 10px !important;
        padding: 11px 12px !important;
        border-radius: 13px !important;
        background: rgba(12,151,82,.14) !important;
        border: 1px solid rgba(12,151,82,.28) !important;
        color: #0a7f47 !important;
        font-size: 13px !important;
        font-weight: 900 !important;
        line-height: 1.2 !important;
      }
      .plyrcard-alert.is-visible { display: flex !important; animation: plyrcardCardIn .24s cubic-bezier(.2,.8,.2,1) both !important; }
      .plyrcard-alert.is-error { background: rgba(255,92,53,.14) !important; border-color: rgba(255,92,53,.32) !important; color: #c73513 !important; }
      .plyrcard-alert button { border: 0 !important; background: transparent !important; color: inherit !important; cursor: pointer !important; font-size: 16px !important; }

      .plyrcard-submit-btn.is-loading,
      .plyrcard-secondary-btn.is-loading,
      .plyrcard-copy-btn.is-loading {
        opacity: .82 !important;
        cursor: wait !important;
        pointer-events: none !important;
      }
      .plyrcard-btn-spinner {
        width: 15px !important;
        height: 15px !important;
        border-radius: 999px !important;
        border: 2px solid rgba(255,255,255,.38) !important;
        border-top-color: #fff !important;
        display: inline-block !important;
        animation: plyrcardSpin .7s linear infinite !important;
      }
      @keyframes plyrcardSpin { to { transform: rotate(360deg); } }


      .plyrcard-refresh-indicator {
        display: none !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 9px !important;
        margin: 0 0 10px !important;
        padding: 10px 12px !important;
        border-radius: 13px !important;
        background: rgba(255,255,255,.08) !important;
        border: 1px solid rgba(255,255,255,.13) !important;
        color: #fff !important;
        font-size: 12px !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        letter-spacing: .035em !important;
      }
      .plyrcard-refresh-indicator.is-visible { display: flex !important; animation: plyrcardCardIn .22s cubic-bezier(.2,.8,.2,1) both !important; }
      .plyrcard-drawer-panel.is-pulling .plyrcard-refresh-indicator { display: flex !important; }
      .plyrcard-drawer-panel.is-refreshing .plyrcard-refresh-indicator { display: flex !important; }

      .plyrcard-locked-panel {
        display: grid !important;
        grid-template-columns: 42px 1fr auto !important;
        gap: 12px !important;
        align-items: center !important;
        margin: 0 0 12px !important;
        padding: 13px !important;
        border-radius: 16px !important;
        background: rgba(255,92,53,.12) !important;
        border: 1px solid rgba(255,92,53,.35) !important;
        color: #111 !important;
      }
      .plyrcard-locked-icon {
        width: 38px !important;
        height: 38px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 999px !important;
        background: rgba(255,92,53,.16) !important;
        color: var(--plyr-accent) !important;
      }
      .plyrcard-locked-panel strong { display: block !important; font-size: 14px !important; font-weight: 950 !important; text-transform: uppercase !important; line-height: 1 !important; }
      .plyrcard-locked-panel span span { display: block !important; margin-top: 4px !important; color: rgba(0,0,0,.58) !important; font-size: 12px !important; font-weight: 700 !important; line-height: 1.25 !important; }
      .plyrcard-locked-panel .plyrcard-submit-btn { min-height: 38px !important; font-size: 13px !important; padding: 0 12px !important; white-space: nowrap !important; }
      .plyrcard-locked-field { opacity: .54 !important; pointer-events: none !important; }
      .plyrcard-drawer-card.is-locked { opacity: .58 !important; }
      .plyrcard-field-error {
        display: none !important;
        margin-top: 5px !important;
        color: #c73513 !important;
        font-size: 11px !important;
        font-weight: 900 !important;
        line-height: 1.2 !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
      }
      .plyrcard-input-label.has-error .plyrcard-field-error { display: block !important; }
      .plyrcard-input-label.has-error .plyrcard-drawer-input,
      .plyrcard-input-label.has-error .plyrcard-drawer-select,
      .plyrcard-input-label.has-error .plyrcard-drawer-textarea,
      .plyrcard-input-label.has-error .plyrcard-position-trigger {
        border-color: rgba(199,53,19,.65) !important;
        box-shadow: 0 0 0 3px rgba(199,53,19,.12) !important;
      }

      @media (max-width: 520px) {
        .plyrcard-dashboard-summary { grid-template-columns: 1fr !important; text-align: center !important; }
        .plyrcard-missing-list { grid-template-columns: 1fr !important; }
      }

      @media (min-width: 768px) {
        .plyrcard-drawer-panel { max-height: min(82dvh, 620px) !important; }
        .plyrcard-drawer-body { padding-left: 12px !important; padding-right: 12px !important; }
        .plyrcard-drawer-tab { width: 210px !important; }
      }


      /* Drawer feature additions: preserve existing visual language, only extend it. */
      .plyrcard-drawer-panel.is-expanded {
        height: 100dvh !important;
        max-height: 100dvh !important;
        border-radius: 0 !important;
      }
      .plyrcard-drawer-panel.is-expanded .plyrcard-drawer-head { border-radius: 0 !important; }
      .plyrcard-drawer-panel.is-expanded .plyrcard-drawer-body {
        max-height: calc(100dvh - 56px) !important;
        padding-bottom: calc(90px + env(safe-area-inset-bottom, 0px)) !important;
      }
      .plyrcard-stat-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 8px !important;
        margin: 10px 0 12px !important;
      }
      .plyrcard-stat-card {
        min-height: 82px !important;
        border-radius: 14px !important;
        background: #fff !important;
        color: #111 !important;
        padding: 11px 8px !important;
        box-shadow: 0 8px 18px rgba(0,0,0,.22) !important;
        display: grid !important;
        align-content: center !important;
        gap: 5px !important;
        text-align: center !important;
        border: 1px solid rgba(255,255,255,.78) !important;
      }
      .plyrcard-stat-card i { color: var(--plyr-accent) !important; font-size: 16px !important; }
      .plyrcard-stat-value { display: block !important; color: #050505 !important; font-size: 24px !important; font-weight: 950 !important; line-height: .9 !important; }
      .plyrcard-stat-label { display: block !important; color: rgba(0,0,0,.56) !important; font-size: 10px !important; font-weight: 850 !important; line-height: 1.05 !important; text-transform: uppercase !important; letter-spacing: .025em !important; }
      .plyrcard-progress-shell {
        position: relative !important;
        width: 100% !important;
        height: 10px !important;
        border-radius: 999px !important;
        overflow: hidden !important;
        background: rgba(0,0,0,.08) !important;
        margin: 4px 0 12px !important;
      }
      .plyrcard-progress-fill { height: 100% !important; width: var(--value, 0%) !important; border-radius: inherit !important; background: var(--plyr-accent) !important; }
      .plyrcard-dashboard-ring {
        width: 118px !important;
        height: 118px !important;
        border-radius: 999px !important;
        margin: 0 auto 10px !important;
        display: grid !important;
        place-items: center !important;
        background: conic-gradient(var(--plyr-accent) var(--value, 0%), rgba(0,0,0,.08) 0) !important;
        box-shadow: inset 0 0 0 10px #fff, 0 8px 18px rgba(0,0,0,.14) !important;
      }
      .plyrcard-dashboard-ring span { color: #050505 !important; font-size: 26px !important; font-weight: 950 !important; line-height: 1 !important; }

      .plyrcard-dashboard-layout { display: grid !important; gap: 12px !important; }
      .plyrcard-dashboard-summary { display: grid !important; grid-template-columns: 128px 1fr !important; gap: 12px !important; align-items: center !important; }
      .plyrcard-dashboard-panel-title { margin: 0 0 8px !important; color: #111 !important; font-size: 17px !important; font-weight: 950 !important; line-height: 1 !important; }
      .plyrcard-dashboard-grid-two { display: grid !important; grid-template-columns: 1fr !important; gap: 10px !important; }
      .plyrcard-missing-section { border-radius: 14px !important; background: rgba(0,0,0,.035) !important; border: 1px solid rgba(0,0,0,.08) !important; padding: 12px !important; }
      .plyrcard-missing-head { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; margin-bottom: 9px !important; }
      .plyrcard-missing-title { margin: 0 !important; color: #111 !important; font-size: 14px !important; font-weight: 950 !important; line-height: 1 !important; }
      .plyrcard-missing-count { color: rgba(0,0,0,.58) !important; font-size: 11px !important; font-weight: 850 !important; }
      .plyrcard-missing-edit { border: 0 !important; background: transparent !important; color: var(--plyr-accent) !important; font-size: 12px !important; font-weight: 950 !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; gap: 6px !important; }
      .plyrcard-missing-list { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px !important; }
      .plyrcard-missing-item { min-height: 42px !important; display: inline-flex !important; align-items: center !important; gap: 8px !important; border-radius: 10px !important; padding: 9px 10px !important; color: #111 !important; background: rgba(255,92,53,.1) !important; border: 1px solid rgba(255,92,53,.24) !important; font-size: 12px !important; font-weight: 900 !important; }
      .plyrcard-missing-item i { color: var(--plyr-accent) !important; }
      .plyrcard-achievement-list { display: grid !important; gap: 8px !important; }
      .plyrcard-achievement-card { min-height: 56px !important; display: grid !important; grid-template-columns: 30px 1fr !important; align-items: center !important; gap: 8px !important; border-radius: 13px !important; padding: 10px !important; background: rgba(0,0,0,.05) !important; border: 1px solid rgba(0,0,0,.08) !important; color: #111 !important; }
      .plyrcard-achievement-card.is-unlocked { background: rgba(0,170,90,.12) !important; border-color: rgba(0,170,90,.25) !important; }
      .plyrcard-achievement-card i { color: rgba(0,0,0,.45) !important; font-size: 16px !important; text-align: center !important; }
      .plyrcard-achievement-card.is-unlocked i { color: #00a85a !important; }
      .plyrcard-achievement-name { display: block !important; font-size: 13px !important; font-weight: 950 !important; color: #111 !important; line-height: 1 !important; }
      .plyrcard-achievement-meta { display: block !important; margin-top: 4px !important; font-size: 11px !important; font-weight: 750 !important; color: rgba(0,0,0,.55) !important; }
      .plyrcard-upcoming-badge { display: inline-flex !important; align-items: center !important; gap: 6px !important; min-height: 24px !important; border-radius: 999px !important; padding: 0 8px !important; background: rgba(255,92,53,.12) !important; color: var(--plyr-accent) !important; font-size: 10px !important; font-weight: 950 !important; text-transform: uppercase !important; }
      .plyrcard-schedule-list { display: grid !important; gap: 9px !important; }
      .plyrcard-schedule-card {
        display: grid !important;
        grid-template-columns: 44px 1fr auto !important;
        align-items: center !important;
        gap: 10px !important;
        border-radius: 14px !important;
        background: #fff !important;
        color: #111 !important;
        padding: 10px !important;
        box-shadow: 0 8px 18px rgba(0,0,0,.18) !important;
        border: 1px solid rgba(255,255,255,.75) !important;
      }
      .plyrcard-schedule-date {
        width: 42px !important;
        min-width: 42px !important;
        height: 48px !important;
        border-radius: 12px !important;
        display: inline-flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: hidden !important;
        background: #ffffff !important;
        border: 1px solid rgba(255,92,53,.28) !important;
        box-shadow: 0 8px 18px rgba(0,0,0,.12) !important;
        font-family: "Antonio", sans-serif !important;
      }
      .plyrcard-schedule-date-month {
        width: 100% !important;
        padding: 4px 0 3px !important;
        background: var(--plyr-accent) !important;
        color: #ffffff !important;
        font-size: 10px !important;
        font-weight: 950 !important;
        line-height: 1 !important;
        letter-spacing: .08em !important;
        text-align: center !important;
        text-transform: uppercase !important;
      }
      .plyrcard-schedule-date-day {
        flex: 1 !important;
        width: 100% !important;
        color: #050505 !important;
        font-size: 21px !important;
        font-weight: 950 !important;
        line-height: 1 !important;
        letter-spacing: -.02em !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #ffffff !important;
      }
      .plyrcard-schedule-title { margin: 0 !important; color: #111 !important; font-size: 16px !important; line-height: 1 !important; font-weight: 950 !important; }
      .plyrcard-schedule-meta { margin: 5px 0 0 !important; color: rgba(0,0,0,.56) !important; font-size: 12px !important; line-height: 1.2 !important; font-weight: 650 !important; }
      .plyrcard-schedule-badge { display: inline-flex !important; align-items: center !important; min-height: 24px !important; padding: 0 8px !important; border-radius: 999px !important; background: #111 !important; color: #fff !important; font-size: 10px !important; font-weight: 900 !important; text-transform: uppercase !important; white-space: nowrap !important; }
      .plyrcard-empty-state { display: grid !important; gap: 8px !important; place-items: center !important; text-align: center !important; border-radius: 16px !important; background: #fff !important; color: #111 !important; padding: 24px 16px !important; box-shadow: 0 8px 18px rgba(0,0,0,.18) !important; }
      .plyrcard-empty-state i { color: var(--plyr-accent) !important; font-size: 28px !important; }

      .plyrcard-profile-section {
        border-radius: 14px !important;
        background: rgba(255,255,255,.06) !important;
        border: 1px solid rgba(255,255,255,.1) !important;
        padding: 0 !important;
        overflow: hidden !important;
      }
      .plyrcard-profile-section + .plyrcard-profile-section { margin-top: 2px !important; }
      .plyrcard-profile-section summary {
        list-style: none !important;
        cursor: pointer !important;
        min-height: 44px !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 12px 13px !important;
        color: #111 !important;
        background: linear-gradient(180deg,#fff,#f7f7f7) !important;
        font-size: 13px !important;
        line-height: 1 !important;
        font-weight: 950 !important;
        text-transform: uppercase !important;
      }
      .plyrcard-profile-section summary::-webkit-details-marker { display: none !important; }
      .plyrcard-profile-section summary i { color: var(--plyr-accent) !important; }
      .plyrcard-profile-section[open] { padding-bottom: 12px !important; }
      .plyrcard-profile-section[open] summary { margin-bottom: 12px !important; border-bottom: 1px solid rgba(0,0,0,.06) !important; }
      .plyrcard-profile-section > label,
      .plyrcard-profile-section > .plyrcard-profile-grid,
      .plyrcard-profile-section > .plyrcard-mini-copy { margin-left: 12px !important; margin-right: 12px !important; }
      .plyrcard-profile-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0,1fr)) !important; gap: 10px !important; }
      .plyrcard-profile-grid .plyrcard-input-label { min-width: 0 !important; }

      .plyrcard-field-help {
        display: block !important;
        margin: 6px 0 0 !important;
        color: rgba(255,255,255,.72) !important;
        font-size: 12px !important;
        line-height: 1.25 !important;
        font-weight: 750 !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
      }

      .plyrcard-position-combo {
        position: relative !important;
        width: 100% !important;
      }

      .plyrcard-position-trigger {
        width: 100% !important;
        min-height: 40px !important;
        border-radius: 12px !important;
        border: 1px solid rgba(0,0,0,.075) !important;
        background: #fff !important;
        color: #111 !important;
        padding: 7px 34px 7px 37px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 8px !important;
        text-align: left !important;
        cursor: pointer !important;
        box-shadow: inset 0 1px 0 rgba(0,0,0,.02), 0 1px 0 rgba(255,255,255,.75) !important;
      }

      .plyrcard-position-trigger:focus {
        outline: none !important;
        border-color: rgba(255,92,53,.55) !important;
        box-shadow: 0 0 0 3px rgba(255,92,53,.12) !important;
      }

      .plyrcard-position-trigger > i {
        position: absolute !important;
        right: 12px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        color: rgba(0,0,0,.48) !important;
        font-size: 13px !important;
        pointer-events: none !important;
      }

      .plyrcard-position-chips {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 5px !important;
        min-width: 0 !important;
        color: rgba(0,0,0,.48) !important;
        font-size: 13px !important;
        font-weight: 750 !important;
        line-height: 1.15 !important;
      }

      .plyrcard-position-chip {
        min-height: 24px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
        padding: 0 8px !important;
        border-radius: 8px !important;
        background: rgba(255,92,53,.14) !important;
        color: var(--plyr-accent) !important;
        border: 1px solid rgba(255,92,53,.28) !important;
        font-size: 12px !important;
        font-weight: 950 !important;
        white-space: nowrap !important;
      }

      .plyrcard-position-chip button {
        appearance: none !important;
        border: 0 !important;
        background: transparent !important;
        color: inherit !important;
        padding: 0 !important;
        cursor: pointer !important;
        font-size: 12px !important;
        line-height: 1 !important;
      }

      .plyrcard-position-menu {
        position: absolute !important;
        z-index: 20 !important;
        left: 0 !important;
        right: 0 !important;
        top: calc(100% + 6px) !important;
        display: none !important;
        max-height: 224px !important;
        overflow-y: auto !important;
        border-radius: 14px !important;
        background: #fff !important;
        border: 1px solid rgba(0,0,0,.1) !important;
        box-shadow: 0 18px 34px rgba(0,0,0,.28) !important;
        padding: 7px !important;
      }

      .plyrcard-position-combo.is-open .plyrcard-position-menu { display: grid !important; gap: 5px !important; }

      .plyrcard-position-option {
        width: 100% !important;
        min-height: 38px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 8px !important;
        border: 0 !important;
        border-radius: 10px !important;
        background: transparent !important;
        color: #111 !important;
        padding: 8px 10px !important;
        font-size: 13px !important;
        font-weight: 900 !important;
        text-align: left !important;
        cursor: pointer !important;
      }

      .plyrcard-position-option:hover,
      .plyrcard-position-option.is-selected {
        background: rgba(255,92,53,.12) !important;
        color: var(--plyr-accent) !important;
      }

      .plyrcard-position-option i { opacity: 0 !important; font-size: 12px !important; }
      .plyrcard-position-option.is-selected i { opacity: 1 !important; }

      .plyrcard-position-empty {
        padding: 12px 10px !important;
        color: rgba(0,0,0,.5) !important;
        font-size: 13px !important;
        font-weight: 800 !important;
      }



      .plyrcard-upgrade-hero {
        position: relative !important;
        overflow: hidden !important;
        border-radius: 16px !important;
        padding: 18px !important;
        background: radial-gradient(circle at 100% 0%, rgba(255,92,53,.34), transparent 34%), linear-gradient(135deg, rgba(255,92,53,.22), rgba(255,92,53,.08) 48%, rgba(0,0,0,.08)) !important;
        border: 1px solid rgba(255,92,53,.32) !important;
        color: #111 !important;
        display: grid !important;
        gap: 12px !important;
      }

      .plyrcard-upgrade-kicker {
        display: inline-flex !important;
        align-items: center !important;
        gap: 7px !important;
        color: var(--plyr-accent) !important;
        font-size: 10px !important;
        font-weight: 950 !important;
        letter-spacing: .13em !important;
        text-transform: uppercase !important;
        line-height: 1 !important;
      }

      .plyrcard-upgrade-title {
        margin: 0 !important;
        color: #111 !important;
        font-size: clamp(28px, 8vw, 48px) !important;
        line-height: .9 !important;
        font-weight: 950 !important;
        letter-spacing: .02em !important;
        text-transform: uppercase !important;
      }

      .plyrcard-upgrade-title strong { color: var(--plyr-accent) !important; font: inherit !important; }

      .plyrcard-upgrade-note {
        margin: 0 !important;
        color: rgba(0,0,0,.62) !important;
        font-size: 13px !important;
        line-height: 1.3 !important;
        font-weight: 750 !important;
      }

      .plyrcard-upgrade-crown {
        width: 56px !important;
        height: 56px !important;
        border-radius: 999px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: rgba(255,92,53,.16) !important;
        border: 1px solid rgba(255,92,53,.28) !important;
        color: var(--plyr-accent) !important;
        font-size: 22px !important;
        justify-self: end !important;
      }

      .plyrcard-upgrade-card-grid {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 11px !important;
      }

      .plyrcard-plan-card {
        position: relative !important;
        overflow: hidden !important;
        display: grid !important;
        gap: 12px !important;
        min-height: 300px !important;
        padding: 14px !important;
        border-radius: 16px !important;
        background: linear-gradient(180deg, #fff, #f7f7f7) !important;
        color: #111 !important;
        border: 1px solid rgba(0,0,0,.09) !important;
        box-shadow: 0 10px 26px rgba(0,0,0,.2) !important;
      }

      .plyrcard-plan-card.is-featured {
        background: radial-gradient(circle at 100% 0%, rgba(255,92,53,.18), transparent 38%), linear-gradient(180deg, #fff, #f8f2ef) !important;
        border-color: rgba(255,92,53,.52) !important;
        box-shadow: 0 14px 30px rgba(255,92,53,.13), 0 10px 24px rgba(0,0,0,.2) !important;
      }

      .plyrcard-plan-card.is-current {
        border-color: rgba(22,139,255,.45) !important;
        background: radial-gradient(circle at 100% 0%, rgba(22,139,255,.16), transparent 38%), linear-gradient(180deg, #fff, #f3f8ff) !important;
      }

      .plyrcard-plan-top {
        display: flex !important;
        align-items: flex-start !important;
        justify-content: space-between !important;
        gap: 10px !important;
      }

      .plyrcard-plan-icon {
        width: 38px !important;
        height: 38px !important;
        border-radius: 10px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: rgba(0,0,0,.07) !important;
        color: rgba(0,0,0,.65) !important;
        font-size: 15px !important;
      }

      .plyrcard-plan-card.is-featured .plyrcard-plan-icon { background: rgba(255,92,53,.15) !important; color: var(--plyr-accent) !important; }
      .plyrcard-plan-card.is-current .plyrcard-plan-icon { background: rgba(22,139,255,.14) !important; color: #168bff !important; }

      .plyrcard-plan-badge-small {
        min-height: 22px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 9px !important;
        border-radius: 999px !important;
        background: #111 !important;
        color: #fff !important;
        font-size: 9px !important;
        font-weight: 950 !important;
        line-height: 1 !important;
        letter-spacing: .09em !important;
        text-transform: uppercase !important;
        white-space: nowrap !important;
      }

      .plyrcard-plan-card.is-featured .plyrcard-plan-badge-small { background: var(--plyr-accent) !important; }
      .plyrcard-plan-card.is-current .plyrcard-plan-badge-small { background: #168bff !important; }

      .plyrcard-plan-name {
        margin: 0 !important;
        color: #111 !important;
        font-size: 25px !important;
        font-weight: 950 !important;
        line-height: .95 !important;
        text-transform: uppercase !important;
      }

      .plyrcard-plan-price {
        display: flex !important;
        align-items: flex-end !important;
        gap: 3px !important;
        margin: 0 !important;
        color: var(--plyr-accent) !important;
        font-size: 31px !important;
        font-weight: 950 !important;
        line-height: .85 !important;
      }

      .plyrcard-plan-card.is-current .plyrcard-plan-price { color: #168bff !important; }
      .plyrcard-plan-price small { color: rgba(0,0,0,.55) !important; font-size: 11px !important; font-weight: 800 !important; line-height: 1.1 !important; }

      .plyrcard-plan-subtitle {
        margin: -7px 0 0 !important;
        color: rgba(0,0,0,.55) !important;
        font-size: 11px !important;
        line-height: 1.2 !important;
        font-weight: 750 !important;
        font-style: italic !important;
      }

      .plyrcard-plan-features {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
        display: grid !important;
        gap: 8px !important;
        color: #111 !important;
      }

      .plyrcard-plan-features li {
        display: grid !important;
        grid-template-columns: 16px 1fr !important;
        gap: 7px !important;
        align-items: start !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        font-weight: 850 !important;
        color: #111 !important;
      }

      .plyrcard-plan-features i { margin-top: 1px !important; color: #16a34a !important; font-size: 11px !important; }
      .plyrcard-plan-features .is-muted { color: rgba(0,0,0,.42) !important; }
      .plyrcard-plan-features .is-muted i { color: rgba(0,0,0,.38) !important; }

      .plyrcard-plan-footer-note {
        margin: 0 !important;
        padding: 10px 11px !important;
        border-radius: 10px !important;
        background: rgba(0,0,0,.045) !important;
        color: rgba(0,0,0,.62) !important;
        font-size: 11px !important;
        line-height: 1.25 !important;
        font-weight: 750 !important;
      }

      .plyrcard-plan-action {
        width: 100% !important;
        min-height: 40px !important;
        margin-top: auto !important;
        text-transform: uppercase !important;
        letter-spacing: .05em !important;
        font-size: 13px !important;
      }

      .plyrcard-plan-action.is-disabled {
        background: rgba(0,0,0,.12) !important;
        color: rgba(0,0,0,.48) !important;
        cursor: default !important;
        pointer-events: none !important;
      }

      @media (min-width: 900px) {
        .plyrcard-upgrade-hero {
          grid-template-columns: 1fr auto !important;
          align-items: center !important;
        }
        .plyrcard-upgrade-card-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
        .plyrcard-plan-card { min-height: 430px !important; }
      }



      /* Upgrade drawer polish: scoped so the rest of the navigation styling stays intact. */
      .plyrcard-upgrade-shell {
        background: #050505 !important;
        border-color: rgba(255,255,255,.12) !important;
        box-shadow: none !important;
        padding: 10px !important;
        gap: 12px !important;
      }

      .plyrcard-upgrade-hero {
        min-height: 154px !important;
        border-radius: 16px !important;
        padding: 18px !important;
        background:
          radial-gradient(circle at 88% 74%, rgba(255,92,53,.32), transparent 0 37px, transparent 38px),
          radial-gradient(circle at 100% 0%, rgba(255,92,53,.26), transparent 36%),
          linear-gradient(135deg, #fff3ef 0%, #fff 55%, #f5f5f5 100%) !important;
        border: 1px solid rgba(255,92,53,.36) !important;
        box-shadow: 0 10px 26px rgba(0,0,0,.24) !important;
        align-items: center !important;
      }

      .plyrcard-upgrade-title {
        font-size: clamp(28px, 7vw, 44px) !important;
        line-height: .92 !important;
        letter-spacing: .015em !important;
        max-width: 82% !important;
      }

      .plyrcard-upgrade-note {
        max-width: 82% !important;
        color: rgba(0,0,0,.66) !important;
        font-size: 13px !important;
        line-height: 1.22 !important;
      }

      .plyrcard-upgrade-crown {
        position: absolute !important;
        right: 18px !important;
        bottom: 18px !important;
        width: 54px !important;
        height: 54px !important;
        background: rgba(255,92,53,.13) !important;
        border-color: rgba(255,92,53,.32) !important;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.42) !important;
      }

      .plyrcard-upgrade-card-grid {
        gap: 10px !important;
      }

      .plyrcard-plan-card {
        min-height: auto !important;
        gap: 11px !important;
        padding: 14px !important;
        border-radius: 16px !important;
        background:
          radial-gradient(circle at 100% 0%, rgba(255,255,255,.06), transparent 42%),
          linear-gradient(180deg, #17181c 0%, #101115 100%) !important;
        color: #fff !important;
        border: 1px solid rgba(255,255,255,.10) !important;
        box-shadow: 0 12px 24px rgba(0,0,0,.32) !important;
      }

      .plyrcard-plan-card.is-featured {
        background:
          radial-gradient(circle at 100% 0%, rgba(255,92,53,.22), transparent 42%),
          linear-gradient(180deg, #201311 0%, #120c0b 100%) !important;
        border-color: rgba(255,92,53,.58) !important;
        box-shadow: 0 14px 28px rgba(255,92,53,.14), 0 12px 24px rgba(0,0,0,.34) !important;
      }

      .plyrcard-plan-card.is-current {
        background:
          radial-gradient(circle at 100% 0%, rgba(22,139,255,.22), transparent 42%),
          linear-gradient(180deg, #111b25 0%, #091018 100%) !important;
        border-color: rgba(22,139,255,.56) !important;
      }

      .plyrcard-plan-card.is-myjourney:not(.is-current) {
        background:
          radial-gradient(circle at 100% 0%, rgba(22,139,255,.14), transparent 42%),
          linear-gradient(180deg, #14181f 0%, #0f1116 100%) !important;
        border-color: rgba(22,139,255,.36) !important;
      }

      .plyrcard-plan-icon {
        width: 40px !important;
        height: 40px !important;
        border-radius: 10px !important;
        background: rgba(255,255,255,.08) !important;
        color: rgba(255,255,255,.78) !important;
      }

      .plyrcard-plan-card.is-featured .plyrcard-plan-icon { background: rgba(255,92,53,.18) !important; color: var(--plyr-accent) !important; }
      .plyrcard-plan-card.is-current .plyrcard-plan-icon,
      .plyrcard-plan-card.is-myjourney .plyrcard-plan-icon { background: rgba(22,139,255,.18) !important; color: #168bff !important; }

      .plyrcard-plan-name {
        color: #fff !important;
        font-size: 25px !important;
        line-height: .95 !important;
      }

      .plyrcard-plan-price {
        display: flex !important;
        align-items: baseline !important;
        gap: 4px !important;
        margin: 8px 0 3px !important;
        color: var(--plyr-accent) !important;
        font-size: 32px !important;
        line-height: 1 !important;
        white-space: nowrap !important;
      }

      .plyrcard-plan-card.is-current .plyrcard-plan-price,
      .plyrcard-plan-card.is-myjourney .plyrcard-plan-price { color: #168bff !important; }

      .plyrcard-plan-price small {
        color: rgba(255,255,255,.62) !important;
        font-size: 11px !important;
        line-height: 1 !important;
        transform: translateY(-1px) !important;
      }

      .plyrcard-plan-subtitle {
        margin: 0 !important;
        color: rgba(255,255,255,.62) !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
      }

      .plyrcard-plan-features {
        gap: 9px !important;
        margin-top: 2px !important;
      }

      .plyrcard-plan-features li {
        color: rgba(255,255,255,.90) !important;
        font-size: 12px !important;
        line-height: 1.18 !important;
        grid-template-columns: 15px 1fr !important;
      }

      .plyrcard-plan-features .is-muted { color: rgba(255,255,255,.38) !important; }
      .plyrcard-plan-features .is-muted i { color: rgba(255,255,255,.36) !important; }

      .plyrcard-plan-footer-note {
        background: rgba(255,255,255,.07) !important;
        color: rgba(255,255,255,.60) !important;
        border: 1px solid rgba(255,255,255,.08) !important;
      }

      .plyrcard-plan-action {
        min-height: 44px !important;
        border-radius: 11px !important;
        font-size: 13px !important;
      }

      .plyrcard-plan-action.is-disabled {
        background: rgba(255,255,255,.10) !important;
        color: rgba(255,255,255,.48) !important;
      }

      @media (max-width: 520px) {
        .plyrcard-upgrade-shell { padding: 8px !important; }
        .plyrcard-upgrade-hero { min-height: 142px !important; padding: 16px !important; }
        .plyrcard-upgrade-title { font-size: 30px !important; max-width: 80% !important; }
        .plyrcard-upgrade-note { max-width: 78% !important; font-size: 12px !important; }
        .plyrcard-upgrade-crown { width: 50px !important; height: 50px !important; right: 16px !important; bottom: 16px !important; }
        .plyrcard-plan-card { padding: 15px !important; }
        .plyrcard-plan-price { font-size: 31px !important; }
      }

      @media (max-width: 420px) {
        .plyrcard-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 6px !important; }
        .plyrcard-stat-card { min-height: 74px !important; padding: 9px 5px !important; }
        .plyrcard-stat-value { font-size: 20px !important; }
        .plyrcard-schedule-card { grid-template-columns: 40px 1fr !important; }
        .plyrcard-schedule-badge { grid-column: 2 !important; justify-self: start !important; }
      }



      /*
      |--------------------------------------------------------------------------
      | Compact UI polish
      |--------------------------------------------------------------------------
      | Keeps the existing Locker Room / Get Started styling, but tightens the
      | spacing, cards, form controls, and section typography so the drawer feels
      | faster and less bulky across mobile, admin, and player website views.
      */
      .plyrcard-action-drawer,
      .plyrcard-action-drawer * {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
        letter-spacing: .01em !important;
      }

      .plyrcard-drawer-panel {
        border-radius: 14px 14px 0 0 !important;
        max-height: min(78dvh, 580px) !important;
        box-shadow: 0 -12px 34px rgba(0,0,0,.46) !important;
      }

      .plyrcard-drawer-handle {
        top: 7px !important;
        width: 50px !important;
        height: 4px !important;
        background: rgba(0,0,0,.2) !important;
      }

      .plyrcard-drawer-head {
        min-height: 50px !important;
        padding: 13px 10px 8px !important;
        gap: 6px !important;
        border-radius: 14px 14px 0 0 !important;
      }

      .plyrcard-drawer-title-row,
      .plyrcard-user-line,
      .plyrcard-drawer-actions {
        gap: 6px !important;
      }

      .plyrcard-main-title,
      .plyrcard-section-title {
        font-size: 14px !important;
        letter-spacing: .015em !important;
      }

      .plyrcard-plan-badge {
        height: 18px !important;
        padding: 0 6px !important;
        font-size: 8px !important;
        max-width: 72px !important;
      }

      .plyrcard-signout-btn {
        height: 24px !important;
        padding: 0 7px !important;
        gap: 4px !important;
        font-size: 10px !important;
      }

      .plyrcard-drawer-close,
      .plyrcard-drawer-back {
        min-width: 26px !important;
        height: 26px !important;
        font-size: 17px !important;
      }

      .plyrcard-drawer-back {
        gap: 4px !important;
        font-size: 15px !important;
      }

      .plyrcard-drawer-body {
        padding: 7px 9px 68px !important;
        max-height: calc(min(78dvh, 580px) - 50px) !important;
      }

      .plyrcard-nav-group + .plyrcard-nav-group {
        margin-top: 7px !important;
      }

      .plyrcard-nav-group-title {
        margin-bottom: 4px !important;
        font-size: 10px !important;
        letter-spacing: .055em !important;
        color: rgba(255,255,255,.58) !important;
      }

      .plyrcard-drawer-grid {
        gap: 6px !important;
      }

      .plyrcard-drawer-card {
        min-height: 52px !important;
        padding: 6px 4px 5px !important;
        gap: 4px !important;
        border-radius: 7px !important;
        box-shadow: 0 3px 8px rgba(0,0,0,.22) !important;
      }

      .plyrcard-menu-icon {
        font-size: 13px !important;
      }

      .plyrcard-drawer-card span {
        font-size: 10px !important;
        line-height: 1.02 !important;
        font-weight: 850 !important;
      }

      .plyrcard-drawer-tab {
        width: 196px !important;
        height: 54px !important;
        padding: 0 12px 0 42px !important;
        gap: 8px !important;
        font-size: 18px !important;
        clip-path: polygon(32px 0, 100% 0, 100% 100%, 0 100%) !important;
      }

      .plyrcard-drawer-tab i {
        font-size: 13px !important;
      }

      .plyrcard-drawer-section-divider {
        margin: 10px 0 9px !important;
      }

      .plyrcard-social-row {
        gap: 16px !important;
      }

      .plyrcard-social-label {
        font-size: 17px !important;
      }

      .plyrcard-social-row i {
        font-size: 19px !important;
      }

      .plyrcard-form-card,
      .plyrcard-mini-panel,
      .plyrcard-offer-card,
      .plyrcard-plan-card,
      .plyrcard-dashboard-card,
      .plyrcard-profile-card,
      .plyrcard-settings-card {
        border-radius: 12px !important;
        padding: 11px !important;
        box-shadow: 0 6px 18px rgba(0,0,0,.22) !important;
      }

      .plyrcard-form-stack,
      .plyrcard-offer-list {
        gap: 7px !important;
      }

      .plyrcard-input-label {
        gap: 4px !important;
        font-size: 10px !important;
        letter-spacing: .045em !important;
      }

      .plyrcard-input-wrap > i {
        left: 10px !important;
        font-size: 12px !important;
      }

      .plyrcard-input-wrap.textarea > i {
        top: 12px !important;
      }

      .plyrcard-drawer-input,
      .plyrcard-drawer-textarea,
      .plyrcard-drawer-select,
      .plyrcard-position-trigger {
        min-height: 36px !important;
        border-radius: 9px !important;
        padding: 8px 10px 8px 31px !important;
        font-size: 12px !important;
        font-weight: 750 !important;
      }

      .plyrcard-drawer-textarea {
        min-height: 72px !important;
        padding-top: 9px !important;
      }

      .plyrcard-subsection-lead {
        margin-bottom: 9px !important;
        font-size: 12px !important;
        line-height: 1.28 !important;
      }

      .plyrcard-mini-title,
      .plyrcard-card-title,
      .plyrcard-section-heading {
        font-size: 16px !important;
        line-height: 1.02 !important;
        margin-bottom: 5px !important;
      }

      .plyrcard-mini-copy,
      .plyrcard-card-copy,
      .plyrcard-helper-text {
        font-size: 12px !important;
        line-height: 1.28 !important;
        margin-bottom: 9px !important;
      }

      .plyrcard-submit-btn,
      .plyrcard-secondary-btn,
      .plyrcard-copy-btn {
        min-height: 36px !important;
        border-radius: 8px !important;
        padding: 0 12px !important;
        gap: 6px !important;
        font-size: 13px !important;
      }

      .plyrcard-toggle-choice-grid {
        gap: 6px !important;
      }

      .plyrcard-toggle-choice {
        min-height: 62px !important;
        padding: 8px !important;
        border-radius: 11px !important;
        gap: 8px !important;
      }

      .plyrcard-toggle-choice-icon {
        width: 29px !important;
        height: 29px !important;
        border-radius: 9px !important;
        font-size: 13px !important;
      }

      .plyrcard-toggle-choice-title {
        font-size: 12px !important;
        line-height: 1 !important;
      }

      .plyrcard-toggle-choice-copy {
        margin-top: 3px !important;
        font-size: 10px !important;
        line-height: 1.16 !important;
      }

      .plyrcard-offer-card {
        grid-template-columns: 44px 1fr auto !important;
        min-height: 64px !important;
        gap: 8px !important;
        padding: 8px 10px 8px 8px !important;
        border-radius: 8px !important;
      }

      .plyrcard-offer-icon {
        width: 36px !important;
        height: 36px !important;
        border-radius: 8px !important;
      }

      .plyrcard-offer-icon i {
        font-size: 17px !important;
      }

      .plyrcard-offer-title {
        font-size: 16px !important;
        margin-bottom: 3px !important;
      }

      .plyrcard-offer-copy {
        font-size: 12px !important;
        line-height: 1.16 !important;
      }

      .plyrcard-offer-price {
        font-size: 20px !important;
      }

      .plyrcard-offer-price small {
        margin-top: 4px !important;
        font-size: 9px !important;
      }

      .plyrcard-locked-panel {
        grid-template-columns: 36px 1fr auto !important;
        gap: 9px !important;
        padding: 10px !important;
        border-radius: 12px !important;
        margin-bottom: 9px !important;
      }

      .plyrcard-locked-icon {
        width: 32px !important;
        height: 32px !important;
      }

      .plyrcard-locked-panel strong {
        font-size: 12px !important;
      }

      .plyrcard-locked-panel span span {
        margin-top: 3px !important;
        font-size: 11px !important;
      }

      .plyrcard-locked-panel .plyrcard-submit-btn {
        min-height: 34px !important;
        font-size: 11px !important;
        padding: 0 9px !important;
      }

      .plyrcard-qr-wrap {
        gap: 9px !important;
      }

      .plyrcard-qr-wrap img {
        width: 148px !important;
        height: 148px !important;
        border-radius: 10px !important;
        padding: 7px !important;
      }

      .plyrcard-share-options {
        gap: 6px !important;
      }

      .plyrcard-alert,
      .plyrcard-refresh-indicator {
        margin-bottom: 8px !important;
        padding: 9px 10px !important;
        border-radius: 10px !important;
        font-size: 11px !important;
      }

      .plyrcard-booking-wrap {
        height: calc(min(78dvh, 580px) - 62px) !important;
        border-radius: 10px !important;
      }

      .plyrcard-drawer-panel.is-expanded {
        max-height: 92dvh !important;
      }

      .plyrcard-drawer-panel.is-expanded .plyrcard-drawer-body {
        max-height: calc(92dvh - 50px) !important;
      }

      .plyrcard-drawer-panel.is-support-fullscreen,
      .plyrcard-drawer-panel.is-expanded.is-support-fullscreen {
        border-radius: 14px 14px 0 0 !important;
      }

      .plyrcard-drawer-panel.is-support-fullscreen .plyrcard-drawer-body {
        height: calc(100dvh - 50px) !important;
        max-height: calc(100dvh - 50px) !important;
        padding-bottom: 62px !important;
      }

      .plyrcard-support-ticket-wrap {
        height: calc(100dvh - 50px - 68px) !important;
        min-height: 560px !important;
        border-radius: 10px !important;
        box-shadow: 0 7px 18px rgba(0,0,0,.22) !important;
      }

      .plyrcard-support-ticket-loading {
        font-size: 12px !important;
      }

      .plyrcard-schedule-date-badge {
        width: 38px !important;
        min-width: 38px !important;
        height: 44px !important;
        border-radius: 10px !important;
      }

      .plyrcard-schedule-date-month {
        padding: 3px 0 2px !important;
        font-size: 9px !important;
      }

      .plyrcard-schedule-date-day {
        font-size: 19px !important;
      }

      @media (max-width: 390px) {
        .plyrcard-drawer-body {
          padding-left: 7px !important;
          padding-right: 7px !important;
        }

        .plyrcard-drawer-grid {
          gap: 5px !important;
        }

        .plyrcard-drawer-card {
          min-height: 49px !important;
          padding: 5px 3px !important;
        }

        .plyrcard-menu-icon {
          font-size: 12px !important;
        }

        .plyrcard-drawer-card span {
          font-size: 9.5px !important;
        }

        .plyrcard-drawer-tab {
          width: 184px !important;
          height: 50px !important;
          padding-left: 38px !important;
          font-size: 16px !important;
          clip-path: polygon(29px 0, 100% 0, 100% 100%, 0 100%) !important;
        }
      }



      /* Support ticket embed zoom fix: keep the GHL iframe readable but scaled down. */
      .plyrcard-drawer-panel.is-support-fullscreen .plyrcard-support-ticket-wrap,
      .plyrcard-support-ticket-wrap {
        height: calc(100dvh - 112px) !important;
        min-height: calc(100dvh - 112px) !important;
        max-height: calc(100dvh - 112px) !important;
        overflow: auto !important;
        -webkit-overflow-scrolling: touch !important;
        background: #ffffff !important;
        border-radius: 10px !important;
      }

      .plyrcard-support-ticket-scale-wrap {
        width: 100% !important;
        height: auto !important;
        min-height: 100% !important;
        overflow: visible !important;
        background: #ffffff !important;
        position: relative !important;
      }

      .plyrcard-support-ticket-scale-wrap iframe,
      .plyrcard-support-ticket-iframe,
      iframe[data-plyrcard-support-iframe],
      iframe#inline-HDaBy0CDwdO7Fw54wi1K {
        width: 125% !important;
        max-width: none !important;
        height: 1425px !important;
        min-height: 1425px !important;
        border: 0 !important;
        border-radius: 3px !important;
        display: block !important;
        background: #ffffff !important;
        transform: scale(0.8) !important;
        transform-origin: top left !important;
      }

      @media (max-width: 520px) {
        .plyrcard-drawer-panel.is-support-fullscreen .plyrcard-support-ticket-wrap,
        .plyrcard-support-ticket-wrap {
          height: calc(100dvh - 104px) !important;
          min-height: calc(100dvh - 104px) !important;
          max-height: calc(100dvh - 104px) !important;
        }

        .plyrcard-support-ticket-scale-wrap iframe,
        .plyrcard-support-ticket-iframe,
        iframe[data-plyrcard-support-iframe],
        iframe#inline-HDaBy0CDwdO7Fw54wi1K {
          width: 133.333% !important;
          height: 1540px !important;
          min-height: 1540px !important;
          transform: scale(0.75) !important;
        }
      }

      /* Seamless navigation loader for Locker Room admin links. */
      .plyrcard-page-loader {
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483646 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: rgba(5, 5, 5, .76) !important;
        backdrop-filter: blur(2px) !important;
        -webkit-backdrop-filter: blur(2px) !important;
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transition: opacity .16s ease, visibility .16s ease !important;
      }

      .plyrcard-page-loader.is-visible {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
      }

      .plyrcard-page-loader-spinner {
        width: 44px !important;
        height: 44px !important;
        border: 4px solid rgba(255, 255, 255, .24) !important;
        border-top-color: var(--plyr-accent, #FF5C35) !important;
        border-radius: 50% !important;
        animation: plyrcardTailSpinner .68s linear infinite !important;
        box-shadow: 0 0 24px rgba(0, 0, 0, .28) !important;
      }

      @keyframes plyrcardTailSpinner {
        to { transform: rotate(360deg); }
      }

      .plyrcard-visually-hidden {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
      }

      /* Registration route guard: never show pull-up / Locker Room access on /registration. */
      body.plyrcard-registration-page .plyrcard-action-drawer,
      body.plyrcard-registration-page #plyrcard-action-drawer,
      body.plyrcard-registration-page [data-plyrcard-open-drawer] {
        display: none !important;
      }
  </style>

  <header id="site-header" class="plyrcard-site-header over-hero {{ $plyrPullUpOnly ? 'is-pullup-only' : '' }} {{ $plyrHideHeaderNavigation ? 'is-player-website-header-hidden' : '' }}">
    <a data-nav href="/" class="logo-wrap" aria-label="PLYRCARD Home">
      <img src="{{ asset('images/plyr-logo.png') }}" alt="PLYRCARD Logo">
    </a>

    <nav class="desktop-nav" aria-label="Primary navigation">
      <a data-nav href="/" class="{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
      <a data-nav href="/about" class="{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
      <a data-nav href="/pricing" class="{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
      <a data-nav href="/podcast" class="{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
      <a data-nav href="/book-demo" class="{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book a Demo</a>
      @auth
        @unless($plyrOnRegistrationPage)
          <a data-nav href="{{ url('/admin/coach-database') }}">Dashboard</a>
        @endunless
      @else
        @unless($plyrOnRegistrationPage)
          <a href="#" data-plyrcard-open-drawer>Login</a>
        @endunless
        <a data-nav href="/registration?utm_plan=free" class="desktop-nav-cta{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
      @endauth
    </nav>

    <button class="menu-btn" id="menu-btn" type="button" aria-label="Open menu" aria-controls="mobile-nav" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </header>

  <nav id="mobile-nav" class="plyrcard-mobile-nav {{ $plyrPullUpOnly ? 'is-pullup-only' : '' }} {{ $plyrHideHeaderNavigation ? 'is-player-website-header-hidden' : '' }}" aria-label="Mobile navigation">
    <a data-nav href="/" class="nav-link{{ ($activePage ?? '') === 'home' ? ' active' : '' }}">Home</a>
    <a data-nav href="/about" class="nav-link{{ ($activePage ?? '') === 'about' ? ' active' : '' }}">About</a>
    <a data-nav href="/pricing" class="nav-link{{ ($activePage ?? '') === 'pricing' ? ' active' : '' }}">Pricing</a>
    <a data-nav href="/podcast" class="nav-link{{ ($activePage ?? '') === 'podcast' ? ' active' : '' }}">Podcast</a>
    <a data-nav href="/book-demo" class="nav-link{{ ($activePage ?? '') === 'book-demo' ? ' active' : '' }}">Book Demo</a>
    @if($plyrShouldRenderPullup)
      <button type="button" class="nav-link" data-plyrcard-open-drawer>{{ $plyrTabLabel }}</button>
    @endif
    @guest
      <a data-nav href="/registration?utm_plan=free" class="nav-cta-pill{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
    @endguest
  </nav>


  @include("partials.locker-room")

@endif