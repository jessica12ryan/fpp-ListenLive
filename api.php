<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## api.php                                                 ##
 * #############################################################
 */

define('LL_PLUGIN_DIR', __DIR__);
define('LL_SETTINGS_FILE', LL_PLUGIN_DIR . '/config/settings.json');
define('LL_LOG_DIR', getenv('LOGDIR') ?: '/home/fpp/media/logs');
define('LL_LOG_FILE', LL_LOG_DIR . '/plugin-fpp-ListenLive.log');

function llLog($msg) {
    @file_put_contents(LL_LOG_FILE, date('Y-m-d H:i:s') . ' fpp-ListenLive api: ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function llLoadSettings() {
    $defaults = [
        'enabled' => 1,
        'source' => 'auto',
        'bitrate' => '128k',
        'sample_rate' => 44100,
        'channels' => 2,
        'alsa_device' => 'default',
        'pulse_source' => 'auto',
        'volume' => 100,
        'allow_remote' => 0
    ];
    if (!file_exists(LL_SETTINGS_FILE)) {
        return $defaults;
    }
    $s = json_decode(@file_get_contents(LL_SETTINGS_FILE), true);
    if (!is_array($s)) {
        return $defaults;
    }
    return array_merge($defaults, $s);
}

function llSaveSettings($s) {
    if (!is_dir(LL_PLUGIN_DIR . '/config')) {
        @mkdir(LL_PLUGIN_DIR . '/config', 0775, true);
    }
    return @file_put_contents(LL_SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function llFindFfmpeg() {
    $candidates = ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/fpp/bin/ffmpeg'];
    foreach ($candidates as $c) {
        if (is_executable($c)) return $c;
    }
    $which = trim(@shell_exec('which ffmpeg 2>/dev/null') ?? '');
    if ($which && is_executable($which)) return $which;
    return 'ffmpeg';
}

function llDetectAudioSources() {
    $result = [
        'ffmpeg' => false,
        'ffmpeg_path' => '',
        'pulse' => false,
        'pulse_sources' => [],
        'alsa' => false,
        'alsa_devices' => []
    ];
    $ffmpeg = llFindFfmpeg();
    $result['ffmpeg_path'] = $ffmpeg;
    $ver = @shell_exec(escapeshellarg($ffmpeg) . ' -version 2>&1 | head -1');
    if ($ver && stripos($ver, 'ffmpeg version') !== false) {
        $result['ffmpeg'] = true;
    }
    // Pulse
    $pactl = trim(@shell_exec('pactl list short sources 2>/dev/null') ?? '');
    if ($pactl !== '') {
        $result['pulse'] = true;
        foreach (explode("\n", $pactl) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (!empty($parts[1])) $result['pulse_sources'][] = $parts[1];
        }
    } else {
        // Check if pulse is running via pactl info
        $info = @shell_exec('pactl info 2>&1');
        if ($info && strpos($info, 'Server String') !== false) {
            $result['pulse'] = true;
        }
    }
    // ALSA
    $arecord = trim(@shell_exec('arecord -L 2>/dev/null | head -20') ?? '');
    if ($arecord !== '') {
        $result['alsa'] = true;
        foreach (explode("\n", $arecord) as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, ':') === false) {
                // skip description lines
                continue;
            }
            if (preg_match('/^(\w+:.*)/', $line, $m)) {
                $result['alsa_devices'][] = $m[1];
            }
        }
        if (empty($result['alsa_devices'])) {
            $result['alsa_devices'] = ['default', 'hw:0,0', 'plughw:0,0'];
        }
    } else {
        // fallback check
        if (file_exists('/proc/asound/cards')) {
            $cards = @file_get_contents('/proc/asound/cards');
            if ($cards && strpos($cards, ']:') !== false) {
                $result['alsa'] = true;
                $result['alsa_devices'] = ['default', 'hw:0,0'];
            }
        }
    }
    return $result;
}

function llGetFppStatus() {
    // Try multiple methods to reach FPPD — FPP's Apache on :80 proxies /api/fppd/status,
    // but some installs need curl or direct 127.0.0.1 handling.
    $urls = [
        'http://localhost/api/fppd/status',
        'http://127.0.0.1/api/fppd/status',
        'http://localhost:32322/api/fppd/status',
        'http://127.0.0.1:32322/api/fppd/status',
    ];

    // Prefer curl if available — more reliable than allow_url_fopen
    if (function_exists('curl_init')) {
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $json = @curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // curl_close is no-op since PHP 8.0, avoid deprecated warning
            if (function_exists('curl_close') && version_compare(PHP_VERSION, '8.0', '<')) {
                @curl_close($ch);
            }
            if ($json !== false && $httpCode === 200 && $json !== '') {
                $data = json_decode($json, true);
                if (is_array($data)) return $data;
            }
        }
    }

    // Fallback: file_get_contents
    foreach ($urls as $url) {
        $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json !== false && $json !== '') {
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }
    }

    // Last resort: try reading FPP status via local file (some FPP versions cache it)
    // or via fpp command line; return null if truly unreachable — caller should handle gracefully
    return null;
}

