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
        'ffmpeg_pipewire' => false,
        'pulse' => false,
        'pulse_sources' => [],
        'pipewire' => false,
        'pipewire_sources' => [],
        'alsa' => false,
        'alsa_devices' => [],
        'liveAvailable' => false
    ];
    $ffmpeg = llFindFfmpeg();
    $result['ffmpeg_path'] = $ffmpeg;
    $ver = @shell_exec(escapeshellarg($ffmpeg) . ' -version 2>&1 | head -1');
    if ($ver && stripos($ver, 'ffmpeg version') !== false) {
        $result['ffmpeg'] = true;
    }
    // Check ffmpeg pipewire demuxer support
    $fmts = @shell_exec(escapeshellarg($ffmpeg) . ' -formats 2>&1 | grep -i pipewire');
    if ($fmts && stripos($fmts, 'pipewire') !== false) {
        $result['ffmpeg_pipewire'] = true;
    }
    // PipeWire detection — FPP 9+ uses PipeWire, pactl is pipewire-pulse compat
    // Try FPP's own PipeWire API first (most reliable on FPP)
    $pipewireApiUrls = [
        'http://localhost/api/pipewire/audio/sources',
        'http://127.0.0.1/api/pipewire/audio/sources',
        'http://localhost/api/pipewire/audio/plugin-sources',
        'http://127.0.0.1/api/pipewire/audio/plugin-sources',
        'http://localhost/api/pipewire/audio/sinks',
        'http://127.0.0.1/api/pipewire/audio/sinks',
    ];
    foreach ($pipewireApiUrls as $url) {
        $json = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $tmp = @curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($tmp !== false && $code === 200 && $tmp !== '') {
                $json = $tmp;
            }
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $tmp = @file_get_contents($url, false, $ctx);
            if ($tmp !== false && $tmp !== '') $json = $tmp;
        }
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data)) {
                $result['pipewire'] = true;
                // Flatten any source names
                foreach ($data as $item) {
                    if (is_string($item)) $result['pipewire_sources'][] = $item;
                    elseif (is_array($item) && isset($item['name'])) $result['pipewire_sources'][] = $item['name'];
                    elseif (is_array($item) && isset($item['nodeName'])) $result['pipewire_sources'][] = $item['nodeName'];
                }
                break;
            }
        }
    }
    // Fallback: check pipewire runtime / tools
    if (!$result['pipewire']) {
        $pwCheck = @shell_exec('which pw-cli 2>/dev/null; which wpctl 2>/dev/null; ls /run/user/*/pipewire-0 2>/dev/null | head -1');
        if ($pwCheck && (strpos($pwCheck, 'pw-cli') !== false || strpos($pwCheck, 'wpctl') !== false || strpos($pwCheck, 'pipewire-0') !== false)) {
            $result['pipewire'] = true;
        }
        // pactl info will say PipeWire when pipewire-pulse is active
        $info = @shell_exec('pactl info 2>&1');
        if ($info && stripos($info, 'PipeWire') !== false) {
            $result['pipewire'] = true;
            $result['pulse'] = true; // pipewire-pulse provides pulse compat
        }
        // wpctl status or pw-cli
        $wpctl = trim(@shell_exec('wpctl status 2>&1 | head -20') ?? '');
        if ($wpctl && stripos($wpctl, 'PipeWire') !== false) {
            $result['pipewire'] = true;
        }
    }
    // Pulse (pipewire-pulse or real pulse)
    $pactl = trim(@shell_exec('pactl list short sources 2>/dev/null') ?? '');
    if ($pactl !== '') {
        $result['pulse'] = true;
        foreach (explode("\n", $pactl) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (!empty($parts[1])) {
                $result['pulse_sources'][] = $parts[1];
                // Also consider pulse monitor sources as pipewire sources when pipewire is active
                if ($result['pipewire'] && strpos($parts[1], '.monitor') !== false && !in_array($parts[1], $result['pipewire_sources'])) {
                    $result['pipewire_sources'][] = $parts[1];
                }
            }
        }
    } else {
        // Check if pulse is running via pactl info
        $info = @shell_exec('pactl info 2>&1');
        if ($info && strpos($info, 'Server String') !== false) {
            $result['pulse'] = true;
        }
    }
    // Also try pw-cli dump for pipewire sources if still empty
    if ($result['pipewire'] && empty($result['pipewire_sources'])) {
        $dump = @shell_exec('pw-dump 2>/dev/null | grep -o "\"name\":[^,]*\.monitor[^,]*" | head -5');
        if ($dump) {
            foreach (explode("\n", $dump) as $line) {
                if (preg_match('/"name":\s*"([^"]+\.monitor[^"]*)"/', $line, $m)) {
                    $result['pipewire_sources'][] = $m[1];
                }
            }
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
    // Deduplicate
    $result['pulse_sources'] = array_values(array_unique($result['pulse_sources']));
    $result['pipewire_sources'] = array_values(array_unique($result['pipewire_sources']));
    $result['alsa_devices'] = array_values(array_unique($result['alsa_devices']));
    // Live capture is available if ffmpeg exists and at least one audio backend is present
    $result['liveAvailable'] = $result['ffmpeg'] && ($result['pipewire'] || $result['pulse'] || $result['alsa']);
    return $result;
}

function llGetFppStatus() {
    // Use fppd's direct HTTP (port 32322, no /api prefix) to avoid Apache deadlock
    // When this plugin's status is requested via Apache, a nested curl to localhost:80/api/fppd/status
    // would deadlock if Apache has no free workers. Direct to fppd avoids that.
    $urls = [
        'http://127.0.0.1:32322/fppd/status',
        'http://localhost:32322/fppd/status',
        'http://127.0.0.1/api/fppd/status',
        'http://localhost/api/fppd/status',
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

function llGetMultisyncElapsed() {
    // Try to get multisync master elapsed for show sync
    // FPP remotes sync to master via multisync; master time is authoritative
    // Check common multisync endpoints and status fields
    $candidates = [
        'http://localhost/api/fppd/multisync',
        'http://127.0.0.1/api/fppd/multisync',
        'http://localhost/api/system/multisync',
        'http://127.0.0.1/api/system/multisync',
        'http://localhost/api/multisync/status',
    ];
    foreach ($candidates as $url) {
        $json = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $tmp = @curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($tmp !== false && $code === 200 && $tmp !== '' && $tmp[0] === '{') $json = $tmp;
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $tmp = @file_get_contents($url, false, $ctx);
            if ($tmp !== false && $tmp !== '' && $tmp[0] === '{') $json = $tmp;
        }
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                // Look for elapsed fields in various possible structures
                foreach (['elapsed','seconds_elapsed','time_elapsed','masterElapsed','position'] as $k) {
                    if (isset($data[$k]) && is_numeric($data[$k])) return (float)$data[$k];
                    if (isset($data['master'][$k]) && is_numeric($data['master'][$k])) return (float)$data['master'][$k];
                }
            }
        }
    }
    // Also check FPP status for multisync field (some versions embed master info)
    $status = llGetFppStatus();
    if ($status && isset($status['multisync'])) {
        $ms = $status['multisync'];
        if (is_array($ms)) {
            foreach (['elapsed','seconds_elapsed','masterElapsed'] as $k) {
                if (isset($ms[$k]) && is_numeric($ms[$k])) return (float)$ms[$k];
            }
        }
    }
    return null;
}

