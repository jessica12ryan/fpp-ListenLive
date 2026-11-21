#!/usr/bin/env php
<?php
/**
 * Host runtime test for the api.php glue — no FPP tree, no fppd, no network.
 *
 * api.php normally relies on FPP's common.php (ReadSettingFromFile,
 * WriteSettingToFile, getSetting, ...) and on header()/json() ending the
 * request. Under plain CLI none of that exists. This harness supplies those
 * stubs, includes api.php, and asserts the security-sensitive surface:
 *   - only read-only endpoints are registered (no update/reinstall/uninstall/
 *     restart-fppd over HTTP),
 *   - settings defaults carry no allow_remote escape hatch,
 *   - saving with enabled=0 makes the stream endpoint answer 503.
 *
 * Run:  php test/glue_runtime_test.php
 *
 * Two harness tricks make this work under plain CLI:
 *  1. A namespaced COPY of api.php is written to a temp dir and included.
 *     Included files do not inherit the includer's namespace, so this is the
 *     only way to make api.php's unqualified header() calls resolve to the
 *     LLRuntimeTest\header() stub (CLI's global header() is a no-op).
 *     __DIR__ in the copy is rewritten to the real plugin directory so the
 *     settings/log paths and the Timing.php require still point at the tree.
 *  2. FPP stubs live in the GLOBAL namespace because api.php probes them with
 *     function_exists('Name') string lookups. Its LLSyncTiming::* calls are
 *     aliased by an LLRuntimeTest\LLSyncTiming subclass of the global helper.
 */

namespace LLRuntimeTest {
    // Capture headers instead of sending them (CLI header() is a no-op).
    function header($header, $replace = true, $http_response_code = 0) {
        $GLOBALS['ll_headers'][] = $header;
    }

    // Timing.php has no namespace, so it defines the GLOBAL LLSyncTiming.
    // Load it here, before the alias, so the parent class exists when declared.
    require_once dirname(__DIR__) . '/src/Timing.php';
    // api.php's in-namespace LLSyncTiming::* calls resolve to this subclass.
    class LLSyncTiming extends \LLSyncTiming {}
}

namespace {
    $PLUGIN_DIR = dirname(__DIR__);

    // ---------------- build a namespaced copy of api.php ----------------
    $source = file_get_contents($PLUGIN_DIR . '/api.php');
    if ($source === false) {
        echo "glue_runtime_test: cannot read api.php\n";
        exit(1);
    }
    // Strip the opening PHP tag, rewrite __DIR__, and declare the namespace.
    $source = preg_replace('/^(\xEF\xBB\xBF)?<\?php\s*/', '', $source, 1);
    $source = str_replace('__DIR__', var_export($PLUGIN_DIR, true), $source);
    $tmpdir = '/private/var/folders/jb/lpqbm1_s0ql_0693mn49kkf80000gn/T/opencode';
    if (!is_dir($tmpdir)) $tmpdir = sys_get_temp_dir();
    $tmpApi = $tmpdir . '/ll_api_runtime_' . getmypid() . '.php';
    file_put_contents($tmpApi, "<?php\nnamespace LLRuntimeTest;\n" . $source);
    register_shutdown_function(function () use ($tmpApi) { @unlink($tmpApi); });

    // api.php's alias guard calls function_exists('getEndpointsfpplistenlive'),
    // which checks the GLOBAL namespace. PHP function names are case-insensitive,
    // so without this global shim the guard misses the namespaced canonical
    // function and then fatals trying to redeclare it. With the shim, the guard
    // is skipped; the assertion below can still call the namespaced spelling.
    function getEndpointsfpplistenlive() {
        return \LLRuntimeTest\getEndpointsfppListenLive();
    }

    require_once $tmpApi;

    // ---------------- FPP stubs (global namespace) ----------------
    $GLOBALS['ll_settings'] = [];
    $GLOBALS['ll_writes'] = [];
    $GLOBALS['ll_headers'] = [];
    $GLOBALS['ll_json'] = [];

    function ReadSettingFromFile($name, $plugin = '') {
        return array_key_exists($name, $GLOBALS['ll_settings'])
            ? $GLOBALS['ll_settings'][$name]
            : false;
    }

