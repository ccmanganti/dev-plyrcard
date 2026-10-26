<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use Illuminate\Console\Command;

class TestPlyrcardSystemEmail extends Command
{
    protected $signature = 'plyrcard:test-system-email {email}';
    protected $description = 'Send a PLYRCARD system email using the environment-aware delivery transport.';

    public function handle(PlyrcardSystemEmailService $systemEmail): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orWhereRaw('LOWER(personal_email) = ?', [$email])
            ->first();

        if (! $user) {
            $this->error('No PLYRCARD user was found for ' . $email . '.');
            return self::FAILURE;
        }

        $result = $systemEmail->sendTest($user);

        if ($result['success'] ?? false) {
            $this->info('PLYRCARD accepted the email through a deliverable transport.');
            $this->line('Recipient: ' . $email);
            $this->line('From: ' . ($result['from_email'] ?? 'n/a'));
            $this->line('Transport: ' . ($result['transport'] ?? 'unknown'));
            $this->newLine();
            $this->warn('The transport accepted the message; final inbox placement can still depend on the receiving mail provider.');
            return self::SUCCESS;
        }

        $this->error('PLYRCARD could not deliver the system email through any available transport.');
        $this->line('Error: ' . ($result['error'] ?? 'Unknown error'));
        return self::FAILURE;
    }
}