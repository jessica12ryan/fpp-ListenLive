<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## about.php                                               ##
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

<div style="margin:0 auto;"> <br />
    <fieldset class="border p-3">
        <legend>About Listen Live Plugin</legend>
        <div class="p-3">
            <div id='credits'>
                <h3 style="margin-top:0;">Listen Live Plugin for FPP</h3>

                <p>
                    Monitor your show's <b>live audio</b> directly in the FPP web UI, frame-locked to FPP's clock when MultiSync is enabled.
                </p>

                <h4>Features</h4>
                <ul>
                    <li>Web Audio <b>Exact frame sync</b>, on by default on first visit: FPP's native MultiSync clock is anchored with a monotonic RTT measurement, and raw PCM blocks are sliced into 0.5 s buffers and scheduled sample-accurately.</li>
                    <li>~0.5 s audible latency with a deeper prefetch that keeps playback gapless; live drift, RTT, and catch-up counters appear beside the player (<code>#ll_exact_status</code>).</li>
                    <li>PHP status-poll fallback when the native sync library or MultiSync is unavailable.</li>
                    <li>Auto-play with FPP, one-cutover track changes, and a clean idle stop when a playlist ends with no background music.</li>
                    <li>Automatic fallback to the MP3 stream (<code>/stream</code>, file-sync first then live capture) after repeated block failures, clearly labeled <b>best-effort / NOT frame-exact</b>.</li>
                    <li>Live capture via PipeWire/PulseAudio monitor or ALSA using FFmpeg → MP3; File Sync capture source also available.</li>
                    <li>Live status: playlist, sequence, song, and elapsed time from <code>/api/fppd/status</code>, polled every 1 s on the Listen page (exact clock at 4 Hz).</li>
                    <li>Volume is stored per browser in <code>localStorage</code>; mute is never persisted — every page load starts unmuted.</li>
                    <li>Works with FPP 8.x, 9.x, 10.x, and 11.x.</li>
                </ul>

                <h4>How It Works</h4>
                <ol>
                    <li>The Listen page reads <code>api/plugin/fpp-ListenLive/sync</code>, which wraps the native MultiSync clock (or the PHP status-poll fallback).</li>
                    <li>It fetches multi-second raw PCM blocks from <code>api/plugin/fpp-ListenLive/media?file=...&amp;seek=...&amp;duration=4&amp;format=pcm</code>, slices them into 0.5 s AudioBuffers, and schedules them against the master clock.</li>
                    <li>If block fetch/decode fails repeatedly, the player switches to <code>api/plugin/fpp-ListenLive/stream</code> — file-sync of the current media first, then live capture. Browser buffering makes this path best-effort, not frame-exact.</li>
                    <li>Enable MultiSync in FPP Settings → MultiSync for exact timing across players and remotes; status and diagnostics are served as JSON via the plugin API.</li>
                </ol>

                <h4>Privacy</h4>
                <p>Audio is captured or read on the FPP host and streamed to your browser on the LAN. No microphone or camera is accessed — the browser never requests those permissions, and live capture reads only FPP's own audio output (monitor/loopback), not a mic input. Nothing is sent off-box, audio is not stored, and there is no telemetry, advertising, or donations. Your browser stores volume and Exact-sync preferences; mute is never saved.</p>

                <h4>Links</h4>
                <p>
                    <a href="https://github.com/jessica12ryan/fpp-ListenLive" target="_blank">GitHub Repository</a><br>
                    <a href="https://github.com/jessica12ryan/fpp-ListenLive/issues" target="_blank">Issue Tracker &amp; Feature Requests</a><br>
                    <a href="https://github.com/jessica12ryan/fpp-ListenLive/blob/main/README.md" target="_blank">README &amp; Installation Guide</a>
                </p>

                <h4>Plugin Info</h4>
                <p>
                    Name: <b>Listen Live Plugin for FPP</b><br>
                    Author: <b>jessica12ryan</b><br>
                    License: <b>MIT</b><br>
                </p>
            </div>
        </div>
    </fieldset>
</div>

<?php include __DIR__ . '/footer.inc'; ?>