    function WriteSettingToFile($name, $value, $plugin = '') {
        $GLOBALS['ll_settings'][$name] = (string)$value;
        $GLOBALS['ll_writes'][(string)$name] = (string)$value;
        return true;
    }

    function getSetting($name, $default = '') {
        return array_key_exists($name, $GLOBALS['ll_settings'])
            ? $GLOBALS['ll_settings'][$name]
            : $default;
    }

    // llJson() prefers FPP's native json(); our stub records the payload and
    // returns it instead of echoing + exit, so the test keeps running.
    function json($data) {
        $GLOBALS['ll_json'][] = $data;
        return $data;
    }

    // ---------------- tiny assertion helpers ----------------
    $rt_failures = 0;
    $rt_case = '';
    function rt_check($ok, $what) {
        global $rt_failures, $rt_case;
        if (!$ok) {
            echo "  FAIL  $rt_case: $what\n";
            $rt_failures++;
        }
    }
    function rt_case($name) { global $rt_case; $rt_case = $name; }

    // ---------------- 1. registered endpoints ----------------
    rt_case('endpoints');
    $endpoints = \LLRuntimeTest\getEndpointsfppListenLive();
    rt_check(is_array($endpoints) && count($endpoints) > 0, 'getEndpointsfppListenLive() returns a list');
    $names = [];
    foreach ($endpoints as $ep) {
        rt_check(isset($ep['endpoint'], $ep['method'], $ep['callback']), 'endpoint entry has method/endpoint/callback');
        $names[] = isset($ep['endpoint']) ? (string)$ep['endpoint'] : '';
    }
    foreach (['status', 'diagnostics', 'nowplaying', 'stream', 'media', 'save',
              'test', 'icon', 'logs', 'sync', 'clock', 'check-updates'] as $expected) {
        rt_check(in_array($expected, $names, true), "endpoint '$expected' is registered");
    }
    $forbidden = ['update', 'reinstall', 'uninstall', 'restart-fppd', 'restartfppd',
                  'restart_fppd', 'restart', 'install'];
    foreach ($names as $name) {
        rt_check(!in_array(strtolower($name), $forbidden, true),
                 "endpoint '$name' is not a destructive update/reinstall/uninstall/restart endpoint");
    }
    rt_check(\LLRuntimeTest\getEndpointsfpplistenlive() === $endpoints,
             'case-insensitive alias returns the same endpoint list');

    // ---------------- 2. settings defaults ----------------
    rt_case('settings defaults');
    $GLOBALS['ll_settings'] = []; // pristine store — no settings written yet
    $defaults = \LLRuntimeTest\llLoadSettings();
    rt_check(is_array($defaults), 'llLoadSettings() returns an array');
    rt_check(!array_key_exists('allow_remote', $defaults),
             'llLoadSettings() defaults contain no allow_remote key');
    rt_check(!array_key_exists('cors', $defaults), 'llLoadSettings() defaults contain no cors key');
    rt_check(isset($defaults['enabled']) && (int)$defaults['enabled'] === 1,
             'enabled defaults to 1');
    rt_check(isset($defaults['source']) && $defaults['source'] === 'auto',
             'source defaults to auto');
    rt_check(isset($defaults['volume']) && (int)$defaults['volume'] === 100,
             'volume defaults to 100');

    // ---------------- 3. save enabled=0 -> stream 503 ----------------
    rt_case('disabled stream');
    $_POST = ['enabled' => 0];
    $GLOBALS['ll_headers'] = [];
    $GLOBALS['ll_json'] = [];
    $saved = \LLRuntimeTest\llSaveEndpoint();
    rt_check(is_array($saved) && !empty($saved['success']), 'save accepted enabled=0');
    rt_check(isset($GLOBALS['ll_writes']['enabled']) && (string)$GLOBALS['ll_writes']['enabled'] === '0',
             'enabled=0 was persisted through WriteSettingToFile');
    rt_check(!array_key_exists('allow_remote', $GLOBALS['ll_writes']),
             'no allow_remote setting was written');

