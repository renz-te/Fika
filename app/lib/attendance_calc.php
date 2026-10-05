<?php

class AttendanceCalc {
    
    /**
     * Calculate attendance metrics purely.
     * 
     * @param string $clockIn ISO 8601 or Y-m-d H:i:s
     * @param string|null $clockOut
     * @param string|null $scheduledStart
     * @param int $lateGraceMinutes
     * @return array
     */
    public static function calculate(string $clockIn, ?string $clockOut, ?string $scheduledStart, int $lateGraceMinutes): array {
        if (!$clockOut) {
            return [
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'night_diff_minutes' => 0,
            ];
        }

        // Assume inputs are UTC if no timezone is specified (like standard MySQL DATETIME)
        $in = new DateTime($clockIn, new DateTimeZone('UTC'));
        $out = new DateTime($clockOut, new DateTimeZone('UTC'));

        // Switch to configured timezone (e.g., Asia/Manila) for local time calculations like Night Diff
        $localTz = new DateTimeZone(date_default_timezone_get());
        $in->setTimezone($localTz);
        $out->setTimezone($localTz);

        if ($out <= $in) {
            // Invalid or 0 duration
            return [
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
                'night_diff_minutes' => 0,
            ];
        }

        // Total Duration in Minutes (Rounding: whole minutes, no rounding up -> floor)
        $diff = $out->getTimestamp() - $in->getTimestamp();
        $workedMinutes = (int) floor($diff / 60);

        $sched = null;
        if ($scheduledStart) {
            $sched = new DateTime($scheduledStart, new DateTimeZone('UTC'));
            $sched->setTimezone($localTz);
        }

        // Lateness
        $lateMinutes = 0;
        if ($sched) {
            $lateDiff = $in->getTimestamp() - $sched->getTimestamp();
            $lateMins = (int) floor($lateDiff / 60);
            if ($lateMins > $lateGraceMinutes) {
                // Beyond grace = late minutes counted from scheduled start
                $lateMinutes = $lateMins;
            }
        }

        // Overtime after 8h (480 mins)
        $overtimeMinutes = max(0, $workedMinutes - 480);

        // Night Differential (22:00 - 06:00)
        // Iterate minute by minute or chunk it. Minute by minute is fast enough for standard shifts (< 24h).
        $ndMinutes = 0;
        $currentTs = $in->getTimestamp();
        $outTs = $currentTs + ($workedMinutes * 60); // use exact floored duration
        
        while ($currentTs < $outTs) {
            $hour = (int) date('H', $currentTs);
            if ($hour >= 22 || $hour < 6) {
                $ndMinutes++;
            }
            $currentTs += 60;
        }

        return [
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'night_diff_minutes' => $ndMinutes,
        ];
    }
}
