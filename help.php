<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## help.php                                                ##
 * #############################################################
 */
$uiLevel = (int)($settings['uiLevel'] ?? 0);
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color); background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg); color: var(--bs-body-color); border-color: var(--bs-border-color); border-bottom-color: var(--bs-body-bg); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg); }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Listen Live — Help &amp; Usage Guide</legend>
        <div class="p-3">

            <h3>What This Plugin Does</h3>
            <p>
                <b>Listen Live</b> lets you hear your show's audio directly in the FPP web UI. Its primary playback path is
                <b>Web Audio "Exact frame sync"</b>: FPP's MultiSync monotonic clock is extrapolated against a wall-clock
                reference, short timestamped media chunks are fetched and decoded, and each chunk is scheduled
                sample-accurately. Only about 0.6–1.5 seconds is buffered, so playback is gapless with minimal latency.
                Enable <b>MultiSync</b> (FPP Settings → MultiSync) for exact timing across players and remotes.
            </p>
            <p>
                If chunks cannot be fetched or decoded, the player falls back after a few failures to the raw live stream
                (<code>audio/mpeg</code>, a live capture of FPP's audio output). That path is clearly labeled
                <b>best-effort / NOT frame-exact</b>: browser buffering adds unmeasured latency, so it cannot be
                frame-locked to MultiSync.
            </p>

            <hr>

            <h3>Quick Start</h3>
            <ol>
                <li>Install the plugin via <b>Content Setup → Plugin Manager</b> (or from the JSON URL).</li>
                <li>Enable <b>MultiSync</b> under FPP Settings → MultiSync if you want exact sync across players/remotes.</li>
                <li>Open <b>Content Setup → Listen Live</b> — you'll land on the Listen tab with a player.</li>
                <li>Start a playlist / schedule so audio is playing.</li>
                <li>Click <b>▶ Play</b>. Exact frame sync is on by default on your first visit; the status beside the player shows sync state and drift.</li>
                <li>Adjust <b>Volume</b> with the slider. It is remembered per browser in <code>localStorage</code>; <b>mute is never remembered</b> — every page load starts unmuted.</li>
            </ol>

            <hr>

            <h3>Configuration</h3>
            <p>Open the <b>Config</b> tab to tune capture and encoding:</p>
            <ul>
                <li><b>Enable Live Streaming</b> — turn the stream on/off. When off, <code>/stream</code> returns HTTP 503.</li>
                <li><b>Audio Source</b> — <code>Auto</code> (default) tries the FPP PipeWire/PulseAudio monitor first, then ALSA; <code>PulseAudio</code>, <code>PipeWire</code>, or <code>ALSA</code> force a backend; <code>File Sync</code> captures the current media file directly.</li>
                <li><b>Pulse Source / ALSA Device</b> — leave as <code>auto</code>/<code>default</code> unless you know the exact source or device name.</li>
                <li><b>Bitrate / Sample Rate / Channels</b> — defaults <code>128k / 44100 Hz / Stereo</code> balance quality and CPU.</li>
            </ul>
            <p>Click <b>Save Settings</b>, then <b>Test Capture</b> to verify FFmpeg can open the chosen source. The <b>Exact frame sync</b> toggle is on the <b>Listen</b> page next to the player.</p>

            <hr>

            <h3>How It Works</h3>
            <p>
                The Listen page reads <code>api/plugin/fpp-ListenLive/sync</code>, a PHP wrapper around the native
                MultiSync clock (<code>/api/plugin-apis/ListenLive/sync</code>). It extrapolates the reported media
                position against a wall-clock reference, fetches short chunks from
                <code>api/plugin/fpp-ListenLive/media?file=...&amp;seek=...&amp;duration=1.5</code>, decodes them with Web
                Audio, and starts each <code>AudioBufferSourceNode</code> at the sample-accurate scheduled time. The queue
                target is only ~0.6–1.5 s; current drift is shown in <code>#ll_exact_status</code>.
            </p>
            <p>
                If the native sync library or MultiSync is unavailable, a PHP status-poll fallback keeps playback working
                but is not frame-exact. If Web Audio cannot fetch/decode chunks (no media file, decode failures), after a
                few failures the player switches to <code>api/plugin/fpp-ListenLive/stream</code>: FFmpeg captures whatever
                FPP is currently outputting (PipeWire/PulseAudio monitor or ALSA) and streams MP3. The UI labels this
                <b>best-effort / NOT frame-exact</b> because browser buffering adds unmeasured latency.
            </p>

            <hr>

            <h3>Requirements</h3>
            <ul>
                <li>FPP 8.x, 9.x, 10.x, or 11.x.</li>
                <li><b>FFmpeg</b> on the FPP host — declared as a dependency in <code>pluginInfo.json</code>; Plugin Manager installs it on FPP 10+ and the installer handles older releases. If installing manually on an older release, run <code>apt update &amp;&amp; apt install -y ffmpeg</code> as root on the FPP host (no <code>sudo</code> needed when FPP runs the installer).</li>
                <li><b>MultiSync</b> enabled in FPP Settings → MultiSync for exact timing across players/remotes; a PHP status-poll fallback is used otherwise.</li>
                <li>For the raw stream: a capture backend (PipeWire/PulseAudio monitor preferred, or ALSA). <code>File Sync</code> capture is also available.</li>
                <li>A modern browser with Web Audio, on the same LAN as FPP.</li>
            </ul>

            <hr>

            <h3>Troubleshooting</h3>

            <h4>Not frame-exact / drift between players</h4>
            <ul>
                <li>Enable <b>MultiSync</b> in FPP Settings → MultiSync; check <code>#ll_exact_status</code> beside the player.</li>
                <li><code>source: fallback</code> means the PHP status poll is in use rather than the native clock.</li>
            </ul>

            <h4>"best-effort / NOT frame-exact" label</h4>
            <ul>
                <li>Web Audio could not fetch/decode media chunks; the raw live stream is now active. Ensure media is playing and the media file is decodable, then press <b>↻ Reconnect</b>.</li>
            </ul>

            <h4>No audio / Stream error</h4>
            <ul>
                <li>Check <b>Status → Diagnostics</b>: is FFmpeg found? Is a capture source detected?</li>
                <li>Click <b>Test Capture</b> in Config — it probes whether FFmpeg can open the selected source.</li>
                <li>Ensure a playlist is actually <b>playing</b> (FPP Status/Control page). No media = no chunks to schedule.</li>
                <li>Check the plugin log: <code>tail -50 &lt;mediadir&gt;/logs/plugin-fpp-ListenLive.log</code>.</li>
            </ul>

            <h4>Choppy or stuttering</h4>
            <ul>
                <li>Lower the bitrate to <code>96k</code> or <code>64k</code>.</li>
                <li>Check FPP CPU (Status page); FFmpeg encoding adds load on small boards.</li>
            </ul>

            <h4>Stream URL for external players</h4>
            <p>You can open the raw stream in VLC: <code>http://&lt;fpp-host&gt;/api/plugin/fpp-ListenLive/stream</code>. Remember: best-effort monitoring, not frame-locked.</p>

            <h4>Privacy</h4>
            <p>Audio is captured on the FPP host and streamed to your browser on the LAN. Nothing is sent off-box, audio is not stored, and there is no telemetry, advertising, or donations.</p>

            <hr>

            <h3>Links</h3>
            <p>
                <a href="https://github.com/jessica12ryan/fpp-ListenLive" target="_blank">GitHub Repository</a><br>
                <a href="https://github.com/jessica12ryan/fpp-ListenLive/issues" target="_blank">Issue Tracker</a>
            </p>
        </div>
    </fieldset>
</div>

<?php include __DIR__ . '/footer.inc'; ?>