function llGetMediaPath($mediaName) {
    // FPP stores media in /home/fpp/media/music or /home/fpp/media/videos
    $bases = [
        '/home/fpp/media/music',
        '/home/fpp/media/videos',
        '/home/fpp/media',
        getenv('MEDIADIR') ? getenv('MEDIADIR') . '/music' : null,
    ];
    foreach ($bases as $base) {
        if (!$base) continue;
        $candidate = rtrim($base, '/') . '/' . $mediaName;
        if (file_exists($candidate)) return $candidate;
        // also try without subdir if mediaName already contains path
        $candidate2 = rtrim($base, '/') . '/' . basename($mediaName);
        if (file_exists($candidate2)) return $candidate2;
    }
    // fallback: search via find (limited)
    $out = trim(@shell_exec('find /home/fpp/media -maxdepth 4 -name ' . escapeshellarg(basename($mediaName)) . ' 2>/dev/null | head -1') ?? '');
    if ($out && file_exists($out)) return $out;
    return null;
}

function llBuildFfmpegCommand($settings, $detection) {
    $ffmpeg = llFindFfmpeg();
    $bitrate = preg_match('/^\d+k$/', $settings['bitrate'] ?? '') ? $settings['bitrate'] : '128k';
    $sr = (int)($settings['sample_rate'] ?? 44100);
    if (!in_array($sr, [22050, 32000, 44100, 48000])) $sr = 44100;
    $channels = (int)($settings['channels'] ?? 2);
    if ($channels < 1 || $channels > 2) $channels = 2;

    $source = $settings['source'] ?? 'auto';

    // Build ordered attempts
    $attempts = [];
    if ($source === 'pulse' || $source === 'auto') {
        if ($detection['pulse']) {
            $ps = $settings['pulse_source'] ?? 'auto';
            if ($ps === 'auto') {
                // Try monitor sources first
                $monitor = null;
                foreach ($detection['pulse_sources'] as $s) {
                    if (strpos($s, '.monitor') !== false) { $monitor = $s; break; }
                }
                if ($monitor) {
                    $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f pulse -i ' . escapeshellarg($monitor), 'pulse:' . $monitor];
                }
                // fallback to default pulse
                $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f pulse -i default', 'pulse:default'];
            } else {
                $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f pulse -i ' . escapeshellarg($ps), 'pulse:' . $ps];
            }
        } elseif ($source === 'auto') {
            // still try default pulse even if detection says no - ffmpeg will fail fast
            $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f pulse -i default', 'pulse:default'];
        }
    }
    if ($source === 'alsa' || $source === 'auto') {
        $alsaDev = $settings['alsa_device'] ?? 'default';
        $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f alsa -i ' . escapeshellarg($alsaDev), 'alsa:' . $alsaDev];
        if ($alsaDev !== 'default') {
            $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f alsa -i default', 'alsa:default'];
        }
        if ($alsaDev !== 'hw:0,0') {
            $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f alsa -i hw:0,0', 'alsa:hw:0,0'];
        }
    }
    // Always add silent fallback if everything else fails - generate silence so stream doesn't die,
    // but we will prefer file sync; this is last resort
    $attempts[] = [$ffmpeg . ' -hide_banner -loglevel error -f lavfi -i anullsrc=r=' . $sr . ':cl=stereo -t 3600', 'silence'];

    // Append encoding args to each attempt
    $enc = ' -ac ' . $channels . ' -ar ' . $sr . ' -codec:a libmp3lame -b:a ' . escapeshellarg($bitrate) . ' -f mp3 -flush_packets 1 -';

    $cmds = [];
    foreach ($attempts as $a) {
        $cmds[] = [$a[0] . $enc, $a[1]];
    }
    return $cmds;
}

