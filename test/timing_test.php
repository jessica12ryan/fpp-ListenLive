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
checkEq(LLSyncTiming::formatTime(3723), '62:03', '3723 = 62m03s', __LINE__);
checkEq(LLSyncTiming::formatTime(-3), '00:00', 'neg clamp', __LINE__);

CASE_('seekPosition latency 1.3');
checkFloat(LLSyncTiming::seekPosition(0), 0.0, 'zero', __LINE__);
checkFloat(LLSyncTiming::seekPosition(0.5), 0.0, 'below latency', __LINE__);
checkFloat(LLSyncTiming::seekPosition(1.3), 0.0, 'at latency', __LINE__);
checkFloat(LLSyncTiming::seekPosition(10), 8.7, '10-1.3', __LINE__);
checkFloat(LLSyncTiming::seekPosition(10, 0.5), 9.5, 'seamless 0.5', __LINE__);
checkFloat(LLSyncTiming::seekPosition(100, 1.3), 98.7, 'large', __LINE__);

CASE_('byteOffset — literal bytes, not a self-referential formula');
// seekPosition(10) = 8.7s; 8.7 * 16000 B/s = 139200 (128k mp3).
checkEq(LLSyncTiming::byteOffset(10), 139200, '10s = 8.7s * 16000', __LINE__);
checkEq(LLSyncTiming::byteOffset(0.5), 0, 'clamped', __LINE__);
// seekPosition(10, 0.5) = 9.5s; 9.5 * 16000 = 152000.
checkEq(LLSyncTiming::byteOffset(10, 0.5), 152000, 'seamless = 9.5s * 16000', __LINE__);
// A custom bytes-per-second must scale the same literal offset.
checkEq(LLSyncTiming::byteOffset(10, 1.3, 8000), 69600, '8k bps half rate', __LINE__);

CASE_('remaining');
checkFloat(LLSyncTiming::remaining(0, 5), 0.0, 'unknown duration', __LINE__);
checkFloat(LLSyncTiming::remaining(100, 30), 70.0, 'mid', __LINE__);
checkFloat(LLSyncTiming::remaining(100, 100), 0.0, 'at end', __LINE__);
checkFloat(LLSyncTiming::remaining(100, 120), 0.0, 'over', __LINE__);

CASE_('shouldSend half-second dedup — mirrors FPPPulseMesh.cpp:131');
// Negatives: values inside the same half-second window must NOT be sent.
check(!LLSyncTiming::shouldSend(1.0, 1.2), 'same half 1.0 vs 1.2', __LINE__);
check(!LLSyncTiming::shouldSend(1.0, 1.49), 'same half 1.49', __LINE__);
check(!LLSyncTiming::shouldSend(1.0, 1.0), 'identical values', __LINE__);
check(!LLSyncTiming::shouldSend(2.25, 2.49), 'same 2.x half', __LINE__);
check(!LLSyncTiming::shouldSend(0.0, 0.49), 'same 0.x half', __LINE__);
check(!LLSyncTiming::shouldSend(3.5, 3.99), 'same 3.x upper half', __LINE__);
check(!LLSyncTiming::shouldSend(10.1, 10.0), 'regressive but same half', __LINE__);
// Positives: crossing a half-second boundary must send.
check(LLSyncTiming::shouldSend(1.0, 1.5), 'cross 1.0->1.5', __LINE__);
check(LLSyncTiming::shouldSend(0.0, 0.5), '0->0.5', __LINE__);
check(LLSyncTiming::shouldSend(1.49, 1.5), 'cross at boundary', __LINE__);

