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
        $jumpstartCents = (int) config('plyrcard-jumpstart.price_cents', 14900);
        $amplifyEnrollmentCents = $amplifySetupCents + ($amplifyFirstMonth ? $journeyCents : 0);
        $manageUrl = rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . '/admin/my-journey';
        $loginUrl = rtrim((string) config('app.url', 'https://plyrcard.com'), '/') . '/admin/login';

        $offers = [];
        if (! $hasJourney && ! $hasAmplify) {
            $offers[] = ['name'=>'My Journey','price'=>$this->money($journeyCents) . '/mo','copy'=>'Your recruiting HQ: custom domain, coach database, outreach tools, and engagement tracking.','cta'=>'Start My Journey'];
        }
        if (! $hasJumpstart && ! $hasAmplify) {
            $offers[] = ['name'=>'Jumpstart','price'=>$this->money($jumpstartCents) . ' one time','copy'=>'One coach outreach campaign, one highlight edit, and one custom graphic. No subscription required.','cta'=>'Get Jumpstart'];
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
        $first=$this->e((string)($user->first_name ?: 'Player')); $site=$this->e($siteUrl); $login=$this->e($loginUrl); $manage=$this->e($manageUrl);
        $offerHtml='';
        foreach ($offers as $offer) {
            $offerHtml .= '<tr><td style="padding:0 0 12px"><table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#15181d;border:1px solid #272d35;border-radius:14px"><tr><td style="padding:20px">'
                . '<div style="font-size:11px;letter-spacing:1.7px;text-transform:uppercase;color:#FF6347;font-weight:800">' . $this->e($offer['name']) . '</div>'
                . '<div style="margin-top:6px;font-size:25px;line-height:1.1;font-weight:900;color:#fff">' . $this->e($offer['price']) . '</div>'
                . '<div style="margin-top:9px;font-size:14px;line-height:1.6;color:#aab0b8">' . $this->e($offer['copy']) . '</div>'
                . '<div style="margin-top:16px"><a href="'.$manage.'" style="display:inline-block;background:#FF6347;color:#111;text-decoration:none;font-size:13px;font-weight:900;padding:11px 16px;border-radius:9px">'.$this->e($offer['cta']).'</a></div>'
                . '</td></tr></table></td></tr>';
        }
        $next = $hasAmplify
            ? '<div style="margin-top:26px;padding:20px;border:1px solid #244832;background:#10251a;border-radius:14px"><div style="font-size:11px;text-transform:uppercase;letter-spacing:1.6px;color:#58d78f;font-weight:800">YOU ARE FULLY AMPLIFIED</div><div style="margin-top:8px;color:#e9fff2;font-size:15px;line-height:1.6">Your highest done-for-you package is already active. Keep your film, stats, contact information, and schedule current so every coach visit lands on your strongest PLYRCARD.</div></div>'
            : '<div style="margin:28px 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:1.8px;color:#FF6347;font-weight:800">WHAT\'S NEXT</div><div style="font-size:27px;font-weight:900;line-height:1.15;color:#fff">A live site is step one. Getting it seen is step two.</div><div style="margin:10px 0 18px;color:#aab0b8;font-size:14px;line-height:1.65">Choose the level of recruiting help that fits right now. Your profile stays intact as you add services.</div><table width="100%" cellpadding="0" cellspacing="0" role="presentation">'.$offerHtml.'</table>';

        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#08090b;font-family:Arial,Helvetica,sans-serif;color:#fff">'
            . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#08090b"><tr><td align="center" style="padding:28px 12px"><table width="600" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:600px">'
            . '<tr><td style="padding:0 20px 18px;font-size:21px;font-weight:900">PLYR<span style="color:#FF6347">CARD</span></td></tr>'
            . '<tr><td style="background:#101216;border:1px solid #242930;border-radius:18px;padding:34px 28px">'
            . '<div style="font-size:11px;letter-spacing:1.9px;text-transform:uppercase;color:#58d78f;font-weight:900">YOUR SITE IS LIVE</div>'
            . '<h1 style="margin:10px 0 0;font-size:36px;line-height:1.05;color:#fff">'.$first.', your PLYRCARD is published.</h1>'
            . '<p style="margin:14px 0 0;color:#aab0b8;font-size:15px;line-height:1.7">Send it to coaches, put it in your bio, and add it to your email signature. This link is yours.</p>'
            . '<div style="margin-top:24px;padding:18px;background:#08090b;border:1px solid #272d35;border-radius:12px;word-break:break-all"><div style="font-size:10px;letter-spacing:1.6px;text-transform:uppercase;color:#737b86;font-weight:800">Your live site</div><div style="margin-top:7px;font-size:17px;color:#fff;font-weight:800">'.$site.'</div></div>'
            . '<div style="margin-top:20px"><a href="'.$site.'" style="display:inline-block;background:#FF6347;color:#111;text-decoration:none;font-weight:900;padding:14px 20px;border-radius:10px;margin-right:8px">View My Site</a><a href="'.$login.'" style="display:inline-block;background:#fff;color:#111;text-decoration:none;font-weight:900;padding:14px 20px;border-radius:10px">Log In &amp; Edit</a></div>'
            . $next
            . '<div style="margin-top:28px;padding-top:20px;border-top:1px solid #272d35;color:#7e8792;font-size:13px;line-height:1.6">This is your journey. It has to come from you.<br><strong style="color:#FF6347">Authenticity is Key.</strong></div>'
            . '</td></tr><tr><td align="center" style="padding:20px;color:#59616b;font-size:11px">&copy; 2026 PLYRCARD</td></tr></table></td></tr></table></body></html>';
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
    protected function sendHtml(User $user,string $to,string $subject,string $html,string $purpose): array
    {
        $from=$this->email(PlyrcardMailSender::email()); if(!$from||!function_exists('mail')) return ['success'=>false,'error'=>'Mail transport unavailable.'];
        $boundary='plyrcard_'.bin2hex(random_bytes(12)); $plain=trim(html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $headers=['MIME-Version: 1.0','From: '.PlyrcardMailSender::name().' <'.$from.'>','Reply-To: '.$from,'Content-Type: multipart/alternative; boundary="'.$boundary.'"'];
        $message='--'.$boundary."\\r\\nContent-Type: text/plain; charset=UTF-8\\r\\n\\r\\n".$plain."\\r\\n--".$boundary."\\r\\nContent-Type: text/html; charset=UTF-8\\r\\n\\r\\n".$html."\\r\\n--".$boundary.'--';
        try { $sent=@mail($to,$subject,$message,implode("\\r\\n",$headers),'-f'.$from); } catch (\Throwable $e) { $sent=false; Log::error('Website published email failed.',['user_id'=>$user->getKey(),'error'=>$e->getMessage()]); }
        if(!$sent) Log::warning('Website published email was not accepted by mail transport.',['user_id'=>$user->getKey(),'recipient'=>$to]);
        return ['success'=>$sent,'recipient'=>$to,'purpose'=>$purpose];
    }
}