// ---------------- Endpoints ----------------

function getEndpointsfppListenLive() {
    $result = [];
    $result[] = ['method' => 'GET',  'endpoint' => 'status',      'callback' => 'llStatusEndpoint'];
    $result[] = ['method' => 'GET',  'endpoint' => 'diagnostics', 'callback' => 'llDiagnosticsEndpoint'];
    $result[] = ['method' => 'GET',  'endpoint' => 'nowplaying',  'callback' => 'llNowPlayingEndpoint'];
    $result[] = ['method' => 'GET',  'endpoint' => 'stream',      'callback' => 'llStreamEndpoint'];
    $result[] = ['method' => 'GET',  'endpoint' => 'media',       'callback' => 'llMediaStreamEndpoint'];
    $result[] = ['method' => 'POST', 'endpoint' => 'save',        'callback' => 'llSaveEndpoint'];
    $result[] = ['method' => 'POST', 'endpoint' => 'test',        'callback' => 'llTestEndpoint'];
    $result[] = ['method' => 'GET',  'endpoint' => 'icon',        'callback' => 'llIconEndpoint'];
    $result[] = ['method' => 'GET',  'endpoint' => 'logs',        'callback' => 'llLogsEndpoint'];
    $result[] = ['method' => 'GET',  'endpoint' => 'check-updates','callback' => 'llCheckUpdatesEndpoint'];
    $result[] = ['method' => 'POST', 'endpoint' => 'update',      'callback' => 'llUpdateEndpoint'];
    $result[] = ['method' => 'POST', 'endpoint' => 'reinstall',   'callback' => 'llReinstallEndpoint'];
    $result[] = ['method' => 'POST', 'endpoint' => 'uninstall',   'callback' => 'llUninstallEndpoint'];
    $result[] = ['method' => 'POST', 'endpoint' => 'restart-fppd','callback' => 'llRestartFPPDEndpoint'];
    return $result;
}

// Alias for case-insensitive loader (FPP normalizes plugin name; guard for case-insensitive PHP)
if (!function_exists('getEndpointsfpplistenlive')) {
    eval('function getEndpointsfpplistenlive(){ return getEndpointsfppListenLive(); }');
}

function llStatusEndpoint() {
    $settings = llLoadSettings();
    $detection = llDetectAudioSources();
    $fppStatus = llGetFppStatus();
    $nowPlaying = null;
    if ($fppStatus) {
        $nowPlaying = [
            'status_name' => $fppStatus['status_name'] ?? $fppStatus['status'] ?? 'unknown',
            'current_playlist' => $fppStatus['current_playlist'] ?? null,
            'current_sequence' => $fppStatus['current_sequence'] ?? null,
            'current_song' => $fppStatus['current_song'] ?? null,
            'seconds_elapsed' => $fppStatus['seconds_elapsed'] ?? 0,
            'seconds_remaining' => $fppStatus['seconds_remaining'] ?? 0,
            'time_elapsed' => $fppStatus['time_elapsed'] ?? '',
            'time_remaining' => $fppStatus['time_remaining'] ?? '',
        ];
        // FPP sometimes nests under 'current_song' etc; try to normalize media name
        if (isset($fppStatus['current_song'])) {
            $nowPlaying['media'] = $fppStatus['current_song'];
        } elseif (isset($fppStatus['current_sequence'])) {
            $nowPlaying['media'] = $fppStatus['current_sequence'];
        }
    }
    return json([
        'success' => true,
        'settings' => $settings,
        'detection' => $detection,
        'fpp_status' => $fppStatus,
        'now_playing' => $nowPlaying,
        'stream_url' => 'api/plugin/fpp-ListenLive/stream',
        'media_url' => 'api/plugin/fpp-ListenLive/media'
    ]);
}

