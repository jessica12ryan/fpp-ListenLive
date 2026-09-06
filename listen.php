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
                    <label style="font-size:12px;color:var(--bs-secondary-color,#6c757d);"><input type="checkbox" id="ll_exact_toggle" onchange="llExactAudio.toggleAudio(this.checked);" style="vertical-align:middle;margin-right:4px;"> Exact frame sync (Web Audio, versatile)</label>
                    <span id="ll_exact_status" style="font-size:11px;color:var(--bs-secondary-color,#6c757d);margin-left:8px;"></span>
                    <div id="ll_exact_info" style="font-size:11px;color:#0c5460;background:#d1ecf1;border:1px solid #bee5eb;border-radius:4px;padding:4px 8px;margin-top:6px;display:none;"></div>
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
// Monotonic vs wall split — mirrors PulseMesh PendingInsertMirror discipline
// performance.now() is CLOCK_MONOTONIC; Date.now() jumps on NTP, performance.now() does not
var llClock = {
    monotonicMs: function() {
        if (window.performance && performance.now) return performance.now();
        return Date.now();
    },
    wallMs: function() { return Date.now(); }
};
// Exact frame sync — versatile, monotonic, Web Audio + MultiSync master
// Polls /api/plugin/fpp-ListenLive/sync (C++ monotonic when available, else php fallback + FPP ms)
// and nudges native <audio> playbackRate to stay frame-locked. No blob: CSP needed.
var llExact = {
    useExact: false,
    enabled: true,
    pollTimer: null,
    lastSync: null,
    startWall: null,
    startMono: null,
    startElapsed: null,
    startLatency: 1.3,
    drift: 0,
    corrections: 0,
    init: function() {
        try { var v = localStorage.getItem('fpp-ListenLive-useExact'); if (v !== null) llExact.useExact = v === '1'; } catch(e) {}
        $('#ll_exact_toggle').prop('checked', llExact.useExact);
        var info = $('#ll_exact_info');
        if (llExact.useExact) {
            info.text('Exact sync active — polling frame clock every 100ms, nudging playbackRate to stay locked. Disable for native drift.').show();
            $('#ll_exact_status').text('exact • on');
        } else {
            info.text('Exact sync off — native timing (1.3s seek, 500ms poll). Enable for versatile frame-exact across single/multi FPP.').show();
            setTimeout(function(){ info.fadeOut(2000); }, 4000);
            $('#ll_exact_status').text('exact • off');
        }
    },
    toggle: function(enabled) {
        llExact.useExact = !!enabled;
        try { localStorage.setItem('fpp-ListenLive-useExact', llExact.useExact ? '1' : '0'); } catch(e) {}
        $('#ll_exact_toggle').prop('checked', llExact.useExact);
        if (llExact.useExact) {
            $('#ll_exact_status').text('exact • on');
            $('#ll_exact_info').text('Exact sync active — frame clock polling.').show();
            if (llPlayer.isPlaying) llExact.start();
        } else {
            $('#ll_exact_status').text('exact • off');
            $('#ll_exact_info').text('Exact sync off.').show();
            setTimeout(function(){ $('#ll_exact_info').fadeOut(2000); }, 3000);
            llExact.stop();
            // reset playbackRate
            try { if (llPlayer.audio) llPlayer.audio.playbackRate = 1.0; } catch(e) {}
        }
    },
    start: function() {
        llExact.stop();
        if (!llExact.useExact || !llPlayer.isPlaying) return;
        // Fetch initial sync to anchor startWall/startElapsed (wall, not monotonic — client/server monotonic not comparable)
        $.ajax({url:'api/plugin/fpp-ListenLive/sync', type:'GET', dataType:'json', success:function(d){
            if (d && d.has_media && typeof d.extrapolated_seconds === 'number' && d.extrapolated_seconds >= 0) {
                llExact.startWall = (d.wall_ms || d.server_wall_ms || Date.now());
                llExact.startElapsed = d.extrapolated_seconds;
                llExact.startAudioTime = llPlayer.audio ? llPlayer.audio.currentTime : 0;
                llExact.startLatency = 1.3;
            } else {
                llExact.startWall = Date.now();
                llExact.startElapsed = 0;
                llExact.startAudioTime = 0;
                llExact.startLatency = 1.3;
            }
            llExact.pollTimer = setInterval(llExact.poll, 100);
        }, error:function(){
            llExact.startWall = Date.now();
            llExact.startElapsed = 0;
            llExact.startAudioTime = llPlayer.audio ? llPlayer.audio.currentTime : 0;
            llExact.startLatency = 0;
            llExact.pollTimer = setInterval(llExact.poll, 100);
        }});
    },
    stop: function() {
        if (llExact.pollTimer) { clearInterval(llExact.pollTimer); llExact.pollTimer = null; }
        llExact.lastSync = null;
        llExact.drift = 0;
    },
    poll: function() {
        if (!llExact.useExact || !llPlayer.isPlaying || !llPlayer.audio || llPlayer.audio.paused) return;
        $.ajax({
            url:'api/plugin/fpp-ListenLive/sync',
            type:'GET',
            dataType:'json',
            cache:false,
            success:function(d){
                if (!d || !d.has_media) {
                    // No media — don't correct
                    try { llPlayer.audio.playbackRate = 1.0; } catch(e) {}
                    $('#ll_exact_status').text('exact • idle');
                    return;
                }
                llExact.lastSync = d;
                var nowWall = Date.now();
                var serverWall = d.wall_ms || d.server_wall_ms || nowWall;
                var rttComp = 0;
                var extrapolated = (typeof d.extrapolated_seconds === 'number') ? d.extrapolated_seconds : d.seconds;
                var age = (nowWall - serverWall) / 1000;
                if (age >=0 && age < 1.0) extrapolated += age;
                extrapolated += rttComp;

                var isFileSync = d.media && d.media.indexOf('.mp3') !== -1;
                var expectedAudioTime;
                if (isFileSync && llExact.startElapsed !== null) {
                    // Native gapless is 1.3s behind server (seek), so stay there — don't chase 0
                    expectedAudioTime = (extrapolated - llExact.startElapsed) + (llExact.startAudioTime || 0);
                } else {
                    // Live: just compare wall vs audio progress — keep at 1.0 unless buffering
                    expectedAudioTime = extrapolated;
                    // For live, don't use file-based correction — just keep low latency
                    // So we only correct if drift > 0.5s (buffer bloat)
                    var liveActual = llPlayer.audio.currentTime;
                    var liveDrift = liveActual - extrapolated;
                    if (Math.abs(liveDrift) > 0.5) {
                        console.log('live drift', liveDrift.toFixed(3));
                    }
                    $('#ll_exact_status').text('exact • live '+(d.confidence||'')+' drift '+liveDrift.toFixed(2)+'s');
                    return;
                }

                var actual = llPlayer.audio.currentTime;
                var drift = actual - expectedAudioTime;
                llExact.drift = drift;

                // <250ms target: gentle nudge, not choppy. 40ms dead zone, 50-250ms small nudge, >250ms larger but still <3%
                var absDrift = Math.abs(drift);
                var rate = 1.0;
                // Hysteresis: require 2 consecutive polls beyond threshold before nudging to avoid jitter
                llExact._driftHits = (absDrift > 0.05) ? (llExact._driftHits||0)+1 : 0;
                if (llExact._driftHits >= 2 && absDrift > 0.05) {
                    if (drift > 0) {
                        rate = absDrift > 0.25 ? 0.97 : 0.985;
                    } else {
                        rate = absDrift > 0.25 ? 1.03 : 1.015;
                    }
                    try { llPlayer.audio.playbackRate = rate; } catch(e) {}
                    llExact.corrections++;
                    if (llExact.corrections % 5 === 0) console.log('exact drift',drift.toFixed(3),'→ rate',rate,'media',d.media);
                    $('#ll_exact_status').text('exact • drift '+drift.toFixed(2)+'s → '+rate.toFixed(2)+'x');
                } else if (absDrift <= 0.05) {
                    try { if (llPlayer.audio.playbackRate !== 1.0) llPlayer.audio.playbackRate = 1.0; } catch(e) {}
                    $('#ll_exact_status').text('exact • locked '+drift.toFixed(2)+'s');
                    llExact._driftHits = 0;
                } else {
                    // within hysteresis window, keep current rate
                    $('#ll_exact_status').text('exact • drift '+drift.toFixed(2)+'s');
                }

                // Hard resync only if >1s and corrected 5 times — avoids broken audio from large jumps
                if (absDrift > 1.0 && llExact.corrections > 5) {
                    console.log('exact hard resync', drift);
                    $('#ll_exact_status').text('exact • hard resync');
                    llExact.corrections = 0;
                    llExact.startWall = nowWall;
                    llExact.startMono = nowWall;
                    llExact.startElapsed = extrapolated;
                    llExact.startAudioTime = actual;
                    try { llPlayer.audio.playbackRate = 1.0; } catch(e) {}
                }
            },
            error:function(){}
        });
    }
};

