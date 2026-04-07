<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $user = $website->user;

        /*
        |--------------------------------------------------------------------------
        | Theme Colors
        |--------------------------------------------------------------------------
        */
        $primary   = $website->primary_color ?: '#334155';
        $secondary = $website->secondary_color ?: '#0f172a';
        $accent    = $website->accent_color ?: '#2563eb';
        $bg        = $website->background_color ?: '#f8fafc';
        $surface   = $website->surface_color ?: '#ffffff';
        $text1     = $website->text_primary_color ?: '#0f172a';
        $text2     = $website->text_secondary_color ?: '#475569';

        /*
        |--------------------------------------------------------------------------
        | Field Value Helpers
        |--------------------------------------------------------------------------
        */
        $fieldValues = $website->relationLoaded('fieldValues')
            ? $website->fieldValues
            : $website->fieldValues()->with('templateField')->get();

        $getFieldRecord = function (string $fieldName) use ($fieldValues) {
            return $fieldValues->first(function ($item) use ($fieldName) {
                return optional($item->templateField)->name === $fieldName;
            });
        };

        $getFieldValue = function (string $fieldName, $default = null) use ($getFieldRecord) {
            $record = $getFieldRecord($fieldName);
            return $record?->value ?? $default;
        };

        $getJsonFieldValue = function (string $fieldName, $default = null) use ($getFieldValue) {
            $raw = $getFieldValue($fieldName);

            if (blank($raw)) {
                return $default;
            }

            if (is_array($raw)) {
                return $raw;
            }

            $decoded = json_decode($raw, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        };

        $hideIfDefault = function ($value) {
            if (! is_string($value) || $value === '') {
                return $value;
            }

            return str_starts_with(trim($value), '[DEFAULT PLACEHOLDER:') ? '' : $value;
        };

        /*
        |--------------------------------------------------------------------------
        | Hero Field Helpers
        |--------------------------------------------------------------------------
        */
        $heroFieldValues = $website->relationLoaded('heroFieldValues')
            ? $website->heroFieldValues
            : $website->heroFieldValues()->with('templateField')->get();

        $getHeroFieldRecord = function (string $fieldName) use ($heroFieldValues) {
            return $heroFieldValues->first(function ($item) use ($fieldName) {
                return optional($item->templateField)->name === $fieldName;
            });
        };

        $getHeroFieldValue = function (string $fieldName, $default = null) use ($getHeroFieldRecord) {
            $record = $getHeroFieldRecord($fieldName);
            return $record?->value ?? $default;
        };

        /*
        |--------------------------------------------------------------------------
        | YouTube Helpers
        |--------------------------------------------------------------------------
        */
        $toYoutubeEmbed = function (string $url) {
            $url = trim($url);
            if ($url === '') {
                return null;
            }

            $videoId = null;

            if (preg_match('~youtu\.be/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId && preg_match('~v=([^&]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId && preg_match('~youtube\.com/shorts/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId && preg_match('~youtube\.com/embed/([^?&/]+)~', $url, $m)) {
                $videoId = $m[1];
            }

            if (! $videoId) {
                return null;
            }

            $params = http_build_query([
                'rel' => 0,
                'modestbranding' => 1,
                'playsinline' => 1,
            ]);

            return "https://www.youtube.com/embed/{$videoId}?{$params}";
        };

        $parseUrlList = function ($raw) use ($toYoutubeEmbed) {
            if (! is_string($raw) || trim($raw) === '') {
                return [];
            }

            $raw = str_replace(["\r\n", "\r"], "\n", $raw);
            $parts = preg_split('/\n|,/', $raw);

            $out = [];
            foreach ($parts as $p) {
                $embed = $toYoutubeEmbed(trim($p));
                if ($embed) {
                    $out[] = $embed;
                }
            }

            return array_values(array_unique($out));
        };

        /*
        |--------------------------------------------------------------------------
        | Media Helpers
        |--------------------------------------------------------------------------
        */
        $resolveMediaUrl = function ($raw, $fallback = '') {
            if (blank($raw)) {
                return $fallback;
            }

            if (is_string($raw)) {
                $trimmed = trim($raw);

                if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                    return $trimmed;
                }

                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $raw = $decoded;
                } else {
                    return asset('storage/' . ltrim($trimmed, '/'));
                }
            }

            if (is_array($raw)) {
                if (isset($raw[0])) {
                    $first = $raw[0];

                    if (is_string($first)) {
                        return filter_var($first, FILTER_VALIDATE_URL)
                            ? $first
                            : asset('storage/' . ltrim($first, '/'));
                    }

                    if (is_array($first)) {
                        $path = $first['url'] ?? $first['path'] ?? $first['image_url'] ?? null;

                        if ($path) {
                            return filter_var($path, FILTER_VALIDATE_URL)
                                ? $path
                                : asset('storage/' . ltrim($path, '/'));
                        }
                    }
                }

                $path = $raw['url'] ?? $raw['path'] ?? $raw['image_url'] ?? null;

                if ($path) {
                    return filter_var($path, FILTER_VALIDATE_URL)
                        ? $path
                        : asset('storage/' . ltrim($path, '/'));
                }
            }

            return $fallback;
        };

        /*
        |--------------------------------------------------------------------------
        | Content Fields
        |--------------------------------------------------------------------------
        */
        $listifyText = function ($value) {
            if (blank($value)) {
                return '';
            }

            $text = is_string($value) ? $value : (string) $value;
            $text = str_replace(["\r\n", "\r"], "\n", $text);

            $lines = collect(explode("\n", $text))
                ->map(fn ($line) => trim(strip_tags($line)))
                ->filter()
                ->values();

            if ($lines->isEmpty()) {
                return '';
            }

            return '<ul>' . $lines->map(fn ($line) => '<li>' . e($line) . '</li>')->implode('') . '</ul>';
        };

        $playerDisplayName = trim(
            ($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')
        );

        $leagueOrClub = $user?->club?->league?->name
            ?? $user?->club?->name
            ?? $user?->team_name
            ?? '';

        $gradYearText = filled($user?->year) ? "CLASS OF " . $user->year : '';

        $defaultPlayerTagline = collect([
            filled($playerDisplayName) ? strtoupper($playerDisplayName) : null,
            filled($leagueOrClub) ? strtoupper($leagueOrClub) : null,
            filled($gradYearText) ? strtoupper($gradYearText) : null,
        ])->filter(fn ($value) => filled($value))->implode(' | ');

        $aboutHeadline = $hideIfDefault($getFieldValue('aboutme_headline', ''))
            ?: 'About Me in 60 Seconds';

        $aboutTagline = $hideIfDefault($getFieldValue('player_tagline', ''))
            ?: $defaultPlayerTagline;

        $aboutBio = $hideIfDefault(
            filled($user?->player_bio)
                ? $user->player_bio
                : $getFieldValue('player_bio', '')
        );

        $scheduleHeadline = $hideIfDefault($getFieldValue('schedules_headline', ''))
            ?: 'Games Schedule';

        $scheduleTagline = $hideIfDefault($getFieldValue('schedules_tagline', ''))
            ?: 'Upcoming games and key dates.';

        $highHeadline = $hideIfDefault($getFieldValue('highlights_headline', ''))
            ?: 'Game Highlights';

        $highTagline = $hideIfDefault($getFieldValue('highlights_tagline', ''))
            ?: 'Top plays and standout moments.';

        $websiteAcademicAccolades = $getFieldValue('academic_accolades', '');
        $websiteSportsAccolades = $getFieldValue('sports_accolades', '');

        $acadRaw = filled($websiteAcademicAccolades)
            ? $websiteAcademicAccolades
            : ($user?->academic_accolades ?? '');

        $sportRaw = filled($websiteSportsAccolades)
            ? $websiteSportsAccolades
            : ($user?->sports_accolades ?? '');

        $acadHeadline = $hideIfDefault($getFieldValue('acad_accolades_headline', ''))
            ?: 'Academic Accolades';

        $acadTagline = $hideIfDefault($getFieldValue('acad_accolades_tagline', ''))
            ?: 'A collection of awards & accomplishments';

        $acadBody = $listifyText($hideIfDefault($acadRaw));

        $sportHeadline = $hideIfDefault($getFieldValue('sport_accolades_headline', ''))
            ?: 'Sports Accolades';

        $sportTagline = $hideIfDefault($getFieldValue('sport_accolades_tagline', ''))
            ?: 'A collection of awards & accomplishments';

        $sportBody = $listifyText($hideIfDefault($sportRaw));

        $hasAcademicAccolades = filled(trim(strip_tags($acadBody)));
        $hasSportsAccolades = filled(trim(strip_tags($sportBody)));
        $hasAnyAccolades = $hasAcademicAccolades || $hasSportsAccolades;

        $contactFormEmbed = $hideIfDefault($getFieldValue('contact_form_embed', ''));

        if (blank($contactFormEmbed)) {
            $contactFormEmbed = <<<'HTML'
<iframe
    src="https://systems.plyrcard.com/widget/form/fNo3I29CD8EJ0N4bzMuA"
    style="width:100%;height:100%;border:none;border-radius:4px"
    id="inline-fNo3I29CD8EJ0N4bzMuA"
    data-layout="{'id':'INLINE'}"
    data-trigger-type="alwaysShow"
    data-trigger-value=""
    data-activation-type="alwaysActivated"
    data-activation-value=""
    data-deactivation-type="neverDeactivate"
    data-deactivation-value=""
    data-form-name="Follow Me Form - Coach"
    data-height="450"
    data-layout-iframe-id="inline-fNo3I29CD8EJ0N4bzMuA"
    data-form-id="fNo3I29CD8EJ0N4bzMuA"
    title="Follow Me Form - Coach"
>
</iframe>
<script src="https://systems.plyrcard.com/js/form_embed.js"></script>
HTML;
        }

        $aboutVideoUrls = filled($user?->featured_video_url)
            ? $user->featured_video_url
            : $getFieldValue('yt_embed', '');

        $aboutVideos = $parseUrlList($aboutVideoUrls);
        $manualVideoSource = filled($user?->featured_video_urls)
            ? $user->featured_video_urls
            : $getFieldValue('yt_playlist_embed', '');

        $manualHighlightVideos = $parseUrlList($manualVideoSource);

        $highlightVideos = !empty($manualHighlightVideos)
            ? collect($manualHighlightVideos)->map(fn ($url) => [
                'embed_url' => $url,
                'title' => 'YouTube video',
            ])->values()->all()
            : ($autoHighlightVideos ?? []);

        if (empty($aboutVideos) && !empty($highlightVideos)) {
            $firstEmbed = $highlightVideos[0]['embed_url'] ?? null;
            $aboutVideos = $firstEmbed ? [$firstEmbed] : [];
        }

        $aboutThumbnailUrl = $resolveMediaUrl(
            filled($user?->youtube_thumbnail)
                ? $user->youtube_thumbnail
                : $getJsonFieldValue('highlights_thumbnail', $getFieldValue('highlights_thumbnail')),
            asset('temp-thumbnail.png')
        );

        $footerClubLogoUrl = $resolveMediaUrl(
            $user?->club?->logo ?? '',
            ''
        );

        $footerLeagueLogoUrl = $resolveMediaUrl(
            $user?->league?->logo ?? $user?->club?->league?->logo ?? '',
            ''
        );

        $footerNationalTeamLogoUrl = $resolveMediaUrl(
            $user?->nationalTeam?->logo ?? '',
            ''
        );

        /*
        |--------------------------------------------------------------------------
        | Player Card Media
        |--------------------------------------------------------------------------
        */
        $playerCardImageUrl = $resolveMediaUrl($user?->plyrcard_image, '');

        /*
        |--------------------------------------------------------------------------
        | Hero Media
        |--------------------------------------------------------------------------
        */
        $heroPlyrCardUrl = $resolveMediaUrl($getHeroFieldValue('hero_plyrcard_image'), '');
        $heroBallLogoUrl = $resolveMediaUrl($getHeroFieldValue('hero_ball_logo'), '');
        $heroCompositeImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_composite_image'), '');
        $heroBackgroundImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_background_image'), '');
        $heroMobileImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_mobile_image'), '');

        /*
        |--------------------------------------------------------------------------
        | Schedules
        |--------------------------------------------------------------------------
        */
        $playerSchedules = collect(
            $user?->createdSchedules
                ? $user->createdSchedules
                    ->sortBy([
                        ['game_date', 'asc'],
                        ['game_time', 'asc'],
                    ])
                    ->values()
                    ->all()
                : []
        );

        $formatScheduleTitle = function ($schedule) {
            $opponent = trim((string) ($schedule->opponent ?? ''));
            $title = trim((string) ($schedule->title ?? ''));

            if ($opponent !== '') {
                return ($schedule->is_home ? 'Home vs ' : 'Away @ ') . $opponent;
            }

            return $title !== '' ? $title : 'Game Day';
        };

        $schedulePayload = $playerSchedules
            ->filter(fn ($schedule) => ! blank($schedule->game_date))
            ->map(function ($schedule) use ($formatScheduleTitle) {
                $date = optional($schedule->game_date);

                $timeDisplay = 'Time TBD';
                $timeSortable = null;

                if (! blank($schedule->game_time)) {
                    try {
                        $parsedTime = $schedule->game_time instanceof \Carbon\CarbonInterface
                            ? $schedule->game_time
                            : \Carbon\Carbon::parse($schedule->game_time);

                        $timeDisplay = $parsedTime->format('g:i A');
                        $timeSortable = $parsedTime->format('H:i:s');
                    } catch (\Throwable $e) {
                        $timeDisplay = 'Time TBD';
                        $timeSortable = null;
                    }
                }

                $locationLine = collect([
                    $schedule->location,
                    $schedule->venue,
                ])->filter(fn ($value) => filled($value))->implode(' ');

                return [
                    'id' => $schedule->id,
                    'title' => $formatScheduleTitle($schedule),
                    'date' => $date?->format('Y-m-d'),
                    'year' => $date?->format('Y'),
                    'month' => $date?->format('m'),
                    'month_label' => $date?->format('F'),
                    'month_year' => $date?->format('F Y'),
                    'day_name' => $date?->format('D'),
                    'day_number' => $date?->format('d'),
                    'month_short' => $date?->format('M'),
                    'full_date_label' => $date?->format('M d, Y'),
                    'time' => $timeDisplay,
                    'time_sortable' => $timeSortable,
                    'location' => $schedule->location ?? '',
                    'venue' => $schedule->venue ?? '',
                    'location_line' => $locationLine,
                    'notes' => $schedule->notes ?? '',
                    'opponent' => $schedule->opponent ?? '',
                    'is_home' => (bool) $schedule->is_home,
                    'status' => $schedule->status ?? '',
                    'result' => $schedule->result ?? '',
                    'score' => $schedule->score ?? '',
                    'search_blob' => strtolower(trim(collect([
                        $formatScheduleTitle($schedule),
                        $schedule->opponent,
                        $schedule->location,
                        $schedule->venue,
                        $schedule->notes,
                        $date?->format('F Y'),
                        $date?->format('M d, Y'),
                    ])->filter(fn ($value) => filled($value))->implode(' '))),
                ];
            })
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Contrast Helpers
        |--------------------------------------------------------------------------
        */
        $hexToRgb = function (string $hex) {
            $hex = ltrim(trim($hex), '#');

            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            if (strlen($hex) !== 6) {
                return [15, 23, 42];
            }

            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ];
        };

        $relativeLuminance = function (array $rgb) {
            $toLinear = function ($v) {
                $v = $v / 255;
                return ($v <= 0.03928) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
            };

            $r = $toLinear($rgb[0]);
            $g = $toLinear($rgb[1]);
            $b = $toLinear($rgb[2]);

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        };

        $contrastText = function (string $bgHex) use ($hexToRgb, $relativeLuminance) {
            $lum = $relativeLuminance($hexToRgb($bgHex));
            return ($lum < 0.55) ? '#ffffff' : '#0f172a';
        };

        $onPrimary   = $contrastText($primary);
        $onSecondary = $contrastText($secondary);

        /*
        |--------------------------------------------------------------------------
        | Coaching Staff
        |--------------------------------------------------------------------------
        */
        $playerEmail = $user->email ?? '';

        $coachRows = collect([
            [
                'name'  => $user->club_coach ?? '',
                'label' => 'HEAD COACH',
                'title' => $user->club?->name ?? ($user->team_name ?? ''),
                'email' => $user->club_coach_email ?? $playerEmail,
            ],
            [
                'name'  => $user->tech_trainer ?? '',
                'label' => 'TECHNICAL TRAINING & MENTORSHIP',
                'email' => $user->tech_trainer_email ?? $playerEmail,
            ],
            [
                'name'  => $user->snc_trainer ?? '',
                'label' => 'AGILITY AND STRENGTH TRAINING',
                'email' => $user->snc_trainer_email ?? $playerEmail,
            ],
            [
                'name'  => $user->natl_coach ?? '',
                'label' => 'NATIONAL TEAM COACH',
                'email' => $user->natl_coach_email ?? $playerEmail,
            ],
        ])->filter(fn ($c) => trim((string) ($c['name'] ?? '')) !== '')->values();

        /*
        |--------------------------------------------------------------------------
        | Footer / Social
        |--------------------------------------------------------------------------
        */
        $igUrl = '';
        if (! empty($user->ig_handle)) {
            $handle = ltrim(trim($user->ig_handle), '@');
            $igUrl = 'https://instagram.com/' . $handle;
        }

        $xUrl = '';
        if (! empty($user->x_handle)) {
            $handle = ltrim(trim($user->x_handle), '@');
            $xUrl = 'https://x.com/' . $handle;
        }

        $ytUrl = $user->yt_url ?? '';
        $footerPhone = $user->phone ?? '';
        $footerEmail = $user->email ?? '';
        $copyright   = 'Plyr Card 2026 © All Rights Reserved';

        $smsPhone = !empty($user->club_coach_phone) ? preg_replace('/\D+/', '', $user->club_coach_phone) : '';
        $textCoachUrl = $smsPhone ? 'sms:' . $smsPhone : ($playerEmail ? 'mailto:' . $playerEmail : '#');

        /*
        |--------------------------------------------------------------------------
        | SEO Helpers
        |--------------------------------------------------------------------------
        */
        $normalizePlainText = function ($value) {
            if (blank($value)) {
                return '';
            }

            $text = is_string($value) ? $value : json_encode($value);
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text);

            return trim((string) $text);
        };

        $truncate = function (?string $text, int $limit = 160) {
            $text = trim((string) $text);

            if ($text === '') {
                return '';
            }

            return \Illuminate\Support\Str::limit($text, $limit, '...');
        };

        $normalizeDisplayValue = function ($value, $separator = ' / ') {
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }

            if (is_array($value)) {
                return implode($separator, array_filter(array_map(function ($item) {
                    return is_scalar($item) ? (string) $item : '';
                }, $value)));
            }

            return filled($value) ? (string) $value : '';
        };

        $formatPositionDisplay = function ($value) use ($normalizeDisplayValue) {
            $position = $normalizeDisplayValue($value);

            if ($position === '') {
                return '';
            }

            return collect(explode(' / ', $position))
                ->map(fn ($item) => str($item)->replace('_', ' ')->title()->toString())
                ->implode(' / ');
        };

        /*
        |--------------------------------------------------------------------------
        | SEO Template Fields (overrides)
        |--------------------------------------------------------------------------
        */
        $seoTitleField = $normalizePlainText($getFieldValue('seo_title', ''));
        $seoDescriptionField = $normalizePlainText($getFieldValue('seo_description', ''));
        $seoKeywordsField = $normalizePlainText($getFieldValue('seo_keywords', ''));
        $seoRobotsField = $normalizePlainText($getFieldValue('seo_robots', ''));
        $seoCanonicalField = $normalizePlainText($getFieldValue('seo_canonical_url', ''));

        $ogTitleField = $normalizePlainText($getFieldValue('og_title', ''));
        $ogDescriptionField = $normalizePlainText($getFieldValue('og_description', ''));
        $ogImageField = $resolveMediaUrl($getFieldValue('og_image'), '');

        $twitterTitleField = $normalizePlainText($getFieldValue('twitter_title', ''));
        $twitterDescriptionField = $normalizePlainText($getFieldValue('twitter_description', ''));
        $twitterImageField = $resolveMediaUrl($getFieldValue('twitter_image'), '');

        $faviconField = $resolveMediaUrl($getFieldValue('favicon'), '');
        $schemaNameOverride = $normalizePlainText($getFieldValue('schema_override_name', ''));
        $schemaDescriptionOverride = $normalizePlainText($getFieldValue('schema_override_description', ''));

        /*
        |--------------------------------------------------------------------------
        | SEO Defaults
        |--------------------------------------------------------------------------
        */
        $playerName = trim(
            $website->name ?: (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''))
        );

        $sportText = filled($user?->sport)
            ? str($user->sport)->replace('_', ' ')->title()->toString()
            : '';

        $positionText = $formatPositionDisplay($user?->position ?? '');

        $schoolName = $user?->school?->name ?? '';
        $clubName = $user?->club?->name ?? ($user?->team_name ?? '');
        $leagueName = $user?->club?->league?->name ?? '';

        $locationText = collect([$user?->city, $user?->state])
            ->filter(fn ($v) => filled($v))
            ->implode(', ');

        $coachNamesForSeo = $coachRows->pluck('name')->filter()->implode(', ');

        $canonicalUrl = filled($seoCanonicalField) ? $seoCanonicalField : url()->current();

        $defaultSeoTitle = collect([
            $playerName,
            $positionText,
            $sportText,
            filled($schoolName) ? $schoolName : null,
            'PlyrCard',
        ])->filter(fn ($v) => filled($v))->implode(' | ');

        $defaultLongDescription = collect([
            filled($playerName) ? "{$playerName} athlete profile." : null,
            filled($positionText) ? "Position: {$positionText}." : null,
            filled($sportText) ? "Sport: {$sportText}." : null,
            filled($schoolName) ? "School: {$schoolName}." : null,
            filled($clubName) ? "Club/Team: {$clubName}." : null,
            filled($leagueName) ? "League: {$leagueName}." : null,
            filled($locationText) ? "Location: {$locationText}." : null,
            filled($aboutTagline) ? $aboutTagline : null,
            filled($aboutHeadline) ? $aboutHeadline . '.' : null,
            filled($aboutBio) ? $truncate($normalizePlainText($aboutBio), 220) : null,
            filled($sportBody) ? $truncate($normalizePlainText($sportBody), 180) : null,
            filled($acadBody) ? $truncate($normalizePlainText($acadBody), 180) : null,
            filled($coachNamesForSeo) ? "Coaching staff includes {$coachNamesForSeo}." : null,
        ])->filter(fn ($v) => filled($v))->implode(' ');

        $defaultSeoDescription = $truncate($defaultLongDescription, 160);

        $defaultKeywords = collect([
            $playerName,
            $sportText,
            $positionText,
            $schoolName,
            $clubName,
            $leagueName,
            $locationText,
            'athlete profile',
            'player profile',
            'student athlete',
            'recruiting profile',
            'PlyrCard',
        ])->filter(fn ($v) => filled($v))->implode(', ');

        $seoTitle = filled($seoTitleField) ? $seoTitleField : $defaultSeoTitle;
        $seoDescription = filled($seoDescriptionField)
            ? $truncate($seoDescriptionField, 160)
            : $defaultSeoDescription;
        $seoKeywords = filled($seoKeywordsField) ? $seoKeywordsField : $defaultKeywords;
        $seoRobots = filled($seoRobotsField) ? $seoRobotsField : 'index,follow';

        $shareImage = $ogImageField
            ?: $twitterImageField
            ?: $aboutThumbnailUrl
            ?: $heroCompositeImageUrl
            ?: $heroBackgroundImageUrl
            ?: $heroMobileImageUrl
            ?: $footerLogoUrl
            ?: $heroPlyrCardUrl
            ?: $playerCardImageUrl
            ?: asset('temp-thumbnail.png');

        $faviconUrl = $faviconField
            ?: $playerCardImageUrl
            ?: $heroPlyrCardUrl
            ?: asset('favicon.ico');

        $ogTitle = filled($ogTitleField) ? $ogTitleField : $seoTitle;
        $ogDescription = filled($ogDescriptionField)
            ? $truncate($ogDescriptionField, 200)
            : $seoDescription;

        $twitterTitle = filled($twitterTitleField) ? $twitterTitleField : $ogTitle;
        $twitterDescription = filled($twitterDescriptionField)
            ? $truncate($twitterDescriptionField, 200)
            : $ogDescription;

        $igHandle = filled($user?->ig_handle) ? '@' . ltrim(trim($user->ig_handle), '@') : '';
        $xHandle = filled($user?->x_handle) ? '@' . ltrim(trim($user->x_handle), '@') : '';

        $schemaName = filled($schemaNameOverride) ? $schemaNameOverride : ($playerName ?: 'PlyrCard Athlete');
        $schemaDescription = filled($schemaDescriptionOverride)
            ? $truncate($schemaDescriptionOverride, 220)
            : $truncate($defaultLongDescription, 220);

        $sameAs = collect([
            $igUrl ?: null,
            $xUrl ?: null,
            $ytUrl ?: null,
        ])->filter(fn ($v) => filled($v))->values()->all();

        $personSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $schemaName,
            'url' => $canonicalUrl,
            'description' => $schemaDescription,
            'image' => $shareImage,
        ];

        if (filled($schoolName)) {
            $personSchema['alumniOf'] = [
                '@type' => 'EducationalOrganization',
                'name' => $schoolName,
            ];
        }

        if (filled($clubName)) {
            $personSchema['memberOf'] = [
                '@type' => 'SportsOrganization',
                'name' => $clubName,
            ];
        }

        if (filled($sportText)) {
            $personSchema['sport'] = $sportText;
        }

        if (! empty($sameAs)) {
            $personSchema['sameAs'] = $sameAs;
        }

        $webPageSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => $seoTitle,
            'url' => $canonicalUrl,
            'description' => $seoDescription,
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $schemaName,
            ],
        ];
    @endphp

    <title>{{ $seoTitle }}</title>

    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ $seoKeywords }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="theme-color" content="{{ $primary }}">
    <meta name="author" content="{{ $playerName ?: 'PlyrCard' }}">

    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">

    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="PlyrCard">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:secure_url" content="{{ $shareImage }}">
    <meta property="og:image:alt" content="{{ $playerName ?: 'PlyrCard athlete profile' }}">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">

    @if (filled($user?->first_name))
        <meta property="profile:first_name" content="{{ $user->first_name }}">
    @endif
    @if (filled($user?->last_name))
        <meta property="profile:last_name" content="{{ $user->last_name }}">
    @endif
    @if (filled($igHandle))
        <meta property="profile:username" content="{{ $igHandle }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $twitterTitle }}">
    <meta name="twitter:description" content="{{ $twitterDescription }}">
    <meta name="twitter:image" content="{{ $twitterImageField ?: $shareImage }}">
    @if (filled($xHandle))
        <meta name="twitter:site" content="{{ $xHandle }}">
        <meta name="twitter:creator" content="{{ $xHandle }}">
    @endif

    <meta itemprop="name" content="{{ $seoTitle }}">
    <meta itemprop="description" content="{{ $seoDescription }}">
    <meta itemprop="image" content="{{ $shareImage }}">

    <script type="application/ld+json">
{!! json_encode($personSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <script type="application/ld+json">
{!! json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=anton-sc:400|antonio:300,400,500,600,700|bebas-neue:400|iceberg:400|poppins:300,400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        #hero-container{
            position: relative;
            overflow: hidden;
        }

        #hero-loader{
            position: absolute;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            transition: opacity .28s ease, visibility .28s ease;
            will-change: opacity;
            background:
                radial-gradient(circle at center,
                    rgba(0, 0, 0, 0.10) 0%,
                    rgba(0, 0, 0, 0.22) 42%,
                    rgba(0, 0, 0, 0.42) 72%,
                    rgba(0, 0, 0, 0.62) 100%
                ),
                linear-gradient(
                    135deg,
                    color-mix(in srgb, var(--primary) 92%, black 8%) 0%,
                    color-mix(in srgb, var(--primary) 78%, black 22%) 50%,
                    color-mix(in srgb, var(--primary) 58%, black 42%) 100%
                );
        }

        #hero-loader::after{
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at center,
                    transparent 0%,
                    transparent 38%,
                    rgba(0, 0, 0, 0.08) 58%,
                    rgba(0, 0, 0, 0.26) 100%
                );
            pointer-events: none;
        }

        #hero-loader.hidden{
            opacity: 0;
            visibility: hidden;
        }

        #hero-loader .loader-media{
            position: relative;
            z-index: 2;
            display: block;
            width: clamp(120px, 16vw, 220px);
            max-width: 220px;
            height: auto;
            object-fit: contain;
            filter:
                drop-shadow(0 14px 30px rgba(0, 0, 0, 0.28))
                drop-shadow(0 4px 10px rgba(0, 0, 0, 0.18));
        }

        @media (max-width: 640px) {
            #hero-loader .loader-media{
                width: min(180px, 52vw);
            }
        }

        :root{
            --primary: {{ $primary }};
            --secondary: {{ $secondary }};
            --accent: {{ $accent }};
            --bg: {{ $bg }};
            --surface: {{ $surface }};
            --text1: {{ $text1 }};
            --text2: {{ $text2 }};
            --on-primary: {{ $onPrimary }};
            --on-secondary: {{ $onSecondary }};
        }

        body{
            font-family: "Poppins", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
            background: var(--bg);
            margin: 0;
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .font-heading{
            font-family: "Bebas Neue", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        }

        .embed-responsive iframe { width: 100%; height: 100%; }

        .tab-btn{
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .tab-btn:not(.is-active):hover{
            background: var(--secondary) !important;
            color: var(--on-secondary) !important;
        }

        .tab-btn.is-active{
            background: var(--secondary) !important;
            color: var(--on-secondary) !important;
        }

        .icon-link{
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
        }

        .icon-link svg{
            transition: 150ms ease;
        }

        .icon-link:hover svg{
            stroke: var(--secondary);
        }

        .icon-link:hover{
            color: var(--secondary);
        }

        .acad-list ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .acad-list li {
            position: relative;
            padding-left: 30px;
            margin: 0.4rem 0;
            line-height: 1.6;
        }

        .acad-list li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.4em;
            width: 18px;
            height: 18px;
            background-color: {{ $primary }};
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='6' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='11' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='16' width='16' height='2' rx='1' fill='black'/%3E%3C/svg%3E") no-repeat center / contain;
                    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='4' y='6' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='11' width='16' height='2' rx='1' fill='black'/%3E%3Crect x='4' y='16' width='16' height='2' rx='1' fill='black'/%3E%3C/svg%3E") no-repeat center / contain;
        }

        .schedule-shell{
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: #ffffff;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .schedule-toolbar{
            display: grid;
            grid-template-columns: minmax(0, 240px) minmax(0, 120px) minmax(0, 1fr) auto;
            gap: 0;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .schedule-control{
            position: relative;
            min-height: 48px;
            border-right: 1px solid rgba(15, 23, 42, 0.08);
            background: #ffffff;
        }

        .schedule-control:last-child{
            border-right: 0;
        }

        .schedule-control select,
        .schedule-control input,
        .schedule-reset-btn{
            width: 100%;
            height: 100%;
            min-height: 48px;
            border: 0;
            outline: 0;
            background: transparent;
            color: {{ $text1 }};
            font-size: 14px;
            padding: 0 14px 0 44px;
        }

        .schedule-control select{
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 38px;
            cursor: pointer;
        }

        .schedule-control-icon{
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: {{ $primary }};
            pointer-events: none;
        }

        .schedule-control-caret{
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: rgba(15,23,42,.55);
            pointer-events: none;
        }

        .schedule-reset-btn{
            padding: 0 18px;
            cursor: pointer;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: {{ $text2 }};
        }

        .schedule-reset-btn:hover{
            background: rgba(15,23,42,.03);
            color: {{ $text1 }};
        }

        .schedule-header{
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
        }

        .schedule-month-row{
            position: relative;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            min-height: 50px;
            padding: 0 16px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .schedule-month-label{
            text-align: center;
            font-family: "Bebas Neue", ui-sans-serif, system-ui;
            font-size: 2rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: {{ $text1 }};
        }

        .schedule-month-next{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            color: {{ $primary }};
        }

        .schedule-week-row{
            display: grid;
            grid-template-columns: 72px 1fr 72px;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
        }

        .schedule-nav-btn{
            width: 50px;
            height: 50px;
            border-radius: 999px;
            border: 1px solid rgba(15,23,42,.12);
            background: #fff;
            color: {{ $primary }};
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            cursor: pointer;
            transition: 180ms ease;
        }

        .schedule-nav-btn:hover:not(:disabled){
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.09);
        }

        .schedule-nav-btn:disabled{
            opacity: .35;
            cursor: not-allowed;
        }

        .schedule-week-center{
            text-align: center;
        }

        .schedule-week-label{
            font-family: "Bebas Neue", ui-sans-serif, system-ui;
            font-size: 2.4rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: {{ $text1 }};
            line-height: 1;
        }

        .schedule-days-grid{
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            background: #fff;
        }

        .schedule-day-cell{
            min-height: 48px;
            padding: 8px 6px;
            text-align: center;
            border-right: 1px solid rgba(15, 23, 42, 0.08);
        }

        .schedule-day-cell:last-child{
            border-right: 0;
        }

        .schedule-day-name{
            display: block;
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: {{ $text1 }};
            text-transform: uppercase;
            line-height: 1.15;
        }

        .schedule-day-date{
            display: block;
            margin-top: 4px;
            font-size: .92rem;
            font-weight: 700;
            color: {{ $text1 }};
        }

        .schedule-day-cell.is-muted .schedule-day-name,
        .schedule-day-cell.is-muted .schedule-day-date{
            opacity: .45;
        }

        .schedule-list-wrap{
            background: #fff;
        }

        .schedule-listing{
            min-height: 140px;
        }

        .schedule-row{
            display: grid;
            grid-template-columns: 94px minmax(0, 1fr);
            gap: 18px;
            padding: 22px 22px;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .schedule-row:first-child{
            border-top: 0;
        }

        .schedule-date-block{
            text-align: center;
            color: {{ $primary }};
            line-height: 1;
            padding-top: 4px;
        }

        .schedule-date-block .num{
            display: block;
            font-family: "Bebas Neue", ui-sans-serif, system-ui;
            font-size: 3.1rem;
            letter-spacing: .03em;
        }

        .schedule-date-block .mon{
            display: block;
            margin-top: 3px;
            font-size: .9rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }

        .schedule-event-time{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: {{ $primary }};
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 6px;
        }

        .schedule-event-title{
            font-family: "Bebas Neue", ui-sans-serif, system-ui;
            font-size: 2.2rem;
            line-height: 1;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: {{ $text1 }};
            margin: 0 0 10px;
        }

        .schedule-event-sub{
            color: {{ $text2 }};
            font-size: 1rem;
            line-height: 1.5;
        }

        .schedule-event-sub + .schedule-event-sub{
            margin-top: 2px;
        }

        .schedule-empty{
            padding: 34px 24px;
            color: {{ $text2 }};
        }

        .schedule-empty-title{
            font-family: "Bebas Neue", ui-sans-serif, system-ui;
            font-size: 2rem;
            line-height: 1;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: {{ $text1 }};
            margin-bottom: 8px;
        }

        .schedule-empty-copy{
            font-size: 1rem;
            line-height: 1.65;
        }

        .mobile-social-footer{
            background: #ffffff;
            border-top: 1px solid rgba(15,23,42,0.12);
        }

        .mobile-social-inner{
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            align-items: center;
            gap: 0;
            padding: 8px 10px 9px;
        }

        .mobile-social-icon,
        .mobile-text-coach{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            justify-self: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .mobile-social-icon{
            width: 30px;
            height: 30px;
            color: #111111;
        }

        .mobile-social-icon svg{
            width: 100%;
            height: 100%;
            display: block;
        }

        .mobile-social-icon.instagram svg{
            width: 31px;
            height: 31px;
        }

        .mobile-social-icon.x svg{
            width: 26px;
            height: 26px;
        }

        .mobile-social-icon.youtube svg{
            width: 30px;
            height: 30px;
        }

        .mobile-social-icon.mail svg{
            width: 31px;
            height: 31px;
        }

        .mobile-text-coach{
            min-width: 118px;
            height: 42px;
            padding: 0 16px;
            border-radius: 12px;
            background: #000000;
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            line-height: 1;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .mobile-social-icon.is-disabled,
        .mobile-text-coach.is-disabled{
            opacity: .4;
            pointer-events: none;
        }

        @media (max-width: 1023px){
            .schedule-toolbar{
                grid-template-columns: 1fr 110px;
            }

            .schedule-control.search{
                grid-column: 1 / -1;
                border-top: 1px solid rgba(15, 23, 42, 0.08);
                border-right: 1px solid rgba(15, 23, 42, 0.08);
            }
        }

        @media (max-width: 767px){
            body{ padding-bottom: 72px; }

            .schedule-toolbar{
                grid-template-columns: 1fr;
            }

            .schedule-control{
                border-right: 0;
                border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            }

            .schedule-control.search{
                border-top: 0;
            }

            .schedule-control.reset{
                border-bottom: 0;
            }

            .schedule-month-row{
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 10px 14px;
            }

            .schedule-month-label{
                font-size: 1.55rem;
            }

            .schedule-month-next{
                display: none;
            }

            .schedule-week-row{
                grid-template-columns: 54px 1fr 54px;
                gap: 8px;
                padding: 10px 12px;
            }

            .schedule-nav-btn{
                width: 42px;
                height: 42px;
            }

            .schedule-week-label{
                font-size: 1.9rem;
            }

            .schedule-day-cell{
                min-height: 52px;
                padding: 8px 3px;
            }

            .schedule-day-name{
                font-size: .72rem;
            }

            .schedule-day-date{
                font-size: .84rem;
            }

            .schedule-row{
                grid-template-columns: 70px minmax(0, 1fr);
                gap: 12px;
                padding: 18px 14px;
            }

            .schedule-date-block .num{
                font-size: 2.35rem;
            }

            .schedule-date-block .mon{
                font-size: .78rem;
            }

            .schedule-event-title{
                font-size: 1.65rem;
            }

            .schedule-event-time,
            .schedule-event-sub{
                font-size: .93rem;
            }
        }

        @media (min-width: 420px){
            .mobile-social-inner{
                padding: 10px 14px 11px;
            }

            .mobile-social-icon{
                width: 34px;
                height: 34px;
            }

            .mobile-social-icon.instagram svg{
                width: 35px;
                height: 35px;
            }

            .mobile-social-icon.x svg{
                width: 29px;
                height: 29px;
            }

            .mobile-social-icon.youtube svg{
                width: 33px;
                height: 33px;
            }

            .mobile-social-icon.mail svg{
                width: 34px;
                height: 34px;
            }

            .mobile-text-coach{
                min-width: 132px;
                height: 46px;
                padding: 0 18px;
                border-radius: 12px;
                font-size: 14px;
            }
        }

        @media (min-width: 768px){
            .mobile-social-footer{
                display: none;
            }
        }
    </style>
</head>

<body>
    @if ($website->heroTemplate?->blade_view)
        <div id="hero-container">
            @include($website->heroTemplate->blade_view, ['website' => $website])

            <div id="hero-loader" aria-hidden="true">
                <img
                    src="{{ asset('PLYR_LOGO_TRANS_GIF.webp') }}"
                    alt="Plyr loading"
                    class="loader-media"
                    width="220"
                    height="220"
                    decoding="async"
                >
            </div>
        </div>
    @endif

    <div class="relative z-30 flex flex-col md:flex-row h-auto w-full">
        <div class="w-full md:w-8/12 min-w-0">
            <div id="tabs" class="flex w-full overflow-x-auto whitespace-nowrap no-scrollbar -mt-12">
                <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center is-active"
                        style="background: {{ $secondary }}; color: {{ $onSecondary }};"
                        data-tab="about">
                    ABOUT ME
                </button>

                <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="schedule">
                    SCHEDULE
                </button>

                <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center"
                        style="background: {{ $primary }}; color: {{ $onPrimary }};"
                        data-tab="highlights">
                    HIGHLIGHTS
                </button>

                @if($hasAnyAccolades)
                    <button class="tab-btn flex-shrink-0 px-5 py-3 font-semibold text-center"
                            style="background: {{ $primary }}; color: {{ $onPrimary }};"
                            data-tab="accolades">
                        ACCOLADES
                    </button>
                @endif
            </div>

            <div class="bg-white">
                <div id="tab-about" class="tab-content">
                    <div class="p-6 md:p-10">
                        <h2 class="text-3xl md:text-4xl font-heading tracking-[0.17em] min-h-[2.5rem]" style="color: {{ $text1 }};">
                            {{ $aboutHeadline }}
                        </h2>

                        <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.1em]" style="color: {{ $primary }};">
                            {{ $aboutTagline }}
                        </div>

                        <div class="space-y-5 md:space-y-6 text-[16px] md:text-[17px] leading-6 min-h-[4rem]" style="color: {{ $text2 }};">
                            {!! $aboutBio !!}
                        </div>
                    </div>

                    @if(!empty($aboutVideos) && !empty($aboutVideos[0]))
                        @php
                            $video = $aboutVideos[0];
                            $videoId = null;

                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})/', $video, $matches)) {
                                $videoId = $matches[1];
                            }

                            $embedUrl = $videoId
                                ? "https://www.youtube.com/embed/".$videoId."?autoplay=1&rel=0"
                                : $video;
                        @endphp

                        <div class="mt-6 md:mt-8">
                            <div class="grid grid-cols-1 gap-6">
                                <div class="w-full aspect-video overflow-hidden relative"
                                     style="background: {{ $bg }};"
                                     id="about-video-player">
                                    <div
                                        class="relative w-full h-full cursor-pointer group"
                                        onclick="document.getElementById('about-video-player').innerHTML = `
                                            <iframe
                                                class='w-full h-full'
                                                src='{{ $embedUrl }}'
                                                title='YouTube video'
                                                frameborder='0'
                                                allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                                                allowfullscreen>
                                            </iframe>`"
                                    >
                                        <img
                                            src="{{ $aboutThumbnailUrl }}"
                                            alt="Video Thumbnail"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                            decoding="async"
                                        />

                                        <div class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition"></div>

                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="w-20 h-20 bg-white/90 rounded-full flex items-center justify-center shadow-lg group-hover:scale-105 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 84 84" class="w-10 h-10 ml-1">
                                                    <path d="M32 25.5v33l26-16.5-26-16.5z" fill="{{ $primary }}"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div id="tab-schedule" class="tab-content hidden p-6 md:p-10">
                    <h2 class="text-3xl md:text-4xl font-heading tracking-[0.17em] min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $scheduleHeadline }}
                    </h2>

                    <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $primary }};">
                        {{ $scheduleTagline }}
                    </div>

                    <div class="schedule-shell" id="schedule-calendar-root">
                        <div class="schedule-toolbar">
                            <div class="schedule-control">
                                <svg class="schedule-control-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                    <path d="M16 2v4M8 2v4M3 10h18"></path>
                                </svg>
                                <select id="schedule-month-select" aria-label="Select month"></select>
                                <svg class="schedule-control-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7 10l5 5 5-5H7z"></path>
                                </svg>
                            </div>

                            <div class="schedule-control">
                                <select id="schedule-year-select" aria-label="Select year"></select>
                                <svg class="schedule-control-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7 10l5 5 5-5H7z"></path>
                                </svg>
                            </div>

                            <div class="schedule-control search">
                                <svg class="schedule-control-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="7"></circle>
                                    <path d="m20 20-3.5-3.5"></path>
                                </svg>
                                <input id="schedule-search-input" type="text" placeholder="Search schedule..." />
                            </div>

                            <div class="schedule-control reset">
                                <button id="schedule-reset-btn" type="button" class="schedule-reset-btn">Reset</button>
                            </div>
                        </div>

                        <div class="schedule-header">
                            <div class="schedule-month-row">
                                <div></div>
                                <div class="schedule-month-label" id="schedule-current-month-label">Schedule</div>
                                <div class="schedule-month-next">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="{{ $primary }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m9 18 6-6-6-6"></path>
                                    </svg>
                                </div>
                            </div>

                            <div class="schedule-week-row">
                                <div class="flex items-center justify-start">
                                    <button type="button" class="schedule-nav-btn" id="schedule-prev-week" aria-label="Previous week">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="{{ $primary }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m15 18-6-6 6-6"></path>
                                        </svg>
                                    </button>
                                </div>

                                <div class="schedule-week-center">
                                    <div class="schedule-week-label" id="schedule-week-label">Week 1</div>
                                </div>

                                <div class="flex items-center justify-end">
                                    <button type="button" class="schedule-nav-btn" id="schedule-next-week" aria-label="Next week">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="{{ $primary }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m9 18 6-6-6-6"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="schedule-days-grid" id="schedule-days-grid"></div>
                        </div>

                        <div class="schedule-list-wrap">
                            <div id="schedule-listing" class="schedule-listing"></div>
                        </div>
                    </div>
                </div>

                <div id="tab-highlights" class="tab-content hidden p-6 md:p-10">
                    <div class="tracking-[0.17em] uppercase font-heading text-3xl md:text-4xl min-h-[2.5rem]" style="color: {{ $text1 }};">
                        {{ $highHeadline }}
                    </div>

                    <div class="tracking-[0.17em] text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem]" style="color: {{ $primary }};">
                        {{ $highTagline }}
                    </div>

                    @if(!empty($highlightVideos))
                        <div class="grid grid-cols-1 sm:grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                            @foreach($highlightVideos as $video)
                                <div class="w-full aspect-video rounded overflow-hidden">
                                    <iframe
                                        class="w-full h-full"
                                        src="{{ $video['embed_url'] ?? '' }}"
                                        title="{{ $video['title'] ?? 'YouTube video' }}"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen>
                                    </iframe>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="min-h-[10rem]"></div>
                </div>

                @if($hasAnyAccolades)
                    <div id="tab-accolades" class="tab-content hidden p-6 md:p-10">
                        @if($hasAcademicAccolades)
                            <div class="mb-8 md:mb-10">
                                <h2 class="text-3xl md:text-4xl tracking-[0.17em] font-heading uppercase min-h-[2.5rem]" style="color: {{ $text1 }};">
                                    {{ $acadHeadline }}
                                </h2>

                                <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $primary }};">
                                    {{ $acadTagline }}
                                </div>

                                <div class="acad-list space-y-3 text-[16px] md:text-[17px] min-h-[4rem]" style="color: {{ $text2 }};">
                                    {!! $acadBody !!}
                                </div>
                            </div>
                        @endif

                        @if($hasSportsAccolades)
                            <div class="mb-8 md:mb-10">
                                <h2 class="text-3xl md:text-4xl tracking-[0.17em] font-heading uppercase min-h-[2.5rem]" style="color: {{ $text1 }};">
                                    {{ $sportHeadline }}
                                </h2>

                                <div class="text-base md:text-lg mb-5 md:mb-6 min-h-[1.75rem] tracking-[0.17em]" style="color: {{ $primary }};">
                                    {{ $sportTagline }}
                                </div>

                                <div class="acad-list space-y-3 text-[16px] md:text-[17px] min-h-[4rem]" style="color: {{ $text2 }};">
                                    {!! $sportBody !!}
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="w-full md:w-4/12 p-6 md:p-10" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="p-4 md:p-6 rounded min-h-[180px]">
                {!! $contactFormEmbed !!}
            </div>
        </div>
    </div>

    <section class="w-full mt-5">
        <div class="relative pt-20 pb-24 md:pt-30 md:pb-10 overflow-hidden" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="absolute top-0 left-0 w-full overflow-hidden leading-[0] z-20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1899 357" preserveAspectRatio="none">
                    <rect width="1899" height="357" fill="{{ $primary }}"/>
                    <path d="M0,0 H1899 V38 C1715,50 1540,58 1368,52 C1215,47 1085,58 954,67 C774,79 585,58 396,40 C267,28 136,22 0,24 Z" fill="#ffffffff"/>
                    <path d="M0,20 C48,18 92,18 133,19 C106,20 62,22 0,22 Z" fill="{{ $primary }}"/>
                    <path d="M646,50 C820,58 1000,63 1147,58 C996,66 810,65 646,50 Z" fill="{{ $primary }}"/>
                    <path d="M728,69 C846,77 980,80 1087,74 C972,84 839,83 728,69 Z" fill="#ffffff" opacity="0.7"/>
                    <path d="M1065,28 C1278,29 1524,27 1818,8 C1644,26 1397,36 1065,28 Z" fill="{{ $primary }}" transform="translate(0,7)" />
                    <path d="M1868,34 C1878,33 1888,32 1899,31 V35 C1888,36 1878,36 1868,34 Z" fill="{{ $primary }}"/>
                    <rect x="0" y="344" width="1899" height="13" fill="{{ $primary }}"/>
                </svg>
            </div>

            <div class="relative text-center z-30 px-0">
                <h2 class="font-heading text-6xl md:text-[100px] leading-none uppercase tracking-tight" style="color: {{ $onPrimary }};">
                    Coaching Staff
                </h2>
                <p class="text-base md:text-[27px] uppercase tracking-[0.1rem] font-thin" style="font-family: Poppins, ui-sans-serif, system-ui; color: {{ $onPrimary }}; opacity: 0.9;">
                    Guided by the Best in the Game
                </p>
            </div>
        </div>

        <div class="py-12 md:py-16 px-6 md:px-20" style="background: {{ $bg }};">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-wrap justify-center lg:justify-between gap-y-8 gap-x-6 md:gap-x-8">
                    @foreach ($coachRows as $coach)
                        <div class="flex-1 min-w-[220px] max-w-[320px] text-center">
                            <div class="font-extrabold uppercase tracking-wide text-lg" style="color: {{ $text1 }};">
                                {{ $coach['name'] ?? '' }}
                            </div>

                            <div class="mt-1 text-xs uppercase tracking-widest" style="color: {{ $primary }};">
                                {{ $coach['label'] ?? '' }}
                            </div>

                            <div class="mt-6 flex justify-center">
                                @if(!empty($coach['email']) || !empty($playerEmail))
                                    <a href="mailto:{{ $coach['email'] ?? $playerEmail }}" class="icon-link inline-flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" viewBox="0 0 24 24" fill="none" stroke="{{ $primary }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 4h16v16H4z"></path>
                                            <path d="m4 6 8 6 8-6"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <footer class="w-full">
        <div class="py-12 md:py-16 px-6 md:px-20" style="background: {{ $primary }}; color: {{ $onPrimary }};">
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-10 md:gap-12 items-start">
                <div class="flex items-center justify-end md:justify-end">
                    <div class="flex flex-wrap items-center gap-6 md:gap-8">
                        @if (!empty($footerClubLogoUrl))
                            <img
                                src="{{ $footerClubLogoUrl }}"
                                alt="Club logo"
                                class="h-14 md:h-20 w-auto object-contain"
                                loading="lazy"
                                decoding="async"
                            >
                        @endif

                        @if (!empty($footerLeagueLogoUrl))
                            <img
                                src="{{ $footerLeagueLogoUrl }}"
                                alt="League logo"
                                class="h-14 md:h-20 w-auto object-contain"
                                loading="lazy"
                                decoding="async"
                            >
                        @endif

                        @if (!empty($footerNationalTeamLogoUrl))
                            <img
                                src="{{ $footerNationalTeamLogoUrl }}"
                                alt="National team logo"
                                class="h-14 md:h-20 w-auto object-contain"
                                loading="lazy"
                                decoding="async"
                            >
                        @endif
                    </div>
                </div>

                <div class="md:border-l md:pl-8 border-t md:border-t-0 pt-8 md:pt-0"
                     style="border-color: rgba(255,255,255,0.25);">
                    <h3 class="text-lg md:text-xl font-bold uppercase tracking-wide mb-6">
                        Get in Touch
                    </h3>

                    <div class="flex items-center gap-4 mb-4 min-h-[1.5rem]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2l2 5-2 1a11 11 0 005 5l1-2 5 2v2a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        @if (!empty($footerPhone))
                            <a href="tel:{{ preg_replace('/\D+/', '', $footerPhone) }}" class="hover:underline">
                                {{ $footerPhone }}
                            </a>
                        @else
                            <div class="flex-1"></div>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 mb-6 min-h-[1.5rem]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M4 6h16a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                        </svg>
                        @if (!empty($footerEmail))
                            <a href="mailto:{{ $footerEmail }}" class="hover:underline">
                                {{ $footerEmail }}
                            </a>
                        @else
                            <div class="flex-1"></div>
                        @endif
                    </div>

                    <div class="hidden md:block">
                        <p class="text-sm uppercase tracking-wider mb-3" style="opacity: 0.8;">
                            Connect
                        </p>

                        <div class="flex items-center gap-6" style="color: {{ $onPrimary }};">
                            <a href="{{ $igUrl ?: '#' }}"
                               class="icon-link {{ empty($igUrl) ? 'pointer-events-none opacity-60' : '' }}"
                               aria-label="Instagram"
                               @if(!empty($igUrl)) target="_blank" rel="noopener noreferrer" @endif>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7.75 2h8.5A5.75 5.75 0 0122 7.75v8.5A5.75 5.75 0 0116.25 22h-8.5A5.75 5.75 0 012 16.25v-8.5A5.75 5.75 0 017.75 2zm4.25 5.5A4.75 4.75 0 1016.75 12 4.76 4.76 0 0012 7.5zm0 7.8A3.05 3.05 0 1115.05 12 3.05 3.05 0 0112 15.3zm4.9-8.55a1.1 1.1 0 11-1.1-1.1 1.1 1.1 0 011.1 1.1z"/>
                                </svg>
                            </a>

                            <a href="{{ $ytUrl ?: '#' }}"
                               class="icon-link {{ empty($ytUrl) ? 'pointer-events-none opacity-60' : '' }}"
                               aria-label="YouTube"
                               @if(!empty($ytUrl)) target="_blank" rel="noopener noreferrer" @endif>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M23.5 6.2a3 3 0 00-2.1-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.4.6A3 3 0 00.5 6.2 31.4 31.4 0 000 12a31.4 31.4 0 00.5 5.8 3 3 0 002.1 2.1c1.8.6 9.4.6 9.4.6s7.6 0 9.4-.6a3 3 0 002.1-2.1A31.4 31.4 0 0024 12a31.4 31.4 0 00-.5-5.8zM9.8 15.5v-7l6.2 3.5-6.2 3.5z"/>
                                </svg>
                            </a>

                            <a href="{{ $xUrl ?: '#' }}"
                               class="icon-link {{ empty($xUrl) ? 'pointer-events-none opacity-60' : '' }}"
                               aria-label="X"
                               @if(!empty($xUrl)) target="_blank" rel="noopener noreferrer" @endif>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18.244 2H21l-6.5 7.43L22 22h-6.828l-4.27-5.588L5.6 22H3l7.1-8.12L2 2h6.828l3.84 5.088L18.244 2z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center py-4 text-xs md:text-sm uppercase tracking-wider" style="background: {{ $secondary }}; color: {{ $onSecondary }};">
            {{ $copyright }}
        </div>
    </footer>

    <div class="mobile-social-footer fixed bottom-0 left-0 w-full z-50 md:hidden">
        <div class="mobile-social-inner max-w-7xl mx-auto">
            <a href="{{ $igUrl ?: '#' }}"
               class="mobile-social-icon instagram {{ empty($igUrl) ? 'is-disabled' : '' }}"
               aria-label="Instagram"
               target="{{ !empty($igUrl) ? '_blank' : '_self' }}"
               rel="{{ !empty($igUrl) ? 'noopener noreferrer' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.15" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2.75" y="2.75" width="18.5" height="18.5" rx="5.25" ry="5.25"></rect>
                    <circle cx="12" cy="12" r="4.2"></circle>
                    <circle cx="17.35" cy="6.65" r="1.15" fill="currentColor" stroke="none"></circle>
                </svg>
            </a>

            <a href="{{ $xUrl ?: '#' }}"
               class="mobile-social-icon x {{ empty($xUrl) ? 'is-disabled' : '' }}"
               aria-label="X"
               target="{{ !empty($xUrl) ? '_blank' : '_self' }}"
               rel="{{ !empty($xUrl) ? 'noopener noreferrer' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865z"/>
                </svg>
            </a>

            <a href="{{ $ytUrl ?: '#' }}"
               class="mobile-social-icon youtube {{ empty($ytUrl) ? 'is-disabled' : '' }}"
               aria-label="YouTube"
               target="{{ !empty($ytUrl) ? '_blank' : '_self' }}"
               rel="{{ !empty($ytUrl) ? 'noopener noreferrer' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.4.6A3 3 0 0 0 .5 6.2 31.4 31.4 0 0 0 0 12a31.4 31.4 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.8.6 9.4.6 9.4.6s7.6 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.4 31.4 0 0 0 24 12a31.4 31.4 0 0 0-.5-5.8ZM9.8 15.5v-7l6.2 3.5-6.2 3.5Z"/>
                </svg>
            </a>

            <a href="{{ $playerEmail ? 'mailto:' . $playerEmail : '#' }}"
               class="mobile-social-icon mail {{ empty($playerEmail) ? 'is-disabled' : '' }}"
               aria-label="Email">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 5.5h18v13H3z"></path>
                    <path d="m4 7 8 6 8-6"></path>
                </svg>
            </a>

            <a href="{{ $textCoachUrl }}"
               class="mobile-text-coach {{ $textCoachUrl === '#' ? 'is-disabled' : '' }}"
               aria-label="Text Coach">
                TEXT COACH
            </a>
        </div>
    </div>

    <script>
        (function () {
            const MIN_LOADER_MS = 250;

            function hideHeroLoader() {
                const loader = document.getElementById('hero-loader');

                if (!loader || loader.classList.contains('hidden')) {
                    return;
                }

                loader.classList.add('hidden');
            }

            function isVisible(el) {
                return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
            }

            function getImageUrlFromBackground(backgroundImage) {
                if (!backgroundImage || backgroundImage === 'none') {
                    return null;
                }

                const match = backgroundImage.match(/url\((['"]?)(.*?)\1\)/i);
                return match ? match[2] : null;
            }

            function getTrackedImages(scope) {
                const seen = new Set();

                return Array.from(scope.querySelectorAll('img')).filter(img => {
                    const src = img.currentSrc || img.getAttribute('src') || '';
                    const isLoaderGif = src.includes('PLYR_LOGO_TRANS_GIF.webp');

                    if (!src.trim() || isLoaderGif || seen.has(src)) {
                        return false;
                    }

                    seen.add(src);
                    return true;
                });
            }

            function getTrackedBackgroundUrls(scope) {
                const urls = new Set();

                Array.from(scope.querySelectorAll('*')).forEach(el => {
                    if (!isVisible(el)) {
                        return;
                    }

                    const bg = window.getComputedStyle(el).backgroundImage;
                    const url = getImageUrlFromBackground(bg);

                    if (url && !url.includes('PLYR_LOGO_TRANS_GIF.webp')) {
                        urls.add(url);
                    }
                });

                return Array.from(urls);
            }

            function waitForImages(images) {
                return Promise.all(
                    images.map(img => {
                        return new Promise(resolve => {
                            const done = () => resolve();

                            if (img.complete && img.naturalWidth > 0) {
                                if (typeof img.decode === 'function') {
                                    img.decode().catch(() => {}).finally(done);
                                } else {
                                    done();
                                }
                                return;
                            }

                            img.addEventListener('load', done, { once: true });
                            img.addEventListener('error', done, { once: true });

                            setTimeout(done, 4000);
                        });
                    })
                );
            }

            function waitForBackgroundImages(urls) {
                return Promise.all(
                    urls.map(url => {
                        return new Promise(resolve => {
                            const img = new Image();
                            img.onload = resolve;
                            img.onerror = resolve;
                            img.src = url;

                            setTimeout(resolve, 4000);
                        });
                    })
                );
            }

            function waitForHeroResources(heroContainer) {
                const trackedImages = getTrackedImages(heroContainer);
                const trackedBackgroundUrls = getTrackedBackgroundUrls(heroContainer);

                return Promise.all([
                    waitForImages(trackedImages),
                    waitForBackgroundImages(trackedBackgroundUrls),
                ]);
            }

            document.addEventListener('DOMContentLoaded', async function () {
                const heroContainer = document.getElementById('hero-container');

                if (!heroContainer) {
                    hideHeroLoader();
                    return;
                }

                const startedAt = Date.now();

                await waitForHeroResources(heroContainer);

                const elapsed = Date.now() - startedAt;
                const remaining = Math.max(0, MIN_LOADER_MS - elapsed);

                if (remaining > 0) {
                    await new Promise(resolve => setTimeout(resolve, remaining));
                }

                requestAnimationFrame(() => {
                    requestAnimationFrame(hideHeroLoader);
                });
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    hideHeroLoader();
                }
            });
        })();
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const buttons  = document.querySelectorAll(".tab-btn");
            const contents = document.querySelectorAll(".tab-content");

            const primary     = @json($primary);
            const onPrimary   = @json($onPrimary);
            const secondary   = @json($secondary);
            const onSecondary = @json($onSecondary);

            buttons.forEach(btn => {
                btn.style.cursor = "pointer";
            });

            buttons.forEach(button => {
                button.addEventListener("click", function () {
                    const target = this.dataset.tab;
                    const active = document.getElementById("tab-" + target);

                    if (!active) {
                        return;
                    }

                    buttons.forEach(btn => {
                        btn.classList.remove("is-active");
                        btn.style.background = primary;
                        btn.style.color = onPrimary;
                    });

                    this.classList.add("is-active");
                    this.style.background = secondary;
                    this.style.color = onSecondary;

                    contents.forEach(content => content.classList.add("hidden"));
                    active.classList.remove("hidden");
                });
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const schedules = @json($schedulePayload);

            const monthSelect = document.getElementById("schedule-month-select");
            const yearSelect = document.getElementById("schedule-year-select");
            const searchInput = document.getElementById("schedule-search-input");
            const resetBtn = document.getElementById("schedule-reset-btn");
            const prevWeekBtn = document.getElementById("schedule-prev-week");
            const nextWeekBtn = document.getElementById("schedule-next-week");
            const monthLabel = document.getElementById("schedule-current-month-label");
            const weekLabel = document.getElementById("schedule-week-label");
            const daysGrid = document.getElementById("schedule-days-grid");
            const listing = document.getElementById("schedule-listing");

            if (!monthSelect || !yearSelect || !searchInput || !resetBtn || !prevWeekBtn || !nextWeekBtn || !monthLabel || !weekLabel || !daysGrid || !listing) {
                return;
            }

            const monthNames = [
                "January","February","March","April","May","June",
                "July","August","September","October","November","December"
            ];

            const dayNames = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

            const uniqueYears = [...new Set(schedules.map(item => item.year).filter(Boolean))].sort();
            const uniqueMonths = [...new Set(schedules.map(item => item.month).filter(Boolean))].sort();

            const now = new Date();
            const currentYearFallback = String(now.getFullYear());
            const currentMonthFallback = String(now.getMonth() + 1).padStart(2, "0");

            let selectedYear = uniqueYears.includes(currentYearFallback)
                ? currentYearFallback
                : (uniqueYears[0] || currentYearFallback);

            const monthsForInitialYear = schedules
                .filter(item => item.year === selectedYear)
                .map(item => item.month);

            let selectedMonth = monthsForInitialYear.includes(currentMonthFallback)
                ? currentMonthFallback
                : (([...new Set(monthsForInitialYear)].sort()[0]) || uniqueMonths[0] || currentMonthFallback);

            let searchTerm = "";
            let selectedWeekIndex = 0;

            function parseLocalDate(dateString) {
                const [y, m, d] = dateString.split("-").map(Number);
                return new Date(y, m - 1, d);
            }

            function getScheduleDateTime(item) {
                const date = parseLocalDate(item.date);

                if (item.time_sortable) {
                    const [hours, minutes, seconds] = item.time_sortable.split(":").map(Number);
                    date.setHours(hours || 0, minutes || 0, seconds || 0, 0);
                } else {
                    date.setHours(23, 59, 59, 999);
                }

                return date;
            }

            function toIsoDate(dateObj) {
                const y = dateObj.getFullYear();
                const m = String(dateObj.getMonth() + 1).padStart(2, "0");
                const d = String(dateObj.getDate()).padStart(2, "0");
                return `${y}-${m}-${d}`;
            }

            function startOfWeekMonday(dateObj) {
                const date = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
                const day = date.getDay();
                const diff = day === 0 ? -6 : 1 - day;
                date.setDate(date.getDate() + diff);
                return date;
            }

            function addDays(dateObj, days) {
                const copy = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
                copy.setDate(copy.getDate() + days);
                return copy;
            }

            function getWeeksForMonth(year, month) {
                const monthIndex = Number(month) - 1;
                const firstOfMonth = new Date(Number(year), monthIndex, 1);
                const lastOfMonth = new Date(Number(year), monthIndex + 1, 0);
                const start = startOfWeekMonday(firstOfMonth);

                const weeks = [];
                let cursor = new Date(start);

                while (cursor <= lastOfMonth || cursor.getMonth() === monthIndex) {
                    const days = [];
                    for (let i = 0; i < 7; i++) {
                        const day = addDays(cursor, i);
                        days.push({
                            iso: toIsoDate(day),
                            dayName: dayNames[i],
                            dateNum: day.getDate(),
                            inMonth: day.getMonth() === monthIndex,
                        });
                    }
                    weeks.push(days);
                    cursor = addDays(cursor, 7);

                    if (weeks.length > 6) {
                        break;
                    }
                }

                return weeks;
            }

            function fillMonthOptions() {
                const monthsForYear = [...new Set(
                    schedules
                        .filter(item => item.year === selectedYear)
                        .map(item => item.month)
                )].sort();

                monthSelect.innerHTML = "";

                monthsForYear.forEach(month => {
                    const option = document.createElement("option");
                    option.value = month;
                    option.textContent = monthNames[Number(month) - 1] || month;
                    if (month === selectedMonth) {
                        option.selected = true;
                    }
                    monthSelect.appendChild(option);
                });

                if (!monthsForYear.includes(selectedMonth)) {
                    selectedMonth = monthsForYear[0] || currentMonthFallback;
                }
            }

            function fillYearOptions() {
                yearSelect.innerHTML = "";

                uniqueYears.forEach(year => {
                    const option = document.createElement("option");
                    option.value = year;
                    option.textContent = year;
                    if (year === selectedYear) {
                        option.selected = true;
                    }
                    yearSelect.appendChild(option);
                });
            }

            function getFilteredSchedules() {
                const normalizedSearch = searchTerm.trim().toLowerCase();

                return schedules.filter(item => {
                    const matchesMonth = item.year === selectedYear && item.month === selectedMonth;
                    const matchesSearch = normalizedSearch === "" || (item.search_blob || "").includes(normalizedSearch);
                    return matchesMonth && matchesSearch;
                });
            }

            function render() {
                fillYearOptions();
                fillMonthOptions();

                const monthIndex = Number(selectedMonth) - 1;
                monthLabel.textContent = `${selectedYear} ${monthNames[monthIndex] || ""}`.trim().toUpperCase();

                const weeks = getWeeksForMonth(selectedYear, selectedMonth);

                if (selectedWeekIndex < 0) {
                    selectedWeekIndex = 0;
                }
                if (selectedWeekIndex >= weeks.length) {
                    selectedWeekIndex = Math.max(0, weeks.length - 1);
                }

                const activeWeek = weeks[selectedWeekIndex] || [];
                weekLabel.textContent = `Week ${selectedWeekIndex + 1}`;

                prevWeekBtn.disabled = selectedWeekIndex <= 0;
                nextWeekBtn.disabled = selectedWeekIndex >= weeks.length - 1;

                daysGrid.innerHTML = "";
                activeWeek.forEach(day => {
                    const cell = document.createElement("div");
                    cell.className = `schedule-day-cell${day.inMonth ? "" : " is-muted"}`;
                    cell.innerHTML = `
                        <span class="schedule-day-name">${day.dayName}</span>
                        <span class="schedule-day-date">${day.dateNum}</span>
                    `;
                    daysGrid.appendChild(cell);
                });

                const weekDates = new Set(activeWeek.map(day => day.iso));
                const filteredSchedules = getFilteredSchedules().filter(item => weekDates.has(item.date));

                listing.innerHTML = "";

                if (!filteredSchedules.length) {
                    listing.innerHTML = `
                        <div class="schedule-empty">
                            <div class="schedule-empty-title">No Schedule Yet</div>
                            <div class="schedule-empty-copy">
                                Upcoming games and event dates will appear here once this player adds them.
                            </div>
                        </div>
                    `;
                    return;
                }

                filteredSchedules.sort((a, b) => {
                    const aTime = getScheduleDateTime(a).getTime();
                    const bTime = getScheduleDateTime(b).getTime();
                    return aTime - bTime;
                });

                filteredSchedules.forEach(item => {
                    const row = document.createElement("div");
                    row.className = "schedule-row";

                    const locationLine = [item.location, item.venue].filter(Boolean).join(" ");
                    const notesLine = item.notes ? `<div class="schedule-event-sub">${escapeHtml(item.notes)}</div>` : "";
                    const statusLine = item.status ? `<div class="schedule-event-sub">${escapeHtml(item.status)}</div>` : "";
                    const resultLine = (item.result || item.score)
                        ? `<div class="schedule-event-sub">${escapeHtml([item.result, item.score].filter(Boolean).join(" • "))}</div>`
                        : "";

                    row.innerHTML = `
                        <div class="schedule-date-block">
                            <span class="num">${item.day_number}</span>
                            <span class="mon">${item.month_short}</span>
                        </div>

                        <div>
                            <div class="schedule-event-time">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="{{ $primary }}" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 7v5l3 3"></path>
                                </svg>
                                <span>${escapeHtml(item.time)}</span>
                            </div>

                            <h3 class="schedule-event-title">${escapeHtml(item.title)}</h3>

                            ${locationLine ? `<div class="schedule-event-sub">${escapeHtml(locationLine)}</div>` : ""}
                            ${statusLine}
                            ${resultLine}
                            ${notesLine}
                        </div>
                    `;

                    listing.appendChild(row);
                });
            }

            function escapeHtml(value) {
                return String(value || "")
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            function setDefaultToNextUpcomingMatch() {
                if (!schedules.length) {
                    return;
                }

                const sortedSchedules = [...schedules].sort((a, b) => {
                    return getScheduleDateTime(a).getTime() - getScheduleDateTime(b).getTime();
                });

                const nextUpcoming = sortedSchedules.find(item => getScheduleDateTime(item).getTime() >= now.getTime()) || sortedSchedules[0];

                if (!nextUpcoming) {
                    return;
                }

                selectedYear = nextUpcoming.year || selectedYear;
                selectedMonth = nextUpcoming.month || selectedMonth;

                const weeks = getWeeksForMonth(selectedYear, selectedMonth);
                const weekIndex = weeks.findIndex(week => week.some(day => day.iso === nextUpcoming.date));
                selectedWeekIndex = weekIndex >= 0 ? weekIndex : 0;
            }

            yearSelect.addEventListener("change", function () {
                selectedYear = this.value;
                const monthsForYear = [...new Set(
                    schedules.filter(item => item.year === selectedYear).map(item => item.month)
                )].sort();
                selectedMonth = monthsForYear[0] || selectedMonth;
                selectedWeekIndex = 0;
                render();
            });

            monthSelect.addEventListener("change", function () {
                selectedMonth = this.value;
                selectedWeekIndex = 0;
                render();
            });

            searchInput.addEventListener("input", function () {
                searchTerm = this.value || "";
                render();
            });

            prevWeekBtn.addEventListener("click", function () {
                if (selectedWeekIndex > 0) {
                    selectedWeekIndex--;
                    render();
                }
            });

            nextWeekBtn.addEventListener("click", function () {
                const totalWeeks = getWeeksForMonth(selectedYear, selectedMonth).length;
                if (selectedWeekIndex < totalWeeks - 1) {
                    selectedWeekIndex++;
                    render();
                }
            });

            resetBtn.addEventListener("click", function () {
                searchTerm = "";
                searchInput.value = "";
                setDefaultToNextUpcomingMatch();
                render();
            });

            if (!schedules.length) {
                listing.innerHTML = `
                    <div class="schedule-empty">
                        <div class="schedule-empty-title">No Upcoming Games Yet</div>
                        <div class="schedule-empty-copy">
                            Once this player confirms attendance for club schedules, upcoming games will appear here.
                        </div>
                    </div>
                `;

                monthLabel.textContent = "No Schedule";
                weekLabel.textContent = "Week 1";
                prevWeekBtn.disabled = true;
                nextWeekBtn.disabled = true;
                daysGrid.innerHTML = dayNames.map(name => `
                    <div class="schedule-day-cell is-muted">
                        <span class="schedule-day-name">${name}</span>
                        <span class="schedule-day-date">--</span>
                    </div>
                `).join("");
                return;
            }

            setDefaultToNextUpcomingMatch();
            render();
        });
    </script>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>