function llDiagnosticsEndpoint() {
    $detection = llDetectAudioSources();
    $fppStatus = llGetFppStatus();
    $settings = llLoadSettings();
    $cmds = llBuildFfmpegCommand($settings, $detection);
    return json([
        'success' => true,
        'detection' => $detection,
        'settings' => $settings,
        'ffmpeg_commands' => array_map(fn($c) => $c[1] . ' => ' . $c[0], $cmds),
        'fpp_reachable' => $fppStatus !== null
    ]);
}

function llNowPlayingEndpoint() {
    $fppStatus = llGetFppStatus();
    if ($fppStatus === null) {
        return json(['success' => false, 'error' => 'Could not reach FPPD at http://localhost/api/fppd/status']);
    }
    return json(['success' => true, 'status' => $fppStatus]);
}

function llStreamEndpoint() {
    // Streaming endpoint - outputs MP3 continuously
    $settings = llLoadSettings();
    if (empty($settings['enabled'])) {
        header('HTTP/1.1 503 Service Unavailable');
        return json(['success' => false, 'error' => 'Listen Live is disabled in plugin settings.']);
    }

    // Prevent caching
    header('Content-Type: audio/mpeg');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Accept-Ranges: none');
    header('Connection: close');
    // CORS for FPP UI
    header('Access-Control-Allow-Origin: *');

    // Disable timeout and buffering
    set_time_limit(0);
    ignore_user_abort(true);
    // Clean output buffers
    while (ob_get_level() > 0) { @ob_end_clean(); }
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
    @ini_set('implicit_flush', '1');
    ob_implicit_flush(1);

    llLog('Stream started from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' source=' . ($settings['source'] ?? 'auto'));

    $detection = llDetectAudioSources();
    if (!$detection['ffmpeg']) {
        // Fallback to file sync mode if ffmpeg missing
        llLog('Stream fallback: ffmpeg not found, trying file sync');
        // Try to stream current media file directly
        $fppStatus = llGetFppStatus();
        $media = $fppStatus['current_song'] ?? $fppStatus['current_sequence'] ?? null;
        if ($media) {
            $path = llGetMediaPath($media);
            if ($path && file_exists($path)) {
                $fp = @fopen($path, 'rb');
                if ($fp) {
                    // Seek based on elapsed? For live feel, start from elapsed offset approximatively
                    $elapsed = (float)($fppStatus['seconds_elapsed'] ?? 0);
                    // Estimate byte offset for MP3: bitrate 128k => 16KB/s
                    // This is rough but gives sync-ish behavior
                    $bitrateBytes = 16000; // 128k
                    $offset = (int)($elapsed * $bitrateBytes);
                    // Align to frame? just seek
                    @fseek($fp, $offset);
                    while (!feof($fp) && connection_status() === CONNECTION_NORMAL) {
                        echo fread($fp, 8192);
                        flush();
                        usleep(50000);
                        if (connection_aborted()) break;
                    }
                    fclose($fp);
                    llLog('Stream file sync ended');
                    exit;
                }
            }
        }
        // If no media, output error as audio? just exit with message
        echo "FFmpeg not available and no media playing\n";
        llLog('Stream failed: no ffmpeg and no media');
        exit;
    }

    $cmds = llBuildFfmpegCommand($settings, $detection);
    $success = false;
    foreach ($cmds as $pair) {
        [$cmd, $label] = $pair;
        // Test if command would quickly fail? Instead try streaming directly
        // We use popen to stream; if it fails immediately, try next
        $handle = @popen($cmd . ' 2>/dev/null', 'r');
        if (!$handle) {
            llLog('Stream attempt failed to popen: ' . $label);
            continue;
        }
        // Check if stream produces data within 1 second
        stream_set_blocking($handle, false);
        $start = microtime(true);
        $gotData = false;
        $buffer = '';
        while (microtime(true) - $start < 1.5) {
            $chunk = fread($handle, 8192);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                $gotData = true;
                break;
            }
            usleep(50000);
            if (connection_aborted()) {
                pclose($handle);
                exit;
            }
        }
        if (!$gotData) {
            pclose($handle);
            llLog('Stream attempt produced no data: ' . $label);
            if ($label === 'silence') {
                // silence source should work; retry blocking
                $handle = @popen($cmd . ' 2>/dev/null', 'r');
                if ($handle) {
                    stream_set_blocking($handle, true);
                    while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
                        $chunk = fread($handle, 8192);
                        if ($chunk === false || $chunk === '') {
                            usleep(20000);
                            continue;
                        }
                        echo $chunk;
                        flush();
                        if (connection_aborted()) break;
                    }
                    pclose($handle);
                    llLog('Stream silence ended');
                    exit;
                }
            }
            continue;
        }
        // We have data - switch to blocking and stream rest
        stream_set_blocking($handle, true);
        // Flush initial buffer
        echo $buffer;
        flush();
        llLog('Stream active using: ' . $label);
        $success = true;
        while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
            $chunk = fread($handle, 8192);
            if ($chunk === false) break;
            if ($chunk !== '') {
                echo $chunk;
                flush();
            } else {
                usleep(10000);
            }
            if (connection_aborted()) break;
        }
        pclose($handle);
        llLog('Stream ended for: ' . $label);
        exit;
    }

    if (!$success) {
        llLog('Stream all attempts failed');
        header('HTTP/1.1 500 Internal Server Error');
        echo "All audio sources failed. Check diagnostics.";
        exit;
    }
}

