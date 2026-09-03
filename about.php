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
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;"> <br />
    <fieldset class="border p-3">
        <legend>About Listen Live Plugin</legend>
        <div class="p-3">
            <div id='credits'>
                <h3 style="margin-top:0;">Listen Live Plugin for FPP</h3>

                <p>
                    Stream your show's <b>live audio</b> directly in the FPP web UI — hear exactly what your audience hears without leaving the browser.
                </p>

                <h4>Features</h4>
                <ul>
                    <li>One-click HTML5 audio player embedded in the FPP UI (Content Setup → Listen Live)</li>
                    <li>Live capture via <b>PulseAudio monitor</b> or <b>ALSA</b> using FFmpeg → MP3</li>
                    <li>Automatic <b>File Sync fallback</b> — streams the current media file in sync when capture is unavailable</li>
                    <li>Live status: current playlist, sequence, song, and elapsed time from <code>/api/fppd/status</code></li>
                    <li>Volume slider + mute, remembered per browser</li>
                    <li>Auto-reconnect on drop, ~1–3s latency on LAN</li>
                    <li>Works with FPP 8.x, 9.x, and 10.x</li>
                </ul>

                <h4>How It Works</h4>
                <ol>
                    <li>Browser requests <code>api/plugin/fpp-ListenLive/stream</code></li>
                    <li>Plugin launches <code>ffmpeg -f pulse/alsa -i &lt;device&gt; -codec:a libmp3lame -b:a 128k -f mp3 -</code> and pipes MP3</li>
                    <li>HTML5 &lt;audio&gt; element plays the stream; status is polled from <code>/api/fppd/status</code></li>
                    <li>If capture fails, the current media file (from <code>/home/fpp/media/music</code>) is streamed with seek to <code>seconds_elapsed</code></li>
                </ol>

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
