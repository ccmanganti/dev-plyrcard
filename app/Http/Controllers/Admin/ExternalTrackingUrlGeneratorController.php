<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use App\Services\ExternalTrackingUrlService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExternalTrackingUrlGeneratorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));
        $selectedPlayer = null;
        $players = collect();

        if ($search !== '') {
            $players = User::query()
                ->whereHas('websites', fn ($q) => $q->where('is_active', true))
                ->where(function ($query) use ($search): void {
                    $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('personal_email', 'like', "%{$search}%")
                        ->orWhereHas('websites', function ($websiteQuery) use ($search): void {
                            $websiteQuery
                                ->where('slug', 'like', "%{$search}%")
                                ->orWhere('domain', 'like', "%{$search}%");
                        });
                })
                ->with(['websites' => fn ($q) => $q->where('is_active', true)->latest('updated_at')])
                ->limit(25)
                ->get();
        }

        if ($request->filled('player')) {
            $selectedPlayer = User::query()
                ->with(['websites' => fn ($q) => $q->where('is_active', true)->latest('updated_at')])
                ->findOrFail($request->integer('player'));
        }

        return view('admin.external-tracking-url-generator', [
            'search' => $search,
            'players' => $players,
            'selectedPlayer' => $selectedPlayer,
            'generated' => session('generated'),
        ]);
    }

    public function generate(
        Request $request,
        ExternalTrackingUrlService $generator
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'player_id' => ['required', 'integer', 'exists:users,id'],
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'campaign' => ['required', 'string', 'max:120'],
            'source' => ['required', 'string', 'max:80'],
            'medium' => ['required', 'string', 'max:80'],
            'include_contact_id' => ['nullable', 'boolean'],
            'include_email' => ['nullable', 'boolean'],
        ]);

        $player = User::query()->findOrFail($validated['player_id']);
        $website = Website::query()
            ->whereKey($validated['website_id'])
            ->where('user_id', $player->getKey())
            ->where('is_active', true)
            ->firstOrFail();

        $generated = $generator->generate($player, $website, [
            'campaign' => $validated['campaign'],
            'source' => $validated['source'],
            'medium' => $validated['medium'],
            'include_contact_id' => $request->boolean('include_contact_id'),
            'include_email' => $request->boolean('include_email'),
        ]);

        return redirect()
            ->route('admin.external-tracking-url-generator', ['player' => $player->getKey()])
            ->with('generated', $generated);
    }

    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $allowed = method_exists($user, 'isSuperadminOrImpersonating')
            && $user->isSuperadminOrImpersonating();

        $allowed = $allowed || (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['superadmin', 'admin', 'Superadmin', 'Admin'])
        );

        abort_unless($allowed, 403);
    }
}
