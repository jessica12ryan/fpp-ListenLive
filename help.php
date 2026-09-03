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
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Listen Live — Help &amp; Usage Guide</legend>
        <div class="p-3">

            <h3>What This Plugin Does</h3>
            <p>
                <b>Listen Live</b> lets you hear your show's audio directly in the FPP web UI — no need to stand outside by the speakers.
                It captures the FPP's live audio output and streams it to your browser as MP3 via a standard HTML5 audio player.
                What you hear is what the audience hears, including sequence audio, playlist media, and any effects mixed by FPP.
            </p>

            <hr>

            <h3>Quick Start</h3>
            <ol>
                <li>Install the plugin via <b>Content Setup → Plugin Manager</b> (or from the JSON URL).</li>
                <li>Open <b>Content Setup → Listen Live</b> — you'll land on the Listen tab with a player.</li>
                <li>Start a playlist / schedule so audio is playing.</li>
                <li>Click <b>▶ Play</b> on the Listen tab. You should hear the live stream within 1–3 seconds.</li>
                <li>Adjust <b>Volume</b> with the slider. The setting is remembered per browser.</li>
            </ol>

            <hr>

            <h3>Configuration</h3>
            <p>Open the <b>Config</b> tab to tune the stream:</p>
            <ul>
                <li><b>Enable Live Streaming</b> — turn the stream on/off. When off, the endpoint returns 503.</li>
                <li><b>Audio Source</b>:
                    <ul>
                        <li><b>Auto</b> (default) — tries PulseAudio monitor first, then ALSA <code>default</code>.</li>
                        <li><b>PulseAudio</b> — uses a PulseAudio monitor source (most accurate).</li>
                        <li><b>ALSA</b> — uses a specific ALSA device.</li>
                        <li><b>File Sync</b> — streams the current media file directly, seeking to the elapsed time for sync.</li>
                    </ul>
                </li>
                <li><b>Pulse Source / ALSA Device</b> — leave as <code>auto</code>/<code>default</code> unless you know the exact device name.</li>
                <li><b>Bitrate / Sample Rate / Channels</b> — <code>128k, 44.1kHz, Stereo</code> matches most shows and keeps latency low.</li>
            </ul>
            <p>Click <b>Save Settings</b> then <b>Test Capture</b> to verify FFmpeg can open the chosen source.</p>

            <hr>

            <h3>How It Works</h3>
            <p>The browser requests <code>api/plugin/fpp-ListenLive/stream</code>. The plugin's PHP handler builds an <code>ffmpeg</code> command that:</p>
            <pre>ffmpeg -f pulse -i &lt;monitor&gt; -ac 2 -ar 44100 -codec:a libmp3lame -b:a 128k -f mp3 -</pre>
            <p>...or the ALSA equivalent, and pipes MP3 continuously with <code>Content-Type: audio/mpeg</code>. The player reconnects automatically if the stream drops.</p>
            <p><b>Fallback:</b> If no capture device works (or FFmpeg is missing), the plugin falls back to File Sync — it proxies <code>/api/fppd/status</code> to find the current media file and streams that file with a byte-offset seek based on <code>seconds_elapsed</code>.</p>

            <hr>

            <h3>Requirements</h3>
            <ul>
                <li>FPP 8.x, 9.x, or 10.x</li>
                <li><b>FFmpeg</b> installed on the FPP host (<code>sudo apt install ffmpeg</code> if missing; the plugin declares it as a dependency so Plugin Manager usually installs it).</li>
                <li>For true live capture: PulseAudio or an ALSA device that supports capture (or <code>snd-aloop</code> loopback).</li>
            </ul>

            <hr>

            <h3>Troubleshooting</h3>

            <h4>No audio / Stream error</h4>
            <ul>
                <li>Check <b>Status → Diagnostics</b>: is FFmpeg found? Is Pulse/ALSA detected?</li>
                <li>Click <b>Test Capture</b> in Config — it probes the selected source for 1 second.</li>
                <li>Try switching Source to <b>File Sync</b> — if that works, the issue is capture, not streaming.</li>
                <li>Ensure a playlist is actually <b>playing</b> (check FPP's Status/Control page). No media = silence.</li>
            </ul>

            <h4>Latency / Delay</h4>
            <p>Expect ~1–3 seconds of delay (MP3 encoding + browser buffering). This is normal. Lower bitrates reduce it slightly. Keep the FPP and browser on the same LAN for best latency.</p>

            <h4>Choppy or stuttering</h4>
            <ul>
                <li>Lower the bitrate to <code>96k</code> or <code>64k</code>.</li>
                <li>Check FPP CPU (Status page) — FFmpeg adds ~5–10% load on a Pi 3/4.</li>
                <li>Try File Sync mode, which has lower overhead.</li>
            </ul>

            <h4>Stream URL for external players</h4>
            <p>You can open the stream in VLC or any audio player: <code>http://&lt;fpp-ip&gt;/api/plugin/fpp-ListenLive/stream</code></p>

            <h4>Logs</h4>
            <pre>tail -20 /home/fpp/media/logs/plugin-fpp-ListenLive.log</pre>

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
