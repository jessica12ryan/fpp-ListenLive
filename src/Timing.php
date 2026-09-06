<?php
/**
 * #############################################################
 * ## Listen Live — Timing helper (host-testable)            ##
 * ## Mirrors PulseMesh PendingInsertMirror discipline:       ##
 * ## dependency-free, clock-injected, monotonic vs wall     ##
 * #############################################################
 *
 * No FPP, no filesystem, no curl. All inputs are parameters.
 * Deadline evaluation happens at read time (view), not on a timer.
 */

class LLSyncTiming
{
    // PulseMesh-equivalent windows — generous multiples of tick/poll
    const IDLE_SETTLE_MS = 250;          // cf PendingInsertMirror idleSettleMs
    const IMMEDIATE_TIMEOUT_MS = 2000;   // cf immediateStartTimeoutMs
    const SEEK_LATENCY_S = 1.3;          // ffmpeg startup + network, api.php:1117
    const SEEK_LATENCY_SEAMLESS_S = 0.5; // next-track resume api.php:1144
    const BYTES_PER_SEC_128K = 16000;    // 128k mp3 byte-offset fallback api.php:1171

    /**
     * Format seconds as MM:SS — identical to listen.php:formatTime
     */
    public static function formatTime(int $sec): string
    {
        $sec = max(0, $sec);
        $m = intdiv($sec, 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d', $m, $s);
    }

    /**
     * Where to seek when (re)starting a file-sync stream so the listener
     * is fractionally *behind* FPP, never ahead. Never negative.
     */
    public static function seekPosition(float $elapsed, float $latency = self::SEEK_LATENCY_S): float
    {
        $v = $elapsed - $latency;
        return $v > 0 ? $v : 0.0;
    }

    /**
     * Byte offset estimate for raw mp3 byte-seek fallback (128k).
     */
    public static function byteOffset(float $elapsed, float $latency = self::SEEK_LATENCY_S, int $bytesPerSec = self::BYTES_PER_SEC_128K): int
    {
        return (int)(self::seekPosition($elapsed, $latency) * $bytesPerSec);
    }

    /**
     * Remaining duration, clamped to >=0. 0 means unknown/live.
     */
    public static function remaining(float $duration, float $elapsed): float
    {
        if ($duration <= 0) return 0.0;
        $r = $duration - $elapsed;
        return $r > 0 ? $r : 0.0;
    }

    /**
     * Half-second deduplication — mirrors PulseMesh SendMediaSyncPacket's
     * curTS = (int)(seconds*2). Returns true if should SEND (changed half-sec).
     */
    public static function shouldSend(float $prevSeconds, float $curSeconds): bool
    {
        return (int)($prevSeconds * 2.0) !== (int)($curSeconds * 2.0);
    }

    /**
     * Extract the authoritative elapsed in seconds from the three status
     * shapes. Pure: caller passes the already-decoded arrays/nulls.
     *
     * Priority: BackgroundMusic trackElapsed > fpp_status seconds_elapsed > fallback elapsed.
     * Returns 0 when nothing is playing.
     */
    public static function elapsedFromStatus(?array $fppStatus, ?array $bgStatus, ?array $fallback): float
    {
        if ($bgStatus !== null) {
            foreach (['trackElapsed', 'elapsed', 'position', 'currentTime'] as $k) {
                if (isset($bgStatus[$k]) && is_numeric($bgStatus[$k])) return (float)$bgStatus[$k];
                if (isset($bgStatus['data'][$k]) && is_numeric($bgStatus['data'][$k])) return (float)$bgStatus['data'][$k];
            }
            if (isset($bgStatus['trackElapsed'])) return (float)$bgStatus['trackElapsed'];
        }
        if ($fppStatus !== null && isset($fppStatus['seconds_elapsed']) && is_numeric($fppStatus['seconds_elapsed'])) {
            return (float)$fppStatus['seconds_elapsed'];
        }
        if ($fallback !== null && isset($fallback['elapsed']) && is_numeric($fallback['elapsed'])) {
            return (float)$fallback['elapsed'];
        }
        return 0.0;
    }

    public static function durationFromStatus(?array $fppStatus, ?array $bgStatus, ?array $fallback): float
    {
        // bgStatus trackDuration is most precise
        if ($bgStatus !== null) {
            foreach (['trackDuration', 'duration'] as $k) {
                if (isset($bgStatus[$k]) && is_numeric($bgStatus[$k]) && (float)$bgStatus[$k] > 0) return (float)$bgStatus[$k];
                if (isset($bgStatus['data'][$k]) && is_numeric($bgStatus['data'][$k]) && (float)$bgStatus['data'][$k] > 0) return (float)$bgStatus['data'][$k];
            }
        }
        if ($fallback !== null) {
            if (isset($fallback['bgStatus']['trackDuration']) && is_numeric($fallback['bgStatus']['trackDuration'])) return (float)$fallback['bgStatus']['trackDuration'];
            if (isset($fallback['bgStatus']['duration']) && is_numeric($fallback['bgStatus']['duration'])) return (float)$fallback['bgStatus']['duration'];
        }
        if ($fppStatus !== null && isset($fppStatus['seconds_elapsed'], $fppStatus['seconds_remaining'])
            && is_numeric($fppStatus['seconds_elapsed']) && is_numeric($fppStatus['seconds_remaining'])) {
            return (float)$fppStatus['seconds_elapsed'] + (float)$fppStatus['seconds_remaining'];
        }
        return 0.0;
    }

    /**
     * Confidence at read time. PulseMesh rule: still deciding => unresolved.
     * For file-sync the window is IDLE_SETTLE_MS after a track change.
     * Caller passes nowMs (monotonic) and announcedAtMs (monotonic of last media change).
     */
    public static function confidence(float $nowMs, ?float $announcedAtMs, bool $hasMedia): string
    {
        if (!$hasMedia) return 'exact'; // empty is exact (nothing pending)
        if ($announcedAtMs === null) return 'exact';
        $age = $nowMs - $announcedAtMs;
        if ($age < self::IDLE_SETTLE_MS) return 'unresolved';
        return 'exact';
    }

    // Clock sources — same split as PendingInsertMirror: monotonic for deadlines,
    // wall for epoch. Pure wrappers so tests can inject values instead of calling.

    public static function monotonicMs(): int
    {
        // hrtime(true) is monotonic ns since arbitrary point (CLOCK_MONOTONIC)
        if (function_exists('hrtime')) {
            return (int)(hrtime(true) / 1e6);
        }
        return (int)(microtime(true) * 1000);
    }

    public static function wallClockMs(): int
    {
        return (int)(microtime(true) * 1000);
    }

    /**
     * Compute ffmpeg args for file-sync (pure, returns array for testability).
     */
    public static function ffmpegSeekArgs(float $elapsed, float $duration, bool $isSeamlessNext = false): array
    {
        $latency = $isSeamlessNext ? self::SEEK_LATENCY_SEAMLESS_S : self::SEEK_LATENCY_S;
        $seekPos = self::seekPosition($elapsed, $latency);
        $remaining = self::remaining($duration, $elapsed);
        $durationArg = $remaining > 0 ? sprintf(' -t %.1f', $remaining + 0.5) : '';
        return [
            'seekPos' => $seekPos,
            'remaining' => $remaining,
            'durationArg' => $durationArg,
        ];
    }
}
