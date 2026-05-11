@once
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Antonio:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
@endonce

@php
    use App\Models\Website;
    use Illuminate\Support\Str;

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
            : Str::of($plyrUser->name ?? 'Player')->trim()->explode(' ')->first();
    }

    $plyrPlanName = 'Free';
    if ($plyrLoggedIn && $plyrUser && method_exists($plyrUser, 'hasRole')) {
        if ($plyrUser->hasRole('My Journey')) {
            $plyrPlanName = 'My Journey';
        } elseif ($plyrUser->hasRole('Plyr Plus')) {
            $plyrPlanName = 'Plyr Plus';
        } elseif ($plyrUser->hasRole('Plyr')) {
            $plyrPlanName = 'Plyr';
        } elseif ($plyrUser->hasRole('Free')) {
            $plyrPlanName = 'Free';
        }
    }

    $plyrHasMyJourneyRole = $plyrLoggedIn && $plyrUser && method_exists($plyrUser, 'hasRole')
        ? $plyrUser->hasRole('My Journey')
        : false;

    $plyrHasPremiumFeatures = $plyrLoggedIn && $plyrUser && method_exists($plyrUser, 'hasRole')
        ? ($plyrUser->hasRole('Plyr Plus') || $plyrUser->hasRole('My Journey'))
        : false;

    $plyrActivePage = $activePage ?? null;
    $plyrCurrentPath = trim(request()->path(), '/');
    $plyrCurrentHost = request()->getHost();
    $plyrCurrentHostNormalized = strtolower(preg_replace('/:\d+$/', '', $plyrCurrentHost));
    $plyrRequestUrl = rtrim(request()->url(), '/');

    $plyrReservedPaths = ['', '/', 'about', 'pricing', 'podcast', 'book-demo', 'registration', 'login', 'admin'];
    $plyrMainHosts = array_filter(array_map('strtolower', [
        'plyrcard.com',
        'www.plyrcard.com',
        parse_url(config('app.url'), PHP_URL_HOST),
        '127.0.0.1',
        'localhost',
    ]));

    $plyrOnAdmin = request()->is('admin') || request()->is('admin/*') || $plyrActivePage === 'admin';
    $plyrOnMainPlyrSite = in_array($plyrCurrentHostNormalized, $plyrMainHosts, true);

    $plyrWebsite = null;
    $plyrWebsiteUrl = null;

    if ($plyrLoggedIn && $plyrUser && class_exists(Website::class)) {
        $plyrWebsite = Website::query()
            ->where('user_id', $plyrUser->id)
            ->where('is_active', true)
            ->where('is_published', true)
            ->latest('updated_at')
            ->first();

        if ($plyrWebsite) {
            if (! blank($plyrWebsite->domain)) {
                $domain = preg_replace('#^https?://#i', '', trim($plyrWebsite->domain));
                $plyrWebsiteUrl = 'https://' . rtrim($domain, '/');
            } elseif (! blank($plyrWebsite->slug)) {
                $plyrWebsiteUrl = url('/' . ltrim($plyrWebsite->slug, '/'));
            } elseif (! blank($plyrWebsite->name)) {
                $plyrWebsiteUrl = url('/' . Str::slug($plyrWebsite->name));
            }
        }
    }

    $plyrViewedWebsite = null;

    if (class_exists(Website::class)) {
        // Custom-domain player site detection. This covers domains such as ernestomarin.com.
        if (! $plyrOnMainPlyrSite) {
            $plyrViewedWebsite = Website::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->whereNotNull('domain')
                ->get()
                ->first(function (Website $website) use ($plyrCurrentHostNormalized) {
                    $domain = strtolower(trim((string) $website->domain));
                    $domain = preg_replace('#^https?://#i', '', $domain);
                    $domain = preg_replace('/:\d+$/', '', $domain);
                    $domain = rtrim($domain, '/');

                    return $domain === $plyrCurrentHostNormalized || 'www.' . $domain === $plyrCurrentHostNormalized;
                });
        }

        // Path-based player site detection for main-domain URLs like /player-name.
        if (! $plyrViewedWebsite && ! $plyrOnAdmin && $plyrCurrentPath !== '' && ! in_array($plyrCurrentPath, $plyrReservedPaths, true)) {
            $pathSlug = strtolower($plyrCurrentPath);
            $plyrViewedWebsite = Website::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->where(function ($query) use ($pathSlug) {
                    $query->whereRaw('LOWER(slug) = ?', [$pathSlug]);
                })
                ->first();

            if (! $plyrViewedWebsite) {
                $plyrViewedWebsite = Website::query()
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->get()
                    ->first(function (Website $website) use ($pathSlug) {
                        return Str::slug($website->name) === $pathSlug;
                    });
            }
        }
    }

    $plyrOnPlayerWebsite = in_array($plyrActivePage, ['website', 'player', 'player-website'], true) || (bool) $plyrViewedWebsite;
    $plyrOwnsViewedWebsite = $plyrLoggedIn && $plyrUser && $plyrViewedWebsite && ((int) $plyrViewedWebsite->user_id === (int) $plyrUser->id);

    // When included from the player's own template with activePage only, fall back to the user's published website.
    if (! $plyrOwnsViewedWebsite && $plyrOnPlayerWebsite && $plyrLoggedIn && $plyrWebsite) {
        $ownSlug = trim((string) ($plyrWebsite->slug ?: Str::slug($plyrWebsite->name)), '/');
        $ownDomain = $plyrWebsite->domain ? strtolower(rtrim(preg_replace('#^https?://#i', '', trim($plyrWebsite->domain)), '/')) : null;

        $plyrOwnsViewedWebsite = ($ownSlug && $ownSlug === $plyrCurrentPath)
            || ($ownDomain && strtolower($ownDomain) === $plyrCurrentHostNormalized);
    }

    $plyrPullUpOnly = $plyrPullUpOnly ?? ($plyrOnAdmin || $plyrOnPlayerWebsite);
    $plyrHideHeaderNavigation = $plyrOnPlayerWebsite;

    /*
     * Visibility rules:
     * - Main PLYRCard site: logged out shows GET STARTED, logged in shows Locker Room.
     * - Player's own website: logged in owner shows Locker Room only.
     * - Other player websites: show nothing.
     */
    if ($plyrOnPlayerWebsite) {
        $plyrShouldRenderPullup = $plyrLoggedIn && $plyrOwnsViewedWebsite;
    } else {
        $plyrShouldRenderPullup = true;
    }

    $plyrTabLabel = $plyrLoggedIn ? 'Locker Room' : 'GET STARTED';
    $plyrWebsiteActionLabel = $plyrOnPlayerWebsite ? 'Edit my Website' : 'View my Website';
    $plyrWebsiteActionHref = $plyrOnPlayerWebsite ? '#' : ($plyrWebsiteUrl ?: '#');
    $plyrWebsiteActionTarget = (! $plyrOnPlayerWebsite && $plyrWebsiteUrl) ? '_blank' : null;
    $plyrWebsiteActionDisabled = (! $plyrOnPlayerWebsite && ! $plyrWebsiteUrl);

    $plyrSupportEmail = 'support@plyrcard.com';
    $plyrPhoneDisplay = '+1 571-888-0852';
    $plyrPhoneHref = '+15718880852';
    $plyrMainShareUrl = 'https://plyrcard.com';
    $plyrWebsiteShareUrl = $plyrWebsiteUrl ?: url('/');
    $plyrLogoutAction = url('/admin/logout');

    $plyrFacebookUrl = $plyrUser->facebook_url ?? $plyrUser->facebook ?? 'https://www.facebook.com/plyrcard';
    $plyrInstagramUrl = $plyrUser->instagram_url ?? $plyrUser->instagram ?? 'https://www.instagram.com/plyrcard/';
    $plyrXUrl = $plyrUser->x_url ?? $plyrUser->twitter_url ?? $plyrUser->twitter ?? 'https://x.com/plyrcard';
    $plyrYouTubeUrl = $plyrUser->youtube_url ?? $plyrUser->youtube ?? 'https://www.youtube.com/@plyrcard';

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
    $plyrDrawerLoginAction = \Illuminate\Support\Facades\Route::has('plyrcard.drawer-login')
        ? route('plyrcard.drawer-login')
        : url('/admin/login');

    $plyrEditableProfileFields = [
        'first_name', 'last_name', 'personal_email', 'phone', 'street', 'city', 'state', 'country',
        'sport', 'position', 'jersey_number', 'year', 'gender', 'birth', 'gpa', 'height', 'weight',
        'player_bio', 'academic_accolades', 'sports_accolades', 'ig_handle', 'x_handle', 'yt_url',
        'featured_video_url', 'featured_video_urls', 'press', 'parent', 'parent_email', 'parent_phone',
        'sec_parent', 'sec_parent_email', 'sec_parent_phone', 'club_coach', 'club_coach_email',
        'club_coach_phone', 'natl_coach', 'natl_coach_email', 'natl_coach_phone', 'tech_trainer',
        'tech_trainer_email', 'tech_trainer_phone', 'snc_trainer', 'snc_trainer_email', 'snc_trainer_phone',
    ];

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


    $plyrDashboardSections = [
        'Basic Information' => ['first_name' => 'First name', 'last_name' => 'Last name', 'personal_email' => 'Personal email', 'phone' => 'Phone', 'birth' => 'Birth date', 'gender' => 'Sex'],
        'Location' => ['street' => 'Street', 'city' => 'City', 'state' => 'State / Province', 'country' => 'Country'],
        'Athlete Details' => ['sport' => 'Sport', 'position' => 'Position', 'jersey_number' => 'Roster #', 'year' => 'Graduation year', 'gpa' => 'GPA', 'height' => 'Height', 'weight' => 'Weight'],
        'Story & Accolades' => ['player_bio' => 'Player bio', 'academic_accolades' => 'Academic accolades', 'sports_accolades' => 'Sports accolades'],
        'Social & Media' => ['ig_handle' => 'Instagram', 'x_handle' => 'X', 'yt_url' => 'YouTube', 'featured_video_url' => 'Featured video', 'press' => 'Press'],
        'People' => ['parent' => 'Primary parent', 'parent_email' => 'Parent email', 'parent_phone' => 'Parent phone', 'club_coach' => 'Club coach', 'club_coach_email' => 'Club coach email', 'club_coach_phone' => 'Club coach phone'],
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
      font-size: 16px !important;
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
      padding: 9px 12px 84px !important;
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

    .plyrcard-nav-group + .plyrcard-nav-group { margin-top: 10px !important; }
    .plyrcard-nav-group-title {
      display: block !important;
      margin: 0 0 5px !important;
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
      gap: 8px !important;
    }

    .plyrcard-drawer-card {
      min-width: 0 !important;
      min-height: 66px !important;
      padding: 8px 5px 7px !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 6px !important;
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

    .plyrcard-menu-icon { font-size: 17px !important; line-height: 1 !important; color: currentColor !important; }
    .plyrcard-drawer-card span { display: block !important; color: currentColor !important; font-size: 12px !important; line-height: .98 !important; font-weight: 850 !important; }

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
      padding: 16px !important;
      box-shadow: 0 10px 26px rgba(0,0,0,.26) !important;
      border: 1px solid rgba(255,255,255,.75) !important;
    }

    .plyrcard-form-stack { display: grid !important; gap: 11px !important; }
    .plyrcard-input-label { display: grid !important; gap: 6px !important; color: rgba(0,0,0,.52) !important; font-size: 11px !important; font-weight: 900 !important; text-transform: uppercase !important; letter-spacing: .035em !important; }
    .plyrcard-input-wrap { position: relative !important; display: block !important; }
    .plyrcard-input-wrap > i { position: absolute !important; left: 12px !important; top: 50% !important; transform: translateY(-50%) !important; color: rgba(0,0,0,.8) !important; font-size: 13px !important; }
    .plyrcard-input-wrap.textarea > i { top: 15px !important; transform: none !important; }

    .plyrcard-drawer-input,
    .plyrcard-drawer-textarea,
    .plyrcard-drawer-select {
      width: 100% !important;
      min-height: 43px !important;
      border-radius: 12px !important;
      border: 1px solid rgba(0,0,0,.075) !important;
      background: #fff !important;
      color: #111 !important;
      padding: 10px 12px 10px 37px !important;
      font-size: 14px !important;
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

    .plyrcard-drawer-textarea { min-height: 92px !important; resize: vertical !important; padding-top: 12px !important; }
    .plyrcard-clean-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; flex-wrap: wrap !important; }
    .plyrcard-text-link { border: 0 !important; background: transparent !important; color: #111 !important; padding: 0 !important; font: inherit !important; text-decoration: underline !important; cursor: pointer !important; }
    .plyrcard-subsection-lead { margin: 0 0 12px !important; color: rgba(255,255,255,.72) !important; font-size: 13px !important; line-height: 1.35 !important; font-weight: 650 !important; }
    .plyrcard-mini-title { margin: 0 0 6px !important; color: #111 !important; font-size: 18px !important; line-height: 1 !important; font-weight: 950 !important; }
    .plyrcard-mini-copy { margin: 0 0 13px !important; color: rgba(0,0,0,.58) !important; font-size: 13px !important; line-height: 1.35 !important; font-weight: 650 !important; }

    .plyrcard-submit-btn,
    .plyrcard-secondary-btn,
    .plyrcard-copy-btn {
      min-height: 42px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 8px !important;
      border: 0 !important;
      border-radius: 10px !important;
      padding: 0 14px !important;
      background: var(--plyr-accent) !important;
      color: #fff !important;
      font-size: 16px !important;
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
      min-height: 44px !important;
      border-radius: 12px !important;
      display: grid !important;
      place-items: center !important;
      background: rgba(255,92,53,.11) !important;
      color: var(--plyr-accent) !important;
      font-size: 18px !important;
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
      font-size: 14px !important;
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

    @media (max-width: 420px) {
      .plyrcard-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 6px !important; }
      .plyrcard-stat-card { min-height: 74px !important; padding: 9px 5px !important; }
      .plyrcard-stat-value { font-size: 20px !important; }
      .plyrcard-schedule-card { grid-template-columns: 40px 1fr !important; }
      .plyrcard-schedule-badge { grid-column: 2 !important; justify-self: start !important; }
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
      <a href="#" data-plyrcard-open-drawer>Dashboard</a>
    @else
      <a href="#" data-plyrcard-open-drawer>Login</a>
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
  <button type="button" class="nav-link" data-plyrcard-open-drawer>{{ $plyrTabLabel }}</button>
  @guest
    <a data-nav href="/registration?utm_plan=free" class="nav-cta-pill{{ ($activePage ?? '') === 'registration' ? ' active' : '' }}">Start Free</a>
  @endguest
</nav>

@if($plyrShouldRenderPullup)
<div id="plyrcard-action-drawer" class="plyrcard-action-drawer" data-state="closed">
  <div class="plyrcard-drawer-scrim" data-plyrcard-close-drawer></div>

  <section class="plyrcard-drawer-panel" aria-label="{{ $plyrLoggedIn ? 'Locker Room menu' : 'Get Started menu' }}">
    <div class="plyrcard-drawer-handle" aria-hidden="true"></div>

    <div class="plyrcard-drawer-head">
      <div class="plyrcard-drawer-title-row" data-plyrcard-main-title>
        @auth
          <div class="plyrcard-user-line">
            <h2 class="plyrcard-main-title">Hi {{ $plyrFirstName }}!</h2>
            <span class="plyrcard-plan-badge">{{ $plyrPlanName }}</span>
          </div>
        @else
          <h2 class="plyrcard-main-title">Get Started</h2>
        @endauth
      </div>

      <div class="plyrcard-drawer-title-row" data-plyrcard-sub-title style="display:none !important;">
        <button type="button" class="plyrcard-drawer-back" data-plyrcard-back>
          <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
          <span>Back</span>
        </button>
        <h2 class="plyrcard-section-title" data-plyrcard-section-title></h2>
      </div>

      <div class="plyrcard-drawer-actions">
        @auth
          <form class="plyrcard-signout-form" method="POST" action="{{ $plyrLogoutAction }}" data-plyrcard-logout-form>
            @csrf
            <button type="submit" class="plyrcard-signout-btn"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign Out</button>
          </form>
        @endauth
        <button type="button" class="plyrcard-drawer-close" aria-label="Close menu" data-plyrcard-close-drawer>
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <div class="plyrcard-drawer-body">
      <div class="plyrcard-refresh-indicator" data-plyrcard-refresh-indicator><span class="plyrcard-btn-spinner" aria-hidden="true"></span><strong data-plyrcard-refresh-text>Refreshing...</strong></div>
      <div class="plyrcard-alert" data-plyrcard-alert><span data-plyrcard-alert-text></span><button type="button" data-plyrcard-alert-close aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button></div>
      @auth
        <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Locker Room</strong>
            <div class="plyrcard-drawer-grid">
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="dashboard"><i class="plyrcard-menu-icon fa-solid fa-gauge-high" aria-hidden="true"></i><span>Dashboard</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="profile"><i class="plyrcard-menu-icon fa-solid fa-user" aria-hidden="true"></i><span>Profile</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="schedule"><i class="plyrcard-menu-icon fa-solid fa-calendar-days" aria-hidden="true"></i><span>My Schedule</span></button>
              <button type="button" class="plyrcard-drawer-card is-disabled" disabled aria-disabled="true" title="Settings coming soon"><i class="plyrcard-menu-icon fa-solid fa-gear" aria-hidden="true"></i><span>Settings</span></button>
            </div>
          </div>

          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Website</strong>
            <div class="plyrcard-drawer-grid">
              @if($plyrWebsiteActionDisabled)
                <button type="button" class="plyrcard-drawer-card is-disabled" disabled aria-disabled="true"><i class="plyrcard-menu-icon fa-solid fa-globe" aria-hidden="true"></i><span>{{ $plyrWebsiteActionLabel }}</span></button>
              @else
                @if($plyrOnPlayerWebsite)
                <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="edit-website"><i class="plyrcard-menu-icon fa-solid fa-globe" aria-hidden="true"></i><span>{{ $plyrWebsiteActionLabel }}</span></button>
                @else
                <a class="plyrcard-drawer-card" href="{{ $plyrWebsiteActionHref }}" @if($plyrWebsiteActionTarget) target="{{ $plyrWebsiteActionTarget }}" rel="noopener" @endif><i class="plyrcard-menu-icon fa-solid fa-globe" aria-hidden="true"></i><span>{{ $plyrWebsiteActionLabel }}</span></a>
                @endif
              @endif
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share-card"><i class="plyrcard-menu-icon fa-solid fa-qrcode" aria-hidden="true"></i><span>Share Card</span></button>
              <a class="plyrcard-drawer-card" href="/podcast"><i class="plyrcard-menu-icon fa-solid fa-podcast" aria-hidden="true"></i><span>PLYRCard Show</span></a>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="a-la-carte"><i class="plyrcard-menu-icon fa-solid fa-bag-shopping" aria-hidden="true"></i><span>A La Carte</span></button>
            </div>
          </div>

          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Growth</strong>
            <div class="plyrcard-drawer-grid">
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="upgrade"><i class="plyrcard-menu-icon fa-solid fa-arrow-trend-up" aria-hidden="true"></i><span>Upgrade</span></button>
              @if($plyrHasPremiumFeatures)
                <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="refer-friend"><i class="plyrcard-menu-icon fa-solid fa-user-plus" aria-hidden="true"></i><span>Refer Friend</span></button>
              @else
                <button type="button" class="plyrcard-drawer-card is-locked" data-plyrcard-section="upgrade"><i class="plyrcard-menu-icon fa-solid fa-lock" aria-hidden="true"></i><span>Refer Friend</span></button>
              @endif
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="support"><i class="plyrcard-menu-icon fa-solid fa-headset" aria-hidden="true"></i><span>Support</span></button>
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="book-demo"><i class="plyrcard-menu-icon fa-solid fa-calendar-check" aria-hidden="true"></i><span>Book a Call</span></button>
            </div>
          </div>

          <div class="plyrcard-nav-group">
            <strong class="plyrcard-nav-group-title">Account</strong>
            <div class="plyrcard-drawer-grid">
              <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="billing"><i class="plyrcard-menu-icon fa-solid fa-credit-card" aria-hidden="true"></i><span>Billing</span></button>
            </div>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="refer-friend" data-title="Refer a Friend">
          @unless($plyrHasPremiumFeatures)
            <div class="plyrcard-form-card"><div class="plyrcard-locked-panel"><span class="plyrcard-locked-icon"><i class="fa-solid fa-lock"></i></span><span><strong>Referrals locked</strong><span>Upgrade to Plyr Plus or My Journey to unlock referral tools.</span></span><button type="button" class="plyrcard-submit-btn" data-plyrcard-section="upgrade">See Plans</button></div></div>
          @else
          <form class="plyrcard-form-card plyrcard-form-stack" action="{{ $plyrReferralStoreAction }}" method="POST" data-plyrcard-ajax-form data-success-message="Referral sent.">
            @csrf
            <label class="plyrcard-input-label">Friend Name<span class="plyrcard-input-wrap"><i class="fa-regular fa-user" aria-hidden="true"></i><input class="plyrcard-drawer-input" name="friend_name" placeholder="Full name"></span></label>
            <label class="plyrcard-input-label">Friend Email<span class="plyrcard-input-wrap"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input class="plyrcard-drawer-input" type="email" name="friend_email" placeholder="friend@example.com"></span></label>
            <label class="plyrcard-input-label">Friend Phone<span class="plyrcard-input-wrap"><i class="fa-solid fa-phone" aria-hidden="true"></i><input class="plyrcard-drawer-input" name="friend_phone" placeholder="{{ $plyrPhoneDisplay }}"></span></label>
            <label class="plyrcard-input-label">Message<span class="plyrcard-input-wrap textarea"><i class="fa-regular fa-message" aria-hidden="true"></i><textarea class="plyrcard-drawer-textarea" name="message" placeholder="Add a short message..."></textarea></span></label>
            <button class="plyrcard-submit-btn" type="submit"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Invite</button>
          </form>
          @endunless
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="support" data-title="Support">
          <form class="plyrcard-form-card plyrcard-form-stack" action="{{ $plyrSupportStoreAction }}" method="POST" data-plyrcard-ajax-form data-success-message="Support request sent.">
            @csrf
            <label class="plyrcard-input-label">Concern<span class="plyrcard-input-wrap"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><select class="plyrcard-drawer-select" name="concern"><option value="">Select your concern</option><option value="Billing">Billing</option><option value="Website">Website</option><option value="Account">Account</option><option value="Other">Other</option></select></span></label>
            <label class="plyrcard-input-label">Details<span class="plyrcard-input-wrap textarea"><i class="fa-regular fa-message" aria-hidden="true"></i><textarea class="plyrcard-drawer-textarea" name="details" placeholder="Give us some more details..."></textarea></span></label>
            <button class="plyrcard-submit-btn" type="submit">Submit</button>
          </form>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="share-card" data-title="Share Card">
          <div class="plyrcard-form-card plyrcard-qr-wrap">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($plyrWebsiteShareUrl) }}" alt="QR code for your PLYRCard">
            <div class="plyrcard-copy-line" style="width:100%;">
              <input class="plyrcard-drawer-input" type="text" value="{{ $plyrWebsiteShareUrl }}" readonly data-plyrcard-copy-source>
              <button type="button" class="plyrcard-copy-btn" data-plyrcard-copy="{{ $plyrWebsiteShareUrl }}">Copy</button>
            </div>
            @if($plyrHasPremiumFeatures)
            <div class="plyrcard-share-options">
              <a class="plyrcard-secondary-btn" href="{{ $plyrFacebookUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i> Facebook</a>
              <a class="plyrcard-secondary-btn" href="{{ $plyrInstagramUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> Instagram</a>
              <a class="plyrcard-secondary-btn" href="{{ $plyrXUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i> X</a>
              <a class="plyrcard-submit-btn" href="{{ $plyrWebsiteShareUrl }}" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> Website</a>
            </div>
            @else
            <div class="plyrcard-locked-panel" style="width:100%;">
              <span class="plyrcard-locked-icon"><i class="fa-solid fa-lock"></i></span>
              <span><strong>Social sharing locked</strong><span>Upgrade to Plyr Plus or My Journey to unlock social share options.</span></span>
              <button type="button" class="plyrcard-submit-btn" data-plyrcard-section="upgrade">See Plans</button>
            </div>
            @endif
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="a-la-carte" data-title="A La Carte">
          <div class="plyrcard-offer-list">
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-regular fa-window-maximize"></i></span><span><h3 class="plyrcard-offer-title">Upgraded Site Design</h3><p class="plyrcard-offer-copy">A full redesign of your athlete website</p></span><strong class="plyrcard-offer-price">$150<small>One-time</small></strong></a>
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-regular fa-images"></i></span><span><h3 class="plyrcard-offer-title">Starting Graphics Bundle</h3><p class="plyrcard-offer-copy">Starting graphic • Showcase graphic • Thank You graphic</p></span><strong class="plyrcard-offer-price">$70<small>Bundle</small></strong></a>
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-solid fa-pen-nib"></i></span><span><h3 class="plyrcard-offer-title">Individual Graphic</h3><p class="plyrcard-offer-copy">Single custom athlete graphic</p></span><strong class="plyrcard-offer-price">$35<small>Each</small></strong></a>
            <a href="#" class="plyrcard-offer-card"><span class="plyrcard-offer-icon"><i class="fa-solid fa-globe"></i></span><span><h3 class="plyrcard-offer-title">Domain</h3><p class="plyrcard-offer-copy">Custom domain registration for your athlete site</p></span><strong class="plyrcard-offer-price">$45<small>/Year</small></strong></a>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="book-demo" data-title="Book a Call">
          <div class="plyrcard-booking-wrap">
            <iframe src="https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l" scrolling="no" id="SvuQy1svAyETQ5Q9px9l_1778163042192"></iframe>
          </div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="dashboard" data-title="Dashboard">
          <div class="plyrcard-mini-panel plyrcard-dashboard-layout">
            <div>
              <h3 class="plyrcard-mini-title">Dashboard</h3>
              <p class="plyrcard-mini-copy">Your PlyrCard performance, profile completion, and next steps.</p>
            </div>

            <div class="plyrcard-stat-grid">
              <div class="plyrcard-stat-card"><i class="fa-regular fa-eye"></i><span class="plyrcard-stat-value">{{ number_format($plyrCardViews) }}</span><span class="plyrcard-stat-label">Card Views</span></div>
              <div class="plyrcard-stat-card"><i class="fa-regular fa-star"></i><span class="plyrcard-stat-value">{{ $plyrCardScore }}</span><span class="plyrcard-stat-label">Card Score</span></div>
              <div class="plyrcard-stat-card"><i class="fa-solid fa-chart-pie"></i><span class="plyrcard-stat-value">{{ $plyrProfileCompletion }}%</span><span class="plyrcard-stat-label">Profile Complete</span></div>
            </div>

            <div class="plyrcard-dashboard-summary">
              <div class="plyrcard-dashboard-ring" style="--value: {{ $plyrProfileCompletion }}%;"><span>{{ $plyrProfileCompletion }}%</span></div>
              <div>
                <h4 class="plyrcard-dashboard-panel-title">Profile Progress</h4>
                <p class="plyrcard-mini-copy">{{ $plyrProfileCompletionLabel }}</p>
                <div class="plyrcard-progress-shell"><div class="plyrcard-progress-fill" style="--value: {{ $plyrProfileCompletion }}%;"></div></div>
                <button type="button" class="plyrcard-submit-btn" data-plyrcard-section="profile"><i class="fa-solid fa-user-pen"></i> Complete Profile</button>
              </div>
            </div>

            <div class="plyrcard-dashboard-grid-two">
              <div class="plyrcard-form-card plyrcard-form-stack">
                <div class="plyrcard-clean-row">
                  <div>
                    <h4 class="plyrcard-dashboard-panel-title">Missing profile parts</h4>
                    <p class="plyrcard-mini-copy">{{ $plyrMissingProfileTotal }} remaining across {{ count($plyrMissingProfileSections) }} section(s).</p>
                  </div>
                </div>

                @forelse($plyrMissingProfileSections as $sectionName => $missingItems)
                  <div class="plyrcard-missing-section">
                    <div class="plyrcard-missing-head">
                      <div>
                        <h5 class="plyrcard-missing-title">{{ $sectionName }}</h5>
                        <span class="plyrcard-missing-count">{{ count($missingItems) }} missing item{{ count($missingItems) === 1 ? '' : 's' }}</span>
                      </div>
                      <button type="button" class="plyrcard-missing-edit" data-plyrcard-section="profile"><i class="fa-regular fa-pen-to-square"></i> Edit section</button>
                    </div>
                    <div class="plyrcard-missing-list">
                      @foreach($missingItems as $missingLabel)
                        <span class="plyrcard-missing-item"><i class="fa-solid fa-circle-exclamation"></i>{{ $missingLabel }}</span>
                      @endforeach
                    </div>
                  </div>
                @empty
                  <div class="plyrcard-empty-state">
                    <i class="fa-solid fa-circle-check"></i>
                    <h3 class="plyrcard-mini-title">Profile complete</h3>
                    <p class="plyrcard-mini-copy">All key profile sections are filled out.</p>
                  </div>
                @endforelse
              </div>

              <div class="plyrcard-form-card plyrcard-form-stack">
                <div class="plyrcard-clean-row">
                  <div>
                    <h4 class="plyrcard-dashboard-panel-title">Achievements</h4>
                    <p class="plyrcard-mini-copy">{{ $plyrUnlockedAchievements }}/{{ count($plyrAchievements) }} unlocked</p>
                  </div>
                </div>
                <div class="plyrcard-achievement-list">
                  @foreach($plyrAchievements as $achievement)
                    @php($achievementUnlocked = $plyrProfileCompletion >= $achievement['threshold'])
                    <div class="plyrcard-achievement-card {{ $achievementUnlocked ? 'is-unlocked' : '' }}">
                      <i class="fa-solid {{ $achievementUnlocked ? 'fa-trophy' : 'fa-lock' }}"></i>
                      <span>
                        <strong class="plyrcard-achievement-name">{{ $achievement['name'] }}</strong>
                        <small class="plyrcard-achievement-meta">Unlocks at {{ $achievement['threshold'] }}%</small>
                      </span>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>

            <div class="plyrcard-share-options">
              <button type="button" class="plyrcard-submit-btn" data-plyrcard-section="profile"><i class="fa-solid fa-user-pen"></i> Edit Profile</button>
              <button type="button" class="plyrcard-secondary-btn" data-plyrcard-section="schedule"><i class="fa-solid fa-calendar-days"></i> View Schedule</button>
            </div>
          </div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="profile" data-title="Profile">
          <form class="plyrcard-form-card plyrcard-form-stack" action="{{ $plyrProfileUpdateAction }}" method="POST" enctype="multipart/form-data" data-plyrcard-ajax-form data-success-message="Profile saved successfully.">
            @csrf
            <p class="plyrcard-mini-copy">Update your player profile. Your PlyrCard login email is locked and cannot be edited here.</p>

            <details class="plyrcard-profile-section" open>
              <summary><i class="fa-solid fa-user"></i> Basic Info</summary>
              <label class="plyrcard-input-label">Locked Login Email<span class="plyrcard-input-wrap"><i class="fa-solid fa-lock"></i><input class="plyrcard-drawer-input" type="email" value="{{ $plyrUser->email ?? '' }}" readonly disabled></span></label>
              <div class="plyrcard-profile-grid">
                <label class="plyrcard-input-label">First Name<span class="plyrcard-input-wrap"><i class="fa-regular fa-user"></i><input class="plyrcard-drawer-input" name="first_name" value="{{ old('first_name', $plyrUser->first_name ?? '') }}" placeholder="First name" required></span></label>
                <label class="plyrcard-input-label">Last Name<span class="plyrcard-input-wrap"><i class="fa-regular fa-user"></i><input class="plyrcard-drawer-input" name="last_name" value="{{ old('last_name', $plyrUser->last_name ?? '') }}" placeholder="Last name" required></span></label>
                <label class="plyrcard-input-label">Personal Email<span class="plyrcard-input-wrap"><i class="fa-regular fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="personal_email" value="{{ old('personal_email', $plyrUser->personal_email ?? '') }}" placeholder="personal@example.com"></span></label>
                <label class="plyrcard-input-label">Phone<span class="plyrcard-input-wrap"><i class="fa-solid fa-phone"></i><input class="plyrcard-drawer-input" name="phone" value="{{ old('phone', $plyrUser->phone ?? '') }}" placeholder="+1 (555) 000-0000"></span></label>
              </div>
            </details>

            <details class="plyrcard-profile-section">
              <summary><i class="fa-solid fa-location-dot"></i> Address</summary>
              <div class="plyrcard-profile-grid">
                <label class="plyrcard-input-label">Street Address<span class="plyrcard-input-wrap"><i class="fa-solid fa-map-pin"></i><input class="plyrcard-drawer-input" name="street" value="{{ old('street', $plyrUser->street ?? '') }}" placeholder="123 Main Street"></span></label>
                <label class="plyrcard-input-label">City<span class="plyrcard-input-wrap"><i class="fa-regular fa-building"></i><input class="plyrcard-drawer-input" name="city" value="{{ old('city', $plyrUser->city ?? '') }}" placeholder="City"></span></label>
                <label class="plyrcard-input-label">State / Province<span class="plyrcard-input-wrap"><i class="fa-regular fa-map"></i><input class="plyrcard-drawer-input" name="state" value="{{ old('state', $plyrUser->state ?? '') }}" placeholder="State / Province"></span></label>
                <label class="plyrcard-input-label">Country<span class="plyrcard-input-wrap"><i class="fa-solid fa-globe"></i><input class="plyrcard-drawer-input" name="country" value="{{ old('country', $plyrUser->country ?? '') }}" placeholder="Country"></span></label>
              </div>
            </details>

            <details class="plyrcard-profile-section" open>
              <summary><i class="fa-solid fa-trophy"></i> Athlete Info</summary>
              <div class="plyrcard-profile-grid">
                <label class="plyrcard-input-label">Sport<span class="plyrcard-input-wrap"><i class="fa-solid fa-medal"></i><select class="plyrcard-drawer-select" name="sport"><option value="">Select sport</option>@foreach(['basketball'=>'Basketball','volleyball'=>'Volleyball','football'=>'Football','baseball'=>'Baseball','softball'=>'Softball','soccer'=>'Soccer','tennis'=>'Tennis','badminton'=>'Badminton','table_tennis'=>'Table Tennis','track_and_field'=>'Track and Field','swimming'=>'Swimming','boxing'=>'Boxing','martial_arts'=>'Martial Arts'] as $sportValue => $sportLabel)<option value="{{ $sportValue }}" @selected(old('sport', $plyrUser->sport ?? '') === $sportValue)>{{ $sportLabel }}</option>@endforeach</select></span></label>
                <label class="plyrcard-input-label">Position(s)<span class="plyrcard-input-wrap"><i class="fa-solid fa-rectangle-list"></i><input class="plyrcard-drawer-input" name="position" value="{{ old('position', is_array($plyrUser->position ?? null) ? implode(', ', $plyrUser->position) : ($plyrUser->position ?? '')) }}" placeholder="Point Guard, Forward"></span></label>
                <label class="plyrcard-input-label">Roster Number<span class="plyrcard-input-wrap"><i class="fa-solid fa-hashtag"></i><input class="plyrcard-drawer-input" name="jersey_number" value="{{ old('jersey_number', $plyrUser->jersey_number ?? '') }}" placeholder="19"></span></label>
                <label class="plyrcard-input-label">Graduation Year<span class="plyrcard-input-wrap"><i class="fa-solid fa-graduation-cap"></i><input class="plyrcard-drawer-input" type="number" min="2000" max="2100" name="year" value="{{ old('year', $plyrUser->year ?? '') }}" placeholder="2027"></span></label>
                <label class="plyrcard-input-label">Sex<span class="plyrcard-input-wrap"><i class="fa-solid fa-user"></i><select class="plyrcard-drawer-select" name="gender"><option value="">Select sex</option><option value="male" @selected(old('gender', $plyrUser->gender ?? '') === 'male')>Male</option><option value="female" @selected(old('gender', $plyrUser->gender ?? '') === 'female')>Female</option></select></span></label>
                <label class="plyrcard-input-label">Birth Date<span class="plyrcard-input-wrap"><i class="fa-regular fa-calendar"></i><input class="plyrcard-drawer-input" type="date" name="birth" value="{{ old('birth', optional($plyrUser->birth ?? null)->format('Y-m-d') ?? ($plyrUser->birth ?? '')) }}"></span></label>
                <label class="plyrcard-input-label">GPA<span class="plyrcard-input-wrap"><i class="fa-solid fa-calculator"></i><input class="plyrcard-drawer-input" type="number" step="0.01" name="gpa" value="{{ old('gpa', $plyrUser->gpa ?? '') }}" placeholder="3.8"></span></label>
                <label class="plyrcard-input-label">Dominant Foot<span class="plyrcard-input-wrap"><i class="fa-solid fa-shoe-prints"></i><select class="plyrcard-drawer-select" name="dominant_foot"><option value="">Select dominant foot</option>@foreach(['left'=>'Left','right'=>'Right','both'=>'Both'] as $footValue => $footLabel)<option value="{{ $footValue }}" @selected(old('dominant_foot', $plyrUser->dominant_foot ?? '') === $footValue)>{{ $footLabel }}</option>@endforeach</select></span></label>
                <label class="plyrcard-input-label">Height<span class="plyrcard-input-wrap"><i class="fa-solid fa-arrows-up-down"></i><input class="plyrcard-drawer-input" name="height" value="{{ old('height', $plyrUser->height ?? '') }}" placeholder="5'10&quot;"></span></label>
                <label class="plyrcard-input-label">Weight<span class="plyrcard-input-wrap"><i class="fa-solid fa-weight-scale"></i><input class="plyrcard-drawer-input" name="weight" value="{{ old('weight', $plyrUser->weight ?? '') }}" placeholder="156"></span></label>
              </div>
            </details>

            <details class="plyrcard-profile-section">
              <summary><i class="fa-solid fa-flag"></i> Team & Experience</summary>
              <p class="plyrcard-mini-copy">These fields save existing IDs when your backend sends them. Team/league search can be upgraded later with AJAX.</p>
              <div class="plyrcard-profile-grid">
                <label class="plyrcard-input-label">School ID<span class="plyrcard-input-wrap"><i class="fa-solid fa-building-columns"></i><input class="plyrcard-drawer-input" name="school_id" value="{{ old('school_id', $plyrUser->school_id ?? '') }}" placeholder="School ID"></span></label>
                <label class="plyrcard-input-label">National Team ID<span class="plyrcard-input-wrap"><i class="fa-solid fa-flag"></i><input class="plyrcard-drawer-input" name="national_team_id" value="{{ old('national_team_id', $plyrUser->national_team_id ?? '') }}" placeholder="National Team ID"></span></label>
                <label class="plyrcard-input-label">National Team Period<span class="plyrcard-input-wrap"><i class="fa-regular fa-calendar"></i><input class="plyrcard-drawer-input" name="national_team_period" value="{{ old('national_team_period', $plyrUser->national_team_period ?? '') }}" placeholder="2025-2026"></span></label>
                <label class="plyrcard-input-label">Team Name<span class="plyrcard-input-wrap"><i class="fa-solid fa-users"></i><input class="plyrcard-drawer-input" name="team_name" value="{{ old('team_name', $plyrUser->team_name ?? '') }}" placeholder="Team name"></span></label>
              </div>
            </details>

            <details class="plyrcard-profile-section" open>
              <summary><i class="fa-solid fa-list-check"></i> Bio & Accolades</summary>
              <label class="plyrcard-input-label">Player Bio<span class="plyrcard-input-wrap textarea"><i class="fa-regular fa-pen-to-square"></i><textarea class="plyrcard-drawer-textarea" name="player_bio" placeholder="Write your athlete story...">{{ old('player_bio', $plyrUser->player_bio ?? '') }}</textarea></span></label>
              <label class="plyrcard-input-label">Academic Accolades<span class="plyrcard-input-wrap textarea"><i class="fa-solid fa-graduation-cap"></i><textarea class="plyrcard-drawer-textarea" name="academic_accolades" placeholder="Dean's List&#10;Honor Roll&#10;AP Scholar">{{ old('academic_accolades', $plyrUser->academic_accolades ?? '') }}</textarea></span></label>
              <label class="plyrcard-input-label">Sports Accolades<span class="plyrcard-input-wrap textarea"><i class="fa-solid fa-medal"></i><textarea class="plyrcard-drawer-textarea" name="sports_accolades" placeholder="Team Captain&#10;All-State Selection&#10;Tournament MVP">{{ old('sports_accolades', $plyrUser->sports_accolades ?? '') }}</textarea></span></label>
            </details>

            <details class="plyrcard-profile-section">
              <summary><i class="fa-solid fa-photo-film"></i> Media</summary>
              <p class="plyrcard-mini-copy">Upload images used across your card and player website.</p>
              <div class="plyrcard-profile-grid">
                <label class="plyrcard-input-label">PlyrCard Image<span class="plyrcard-input-wrap"><i class="fa-regular fa-image"></i><input class="plyrcard-drawer-input" type="file" name="plyrcard_image" accept="image/*"></span></label>
                <label class="plyrcard-input-label">Player Image<span class="plyrcard-input-wrap"><i class="fa-regular fa-image"></i><input class="plyrcard-drawer-input" type="file" name="player_image" accept="image/*"></span></label>
                <label class="plyrcard-input-label">Action Image<span class="plyrcard-input-wrap"><i class="fa-regular fa-image"></i><input class="plyrcard-drawer-input" type="file" name="action_image" accept="image/*"></span></label>
                <label class="plyrcard-input-label">Vertical Hero Image<span class="plyrcard-input-wrap"><i class="fa-regular fa-image"></i><input class="plyrcard-drawer-input" type="file" name="mobile_hero_image" accept="image/*"></span></label>
                <label class="plyrcard-input-label">YouTube Thumbnail<span class="plyrcard-input-wrap"><i class="fa-brands fa-youtube"></i><input class="plyrcard-drawer-input" type="file" name="youtube_thumbnail" accept="image/*"></span></label>
                <label class="plyrcard-input-label">National Team Image<span class="plyrcard-input-wrap"><i class="fa-regular fa-image"></i><input class="plyrcard-drawer-input" type="file" name="national_team_image" accept="image/*"></span></label>
              </div>
            </details>

            <details class="plyrcard-profile-section">
              <summary><i class="fa-solid fa-share-nodes"></i> Social & Press</summary>
              @unless($plyrHasPremiumFeatures)
                <div class="plyrcard-locked-panel">
                  <span class="plyrcard-locked-icon"><i class="fa-solid fa-lock"></i></span>
                  <span><strong>Unlock social & video links</strong><span>This feature is available on Plyr Plus and My Journey.</span></span>
                  <button type="button" class="plyrcard-submit-btn" data-plyrcard-section="upgrade">See Plans</button>
                </div>
              @endunless
              <div class="plyrcard-profile-grid {{ $plyrHasPremiumFeatures ? '' : 'plyrcard-locked-field' }}">
                <label class="plyrcard-input-label">Instagram Handle<span class="plyrcard-input-wrap"><i class="fa-brands fa-instagram"></i><input class="plyrcard-drawer-input" name="ig_handle" @unless($plyrHasPremiumFeatures) disabled aria-disabled="true" @endunless value="{{ old('ig_handle', $plyrUser->ig_handle ?? '') }}" placeholder="@handle"></span></label>
                <label class="plyrcard-input-label">X Handle<span class="plyrcard-input-wrap"><i class="fa-brands fa-x-twitter"></i><input class="plyrcard-drawer-input" name="x_handle" @unless($plyrHasPremiumFeatures) disabled aria-disabled="true" @endunless value="{{ old('x_handle', $plyrUser->x_handle ?? '') }}" placeholder="@handle"></span></label>
              </div>
              <label class="plyrcard-input-label">YouTube URL<span class="plyrcard-input-wrap"><i class="fa-brands fa-youtube"></i><input class="plyrcard-drawer-input" name="yt_url" @unless($plyrHasPremiumFeatures) disabled aria-disabled="true" @endunless value="{{ old('yt_url', $plyrUser->yt_url ?? '') }}" placeholder="https://youtube.com/@channel"></span></label>
              <label class="plyrcard-input-label">Featured Video URL<span class="plyrcard-input-wrap"><i class="fa-solid fa-play"></i><input class="plyrcard-drawer-input" name="featured_video_url" @unless($plyrHasPremiumFeatures) disabled aria-disabled="true" @endunless value="{{ old('featured_video_url', $plyrUser->featured_video_url ?? '') }}" placeholder="https://youtube.com/watch?v=..."></span></label>
              <label class="plyrcard-input-label">Featured Video URLs<span class="plyrcard-input-wrap textarea"><i class="fa-solid fa-list"></i><textarea class="plyrcard-drawer-textarea" name="featured_video_urls" @unless($plyrHasPremiumFeatures) disabled aria-disabled="true" @endunless placeholder="One YouTube URL per line">{{ old('featured_video_urls', $plyrUser->featured_video_urls ?? '') }}</textarea></span></label>
              <label class="plyrcard-input-label">Press<span class="plyrcard-input-wrap textarea"><i class="fa-regular fa-newspaper"></i><textarea class="plyrcard-drawer-textarea" name="press" @unless($plyrHasPremiumFeatures) disabled aria-disabled="true" @endunless placeholder="Article links, interviews, media coverage...">{{ old('press', $plyrUser->press ?? '') }}</textarea></span></label>
            </details>

            <details class="plyrcard-profile-section">
              <summary><i class="fa-solid fa-people-group"></i> Parents, Coaches & Trainers</summary>
              <div class="plyrcard-profile-grid">
                <label class="plyrcard-input-label">Primary Parent<span class="plyrcard-input-wrap"><i class="fa-regular fa-user"></i><input class="plyrcard-drawer-input" name="parent" value="{{ old('parent', $plyrUser->parent ?? '') }}" placeholder="Full name"></span></label>
                <label class="plyrcard-input-label">Parent Email<span class="plyrcard-input-wrap"><i class="fa-regular fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="parent_email" value="{{ old('parent_email', $plyrUser->parent_email ?? '') }}" placeholder="parent@example.com"></span></label>
                <label class="plyrcard-input-label">Parent Phone<span class="plyrcard-input-wrap"><i class="fa-solid fa-phone"></i><input class="plyrcard-drawer-input" name="parent_phone" value="{{ old('parent_phone', $plyrUser->parent_phone ?? '') }}" placeholder="+1 (555) 000-0000"></span></label>
                <label class="plyrcard-input-label">Secondary Parent<span class="plyrcard-input-wrap"><i class="fa-regular fa-user"></i><input class="plyrcard-drawer-input" name="sec_parent" value="{{ old('sec_parent', $plyrUser->sec_parent ?? '') }}" placeholder="Full name"></span></label>
                <label class="plyrcard-input-label">Secondary Email<span class="plyrcard-input-wrap"><i class="fa-regular fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="sec_parent_email" value="{{ old('sec_parent_email', $plyrUser->sec_parent_email ?? '') }}" placeholder="parent2@example.com"></span></label>
                <label class="plyrcard-input-label">Secondary Phone<span class="plyrcard-input-wrap"><i class="fa-solid fa-phone"></i><input class="plyrcard-drawer-input" name="sec_parent_phone" value="{{ old('sec_parent_phone', $plyrUser->sec_parent_phone ?? '') }}" placeholder="+1 (555) 000-0000"></span></label>
                <label class="plyrcard-input-label">Club Coach<span class="plyrcard-input-wrap"><i class="fa-solid fa-bullhorn"></i><input class="plyrcard-drawer-input" name="club_coach" value="{{ old('club_coach', $plyrUser->club_coach ?? '') }}" placeholder="Coach name"></span></label>
                <label class="plyrcard-input-label">Club Coach Email<span class="plyrcard-input-wrap"><i class="fa-regular fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="club_coach_email" value="{{ old('club_coach_email', $plyrUser->club_coach_email ?? '') }}" placeholder="coach@example.com"></span></label>
                <label class="plyrcard-input-label">Club Coach Phone<span class="plyrcard-input-wrap"><i class="fa-solid fa-phone"></i><input class="plyrcard-drawer-input" name="club_coach_phone" value="{{ old('club_coach_phone', $plyrUser->club_coach_phone ?? '') }}" placeholder="+1 (555) 000-0000"></span></label>
                <label class="plyrcard-input-label">National Team Coach<span class="plyrcard-input-wrap"><i class="fa-solid fa-flag"></i><input class="plyrcard-drawer-input" name="natl_coach" value="{{ old('natl_coach', $plyrUser->natl_coach ?? '') }}" placeholder="Coach name"></span></label>
                <label class="plyrcard-input-label">Technical Trainer<span class="plyrcard-input-wrap"><i class="fa-solid fa-bolt"></i><input class="plyrcard-drawer-input" name="tech_trainer" value="{{ old('tech_trainer', $plyrUser->tech_trainer ?? '') }}" placeholder="Trainer name"></span></label>
                <label class="plyrcard-input-label">Strength Trainer<span class="plyrcard-input-wrap"><i class="fa-solid fa-dumbbell"></i><input class="plyrcard-drawer-input" name="snc_trainer" value="{{ old('snc_trainer', $plyrUser->snc_trainer ?? '') }}" placeholder="Trainer name"></span></label>
              </div>
            </details>

            <button class="plyrcard-submit-btn" type="submit"><i class="fa-solid fa-check"></i> Save Profile</button>
          </form>
        </div>
        
<div class="plyrcard-drawer-view" data-plyrcard-view="edit-website" data-title="Edit Website">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Edit Website</h3><p class="plyrcard-mini-copy">Website editing tools can be embedded here so players stay on their card.</p><div class="plyrcard-share-options"><button type="button" class="plyrcard-submit-btn"><i class="fa-solid fa-pen-to-square"></i> Website Editor</button>@if($plyrWebsiteUrl)<a class="plyrcard-secondary-btn" href="{{ $plyrWebsiteUrl }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open Website</a>@endif</div></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="upgrade" data-title="Upgrade">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Upgrade</h3><p class="plyrcard-mini-copy">Current plan: {{ $plyrPlanName }}. Add your plan upgrade cards or checkout embed here.</p><div class="plyrcard-share-options"><button type="button" class="plyrcard-secondary-btn">Free</button><button type="button" class="plyrcard-secondary-btn">Plyr</button><button type="button" class="plyrcard-submit-btn">My Journey</button><button type="button" class="plyrcard-secondary-btn" data-plyrcard-section="a-la-carte">Add-ons</button></div></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="schedule" data-title="My Schedule">
          <div class="plyrcard-mini-panel plyrcard-form-stack">
            <div class="plyrcard-clean-row">
              <div>
                <h3 class="plyrcard-mini-title">My Schedule</h3>
                <p class="plyrcard-mini-copy">View your latest games and events.</p>
              </div>
              <button type="button" class="plyrcard-submit-btn" data-plyrcard-section="schedule-form"><i class="fa-solid fa-plus"></i> New</button>
            </div>
            <div class="plyrcard-stat-grid">
              <div class="plyrcard-stat-card"><i class="fa-solid fa-calendar-days"></i><span class="plyrcard-stat-value">{{ $plyrScheduleCount }}</span><span class="plyrcard-stat-label">Total Events</span></div>
              <div class="plyrcard-stat-card"><i class="fa-solid fa-flag"></i><span class="plyrcard-stat-value">{{ $plyrUpcomingScheduleCount }}</span><span class="plyrcard-stat-label">Upcoming</span></div>
              <div class="plyrcard-stat-card"><i class="fa-solid fa-house-flag"></i><span class="plyrcard-stat-value">{{ max(0, $plyrScheduleCount - $plyrUpcomingScheduleCount) }}</span><span class="plyrcard-stat-label">Past / Other</span></div>
            </div>
            <div class="plyrcard-schedule-list">
              @forelse($plyrSchedules as $schedule)
                <div class="plyrcard-schedule-card">
                  <div class="plyrcard-schedule-date"><i class="fa-regular fa-calendar"></i></div>
                  <div>
                    <h4 class="plyrcard-schedule-title">{{ $schedule->title ?: 'Game vs ' . ($schedule->opponent ?? 'Opponent') }}</h4>
                    <p class="plyrcard-schedule-meta">
                      @if(! blank($schedule->opponent)) vs {{ $schedule->opponent }} @endif
                      @if(! blank($schedule->game_date)) • {{ optional($schedule->game_date instanceof \Carbon\CarbonInterface ? $schedule->game_date : \Carbon\Carbon::parse($schedule->game_date))->format('M j, Y') }} @endif
                      @if(! blank($schedule->game_time)) • {{ $schedule->game_time }} @endif
                      @if(! blank($schedule->venue)) • {{ $schedule->venue }} @elseif(! blank($schedule->location)) • {{ $schedule->location }} @endif
                    </p>
                  </div>
                  <span class="plyrcard-schedule-badge">{{ $schedule->status ?: 'Upcoming' }}</span>
                </div>
              @empty
                <div class="plyrcard-empty-state">
                  <i class="fa-regular fa-calendar-plus"></i>
                  <h3 class="plyrcard-mini-title">No schedules yet</h3>
                  <p class="plyrcard-mini-copy">Add your first game, showcase, or event.</p>
                  <button type="button" class="plyrcard-submit-btn" data-plyrcard-section="schedule-form"><i class="fa-solid fa-plus"></i> New Schedule</button>
                </div>
              @endforelse
            </div>
          </div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="schedule-form" data-title="New Schedule">
          <form class="plyrcard-form-card plyrcard-form-stack" action="{{ $plyrScheduleStoreAction }}" method="POST" data-plyrcard-ajax-form data-success-message="Schedule saved successfully.">
            @csrf
            <p class="plyrcard-mini-copy">Add the game or event details below.</p>
            <label class="plyrcard-input-label">Title<span class="plyrcard-input-wrap"><i class="fa-solid fa-pen"></i><input class="plyrcard-drawer-input" name="title" placeholder="League Match"></span></label>
            <label class="plyrcard-input-label">Opponent<span class="plyrcard-input-wrap"><i class="fa-solid fa-shield-halved"></i><input class="plyrcard-drawer-input" name="opponent" placeholder="Opponent" required></span></label>
            <label class="plyrcard-input-label">Status<span class="plyrcard-input-wrap"><i class="fa-solid fa-flag"></i><select class="plyrcard-drawer-select" name="status"><option value="upcoming">Upcoming</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option><option value="postponed">Postponed</option></select></span></label>
            <label class="plyrcard-input-label">Game Date<span class="plyrcard-input-wrap"><i class="fa-regular fa-calendar"></i><input class="plyrcard-drawer-input" type="date" name="game_date"></span></label>
            <label class="plyrcard-input-label">Game Time<span class="plyrcard-input-wrap"><i class="fa-regular fa-clock"></i><input class="plyrcard-drawer-input" type="time" name="game_time"></span></label>
            <label class="plyrcard-input-label">Location<span class="plyrcard-input-wrap"><i class="fa-solid fa-location-dot"></i><input class="plyrcard-drawer-input" name="location" placeholder="City, State"></span></label>
            <label class="plyrcard-input-label">Venue<span class="plyrcard-input-wrap"><i class="fa-regular fa-building"></i><input class="plyrcard-drawer-input" name="venue" placeholder="Venue"></span></label>
            <label class="plyrcard-input-label">Result<span class="plyrcard-input-wrap"><i class="fa-solid fa-trophy"></i><input class="plyrcard-drawer-input" name="result" placeholder="Win / Loss"></span></label>
            <label class="plyrcard-input-label">Score<span class="plyrcard-input-wrap"><i class="fa-solid fa-hashtag"></i><input class="plyrcard-drawer-input" name="score" placeholder="3-1 / 58-61"></span></label>
            <label class="plyrcard-input-label">Notes<span class="plyrcard-input-wrap textarea"><i class="fa-regular fa-note-sticky"></i><textarea class="plyrcard-drawer-textarea" name="notes" placeholder="Schedule notes..."></textarea></span></label>
            <label class="plyrcard-clean-row" style="color:#111;font-size:13px;font-weight:800;"><span><input type="checkbox" name="is_home" value="1"> Home game</span></label>
            <div class="plyrcard-share-options"><button class="plyrcard-submit-btn" type="submit"><i class="fa-solid fa-calendar-check"></i> Save Schedule</button><button type="button" class="plyrcard-secondary-btn" data-plyrcard-section="schedule"><i class="fa-solid fa-list"></i> View List</button></div>
          </form>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="billing" data-title="Billing">
          <div class="plyrcard-mini-panel"><h3 class="plyrcard-mini-title">Billing</h3><p class="plyrcard-mini-copy">Current plan: {{ $plyrPlanName }}. Add your billing portal, invoices, or plan management embed here.</p><div class="plyrcard-share-options"><button type="button" class="plyrcard-secondary-btn">Plan</button><button type="button" class="plyrcard-secondary-btn">Invoices</button><button type="button" class="plyrcard-secondary-btn">Payment Method</button><button type="button" class="plyrcard-submit-btn" data-plyrcard-section="support">Billing Help</button></div></div>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="settings" data-title="Settings">
          <div class="plyrcard-mini-panel plyrcard-form-stack">
            <span class="plyrcard-upcoming-badge"><i class="fa-solid fa-clock"></i> Coming Soon</span>
            <h3 class="plyrcard-mini-title">Settings</h3>
            <p class="plyrcard-mini-copy">Account preferences are being prepared. For now, this section is disabled and will be available in a future update.</p>
          </div>
        </div>
      @else
        <div class="plyrcard-drawer-view is-active" data-plyrcard-view="main">
          <div class="plyrcard-nav-group"><strong class="plyrcard-nav-group-title">Contact</strong><div class="plyrcard-drawer-grid">
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="email-us"><i class="plyrcard-menu-icon fa-solid fa-envelope"></i><span>Email Us</span></button>
            <a class="plyrcard-drawer-card" href="sms:{{ $plyrPhoneHref }}"><i class="plyrcard-menu-icon fa-solid fa-comment-dots"></i><span>Text us</span></a>
            <a class="plyrcard-drawer-card" href="tel:{{ $plyrPhoneHref }}"><i class="plyrcard-menu-icon fa-solid fa-phone"></i><span>Call us</span></a>
            <a class="plyrcard-drawer-card" href="{{ $plyrFacebookUrl }}" target="_blank" rel="noopener"><i class="plyrcard-menu-icon fa-brands fa-facebook-messenger"></i><span>Chat Us</span></a>
          </div></div>
          <div class="plyrcard-nav-group"><strong class="plyrcard-nav-group-title">Start</strong><div class="plyrcard-drawer-grid">
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="share-site"><i class="plyrcard-menu-icon fa-solid fa-share-nodes"></i><span>Share</span></button>
            <button type="button" class="plyrcard-drawer-card" data-plyrcard-section="book-demo"><i class="plyrcard-menu-icon fa-solid fa-calendar-check"></i><span>Book Demo</span></button>
            <a class="plyrcard-drawer-card" href="/pricing"><i class="plyrcard-menu-icon fa-solid fa-user-plus"></i><span>Register Now</span></a>
            <button type="button" class="plyrcard-drawer-card is-accent" data-plyrcard-section="login"><i class="plyrcard-menu-icon fa-solid fa-right-to-bracket"></i><span>Login</span></button>
          </div></div>
        </div>

        <div class="plyrcard-drawer-view" data-plyrcard-view="email-us" data-title="Email Us"><div class="plyrcard-form-card"><a class="plyrcard-submit-btn" href="mailto:{{ $plyrSupportEmail }}"><i class="fa-solid fa-envelope"></i>{{ $plyrSupportEmail }}</a></div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="share-site" data-title="Share"><div class="plyrcard-form-card plyrcard-form-stack"><label class="plyrcard-input-label">PLYRCard URL</label><div class="plyrcard-copy-line"><input class="plyrcard-drawer-input" type="text" value="{{ $plyrMainShareUrl }}" readonly><button type="button" class="plyrcard-copy-btn" data-plyrcard-copy="{{ $plyrMainShareUrl }}">Copy</button></div></div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="book-demo" data-title="Book Demo"><div class="plyrcard-booking-wrap"><iframe src="https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l" scrolling="no" id="SvuQy1svAyETQ5Q9px9l_1778163042192"></iframe></div></div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="login" data-title="Login">
          <form class="plyrcard-form-card plyrcard-form-stack" method="POST" action="{{ $plyrDrawerLoginAction }}">
            @csrf
            <label class="plyrcard-input-label">Email<span class="plyrcard-input-wrap"><i class="fa-solid fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="email" placeholder="you@example.com" required></span></label>
            <label class="plyrcard-input-label">Password<span class="plyrcard-input-wrap"><i class="fa-solid fa-lock"></i><input class="plyrcard-drawer-input" type="password" name="password" placeholder="Password" required></span></label>
            <label class="plyrcard-clean-row" style="color:#111;font-size:13px;font-weight:800;"><span><input type="checkbox" name="remember" value="1"> Remember me</span><button type="button" class="plyrcard-text-link" data-plyrcard-section="forgot-password">Forgot Password?</button></label>
            <button class="plyrcard-submit-btn" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
            <div class="plyrcard-clean-row"><a class="plyrcard-secondary-btn" href="/pricing">Register</a><button type="button" class="plyrcard-secondary-btn" data-plyrcard-section="book-demo">Book Demo</button></div>
          </form>
        </div>
        <div class="plyrcard-drawer-view" data-plyrcard-view="forgot-password" data-title="Reset Password">
          <form class="plyrcard-form-card plyrcard-form-stack" method="POST" action="{{ url('/admin/password-reset/request') }}">
            @csrf
            <p class="plyrcard-mini-copy">Enter your email and we’ll send password reset instructions.</p>
            <label class="plyrcard-input-label">Email<span class="plyrcard-input-wrap"><i class="fa-solid fa-envelope"></i><input class="plyrcard-drawer-input" type="email" name="email" placeholder="you@example.com" required></span></label>
            <button class="plyrcard-submit-btn" type="submit"><i class="fa-solid fa-paper-plane"></i> Send Reset Link</button>
          </form>
        </div>
      @endauth
    </div>
  </section>

  <button type="button" class="plyrcard-drawer-tab" data-plyrcard-toggle-drawer aria-expanded="false">
    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
    <span>{{ $plyrTabLabel }}</span>
  </button>
</div>

@once
    <script src="https://systems.plyrcard.com/js/form_embed.js" type="text/javascript"></script>
@endonce

<script>
  (function () {
    let drawer = document.getElementById('plyrcard-action-drawer');
    if (!drawer) return;

    const expandedSections = ['dashboard', 'profile', 'schedule', 'schedule-form', 'book-demo', 'edit-website', 'settings', 'billing', 'upgrade'];
    let viewStack = ['main'];
    let currentView = 'main';
    let alertTimer = null;
    let isRefreshing = false;
    let pullStartY = null;
    let pullDistance = 0;

    function q(selector, root = drawer) { return root ? root.querySelector(selector) : null; }
    function qa(selector, root = drawer) { return root ? Array.from(root.querySelectorAll(selector)) : []; }

    function panel() { return q('.plyrcard-drawer-panel'); }
    function body() { return q('.plyrcard-drawer-body'); }
    function tabButton() { return q('[data-plyrcard-toggle-drawer]'); }

    function resetDrawerScroll() {
      const drawerBody = body();
      if (drawerBody) drawerBody.scrollTop = 0;
    }

    function ensureRefreshIndicator() {
      const drawerBody = body();
      if (!drawerBody || q('[data-plyrcard-refresh-indicator]')) return;
      const indicator = document.createElement('div');
      indicator.className = 'plyrcard-refresh-indicator';
      indicator.setAttribute('data-plyrcard-refresh-indicator', '');
      indicator.innerHTML = '<span class="plyrcard-btn-spinner" aria-hidden="true"></span><strong data-plyrcard-refresh-text>Refreshing...</strong>';
      drawerBody.insertBefore(indicator, drawerBody.firstChild);
    }

    function setRefreshVisible(visible, text = 'Refreshing...') {
      ensureRefreshIndicator();
      const indicator = q('[data-plyrcard-refresh-indicator]');
      const textNode = q('[data-plyrcard-refresh-text]');
      const p = panel();
      if (textNode) textNode.textContent = text;
      if (indicator) indicator.classList.toggle('is-visible', visible);
      if (p) p.classList.toggle('is-refreshing', visible);
    }

    function showAlert(message, isError = false) {
      const alertBox = q('[data-plyrcard-alert]');
      const alertText = q('[data-plyrcard-alert-text]');
      if (!alertBox || !alertText) return;
      alertText.textContent = message;
      alertBox.classList.toggle('is-error', isError);
      alertBox.classList.add('is-visible');
      window.clearTimeout(alertTimer);
      alertTimer = window.setTimeout(() => alertBox.classList.remove('is-visible'), 4600);
      const drawerBody = body();
      if (drawerBody) drawerBody.scrollTop = 0;
    }

    function ensureMainView() {
      const active = q('.plyrcard-drawer-view.is-active');
      if (!active || !active.querySelector('.plyrcard-drawer-card, .plyrcard-form-card, .plyrcard-mini-panel, .plyrcard-offer-list, .plyrcard-booking-wrap')) {
        showView('main', { push: false });
      }
    }

    function setOpen(isOpen) {
      if (isOpen) ensureMainView();
      drawer.classList.toggle('is-open', isOpen);
      drawer.dataset.state = isOpen ? 'open' : 'closed';
      const tab = tabButton();
      if (tab) tab.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      document.documentElement.classList.toggle('plyrcard-drawer-open', isOpen);
      if (isOpen) window.requestAnimationFrame(resetDrawerScroll);
    }

    function updateTitles(name) {
      const mainTitle = q('[data-plyrcard-main-title]');
      const subTitle = q('[data-plyrcard-sub-title]');
      const sectionTitle = q('[data-plyrcard-section-title]');
      const view = q('[data-plyrcard-view="' + name + '"]');
      const isMain = name === 'main';
      if (mainTitle) mainTitle.style.setProperty('display', isMain ? 'flex' : 'none', 'important');
      if (subTitle) subTitle.style.setProperty('display', isMain ? 'none' : 'flex', 'important');
      if (sectionTitle) sectionTitle.textContent = view?.dataset.title || '';
    }

    function showView(name, options = {}) {
      const push = options.push !== false;
      const view = q('[data-plyrcard-view="' + name + '"]');
      if (!view) return;

      const p = panel();
      if (p) {
        p.classList.toggle('is-expanded', expandedSections.includes(name));
        p.classList.remove('is-switching');
        void p.offsetWidth;
        p.classList.add('is-switching');
        window.setTimeout(() => p.classList.remove('is-switching'), 280);
      }

      qa('[data-plyrcard-view]').forEach(item => item.classList.toggle('is-active', item === view));
      resetDrawerScroll();
      updateTitles(name);

      if (push && currentView !== name) {
        viewStack.push(name);
      } else if (!push && viewStack[viewStack.length - 1] !== name) {
        viewStack[viewStack.length - 1] = name;
      }
      currentView = name;
    }

    function goBack() {
      if (viewStack.length > 1) {
        viewStack.pop();
        const previous = viewStack[viewStack.length - 1] || 'main';
        showView(previous, { push: false });
        currentView = previous;
        return;
      }
      showView('main', { push: false });
    }

    function serializeSuccessfulFormValues(form) {
      const values = {};
      if (!form) return values;
      const fd = new FormData(form);
      fd.forEach((value, key) => {
        if (key === '_token') return;
        if (values[key] !== undefined) {
          if (!Array.isArray(values[key])) values[key] = [values[key]];
          values[key].push(value);
        } else {
          values[key] = value;
        }
      });
      return values;
    }

    function applyFormValuesLocally(form) {
      const values = serializeSuccessfulFormValues(form);
      Object.keys(values).forEach(name => {
        qa('[name="' + CSS.escape(name) + '"]').forEach(field => {
          if (field.form === form) return;
          if (field.type === 'checkbox' || field.type === 'radio') {
            field.checked = Array.isArray(values[name]) ? values[name].includes(field.value) : values[name] == field.value;
          } else if (field.tagName === 'SELECT' && field.multiple && Array.isArray(values[name])) {
            Array.from(field.options).forEach(option => option.selected = values[name].includes(option.value));
          } else if (field.type !== 'file') {
            field.value = Array.isArray(values[name]) ? values[name].join(', ') : values[name];
          }
        });
      });
    }

    async function refreshDrawerFromServer(options = {}) {
      if (isRefreshing) return false;
      isRefreshing = true;
      const keepView = options.keepView || currentView || 'main';
      const wasOpen = drawer.classList.contains('is-open');
      setRefreshVisible(!options.silent, options.text || 'Refreshing...');

      try {
        const response = await fetch(window.location.href, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html, application/xhtml+xml',
          },
        });
        if (!response.ok) throw new Error('Refresh failed');
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const freshDrawer = doc.getElementById('plyrcard-action-drawer');
        const freshBody = freshDrawer?.querySelector('.plyrcard-drawer-body');
        const currentBody = body();

        if (freshBody && currentBody) {
          currentBody.innerHTML = freshBody.innerHTML;
          ensureRefreshIndicator();
          bindDynamicHandlers();
          bindPullToRefresh();
          const viewExists = q('[data-plyrcard-view="' + keepView + '"]');
          showView(viewExists ? keepView : 'main', { push: false });
          if (wasOpen) setOpen(true);
          return true;
        }
      } catch (error) {
        if (!options.silent) showAlert('Unable to refresh right now.', true);
      } finally {
        setRefreshVisible(false);
        isRefreshing = false;
      }
      return false;
    }

    function bindDynamicHandlers() {
      ensureRefreshIndicator();

      qa('[data-plyrcard-alert-close]').forEach(button => {
        if (button.dataset.plyrBound) return;
        button.dataset.plyrBound = '1';
        button.addEventListener('click', () => q('[data-plyrcard-alert]')?.classList.remove('is-visible'));
      });

      qa('[data-plyrcard-ajax-form]').forEach(form => {
        if (form.dataset.plyrAjaxBound) return;
        form.dataset.plyrAjaxBound = '1';
        form.addEventListener('submit', async event => {
          event.preventDefault();

          const submitButton = form.querySelector('button[type="submit"], .plyrcard-submit-btn[type="submit"]');
          const originalHtml = submitButton ? submitButton.innerHTML : '';
          const action = form.getAttribute('action') || '#';

          if (!action || action === '#') {
            showAlert('This form is not connected yet. Add the route/controller to enable saving.', true);
            return;
          }

          if (submitButton) {
            submitButton.disabled = true;
            submitButton.classList.add('is-loading');
            submitButton.innerHTML = '<span class="plyrcard-btn-spinner" aria-hidden="true"></span> Saving...';
          }

          const formData = new FormData(form);
          const token = formData.get('_token') || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

          try {
            const response = await fetch(action, {
              method: (form.getAttribute('method') || 'POST').toUpperCase(),
              body: formData,
              credentials: 'same-origin',
              headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json, text/html, */*',
              },
            });

            if (!response.ok) {
              let errorMessage = 'Something went wrong. Please check the form and try again.';
              try {
                const payload = await response.clone().json();
                if (payload?.message) errorMessage = payload.message;
                if (payload?.errors) {
                  const firstError = Object.values(payload.errors).flat()[0];
                  if (firstError) errorMessage = firstError;
                }
              } catch (error) {}
              showAlert(errorMessage, true);
              return;
            }

            applyFormValuesLocally(form);
            showAlert(form.dataset.successMessage || 'Saved successfully.');
            form.dispatchEvent(new CustomEvent('plyrcard:ajax-saved', { bubbles: true }));
            await refreshDrawerFromServer({ silent: true, keepView: currentView });
          } catch (error) {
            showAlert('Unable to save right now. Please try again.', true);
          } finally {
            if (submitButton) {
              submitButton.disabled = false;
              submitButton.classList.remove('is-loading');
              submitButton.innerHTML = originalHtml;
            }
          }
        });
      });

      qa('[data-plyrcard-copy]').forEach(button => {
        if (button.dataset.plyrCopyBound) return;
        button.dataset.plyrCopyBound = '1';
        button.addEventListener('click', async () => {
          const value = button.getAttribute('data-plyrcard-copy') || '';
          try {
            await navigator.clipboard.writeText(value);
            const original = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => button.textContent = original, 1400);
          } catch (error) {
            window.prompt('Copy this URL:', value);
          }
        });
      });
    }

    function bindStaticHandlers() {
      document.querySelectorAll('[data-plyrcard-toggle-drawer], [data-plyrcard-open-drawer]').forEach(button => {
        if (button.dataset.plyrToggleBound) return;
        button.dataset.plyrToggleBound = '1';
        button.addEventListener('click', () => setOpen(!drawer.classList.contains('is-open')));
      });

      qa('[data-plyrcard-close-drawer]').forEach(button => {
        if (button.dataset.plyrCloseBound) return;
        button.dataset.plyrCloseBound = '1';
        button.addEventListener('click', () => { setOpen(false); showView('main', { push: false }); viewStack = ['main']; });
      });

      const backButton = q('[data-plyrcard-back]');
      if (backButton && !backButton.dataset.plyrBackBound) {
        backButton.dataset.plyrBackBound = '1';
        backButton.addEventListener('click', goBack);
      }

      qa('[data-plyrcard-logout-form]').forEach(form => {
        if (form.dataset.plyrLogoutBound) return;
        form.dataset.plyrLogoutBound = '1';
        form.addEventListener('submit', async event => {
          event.preventDefault();

          const formData = new FormData(form);
          const token = formData.get('_token') || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
          const button = form.querySelector('button[type="submit"]');
          const originalHtml = button ? button.innerHTML : '';
          if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="plyrcard-btn-spinner" aria-hidden="true"></span> Signing out...';
          }

          try {
            await fetch(form.action, {
              method: 'POST',
              body: formData,
              credentials: 'same-origin',
              headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html, application/xhtml+xml',
              },
            });
          } catch (error) {}

          window.location.assign('/');
          if (button) button.innerHTML = originalHtml;
        });
      });
    }

    drawer.addEventListener('click', event => {
      const sectionButton = event.target.closest('[data-plyrcard-section]');
      if (!sectionButton || !drawer.contains(sectionButton)) return;
      event.preventDefault();
      showView(sectionButton.dataset.plyrcardSection, { push: true });
      setOpen(true);
    });

    function bindPullToRefresh() {
      const p = panel();
      const drawerBody = body();
      if (!p || !drawerBody || drawerBody.dataset.plyrPullBound) return;
      drawerBody.dataset.plyrPullBound = '1';

      drawerBody.addEventListener('touchstart', event => {
        if (!drawer.classList.contains('is-open') || drawerBody.scrollTop > 0 || isRefreshing) return;
        pullStartY = event.touches[0].clientY;
        pullDistance = 0;
      }, { passive: true });

      drawerBody.addEventListener('touchmove', event => {
        if (pullStartY === null || drawerBody.scrollTop > 0 || isRefreshing) return;
        pullDistance = event.touches[0].clientY - pullStartY;
        if (pullDistance > 24) {
          p.classList.add('is-pulling');
          const textNode = q('[data-plyrcard-refresh-text]');
          if (textNode) textNode.textContent = pullDistance > 88 ? 'Release to refresh' : 'Pull to refresh';
        }
      }, { passive: true });

      drawerBody.addEventListener('touchend', () => {
        if (pullStartY === null) return;
        const shouldRefresh = pullDistance > 88;
        pullStartY = null;
        pullDistance = 0;
        p.classList.remove('is-pulling');
        if (shouldRefresh) refreshDrawerFromServer({ keepView: currentView, text: 'Refreshing...' });
      }, { passive: true });
    }

    bindStaticHandlers();
    bindDynamicHandlers();
    bindPullToRefresh();
    showView('main', { push: false });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        setOpen(false);
        showView('main', { push: false });
        viewStack = ['main'];
      }
    });
  })();
</script>
@endif