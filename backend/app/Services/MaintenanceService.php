<?php

namespace App\Services;

use App\Models\MaintenanceLog;
use Carbon\Carbon;

/**
 * Daily maintenance window (Asia/Kuala_Lumpur). During the window login,
 * withdraw and transfer are disabled. Window defaults to 00:00–07:00 (active
 * again at 07:00). Admin can force maintenance via the maintenance_manual flag.
 */
class MaintenanceService
{
    public function __construct(protected SettingsService $settings) {}

    public function tz(): string
    {
        return config('app.timezone', 'Asia/Kuala_Lumpur');
    }

    public function isManual(): bool
    {
        return (bool) $this->settings->get('maintenance_manual', false);
    }

    /** Is the system currently in maintenance (scheduled window OR manual)? */
    public function isActive(?Carbon $now = null): bool
    {
        if ($this->isManual()) {
            return true;
        }
        return $this->inWindow($now);
    }

    public function inWindow(?Carbon $now = null): bool
    {
        $now   = ($now ?? Carbon::now($this->tz()))->copy()->setTimezone($this->tz());
        $start = $this->todayAt($this->settings->get('maintenance_start'), $now);
        $end   = $this->todayAt($this->settings->get('maintenance_end'), $now);

        // Window does not cross midnight in defaults (00:00–07:00)
        if ($start->lessThanOrEqualTo($end)) {
            return $now->betweenIncluded($start, $end->copy()->subSecond());
        }
        // Crosses midnight (e.g. 23:00–07:00)
        return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
    }

    /** Status payload for the frontend countdown page. */
    public function status(): array
    {
        $now    = Carbon::now($this->tz());
        $active = $this->isActive($now);
        $end    = $this->todayAt($this->settings->get('maintenance_end'), $now);
        if ($now->greaterThanOrEqualTo($end)) {
            $end->addDay();
        }
        $start = $this->todayAt($this->settings->get('maintenance_start'), $now);
        if ($now->greaterThanOrEqualTo($start)) {
            $start->addDay();
        }

        return [
            'active'          => $active,
            'manual'          => $this->isManual(),
            'timezone'        => $this->tz(),
            'window_start'    => $this->settings->get('maintenance_start'),
            'window_end'      => $this->settings->get('maintenance_end'),
            'server_time'     => $now->toIso8601String(),
            'ends_at'         => $active ? $end->toIso8601String() : null,
            'next_start_at'   => $active ? null : $start->toIso8601String(),
            'seconds_to_end'  => $active ? $now->diffInSeconds($end) : null,
        ];
    }

    /** Open or close the maintenance log to match the current window state. */
    public function sync(): void
    {
        $shouldBeActive = $this->isActive();
        $open = MaintenanceLog::where('active', true)->latest('started_at')->first();

        if ($shouldBeActive && ! $open) {
            MaintenanceLog::create([
                'started_at'   => now(),
                'active'       => true,
                'triggered_by' => $this->isManual() ? 'admin' : 'schedule',
            ]);
        } elseif (! $shouldBeActive && $open) {
            $open->update(['active' => false, 'ended_at' => now()]);
        }
    }

    protected function todayAt(string $hhmm, Carbon $ref): Carbon
    {
        [$h, $m] = array_pad(explode(':', $hhmm), 2, 0);
        return $ref->copy()->setTime((int) $h, (int) $m, 0);
    }
}