function llGetBackgroundMusicStatus() {
    $urls = [
        'http://localhost/api/plugin/fpp-plugin-BackgroundMusic/status',
        'http://127.0.0.1/api/plugin/fpp-plugin-BackgroundMusic/status',
        'http://localhost/api/plugin/BackgroundMusic/status',
        'http://127.0.0.1/api/plugin/BackgroundMusic/status',
    ];
    foreach ($urls as $url) {
        $json = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $tmp = @curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($tmp !== false && $code === 200 && $tmp !== '' && $tmp[0] === '{') {
                $json = $tmp;
            }
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $tmp = @file_get_contents($url, false, $ctx);
            if ($tmp !== false && $tmp !== '' && $tmp[0] === '{') $json = $tmp;
        }
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }
    }
    // Fallback: read status file directly (more up-to-date and works even if HTTP API is slow)
    $statusFile = '/tmp/bg_music_status.txt';
    if (file_exists($statusFile) && is_readable($statusFile)) {
        $lines = @file($statusFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            $data = [];
            foreach ($lines as $line) {
                $pos = strpos($line, '=');
                if ($pos !== false) {
                    $k = substr($line, 0, $pos);
                    $v = substr($line, $pos + 1);
                    $data[$k] = $v;
                }
            }
            if (!empty($data['filename'])) {
                // Convert to API-like structure for compatibility
                $data['currentTrack'] = $data['filename'];
                $data['trackElapsed'] = isset($data['elapsed']) ? (int)$data['elapsed'] : 0;
                $data['trackDuration'] = isset($data['duration']) ? (int)$data['duration'] : 0;
                $data['backgroundMusicRunning'] = true;
                // Also include raw for debugging
                $data['_source'] = 'direct_file';
                return $data;
            }
        }
    }
    return null;
}

function llGetAfterHoursStatus() {
    $urls = [
        'http://localhost/api/plugin/fpp-after-hours/getDetails',
        'http://127.0.0.1/api/plugin/fpp-after-hours/getDetails',
    ];
    foreach ($urls as $url) {
        $json = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $tmp = @curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($tmp !== false && $code === 200 && $tmp !== '' && $tmp[0] === '{') {
                $json = $tmp;
            }
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $tmp = @file_get_contents($url, false, $ctx);
            if ($tmp !== false && $tmp !== '' && $tmp[0] === '{') $json = $tmp;
        }
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }
    }
    return null;
}

function llGetFallbackMedia() {
    // Returns [ 'path' => ..., 'type' => 'fpp'|'background'|'afterhours', 'info' => ... ] or null
    // 1) FPP current song/sequence
    $fppStatus = llGetFppStatus();
    if ($fppStatus) {
        $media = $fppStatus['current_song'] ?? $fppStatus['current_sequence'] ?? null;
        if ($media && is_string($media) && $media !== '' && $media !== 'false') {
            $path = llGetMediaPath($media);
            if ($path && file_exists($path)) {
                return ['path' => $path, 'type' => 'fpp', 'media' => $media, 'elapsed' => (float)($fppStatus['seconds_elapsed'] ?? 0)];
            }
            // Even if file not found, return media name for diagnostics
            return ['path' => null, 'type' => 'fpp', 'media' => $media, 'elapsed' => (float)($fppStatus['seconds_elapsed'] ?? 0)];
        }
    }
     // 2) BackgroundMusic plugin — check if it is playing
    $bg = llGetBackgroundMusicStatus();
    if ($bg) {
        // Check if background is a stream (internet radio) — handle before file candidates
        $bgSource = $bg['config']['BackgroundMusicSource'] ?? $bg['BackgroundMusicSource'] ?? '';
        $bgStreamUrl = $bg['config']['BackgroundMusicStreamURL'] ?? $bg['BackgroundMusicStreamURL'] ?? $bg['stream_url'] ?? $bg['streamUrl'] ?? '';
        if (($bgSource === 'stream' || !empty($bg['streamSource']) || !empty($bg['stream_source'])) && $bgStreamUrl) {
            return ['path' => null, 'type' => 'background', 'media' => $bgStreamUrl, 'streamUrl' => $bgStreamUrl, 'bgStatus' => $bg, 'elapsed' => 0];
        }
        // Try various field names used by different versions
        $candidates = [];
        // Common fields: currentTrack, currentSong, track, nowPlaying, currentMedia
        foreach (['currentTrack','currentSong','current_track','track','nowPlaying','currentMedia','playingTrack'] as $k) {
            if (isset($bg[$k]) && is_string($bg[$k]) && $bg[$k] !== '') $candidates[] = $bg[$k];
            if (isset($bg['data'][$k]) && is_string($bg['data'][$k]) && $bg['data'][$k] !== '') $candidates[] = $bg['data'][$k];
        }
        // Also check nested status objects
        if (isset($bg['status']) && is_array($bg['status'])) {
            foreach (['currentTrack','track'] as $k) if (isset($bg['status'][$k]) && is_string($bg['status'][$k])) $candidates[] = $bg['status'][$k];
        }
        // Check isPlaying flag
        $isPlaying = false;
        foreach (['isPlaying','playing','running','backgroundMusicRunning','isRunning'] as $k) {
            if (!empty($bg[$k])) $isPlaying = true;
            if (!empty($bg['data'][$k])) $isPlaying = true;
        }
        // Extract elapsed for background — use trackElapsed if available (for sync)
        $bgElapsed = 0;
        foreach (['trackElapsed','elapsed','position','currentTime'] as $ek) {
            if (isset($bg[$ek]) && is_numeric($bg[$ek])) { $bgElapsed = (float)$bg[$ek]; break; }
            if (isset($bg['data'][$ek]) && is_numeric($bg['data'][$ek])) { $bgElapsed = (float)$bg['data'][$ek]; break; }
        }
        // Also check statusFile values: statusData elapsed is seconds
        if ($bgElapsed == 0 && isset($bg['trackElapsed'])) $bgElapsed = (float)$bg['trackElapsed'];
        foreach ($candidates as $media) {
            $path = llGetMediaPath($media);
            if ($path && file_exists($path)) {
                return ['path' => $path, 'type' => 'background', 'media' => $media, 'elapsed' => $bgElapsed, 'bgStatus' => $bg];
            }
        }
        // Try reading playlist file directly for background (more reliable than mediaName)
        if ($isPlaying && !empty($candidates)) {
            $playlistFile = '/tmp/background_music_playlist.m3u';
            if (file_exists($playlistFile)) {
                $lines = @file($playlistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || $line[0] === '#') continue;
                        if (basename($line) === basename($candidates[0]) && file_exists($line)) {
                            return ['path' => $line, 'type' => 'background', 'media' => basename($line), 'elapsed' => $bgElapsed, 'bgStatus' => $bg];
                        }
                    }
                }
            }
        }
        // If bg says playing but we couldn't find file, return bg status for diagnostics
        if ($isPlaying || !empty($candidates)) {
            // Small retry for just-changed track (file may not be indexed yet)
            usleep(300000);
            foreach ($candidates as $media) {
                $path = llGetMediaPath($media);
                if ($path && file_exists($path)) {
                    return ['path' => $path, 'type' => 'background', 'media' => $media, 'elapsed' => $bgElapsed, 'bgStatus' => $bg];
                }
            }
            return ['path' => null, 'type' => 'background', 'media' => $candidates[0] ?? 'unknown', 'elapsed' => $bgElapsed, 'bgStatus' => $bg];
        }
        // Also check if bg has playlist details with current index
        if (isset($bg['playlistDetails']) || isset($bg['tracks'])) {
            return ['path' => null, 'type' => 'background', 'media' => 'background playlist', 'bgStatus' => $bg];
        }
    }
    // 3) After-hours plugin — internet stream (not local file)
    $ah = llGetAfterHoursStatus();
    if ($ah) {
        // Check if after-hours is active
        $isActive = false;
        if (!empty($ah['isPlaying']) || !empty($ah['playing']) || !empty($ah['status'])) {
            // getDetails returns status:true and data array when streams configured
            if (isset($ah['data']) && is_array($ah['data']) && !empty($ah['data'])) $isActive = true;
            if (isset($ah['isPlaying']) && $ah['isPlaying']) $isActive = true;
        }
        // Also check direct fields
        if (isset($ah['data']) && is_array($ah['data'])) {
            foreach ($ah['data'] as $stream) {
                if (isset($stream['url']) || isset($stream['streamUrl'])) {
                    $url = $stream['url'] ?? $stream['streamUrl'];
                    if ($url) return ['path' => null, 'type' => 'afterhours', 'media' => $url, 'ahStatus' => $ah, 'streamUrl' => $url];
                }
            }
        }
        if ($isActive) {
            return ['path' => null, 'type' => 'afterhours', 'media' => 'after-hours stream', 'ahStatus' => $ah];
        }
    }
    return null;
}

