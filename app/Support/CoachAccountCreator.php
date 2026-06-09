<?php

namespace App\Support;

use App\Mail\CoachAccountCreatedMail;
use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\TeamManagerAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CoachAccountCreator
{
    public static function create(array $data, Club $club): User
    {
        $role = $data['manager_role'] ?? 'Club Manager';

        if (! in_array($role, ['Club Manager', 'Team Manager'], true)) {
            throw ValidationException::withMessages([
                'manager_role' => 'Invalid coach access level.',
            ]);
        }

        Role::findOrCreate($role);

        $password = $data['password'];

        $coach = User::create([
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'title' => $data['title'] ?? null,
            'email' => strtolower(trim((string) $data['email'])),
            'phone' => PhoneFormatter::normalize($data['phone'] ?? null),
            'club_id' => $club->id,
            'password' => Hash::make($password),
            'club_manager_created_at' => now(),
        ]);

        $coach->syncRoles([$role]);

        static::syncTeamAssignments(
            coach: $coach,
            role: $role,
            club: $club,
            clubLeagueIds: $data['assigned_club_league_ids'] ?? [],
            teamNames: $data['assigned_team_names'] ?? [],
        );

        Mail::to($coach->email)->send(new CoachAccountCreatedMail(
            coach: $coach,
            plainPassword: $password,
            loginUrl: url('/admin/login'),
            accessTitle: $role,
        ));

        $coach->forceFill([
            'coach_account_credentials_sent_at' => now(),
        ])->save();

        return $coach;
    }

    public static function update(User $coach, array $data, Club $club): User
    {
        $role = $data['manager_role'] ?? ($coach->hasRole('Team Manager') ? 'Team Manager' : 'Club Manager');

        if (! in_array($role, ['Club Manager', 'Team Manager'], true)) {
            throw ValidationException::withMessages([
                'manager_role' => 'Invalid coach access level.',
            ]);
        }

        $update = [
            'first_name' => trim((string) ($data['first_name'] ?? $coach->first_name)),
            'last_name' => trim((string) ($data['last_name'] ?? $coach->last_name)),
            'title' => $data['title'] ?? null,
            'email' => strtolower(trim((string) ($data['email'] ?? $coach->email))),
            'phone' => PhoneFormatter::normalize($data['phone'] ?? null),
            'club_id' => $club->id,
        ];

        if (filled($data['password'] ?? null)) {
            $update['password'] = Hash::make($data['password']);
        }

        $coach->forceFill($update)->save();
        $coach->syncRoles([$role]);

        static::syncTeamAssignments(
            coach: $coach,
            role: $role,
            club: $club,
            clubLeagueIds: $data['assigned_club_league_ids'] ?? [],
            teamNames: $data['assigned_team_names'] ?? [],
        );

        return $coach;
    }

    public static function syncTeamAssignments(User $coach, string $role, Club $club, array $clubLeagueIds, array $teamNames): void
    {
        TeamManagerAssignment::query()
            ->where('user_id', $coach->id)
            ->delete();

        if ($role !== 'Team Manager') {
            return;
        }

        foreach ($clubLeagueIds as $clubLeagueId) {
            $clubLeague = ClubLeague::query()
                ->where('club_id', $club->id)
                ->whereKey($clubLeagueId)
                ->first();

            if (! $clubLeague) {
                continue;
            }

            foreach ($teamNames as $teamName) {
                TeamManagerAssignment::updateOrCreate([
                    'user_id' => $coach->id,
                    'club_id' => $club->id,
                    'club_league_id' => $clubLeague->id,
                    'team_name' => $teamName,
                ], [
                    'league_id' => $clubLeague->league_id,
                ]);
            }
        }
    }
}
