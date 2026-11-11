<?php

namespace App\Services;

use App\Models\PlyrLabTrainer;
use App\Models\PlyrLabTrainerAvailability;
use App\Models\PlyrLabTrainerBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class PlyrLabAvailabilityService
{
    public const DAY_LABELS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function weeklySchedule(PlyrLabTrainer $trainer): array
    {
        $rows = collect();

        if (Schema::hasTable('plyr_lab_trainer_availabilities')) {
            $rows = PlyrLabTrainerAvailability::query()
                ->where('trainer_id', $trainer->getKey())
                ->get()
                ->keyBy('day_of_week');
        }

        return collect(self::DAY_LABELS)
            ->map(function (string $label, int $day) use ($rows): array {
                $row = $rows->get($day);

                // Preserve the pre-calendar behavior until a trainer saves a schedule:
                // every day is open by default rather than unexpectedly rejecting bookings.
                return [
                    'day' => $day,
                    'label' => $label,
                    'enabled' => $row ? (bool) $row->is_available : true,
                    'start' => $this->normalizeTime($row?->start_time, '00:00'),
                    'end' => $this->normalizeTime($row?->end_time, '23:59'),
                    'configured' => (bool) $row,
                ];
            })
            ->values()
            ->all();
    }

    public function hasConfiguredSchedule(PlyrLabTrainer $trainer): bool
    {
        return Schema::hasTable('plyr_lab_trainer_availabilities')
            && PlyrLabTrainerAvailability::query()
                ->where('trainer_id', $trainer->getKey())
                ->exists();
    }

    public function unavailableReason(PlyrLabTrainer $trainer, Carbon $requestedAt): ?string
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $start = $requestedAt->copy()->timezone($timezone);
        $duration = max(15, (int) ($trainer->booking_duration_minutes ?: 60));
        $end = $start->copy()->addMinutes($duration);

        if (Schema::hasTable('plyr_lab_trainer_blocks')) {
            $block = PlyrLabTrainerBlock::query()
                ->where('trainer_id', $trainer->getKey())
                ->where('starts_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->orderBy('starts_at')
                ->first();

            if ($block) {
                $reason = trim((string) ($block->reason ?? ''));
                return $reason !== ''
                    ? 'This trainer is unavailable at that time: ' . $reason
                    : 'This trainer has blocked that date or time.';
            }
        }

        if (! $this->hasConfiguredSchedule($trainer)) {
            return null;
        }

        $availability = PlyrLabTrainerAvailability::query()
            ->where('trainer_id', $trainer->getKey())
            ->where('day_of_week', $start->dayOfWeek)
            ->first();

        if (! $availability || ! $availability->is_available) {
            return 'This trainer is not available on ' . $start->format('l') . '.';
        }

        $dayStart = Carbon::parse(
            $start->format('Y-m-d') . ' ' . $this->normalizeTime($availability->start_time, '00:00'),
            $timezone
        );
        $dayEnd = Carbon::parse(
            $start->format('Y-m-d') . ' ' . $this->normalizeTime($availability->end_time, '23:59'),
            $timezone
        );

        if ($start->lt($dayStart) || $end->gt($dayEnd)) {
            return 'This trainer is available on ' . $start->format('l')
                . ' from ' . $dayStart->format('g:i A')
                . ' to ' . $dayEnd->format('g:i A') . '.';
        }

        return null;
    }

    public function normalizeTime(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^(\d{2}):(\d{2})/', $value, $matches)) {
            return $matches[1] . ':' . $matches[2];
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