function llMediaStreamEndpoint() {
    // Direct media file streaming with optional seek: /api/plugin/fpp-ListenLive/media?file=xxx&seek=seconds
    $file = $_GET['file'] ?? $_GET['media'] ?? param('file', null);
    // FPP router may pass file as path param: /media/<name>
    if (!$file && isset($_GET['file'])) $file = $_GET['file'];
    // Try to get from URI
    if (!$file) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/media/(.+)$#', $uri, $m)) {
            $file = urldecode($m[1]);
        }
    }
    // Also check FPP's param helper
    if (!$file) {
        $file = param('file', param('media', null));
    }

    if (!$file) {
        // If no file specified, stream currently playing media
        $fppStatus = llGetFppStatus();
        $file = $fppStatus['current_song'] ?? $fppStatus['current_sequence'] ?? null;
        if (!$file) {
            header('HTTP/1.1 404 Not Found');
            return json(['success' => false, 'error' => 'No file specified and nothing is currently playing']);
        }
    }

    $path = llGetMediaPath($file);
    if (!$path || !file_exists($path)) {
        header('HTTP/1.1 404 Not Found');
        return json(['success' => false, 'error' => 'Media not found: ' . $file]);
    }

    $seek = isset($_GET['seek']) ? (float)$_GET['seek'] : 0;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $path) : 'audio/mpeg';
    if ($finfo) finfo_close($finfo);
    // Fallback mime by extension
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeMap = ['mp3'=>'audio/mpeg','ogg'=>'audio/ogg','wav'=>'audio/wav','flac'=>'audio/flac','m4a'=>'audio/mp4','aac'=>'audio/aac'];
    if (isset($mimeMap[$ext])) $mime = $mimeMap[$ext];

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-cache');
    // Support Range requests for seeking
    if (isset($_SERVER['HTTP_RANGE'])) {
        $size = filesize($path);
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $start = (int)$m[1];
            $end = $m[2] !== '' ? (int)$m[2] : $size - 1;
            if ($start < $size && $end < $size) {
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes $start-$end/$size");
                header('Content-Length: ' . ($end - $start + 1));
                $fp = fopen($path, 'rb');
                fseek($fp, $start);
                $remaining = $end - $start + 1;
                while ($remaining > 0 && !feof($fp) && connection_status() === CONNECTION_NORMAL) {
                    $chunk = fread($fp, min(8192, $remaining));
                    if ($chunk === false) break;
                    echo $chunk;
                    flush();
                    $remaining -= strlen($chunk);
                }
                fclose($fp);
                exit;
            }
        }
    }
    // Simple seek by byte offset estimation if requested
    if ($seek > 0) {
        $bitrateBytes = 16000;
        $offset = (int)($seek * $bitrateBytes);
        // For accurate seek with ffmpeg, we could use ffmpeg -ss, but for now just send file with Content-Range hint via 206
        // Fallback: use ffmpeg to transcode with seek for exact sync
        $ffmpeg = llFindFfmpeg();
        $cmd = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -ss ' . (int)$seek . ' -i ' . escapeshellarg($path) . ' -codec:a libmp3lame -b:a 128k -f mp3 -';
        header('Content-Type: audio/mpeg');
        headerRemove('Content-Length');
        set_time_limit(0);
        while (ob_get_level() > 0) @ob_end_clean();
        $handle = @popen($cmd . ' 2>/dev/null', 'r');
        if ($handle) {
            while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
                $chunk = fread($handle, 8192);
                if ($chunk !== false && $chunk !== '') { echo $chunk; flush(); }
                if (connection_aborted()) break;
            }
            pclose($handle);
            exit;
        }
    }
    readfile($path);
    exit;
}

