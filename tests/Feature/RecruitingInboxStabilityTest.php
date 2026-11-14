<?php

use App\Http\Middleware\PlyrLabProductionGate;
use App\Models\User;
use App\Models\Website;
use App\Services\GoHighLevelService;
use App\Services\LocalTemplateMergeValueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

test('locker room registers authenticated player photo management endpoints', function () {
    expect(Route::has('locker-room.photos.upload'))->toBeTrue();
    expect(Route::has('locker-room.photos.delete'))->toBeTrue();
    expect(Route::has('locker-room.photos.reorder'))->toBeTrue();
    expect(Route::has('locker-room.photos.replace'))->toBeTrue();
});

test('PLYRLab production gate does not interfere with dev or test', function () {
    $gate = new PlyrLabProductionGate();
    $next = fn (Request $request) => new Response('PLYRLab available', 200);

    foreach (['plyrcard.com', 'dev.plyrcard.com', 'test.plyrcard.com'] as $host) {
        $request = Request::create('https://'.$host.'/locker-room/plyr-lab', 'GET');
        $response = $gate->handle($request, $next);
        expect($response->getStatusCode())->toBe($host === 'plyrcard.com' ? 503 : 200);
    }
});

test('email parser extracts the nested v3 email IDs without losing older shapes', function () {
    $service = new GoHighLevelService();
    $method = new ReflectionMethod($service, 'conversationEmailMessageIds');

    $ids = $method->invoke($service, [
        'meta' => [
            'email' => [
                'messageIds' => ['legacy-1'],
                'email' => ['messageIds' => ['v3-1', 'v3-2']],
            ],
        ],
    ]);

    expect($ids)->toBe(['legacy-1', 'v3-1', 'v3-2']);
});

test('plain text email fallback does not expose tracking tokens or HTML', function () {
    $service = new GoHighLevelService();
    $method = new ReflectionMethod($service, 'trackedPlainTextFromHtml');
    $text = $method->invoke(
        $service,
        '<p>Hello coach!</p><p>See <a href="https://plyrcard.com/track/click/a-very-long-token">My Profile</a>.</p>'
    );

    expect($text)->toContain("Hello coach!")
        ->toContain('My Profile')
        ->not->toContain('/track/click/')
        ->not->toContain('<p>');
});

test('signature merge escapes user fields without double decoding email HTML', function () {
    $athlete = new User();
    $athlete->forceFill([
        'first_name' => 'A&B',
        'last_name' => 'Player',
        'email' => 'athlete@example.com',
    ]);
    $website = new Website();
    $website->slug = 'athlete-profile';
    $athlete->setRelation('activeWebsite', $website);

    $html = '<p>Hello {{AthleteFirstName}} &amp; team.</p><a href="{{ProfileLink}}">View Profile</a>';
    $result = app(LocalTemplateMergeValueService::class)->replace($html, $athlete);

    expect($result)->toContain('A&amp;B')
        ->toContain('&amp; team.')
        ->not->toContain('A&B & team.');
});
