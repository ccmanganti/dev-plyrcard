<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;

class WebsitePublishedEmailService
{
    public function send(User $user, Website $website): array
    {
        $recipient = $this->email($user->email ?: $user->personal_email);
        if (! $recipient) return ['success'=>false,'error'=>'Player email is unavailable.'];

        $user->loadMissing('roles');
        $hasAmplify = $this->hasRole($user, 'Amplify');
        $hasJumpstart = $this->hasRole($user, 'Jumpstart');
        $hasJourney = $this->hasRole($user, 'My Journey');
        $siteUrl = $this->siteUrl($website);
        $plans = (array) config('plyrcard-registration.plans', []);
        $journeyCents = (int) data_get($plans, 'my-journey.recurring_amount_cents', 4900);
        $amplifySetupCents = (int) data_get($plans, 'amplify.setup_fee_cents', 50000);
        $amplifyFirstMonth = (bool) data_get($plans, 'amplify.charge_first_month_upfront', true);
        $jumpstartCents = (int) config('plyrcard-registration.plans.jumpstart.setup_fee_cents', 14900);
        $jumpstartEnrollmentCents = $jumpstartCents + $journeyCents;
        $amplifyEnrollmentCents = $amplifySetupCents + ($amplifyFirstMonth ? $journeyCents : 0);
        $manageUrl = rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . '/admin/my-journey';
        $loginUrl = rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . '/admin/login';

        $offers = [];
        if (! $hasJourney && ! $hasAmplify) {
            $offers[] = ['name'=>'My Journey','price'=>$this->money($journeyCents) . '/mo','copy'=>'Your recruiting HQ: custom domain, coach database, outreach tools, and engagement tracking.','cta'=>'Start My Journey'];
        }
        if (! $hasJumpstart && ! $hasAmplify) {
            $jumpPrice = $hasJourney ? $this->money($jumpstartCents) . ' one time' : $this->money($jumpstartEnrollmentCents) . ' due today';
            $jumpCopy = $hasJourney
                ? 'Add the ' . $this->money($jumpstartCents) . ' Jumpstart recruiting service to your active My Journey membership.'
                : 'Start My Journey and add Jumpstart in one checkout: the service plus your first monthly payment.';
            $offers[] = ['name'=>'Jumpstart','price'=>$jumpPrice,'copy'=>$jumpCopy,'cta'=>'Get Jumpstart'];
        }
        if (! $hasAmplify) {
            $ampPrice = $hasJourney ? $this->money($amplifySetupCents) . ' one time' : $this->money($amplifyEnrollmentCents) . ' due today';
            $ampCopy = $hasJourney
                ? 'Add the one-time Amplify service to your active My Journey membership.'
                : 'Start My Journey and add the full Amplify done-for-you recruiting service in one checkout.';
            $offers[] = ['name'=>'Amplify','price'=>$ampPrice,'copy'=>$ampCopy,'cta'=>'Explore Amplify'];
        }

        $html = $this->render($user, $siteUrl, $loginUrl, $manageUrl, $offers, $hasAmplify);
        return $this->sendHtml($user,$recipient,'Your PLYRCARD is live',$html,'website_published');
    }

