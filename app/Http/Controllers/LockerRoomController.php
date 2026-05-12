<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
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

        // Never let the drawer update the login email or security/role fields.
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

        return $this->success($request, 'Profile saved successfully.');
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

        return $this->success($request, 'Schedule saved successfully.');
    }

    public function updateSettings(Request $request): RedirectResponse|JsonResponse
    {
        return $this->success($request, 'Settings are coming soon.');
    }

    public function storeSupport(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'concern' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:5000'],
        ]);

        $body = "Support request from {$user?->first_name} {$user?->last_name} ({$user?->email})\n\nConcern: " . ($data['concern'] ?? 'General') . "\n\n" . $data['details'];

        try {
            Mail::raw($body, function ($message) use ($user) {
                $message->to('support@plyrcard.com')
                    ->subject('Locker Room support request');

                if ($user?->email) {
                    $message->replyTo($user->email);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->success($request, 'Support request submitted.');
    }

    public function storeReferral(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'friend_name' => ['required', 'string', 'max:255'],
            'friend_email' => ['nullable', 'email', 'max:255', 'required_without:friend_phone'],
            'friend_phone' => ['nullable', 'string', 'max:255', 'required_without:friend_email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $body = "Referral from {$user?->first_name} {$user?->last_name} ({$user?->email})\n\nFriend: {$data['friend_name']}\nEmail: " . ($data['friend_email'] ?? '-') . "\nPhone: " . ($data['friend_phone'] ?? '-') . "\n\nMessage:\n" . ($data['message'] ?? '');

        try {
            Mail::raw($body, function ($message) use ($user) {
                $message->to('support@plyrcard.com')
                    ->subject('New Locker Room referral');

                if ($user?->email) {
                    $message->replyTo($user->email);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->success($request, 'Referral submitted.');
    }
    private function success(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

}