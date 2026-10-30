<?php

declare(strict_types=1);

use App\Models\HeroTemplate;
use App\Models\Website;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$shouldRun = in_array('--run', $argv, true);

echo PHP_EOL;
echo "PLYRCARD Free Hero Migration" . PHP_EOL;
echo "============================" . PHP_EOL;
echo $shouldRun
    ? "MODE: LIVE UPDATE" . PHP_EOL
    : "MODE: DRY RUN (no database changes)" . PHP_EOL;
echo PHP_EOL;

$oldHero = HeroTemplate::query()
    ->where(function ($query): void {
        $query
            ->whereIn('name', ['Hero Free', 'Hero Template Free'])
            ->orWhere('blade_view', 'hero.hero_template_free');
    })
    ->orderByRaw("CASE WHEN name = 'Hero Free' THEN 0 WHEN name = 'Hero Template Free' THEN 1 ELSE 2 END")
    ->first();

$newHero = HeroTemplate::query()
    ->where(function ($query): void {
        $query
            ->where('name', 'Hero Soccer Two')
            ->orWhere('blade_view', 'hero.hero_soccer_two');
    })
    ->orderByRaw("CASE WHEN name = 'Hero Soccer Two' THEN 0 ELSE 1 END")
    ->first();

if (! $oldHero) {
    fwrite(STDERR, "ERROR: Could not find Hero Free / Hero Template Free." . PHP_EOL);
    exit(1);
}

if (! $newHero) {
    fwrite(STDERR, "ERROR: Could not find Hero Soccer Two / hero.hero_soccer_two." . PHP_EOL);
    exit(1);
}

if ((int) $oldHero->getKey() === (int) $newHero->getKey()) {
    fwrite(STDERR, "ERROR: Source and destination hero templates are the same record." . PHP_EOL);
    exit(1);
}

echo "Source hero: {$oldHero->name} (#{$oldHero->id})" . PHP_EOL;
echo "Target hero: {$newHero->name} (#{$newHero->id})" . PHP_EOL;

$oldFields = $oldHero->fields()->get()->keyBy('name');
$newFields = $newHero->fields()->get()->keyBy('name');
$matchingFieldNames = $oldFields->keys()->intersect($newFields->keys())->values();

echo "Matching hero fields: {$matchingFieldNames->count()}" . PHP_EOL . PHP_EOL;

$query = Website::query()
    ->where('hero_template_id', $oldHero->id)
    ->whereHas('user', function ($userQuery): void {
        $userQuery
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'Free'))
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', [
                'My Journey',
                'Jumpstart',
                'Amplify',
                'PLYR+',
            ]))
            ->where(function ($billingScope): void {
                $billingScope
                    ->whereDoesntHave('billingInformation')
                    ->orWhereHas('billingInformation', fn ($billing) => $billing->where('plan_key', 'free'));
            });
    });

$count = (clone $query)->count();

echo "Eligible Free-tier websites using {$oldHero->name}: {$count}" . PHP_EOL . PHP_EOL;

if ($count === 0) {
    echo "Nothing to update." . PHP_EOL;
    exit(0);
}

echo "Affected players:" . PHP_EOL;

(clone $query)
    ->with('user:id,first_name,last_name,email')
    ->orderBy('id')
    ->get()
    ->each(function (Website $website): void {
        $user = $website->user;
        $name = trim((string) (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));
        $label = $name !== '' ? $name : (string) ($user?->email ?? 'Unknown player');

        echo "  Website #{$website->id} - {$label}" . PHP_EOL;
    });

if (! $shouldRun) {
    echo PHP_EOL;
    echo "DRY RUN COMPLETE - no changes made." . PHP_EOL;
    echo "Run this to apply the migration:" . PHP_EOL;
    echo "  php scripts/convert-free-hero-to-soccer-two.php --run" . PHP_EOL;
    exit(0);
}

echo PHP_EOL . "Applying migration..." . PHP_EOL;

$updated = 0;
$migratedValues = 0;
$deletedOldValues = 0;

DB::transaction(function () use (
    $query,
    $oldFields,
    $newFields,
    $matchingFieldNames,
    $newHero,
    &$updated,
    &$migratedValues,
    &$deletedOldValues
): void {
    $query
        ->with([
            'user:id,first_name,last_name,email',
            'heroFieldValues.templateField',
        ])
        ->orderBy('id')
        ->chunkById(100, function ($websites) use (
            $oldFields,
            $newFields,
            $matchingFieldNames,
            $newHero,
            &$updated,
            &$migratedValues,
            &$deletedOldValues
        ): void {
            foreach ($websites as $website) {
                foreach ($matchingFieldNames as $fieldName) {
                    $oldField = $oldFields->get($fieldName);
                    $newField = $newFields->get($fieldName);

                    if (! $oldField || ! $newField) {
                        continue;
                    }

                    $oldValue = $website->heroFieldValues
                        ->firstWhere('hero_template_field_id', $oldField->id);

                    if (! $oldValue) {
                        continue;
                    }

                    $website->heroFieldValues()->updateOrCreate(
                        ['hero_template_field_id' => $newField->id],
                        [
                            'value' => $oldValue->value,
                            'meta' => $oldValue->meta,
                        ],
                    );

                    $migratedValues++;
                }

                $deletedOldValues += $website->heroFieldValues()
                    ->whereIn('hero_template_field_id', $oldFields->pluck('id')->all())
                    ->delete();

                $website->forceFill([
                    'hero_template_id' => $newHero->id,
                ])->saveQuietly();

                $updated++;

                $user = $website->user;
                $name = trim((string) (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));
                $label = $name !== '' ? $name : (string) ($user?->email ?? 'Unknown player');

                echo "  UPDATED Website #{$website->id} - {$label}" . PHP_EOL;
            }
        });
});

echo PHP_EOL;
echo "DONE" . PHP_EOL;
echo "Websites converted: {$updated}" . PHP_EOL;
echo "Hero values migrated: {$migratedValues}" . PHP_EOL;
echo "Old Hero Free values removed: {$deletedOldValues}" . PHP_EOL;
echo "New hero: {$newHero->name}" . PHP_EOL . PHP_EOL;