    $GLOBALS['ll_headers'] = [];
    $GLOBALS['ll_json'] = [];
    $response = \LLRuntimeTest\llStreamEndpoint();
    rt_check(in_array('HTTP/1.1 503 Service Unavailable', $GLOBALS['ll_headers'], true),
             'llStreamEndpoint() emits a 503 header when disabled');
    rt_check(is_array($response) && empty($response['success']),
             'disabled stream returns a JSON error payload');
    rt_check(isset($response['error']) && stripos($response['error'], 'disabled') !== false,
             'error message explains that Listen Live is disabled');
    $_POST = [];

    // ---------------- 4. sync/clock never 503 and fill duration ----------------
    rt_case('sync/clock shape');
    $GLOBALS['ll_headers'] = [];
    $GLOBALS['ll_json'] = [];
    $sync = \LLRuntimeTest\llSyncEndpoint();
    rt_check(is_array($sync), 'llSyncEndpoint() returns an array');
    $allowedSources = ['c++', 'file', 'fallback'];
    rt_check(isset($sync['source']) && in_array($sync['source'], $allowedSources, true),
             'sync source is one of c++/file/fallback');
    rt_check(array_key_exists('duration', $sync), 'sync always has duration');
    rt_check(array_key_exists('has_duration', $sync), 'sync always has has_duration');
    rt_check(array_key_exists('has_media', $sync), 'sync always has has_media');
    rt_check(array_key_exists('media', $sync), 'sync always has media');
    rt_check(!in_array('HTTP/1.1 503 Service Unavailable', $GLOBALS['ll_headers'], true),
             'sync never emits 503');

    $GLOBALS['ll_headers'] = [];
    $GLOBALS['ll_json'] = [];
    $clock = \LLRuntimeTest\llClockEndpoint();
    rt_check(is_array($clock), 'llClockEndpoint() returns an array');
    foreach (['monotonic_ms', 'wall_ms', 'epoch_ms'] as $field) {
        rt_check(isset($clock[$field]) && is_int($clock[$field]), "clock.$field is an int");
    }
    rt_check(!in_array('HTTP/1.1 503 Service Unavailable', $GLOBALS['ll_headers'], true),
             'clock never emits 503');

    // ---------------- 5. FPP transport mapping (auto-play / switching / end) ----------------
    rt_case('sync transport');
    $t = \LLRuntimeTest\llSyncTransport([
        'status_name' => 'playing',
        'current_song' => 'song.mp3',
        'current_sequence' => 'show.fseq',
        'current_playlist' => 'Saturday',
        'milliseconds_elapsed' => 12345,
        'seconds_remaining' => 45,
    ], ['duration' => 0, 'playlist_item' => 2, 'playlist_action' => 'start']);
    rt_check($t['available'] === true, 'transport available from fppd status');
    rt_check($t['playing'] === true, 'transport playing');
    rt_check($t['current_song'] === 'song.mp3' && $t['current_sequence'] === 'show.fseq',
             'transport exposes song and sequence');
    rt_check($t['media_key'] === 'song.mp3|show.fseq|Saturday|2',
             'media_key includes song, sequence, playlist and item');
    rt_check(!empty($t['clock_epoch']), 'transport exposes a clock epoch for track changes');
    rt_check(abs((float)$t['elapsed'] - 12.345) < 0.001, 'elapsed uses milliseconds_elapsed');
    rt_check(abs((float)$t['duration'] - 57.345) < 0.001, 'duration derived from elapsed + remaining');

    $idle = \LLRuntimeTest\llSyncTransport(['status_name' => 'idle', 'current_song' => 'false', 'current_sequence' => 'false'], []);
    rt_check($idle['available'] === true && $idle['playing'] === false, 'idle status detected');
    $unknown = \LLRuntimeTest\llSyncTransport(null, []);
    rt_check($unknown['available'] === false && $unknown['playing'] === false, 'unknown transport when fppd unreachable');
    $paused = \LLRuntimeTest\llSyncTransport(['status_name' => 'paused', 'current_song' => 'song.mp3', 'current_sequence' => 'show.fseq'], []);
    rt_check($paused['playing'] === false, 'paused FPP is not treated as playing even with media fields');

