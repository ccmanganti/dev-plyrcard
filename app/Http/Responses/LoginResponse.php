<?php

namespace App\Http\Responses;

use App\Models\Website;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->to('/admin');
        }

        $website = Website::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('is_published', true)
            ->latest('updated_at')
            ->first();

        if (! $website) {
            return redirect()->to('/admin');
        }

        if (! blank($website->domain)) {
            $domain = trim($website->domain);
            $domain = preg_replace('#^https?://#i', '', $domain);
            $domain = rtrim($domain, '/');

            return redirect()->away('https://' . $domain);
        }

        $slug = $website->slug ?: Str::slug($website->name);

        if (blank($slug)) {
            return redirect()->to('/admin');
        }

        return redirect()->to('/' . $slug);
    }
}