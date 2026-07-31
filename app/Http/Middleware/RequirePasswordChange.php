<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! (bool) $user->must_change_password) {
            return $next($request);
        }

        if ($this->shouldBypassPasswordChange($user)) {
            return $next($request);
        }

        // Always permit Livewire's update endpoint. The browser may omit the
        // Referer header, so referer-based checks can leave the form spinning.
        // Protected panel GET routes remain blocked below.
        if ($request->routeIs('livewire.update')
            || $request->is('livewire/update')
            || $request->is('livewire/update/*')
            || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.pages.force-password-change')
            || $request->is('admin/force-password-change')
            || $request->is('admin/force-password-change/*')) {
            return $next($request);
        }

        return redirect()->to($this->forcePasswordChangeUrl());
    }

    protected function forcePasswordChangeUrl(): string
    {
        if (app('router')->has('filament.admin.pages.force-password-change')) {
            return route('filament.admin.pages.force-password-change');
        }

        return url('/admin/force-password-change');
    }

    protected function shouldBypassPasswordChange(User $user): bool
    {
        if (method_exists($user, 'isSuperadminOrImpersonating')) {
            return $user->isSuperadminOrImpersonating();
        }

        return method_exists($user, 'hasRole') && $user->hasRole('superadmin');
    }
}