<?php

namespace App\Http\Controllers;

use App\Models\AdditionalServiceRequest;
use App\Models\BillingInformation;
use App\Models\LockerRoomReferral;
use App\Models\LockerRoomSupportRequest;
use App\Models\Schedule;
use App\Services\GoHighLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class LockerRoomController extends Controller
{
    public function updateProfile(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            'sport' => ['required', 'string', 'max:255'],
            'position' => ['required', 'array', 'min:1'],
            'position.*' => ['required', 'string', 'max:255'],
            'jersey_number' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth' => ['nullable', 'date'],
            'gpa' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'height' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'string', 'max:255'],
            'dominant_foot' => ['nullable', Rule::in(['left', 'right', 'both'])],

            'school_id' => ['nullable', 'integer'],
            'national_team_id' => ['nullable', 'integer'],
            'national_team_period' => ['nullable', 'string', 'max:255'],
            'team_name' => ['nullable', 'string', 'max:255'],

            'player_bio' => ['nullable', 'string'],
            'academic_accolades' => ['nullable', 'string'],
            'sports_accolades' => ['nullable', 'string'],

            'ig_handle' => ['nullable', 'string', 'max:255'],
            'x_handle' => ['nullable', 'string', 'max:255'],
            'yt_url' => ['nullable', 'url', 'max:2048'],
            'featured_video_url' => ['nullable', 'url', 'max:2048'],
            'featured_video_urls' => ['nullable', 'string'],
            'press' => ['nullable', 'string'],

            'parent' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:255'],
            'sec_parent' => ['nullable', 'string', 'max:255'],
            'sec_parent_phone' => ['nullable', 'string', 'max:255'],
            'club_coach' => ['nullable', 'string', 'max:255'],
            'club_coach_phone' => ['nullable', 'string', 'max:255'],
            'natl_coach' => ['nullable', 'string', 'max:255'],
            'natl_coach_phone' => ['nullable', 'string', 'max:255'],
            'tech_trainer' => ['nullable', 'string', 'max:255'],
            'tech_trainer_phone' => ['nullable', 'string', 'max:255'],
            'snc_trainer' => ['nullable', 'string', 'max:255'],
            'snc_trainer_phone' => ['nullable', 'string', 'max:255'],

            'plyrcard_image' => ['nullable', 'image', 'max:10240'],
            'player_image' => ['nullable', 'image', 'max:10240'],
            'action_image' => ['nullable', 'image', 'max:10240'],
            'mobile_hero_image' => ['nullable', 'image', 'max:10240'],
            'youtube_thumbnail' => ['nullable', 'image', 'max:10240'],
            'national_team_image' => ['nullable', 'image', 'max:10240'],
        ]);

        unset(
            $data['email'],
            $data['personal_email'],
            $data['parent_email'],
            $data['sec_parent_email'],
            $data['club_coach_email'],
            $data['natl_coach_email'],
            $data['tech_trainer_email'],
            $data['snc_trainer_email'],
            $data['password'],
            $data['roles']
        );

        if (array_key_exists('position', $data)) {
            $data['position'] = collect($data['position'] ?? [])
                ->map(fn ($position) => trim((string) $position))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        foreach (['plyrcard_image', 'player_image', 'action_image', 'mobile_hero_image', 'youtube_thumbnail', 'national_team_image'] as $imageField) {
            if ($request->hasFile($imageField)) {
                $data[$imageField] = $request->file($imageField)->store('user-player-images', 'public');
            } else {
                unset($data[$imageField]);
            }
        }

        $user->forceFill($data)->save();

        return $this->success($request, 'Profile saved successfully.', ['user' => $user->fresh()]);
    }

    public function storeSchedule(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'opponent' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['upcoming', 'completed', 'cancelled', 'postponed'])],
            'game_date' => ['required', 'date'],
            'game_time' => ['nullable'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'string', 'max:255'],
            'score' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_home' => ['nullable', 'boolean'],
        ]);

        $data['created_by_user_id'] = $user->id;
        $data['status'] = $data['status'] ?? 'upcoming';
        $data['is_home'] = $request->boolean('is_home');

        $schedule = Schedule::create($data);

        if (method_exists($schedule, 'users')) {
            $schedule->users()->syncWithoutDetaching([$user->id]);
        }

        return $this->success($request, 'Schedule saved successfully.', ['schedule' => $schedule]);
    }

    public function updateBilling(Request $request, GoHighLevelService $ghl): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:255'],
            'billing_company' => ['nullable', 'string', 'max:255'],
            'billing_address_1' => ['required', 'string', 'max:255'],
            'billing_address_2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['required', 'string', 'max:255'],
            'billing_state' => ['required', 'string', 'max:255'],
            'billing_postal_code' => ['required', 'string', 'max:40'],
            'billing_country' => ['required', 'string', 'max:255'],
            'cardholder_name' => ['nullable', 'string', 'max:255'],
            'card_last_four' => ['nullable', 'digits:4'],
            'card_expiration' => ['nullable', 'string', 'max:12'],
            'payment_type' => ['nullable', Rule::in(['card', 'bank', 'other'])],
        ]);

        $billing = BillingInformation::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        $sync = $ghl->upsertContact($user, [
            'name' => $data['billing_name'],
            'email' => $data['billing_email'],
            'phone' => $data['billing_phone'] ?? $user->phone,
            'address1' => trim($data['billing_address_1'] . ' ' . ($data['billing_address_2'] ?? '')),
            'city' => $data['billing_city'],
            'state' => $data['billing_state'],
            'postalCode' => $data['billing_postal_code'],
            'country' => $data['billing_country'],
            'companyName' => $data['billing_company'] ?? null,
        ], [], 'PlyrCard Billing Information');

        $billing->forceFill([
            'ghl_contact_id' => $sync['contact_id'] ?? $billing->ghl_contact_id,
            'ghl_sync_status' => ($sync['ok'] ?? false) ? 'synced' : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
            'ghl_sync_response' => $sync,
            'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : $billing->ghl_synced_at,
        ])->save();

        return $this->success($request, 'Billing information saved and synced.', ['billing' => $billing->fresh()]);
    }

    public function updateSettings(Request $request): RedirectResponse|JsonResponse
    {
        return $this->success($request, 'Settings are coming soon.');
    }

    public function storeSupport(Request $request, GoHighLevelService $ghl): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'concern' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $support = LockerRoomSupportRequest::create([
            'user_id' => $user->id,
            'concern' => $data['concern'],
            'details' => $data['details'],
            'status' => 'open',
        ]);

        $sync = $ghl->upsertContact($user, [], [], 'PlyrCard Support Request');
        $note = $ghl->addContactNote($sync['contact_id'] ?? null, "Support request: {$data['concern']}\n\n{$data['details']}");

        $support->forceFill([
            'ghl_contact_id' => $sync['contact_id'] ?? null,
            'ghl_sync_status' => ($sync['ok'] ?? false) ? 'synced' : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
            'ghl_sync_response' => ['contact' => $sync, 'note' => $note],
            'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : null,
        ])->save();

        $this->mailSupportCopy(
            $user,
            'Locker Room support request',
            "Support request from {$user->first_name} {$user->last_name} ({$user->email})\n\nConcern: {$data['concern']}\n\n{$data['details']}"
        );

        return $this->success($request, 'Support request submitted.', ['support_request' => $support->fresh()]);
    }

    public function storeReferral(Request $request, GoHighLevelService $ghl): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $data = $request->validate([
            'friend_name' => ['required', 'string', 'max:255'],
            'friend_email' => ['nullable', 'email', 'max:255', 'required_without:friend_phone'],
            'friend_phone' => ['nullable', 'string', 'max:255', 'required_without:friend_email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $referral = LockerRoomReferral::create([
            'user_id' => $user->id,
            'friend_name' => $data['friend_name'],
            'friend_email' => $data['friend_email'] ?? null,
            'friend_phone' => $data['friend_phone'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'new',
        ]);

        $nameParts = preg_split('/\s+/', trim($data['friend_name']), 2);
        $sync = $ghl->upsertContact($user, [
            'firstName' => $nameParts[0] ?? $data['friend_name'],
            'lastName' => $nameParts[1] ?? null,
            'name' => $data['friend_name'],
            'email' => $data['friend_email'] ?? null,
            'phone' => $data['friend_phone'] ?? null,
        ], [], 'PlyrCard Referral');

        $note = $ghl->addContactNote($sync['contact_id'] ?? null, "Referred by {$user->first_name} {$user->last_name} ({$user->email}).\n\nMessage: " . ($data['message'] ?? '-'));

        $referral->forceFill([
            'ghl_contact_id' => $sync['contact_id'] ?? null,
            'ghl_sync_status' => ($sync['ok'] ?? false) ? 'synced' : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
            'ghl_sync_response' => ['contact' => $sync, 'note' => $note],
            'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : null,
        ])->save();

        $this->mailSupportCopy(
            $user,
            'New Locker Room referral',
            "Referral from {$user->first_name} {$user->last_name} ({$user->email})\n\nFriend: {$data['friend_name']}\nEmail: " . ($data['friend_email'] ?? '-') . "\nPhone: " . ($data['friend_phone'] ?? '-') . "\n\nMessage:\n" . ($data['message'] ?? '')
        );

        return $this->success($request, 'Referral submitted.', ['referral' => $referral->fresh()]);
    }

    public function storeAdditionalService(Request $request, GoHighLevelService $ghl): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $services = [
            'upgraded_site_design' => 'Upgraded Site Design',
            'starting_graphics_bundle' => 'Starting Graphics Bundle',
            'individual_graphic' => 'Individual Graphic',
            'domain' => 'Domain',
            'custom' => 'Custom / Other',
        ];

        $data = $request->validate([
            'service_key' => ['required', Rule::in(array_keys($services))],
            'service_name' => ['nullable', 'string', 'max:255'],
            'listed_price' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $serviceName = $data['service_name'] ?: $services[$data['service_key']];

        $serviceRequest = AdditionalServiceRequest::create([
            'user_id' => $user->id,
            'service_key' => $data['service_key'],
            'service_name' => $serviceName,
            'listed_price' => $data['listed_price'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'new',
        ]);

        $sync = $ghl->upsertContact($user, [], [], 'PlyrCard Additional Service');
        $note = $ghl->addContactNote($sync['contact_id'] ?? null, "Additional service request: {$serviceName}\nListed price: " . ($data['listed_price'] ?? '-') . "\n\nNotes: " . ($data['notes'] ?? '-'));

        $serviceRequest->forceFill([
            'ghl_contact_id' => $sync['contact_id'] ?? null,
            'ghl_sync_status' => ($sync['ok'] ?? false) ? 'synced' : (($sync['skipped'] ?? false) ? 'skipped' : 'failed'),
            'ghl_sync_response' => ['contact' => $sync, 'note' => $note],
            'ghl_synced_at' => ($sync['ok'] ?? false) ? now() : null,
        ])->save();

        $this->mailSupportCopy(
            $user,
            'New Additional Service request',
            "Additional service request from {$user->first_name} {$user->last_name} ({$user->email})\n\nService: {$serviceName}\nPrice: " . ($data['listed_price'] ?? '-') . "\n\nNotes:\n" . ($data['notes'] ?? '')
        );

        return $this->success($request, 'Additional service request submitted.', ['additional_service_request' => $serviceRequest->fresh()]);
    }

    private function mailSupportCopy($user, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to('support@plyrcard.com')->subject($subject);

                if ($user?->email) {
                    $message->replyTo($user->email);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function success(Request $request, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
            ], $extra));
        }

        return back()->with('success', $message);
    }
}