CASE_('elapsedFromStatus priority bg > fpp > fallback');
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>10], ['trackElapsed'=>30], ['elapsed'=>5]), 30.0, 'bg wins', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>10], null, ['elapsed'=>5]), 10.0, 'fpp fallback', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(null, null, ['elapsed'=>7]), 7.0, 'fallback only', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(null, null, null), 0.0, 'zero', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>0], ['elapsed'=>12], null), 12.0, 'bg elapsed alt key', __LINE__);
// A non-numeric bgStatus.trackElapsed must NOT cast to 0 and shadow fppd's
// seconds_elapsed (src/Timing.php:84 no longer does `return (int)...`).
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>42], ['trackElapsed'=>'--:--'], null), 42.0, 'non-numeric bgElapsed ignored', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>42], ['trackElapsed'=>'N/A'], ['elapsed'=>7]), 42.0, 'non-numeric bgElapsed falls through', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(['seconds_elapsed'=>42], ['trackElapsed'=>''], null), 42.0, 'empty bgElapsed ignored', __LINE__);
checkFloat(LLSyncTiming::elapsedFromStatus(null, ['trackElapsed'=>'--:--'], ['elapsed'=>7]), 7.0, 'non-numeric bgElapsed, fallback used', __LINE__);

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
checkEq($a['durationArg'], ' -t 91.8', '-t covers remaining + latency + pad', __LINE__);
$b = LLSyncTiming::ffmpegSeekArgs(0.5, 0);
checkFloat($b['seekPos'], 0.0, 'seek clamped', __LINE__);
checkFloat($b['remaining'], 0.0, 'no duration', __LINE__);
checkEq($b['durationArg'], '', 'no -t when unknown', __LINE__);
$c = LLSyncTiming::ffmpegSeekArgs(10, 100, true);
checkFloat($c['seekPos'], 9.5, 'seamless seek', __LINE__);
checkEq($c['durationArg'], ' -t 91.0', 'seamless -t covers remaining + 0.5 latency + pad', __LINE__);
// elapsed > duration (status overshoot): seek still positive, remaining 0, no -t.
$d = LLSyncTiming::ffmpegSeekArgs(120, 100);
checkFloat($d['seekPos'], 118.7, 'seek past duration', __LINE__);
checkFloat($d['remaining'], 0.0, 'no remaining past end', __LINE__);
checkEq($d['durationArg'], '', 'no -t past end', __LINE__);
$e = LLSyncTiming::ffmpegSeekArgs(100, 100, true);
checkFloat($e['seekPos'], 99.5, 'seamless seek at duration', __LINE__);
checkFloat($e['remaining'], 0.0, 'no remaining at end', __LINE__);
checkEq($e['durationArg'], '', 'no -t at end', __LINE__);

CASE_('masterAt — monotonic only, never wall clocks');
checkFloat(LLSyncTiming::masterAt(10.0, 1000.0, 1000.0), 10.0, 'no time passed', __LINE__);
checkFloat(LLSyncTiming::masterAt(10.0, 1000.0, 1250.0), 10.25, '250ms forward', __LINE__);
checkFloat(LLSyncTiming::masterAt(10.0, 1000.0, 900.0), 10.0, 'clock went backwards', __LINE__);
// A 1.2s wall-clock skew must never enter the master position.
checkFloat(LLSyncTiming::masterAt(10.0, 1000.0, 1100.0), 10.1, 'monotonic delta, no skew', __LINE__);

CASE_('planChunk — content time == master time at scheduled start');
$p = LLSyncTiming::planChunk(8.3849, 3.0, 0.5, 0.5);
checkFloat($p['seek'], 8.885, 'seek = master + latency', __LINE__);
checkFloat($p['when'], 3.5, 'when = ctx + latency', __LINE__);
// At the scheduled instant, master has advanced by the latency and equals seek.
// seek is rounded to ffmpeg's millisecond resolution => <0.5ms error.
checkFloat($p['seek'], 8.3849 + ($p['when'] - 3.0), 'zero drift at start', __LINE__, 0.001);
checkFloat($p['end'], 9.385, 'chunk end', __LINE__, 0.001);
$p2 = LLSyncTiming::planChunk(0.0, 0.0, 0.5, 0.25);
checkFloat($p2['seek'], 0.5, 'early track seek', __LINE__);

CASE_('drift sign and thresholds');
checkFloat(LLSyncTiming::drift(10.1, 10.0), 0.1, 'audio ahead positive', __LINE__);
checkFloat(LLSyncTiming::drift(9.9, 10.0), -0.1, 'audio behind negative', __LINE__);
check(!LLSyncTiming::shouldNudge(0.02), '20ms ignored', __LINE__);
check(LLSyncTiming::shouldNudge(0.031), '31ms nudged', __LINE__);
check(!LLSyncTiming::shouldResync(0.10), '100ms not resync', __LINE__);
check(LLSyncTiming::shouldResync(0.151), '151ms resync', __LINE__);
check(LLSyncTiming::shouldResync(-0.151), 'negative resync', __LINE__);

CASE_('pcmFrames');
checkEq(LLSyncTiming::pcmFrames(176400), 44100, '0.5s stereo 44.1k', __LINE__);
checkEq(LLSyncTiming::pcmFrames(176400, 1), 88200, 'mono', __LINE__);
checkEq(LLSyncTiming::pcmFrames(3), 0, 'short', __LINE__);
checkEq(LLSyncTiming::pcmFrames(8, 0), 0, 'zero channels', __LINE__);

if ($failures === 0) {
    echo "timing_test: all checks passed\n";
    exit(0);
} else {
    echo "timing_test: $failures failure(s)\n";
    exit(1);
}