    rt_case('clock gate release');
    $gateT = ['elapsed' => 12.0, 'current_song' => 'song.mp3', 'current_sequence' => 'show.fseq'];
    rt_check(\LLRuntimeTest\llGateClockReset($gateT, ['media' => 'song.mp3', 'extrapolated_seconds' => 12.3, 'file_type' => 'media'], 1),
             'gate releases when native and fppd agree for the same song');
    rt_check(!\LLRuntimeTest\llGateClockReset($gateT, ['media' => 'song.mp3', 'extrapolated_seconds' => 95.0, 'file_type' => 'media'], 1),
             'gate stays closed while the native clock is stale');
    rt_check(!\LLRuntimeTest\llGateClockReset($gateT, ['media' => 'old.mp3', 'extrapolated_seconds' => 12.1, 'file_type' => 'media'], 1),
             'gate stays closed while native media does not match');
    rt_check(\LLRuntimeTest\llGateClockReset($gateT, ['media' => 'old.mp3', 'extrapolated_seconds' => 95.0, 'file_type' => 'media'], 11),
             'gate times out rather than hanging forever');

    rt_case('apply transport to sync');
    $base = [
        'has_media' => true, 'media' => 'show.fseq', 'file_type' => 'sequence',
        'seconds' => 7, 'extrapolated_seconds' => 7, 'duration' => 300,
    ];
    $mapped = \LLRuntimeTest\llApplyTransportToSync($base, [
        'status_name' => 'playing', 'current_song' => 'song.mp3',
        'current_sequence' => 'show.fseq', 'current_playlist' => 'Saturday',
    ], 1000);
    rt_check($mapped['media'] === 'song.mp3' && $mapped['sync_media'] === 'show.fseq',
             'sequence clock is mapped to the current_song audio');
    rt_check($mapped['has_media'] === true && is_array($mapped['transport']),
             'mapped sync carries transport');

    $silent = \LLRuntimeTest\llApplyTransportToSync($base, [
        'status_name' => 'playing', 'current_song' => '', 'current_sequence' => 'show.fseq',
    ], 1000);
    rt_check($silent['has_media'] === false && $silent['file_type'] === 'sequence-silent',
             'sequence without audio goes idle instead of decoding .fseq');

    rt_case('reanchor master');
    $re = \LLRuntimeTest\llReanchorMaster([
        'extrapolated_seconds' => 10.0,
        'snapshot_wall_ms' => 99000,
    ], 99250);
    rt_check(abs((float)$re['master_seconds'] - 10.25) < 0.001 && $re['sync_age_ms'] === 250,
             'master_seconds advanced by response age');

    rt_case('transport gate');
    $gated = \LLRuntimeTest\llApplyTransportGate([
        'has_media' => true, 'media' => 'old.mp3',
        'seconds' => 95.0, 'extrapolated_seconds' => 95.0, 'master_seconds' => 95.0,
    ], ['elapsed' => 12.5], true, 1000);
    rt_check(!empty($gated['clock_reset_pending']) && $gated['confidence'] === 'unresolved',
             'pending transition is not claimed exact');
    rt_check(abs((float)$gated['master_seconds'] - 12.5) < 0.001,
             'transition master is seeded from fppd elapsed, not the stale native clock');
    $cacheDir = \LLRuntimeTest\llGetCacheDir();
    rt_check(is_string($cacheDir) && is_dir($cacheDir) && is_writable($cacheDir),
             'cache directory is writable even when plugindata is root-owned');

    rt_case('one-shot fresh status');
    $tf = $cacheDir . '/ll_transport.json';
    @file_put_contents($tf, json_encode(['key' => 'x', 'fresh' => true]));
    rt_check(\LLRuntimeTest\llConsumeFreshTransportStatus() === true,
             'a media-key change requests one fresh fppd status');
    $after = @json_decode(@file_get_contents($tf), true);
    rt_check(is_array($after) && empty($after['fresh']),
             'fresh-status flag is consumed after one request');

    if ($rt_failures === 0) {
        echo "glue_runtime_test: all checks passed\n";
        exit(0);
    }
    echo "glue_runtime_test: $rt_failures failure(s)\n";
    exit(1);
}
