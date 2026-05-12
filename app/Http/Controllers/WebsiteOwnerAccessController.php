<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WebsiteOwnerAccessController extends Controller
{
    public function redirectToOwnedWebsite(Request $request, Website $website)
    {
        abort_unless(Auth::check(), 403);
        abort_unless((int) $website->user_id === (int) Auth::id(), 403);
        abort_unless($website->is_active && $website->is_published, 404);

        $token = Crypt::encryptString(json_encode([
            'user_id' => Auth::id(),
            'website_id' => $website->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'nonce' => Str::random(32),
        ]));

        $target = $this->websiteBaseUrl($website);

        if (! $target) {
            return redirect('/')->with('error', 'Your website is not ready yet.');
        }

        return redirect()->away(rtrim($target, '/') . '/locker-room/owner-access?token=' . urlencode($token));
    }

    public function consumeOwnerAccess(Request $request)
    {
        $token = (string) $request->query('token', '');

        abort_if($token === '', 403);

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            abort(403);
        }

        abort_unless(isset($payload['user_id'], $payload['website_id'], $payload['expires_at']), 403);
        abort_if(now()->timestamp > (int) $payload['expires_at'], 403);

        $website = Website::query()
            ->whereKey($payload['website_id'])
            ->where('user_id', $payload['user_id'])
            ->where('is_active', true)
            ->where('is_published', true)
            ->firstOrFail();

        if (! blank($website->domain)) {
            abort_unless($this->domainsMatch($request->getHost(), $website->domain), 403);
        }

        Auth::loginUsingId((int) $payload['user_id'], true);
        $request->session()->regenerate();

        if (! blank($website->domain)) {
            return redirect('/');
        }

        if (! blank($website->slug)) {
            return redirect('/' . ltrim($website->slug, '/'));
        }

        if (! blank($website->name)) {
            return redirect('/' . Str::slug($website->name));
        }

        return redirect('/');
    }

    private function websiteBaseUrl(Website $website): ?string
    {
        if (! blank($website->domain)) {
            $domain = $this->normalizeDomain($website->domain);

            return $domain ? 'https://' . $domain : null;
        }

        if (! blank($website->slug)) {
            return url('/' . ltrim($website->slug, '/'));
        }

        if (! blank($website->name)) {
            return url('/' . Str::slug($website->name));
        }

        return null;
    }

    private function normalizeDomain(?string $value): string
    {
        $domain = strtolower(trim((string) $value));
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('/:\d+$/', '', $domain);

        return rtrim($domain, '/');
    }

    private function domainBase(?string $value): string
    {
        return preg_replace('/^www\./i', '', $this->normalizeDomain($value));
    }

    private function domainsMatch(?string $requestHost, ?string $websiteDomain): bool
    {
        $host = $this->normalizeDomain($requestHost);
        $hostBase = $this->domainBase($host);
        $domain = $this->normalizeDomain($websiteDomain);
        $domainBase = $this->domainBase($domain);

        return filled($hostBase)
            && filled($domainBase)
            && ($host === $domain || $hostBase === $domainBase);
    }
}