<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use Illuminate\Console\Command;

class TestPlyrcardSystemEmail extends Command
{
    protected $signature = 'plyrcard:test-system-email {email}';
    protected $description = 'Send a PLYRCARD system email through the existing GHL Conversations email connection.';

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
            $this->info('GHL accepted the PLYRCARD system email.');
            $this->line('Recipient: ' . $email);
            $this->line('Contact ID: ' . ($result['contact_id'] ?? 'n/a'));
            $this->line('Message ID: ' . ($result['message_id'] ?? 'n/a'));
            return self::SUCCESS;
        }

        $this->error('GHL did not send the PLYRCARD system email.');
        $this->line('HTTP status: ' . ($result['status'] ?? 'n/a'));
        $this->line('Error: ' . ($result['error'] ?? 'Unknown error'));

        return self::FAILURE;
    }
}