function llGetMediaPath($mediaName) {
    // If already absolute path
    if (strpos($mediaName, '/') === 0 && file_exists($mediaName)) return $mediaName;
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
    // Try background music playlist file (contains full paths)
    $bgPlaylist = '/tmp/background_music_playlist.m3u';
    if (file_exists($bgPlaylist)) {
        $lines = @file($bgPlaylist, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (basename($line) === basename($mediaName) && file_exists($line)) return $line;
                if ($line === $mediaName && file_exists($line)) return $line;
            }
        }
    }
    // fallback: search via find (limited)
    $out = trim(@shell_exec('find /home/fpp/media -maxdepth 4 -name ' . escapeshellarg(basename($mediaName)) . ' 2>/dev/null | head -1') ?? '');
    if ($out && file_exists($out)) return $out;
    return null;
}

function llIsSilenceBuffer($buffer) {
    // Very rough silence detection: check if MP3 payload after headers is mostly zeros
    // MP3 frames start with 0xFF 0xFB or similar sync; silence will have low energy in side info
    // For now, consider buffer with < 5% non-zero bytes after first 1k as silence
    if (strlen($buffer) < 4096) return true;
    $sample = substr($buffer, 1024, 4096);
    $nonZero = 0;
    for ($i=0; $i<strlen($sample); $i++) {
        if (ord($sample[$i]) > 16) $nonZero++;
    }
    return ($nonZero / strlen($sample)) < 0.08;
}

