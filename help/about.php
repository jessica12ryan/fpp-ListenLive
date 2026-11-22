<h3>Listen Live — About</h3>
<p>Monitor your show's <b>live audio</b> directly in the FPP web UI, frame-locked to FPP's clock when MultiSync is enabled.</p>

<h4>Features</h4>
<ul>
    <li>Web Audio <b>Exact frame sync</b>, on by default on first visit: FPP's native MultiSync clock is anchored with a monotonic RTT measurement and short raw-PCM chunks are scheduled sample-accurately.</li>
    <li>~0.5 s audible latency with a deeper prefetch that never delays playback; live drift, RTT, and clock source are shown in <code>#ll_exact_status</code> on the Listen page.</li>
    <li>PHP status-poll fallback when the native sync library or MultiSync is unavailable.</li>
    <li>Automatic fallback after repeated chunk failures to the raw <code>/stream</code> (<code>audio/mpeg</code>), clearly labeled <b>best-effort / NOT frame-exact</b>.</li>
    <li>Live capture via PipeWire/PulseAudio monitor or ALSA using FFmpeg → MP3 (<code>libmp3lame</code>); File Sync capture source also available.</li>
    <li>Auto-play with FPP, one-cutover track changes, and clean idle stop when a playlist ends with no background music.</li>
    <li>Status: playlist, sequence, song, and elapsed time from <code>/api/fppd/status</code>, polled every 1 s on the Listen page (exact clock at 4 Hz).</li>
    <li>Volume is remembered per browser in <code>localStorage</code>; mute is never persisted — each page load starts unmuted.</li>
    <li>Works with FPP 8.x, 9.x, 10.x, and 11.x.</li>
</ul>

<h4>How It Works</h4>
<ol>
    <li>The Listen page reads <code>api/plugin/fpp-ListenLive/sync</code>, a wrapper around the native MultiSync clock (or the PHP status-poll fallback).</li>
    <li>It fetches multi-second raw PCM blocks from <code>api/plugin/fpp-ListenLive/media?file=...&amp;seek=...&amp;duration=4&amp;format=pcm</code>, slices them into 0.5 s AudioBuffers, and schedules them against the master clock.</li>
    <li>If block fetch/decode fails repeatedly, playback switches to <code>api/plugin/fpp-ListenLive/stream</code> — file-sync of the current media first, then live capture. Browser buffering makes that path best-effort, not frame-exact.</li>
    <li>Enable MultiSync in FPP Settings → MultiSync for exact timing across players and remotes.</li>
</ol>

<h4>Privacy</h4>
<p>Audio is captured or read on the FPP host and streamed to your browser on the LAN. The browser never requests camera or microphone permission, and no camera is used. Live capture uses a PipeWire/PulseAudio monitor, an ALSA loopback, or the capture device selected in Config — on hardware with a microphone attached, a fallback device may be a mic input. Captured audio is streamed only to browsers on your LAN and is never written to disk. Nothing is sent off-box, and there is no telemetry, advertising, or donations. Your browser stores volume and Exact-sync preferences; mute is never saved.</p>

<h4>Links</h4>
<ul>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive" target="_blank">GitHub Repository</a> — source, README, and releases.</li>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive/issues" target="_blank">Issue Tracker</a> — bugs and feature requests.</li>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive/blob/main/README.md" target="_blank">README &amp; Installation Guide</a> — full install and troubleshooting.</li>
</ul>

<h4>Plugin Info</h4>
<p><b>Name:</b> Listen Live Plugin for FPP<br><b>Author:</b> jessica12ryan<br><b>License:</b> MIT — Falcon Christmas FPP is © Falcon Christmas, not affiliated.</p>
<p>Press <b>F1</b> again to close.</p>
