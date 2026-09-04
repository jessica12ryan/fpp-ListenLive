<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## listen.php - Main player page                           ##
 * #############################################################
 */
$llPluginDir = __DIR__;
$llSettingsFile = $llPluginDir . '/config/settings.json';
$llSettings = [];
if (file_exists($llSettingsFile)) {
    $llSettings = json_decode(@file_get_contents($llSettingsFile), true) ?: [];
}
$enabled = !empty($llSettings['enabled']) ? 1 : 0;
// Preserve FPP global $settings for UI level detection — do not overwrite it
// FPP sets $settings from /home/fpp/media/settings; use that for tab visibility
$_fppUiLevel = (int)($settings['uiLevel'] ?? $GLOBALS['settings']['uiLevel'] ?? 0);
$uiLevel = $_fppUiLevel;
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
@media only screen and (max-width: 480px) {
    fieldset { padding: 5px !important; }
    table { width: 100%; table-layout: fixed; word-wrap: break-word; }
    td { display: block; width: 100% !important; box-sizing: border-box; }
    input[type="text"], select { width: 100% !important; box-sizing: border-box; }
    input.buttons { width: 100%; margin-bottom: 4px; box-sizing: border-box; }
}
.ll-player-wrap { background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-radius: 8px; padding: 16px; text-align: center; }
.ll-player-wrap audio { width: 100%; max-width: 560px; margin: 12px auto; display: block; }
.ll-controls { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; align-items: center; margin: 12px 0; }
.ll-controls .buttons { min-width: 110px; }
.ll-volume { display: flex; align-items: center; gap: 6px; justify-content: center; margin: 8px 0; }
.ll-volume input[type=range] { width: 160px; }
.ll-status-grid { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; text-align: left; max-width: 560px; margin: 12px auto; font-size: 14px; }
.ll-status-grid b { white-space: nowrap; }
.ll-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.ll-badge-live { background: #dc3545; color: #fff; animation: llpulse 1.5s infinite; }
.ll-badge-idle { background: #6c757d; color: #fff; }
.ll-badge-warn { background: #ffc107; color: #212529; }
@keyframes llpulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
.ll-nowplaying { font-size: 16px; font-weight: 600; margin: 8px 0; min-height: 24px; }
.ll-wave { height: 40px; display: flex; align-items: center; justify-content: center; gap: 3px; margin: 10px 0; }
.ll-wave span { display: inline-block; width: 4px; height: 12px; background: #0d6efd; border-radius: 2px; animation: llwave 0.8s ease-in-out infinite; }
.ll-wave span:nth-child(2) { animation-delay: 0.1s; }
.ll-wave span:nth-child(3) { animation-delay: 0.2s; }
.ll-wave span:nth-child(4) { animation-delay: 0.3s; }
.ll-wave span:nth-child(5) { animation-delay: 0.4s; }
@keyframes llwave { 0%,100% { height: 10px; } 50% { height: 30px; } }
.ll-wave.paused span { animation-play-state: paused; opacity: 0.3; }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Listen Live</legend>
        <div class="p-3">
            <div class="ll-player-wrap">
                <div style="display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap;">
                    <span id="ll_badge" class="ll-badge ll-badge-idle">Idle</span>
                    <span id="ll_status_text" class="text-secondary" style="font-size:14px;">Loading...</span>
                </div>

                <div id="ll_nowplaying" class="ll-nowplaying text-secondary">—</div>

                <div id="ll_wave" class="ll-wave paused">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>

                <audio id="ll_audio" controls preload="none" playsinline></audio>

                <div class="ll-controls">
                    <input type="button" class="buttons" id="ll_btn_play" value="▶ Play" onclick="llPlayer.play();">
                    <input type="button" class="buttons" id="ll_btn_stop" value="■ Stop" onclick="llPlayer.stop();">
                    <input type="button" class="buttons" value="↻ Reconnect" onclick="llPlayer.reconnect();">
                </div>

                <div class="ll-volume">
                    <label for="ll_vol"><b>Volume:</b></label>
                    <input type="range" id="ll_vol" min="0" max="100" value="80" oninput="llPlayer.setVolume(this.value);">
                    <span id="ll_vol_label">80%</span>
                    <input type="button" class="buttons" style="padding:4px 10px; margin-left:8px;" value="Mute" id="ll_mute_btn" onclick="llPlayer.toggleMute();">
                </div>

                <div class="ll-status-grid" id="ll_details">
                    <b>Source:</b> <span id="ll_src">—</span>
                    <b>Bitrate:</b> <span id="ll_bitrate">—</span>
                    <b>Elapsed:</b> <span id="ll_elapsed">—</span>
                    <b>Playlist:</b> <span id="ll_playlist">—</span>
                    <b>Sequence:</b> <span id="ll_sequence">—</span>
                </div>

                <p class="text-secondary" style="font-size:12px; margin-top:10px;">
                    Tip: Keep this tab open to monitor your show. Audio is streamed from the FPP's live output — what you hear is what your audience hears.<br>
                    If live capture is unavailable, the player will fall back to streaming the current media file in sync.
                </p>
            </div>
        </div>
    </fieldset>

    <fieldset class="border p-3" style="margin-top:12px;">
        <legend>Stream Info</legend>
        <div class="p-3" id="ll_stream_info">
            <span class="text-secondary">Loading diagnostics...</span>
        </div>
    </fieldset>
</div>

<script>
var llPlayer = {
    audio: null,
    isPlaying: false,
    isMuted: false,
    preMuteVol: 80,
    lastMedia: null,
    streamStartElapsed: null,
    streamStartTime: null,
    driftChecks: 0,
    reconnectAttempts: 0,
    stalledTimer: null,
    waitingTimer: null,
    trackChangePending: false,
    trackChangeTimeout: null,
    streamUrl: 'api/plugin/fpp-ListenLive/stream',
    // Append cache buster to force reconnect without browser cache
    buildUrl: function() { return llPlayer.streamUrl + '?t=' + Date.now(); },

    init: function() {
        llPlayer.audio = document.getElementById('ll_audio');
        // Load saved volume
        var savedVol = localStorage.getItem('fpp-ListenLive-volume');
        if (savedVol !== null) {
            var v = parseInt(savedVol, 10);
            if (!isNaN(v) && v >= 0 && v <= 100) {
                document.getElementById('ll_vol').value = v;
                document.getElementById('ll_vol_label').textContent = v + '%';
                llPlayer.audio.volume = v / 100;
            }
        } else {
            llPlayer.audio.volume = 0.8;
        }
        llPlayer.audio.addEventListener('playing', function() {
            llPlayer.isPlaying = true;
            llPlayer.reconnectAttempts = 0;
            llPlayer.trackChangePending = false;
            clearTimeout(llPlayer.trackChangeTimeout);
            clearTimeout(llPlayer.stalledTimer);
            clearTimeout(llPlayer.waitingTimer);
            // Reset sync tracking — will be set on next status poll with current elapsed
            llPlayer.streamStartElapsed = null;
            llPlayer.streamStartTime = Date.now();
            llPlayer.driftChecks = 0;
            $('#ll_badge').removeClass('ll-badge-idle ll-badge-warn').addClass('ll-badge-live').text('● LIVE');
            $('#ll_wave').removeClass('paused');
            $('#ll_btn_play').val('❚❚ Pause');
            $('#ll_btn_play').attr('onclick', 'llPlayer.pause();');
        });
        llPlayer.audio.addEventListener('pause', function() {
            // Only mark as paused if not ended and not seeking reconnect
            if (!llPlayer.audio.ended) {
                llPlayer.isPlaying = false;
                llPlayer.trackChangePending = false;
                clearTimeout(llPlayer.trackChangeTimeout);
                clearTimeout(llPlayer.stalledTimer);
                clearTimeout(llPlayer.waitingTimer);
                $('#ll_badge').removeClass('ll-badge-live').addClass('ll-badge-idle').text('Paused');
                $('#ll_wave').addClass('paused');
                $('#ll_btn_play').val('▶ Play');
                $('#ll_btn_play').attr('onclick', 'llPlayer.play();');
            }
        });
        llPlayer.audio.addEventListener('error', function() {
            var err = llPlayer.audio.error;
            var code = err ? err.code : 0;
            var msg = 'Stream error (code ' + code + ')';
            if (code === 4) msg += ' — source returned no audio or unsupported format';
            if (code === 2) msg += ' — network error';
            if (code === 3) msg += ' — decoding failed';
            llPlayer.reconnectAttempts = (llPlayer.reconnectAttempts || 0) + 1;
            $('#ll_badge').removeClass('ll-badge-live ll-badge-idle').addClass('ll-badge-warn').text('Error');
            $('#ll_status_text').html('<span class="text-danger">' + escHtml(msg) + ' (attempt ' + llPlayer.reconnectAttempts + '). Checking diagnostics...</span>');
            // Fetch diagnostics to show helpful hint
            $.ajax({
                url: 'api/plugin/fpp-ListenLive/diagnostics',
                type: 'GET',
                dataType: 'json',
                success: function(d) {
                    var hint = '';
                    var isTrackChange = llPlayer.lastMedia && d.fallback_media && d.fallback_media.media && llPlayer.lastMedia.indexOf(d.fallback_media.media) === -1;
                    if (isTrackChange) {
                        hint = ' — track changed, waiting for new file';
                        // Wait a bit longer for new track to be ready
                        if (llPlayer.isPlaying && llPlayer.reconnectAttempts < 5) {
                            setTimeout(function(){ llPlayer.reconnect(); }, 1800);
                            return;
                        }
                    }
                    if (d.fallback_media && !d.fallback_media.path && !d.fallback_media.streamUrl) {
                        hint = ' — no media found (' + escHtml(d.fallback_media.type + ': ' + d.fallback_media.media) + ')';
                        if (d.fallback_media.type === 'background' || d.fallback_media.type === 'fpp') {
                            hint += ' — is playlist still playing? Check FPP Status/Control';
                        }
                    } else if (!d.detection || !d.detection.ffmpeg) {
                        hint = ' — ffmpeg missing';
                    } else if (!d.detection.pipewire && !d.detection.pulse && !d.detection.alsa) {
                        hint = ' — no capture devices, trying file sync';
                    } else if (d.fallback_media && d.fallback_media.path) {
                        hint = ' — retrying file sync for ' + escHtml(d.fallback_media.media);
                    }
                    $('#ll_status_text').html('<span class="text-danger">' + escHtml(msg) + hint + '</span> <span class="text-secondary" style="font-size:12px;">Check Diagnostics & Logs tabs. Will retry...</span>');
                },
                error: function() {
                    $('#ll_status_text').html('<span class="text-danger">' + escHtml(msg) + '</span> <span class="text-secondary">Could not load diagnostics — will retry...</span>');
                }
            });
            // Auto reconnect with backoff if we were playing (max 5 attempts, then pause)
            if (llPlayer.isPlaying) {
                if (llPlayer.reconnectAttempts > 8) {
                    $('#ll_status_text').html('<span class="text-danger">Gave up after multiple errors — click Play to retry</span>');
                    llPlayer.isPlaying = false;
                    return;
                }
                var backoff = Math.min(8000, 1000 * Math.pow(1.5, llPlayer.reconnectAttempts - 1));
                // If track just changed, wait a bit longer
                if (llPlayer.lastMedia && llPlayer.reconnectAttempts === 1) backoff = Math.max(backoff, 1500);
                setTimeout(function() { if (llPlayer.isPlaying) llPlayer.reconnect(); }, backoff);
            }
        });
        llPlayer.audio.addEventListener('stalled', function() {
            if (llPlayer.trackChangePending) return;
            clearTimeout(llPlayer.stalledTimer);
            llPlayer.stalledTimer = setTimeout(function(){
                if (llPlayer.audio.readyState < 2 && llPlayer.isPlaying && !llPlayer.audio.paused && !llPlayer.trackChangePending) {
                    $('#ll_status_text').html('<span class="text-warning">Buffering...</span>');
                    clearTimeout(llPlayer.stalledTimer);
                    llPlayer.stalledTimer = setTimeout(function(){
                        if (llPlayer.audio.readyState < 2 && llPlayer.isPlaying && !llPlayer.trackChangePending) {
                            $('#ll_status_text').html('<span class="text-warning">Buffering timeout — re-syncing...</span>');
                            llPlayer.reconnect();
                        }
                    }, 4000);
                }
            }, 800);
        });
        llPlayer.audio.addEventListener('waiting', function() {
            if (llPlayer.trackChangePending) return;
            clearTimeout(llPlayer.waitingTimer);
            llPlayer.waitingTimer = setTimeout(function(){
                if (llPlayer.audio.readyState < 3 && llPlayer.isPlaying && !llPlayer.audio.paused && !llPlayer.trackChangePending) {
                    $('#ll_status_text').html('<span class="text-warning">Buffering...</span>');
                }
            }, 800);
        });
        llPlayer.audio.addEventListener('canplay', function() {
            clearTimeout(llPlayer.stalledTimer);
            clearTimeout(llPlayer.waitingTimer);
            if ($('#ll_status_text').text().indexOf('Buffering') !== -1 && llPlayer.isPlaying) {
                $('#ll_status_text').html('<span class="text-success">● Streaming live audio</span>');
            }
        });
        llPlayer.audio.addEventListener('progress', function() {
            if (llPlayer.audio.readyState >= 3) {
                clearTimeout(llPlayer.stalledTimer);
                clearTimeout(llPlayer.waitingTimer);
            }
        });
        llPlayer.refreshStatus();
        setInterval(llPlayer.refreshStatus, 2000);
        llPlayer.refreshDiagnostics();
    },

    play: function() {
        var a = llPlayer.audio;
        if (!a.src || a.src === window.location.href) {
            a.src = llPlayer.buildUrl();
            a.load();
        }
        var p = a.play();
        if (p && p.catch) {
            p.catch(function(e) {
                $('#ll_status_text').html('<span class="text-danger">Playback blocked: ' + e.message + ' — click Play again.</span>');
            });
        }
        llPlayer.isPlaying = true;
        $('#ll_status_text').html('<span class="text-success">Connecting...</span>');
    },

    pause: function() {
        llPlayer.audio.pause();
        llPlayer.isPlaying = false;
        llPlayer.trackChangePending = false;
        clearTimeout(llPlayer.trackChangeTimeout);
        clearTimeout(llPlayer.stalledTimer);
        clearTimeout(llPlayer.waitingTimer);
    },

    stop: function() {
        llPlayer.audio.pause();
        llPlayer.audio.removeAttribute('src');
        llPlayer.audio.load();
        llPlayer.isPlaying = false;
        llPlayer.trackChangePending = false;
        clearTimeout(llPlayer.trackChangeTimeout);
        clearTimeout(llPlayer.stalledTimer);
        clearTimeout(llPlayer.waitingTimer);
        $('#ll_badge').removeClass('ll-badge-live ll-badge-warn').addClass('ll-badge-idle').text('Stopped');
        $('#ll_wave').addClass('paused');
        $('#ll_status_text').html('<span class="text-secondary">Stopped</span>');
        $('#ll_btn_play').val('▶ Play');
        $('#ll_btn_play').attr('onclick', 'llPlayer.play();');
    },

    reconnect: function() {
        llPlayer.audio.pause();
        llPlayer.audio.src = llPlayer.buildUrl();
        llPlayer.audio.load();
        var p = llPlayer.audio.play();
        if (p && p.catch) p.catch(function(){});
        $('#ll_status_text').html('<span class="text-warning">Reconnecting...</span>');
        llPlayer.isPlaying = true;
    },

    setVolume: function(v) {
        v = parseInt(v, 10);
        if (isNaN(v)) return;
        llPlayer.audio.volume = v / 100;
        document.getElementById('ll_vol_label').textContent = v + '%';
        localStorage.setItem('fpp-ListenLive-volume', v);
        if (v > 0 && llPlayer.isMuted) {
            llPlayer.isMuted = false;
            $('#ll_mute_btn').val('Mute');
        }
    },

    toggleMute: function() {
        if (llPlayer.isMuted) {
            llPlayer.audio.muted = false;
            llPlayer.isMuted = false;
            $('#ll_mute_btn').val('Mute');
            var v = document.getElementById('ll_vol').value;
            llPlayer.audio.volume = v / 100;
        } else {
            llPlayer.preMuteVol = document.getElementById('ll_vol').value;
            llPlayer.audio.muted = true;
            llPlayer.isMuted = true;
            $('#ll_mute_btn').val('Unmute');
        }
    },

    refreshStatus: function() {
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/status',
            type: 'GET',
            dataType: 'json',
            success: function(d) {
                if (!d.success) {
                    $('#ll_status_text').html('<span class="text-danger">' + (d.error || 'Unknown error') + '</span>');
                    return;
                }
                var s = d.fpp_status || {};
                var np = d.now_playing || {};
                var settings = d.settings || {};
                var fppdReachable = !!d.fpp_status;
                var isBackgroundPlaying = !!(d.fallback_media && (d.fallback_media.type === 'background' || d.fallback_media.type === 'afterhours'));

                // Badge based on FPP status + background/after-hours
                var statusName = (s.status_name || s.status || '').toString().toLowerCase();
                var isPlaying = statusName.indexOf('playing') !== -1 || statusName.indexOf('active') !== -1;
                if (isBackgroundPlaying) isPlaying = true;
                if (!llPlayer.isPlaying) {
                    if (isPlaying) {
                        var label = isBackgroundPlaying && !s.current_song ? 'Background Playing' : 'FPP Playing';
                        $('#ll_badge').removeClass('ll-badge-idle ll-badge-live ll-badge-warn').addClass('ll-badge-warn').text(label);
                    } else {
                        $('#ll_badge').removeClass('ll-badge-live ll-badge-warn').addClass('ll-badge-idle').text('Idle');
                    }
                }
                // Now playing — also check background/after-hours when FPP idle
                // Handle current_playlist being an object like {"count":"0","playlist":""} when idle
                var rawPlaylist = s.current_playlist;
                var playlistStr = '';
                if (typeof rawPlaylist === 'string') playlistStr = rawPlaylist;
                else if (rawPlaylist && typeof rawPlaylist === 'object' && rawPlaylist.playlist) playlistStr = rawPlaylist.playlist;
                var media = s.current_song || s.current_sequence || playlistStr || '';
                if (typeof media === 'object') {
                    // Only stringify if it has meaningful content
                    if (media.playlist || media.count !== "0") media = JSON.stringify(media);
                    else media = '';
                }
                // Fallback to background/after-hours media when FPP idle
                if ((!media || media === 'false' || media === '') && d.fallback_media && d.fallback_media.media) {
                    media = d.fallback_media.media + ' (' + d.fallback_media.type + ')';
                    if (d.fallback_media.type === 'background') isBackgroundPlaying = true;
                    if (d.fallback_media.type === 'afterhours') isBackgroundPlaying = true;
                }
                if (!media || media === 'false' || media === '') {
                    $('#ll_nowplaying').html('<span class="text-secondary">Nothing playing — FPP is idle</span>');
                    $('#ll_elapsed').text('—');
                } else {
                    var elapsed = s.seconds_elapsed || s.time_elapsed || np.seconds_elapsed || 0;
                    var remaining = s.seconds_remaining || s.time_remaining || '';
                    // For background/after-hours, use fallback elapsed (trackElapsed) for sync-accurate display
                    if (isBackgroundPlaying) {
                        if (d.fallback_media && typeof d.fallback_media.elapsed === 'number' && d.fallback_media.elapsed > 0) {
                            elapsed = d.fallback_media.elapsed;
                        } else if (d.background_status && typeof d.background_status.trackElapsed === 'number') {
                            elapsed = d.background_status.trackElapsed;
                            if (typeof d.background_status.trackDuration === 'number' && d.background_status.trackDuration > 0) {
                                remaining = Math.max(0, d.background_status.trackDuration - elapsed);
                            }
                        }
                        // Multisync: if background is via FPP playlist, elapsed is already multisync-aware
                    }
                    $('#ll_nowplaying').text(media);
                    $('#ll_elapsed').text((elapsed || '0') + (remaining ? ' / ' + remaining : ''));
                    if (isBackgroundPlaying && !s.current_song) {
                        $('#ll_nowplaying').html(escHtml(media) + ' <span class="ll-badge" style="background:#198754;color:#fff;font-size:11px;">via ' + escHtml(d.fallback_media.type) + ' • sync</span>');
                    }
                }
                // Also show background status in playlist/sequence when idle
                var bgPlaylist = (d.background_status && (d.background_status.playlist || d.background_status.backgroundMusicPlaylist)) || '';
                if (!s.current_playlist && bgPlaylist) {
                    $('#ll_playlist').text(bgPlaylist + ' (BackgroundMusic)');
                } else {
                    $('#ll_playlist').text(s.current_playlist || np.current_playlist || (fppdReachable ? '—' : '— (FPPD not reachable)'));
                }
                if (!s.current_sequence && isBackgroundPlaying) {
                    $('#ll_sequence').text('— (background active)');
                } else {
                    $('#ll_sequence').text(s.current_sequence || (fppdReachable ? '—' : '— (FPPD not reachable)'));
                }
                $('#ll_src').text(settings.source || 'auto');
                $('#ll_bitrate').text(settings.bitrate || '128k');

                // Auto-reconnect when track changes while in file-sync mode
                // Live PipeWire/Pulse capture is gapless and mixes background correctly — no reconnect needed there
                var currentMediaKey = (media || '') + '|' + (s.current_playlist || '') + '|' + (d.fallback_media ? d.fallback_media.type : '') + '|' + (d.fallback_media ? d.fallback_media.media : '');
                var isFileSync = !settings.enabled ? false : (settings.source === 'file' || !det.liveAvailable);
                if (llPlayer.isPlaying && llPlayer.lastMedia && llPlayer.lastMedia !== currentMediaKey && isFileSync) {
                    $('#ll_status_text').html('<span class="text-warning">Track changed — re-syncing...</span>');
                    // Reset drift tracking for new track
                    llPlayer.streamStartElapsed = null;
                    llPlayer.streamStartTime = null;
                    llPlayer.driftChecks = 0;
                    llPlayer.trackChangePending = true;
                    llPlayer.lastMedia = currentMediaKey;
                    clearTimeout(llPlayer.stalledTimer);
                    clearTimeout(llPlayer.waitingTimer);
                    clearTimeout(llPlayer.trackChangeTimeout);
                    llPlayer.trackChangeTimeout = setTimeout(function(){ llPlayer.trackChangePending = false; }, 8000);
                    setTimeout(function(){
                        // Double-check new track still current before reconnect (avoid flapping if status hasn't settled)
                        $.ajax({
                            url: 'api/plugin/fpp-ListenLive/status',
                            type: 'GET',
                            dataType: 'json',
                            success: function(nd) {
                                var nm = (nd.fpp_status && (nd.fpp_status.current_song || nd.fpp_status.current_sequence)) || (nd.fallback_media && nd.fallback_media.media) || '';
                                if (nm && media && nm !== media.split(' (')[0]) {
                                    // Status still shows old track, wait a bit more
                                    setTimeout(function(){ if (llPlayer.isPlaying) llPlayer.reconnect(); }, 800);
                                } else {
                                    if (llPlayer.isPlaying) llPlayer.reconnect();
                                }
                            },
                            error: function(){ if (llPlayer.isPlaying) llPlayer.reconnect(); }
                        });
                    }, 1800);
                } else {
                    llPlayer.lastMedia = currentMediaKey;
                }

                // Drift correction for file-sync (keeps background/FPP file sync in sync with show)
                if (llPlayer.isPlaying && isFileSync && !llPlayer.trackChangePending && llPlayer.audio && !llPlayer.audio.paused && llPlayer.audio.readyState >= 2) {
                    var currentFallbackElapsed = 0;
                    if (d.fallback_media && typeof d.fallback_media.elapsed === 'number') currentFallbackElapsed = d.fallback_media.elapsed;
                    else if (typeof s.seconds_elapsed === 'number') currentFallbackElapsed = s.seconds_elapsed;
                    else if (d.background_status && typeof d.background_status.trackElapsed === 'number') currentFallbackElapsed = d.background_status.trackElapsed;
                    if (llPlayer.streamStartElapsed === null && currentFallbackElapsed > 0) {
                        llPlayer.streamStartElapsed = currentFallbackElapsed;
                        llPlayer.streamStartTime = Date.now();
                        llPlayer.driftChecks = 0;
                    } else if (llPlayer.streamStartElapsed !== null && currentFallbackElapsed > 0) {
                        var wallElapsed = (Date.now() - llPlayer.streamStartTime) / 1000;
                        var expectedAudioTime = currentFallbackElapsed - llPlayer.streamStartElapsed;
                        var actual = llPlayer.audio.currentTime;
                        // Account for initial seek offset already in stream (stream starts at seekPos, so actual 0 = elapsed)
                        // Drift is actual vs expected
                        var drift = actual - expectedAudioTime;
                        if (wallElapsed > 6 && Math.abs(drift) > 2.8) {
                            llPlayer.driftChecks = (llPlayer.driftChecks || 0) + 1;
                            if (llPlayer.driftChecks >= 2) {
                                $('#ll_status_text').html('<span class="text-warning">Drift ' + drift.toFixed(1) + 's — re-syncing...</span>');
                                llPlayer.driftChecks = 0;
                                llPlayer.streamStartElapsed = currentFallbackElapsed;
                                llPlayer.streamStartTime = Date.now();
                                setTimeout(function(){ if (llPlayer.isPlaying) llPlayer.reconnect(); }, 400);
                            }
                        } else {
                            // Small drift, reset counter
                            if (Math.abs(drift) < 1.5) llPlayer.driftChecks = 0;
                        }
                    }
                } else if (!llPlayer.isPlaying) {
                    llPlayer.streamStartElapsed = null;
                    llPlayer.streamStartTime = null;
                    llPlayer.driftChecks = 0;
                }

                if (!fppdReachable && !llPlayer.isPlaying && !isBackgroundPlaying) {
                    $('#ll_nowplaying').html('<span class="text-warning">FPPD not reachable — live capture still works, but Now Playing is unavailable. Check FPPD is running.</span>');
                }

                if (llPlayer.trackChangePending) {
                    // Keep "Track changed — re-syncing..." until new stream starts
                } else if (!settings.enabled) {
                    $('#ll_status_text').html('<span class="text-danger">Plugin disabled — enable in Config tab</span>');
                } else if (llPlayer.isPlaying) {
                    $('#ll_status_text').html('<span class="text-success">● Streaming live audio</span>' + (fppdReachable ? '' : ' <span class="text-warning" style="font-size:12px;">(FPPD unreachable)</span>') + (isBackgroundPlaying ? ' <span class="text-secondary" style="font-size:12px;">(background)</span>' : ''));
                } else {
                    if (!fppdReachable && !isBackgroundPlaying) {
                        $('#ll_status_text').html('<span class="text-warning">FPPD not reachable — click Play to try live capture anyway (Now Playing unavailable)</span>');
                    } else if (isBackgroundPlaying) {
                        $('#ll_status_text').html('<span class="text-success">Background music active — click Play to listen</span>');
                    } else if (isPlaying) {
                        $('#ll_status_text').html('<span class="text-warning">FPP is playing — click Play to listen</span>');
                    } else {
                        $('#ll_status_text').html('<span class="text-secondary">FPP is idle — start a playlist or background music to hear audio</span>');
                    }
                }
            },
            error: function(xhr) {
                var msg = 'Could not reach plugin API';
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.error) msg += ': ' + r.error;
                    else if (xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                } catch(e) {
                    if (xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                }
                $('#ll_status_text').html('<span class="text-danger">' + escHtml(msg) + ' — check FPP logs at /home/fpp/media/logs/plugin-fpp-ListenLive.log</span>');
                $('#ll_stream_info').html('<span class="text-danger">' + escHtml(msg) + '</span>');
            }
        });
    },

    refreshDiagnostics: function() {
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/diagnostics',
            type: 'GET',
            dataType: 'json',
            success: function(d) {
                var det = d.detection || {};
                var html = '<table class="fppTable" style="width:auto;">';
                html += '<tr><td style="padding:4px;"><b>FFmpeg:</b></td><td style="padding:4px;">' + (det.ffmpeg ? '<span class="text-success">Yes</span> (' + escHtml(det.ffmpeg_path) + (det.ffmpeg_pipewire ? ', pipewire demuxer' : '') + ')' : '<span class="text-danger">Not found</span> — install ffmpeg') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>PipeWire:</b></td><td style="padding:4px;">' + (det.pipewire ? '<span class="text-success">Available</span>' : '<span class="text-secondary">Not detected</span>') + (det.pipewire_sources && det.pipewire_sources.length ? ' <span class="text-secondary">(' + escHtml(det.pipewire_sources.slice(0,2).join(', ')) + ')</span>' : '') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>PulseAudio:</b></td><td style="padding:4px;">' + (det.pulse ? '<span class="text-success">Available</span> <span class="text-secondary">(pipewire-pulse compat)</span>' : '<span class="text-secondary">Not detected</span>') + (det.pulse_sources && det.pulse_sources.length ? ' <span class="text-secondary">(' + escHtml(det.pulse_sources.slice(0,2).join(', ')) + ')</span>' : '') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>ALSA:</b></td><td style="padding:4px;">' + (det.alsa ? '<span class="text-success">Available</span>' : '<span class="text-secondary">Not detected</span>') + (det.alsa_devices && det.alsa_devices.length ? ' <span class="text-secondary">(' + escHtml(det.alsa_devices.slice(0,2).join(', ')) + ')</span>' : '') + '</td></tr>';
                if (d.fallback_media) {
                    var fm = d.fallback_media;
                    html += '<tr><td style="padding:4px;"><b>Fallback media:</b></td><td style="padding:4px;">' + escHtml(fm.type + ': ' + fm.media) + (fm.path ? ' <span class="text-success">(' + escHtml(fm.path) + ')</span>' : ' <span class="text-danger">(not found)</span>') + '</td></tr>';
                }
                if (d.background_status) html += '<tr><td style="padding:4px;"><b>BackgroundMusic:</b></td><td style="padding:4px;"><span class="text-success">Plugin responding</span></td></tr>';
                if (d.afterhours_status) html += '<tr><td style="padding:4px;"><b>AfterHours:</b></td><td style="padding:4px;"><span class="text-success">Plugin responding</span></td></tr>';
                html += '<tr><td style="padding:4px;"><b>Stream URL:</b></td><td style="padding:4px;"><code>api/plugin/fpp-ListenLive/stream</code> <a href="api/plugin/fpp-ListenLive/stream" target="_blank" style="margin-left:8px;">Open directly</a></td></tr>';
                if (!det.ffmpeg) {
                    html += '<tr><td colspan="2" style="padding:8px;"><span class="text-warning">FFmpeg is required for live capture. Install via FPP OS or <code>sudo apt install ffmpeg</code>. File-sync fallback will be used until then.</span></td></tr>';
                }
                if (d.fpp_reachable === false) {
                    html += '<tr><td colspan="2" style="padding:8px;"><span class="text-warning">FPPD not reachable — live Now Playing unavailable, but live capture and file-sync may still work.</span></td></tr>';
                }
                html += '</table>';
                $('#ll_stream_info').html(html);
            },
            error: function() {
                $('#ll_stream_info').html('<span class="text-danger">Could not load diagnostics</span>');
            }
        });
    }
};

function escHtml(s) { if (s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

$(document).ready(function() { llPlayer.init(); });
</script>

<?php include __DIR__ . '/footer.inc'; ?>