// AudioContext exact gapless — versatile, frame-exact, no blob: CSP
var llExactAudio = {
    ctx: null,
    useAudio: false,
    queue: [],
    nextStart: 0,
    fetching: false,
    lastMedia: null,
    init: function() {
        window.AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!window.AudioContext) {
            $('#ll_exact_status').text('exact • AudioContext not supported — using native');
            return;
        }
        // Default on for exact gapless (versatile, frame-exact) — user can opt-out
        this.useAudio = true;
        try {
            var v = localStorage.getItem('fpp-ListenLive-useExactAudio');
            if (v !== null) this.useAudio = v === '1';
            else {
                // First visit: default on, persist
                localStorage.setItem('fpp-ListenLive-useExactAudio', '1');
                localStorage.setItem('fpp-ListenLive-useExact', '1');
            }
            var old = localStorage.getItem('fpp-ListenLive-useExact');
            if (old === '1' && !localStorage.getItem('fpp-ListenLive-useExactAudio')) {
                this.useAudio = true;
            }
        } catch(e) {}
        // Reflect default in UI
        try { $('#ll_exact_toggle').prop('checked', this.useAudio); } catch(e) {}
        if (this.useAudio) {
            $('#ll_exact_status').text('exact • AudioContext • default on');
            $('#ll_exact_info').text('Exact frame sync default on — AudioContext gapless, multisync master when available (fallback to native when idle).').show();
            setTimeout(function(){ $('#ll_exact_info').fadeOut(4000); }, 4000);
        }
    },
    toggleAudio: function(enabled) {
        this.useAudio = !!enabled;
        try { localStorage.setItem('fpp-ListenLive-useExactAudio', this.useAudio ? '1' : '0'); } catch(e) {}
        try { localStorage.setItem('fpp-ListenLive-useExact', this.useAudio ? '1' : '0'); } catch(e) {}
        $('#ll_exact_toggle').prop('checked', this.useAudio);
        if (this.useAudio) {
            $('#ll_exact_status').text('exact • AudioContext');
            $('#ll_exact_info').text('AudioContext gapless — decoding via Web Audio, scheduled to master clock.').show();
            // When AudioContext is on, disable playbackRate path to avoid double correction
            try { llExact.useExact = false; if (llExact.pollTimer) llExact.stop(); } catch(e) {}
            if (llPlayer.isPlaying) this.start();
        } else {
            $('#ll_exact_status').text('exact • off');
            $('#ll_exact_info').text('AudioContext off — native.').show();
            setTimeout(function(){ $('#ll_exact_info').fadeOut(2000); }, 3000);
            this.stop();
            try { if (llPlayer.audio) { llPlayer.audio.muted = llPlayer.isMuted; llPlayer.audio.playbackRate = 1.0; } } catch(e) {}
        }
    },
    start: function() {
        if (!this.useAudio || !llPlayer.isPlaying) return;
        if (!window.AudioContext) return;
        if (this.ctx) try { this.ctx.close(); } catch(e) {}
        this.ctx = new (window.AudioContext || window.webkitAudioContext)({latencyHint: 'interactive'});
        this.nextStart = this.ctx.currentTime + 0.4;
        this.queue = [];
        this.fetching = false;
        this.lastMedia = null;
        // Keep native audible — exact frames via AudioContext, not muted (versatile)
        try { llPlayer.audio.muted = llPlayer.isMuted; } catch(e) {}
        this.schedule();
    },
    stop: function() {
        this.fetching = false;
        this.queue = [];
        if (this.ctx) { try { this.ctx.close(); } catch(e) {} this.ctx = null; }
        try { llPlayer.audio.muted = llPlayer.isMuted; } catch(e) {}
        // Reset native playbackRate
        try { if (llPlayer.audio) llPlayer.audio.playbackRate = 1.0; } catch(e) {}
    },
    schedule: function() {
        if (!this.useAudio || !llPlayer.isPlaying || !this.ctx) return;
        var self = this;
        if (self.nextStart - self.ctx.currentTime > 3.0) {
            return setTimeout(function(){ self.schedule(); }, 200);
        }
        if (self.fetching) return setTimeout(function(){ self.schedule(); }, 100);
        self.fetching = true;
        $.getJSON('api/plugin/fpp-ListenLive/sync', function(d){
            if (!d || !d.has_media || !d.media) {
                self.fetching = false;
                // Keep already queued audio, just wait for next track — don't stop AudioContext
                // If we have <0.5s left and no media, we'll hear gap, but don't kill ctx
                if (self.nextStart - self.ctx.currentTime < 0.5) {
                    $('#ll_exact_status').text('exact • idle • waiting for next track');
                }
                return setTimeout(function(){ self.schedule(); }, 300);
            }
            var nowWall = Date.now();
            var serverWall = d.wall_ms || d.server_wall_ms || nowWall;
            var master = (typeof d.extrapolated_seconds === 'number' ? d.extrapolated_seconds : d.seconds) + (nowWall - serverWall)/1000;
            // Detect song change — if media changed, don't wait for nextStart, schedule next chunk immediately after current
            var isNewSong = self.lastMedia && self.lastMedia.split('|')[0] !== d.media;
            if (isNewSong) {
                console.log('AudioContext new song', d.media, 'seek', Math.floor(master));
                // Keep nextStart as is for gapless (old file ends, new starts), but clear lastMedia so we fetch from 0
                // Don't reset nextStart to now, keep gapless
            }
            // 5s chunk aligned for low buffering
            var seek = Math.floor(Math.max(0, master) / 5) * 5;
            if (isNewSong) seek = 0;
            var mediaKey = d.media + '|' + seek;
            if (self.lastMedia === mediaKey && !isNewSong) {
                self.fetching = false;
                return setTimeout(function(){ self.schedule(); }, 150);
            }
            self.lastMedia = mediaKey;
            // Fetch 5s chunk for low buffering (was whole file)
            var url = 'api/plugin/fpp-ListenLive/media?file=' + encodeURIComponent(d.media) + '&seek=' + seek + '&duration=5';
            fetch(url).then(function(resp){
                if (!resp.ok) throw new Error('media fetch '+resp.status);
                return resp.arrayBuffer();
            }).then(function(buf){
                if (buf.byteLength < 1024) {
                    try {
                        var txt = new TextDecoder().decode(buf.slice(0,200));
                        if (txt.trim().startsWith('{') && txt.indexOf('success') !== -1) throw new Error('media not found JSON');
                    } catch(e) {}
                }
                return self.ctx.decodeAudioData(buf.slice(0));
            }).then(function(decoded){
                if (!self.ctx || self.ctx.state === 'closed') throw new Error('ctx closed');
                var src = self.ctx.createBufferSource();
                src.buffer = decoded;
                src.connect(self.ctx.destination);
                var when = Math.max(self.nextStart, self.ctx.currentTime + 0.05);
                // If new song and when is far in future (>5s), pull it in to avoid 6s gap
                if (isNewSong && when - self.ctx.currentTime > 3.0) {
                    when = self.ctx.currentTime + 0.1;
                    self.nextStart = when;
                }
                src.start(when);
                self.nextStart = when + decoded.duration;
                self.fetching = false;
                $('#ll_exact_status').text('exact • AudioContext • '+d.media.split('/').pop().substring(0,20)+' • '+(master).toFixed(1)+'s • next '+self.nextStart.toFixed(1));
                setTimeout(function(){ self.schedule(); }, 80);
            }).catch(function(e){
                console.warn('AudioContext decode/fetch failed', d.media, e);
                self.fetching = false;
                // Don't stop — try next seek or next track, keep native muted
                // If decode failed for this seek, try next second
                if (e.message && e.message.indexOf('media not found') !== -1) {
                    self.lastMedia = d.media + '|' + (seek+1);
                }
                setTimeout(function(){ self.schedule(); }, 400);
            });
        }).fail(function(){
            self.fetching = false;
            setTimeout(function(){ self.schedule(); }, 400);
        });
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
        try { llExact.init(); } catch(e) { console.warn('Exact init error', e); }
        try { llExactAudio.init(); } catch(e) { console.warn('ExactAudio init error', e); }
        // Sync AudioContext toggle with Exact
        try { if (llExactAudio.useAudio) { llExact.useExact = true; $('#ll_exact_toggle').prop('checked', true); } } catch(e) {}
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
            try { if (llExact.useExact) llExact.start(); } catch(e) {}
            try { if (llExactAudio.useAudio) llExactAudio.start(); } catch(e) {}
        });
        llPlayer.audio.addEventListener('pause', function() {
            // Only mark as paused if not ended and not seeking reconnect
            if (!llPlayer.audio.ended) {
                llPlayer.isPlaying = false;
                llPlayer.trackChangePending = false;
                clearTimeout(llPlayer.trackChangeTimeout);
                clearTimeout(llPlayer.stalledTimer);
                clearTimeout(llPlayer.waitingTimer);
                try { llExact.stop(); } catch(e) {}
                try { llExactAudio.stop(); } catch(e) {}
                try { if (llPlayer.audio) llPlayer.audio.playbackRate = 1.0; } catch(e) {}
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
            llPlayer.reconnectAttempts = (llPlayer.reconnectAttempts || 0) + 1;
            $('#ll_badge').removeClass('ll-badge-live ll-badge-idle').addClass('ll-badge-warn').text('Error');
            $('#ll_status_text').html('<span class="text-danger">' + escHtml(msg) + ' (attempt ' + llPlayer.reconnectAttempts + '). Checking diagnostics...</span>');
            // Fetch diagnostics to show helpful hint — distinguish idle (no file) from capture failure
            $.ajax({
                url: 'api/plugin/fpp-ListenLive/diagnostics',
                type: 'GET',
                dataType: 'json',
                success: function(d) {
                    var hint = '';
                    var isIdle = !d.fallback_media || (!d.fallback_media.path && !d.fallback_media.streamUrl);
                    if (isIdle) {
                        hint = ' — FPP is idle (no file playing). Start a playlist — stream will be available when media is playing.';
                    } else if (!d.detection || !d.detection.ffmpeg) {
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
        llPlayer.isPlaying = true;
        $('#ll_status_text').html('<span class="text-success">Connecting...</span>');
        // AudioContext exact takes over — mute native and let it schedule
        if (llExactAudio.useAudio) {
            try { a.muted = true; } catch(e) {}
            // Still set native src as fallback, but muted
            if (!a.src || a.src === window.location.href) {
                a.src = llPlayer.buildUrl();
                a.load();
                var p2 = a.play(); if (p2 && p2.catch) p2.catch(function(){});
            }
            llPlayer.initialConnect = true;
            clearTimeout(llPlayer.initialConnectTimer);
            llPlayer.initialConnectTimer = setTimeout(function(){ llPlayer.initialConnect = false; }, 5000);
            return;
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
    },

    pause: function() {
        try { llExactAudio.stop(); } catch(e) {}
        llPlayer.audio.pause();
        llPlayer.isPlaying = false;
        llPlayer.trackChangePending = false;
        clearTimeout(llPlayer.trackChangeTimeout);
        clearTimeout(llPlayer.stalledTimer);
        clearTimeout(llPlayer.waitingTimer);
    },

    stop: function() {
        try { llExact.stop(); } catch(e) {}
        try { llExactAudio.stop(); } catch(e) {}
        try { if (llPlayer.audio) llPlayer.audio.playbackRate = 1.0; } catch(e) {}
        llPlayer.audio.pause();
        try { llPlayer.audio.removeAttribute('src'); } catch(e) {}
        try { llPlayer.audio.load(); } catch(e) {}
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
        try { llExact.stop(); } catch(e) {}
        try { llExactAudio.stop(); } catch(e) {}
        if (llExactAudio.useAudio) {
            $('#ll_status_text').html('<span class="text-warning">Re-syncing (AudioContext)...</span>');
            llPlayer.isPlaying = true;
            setTimeout(function(){ if (llPlayer.isPlaying) llExactAudio.start(); }, 300);
            return;
        }
        llPlayer.audio.pause();
        llPlayer.audio.src = llPlayer.buildUrl();
        llPlayer.audio.load();
        var p = llPlayer.audio.play();
        if (p && p.catch) p.catch(function(){});
        $('#ll_status_text').html('<span class="text-warning">Reconnecting...</span>');
        llPlayer.isPlaying = true;
        try { if (llExact.useExact) setTimeout(function(){ if (llPlayer.isPlaying) llExact.start(); }, 500); } catch(e) {}
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
                var mediaChanged = llPlayer.lastMedia !== currentMediaKey && currentMediaKey;
                if (mediaChanged) {
                    llPlayer.lastMediaAnnouncedAtMono = llClock.monotonicMs();
                    if (llExact.useExact && llPlayer.isPlaying) {
                        // Reset exact anchors on track change — versatile for file vs live
                        llExact.stop();
                        setTimeout(function(){ if (llPlayer.isPlaying) llExact.start(); }, 250);
                    }
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
