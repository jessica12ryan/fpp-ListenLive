<h3>Listen Live — About</h3>
<p>Monitor your show's <b>live audio</b> directly in the FPP web UI, frame-locked to FPP's clock when MultiSync is enabled.</p>

<h4>Features</h4>
<ul>
    <li>Web Audio <b>Exact frame sync</b>, on by default on first visit: FPP's native MultiSync clock is anchored with a monotonic RTT measurement and short raw-PCM chunks are scheduled sample-accurately.</li>
    <li>Small ~0.5 s chunk queue; live drift, RTT, and clock source are shown in <code>#ll_exact_status</code> on the Listen page.</li>
    <li>PHP status-poll fallback when the native sync library or MultiSync is unavailable.</li>
    <li>Automatic fallback after repeated chunk failures to the raw <code>/stream</code> (<code>audio/mpeg</code>), clearly labeled <b>best-effort / NOT frame-exact</b>.</li>
    <li>Live capture via PipeWire/PulseAudio monitor or ALSA using FFmpeg → MP3 (<code>libmp3lame</code>); File Sync capture source also available.</li>
    <li>Status: playlist, sequence, song, and elapsed time from <code>/api/fppd/status</code>, polled every 500 ms on the Listen page.</li>
    <li>Volume is remembered per browser in <code>localStorage</code>; mute is never persisted — each page load starts unmuted.</li>
    <li>Works with FPP 8.x, 9.x, 10.x, and 11.x.</li>
</ul>

<h4>How It Works</h4>
<ol>
    <li>The Listen page reads <code>api/plugin/fpp-ListenLive/sync</code>, a wrapper around the native MultiSync clock (or the PHP status-poll fallback).</li>
    <li>It fetches raw PCM chunks from <code>api/plugin/fpp-ListenLive/media?file=...&amp;seek=...&amp;duration=0.5&amp;format=pcm</code>, builds AudioBuffers directly, and schedules them against the master clock.</li>
    <li>If chunk fetch/decode fails repeatedly, playback switches to <code>api/plugin/fpp-ListenLive/stream</code> — a live capture of FPP's audio output. Browser buffering makes that path best-effort, not frame-exact.</li>
    <li>Enable MultiSync in FPP Settings → MultiSync for exact timing across players and remotes.</li>
</ol>

<h4>Privacy</h4>
<p>Audio is captured on the FPP host and streamed to your browser on the LAN. Nothing is sent off-box, audio is not stored, and there is no telemetry, advertising, or donations.</p>

<h4>Links</h4>
<ul>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive" target="_blank">GitHub Repository</a> — source, README, and releases.</li>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive/issues" target="_blank">Issue Tracker</a> — bugs and feature requests.</li>
    <li><a href="https://github.com/jessica12ryan/fpp-ListenLive/blob/main/README.md" target="_blank">README &amp; Installation Guide</a> — full install and troubleshooting.</li>
</ul>

<h4>Plugin Info</h4>
<p><b>Name:</b> Listen Live Plugin for FPP<br><b>Author:</b> jessica12ryan<br><b>License:</b> MIT — Falcon Christmas FPP is © Falcon Christmas, not affiliated.</p>
<p>Press <b>F1</b> again to close.</p>