    protected function render(User $user, string $siteUrl, string $loginUrl, string $manageUrl, array $offers, bool $hasAmplify): string
    {
        $first = $this->e((string) ($user->first_name ?: 'Player'));
        $site = $this->e($siteUrl);
        $login = $this->e($loginUrl);
        $manage = $this->e($manageUrl);

        $offerHtml = '';
        foreach ($offers as $offer) {
            $name = $this->e((string) $offer['name']);
            $price = $this->e((string) $offer['price']);
            $copy = $this->e((string) $offer['copy']);
            $cta = $this->e((string) $offer['cta']);
            $dark = strcasecmp((string) $offer['name'], 'Amplify') === 0;
            $bg = $dark ? '#000000' : '#ffffff';
            $text = $dark ? '#ffffff' : '#1e1e1e';
            $border = $dark ? '#000000' : '#dbdbdb';
            $buttonBg = $dark ? '#ff6347' : '#000000';
            $buttonText = $dark ? '#1e1e1e' : '#ffffff';

            $offerHtml .= '<tr><td style="padding:0 24px 16px">'
                . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:'.$bg.';border:2px solid '.$border.';border-radius:10px">'
                . '<tr><td style="padding:18px;color:'.$text.'">'
                . '<div style="font-size:25px;font-weight:900;line-height:1.2">'.$name.'</div>'
                . '<div style="margin-top:8px;font-size:38px;font-weight:900;line-height:1;color:#ff6347">'.$price.'</div>'
                . '<div style="margin-top:12px;font-size:15px;line-height:1.55;color:'.($dark ? '#ffffff' : '#4a4741').'">'.$copy.'</div>'
                . '<div style="margin-top:16px"><a href="'.$manage.'" style="display:inline-block;background:'.$buttonBg.';color:'.$buttonText.';text-decoration:none;font-weight:900;padding:11px 18px;border-radius:8px">'.$cta.'</a></div>'
                . '</td></tr></table></td></tr>';
        }

        $next = $hasAmplify
            ? '<tr><td style="padding:8px 24px 22px"><div style="border:2px solid #000;border-radius:10px;background:#000;color:#fff;padding:20px"><div style="color:#ff6347;font-size:14px;font-weight:900;letter-spacing:.04em">YOU ARE FULLY AMPLIFIED</div><div style="margin-top:8px;font-size:24px;font-weight:900">Your highest service extension is already active.</div><p style="margin:10px 0 0;line-height:1.55">Keep your film, stats, schedule, and contact information current so every coach visit lands on your strongest PLYRCARD.</p></div></td></tr>'
            : '<tr><td style="padding:20px 24px 10px"><div style="color:#ff6347;font-size:14px;font-weight:900;letter-spacing:.04em">WHAT\'S NEXT</div><div style="margin-top:8px;font-size:30px;font-weight:900;line-height:1.15">A site coaches never see doesn\'t do much.</div><p style="margin:10px 0 0;color:#4a4741;font-size:15px;font-weight:700;line-height:1.55">The profile is step one. Step two is getting it in front of the right programs, with video and graphics that make them stop scrolling. Choose the level that fits right now.</p></td></tr>'.$offerHtml;

        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f0f1f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e">'
            . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f0f1f5"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:600px;background:#fff">'
            . '<tr><td style="padding:24px"><a href="https://plyrcard.com" style="text-decoration:none;font-size:22px;font-weight:900;color:#000">PLYR<span style="color:#ff6347">CARD</span></a></td></tr>'
            . '<tr><td style="padding:0 24px"><div style="background:#ff6347;border-radius:10px;padding:34px 22px;text-align:center"><div style="font-size:14px;font-weight:900;letter-spacing:.08em">YOUR PLYRSITE IS LIVE</div><div style="margin-top:8px;font-size:34px;line-height:1.05;font-weight:900">YOU MADE IT OFFICIAL</div></div></td></tr>'
            . '<tr><td style="padding:20px 24px 10px"><p style="margin:0;font-size:16px;font-weight:700">Hi '.$first.',</p><p style="margin:12px 0 0;color:#4a4741;font-size:16px;font-weight:700;line-height:1.55">Your PLYRCARD profile is published and live. Send it to coaches, put it in your bio, and add it to your email signature. This link is yours.</p></td></tr>'
            . '<tr><td style="padding:8px 24px 22px"><table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td style="width:48%;vertical-align:top;padding-right:8px"><div style="font-size:25px;font-weight:900">YOUR SITE IS LIVE</div><p style="color:#4a4741;line-height:1.55">Bookmark your account. Stats, video, and contact information can be updated anytime.</p></td><td style="width:52%;vertical-align:top;padding-left:8px"><a href="'.$site.'" style="display:block;background:#ff6347;color:#1e1e1e;text-decoration:none;text-align:center;font-weight:900;padding:12px;border-radius:7px;margin-bottom:10px">VIEW MY SITE</a><a href="'.$login.'" style="display:block;background:#000;color:#fff;text-decoration:none;text-align:center;font-weight:900;padding:12px;border-radius:7px">LOG IN &amp; EDIT</a></td></tr></table></td></tr>'
            . $next
            . '<tr><td style="padding:16px 24px 24px"><p style="margin:0;color:#4a4741;font-size:15px;font-weight:700;line-height:1.55">Not sure which one fits? Reply with your grad year, position, and the level you\'re chasing.</p><p style="margin:16px 0 0;font-weight:700">This is your journey. It has to come from you. <span style="color:#ff6347">Authenticity is Key.</span></p></td></tr>'
            . '<tr><td style="background:#000;padding:20px;text-align:center;color:#fff;font-size:18px;font-weight:900">PLYR<span style="color:#ff6347">CARD</span></td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function money(int $cents): string
    {
        $amount = $cents / 100;
        return '$' . (floor($amount) === $amount ? number_format($amount, 0) : number_format($amount, 2));
    }

    protected function siteUrl(Website $website): string
    {
        if (filled($website->domain)) { $d=preg_replace('#^https?://#i','',trim((string)$website->domain)); return 'https://'.trim((string)$d,'/'); }
        return filled($website->slug) ? rtrim((string)config('app.url','https://plyrcard.com'),'/').'/'.ltrim((string)$website->slug,'/') : rtrim((string)config('app.url','https://plyrcard.com'),'/');
    }
    protected function hasRole(User $user,string $role): bool { try { return method_exists($user,'hasRole') && $user->hasRole($role); } catch (\Throwable) { return false; } }
    protected function e(string $s): string { return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    protected function email(?string $e): ?string { $e=strtolower(trim(str_replace(["\\r","\\n"],'',(string)$e))); return filter_var($e,FILTER_VALIDATE_EMAIL)?$e:null; }
    protected function sendHtml(User $user, string $to, string $subject, string $html, string $purpose): array
    {
        $from = $this->email(PlyrcardMailSender::email());
        if (! $from || ! function_exists('mail')) {
            return ['success' => false, 'error' => 'Mail transport unavailable.'];
        }

        // Keep the launch email self-contained in this service and send one HTML
        // MIME part. This avoids exposing raw multipart boundaries/HTML in Gmail
        // on shared-host mail transports.
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . PlyrcardMailSender::name() . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        try {
            $sent = @mail($to, $subject, $html, implode("\r\n", $headers), '-f' . $from);
        } catch (\Throwable $exception) {
            $sent = false;
            Log::error('Website published email failed.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        if (! $sent) {
            Log::warning('Website published email was not accepted by mail transport.', [
                'user_id' => $user->getKey(),
                'recipient' => $to,
            ]);
        }

        return ['success' => $sent, 'recipient' => $to, 'purpose' => $purpose];
    }
}