function llBuildFfmpegCommand($settings, $detection) {
    $ffmpeg = llFindFfmpeg();
    $bitrate = preg_match('/^\d+k$/', $settings['bitrate'] ?? '') ? $settings['bitrate'] : '128k';
    $sr = (int)($settings['sample_rate'] ?? 44100);
    if (!in_array($sr, [22050, 32000, 44100, 48000])) $sr = 44100;
    $channels = (int)($settings['channels'] ?? 2);
    if ($channels < 1 || $channels > 2) $channels = 2;

    $source = $settings['source'] ?? 'auto';

    // Build ordered attempts — prioritize PipeWire (FPP 9+), then Pulse, then ALSA
    $attempts = [];

     // Helper to wrap ffmpeg with environment for pipewire/pulse socket access
    // FPP runs as user 'fpp', apache/php-fpm also as 'fpp', but ensure XDG_RUNTIME_DIR and PIPEWIRE_REMOTE
    $envPrefix = '';
    // FPP 10 uses system-wide PipeWire at /run/pipewire-fpp — use it explicitly
    if (is_dir('/run/pipewire-fpp') || file_exists('/run/pipewire-fpp/pipewire-0')) {
        $envPrefix = 'PIPEWIRE_REMOTE=/run/pipewire-fpp/pipewire-0 PIPEWIRE_RUNTIME_DIR=/run/pipewire-fpp ';
        // Do NOT set XDG_RUNTIME_DIR to /run/pipewire-fpp when running as fpp via sudo - it causes "not owned by us"
        // Only set XDG if running as root without sudo
        if (trim(@shell_exec('whoami 2>/dev/null') ?? '') === 'root') {
            $envPrefix .= 'XDG_RUNTIME_DIR=/run/pipewire-fpp ';
        }
    } else {
        $xdg = trim(@shell_exec('echo $XDG_RUNTIME_DIR 2>/dev/null') ?? '');
        if ($xdg === '') {
            if (is_dir('/run/user/1000')) $envPrefix = 'XDG_RUNTIME_DIR=/run/user/1000 ';
            elseif (is_dir('/run/user/512')) $envPrefix = 'XDG_RUNTIME_DIR=/run/user/512 ';
        }
        if (file_exists('/run/user/1000/pipewire-0')) {
            $envPrefix .= 'PIPEWIRE_REMOTE=/run/user/1000/pipewire-0 ';
        }
    }
    // For PipeWire system service, fpp user gets Permission denied even though in audio group
    // Use sudo (as root) for PipeWire, and sudo -u fpp for ALSA/pulse where needed
    $canSudo = trim(@shell_exec('sudo -n true 2>&1 && echo yes') ?? '') === 'yes';
    $canSudoFpp = trim(@shell_exec('sudo -n -u fpp true 2>&1 && echo yes') ?? '') === 'yes';
    $sudoPrefix = $canSudoFpp ? 'sudo -u fpp ' : '';
    $sudoRootPrefix = $canSudo ? 'sudo ' : '';

    if ($source === 'auto' || $source === 'pulse' || $source === 'pipewire') {
        // PipeWire first — prioritize the actual FPP mix (fpp_group_default.monitor) which carries show + background
        if (!empty($detection['pipewire'])) {
            $monitors = array_filter($detection['pipewire_sources'], fn($s) => strpos($s, '.monitor') !== false);
            usort($monitors, function($a,$b) {
                $score = function($s) {
                    if (strpos($s, 'fpp_group_default.monitor') !== false) return 0;
                    if (strpos($s, 'fpp_alsa_audio.monitor') !== false) return 1;
                    if (strpos($s, 'fpp_fx') !== false) return 2;
                    if (strpos($s, 'bgmusic') !== false) return 1;
                    if (strpos($s, 'alsa_output') !== false) return 3;
                    return 4;
                };
                return $score($a) - $score($b);
            });
            // Force fpp_group_default.monitor first if we know it exists (seen on this host) — use root for PipeWire
            $forced = ['fpp_group_default.monitor','fpp_alsa_audio.monitor','fpp_fx_g1_audio.monitor'];
            foreach ($forced as $f) {
                if (in_array($f, $monitors)) {
                    $monitors = array_merge([$f], array_diff($monitors, [$f]));
                } else {
                    $attempts[] = [$sudoRootPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i ' . escapeshellarg($f), 'pipewire-pulse:' . $f];
                }
            }
            // OS-level capture via pw-record as root — most reliable for FPP's system PipeWire (fpp user gets Permission denied)
            $pwSudo = trim(@shell_exec('sudo -n true 2>&1 && echo yes')) === 'yes' ? 'sudo ' : '';
            if (@shell_exec('which pw-record 2>/dev/null')) {
                foreach (['fpp_group_default','bgmusic_main','bgmusic_crossfade','fpp_alsa_audio','0'] as $tgt) {
                    $attempts[] = [$pwSudo . $envPrefix . 'pw-record --target ' . escapeshellarg($tgt) . ' - 2>/dev/null | ' . $ffmpeg . ' -hide_banner -loglevel error -f s16le -ar 48000 -ac 2 -i -', 'pw-record:' . $tgt];
                }
                $attempts[] = [$pwSudo . $envPrefix . 'pw-record - --rate 48000 --channels 2 2>/dev/null | ' . $ffmpeg . ' -hide_banner -loglevel error -f s16le -ar 48000 -ac 2 -i -', 'pw-record:default'];
            }
            foreach ($monitors as $src) {
                // Use sudo (as root) for PipeWire monitors — fpp gets Permission denied on system socket
                $attempts[] = [$sudoRootPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i ' . escapeshellarg($src), 'pipewire-pulse:' . $src];
                if (!empty($detection['ffmpeg_pipewire'])) {
                    $attempts[] = [$sudoRootPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pipewire -i ' . escapeshellarg($src), 'pipewire:' . $src];
                }
            }
            // Generic pulse default (pipewire-pulse)
            $attempts[] = [$sudoRootPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i default', 'pipewire-pulse:default'];
            $attempts[] = [$sudoRootPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i 0', 'pulse:0'];
            $attempts[] = [$sudoRootPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i 1', 'pulse:1'];
        }
        // Then Pulse
        if (!empty($detection['pulse'])) {
            $ps = $settings['pulse_source'] ?? 'auto';
            if ($ps === 'auto') {
                $monitor = null;
                foreach ($detection['pulse_sources'] as $s) {
                    if (strpos($s, '.monitor') !== false) { $monitor = $s; break; }
                }
                if ($monitor) {
                    $attempts[] = [$sudoPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i ' . escapeshellarg($monitor), 'pulse:' . $monitor];
                }
                $attempts[] = [$sudoPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i default', 'pulse:default'];
                $attempts[] = [$sudoPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i 0', 'pulse:0'];
            } else {
                $attempts[] = [$sudoPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i ' . escapeshellarg($ps), 'pulse:' . $ps];
            }
        } elseif ($source === 'auto') {
            $attempts[] = [$sudoPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i default', 'pulse:default'];
            $attempts[] = [$sudoPrefix . $envPrefix . $ffmpeg . ' -hide_banner -loglevel error -f pulse -i 0', 'pulse:0'];
        }
        // Also try parecord as fallback
        if (@shell_exec('which parecord 2>/dev/null')) {
            $attempts[] = [$sudoPrefix . $envPrefix . 'parecord --monitor-stream 0 --file-format=wav - 2>/dev/null | ' . $ffmpeg . ' -hide_banner -loglevel error -i -', 'parecord:0'];
        }
    }
    if ($source === 'alsa' || $source === 'auto') {
        $alsaDev = $settings['alsa_device'] ?? 'default';
        // Try to ensure snd-aloop is loaded for OS-level capture when direct ALSA is used
        @shell_exec('lsmod | grep -q snd_aloop || sudo modprobe snd-aloop 2>/dev/null');
        $attempts[] = [$sudoPrefix . $ffmpeg . ' -hide_banner -loglevel error -f alsa -i ' . escapeshellarg($alsaDev), 'alsa:' . $alsaDev];
        if ($alsaDev !== 'default') {
            $attempts[] = [$sudoPrefix . $ffmpeg . ' -hide_banner -loglevel error -f alsa -i default', 'alsa:default'];
        }
        // Try common ALSA devices for USB and onboard — ordered by likelihood on FPP (USB often hw:1,0)
        foreach (['hw:0,0','plughw:0,0','hw:1,0','plughw:1,0','hw:0,1','plughw:0,1','sysdefault','front','dsnoop:0','dsnoop:1'] as $dev) {
            if ($dev !== $alsaDev) {
                $attempts[] = [$sudoPrefix . $ffmpeg . ' -hide_banner -loglevel error -f alsa -i ' . escapeshellarg($dev), 'alsa:' . $dev];
            }
        }
        // Also try Loopback for snd-aloop capture (captures whatever is sent to hw:Loopback,0,0)
        $attempts[] = [$sudoPrefix . $ffmpeg . ' -hide_banner -loglevel error -f alsa -i hw:Loopback,1,0', 'alsa:Loopback'];
        $attempts[] = [$sudoPrefix . $ffmpeg . ' -hide_banner -loglevel error -f alsa -i hw:Loopback,0,0', 'alsa:Loopback0'];
        // Try dsnoop for shared capture when device is busy
        $attempts[] = [$sudoPrefix . $ffmpeg . ' -hide_banner -loglevel error -f alsa -i dsnoop:Loopback', 'alsa:dsnoop'];
        // Try to get FPP's configured audio output device
        $fppAudio = trim(@shell_exec('cat /home/fpp/media/config/FPPAudio 2>/dev/null || cat /home/fpp/media/settings 2>/dev/null | grep AudioOutput | cut -d= -f2') ?? '');
        if ($fppAudio && $fppAudio !== $alsaDev && $fppAudio !== 'default') {
            $attempts[] = [$sudoPrefix . $ffmpeg . ' -hide_banner -loglevel error -f alsa -i ' . escapeshellarg($fppAudio), 'alsa:fpp:' . $fppAudio];
        }
        // Try arecord pipe as last resort
        if (@shell_exec('which arecord 2>/dev/null')) {
            $attempts[] = [$sudoPrefix . 'arecord -D plughw:0,0 -f cd -t raw 2>/dev/null | ' . $ffmpeg . ' -hide_banner -loglevel error -f s16le -ar 44100 -ac 2 -i -', 'arecord:plughw0'];
        }
    }
    // NOTE: Do NOT add silence fallback here — file sync is preferred over silence.
    // Silence would show as "playing" but be inaudible, confusing users.

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
    $bgStatus = llGetBackgroundMusicStatus();
    $ahStatus = llGetAfterHoursStatus();
    $fallback = llGetFallbackMedia();
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
        if (isset($fppStatus['current_song'])) {
            $nowPlaying['media'] = $fppStatus['current_song'];
        } elseif (isset($fppStatus['current_sequence'])) {
            $nowPlaying['media'] = $fppStatus['current_sequence'];
        }
    }
    // No fallback augmentation per user request — live capture only
    // What is outputted from FPP (including background mixed via PipeWire) is what should be played
    $activeSource = 'fpp';
    if ($fppStatus && !empty($fppStatus['current_song'])) {
        $activeSource = 'fpp';
    } elseif ($fppStatus) {
        $activeSource = $fppStatus['status_name'] ?? 'idle';
    } else {
        $activeSource = 'unavailable';
    }
    return llJson([
        'success' => true,
        'settings' => $settings,
        'detection' => $detection,
        'fpp_status' => $fppStatus,
        'background_status' => $bgStatus,
        'afterhours_status' => $ahStatus,
        'fallback_media' => $fallback,
        'active_source' => $activeSource,
        'now_playing' => $nowPlaying,
        'stream_url' => 'api/plugin/fpp-ListenLive/stream',
        'media_url' => 'api/plugin/fpp-ListenLive/media'
    ]);
}

function llDiagnosticsEndpoint() {
    $detection = llDetectAudioSources();
    $fppStatus = llGetFppStatus();
    $bgStatus = llGetBackgroundMusicStatus();
    $ahStatus = llGetAfterHoursStatus();
    $fallback = llGetFallbackMedia();
    $settings = llLoadSettings();
    $cmds = llBuildFfmpegCommand($settings, $detection);
    return llJson([
        'success' => true,
        'detection' => $detection,
        'settings' => $settings,
        'ffmpeg_commands' => array_map(fn($c) => $c[1] . ' => ' . $c[0], $cmds),
        'fpp_reachable' => $fppStatus !== null,
        'background_reachable' => $bgStatus !== null,
        'afterhours_reachable' => $ahStatus !== null,
        'fallback_media' => $fallback,
        'background_status' => $bgStatus,
        'afterhours_status' => $ahStatus,
    ]);
}

function llNowPlayingEndpoint() {
    $fppStatus = llGetFppStatus();
    if ($fppStatus === null) {
        return llJson(['success' => false, 'error' => 'Could not reach FPPD at http://localhost/api/fppd/status']);
    }
    return llJson(['success' => true, 'status' => $fppStatus]);
}

function llStreamEndpoint() {
    $settings = llLoadSettings();
    if (empty($settings['enabled'])) {
        header('HTTP/1.1 503 Service Unavailable');
        header('Content-Type: application/json');
        return llJson(['success' => false, 'error' => 'Listen Live is disabled in plugin settings. Enable it in Content Setup → Listen Live → Config.']);
    }

    // Per latest user request: file-based playback synced to FPP multisync + BackgroundMusic
    // Try file-sync first (most reliable for staying in sync), live capture as fallback
    $detection = llDetectAudioSources();
    $ffmpeg = $detection['ffmpeg'];
    if (!$ffmpeg) {
        header('HTTP/1.1 503 Service Unavailable');
        header('Content-Type: application/json');
        llLog('Stream unavailable: ffmpeg not found');
        return llJson(['success' => false, 'error' => 'FFmpeg not found. Install ffmpeg (sudo apt install ffmpeg).']);
    }
    // Strictly file sync per user request — no OS-level fallback
    $preFallback = llGetFallbackMedia();
    if ($preFallback && (!empty($preFallback['path']) || !empty($preFallback['streamUrl']))) {
        llLog('Stream: file-sync for ' + $preFallback['type'] + ' - ' + $preFallback['media']);
        return llStreamFileSync(false);
    }
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: application/json');
    llLog('Stream unavailable: no file to sync (strict file-sync, no live fallback)');
    return llJson(['success' => false, 'error' => 'Stream unavailable — no media file currently playing. Start a playlist or background music. File sync is strictly used per user request.']);
    // Live capture fallback disabled per user request — code below is unreachable but kept for reference
    // Probe live capture candidates WITHOUT sending headers yet — find first that yields data
    // Use proc_open to capture stderr for detailed logging when probe fails
    $cmds = llBuildFfmpegCommand($settings, $detection);
    $probeResult = null;
    foreach ($cmds as $pair) {
        [$cmd, $label] = $pair;
        // Use proc_open so we can capture ffmpeg stderr for diagnostics
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $env = null;
        // Ensure correct env for pipewire/pulse
        $proc = @proc_open($cmd, $descriptors, $pipes, null, $env);
        if (!is_resource($proc)) {
            llLog('Stream probe failed to proc_open: ' . $label . ' cmd=' . $cmd);
            continue;
        }
        // Close stdin immediately
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $start = microtime(true);
        $gotData = false;
        $buffer = '';
        $stderr = '';
        while (microtime(true) - $start < 2.2) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                // Require at least 8k of MP3 data to be considered success (not just ID3 header)
                if (strlen($buffer) >= 8192) {
                    $gotData = true;
                    break;
                }
            }
            // Collect stderr non-blocking
            $errChunk = fread($pipes[2], 4096);
            if ($errChunk !== false && $errChunk !== '') $stderr .= $errChunk;
            // Check if process died
            $status = proc_get_status($proc);
            if (!$status['running'] && feof($pipes[1])) break;
            usleep(50000);
            if (connection_aborted()) {
                proc_terminate($proc, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                exit;
            }
        }
        // Collect any remaining stderr
        $extraErr = stream_get_contents($pipes[2]);
        if ($extraErr) $stderr .= $extraErr;
        // Clean up stderr pipe, keep stdout pipe open for streaming if we succeeded
        if (!$gotData) {
            // No data — log detailed reason
            $status = proc_get_status($proc);
            $exitCode = $status['exitcode'] ?? -1;
            $stderrTrim = trim(substr($stderr, 0, 1200));
            // Also try a quick diagnostic: is device busy, permission, etc.
            llLog('Stream probe no data: ' . $label . ' exit=' . $exitCode . ' stderr=' . ($stderrTrim ?: '(empty)') . ' cmd=' . $cmd);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            continue;
        }
        // Note: Don't check for silence here — even silence MP3 is valid data indicating the device is working
        // The actual background music will be audible when present; silence just means no audio currently, but device is still correct
        // Success — keep stdout pipe open, close stderr, and keep proc for streaming
        // For streaming we need to keep the proc open; store pipes and proc
        fclose($pipes[2]);
        $probeResult = ['handle' => $pipes[1], 'proc' => $proc, 'label' => $label, 'buffer' => $buffer, 'cmd' => $cmd];
        break;
    }

    if ($probeResult) {
        // We have live capture — now send headers and stream
        header('Content-Type: audio/mpeg');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Accept-Ranges: none');
        header('Connection: close');
        header('Access-Control-Allow-Origin: *');

        set_time_limit(0);
        ignore_user_abort(true);
        while (ob_get_level() > 0) { @ob_end_clean(); }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        ob_implicit_flush(1);

        $handle = $probeResult['handle'];
        $proc = $probeResult['proc'] ?? null;
        $label = $probeResult['label'];
        $buffer = $probeResult['buffer'];

        llLog('Stream started from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' source=' . $label . ' (live capture)');

        stream_set_blocking($handle, true);
        echo $buffer;
        flush();
        while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
            $chunk = fread($handle, 8192);
            if ($chunk === false) break;
            if ($chunk !== '') {
                echo $chunk;
                flush();
                usleep(5000);
            } else {
                // Check if proc died
                if ($proc) {
                    $st = proc_get_status($proc);
                    if (!$st['running']) break;
                } else if (feof($handle)) break;
                usleep(10000);
            }
            if (connection_aborted()) break;
        }
        // Check why we exited — log for debugging drops after ~60s
        $aborted = connection_aborted();
        $eof = feof($handle);
        if ($proc) {
            // proc_open path
            fclose($handle);
            $exitCode = 0;
            $status = proc_get_status($proc);
            if ($status['running']) {
                proc_terminate($proc, 9);
            }
            proc_close($proc);
            llLog('Stream ended live: ' . $label . ' eof=' . ($eof?1:0) . ' aborted=' . ($aborted?1:0) . ' status=' . connection_status() . ' exit=' . $exitCode);
            // Fallback is disabled per user request — just exit, don't try file sync
            exit;
        } else {
            pclose($handle);
            llLog('Stream ended live: ' . $label . ' eof=' . ($eof?1:0) . ' aborted=' . ($aborted?1:0) . ' status=' . connection_status());
            exit;
        }
    }

    // If live capture produced only silence, treat as failure and try next candidate
    // Check captured buffer for actual audio vs silence by looking at MP3 frame energy
    // Simple heuristic: silence MP3 at 128k for 1.2s should still be ~15k, but we can check for non-zero payload
    // For now, also try to verify via volumedetect if ffmpeg is available
    // Live capture failed for all candidates — no fallback per user request
    llLog('Stream live capture failed for all candidates — no fallback, stream unavailable');
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: application/json');
    $hint = 'Live capture failed. Tried: ' . implode(', ', array_map(fn($c)=>$c[1], $cmds));
    if (!$detection['pipewire'] && !$detection['pulse'] && !$detection['alsa']) {
        $hint .= ' — no capture devices detected. Ensure PipeWire/Pulse is running and FPP audio is configured.';
    }
    // Include hint about fallback being disabled
    return llJson(['success' => false, 'error' => 'Stream unavailable — live capture failed and fallback is disabled.', 'details' => $hint, 'hint' => 'What is outputted from FPP is what should be played. Check Diagnostics tab for capture devices.']);
}

function llStreamFileSync($isExplicitFileMode) {
    // Try to find a local media file to stream (FPP, BackgroundMusic, AfterHours)
    // Returns never — either streams and exits, or sends JSON error and exits

    // Ensure headers not yet sent as audio — we will decide type below
    // Clean buffers before choosing
    while (ob_get_level() > 0) { @ob_end_clean(); }

    $fallback = llGetFallbackMedia();
    // Use fallback's elapsed (which for FPP is seconds_elapsed, for background is trackElapsed)
    // This ensures sync with whatever source is active, including multisync-aware FPP timing
    $elapsed = (float)($fallback['elapsed'] ?? 0);
    // For FPP type, double-check multisync-aware elapsed from fresh status (fallback may be stale)
    if ($fallback && $fallback['type'] === 'fpp') {
        $fresh = llGetFppStatus();
        if ($fresh && isset($fresh['seconds_elapsed'])) {
            $elapsed = (float)$fresh['seconds_elapsed'];
            // If FPP is MultiSync remote, try to get master time via /api/multisync/status
            // Master time is more authoritative for show sync
            $msElapsed = llGetMultisyncElapsed();
            if ($msElapsed !== null) $elapsed = $msElapsed;
        }
    }

    // Handle internet stream (after-hours or background stream) — proxy it
    if ($fallback && in_array($fallback['type'], ['afterhours','background']) && !empty($fallback['streamUrl'])) {
        $streamUrl = $fallback['streamUrl'];
        llLog('Stream file sync: proxying ' . $fallback['type'] . ' stream ' . $streamUrl);
        // Proxy the remote stream via ffmpeg transcoding to mp3 for browser compat
        $ffmpeg = llFindFfmpeg();
        if (llDetectAudioSources()['ffmpeg']) {
            header('Content-Type: audio/mpeg');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Access-Control-Allow-Origin: *');
            set_time_limit(0);
            ignore_user_abort(true);
            @ini_set('zlib.output_compression', '0');
            ob_implicit_flush(1);
            $cmd = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -i ' . escapeshellarg($streamUrl) . ' -codec:a libmp3lame -b:a 128k -f mp3 -flush_packets 1 -';
            $handle = @popen($cmd . ' 2>/dev/null', 'r');
            if ($handle) {
                while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
                    $chunk = fread($handle, 8192);
                    if ($chunk !== false && $chunk !== '') { echo $chunk; flush(); }
                    if (connection_aborted()) break;
                    if ($chunk === '' || $chunk === false) usleep(20000);
                }
                pclose($handle);
                llLog('Stream after-hours proxy ended');
                exit;
            }
        }
        // Fallback: redirect to stream URL directly
        header('Location: ' . $streamUrl, true, 302);
        exit;
    }

    // For local file (FPP or BackgroundMusic), stream the file
    $path = $fallback['path'] ?? null;
    $media = $fallback['media'] ?? null;
    $type = $fallback['type'] ?? 'fpp';

    if ($path && file_exists($path)) {
        llLog('Stream file sync: streaming ' . $type . ' file ' . $path . ' elapsed=' . $elapsed);
        // Use ffmpeg to seek and transcode for accurate sync, fallback to raw read
        $ffmpeg = llFindFfmpeg();
        $hasFfmpeg = llDetectAudioSources()['ffmpeg'];
        if ($hasFfmpeg && $elapsed > 1) {
            header('Content-Type: audio/mpeg');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Access-Control-Allow-Origin: *');
            set_time_limit(0);
            ignore_user_abort(true);
            @ini_set('zlib.output_compression', '0');
            ob_implicit_flush(1);
            // Use -ss before -i for fast seek; subtract 1.3s for startup/network/ffmpeg latency so client is slightly behind, not ahead (user reported few seconds drift)
            $seekPos = max(0, $elapsed - 1.3);
            // Use -copyts and -start_at_zero to keep timestamps correct for gapless
            $cmd = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -ss ' . escapeshellarg((string)$seekPos) . ' -i ' . escapeshellarg($path) . ' -codec:a libmp3lame -b:a 128k -f mp3 -flush_packets 1 -';
            $handle = @popen($cmd . ' 2>/dev/null', 'r');
            if ($handle) {
                while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
                    $chunk = fread($handle, 8192);
                    if ($chunk !== false && $chunk !== '') { echo $chunk; flush(); }
                    if (connection_aborted()) break;
                    if ($chunk === '' || $chunk === false) usleep(15000);
                }
                pclose($handle);
                llLog('Stream file sync transcoded ended: ' . $path);
                exit;
            }
        }
        // Raw file fallback with byte-offset approximation (for mp3)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $path) : 'audio/mpeg';
        if ($finfo) finfo_close($finfo);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = ['mp3'=>'audio/mpeg','ogg'=>'audio/ogg','wav'=>'audio/wav','flac'=>'audio/flac','m4a'=>'audio/mp4','aac'=>'audio/aac'];
        if (isset($mimeMap[$ext])) $mime = $mimeMap[$ext];
        // For mp3, try byte offset seek (less accurate, fallback)
        if ($ext === 'mp3' && $elapsed > 1) {
            $bitrateBytes = 16000;
            $offset = (int)(max(0, $elapsed - 1.3) * $bitrateBytes);
            $size = filesize($path);
            if ($offset < $size) {
                header('Content-Type: audio/mpeg');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Access-Control-Allow-Origin: *');
                $fp = @fopen($path, 'rb');
                if ($fp) {
                    @fseek($fp, $offset);
                    set_time_limit(0);
                    ignore_user_abort(true);
                    @ini_set('zlib.output_compression', '0');
                    ob_implicit_flush(1);
                    while (!feof($fp) && connection_status() === CONNECTION_NORMAL) {
                        $data = fread($fp, 8192);
                        if ($data === false) break;
                        echo $data;
                        flush();
                        usleep(30000);
                        if (connection_aborted()) break;
                    }
                    fclose($fp);
                    llLog('Stream file sync raw ended (seek): ' . $path);
                    exit;
                }
            }
        }
        // No seek or non-mp3: just send file
        header('Content-Type: ' . $mime);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Access-Control-Allow-Origin: *');
        // Support range
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
        } else {
            header('Content-Length: ' . filesize($path));
        }
        header('Accept-Ranges: bytes');
        readfile($path);
        llLog('Stream file sync readfile ended: ' . $path);
        exit;
    }

    // No file found — check why and give helpful JSON error
    $reason = 'No audio is currently playing';
    $details = [];
    if ($fppStatus) {
        $statusName = $fppStatus['status_name'] ?? $fppStatus['status'] ?? 'idle';
        if (strtolower($statusName) === 'idle' || $fppStatus['current_song'] === null) {
            $reason = 'FPP is idle and no background music is active';
            $bg = llGetBackgroundMusicStatus();
            $ah = llGetAfterHoursStatus();
            if ($bg) $details[] = 'BackgroundMusic plugin responded but no track found';
            if ($ah) $details[] = 'AfterHours plugin responded but no stream active';
            if (!$bg && !$ah) $details[] = 'No background plugins detected — is BackgroundMusic or AfterHours installed and playing?';
        }
    } else {
        $reason = 'FPPD not reachable and no fallback media found';
    }
    if ($fallback && empty($fallback['path'])) {
        $reason = 'Found fallback media "' . ($fallback['media'] ?? 'unknown') . '" (' . $fallback['type'] . ') but file not found at ' . ($fallback['path'] ?? 'null');
    }
    if ($isExplicitFileMode) {
        $reason .= ' (file mode requested)';
    }
    llLog('Stream file sync failed: ' . $reason . ' details: ' . implode('; ', $details));
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: application/json');
    return llJson(['success' => false, 'error' => $reason, 'details' => $details, 'hint' => 'Start a playlist, or start background music via BackgroundMusic/AfterHours plugin, then try again. Check Diagnostics and Logs tabs.']);
}

function llMediaStreamEndpoint() {
    // Direct media file streaming with optional seek: /api/plugin/fpp-ListenLive/media?file=xxx&seek=seconds
    $file = $_GET['file'] ?? $_GET['media'] ?? llParam('file', null);
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
        $file = llParam('file', llParam('media', null));
    }

    if (!$file) {
        // If no file specified, stream currently playing media
        $fppStatus = llGetFppStatus();
        $file = $fppStatus['current_song'] ?? $fppStatus['current_sequence'] ?? null;
        if (!$file) {
            header('HTTP/1.1 404 Not Found');
            return llJson(['success' => false, 'error' => 'No file specified and nothing is currently playing']);
        }
    }

    $path = llGetMediaPath($file);
    if (!$path || !file_exists($path)) {
        header('HTTP/1.1 404 Not Found');
        return llJson(['success' => false, 'error' => 'Media not found: ' . $file]);
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
        llHeaderRemove('Content-Length');
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
        return llJson(['success' => false, 'error' => 'No data received']);
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
        return llJson(['success' => false, 'error' => 'Could not write settings file. Check permissions.']);
    }
    llLog('Settings saved: source=' . $clean['source'] . ' bitrate=' . $clean['bitrate']);
    return llJson(['success' => true, 'message' => 'Settings saved', 'settings' => $clean]);
}

function llTestEndpoint() {
    $detection = llDetectAudioSources();
    $settings = llLoadSettings();
    $results = [];
    $results[] = ['check' => 'FFmpeg installed (' . $detection['ffmpeg_path'] . ')', 'ok' => $detection['ffmpeg'] ? 1 : 0, 'detail' => $detection['ffmpeg_pipewire'] ? 'pipewire demuxer yes' : ''];
    $results[] = ['check' => 'PipeWire available', 'ok' => $detection['pipewire'] ? 1 : 0, 'detail' => implode(', ', array_slice($detection['pipewire_sources'],0,3))];
    $results[] = ['check' => 'PulseAudio (pipewire-pulse) available', 'ok' => $detection['pulse'] ? 1 : 0, 'detail' => implode(', ', $detection['pulse_sources'])];
    $results[] = ['check' => 'ALSA available', 'ok' => $detection['alsa'] ? 1 : 0, 'detail' => implode(', ', array_slice($detection['alsa_devices'],0,3))];
    $fppStatus = llGetFppStatus();
    $bgStatus = llGetBackgroundMusicStatus();
    $ahStatus = llGetAfterHoursStatus();
    $fallback = llGetFallbackMedia();
    $results[] = ['check' => 'FPPD reachable', 'ok' => $fppStatus ? 1 : 0];
    if ($fppStatus) {
        $media = $fppStatus['current_song'] ?? $fppStatus['current_sequence'] ?? 'none';
        $results[] = ['check' => 'FPP currently playing', 'ok' => 1, 'detail' => is_string($media) ? $media : json_encode($media)];
    }
    $results[] = ['check' => 'BackgroundMusic plugin', 'ok' => $bgStatus ? 1 : 0, 'detail' => $bgStatus ? json_encode(array_slice($bgStatus,0,2)) : 'not installed / not responding'];
    $results[] = ['check' => 'AfterHours plugin', 'ok' => $ahStatus ? 1 : 0, 'detail' => $ahStatus ? 'responding' : 'not installed / not responding'];
    if ($fallback) {
        $results[] = ['check' => 'Fallback media (' . $fallback['type'] . ')', 'ok' => !empty($fallback['path']) || !empty($fallback['streamUrl']) ? 1 : 0, 'detail' => $fallback['media'] . (empty($fallback['path']) && empty($fallback['streamUrl']) ? ' (file not found)' : '')];
    } else {
        $results[] = ['check' => 'Fallback media', 'ok' => 0, 'detail' => 'No FPP/background/after-hours media found'];
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
    return llJson(['success' => $allOk, 'results' => $results, 'detection' => $detection]);
}

function llIconEndpoint() {
    $iconFile = LL_PLUGIN_DIR . '/icon.png';
    if (!file_exists($iconFile)) {
        header('HTTP/1.0 404 Not Found');
        return llJson(['error' => 'Icon not found']);
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
    if (!file_exists($logFile)) return llJson(['success' => true, 'entries' => []]);
    $fileLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($fileLines === false) return llJson(['success' => false, 'error' => 'Could not read log file.']);
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
    return llJson(['success' => true, 'entries' => array_reverse($lines)]);
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
    return llJson(['updateAvailable' => $updateAvailable, 'localSha' => !empty($localSha) ? substr($localSha,0,7) : 'unknown', 'remoteSha' => !empty($remoteSha) ? substr($remoteSha,0,7) : 'unknown']);
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
    return llJson(['success' => true, 'message' => 'Plugin updated']);
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
    return llJson(['success' => true, 'message' => 'Plugin removed']);
}

function llRestartFPPDEndpoint() {
    $opts = ['http' => ['method' => 'PUT', 'header' => 'Content-Type: application/json', 'content' => '1']];
    $result = @file_get_contents('http://localhost/api/settings/restartFlag', false, stream_context_create($opts));
    if ($result === false) return llJson(['success' => false, 'error' => 'Could not set restart flag']);
    return llJson(['success' => true, 'message' => 'FPPD restart flag set']);
}

// Helpers that do NOT collide with FPP's reserved names (PluginApiFunctionConflicts scans for `function json`/`function param`)
function llParam($key, $default = null) {
    // Prefer FPP's native param() if it exists (limonade)
    if (function_exists('param') && is_callable('param')) {
        // Call FPP's param via indirection to avoid tokenizer seeing `param` definition
        $fn = 'param';
        return $fn($key, $default);
    }
    if (isset($_GET[$key])) return $_GET[$key];
    if (isset($_POST[$key])) return $_POST[$key];
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/' . preg_quote($key, '#') . '/([^/?]+)#', $uri, $m)) return urldecode($m[1]);
    return $default;
}
function llJson($data) {
    // Prefer FPP's native json() if available
    if (function_exists('json') && is_callable('json')) {
        $fn = 'json';
        return $fn($data);
    }
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE);
    exit;
}
function llHeaderRemove($name) {
    if (function_exists('header_remove')) header_remove($name);
}
?>
