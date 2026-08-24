@php
    // Backwards-compatible filename only. Email verification is intentionally
    // not part of registration anymore; older callers render the welcome email.
    $dashboardUrl = trim((string) ($dashboardUrl ?? '')) ?: url('/admin');
    $isMyJourney = (bool) ($isMyJourney ?? false);
@endphp
@include('emails.plyrcard-registration-welcome', [
    'user' => $user,
    'dashboardUrl' => $dashboardUrl,
    'planKey' => $planKey ?? null,
    'isMyJourney' => $isMyJourney,
])