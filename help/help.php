<h3>Listen Live — Help &amp; Usage Guide</h3>
<p>This is the browsable help index for the plugin (also reachable via the <b>Help</b> tab). Press <b>F1</b> on any plugin page to see page-specific help for that page — the same content appears in the F1 modal.</p>

<h4>What This Plugin Does</h4>
<p><b>Listen Live</b> lets you hear your show's audio directly in the FPP web UI. It captures the FPP's live audio output and streams it to your browser as MP3 via a standard HTML5 player. What you hear is what the audience hears, including sequence audio, playlist media, and any effects mixed by FPP.</p>

<h4>Quick Start</h4>
<ol>
    <li>Install via <b>Content Setup → Plugin Manager</b> (or from the JSON URL).</li>
    <li>Open <b>Content Setup → Listen Live</b> — you'll land on the <b>Listen</b> tab.</li>
    <li>Start a playlist/schedule so audio is playing.</li>
    <li>Click <b>▶ Play</b>. You should hear the stream within 1–3s.</li>
    <li>Adjust <b>Volume</b> with the slider — per-browser, remembered.</li>
</ol>

<h4>Tabs</h4>
<ul>
    <li><b>Listen</b> — Player, timing bar, volume, Exact sync, and Stream Info diagnostics. Press <b>F1</b> for player help.</li>
    <li><b>Status</b> — Live config + FPP playback state and full capture diagnostics. Each value has <b>?</b> help to the right of the field. Press <b>F1</b> for status help.</li>
    <li><b>Config</b> — 7 settings with <b>?</b> icons to the right of each field (FPP Settings style). Press <b>F1</b> for field-by-field help.</li>
    <li><b>Logs</b> — Tail of <code>plugin-fpp-ListenLive.log</code>. Press <b>F1</b> for log help.</li>
    <li><b>Developer</b> (Developer UI Level) — Update/reinstall/uninstall and restart FPPD. Press <b>F1</b> for developer help.</li>
    <li><b>Help</b> — This page.</li>
    <li><b>About</b> — Features, links, and version info. Press <b>F1</b> for about help.</li>
</ul>

<h4>How It Works</h4>
<p>Browser → <code>api/plugin/fpp-ListenLive/stream</code> → PHP builds <code>ffmpeg -f pulse/alsa -i &lt;monitor&gt; -codec:a libmp3lame -b:a 128k -f mp3 -</code> and pipes MP3. Status is polled from <code>/api/fppd/status</code> and the monotonic sync clock at <code>/api/plugin-apis/ListenLive/sync</code>. Fallback streams the current media file with a seek to <code>seconds_elapsed</code>.</p>

<h4>Requirements &amp; Troubleshooting</h4>
<ul>
    <li>FPP 8/9/10, <b>FFmpeg</b> (<code>apt install ffmpeg</code>), and for true live capture PulseAudio (PipeWire) or ALSA with <code>snd-aloop</code>.</li>
    <li><b>No audio / Stream error:</b> Check <b>Status → Diagnostics</b> (FFmpeg, PipeWire, Pulse, ALSA), hit <b>Test Capture</b> in Config, try <b>File Sync</b>, and ensure a playlist is playing.</li>
    <li><b>Latency:</b> ~1–3s is normal. Lower bitrate slightly helps; stay on the same LAN.</li>
    <li><b>Choppy:</b> Lower bitrate to 96k/64k; check CPU (~5–10% on Pi 4).</li>
    <li><b>Logs:</b> <code>tail -20 /home/fpp/media/logs/plugin-fpp-ListenLive.log</code></li>
</ul>

<p>Press <b>F1</b> again to close. Each page's <b>?</b> icons give field-level help; F1 gives page-level help.</p>
