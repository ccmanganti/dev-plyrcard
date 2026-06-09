<?php

namespace App\Filament\Resources\ClubCoaches\Pages;

use App\Filament\Resources\ClubCoaches\ClubCoachResource;
use App\Mail\CoachAccountCreatedMail;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EditClubCoach extends EditRecord
{
    protected static string $resource = ClubCoachResource::class;

    protected string $managerRole = 'Club Manager';

    protected array $assignedTeamKeys = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->managerRole = $data['manager_role'] ?? 'Club Manager';
        $this->assignedTeamKeys = $data['assigned_team_keys'] ?? [];

        $data['phone'] = \App\Support\PhoneFormatter::normalize($data['phone'] ?? null);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        unset($data['plain_password'], $data['manager_role'], $data['assigned_club_league_ids'], $data['assigned_team_keys']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);

        $record->syncRoles([$this->managerRole]);

        ClubCoachResource::syncAssignments($record, $this->managerRole, $this->assignedTeamKeys);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendPasswordEmail')
                ->label('Send Password')
                ->icon('heroicon-m-envelope')
                ->form([
                    TextInput::make('password')
                        ->label('New Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8),
                ])
                ->action(function (array $data): void {
                    $this->record->forceFill([
                        'password' => Hash::make($data['password']),
                    ])->save();

                    Mail::to($this->record->email)->send(new CoachAccountCreatedMail(
                        coach: $this->record,
                        plainPassword: $data['password'],
                        loginUrl: url('/admin/login'),
                        accessTitle: $this->record->roles->pluck('name')->implode(', ') ?: 'Coach',
                    ));

                    $this->record->forceFill([
                        'coach_account_credentials_sent_at' => now(),
                    ])->save();
                }),

            DeleteAction::make()
                ->before(fn () => \App\Models\TeamManagerAssignment::query()->where('user_id', $this->record->id)->delete()),
        ];
    }
}