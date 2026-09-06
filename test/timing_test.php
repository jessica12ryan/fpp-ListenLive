#!/usr/bin/env php
<?php
/**
 * Host tests for LLSyncTiming — no FPP tree, no fppd, no network.
 * Mirrors PulseMesh test/mirror_test.cpp discipline: dependency-free,
 * deterministic, runs on any PHP 7+ box.
 *
 *   php test/timing_test.php
 */

require_once __DIR__ . '/../src/Timing.php';

$failures = 0;
$case = '';

function check(bool $ok, string $what, int $line): void {
    global $failures, $case;
    if (!$ok) {
        echo "  FAIL  $case: $what (line $line)\n";
        $failures++;
    }
}
function checkEq($got, $want, string $what, int $line): void {
    global $failures, $case;
    if ($got !== $want) {
        $g = var_export($got, true); $w = var_export($want, true);
        echo "  FAIL  $case: $what — got $g want $w (line $line)\n";
        $failures++;
    }
}
function checkFloat(float $got, float $want, string $what, int $line, float $eps = 1e-6): void {
    global $failures, $case;
    if (abs($got - $want) > $eps) {
        echo "  FAIL  $case: $what — got $got want $want (line $line)\n";
        $failures++;
    }
}
function CASE_(string $name): void { global $case; $case = $name; }

CASE_('formatTime 0..3723');
checkEq(LLSyncTiming::formatTime(0), '00:00', '0', __LINE__);
checkEq(LLSyncTiming::formatTime(5), '00:05', '5', __LINE__);
checkEq(LLSyncTiming::formatTime(60), '01:00', '60', __LINE__);
checkEq(LLSyncTiming::formatTime(61), '01:01', '61', __LINE__);
checkEq(LLSyncTiming::formatTime(600), '10:00', '600', __LINE__);
checkEq(LLSyncTiming::formatTime(-3), '00:00', 'neg clamp', __LINE__);

CASE_('seekPosition latency 1.3');
checkFloat(LLSyncTiming::seekPosition(0), 0.0, 'zero', __LINE__);
checkFloat(LLSyncTiming::seekPosition(0.5), 0.0, 'below latency', __LINE__);
checkFloat(LLSyncTiming::seekPosition(1.3), 0.0, 'at latency', __LINE__);
checkFloat(LLSyncTiming::seekPosition(10), 8.7, '10-1.3', __LINE__);
checkFloat(LLSyncTiming::seekPosition(10, 0.5), 9.5, 'seamless 0.5', __LINE__);
checkFloat(LLSyncTiming::seekPosition(100, 1.3), 98.7, 'large', __LINE__);

CASE_('byteOffset');
checkEq(LLSyncTiming::byteOffset(10), (int)(8.7*16000), '10s', __LINE__);
checkEq(LLSyncTiming::byteOffset(0.5), 0, 'clamped', __LINE__);
checkEq(LLSyncTiming::byteOffset(10, 0.5), (int)(9.5*16000), 'seamless', __LINE__);

CASE_('remaining');
checkFloat(LLSyncTiming::remaining(0, 5), 0.0, 'unknown duration', __LINE__);
checkFloat(LLSyncTiming::remaining(100, 30), 70.0, 'mid', __LINE__);
checkFloat(LLSyncTiming::remaining(100, 100), 0.0, 'at end', __LINE__);
checkFloat(LLSyncTiming::remaining(100, 120), 0.0, 'over', __LINE__);

CASE_('shouldSend half-second dedup — mirrors FPPPulseMesh.cpp:131');
check(!LLSyncTiming::shouldSend(1.0, 1.2), 'same half 1.0 vs 1.2', __LINE__);
check(!LLSyncTiming::shouldSend(1.0, 1.49), 'same half 1.49', __LINE__);
check(LLSyncTiming::shouldSend(1.0, 1.5), 'cross 1.0->1.5', __LINE__);
check(LLSyncTiming::shouldSend(0.0, 0.5), '0->0.5', __LINE__);
check(!LLSyncTiming::shouldSend(2.25, 2.49), 'same 2.x half', __LINE__);

CASE_('elapsedFromStatus priority bg > fpp > fallback');
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>10], ['trackElapsed'=>30], ['elapsed'=>5]), 30.0, 'bg wins', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>10], null, ['elapsed'=>5]), 10.0, 'fpp fallback', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(null, null, ['elapsed'=>7]), 7.0, 'fallback only', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(null, null, null), 0.0, 'zero', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>0], ['elapsed'=>12], null), 12.0, 'bg elapsed alt key', __LINE__);

CASE_('durationFromStatus');
checkFloat(LLSyncTiming::durationFromStatus(null, ['trackDuration'=>180], null), 180.0, 'bg duration', __LINE__);
checkFloat(LLSyncTiming::durationFromStatus(['seconds_elapsed'=>10,'seconds_remaining'=>90], null, null), 100.0, 'fpp sum', __LINE__);
checkFloat(LLSyncTiming::durationFromStatus(null, null, ['bgStatus'=>['trackDuration'=>200]]), 200.0, 'fallback bgStatus', __LINE__);
checkFloat(LLSyncTiming::durationFromStatus(null, null, null), 0.0, 'unknown', __LINE__);

CASE_('confidence inside settle is unresolved');
checkEq(LLSyncTiming::confidence(1000, 900, true), 'unresolved', '100ms age', __LINE__);
checkEq(LLSyncTiming::confidence(1000, 750, true), 'exact', '250ms age exact', __LINE__);
checkEq(LLSyncTiming::confidence(1000, 700, true), 'exact', '300ms', __LINE__);
checkEq(LLSyncTiming::confidence(1000, null, true), 'exact', 'no announcedAt', __LINE__);
checkEq(LLSyncTiming::confidence(1000, 900, false), 'exact', 'empty', __LINE__);

CASE_('ffmpegSeekArgs');
$a = LLSyncTiming::ffmpegSeekArgs(10, 100);
checkFloat($a['seekPos'], 8.7, 'seek', __LINE__);
checkFloat($a['remaining'], 90.0, 'remaining', __LINE__);
check($a['durationArg'] !== '', 'has -t', __LINE__);
$b = LLSyncTiming::ffmpegSeekArgs(0.5, 0);
checkFloat($b['seekPos'], 0.0, 'seek clamped', __LINE__);
checkFloat($b['remaining'], 0.0, 'no duration', __LINE__);
checkEq($b['durationArg'], '', 'no -t when unknown', __LINE__);
$c = LLSyncTiming::ffmpegSeekArgs(10, 100, true);
checkFloat($c['seekPos'], 9.5, 'seamless seek', __LINE__);

if ($failures === 0) {
    echo "timing_test: all checks passed\n";
    exit(0);
} else {
    echo "timing_test: $failures failure(s)\n";
    exit(1);
}