function llSaveEndpoint() {
    $data = $_POST;
    if (empty($data)) {
        $raw = json_decode(file_get_contents('php://input'), true);
        if (is_array($raw)) $data = $raw;
    }
    if (empty($data)) {
        return json(['success' => false, 'error' => 'No data received']);
    }
    $existing = llLoadSettings();
    $allowed = ['enabled','source','bitrate','sample_rate','channels','alsa_device','pulse_source','volume','allow_remote'];
    $clean = $existing;
    foreach ($allowed as $k) {
        if (array_key_exists($k, $data)) $clean[$k] = $data[$k];
    }
    // Validate
    $clean['enabled'] = !empty($clean['enabled']) ? 1 : 0;
    $validSources = ['auto','pulse','alsa','file'];
    if (!in_array($clean['source'], $validSources)) $clean['source'] = 'auto';
    $validBitrates = ['64k','96k','128k','160k','192k','256k','320k'];
    if (!in_array($clean['bitrate'], $validBitrates)) $clean['bitrate'] = '128k';
    $clean['sample_rate'] = (int)$clean['sample_rate'];
    if (!in_array($clean['sample_rate'], [22050,32000,44100,48000])) $clean['sample_rate'] = 44100;
    $clean['channels'] = (int)$clean['channels'];
    if ($clean['channels'] < 1 || $clean['channels'] > 2) $clean['channels'] = 2;
    $clean['volume'] = max(0, min(200, (int)$clean['volume']));

    if (llSaveSettings($clean) === false) {
        return json(['success' => false, 'error' => 'Could not write settings file. Check permissions.']);
    }
    llLog('Settings saved: source=' . $clean['source'] . ' bitrate=' . $clean['bitrate']);
    return json(['success' => true, 'message' => 'Settings saved', 'settings' => $clean]);
}

