<h3>Listen Live — About</h3>
<p>Stream your show's <b>live audio</b> directly in the FPP web UI — hear exactly what your audience hears without leaving the browser.</p>

<h4>Features</h4>
<ul>
    <li>One-click HTML5 audio player on <b>Content Setup → Listen Live → Listen</b> with volume + mute (remembered per browser).</li>
    <li>Live capture via <b>PulseAudio monitor</b> (PipeWire) or <b>ALSA</b> using FFmpeg → MP3 (<code>libmp3lame</code>).</li>
    <li>Automatic <b>File Sync fallback</b> — streams the current media file in sync when capture is unavailable.</li>
    <li>Live status: current playlist, sequence, song, and elapsed time from <code>/api/fppd/status</code>.</li>
    <li>Auto-reconnect on drop, ~1–3s latency on LAN, multisync-aware timing via the native <code>libfpp-ListenLive.so</code> (<code>/api/plugin-apis/ListenLive/sync</code>).</li>
    <li>Works with FPP 8.x, 9.x, and 10.x.</li>
</ul>

<h4>How It Works</h4>
<ol>
    <li>Browser requests <code>api/plugin/fpp-ListenLive/stream</code>.</li>
    <li>Plugin builds <code>ffmpeg -f pulse/alsa -i &lt;device&gt; -codec:a libmp3lame -b:a 128k -f mp3 -</code> and pipes MP3 with <code>Content-Type: audio/mpeg</code>.</li>
    <li>HTML5 <code>&lt;audio&gt;</code> plays the stream; the <b>Status</b> tab polls <code>/api/fppd/status</code> every 5s and the <b>Listen</b> tab polls the monotonic sync clock.</li>
    <li>If capture fails, the current media file (under <code>/home/fpp/media/music</code>) is streamed with a seek to <code>seconds_elapsed</code>.</li>
</ol>

<h4>Links</h4>
<ul>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive" target="_blank">GitHub Repository</a> — source, README, and releases.</li>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive/issues" target="_blank">Issue Tracker</a> — bugs and feature requests.</li>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive/blob/main/README.md" target="_blank">README &amp; Installation Guide</a> — full install and troubleshooting.</li>
</ul>

<h4>Plugin Info</h4>
<p><b>Name:</b> Listen Live Plugin for FPP<br><b>Author:</b> jessica12ryan<br><b>License:</b> MIT — Falcon Christmas FPP is © Falcon Christmas, not affiliated.</p>
<p>Press <b>F1</b> again to close.</p>
