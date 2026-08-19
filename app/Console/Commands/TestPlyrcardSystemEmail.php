<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use Illuminate\Console\Command;

class TestPlyrcardSystemEmail extends Command
{
    protected $signature = 'plyrcard:test-system-email {email}';
    protected $description = 'Send a PLYRCARD system email using the hosting server native PHP mail() transport.';

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
            $this->info('PHP mail() accepted the PLYRCARD system email for handoff.');
            $this->line('Recipient: ' . $email);
            $this->line('From: ' . ($result['from_email'] ?? 'n/a'));
            $this->line('Envelope sender used: ' . (($result['used_envelope_sender'] ?? false) ? 'yes' : 'fallback without -f'));
            $this->newLine();
            $this->warn('mail() returning true means the local mail system accepted the message; final inbox delivery is still determined by the hosting mail server.');
            return self::SUCCESS;
        }

        $this->error('PHP mail() did not accept the PLYRCARD system email.');
        $this->line('Error: ' . ($result['error'] ?? 'Unknown error'));
        return self::FAILURE;
    }
}