function llTestEndpoint() {
    $detection = llDetectAudioSources();
    $settings = llLoadSettings();
    $results = [];
    $results[] = ['check' => 'FFmpeg installed (' . $detection['ffmpeg_path'] . ')', 'ok' => $detection['ffmpeg'] ? 1 : 0];
    $results[] = ['check' => 'PulseAudio available', 'ok' => $detection['pulse'] ? 1 : 0, 'detail' => implode(', ', $detection['pulse_sources'])];
    $results[] = ['check' => 'ALSA available', 'ok' => $detection['alsa'] ? 1 : 0, 'detail' => implode(', ', array_slice($detection['alsa_devices'],0,3))];
    $fppStatus = llGetFppStatus();
    $results[] = ['check' => 'FPPD reachable', 'ok' => $fppStatus ? 1 : 0];
    if ($fppStatus) {
        $media = $fppStatus['current_song'] ?? $fppStatus['current_sequence'] ?? 'none';
        $results[] = ['check' => 'Currently playing', 'ok' => 1, 'detail' => is_string($media) ? $media : json_encode($media)];
    }
    // Try a 1-second ffmpeg probe for the chosen source
    if ($detection['ffmpeg']) {
        $cmds = llBuildFfmpegCommand($settings, $detection);
        $probeOk = false;
        foreach (array_slice($cmds, 0, 2) as $pair) {
            [$cmd] = $pair;
            // Limit to 1 second of capture
            $probeCmd = $cmd . ' 2>&1 | head -c 1000';
            // Actually run with timeout
            $out = @shell_exec('timeout 2 ' . $cmd . ' 2>&1 | head -c 500; echo EXIT:$?');
            if ($out && strpos($out, 'EXIT') !== false) {
                // if we got binary data, it'll be non-empty
                if (strlen($out) > 100) $probeOk = true;
            }
        }
        $results[] = ['check' => 'Audio capture probe (1s)', 'ok' => $probeOk ? 1 : 0, 'detail' => $probeOk ? 'Got audio data' : 'No data - check source selection'];
    }
    $allOk = true;
    foreach ($results as $r) if (!$r['ok']) $allOk = false;
    return json(['success' => $allOk, 'results' => $results, 'detection' => $detection]);
}

function llIconEndpoint() {
    $iconFile = LL_PLUGIN_DIR . '/icon.png';
    if (!file_exists($iconFile)) {
        header('HTTP/1.0 404 Not Found');
        return json(['error' => 'Icon not found']);
    }
    $mtime = filemtime($iconFile);
    $etag = '"' . md5_file($iconFile) . '"';
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($iconFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
    readfile($iconFile);
    exit;
}

function llLogsEndpoint() {
    $logFile = LL_LOG_FILE;
    if (!file_exists($logFile)) return json(['success' => true, 'entries' => []]);
    $fileLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($fileLines === false) return json(['success' => false, 'error' => 'Could not read log file.']);
    $fileLines = array_slice($fileLines, -100);
    $lines = [];
    foreach ($fileLines as $line) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (fpp-ListenLive) (\S+): (.*)$/', $line, $m)) {
            $message = $m[4];
            $level = 'INFO';
            if (strpos($message, 'ERROR') !== false) $level = 'ERROR';
            elseif (strpos($message, 'SUCCESS') !== false) $level = 'SUCCESS';
            elseif (strpos($message, 'WARNING') !== false) $level = 'WARNING';
            $lines[] = ['timestamp' => $m[1], 'source' => $m[3], 'level' => $level, 'message' => $message];
        } else {
            $lines[] = ['timestamp' => '', 'source' => '', 'level' => 'INFO', 'message' => $line];
        }
    }
    return json(['success' => true, 'entries' => array_reverse($lines)]);
}

function llCheckUpdatesEndpoint() {
    $pluginDir = LL_PLUGIN_DIR;
    $localSha = trim(@shell_exec('git -C ' . escapeshellarg($pluginDir) . ' rev-parse HEAD 2>/dev/null') ?? '');
    $remoteRef = trim(@shell_exec('git -C ' . escapeshellarg($pluginDir) . ' ls-remote origin main 2>/dev/null') ?? '');
    $remoteSha = '';
    if (!empty($remoteRef)) {
        $parts = preg_split('/\s+/', $remoteRef);
        $remoteSha = $parts[0] ?? '';
    }
    $updateAvailable = !empty($localSha) && !empty($remoteSha) && $localSha !== $remoteSha;
    return json(['updateAvailable' => $updateAvailable, 'localSha' => !empty($localSha) ? substr($localSha,0,7) : 'unknown', 'remoteSha' => !empty($remoteSha) ? substr($remoteSha,0,7) : 'unknown']);
}

