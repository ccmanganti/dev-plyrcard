<?php

namespace App\Filament\Resources\ClubCoaches\Pages;

use App\Filament\Resources\ClubCoaches\ClubCoachResource;
use App\Mail\CoachAccountCreatedMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class CreateClubCoach extends CreateRecord
{
    protected static string $resource = ClubCoachResource::class;

    protected string $managerRole = 'Club Manager';

    protected array $assignedTeamKeys = [];

    protected string $plainPassword = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->plainPassword = (string) $data['password'];
        $this->managerRole = $data['manager_role'] ?? 'Club Manager';
        $this->assignedTeamKeys = $data['assigned_team_keys'] ?? [];

        $data['password'] = \Illuminate\Support\Facades\Hash::make($this->plainPassword);
        $data['phone'] = \App\Support\PhoneFormatter::normalize($data['phone'] ?? null);
        $data['club_manager_created_at'] = now();

        unset($data['plain_password'], $data['manager_role'], $data['assigned_club_league_ids'], $data['assigned_team_keys']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        $record->syncRoles([$this->managerRole]);

        ClubCoachResource::syncAssignments($record, $this->managerRole, $this->assignedTeamKeys);

        Mail::to($record->email)->send(new CoachAccountCreatedMail(
            coach: $record,
            plainPassword: $this->plainPassword,
            loginUrl: url('/admin/login'),
            accessTitle: $this->managerRole,
        ));

        $record->forceFill(['coach_account_credentials_sent_at' => now()])->save();

        return $record;
    }
}