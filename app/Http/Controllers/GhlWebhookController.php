<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GhlWebhookController extends Controller
{
    public function handle(Request $request)
    {   
        Log::info('Webhook headers', [
        'x_webhook_secret' => $request->header('X-Webhook-Secret'),
        'expected' => config('services.ghl.webhook_secret'),
        ]);

        if ($request->header('X-Webhook-Secret') !== config('services.ghl.webhook_secret')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 🔐 1. Verify secret
        if ($request->header('X-Webhook-Secret') !== config('services.ghl.webhook_secret')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 🧾 2. Get payload
        $data = $request->all();

        Log::info('GHL Webhook Received', $data);

        $email = $data['email'] ?? null;
        $contactId = $data['contact_id'] ?? null;
        $planId = $data['plan_id'] ?? null;
        $planName = $data['plan_name'] ?? null;
        $status = $data['status'] ?? null;

        if (! $email) {
            return response()->json(['message' => 'Missing email'], 200);
        }

        // 👤 3. Find user
        $user = User::where('email', $email)->first();

        if (! $user) {
            Log::warning('User not found for webhook', ['email' => $email]);
            return response()->json(['message' => 'User not found'], 200);
        }

        // 🧠 4. Save GHL contact_id if not set
        if ($contactId && ! $user->ghl_contact_id) {
            $user->update(['ghl_contact_id' => $contactId]);
        }

        // 🎯 5. Map plan → role
        $role = $this->mapPlanToRole($planId, $planName);

        // 🔄 6. Sync roles
        $this->syncUserRole($user, $role, $status);

        return response()->json(['success' => true]);
    }

    private function mapPlanToRole($planId, $planName)
    {
        $map = config('ghl-plans.map');

        // Prefer plan_id
        if ($planId && isset($map[$planId])) {
            return $map[$planId];
        }

        // fallback to name
        return match ($planName) {
            'Rookie' => 'Rookie',
            'Plyr' => 'Plyr',
            'My Journey' => 'My Journey',
            default => null,
        };
    }

    private function syncUserRole($user, $role, $status)
    {
        $planRoles = ['Rookie', 'Plyr', 'My Journey'];

        // Keep non-plan roles
        $otherRoles = $user->roles
            ->pluck('name')
            ->reject(fn ($r) => in_array($r, $planRoles))
            ->values()
            ->all();

        // If inactive → remove plan roles
        if (! in_array($status, ['active', 'trialing'])) {
            $user->syncRoles($otherRoles);
            return;
        }

        if (! $role) {
            return;
        }

        $user->syncRoles([...$otherRoles, $role]);
    }
}