function llUpdateEndpoint() {
    $pluginDir = LL_PLUGIN_DIR;
    $backupDir = sys_get_temp_dir() . '/fpp-ll-update-backup';
    @mkdir($backupDir, 0777, true);
    if (file_exists(LL_SETTINGS_FILE)) @copy(LL_SETTINGS_FILE, $backupDir . '/settings.json');
    if (is_dir($pluginDir . '/.git')) {
        exec('git -C ' . escapeshellarg($pluginDir) . ' fetch origin 2>&1');
        exec('git -C ' . escapeshellarg($pluginDir) . ' checkout -- . 2>&1');
        exec('git -C ' . escapeshellarg($pluginDir) . ' clean -fd 2>&1');
        exec('git -C ' . escapeshellarg($pluginDir) . ' reset --hard origin/main 2>&1');
    }
    if (file_exists($backupDir . '/settings.json')) {
        @mkdir(dirname(LL_SETTINGS_FILE), 0777, true);
        @copy($backupDir . '/settings.json', LL_SETTINGS_FILE);
    }
    exec('rm -rf ' . escapeshellarg($backupDir));
    @chmod(LL_PLUGIN_DIR . '/config', 0775);
    foreach (glob(LL_PLUGIN_DIR . '/config/*') as $f) @chmod($f, 0664);
    $infoFile = $pluginDir . '/pluginInfo.json';
    if (file_exists($infoFile)) {
        $info = json_decode(file_get_contents($infoFile), true);
        if ($info && isset($info['versions'])) {
            $sha = trim(@shell_exec('git -C ' . escapeshellarg($pluginDir) . ' rev-parse HEAD 2>/dev/null') ?? '');
            if (!empty($sha)) {
                foreach ($info['versions'] as &$v) $v['sha'] = $sha;
                file_put_contents($infoFile, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            }
        }
    }
    $opts = ['http' => ['method' => 'PUT', 'header' => 'Content-Type: application/json', 'content' => '1']];
    @file_get_contents('http://localhost/api/settings/restartFlag', false, stream_context_create($opts));
    llLog('Plugin updated');
    return json(['success' => true, 'message' => 'Plugin updated']);
}

function llReinstallEndpoint() {
    return llUpdateEndpoint();
}

function llUninstallEndpoint() {
    $pluginDir = LL_PLUGIN_DIR;
    $it = new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) @rmdir($file->getRealPath()); else @unlink($file->getRealPath());
    }
    @rmdir($pluginDir);
    $opts = ['http' => ['method' => 'PUT', 'header' => 'Content-Type: application/json', 'content' => '1']];
    @file_get_contents('http://localhost/api/settings/restartFlag', false, stream_context_create($opts));
    llLog('Plugin uninstalled');
    return json(['success' => true, 'message' => 'Plugin removed']);
}

function llRestartFPPDEndpoint() {
    $opts = ['http' => ['method' => 'PUT', 'header' => 'Content-Type: application/json', 'content' => '1']];
    $result = @file_get_contents('http://localhost/api/settings/restartFlag', false, stream_context_create($opts));
    if ($result === false) return json(['success' => false, 'error' => 'Could not set restart flag']);
    return json(['success' => true, 'message' => 'FPPD restart flag set']);
}

// Helper for FPP's param() fallback
if (!function_exists('param')) {
    function param($key, $default = null) {
        if (isset($_GET[$key])) return $_GET[$key];
        if (isset($_POST[$key])) return $_POST[$key];
        // Check URI segments for /endpoint/value style
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/' . preg_quote($key, '#') . '/([^/?]+)#', $uri, $m)) return urldecode($m[1]);
        return $default;
    }
}
if (!function_exists('json')) {
    function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE);
        exit;
    }
}
if (!function_exists('headerRemove')) {
    function headerRemove($name) {
        if (function_exists('header_remove')) header_remove($name);
    }
}
?>
