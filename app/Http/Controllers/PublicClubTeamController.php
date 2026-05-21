<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicClubTeamController extends Controller
{
    public function club(string $clubSlug): View
    {
        $club = Club::query()
            ->with(['league', 'teams.club.league'])
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $teams = $club->teams()
            ->with(['club.league'])
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->orderBy('name')
            ->get();

        return view('public.club-landing', [
            'club' => $club,
            'teams' => $teams,
            'coachCheckIn' => session('coach_checkin'),
            'savedPlayers' => session('coach_saved_players', []),
        ]);
    }

    public function team(string $clubSlug, string $gender, string $teamSlug): View
    {
        $club = Club::query()
            ->with('league')
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $team = Team::query()
            ->with(['club.league'])
            ->where('club_id', $club->id)
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $requestedGender = $this->normalizeLandingGenderSegment($gender);
        $teamGender = $this->normalizeLandingGenderSegment($team->landingGenderSegment());

        abort_unless($teamGender === $requestedGender, 404);

        $players = $this->playersForTeam($team, $club);

        return view('public.team-landing', [
            'team' => $team,
            'club' => $club,
            'players' => $players,
            'coachCheckIn' => session('coach_checkin'),
            'savedPlayers' => session('coach_saved_players', []),
        ]);
    }

    public function legacyTeam(string $teamSlug): RedirectResponse
    {
        $team = Team::query()
            ->with(['club.league'])
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $club = $team->club;

        abort_unless(
            $club
            && $club->has_landing_page
            && $club->landing_page_is_published
            && filled($club->landing_page_slug),
            404
        );

        return redirect()->route('clubs.teams.landing', [
            'clubSlug' => $club->landing_page_slug,
            'gender' => $this->normalizeLandingGenderSegment($team->landingGenderSegment()),
            'teamSlug' => $team->landing_page_slug,
        ], 301);
    }

    public function coachCheckIn(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $validated = $request->validate([
            'school' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        session([
            'coach_checkin' => [
                'club_id' => $club->id,
                'club_slug' => $club->landing_page_slug,
                'school' => $validated['school'],
                'name' => $validated['name'],
                'title' => $validated['title'] ?? '',
                'email' => strtolower($validated['email']),
                'checked_in_at' => now()->toDateTimeString(),
            ],
        ]);

        session()->put('coach_saved_players', session('coach_saved_players', []));

        return back()->with('coach_checkin_success', 'You are checked in. You can now save players while reviewing this club.');
    }

    public function coachCheckOut(Request $request, string $clubSlug): RedirectResponse
    {
        Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $request->session()->forget([
            'coach_checkin',
            'coach_saved_players',
        ]);

        return back()->with('coach_checkin_success', 'You have been checked out.');
    }

    public function savePlayer(
        Request $request,
        string $clubSlug,
        string $gender,
        string $teamSlug,
        User $player
    ): RedirectResponse|JsonResponse {
        $club = Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $team = Team::query()
            ->with(['club.league'])
            ->where('club_id', $club->id)
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $requestedGender = $this->normalizeLandingGenderSegment($gender);
        $teamGender = $this->normalizeLandingGenderSegment($team->landingGenderSegment());

        abort_unless($teamGender === $requestedGender, 404);
        abort_unless($this->playerBelongsToTeam($player, $team, $club), 404);

        $coachCheckIn = session('coach_checkin');

        if (! is_array($coachCheckIn) || empty($coachCheckIn['email'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please check in as a coach before saving players.',
                ], 422);
            }

            return back()->withErrors([
                'coach_checkin' => 'Please check in as a coach before saving players.',
            ]);
        }

        $player->loadMissing(['school', 'club.league', 'league', 'nationalTeam', 'websites']);

        $coachEmail = strtolower((string) ($coachCheckIn['email'] ?? ''));
        $playerUrl = $this->playerWebsiteUrl($player);

        $savedPlayers = collect(session('coach_saved_players', []));

        $alreadySaved = $savedPlayers->contains(function ($saved) use ($player, $club, $team, $coachEmail) {
            return (int) ($saved['player_id'] ?? 0) === (int) $player->id
                && (int) ($saved['club_id'] ?? 0) === (int) $club->id
                && (int) ($saved['team_id'] ?? 0) === (int) $team->id
                && strtolower((string) ($saved['coach_email'] ?? '')) === $coachEmail;
        });

        $savedPayload = [
            'player_id' => $player->id,
            'player_name' => trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? '')),
            'player_email' => $player->email,
            'player_personal_email' => $player->personal_email,
            'player_phone' => $player->phone,
            'player_url' => $playerUrl,

            'parent' => $player->parent,
            'parent_email' => $player->parent_email,
            'parent_phone' => $player->parent_phone,

            'sec_parent' => $player->sec_parent,
            'sec_parent_email' => $player->sec_parent_email,
            'sec_parent_phone' => $player->sec_parent_phone,

            'club_coach' => $player->club_coach,
            'club_coach_email' => $player->club_coach_email,
            'club_coach_phone' => $player->club_coach_phone,

            'jersey_number' => $player->jersey_number,
            'position' => is_array($player->position)
                ? implode(', ', array_filter($player->position))
                : $player->position,
            'year' => $player->year,
            'height' => $player->height,
            'weight' => $player->weight,
            'gpa' => $player->gpa,
            'city' => $player->city,
            'state' => $player->state,
            'school' => $player->school?->name,
            'sport' => $player->sport,

            'club_id' => $club->id,
            'club_name' => $club->name,
            'team_id' => $team->id,
            'team_name' => $team->name,
            'league_name' => $club->league?->name,

            'coach_email' => $coachEmail,
            'coach_name' => $coachCheckIn['name'] ?? '',
            'coach_school' => $coachCheckIn['school'] ?? '',
            'coach_title' => $coachCheckIn['title'] ?? '',

            'saved_at' => now()->toDateTimeString(),
        ];

        if (! $alreadySaved) {
            $savedPlayers->push($savedPayload);
            session(['coach_saved_players' => $savedPlayers->values()->all()]);
        }

        $emailSent = false;
        $emailError = null;

        if (! $alreadySaved) {
            try {
                $this->sendSavedPlayerEmail($coachEmail, $coachCheckIn, $savedPayload, $player, $club, $team);
                $emailSent = true;
            } catch (\Throwable $exception) {
                report($exception);
                $emailError = 'Player was saved, but the email could not be sent.';
            }
        }

        $message = $alreadySaved
            ? 'Player already saved.'
            : ($emailSent ? 'Player saved and emailed to the coach.' : ($emailError ?: 'Player saved.'));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'email_sent' => $emailSent,
                'saved_count' => $savedPlayers->count(),
                'player_id' => $player->id,
                'saved_player' => $savedPayload,
            ]);
        }

        return back()->with('player_save_success', $message);
    }

    public function unsavePlayer(
        Request $request,
        string $clubSlug,
        string $gender,
        string $teamSlug,
        User $player
    ): RedirectResponse|JsonResponse {
        $club = Club::query()
            ->where('landing_page_slug', $clubSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $team = Team::query()
            ->where('club_id', $club->id)
            ->where('landing_page_slug', $teamSlug)
            ->where('has_landing_page', true)
            ->where('landing_page_is_published', true)
            ->firstOrFail();

        $requestedGender = $this->normalizeLandingGenderSegment($gender);
        $teamGender = $this->normalizeLandingGenderSegment($team->landingGenderSegment());

        abort_unless($teamGender === $requestedGender, 404);

        $coachCheckIn = session('coach_checkin');
        $coachEmail = strtolower((string) ($coachCheckIn['email'] ?? ''));

        $savedPlayers = collect(session('coach_saved_players', []))
            ->reject(function ($saved) use ($player, $club, $team, $coachEmail) {
                return (int) ($saved['player_id'] ?? 0) === (int) $player->id
                    && (int) ($saved['club_id'] ?? 0) === (int) $club->id
                    && (int) ($saved['team_id'] ?? 0) === (int) $team->id
                    && strtolower((string) ($saved['coach_email'] ?? '')) === $coachEmail;
            })
            ->values();

        session(['coach_saved_players' => $savedPlayers->all()]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Player removed from saved list.',
                'saved_count' => $savedPlayers->count(),
                'player_id' => $player->id,
            ]);
        }

        return back()->with('player_save_success', 'Player removed from saved list.');
    }

    protected function playersForTeam(Team $team, Club $club)
    {
        return User::query()
            ->with([
                'school',
                'club.league',
                'league',
                'nationalTeam',
                'roles',
                'websites' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->where('is_published', true)
                        ->latest('updated_at');
                },
            ])
            ->where('club_id', $club->id)
            ->where(function ($query) use ($team) {
                $query
                    ->where('team_name', $team->name)
                    ->orWhere('team_name', trim((string) $team->name));
            })
            ->get()
            ->sortBy(function (User $player) {
                $number = trim((string) ($player->jersey_number ?? ''));

                if ($number === '' || ! is_numeric($number)) {
                    return 9999;
                }

                return (int) $number;
            })
            ->values();
    }

    protected function normalizeLandingGenderSegment(string $gender): string
    {
        $gender = strtolower(trim($gender));

        return match ($gender) {
            'mens', 'men', 'boys', 'boy', 'male' => 'boys',
            'womens', 'women', 'girls', 'girl', 'female' => 'girls',
            default => $gender,
        };
    }

    protected function playerBelongsToTeam(User $player, Team $team, Club $club): bool
    {
        $playerTeamName = trim((string) ($player->team_name ?? ''));
        $teamName = trim((string) ($team->name ?? ''));

        if ($playerTeamName === '' || $teamName === '') {
            return false;
        }

        return (int) ($player->club_id ?? 0) === (int) $club->id
            && strcasecmp($playerTeamName, $teamName) === 0;
    }

    protected function playerWebsiteUrl(User $player): ?string
    {
        $website = $player->websites
            ? $player->websites->firstWhere('is_published', true) ?: $player->websites->first()
            : null;

        if (! $website) {
            return null;
        }

        if (filled($website->domain)) {
            return 'https://' . preg_replace('/^https?:\/\//', '', (string) $website->domain);
        }

        if (filled($website->slug)) {
            return url('/' . ltrim((string) $website->slug, '/'));
        }

        return null;
    }

    protected function sendSavedPlayerEmail(
        string $coachEmail,
        array $coachCheckIn,
        array $savedPlayer,
        User $player,
        Club $club,
        Team $team
    ): void {
        $coachEmail = strtolower(trim($coachEmail));

        if (! filter_var($coachEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Native Domain Mail
        |--------------------------------------------------------------------------
        |
        | This intentionally does NOT depend on Laravel SMTP/.env mail settings.
        | It works like the sample RSVP script: PHP's native mail() sends using
        | a domain sender/envelope sender from plyrcard.com.
        |
        | Note: the hosting server still has to allow PHP mail(). For best
        | deliverability, support@plyrcard.com should exist and the domain should
        | have SPF/DKIM configured by the host/email provider.
        |
        */

        $fromEmail = 'support@plyrcard.com';
        $fromName = 'PlyrCard';
        $subjectPlayerName = $savedPlayer['player_name'] ?: 'Player';
        $subject = "Saved Player: {$subjectPlayerName} - {$club->name}";

        $replyTo = $player->email
            ?: $player->personal_email
            ?: $player->parent_email
            ?: $player->club_coach_email
            ?: $fromEmail;

        $htmlBody = view('emails.coach-saved-player', [
            'coach' => $coachCheckIn,
            'savedPlayer' => $savedPlayer,
            'player' => $player,
            'club' => $club,
            'team' => $team,
        ])->render();

        $textBody = $this->buildSavedPlayerTextEmail(
            $coachCheckIn,
            $savedPlayer,
            $player,
            $club,
            $team
        );

        $sent = $this->sendNativeMultipartMail(
            to: $coachEmail,
            subject: $subject,
            textBody: $textBody,
            htmlBody: $htmlBody,
            fromEmail: $fromEmail,
            fromName: $fromName,
            replyTo: $replyTo,
            envelopeFrom: $fromEmail
        );

        if (! $sent) {
            logger()->warning('Coach saved player native email failed.', [
                'to' => $coachEmail,
                'from' => $fromEmail,
                'player_id' => $player->id,
                'club_id' => $club->id,
                'team_id' => $team->id,
            ]);
        }
    }

    protected function sendNativeMultipartMail(
        string $to,
        string $subject,
        string $textBody,
        string $htmlBody,
        string $fromEmail = 'support@plyrcard.com',
        string $fromName = 'PlyrCard',
        string $replyTo = 'support@plyrcard.com',
        string $envelopeFrom = 'support@plyrcard.com'
    ): bool {
        $to = trim($to);
        $fromEmail = trim($fromEmail);
        $replyTo = trim($replyTo);
        $envelopeFrom = trim($envelopeFrom);

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = 'support@plyrcard.com';
        }

        if (! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = $fromEmail;
        }

        if (! filter_var($envelopeFrom, FILTER_VALIDATE_EMAIL)) {
            $envelopeFrom = $fromEmail;
        }

        $safeFromName = $this->sanitizeMailHeader($fromName ?: 'PlyrCard');
        $safeSubject = $this->sanitizeMailHeader($subject ?: 'Saved Player Information');
        $safeReplyTo = $this->sanitizeMailHeader($replyTo);

        $boundary = 'plyrcard_' . md5(uniqid((string) mt_rand(), true));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $safeFromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $safeReplyTo,
            'X-Mailer: PHP/' . phpversion(),
        ];

        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $textBody . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n"
            . "--{$boundary}--";

        $headerString = implode("\r\n", $headers);

        $sent = @mail($to, $safeSubject, $message, $headerString, '-f' . $envelopeFrom);

        if (! $sent) {
            $sent = @mail($to, $safeSubject, $message, $headerString);
        }

        return (bool) $sent;
    }

    protected function sanitizeMailHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    protected function buildSavedPlayerTextEmail(
        array $coachCheckIn,
        array $savedPlayer,
        User $player,
        Club $club,
        Team $team
    ): string {
        $lines = [
            'PlyrCard Saved Player',
            '',
            'Coach:',
            'Name: ' . ($coachCheckIn['name'] ?? ''),
            'School: ' . ($coachCheckIn['school'] ?? ''),
            'Title: ' . ($coachCheckIn['title'] ?? ''),
            'Email: ' . ($coachCheckIn['email'] ?? ''),
            '',
            'Player:',
            'Name: ' . ($savedPlayer['player_name'] ?? ''),
            'Jersey: ' . ($savedPlayer['jersey_number'] ?? ''),
            'Position: ' . ($savedPlayer['position'] ?? ''),
            'Class: ' . ($savedPlayer['year'] ?? ''),
            'Height: ' . ($savedPlayer['height'] ?? ''),
            'Weight: ' . ($savedPlayer['weight'] ?? ''),
            'GPA: ' . ($savedPlayer['gpa'] ?? ''),
            'Location: ' . trim(($savedPlayer['city'] ?? '') . ', ' . ($savedPlayer['state'] ?? ''), ', '),
            '',
            'Program:',
            'Club: ' . ($club->name ?? ''),
            'Team: ' . ($team->name ?? ''),
            'League: ' . ($club->league?->name ?? ''),
            '',
            'Website:',
            ($savedPlayer['website_url'] ?? '') ?: 'No published website available.',
            '',
            'Contact:',
            'Player Email: ' . (($savedPlayer['player_email'] ?? '') ?: 'N/A'),
            'Personal Email: ' . (($savedPlayer['player_personal_email'] ?? '') ?: 'N/A'),
            'Phone: ' . (($savedPlayer['player_phone'] ?? '') ?: 'N/A'),
            '',
            'Parent / Guardian:',
            'Parent: ' . (($savedPlayer['parent'] ?? '') ?: 'N/A'),
            'Parent Email: ' . (($savedPlayer['parent_email'] ?? '') ?: 'N/A'),
            'Parent Phone: ' . (($savedPlayer['parent_phone'] ?? '') ?: 'N/A'),
            '',
            'Coach Contact:',
            'Club Coach: ' . (($savedPlayer['club_coach'] ?? '') ?: 'N/A'),
            'Club Coach Email: ' . (($savedPlayer['club_coach_email'] ?? '') ?: 'N/A'),
            'Club Coach Phone: ' . (($savedPlayer['club_coach_phone'] ?? '') ?: 'N/A'),
            '',
            'Saved At: ' . (($savedPlayer['saved_at'] ?? '') ?: now()->toDateTimeString()),
        ];

        return implode("\n", $lines);
    }

}