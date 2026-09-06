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
                <div id="ll_timing" class="ll-timing" style="max-width:560px;margin:8px auto 4px auto;text-align:center;display:none;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--bs-secondary-color,#6c757d);margin-bottom:4px;">
                        <span id="ll_time_elapsed">00:00</span>
                        <span id="ll_time_duration">--:--</span>
                    </div>
                    <div style="height:6px;background:var(--bs-secondary-bg,#e9ecef);border-radius:3px;overflow:hidden;">
                        <div id="ll_progress" style="height:100%;width:0%;background:#0d6efd;transition:width 0.3s linear;"></div>
                    </div>
                    <div id="ll_time_remaining" style="font-size:11px;color:var(--bs-secondary-color,#6c757d);margin-top:2px;"></div>
                </div>

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
                <div style="margin:8px auto;max-width:560px;text-align:center;">
                    <label style="font-size:12px;color:var(--bs-secondary-color,#6c757d);"><input type="checkbox" id="ll_mse_toggle" onchange="llMSE.toggle(this.checked)" style="vertical-align:middle;margin-right:4px;"> Try MSE gapless (prototype, less buffering)</label>
                    <span id="ll_mse_status" style="font-size:11px;color:var(--bs-secondary-color,#6c757d);margin-left:8px;"></span>
                </div>
                <script>
                $(function(){
                    var s = llMSE.enabled ? 'MSE supported' : 'MSE not supported — using native';
                    $('#ll_mse_status').text(s + (llMSE.useMSE ? ' • enabled' : ' • native'));
                });
                </script>

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
// Monotonic vs wall split — mirrors PulseMesh PendingInsertMirror discipline
// performance.now() is CLOCK_MONOTONIC; Date.now() jumps on NTP, performance.now() does not
var llClock = {
    monotonicMs: function() {
        if (window.performance && performance.now) return performance.now();
        return Date.now();
    },
    wallMs: function() { return Date.now(); }
};
// MSE prototype for gapless, low-buffer live — falls back to native <audio> if unsupported
var llMSE = {
    enabled: !!(window.MediaSource && window.MediaSource.isTypeSupported && window.MediaSource.isTypeSupported('audio/mpeg')),
    mediaSource: null,
    sourceBuffer: null,
    audio: null,
    fetching: false,
    controller: null,
    useMSE: false,
    pendingUrl: null,
    queue: [],
    queueProcessing: false,
    sourceOpen: false,
    init: function(audioEl) {
        llMSE.audio = audioEl;
        llMSE.queue = [];
        llMSE.queueProcessing = false;
        llMSE.sourceOpen = false;
        llMSE.pendingUrl = null;
        try { var v = localStorage.getItem('fpp-ListenLive-useMSE'); if (v !== null) llMSE.useMSE = v === '1'; } catch(e) {}
        if (llMSE.useMSE && !llMSE.enabled) llMSE.useMSE = false;
        $('#ll_mse_toggle').prop('checked', llMSE.useMSE);
        var mseStatus = $('#ll_mse_status');
        if (mseStatus.length) mseStatus.text(llMSE.enabled ? (llMSE.useMSE ? 'MSE • enabled' : 'MSE supported — native') : 'MSE not supported — using native');
        if (!llMSE.useMSE || !llMSE.enabled) return false;
        try {
            llMSE.mediaSource = new MediaSource();
            audioEl.src = URL.createObjectURL(llMSE.mediaSource);
            llMSE.mediaSource.addEventListener('sourceopen', function() {
                llMSE.sourceOpen = true;
                try {
                    llMSE.sourceBuffer = llMSE.mediaSource.addSourceBuffer('audio/mpeg');
                    llMSE.sourceBuffer.mode = 'sequence';
                    llMSE.sourceBuffer.addEventListener('updateend', function() {
                        llMSE.queueProcessing = false;
                        // trim if buffer grew beyond 30s — must happen when not updating
                        try {
                            if (llMSE.audio.buffered.length > 0) {
                                var start = llMSE.audio.buffered.start(0);
                                var end = llMSE.audio.buffered.end(llMSE.audio.buffered.length - 1);
                                if (end - start > 30 && llMSE.sourceBuffer && !llMSE.sourceBuffer.updating) {
                                    // keep last 20s
                                    llMSE.sourceBuffer.remove(start, end - 20);
                                    llMSE.queueProcessing = true; // remove is an update
                                    return;
                                }
                            }
                        } catch(e) {}
                        // process next queued chunk
                        llMSE.processQueue();
                        if (llMSE.audio.paused && llMSE.audio.readyState >= 2) {
                            var p = llMSE.audio.play();
                            if (p && p.catch) p.catch(function(){});
                        }
                    });
                    // if a fetch was queued before sourceopen, start it now
                    if (llMSE.pendingUrl) {
                        var u = llMSE.pendingUrl; llMSE.pendingUrl = null;
                        llMSE.fetchAndAppend(u);
                    }
                } catch(e) { console.warn('MSE SourceBuffer failed', e); llMSE.fallbackToNative(); }
            });
            llMSE.mediaSource.addEventListener('sourceended', function(){ llMSE.sourceOpen = false; });
            llMSE.mediaSource.addEventListener('error', function(e){ console.warn('MSE MediaSource error', e); });
            return true;
        } catch(e) { console.warn('MSE init failed', e); llMSE.fallbackToNative(); return false; }
    },
    processQueue: function() {
        if (!llMSE.sourceBuffer || llMSE.sourceBuffer.updating || llMSE.queue.length === 0) return;
        llMSE.queueProcessing = true;
        var chunk = llMSE.queue.shift();
        try {
            llMSE.sourceBuffer.appendBuffer(chunk);
        } catch(e) {
            console.warn('appendBuffer failed', e);
            llMSE.queueProcessing = false;
            // re-queue and retry shortly
            llMSE.queue.unshift(chunk);
            setTimeout(llMSE.processQueue, 50);
        }
    },
    enqueue: function(chunk) {
        llMSE.queue.push(chunk);
        if (!llMSE.queueProcessing && llMSE.sourceBuffer && !llMSE.sourceBuffer.updating) {
            llMSE.processQueue();
        }
    },
    fallbackToNative: function() {
        llMSE.useMSE = false;
        llMSE.pendingUrl = null;
        llMSE.queue = [];
        llMSE.queueProcessing = false;
        llMSE.sourceOpen = false;
        try { localStorage.setItem('fpp-ListenLive-useMSE', '0'); } catch(e) {}
        $('#ll_mse_toggle').prop('checked', false);
        var mseStatus = $('#ll_mse_status');
        if (mseStatus.length) mseStatus.text(llMSE.enabled ? 'MSE supported — native' : 'MSE not supported — using native');
        if (llMSE.mediaSource) { try { URL.revokeObjectURL(llMSE.audio.src); } catch(e) {} }
        llMSE.mediaSource = null; llMSE.sourceBuffer = null;
        llMSE.stop();
        if (llPlayer.isPlaying) {
            llPlayer.audio.pause();
            llPlayer.audio.removeAttribute('src');
            llPlayer.audio.load();
            llPlayer.audio.src = llPlayer.buildUrl();
            llPlayer.audio.load();
            var p = llPlayer.audio.play(); if (p && p.catch) p.catch(function(e){ console.warn('fallback play failed', e); });
        }
        $('#ll_status_text').html('<span class="text-warning">MSE failed — fell back to native</span>');
    },
    toggle: function(enabled) {
        llMSE.useMSE = !!enabled;
        try { localStorage.setItem('fpp-ListenLive-useMSE', llMSE.useMSE ? '1' : '0'); } catch(e) {}
        location.reload();
    },
    fetchAndAppend: function(url) {
        if (!llMSE.useMSE) return false;
        // if sourceBuffer not ready yet, queue the URL until sourceopen
        if (!llMSE.sourceBuffer) {
            if (!llMSE.sourceOpen) {
                llMSE.pendingUrl = url;
                console.log('MSE queued fetch until sourceopen', url);
                return true;
            }
            return false;
        }
        if (llMSE.fetching) { try { llMSE.controller.abort(); } catch(e) {} llMSE.queue = []; }
        llMSE.fetching = true;
        llMSE.controller = new AbortController();
        console.log('MSE fetching', url);
        fetch(url, {signal: llMSE.controller.signal, cache: 'no-store'}).then(function(resp) {
            if (!resp.ok) {
                // 503 idle is expected when nothing playing — not a live-capture failure
                if (resp.status === 503) {
                    console.log('MSE 503 idle — will retry when playing');
                    llMSE.fetching = false;
                    if (llPlayer.isPlaying && llMSE.useMSE) {
                        setTimeout(function(){ if (llPlayer.isPlaying) llMSE.fetchAndAppend(llPlayer.buildUrl()); }, 1500);
                    }
                    return;
                }
                throw new Error('fetch failed ' + resp.status);
            }
            if (!resp.body) throw new Error('fetch failed no body');
            var reader = resp.body.getReader();
            var firstChunk = true;
            function pump() {
                return reader.read().then(function(result) {
                    if (result.done) {
                        llMSE.fetching = false;
                        console.log('MSE fetch done');
                        if (llPlayer.isPlaying && llMSE.useMSE && llMSE.mediaSource && llMSE.mediaSource.readyState === 'open') {
                            setTimeout(function(){
                                if (llPlayer.isPlaying && !llMSE.fetching && llMSE.useMSE) {
                                    console.log('MSE auto-fetch next track');
                                    llMSE.fetchAndAppend(llPlayer.buildUrl());
                                }
                            }, 300);
                        }
                        return;
                    }
                    var chunk = result.value;
                    // Strip ID3v2 if present on first chunk — MediaSource mp3 demuxer rejects ID3
                    if (firstChunk && chunk && chunk.length >= 10 && chunk[0]===0x49 && chunk[1]===0x44 && chunk[2]===0x33) {
                        var size = ((chunk[6] & 0x7F) << 21) | ((chunk[7] & 0x7F) << 14) | ((chunk[8] & 0x7F) << 7) | (chunk[9] & 0x7F);
                        var total = 10 + size;
                        console.log('MSE stripping ID3v2', total, 'bytes');
                        if (chunk.length > total) chunk = chunk.slice(total);
                        else { firstChunk = false; return pump(); } // ID3 spans chunks — drop this chunk
                    }
                    firstChunk = false;
                    // Detect JSON error payload (503 idle returned as json but resp.ok true edge case)
                    if (chunk && chunk.length < 500) {
                        try {
                            var txt = new TextDecoder().decode(chunk.slice(0, 200));
                            if (txt.trim().startsWith('{') && txt.indexOf('\"success\"') !== -1 && txt.indexOf('Stream unavailable') !== -1) {
                                console.log('MSE got JSON idle payload instead of mp3 — retrying');
                                llMSE.fetching = false;
                                try { llMSE.controller.abort(); } catch(e) {}
                                setTimeout(function(){ if (llPlayer.isPlaying) llMSE.fetchAndAppend(llPlayer.buildUrl()); }, 1500);
                                return;
                            }
                        } catch(e) {}
                    }
                    llMSE.enqueue(chunk);
                    if (llMSE.audio.paused && llMSE.audio.readyState >= 1 && llMSE.sourceBuffer && !llMSE.sourceBuffer.updating) {
                        var p = llMSE.audio.play();
                        if (p && p.catch) p.catch(function(){});
                    }
                    return pump();
                });
            }
            return pump();
        }).catch(function(e) {
            if (e.name === 'AbortError') { console.log('MSE fetch aborted'); }
            else { console.warn('MSE fetch error', e); $('#ll_status_text').html('<span class="text-warning">MSE fetch error: '+escHtml(e.message)+' — retrying</span>'); }
            llMSE.fetching = false;
            if (llPlayer.isPlaying && llMSE.useMSE) {
                setTimeout(function(){ if (llPlayer.isPlaying) llMSE.fetchAndAppend(llPlayer.buildUrl()); }, 800);
            }
        });
        return true;
    },
    stop: function() {
        llMSE.fetching = false;
        llMSE.pendingUrl = null;
        llMSE.queue = [];
        llMSE.queueProcessing = false;
        if (llMSE.controller) { try { llMSE.controller.abort(); } catch(e) {} llMSE.controller = null; }
        if (llMSE.sourceBuffer) {
            try { if (llMSE.sourceBuffer.updating) llMSE.sourceBuffer.abort(); } catch(e) {}
        }
    }
};

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
    initialConnect: false,
    initialConnectTimer: null,
    streamUrl: 'api/plugin/fpp-ListenLive/stream',
    // Deadline-at-read: monotonic timestamp of last media change for confidence
    lastMediaAnnouncedAtMono: null,
    lastElapsedHalf: null,
    lastSendErrorCount: 0,
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
        // Init MSE prototype (gapless, low-buffer) — falls back to native if unsupported
        try { llMSE.init(llPlayer.audio); } catch(e) { console.warn('MSE init error', e); }
        llPlayer.audio.addEventListener('playing', function() {
            llPlayer.isPlaying = true;
            llPlayer.reconnectAttempts = 0;
            llPlayer.trackChangePending = false;
            llPlayer.initialConnect = false;
            clearTimeout(llPlayer.trackChangeTimeout);
            clearTimeout(llPlayer.stalledTimer);
            clearTimeout(llPlayer.waitingTimer);
            // Reset sync tracking — will be set on next status poll with current elapsed (monotonic)
            llPlayer.streamStartElapsed = null;
            llPlayer.streamStartTime = llClock.monotonicMs();
            llPlayer.driftChecks = 0;
            $('#ll_badge').removeClass('ll-badge-idle ll-badge-warn').addClass('ll-badge-live').text('● LIVE');
            $('#ll_wave').removeClass('paused');
            $('#ll_btn_play').val('❚❚ Pause');
            $('#ll_btn_play').attr('onclick', 'llPlayer.pause();');
            $('#ll_status_text').html('<span class="text-success">● Streaming live audio</span>');
        });
        llPlayer.audio.addEventListener('pause', function() {
            // Only mark as paused if not ended and not seeking reconnect
            if (!llPlayer.audio.ended) {
                llPlayer.isPlaying = false;
                llPlayer.trackChangePending = false;
                clearTimeout(llPlayer.trackChangeTimeout);
                clearTimeout(llPlayer.stalledTimer);
                clearTimeout(llPlayer.waitingTimer);
                $('#ll_timing').hide();
                if ('mediaSession' in navigator) { try { navigator.mediaSession.metadata = null; } catch(e) {} }
                $('#ll_badge').removeClass('ll-badge-live').addClass('ll-badge-idle').text('Paused');
                $('#ll_wave').addClass('paused');
                $('#ll_btn_play').val('▶ Play');
                $('#ll_btn_play').attr('onclick', 'llPlayer.play();');
            }
        });
        llPlayer.audio.addEventListener('ended', function() {
            // With server-side seamless file-sync, ended should rarely fire (server concatenates)
            // If it does (file-sync without seamless or live gap), just update display, don't auto-reconnect immediately
            // Next status poll will show new track; server will already be streaming it if seamless
            if (llPlayer.isPlaying) {
                $('#ll_status_text').html('<span class="text-secondary">Track ended — waiting for next...</span>');
                // Don't auto-reconnect here for seamless server — let status poll handle display
                // Only reconnect if still ended after 2s and no new track (fallback)
                setTimeout(function(){
                    if (llPlayer.isPlaying && llPlayer.audio.ended) {
                        llPlayer.reconnect();
                    }
                }, 2000);
            }
        });
        llPlayer.audio.addEventListener('suspend', function() {
            if (llPlayer.isPlaying && !llPlayer.trackChangePending && !llPlayer.initialConnect) {
                // Browser suspended download (e.g. tab throttled) — try to resume
                setTimeout(function(){
                    if (llPlayer.isPlaying && llPlayer.audio.paused && !llPlayer.audio.ended) {
                        var p = llPlayer.audio.play();
                        if (p && p.catch) p.catch(function(){});
                    }
                }, 1000);
            }
        });
        llPlayer.audio.addEventListener('error', function() {
            var err = llPlayer.audio.error;
            var code = err ? err.code : 0;
            var msg = 'Stream error (code ' + code + ')';
            if (code === 4) msg += ' — source returned no audio or unsupported format';
            if (code === 2) msg += ' — network error';
            if (code === 3) msg += ' — decoding failed';
            // MSE path has its own fetch/append error handling — don't double-report as live capture
            if (llMSE.useMSE) {
                console.warn('audio.error in MSE mode', code, err);
                $('#ll_badge').removeClass('ll-badge-live ll-badge-idle').addClass('ll-badge-warn').text('MSE Error');
                $('#ll_status_text').html('<span class="text-warning">' + escHtml(msg) + ' (MSE) — trying native fallback…</span>');
                // Give MSE a moment, then fallback if still failing
                setTimeout(function(){ if (llMSE.useMSE && llPlayer.isPlaying) llMSE.fallbackToNative(); }, 1200);
                return;
            }
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
                    // Live capture only — no file fallback per user request
                    if (!d.detection || !d.detection.ffmpeg) {
                        hint = ' — ffmpeg missing';
                    } else if (!d.detection.pipewire && !d.detection.pulse && !d.detection.alsa) {
                        hint = ' — no capture devices detected. Check Diagnostics and FPP Audio settings (PipeWire/Pulse).';
                    } else if (d.detection && !d.detection.liveAvailable) {
                        hint = ' — live capture not available. Ensure FPP audio is playing and PipeWire/Pulse monitor is accessible.';
                    } else {
                        hint = ' — live capture failed, check logs for ffmpeg stderr';
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
            if (llPlayer.trackChangePending || llPlayer.initialConnect) return;
            clearTimeout(llPlayer.stalledTimer);
            var isLongPlayStalled = llPlayer.audio.currentTime > 50;
            llPlayer.stalledTimer = setTimeout(function(){
                if (llPlayer.audio.readyState < 2 && llPlayer.isPlaying && !llPlayer.audio.paused && !llPlayer.trackChangePending && !llPlayer.initialConnect) {
                    var played = llPlayer.audio.currentTime;
                    var isLongPlay = played > 50;
                    $('#ll_status_text').html('<span class="text-warning">Buffering' + (isLongPlay ? ' (long play)': '') + '...</span>');
                    clearTimeout(llPlayer.stalledTimer);
                    llPlayer.stalledTimer = setTimeout(function(){
                        if (llPlayer.audio.readyState < 2 && llPlayer.isPlaying && !llPlayer.trackChangePending) {
                            $('#ll_status_text').html('<span class="text-warning">Buffering timeout — re-syncing...</span>');
                            llPlayer.reconnect();
                        }
                    }, isLongPlay ? 2000 : 4000);
                }
            }, isLongPlayStalled ? 800 : 1500);
        });
        llPlayer.audio.addEventListener('waiting', function() {
            if (llPlayer.trackChangePending || llPlayer.initialConnect) return;
            clearTimeout(llPlayer.waitingTimer);
            llPlayer.waitingTimer = setTimeout(function(){
                if (llPlayer.audio.readyState < 3 && llPlayer.isPlaying && !llPlayer.audio.paused && !llPlayer.trackChangePending && !llPlayer.initialConnect) {
                    $('#ll_status_text').html('<span class="text-warning">Buffering...</span>');
                }
            }, 1500);
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
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && llPlayer.isPlaying && llPlayer.audio.paused && !llPlayer.audio.ended) {
                var p = llPlayer.audio.play();
                if (p && p.catch) p.catch(function(){});
            }
        });
        llPlayer.refreshStatus();
        setInterval(llPlayer.refreshStatus, 500);
        llPlayer.refreshDiagnostics();
    },

    play: function() {
        var a = llPlayer.audio;
        // MSE path: fetch via MediaSource instead of setting src directly
        if (llMSE.useMSE) {
            // Ensure MediaSource is ready; fetchAndAppend will queue if still opening
            var ok = llMSE.fetchAndAppend(llPlayer.buildUrl());
            if (ok || llMSE.pendingUrl) {
                llPlayer.initialConnect = true;
                clearTimeout(llPlayer.initialConnectTimer);
                llPlayer.initialConnectTimer = setTimeout(function(){ llPlayer.initialConnect = false; }, 5000);
                llPlayer.isPlaying = true;
                $('#ll_status_text').html('<span class="text-success">Connecting (MSE)...</span>');
                // Trigger play after a bit of buffering — also handled in updateend
                setTimeout(function(){
                    if (llMSE.audio && llMSE.audio.readyState >= 1) {
                        var p2 = llMSE.audio.play();
                        if (p2 && p2.catch) p2.catch(function(e){
                            $('#ll_status_text').html('<span class="text-danger">Playback blocked: ' + e.message + ' — click Play again.</span>');
                        });
                    }
                }, 600);
                return;
            }
            // if MSE failed to queue, fall through to native
            console.warn('MSE fetch queue failed, falling through to native');
        }
        if (!a.src || a.src === window.location.href) {
            a.src = llPlayer.buildUrl();
            a.load();
        }
        llPlayer.initialConnect = true;
        clearTimeout(llPlayer.initialConnectTimer);
        llPlayer.initialConnectTimer = setTimeout(function(){ llPlayer.initialConnect = false; }, 5000);
        var p = a.play();
        if (p && p.catch) {
            p.catch(function(e) {
                llPlayer.initialConnect = false;
                $('#ll_status_text').html('<span class="text-danger">Playback blocked: ' + e.message + ' — click Play again.</span>');
            });
        }
        llPlayer.isPlaying = true;
        $('#ll_status_text').html('<span class="text-success">Connecting...</span>');
    },

    pause: function() {
        llMSE.stop();
        llPlayer.audio.pause();
        llPlayer.isPlaying = false;
        llPlayer.trackChangePending = false;
        clearTimeout(llPlayer.trackChangeTimeout);
        clearTimeout(llPlayer.stalledTimer);
        clearTimeout(llPlayer.waitingTimer);
    },

    stop: function() {
        llMSE.stop();
        llPlayer.audio.pause();
        try { llPlayer.audio.removeAttribute('src'); } catch(e) {}
        try { llPlayer.audio.load(); } catch(e) {}
        // If MSE was used, revoke and re-init on next play
        if (llMSE.useMSE && llMSE.mediaSource) {
            try { URL.revokeObjectURL(llPlayer.audio.src); } catch(e) {}
            llMSE.mediaSource = null; llMSE.sourceBuffer = null;
        }
        llPlayer.isPlaying = false;
        llPlayer.trackChangePending = false;
        clearTimeout(llPlayer.trackChangeTimeout);
        clearTimeout(llPlayer.stalledTimer);
        clearTimeout(llPlayer.waitingTimer);
        $('#ll_timing').hide();
        if ('mediaSession' in navigator) { try { navigator.mediaSession.metadata = null; } catch(e) {} }
        $('#ll_badge').removeClass('ll-badge-live ll-badge-warn').addClass('ll-badge-idle').text('Stopped');
        $('#ll_wave').addClass('paused');
        $('#ll_status_text').html('<span class="text-secondary">Stopped</span>');
        $('#ll_btn_play').val('▶ Play');
        $('#ll_btn_play').attr('onclick', 'llPlayer.play();');
    },

    reconnect: function() {
        if (llMSE.useMSE) {
            llMSE.stop();
            // For MSE, just fetch new URL and append (gapless, no src reset)
            llMSE.fetchAndAppend(llPlayer.buildUrl());
            $('#ll_status_text').html('<span class="text-warning">Re-syncing (MSE)...</span>');
            llPlayer.isPlaying = true;
            return;
        }
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

    formatTime: function(sec) {
        sec = Math.max(0, Math.floor(sec));
        var m = Math.floor(sec / 60), s = sec % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    },
    updateTiming: function(elapsed, duration, media) {
        if (!llPlayer.isPlaying) {
            $('#ll_timing').hide();
            return;
        }
        var el = elapsed || 0, du = duration || 0;
        $('#ll_time_elapsed').text(llPlayer.formatTime(el));
        if (du > 0) {
            $('#ll_time_duration').text(llPlayer.formatTime(du));
            $('#ll_time_remaining').text('-' + llPlayer.formatTime(Math.max(0, du - el)) + ' remaining');
            $('#ll_progress').css('width', Math.min(100, (el/du)*100) + '%');
        } else {
            $('#ll_time_duration').text(du ? llPlayer.formatTime(du) : 'Live');
            $('#ll_time_remaining').text(du ? '' : 'Live • chunked');
            $('#ll_progress').css('width', el > 0 ? '100%' : '0%');
        }
        $('#ll_timing').show();
        // Media Session for lock-screen
        if ('mediaSession' in navigator && media) {
            try {
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: media.split('/').pop().split('.')[0] || 'Listen Live',
                    artist: 'FPP • ' + (isBackgroundPlaying ? 'Background' : 'Show'),
                    album: 'FPP Listen Live'
                });
                if (du > 0 && 'setPositionState' in navigator.mediaSession) {
                    navigator.mediaSession.setPositionState({duration: du, playbackRate: 1, position: Math.min(el, du)});
                }
            } catch(e) {}
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
                var isBackgroundPlaying = !!(d.background_status && d.background_status.backgroundMusicRunning) || !!(d.afterhours_status && d.afterhours_status.status);

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
                // Now playing — also check background/after-hours when FPP idle for display only
                // (stream is live capture only, but UI should still show background track when FPP idle)
                var rawPlaylist = s.current_playlist;
                var playlistStr = '';
                if (typeof rawPlaylist === 'string') playlistStr = rawPlaylist;
                else if (rawPlaylist && typeof rawPlaylist === 'object' && rawPlaylist.playlist) playlistStr = rawPlaylist.playlist;
                var media = s.current_song || s.current_sequence || playlistStr || '';
                if (typeof media === 'object') {
                    if (media.playlist || media.count !== "0") media = JSON.stringify(media);
                    else media = '';
                }
                // Background/after-hours for UI badge only (stream is live capture, not file)
                // isBackgroundPlaying already set from background_status/afterhours_status above
                if (!media || media === 'false' || media === '') {
                    $('#ll_nowplaying').html('<span class="text-secondary">Nothing playing — FPP is idle</span>');
                    $('#ll_elapsed').text('—');
                    $('#ll_timing').hide();
                } else {
                    var elapsed = s.seconds_elapsed || s.time_elapsed || np.seconds_elapsed || 0;
                    var remaining = s.seconds_remaining || s.time_remaining || '';
                    var durationForBar = 0;
                    // For background/after-hours, use direct background status for display
                    if (isBackgroundPlaying) {
                        if (d.background_status && typeof d.background_status.trackElapsed === 'number') {
                            elapsed = d.background_status.trackElapsed;
                            if (typeof d.background_status.trackDuration === 'number' && d.background_status.trackDuration > 0) {
                                durationForBar = d.background_status.trackDuration;
                                remaining = Math.max(0, durationForBar - elapsed);
                            }
                        } else if (d.fallback_media && typeof d.fallback_media.elapsed === 'number' && d.fallback_media.elapsed > 0) {
                            elapsed = d.fallback_media.elapsed;
                            if (typeof d.fallback_media.duration === 'number' && d.fallback_media.duration > 0) {
                                durationForBar = d.fallback_media.duration;
                                remaining = Math.max(0, durationForBar - elapsed);
                            }
                        }
                    } else {
                        // For FPP, try to get duration from fallback or status
                        if (typeof s.seconds_remaining === 'number' && typeof s.seconds_elapsed === 'number') {
                            durationForBar = s.seconds_elapsed + s.seconds_remaining;
                        }
                    }
                    $('#ll_nowplaying').text(media);
                    $('#ll_elapsed').text((elapsed || '0') + (remaining ? ' / ' + remaining : ''));
                    // Update custom timing bar + Media Session only when playing
                    // Use audio.currentTime for live-accurate elapsed when available, fallback to status
                    var displayElapsed = elapsed;
                    var displayDuration = durationForBar;
                    if (llPlayer.isPlaying && llPlayer.audio && !llPlayer.audio.paused && llPlayer.audio.readyState >= 2 && llPlayer.streamStartElapsed !== null) {
                        // For file-sync, audio.currentTime is time since stream start (seekPos), so real elapsed = streamStartElapsed + currentTime
                        // For live, audio.currentTime is just time since play, but status elapsed is more accurate for display
                        // Use status elapsed directly, but correct for 1.3s seek offset
                        if (isBackgroundPlaying || s.current_song) {
                            // File-sync: status elapsed is authoritative, but account for seek offset
                            // Server seeks to elapsed-1.3, so client hears elapsed-1.3 at start, then progresses with audio clock
                            // For display, just use status elapsed (already multisync-aware)
                            displayElapsed = elapsed;
                        }
                    }
                    if (llPlayer.isPlaying) {
                        var duNum = displayDuration;
                        if (!duNum) {
                            if (isBackgroundPlaying && d.background_status && typeof d.background_status.trackDuration === 'number') duNum = d.background_status.trackDuration;
                            else if (typeof s.seconds_remaining === 'number' && typeof s.seconds_elapsed === 'number') duNum = s.seconds_elapsed + s.seconds_remaining;
                            else if (remaining && !isNaN(parseInt(remaining))) duNum = parseInt(displayElapsed) + parseInt(remaining);
                        }
                        // Half-second dedup — mirrors PulseMesh SendMediaSyncPacket curTS
                        var curHalf = Math.floor((parseFloat(displayElapsed)||0) * 2);
                        var duHalf = duNum ? Math.floor(duNum * 2) : -1;
                        var lastDuHalf = llPlayer.lastDuHalf;
                        if (curHalf !== llPlayer.lastElapsedHalf || duHalf !== lastDuHalf || llPlayer.lastMedia !== currentMediaKey) {
                            llPlayer.updateTiming(parseInt(displayElapsed)||0, duNum, media);
                            llPlayer.lastElapsedHalf = curHalf;
                            llPlayer.lastDuHalf = duHalf;
                        }
                        // Confidence at read time (250ms settle window) — like PendingInsertMirror view(now)
                        var conf = 'exact';
                        if (llPlayer.lastMediaAnnouncedAtMono !== null) {
                            var age = llClock.monotonicMs() - llPlayer.lastMediaAnnouncedAtMono;
                            if (age < 250) conf = 'unresolved';
                        }
                        // Prefer server confidence if fresher (server monotonic vs client mono not comparable, but server knows announce)
                        if (d.timing && d.timing.confidence === 'unresolved') conf = 'unresolved';
                        if (conf === 'unresolved' && llPlayer.isPlaying) {
                            $('#ll_time_remaining').text($('#ll_time_remaining').text() + ' • syncing');
                        }
                    } else {
                        $('#ll_timing').hide();
                        if ('mediaSession' in navigator) {
                            try { navigator.mediaSession.metadata = null; } catch(e) {}
                        }
                    }
                    if (isBackgroundPlaying && !s.current_song && d.background_status) {
                        var bgType = d.background_status ? 'background' : 'afterhours';
                        $('#ll_nowplaying').html(escHtml(media) + ' <span class="ll-badge" style="background:#198754;color:#fff;font-size:11px;">via ' + escHtml(bgType) + '</span>');
                    }
                }
                // Also show background status in playlist/sequence when idle
                var bgPlaylist = (d.background_status && (d.background_status.playlist || d.background_status.backgroundMusicPlaylist || d.background_status.currentTrack)) || '';
                // Handle s.current_playlist being object like {"playlist":"Ghostbusters...","count":"1"} vs string
                var curPlaylistStr = '';
                if (typeof s.current_playlist === 'string') curPlaylistStr = s.current_playlist;
                else if (s.current_playlist && typeof s.current_playlist === 'object' && s.current_playlist.playlist) curPlaylistStr = s.current_playlist.playlist;
                else if (np.current_playlist && typeof np.current_playlist === 'string') curPlaylistStr = np.current_playlist;
                if (!curPlaylistStr && bgPlaylist) {
                    $('#ll_playlist').text(bgPlaylist + ' (BackgroundMusic)');
                } else {
                    $('#ll_playlist').text(curPlaylistStr || (fppdReachable ? '—' : '— (FPPD not reachable)'));
                }
                if (!s.current_sequence && isBackgroundPlaying) {
                    $('#ll_sequence').text('— (background active)');
                } else {
                    $('#ll_sequence').text(s.current_sequence || (fppdReachable ? '—' : '— (FPPD not reachable)'));
                }
                $('#ll_src').text(settings.source || 'auto');
                $('#ll_bitrate').text(settings.bitrate || '128k');

                // Track change — deadline-at-read confidence (mirrors PendingInsertMirror settle)
                var currentMediaKey = (media || '') + '|' + (s.current_playlist || '') + '|' + (isBackgroundPlaying ? 'background' : 'fpp') + '|' + (media || '');
                if (llPlayer.lastMedia !== currentMediaKey && currentMediaKey) {
                    llPlayer.lastMediaAnnouncedAtMono = llClock.monotonicMs();
                }
                // File-sync is disabled per user request (live only), but keep display in sync
                var isFileSync = false;
                if (false && llPlayer.isPlaying && llPlayer.lastMedia && llPlayer.lastMedia !== currentMediaKey && isFileSync) {
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
                                var nm = (nd.fpp_status && (nd.fpp_status.current_song || nd.fpp_status.current_sequence)) || (nd.background_status && nd.background_status.currentTrack) || '';
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

                // Drift correction disabled — live capture is gapless, no file-sync drift
                if (false && llPlayer.isPlaying && isFileSync && !llPlayer.trackChangePending && llPlayer.audio && !llPlayer.audio.paused && llPlayer.audio.readyState >= 2) {
                    var currentFallbackElapsed = 0;
                    if (d.background_status && typeof d.background_status.trackElapsed === 'number') currentFallbackElapsed = d.background_status.trackElapsed;
                    else if (typeof s.seconds_elapsed === 'number') currentFallbackElapsed = s.seconds_elapsed;
                    else if (d.fallback_media && typeof d.fallback_media.elapsed === 'number') currentFallbackElapsed = d.fallback_media.elapsed;
                    if (llPlayer.streamStartElapsed === null && currentFallbackElapsed > 0) {
                        llPlayer.streamStartElapsed = currentFallbackElapsed;
                        llPlayer.streamStartTime = llClock.monotonicMs();
                        llPlayer.driftChecks = 0;
                    } else if (llPlayer.streamStartElapsed !== null && currentFallbackElapsed > 0) {
                        var wallElapsed = (llClock.monotonicMs() - llPlayer.streamStartTime) / 1000;
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
                                llPlayer.streamStartTime = llClock.monotonicMs();
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
                // Fallback media is display-only, not used for streaming (live capture only)
                if (d.fallback_media) {
                    var fm = d.fallback_media;
                    html += '<tr><td style="padding:4px;"><b>Detected media (display only):</b></td><td style="padding:4px;">' + escHtml(fm.type + ': ' + fm.media) + (fm.path ? ' <span class="text-secondary">(' + escHtml(fm.path) + ')</span>' : ' <span class="text-secondary">(file not found — live capture only)</span>') + '</td></tr>';
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
