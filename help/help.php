<h3>Listen Live — Help &amp; Usage Guide</h3>
<p>This is the browsable help index for the plugin (also reachable via the <b>Help</b> tab). Press <b>F1</b> on any plugin page to see page-specific help for that page — the same content appears in the F1 modal.</p>

<h4>What This Plugin Does</h4>
<p><b>Listen Live</b> lets you hear your show's audio directly in the FPP web UI. Its primary path is Web Audio <b>Exact frame sync</b>: FPP's native MultiSync media/sequence clock is estimated with a monotonic round-trip measurement (never a wall-clock difference), and short raw-PCM chunks are scheduled sample-accurately with a small ~0.5 s queue. Enable <b>MultiSync</b> in FPP Settings → MultiSync for exact timing across players and remotes. If chunks cannot be fetched/decoded, playback falls back to the raw live stream, clearly labeled <b>best-effort / NOT frame-exact</b>.</p>

<h4>Quick Start</h4>
<ol>
    <li>Install via <b>Content Setup → Plugin Manager</b> (or from the JSON URL).</li>
    <li>Enable <b>MultiSync</b> in FPP Settings → MultiSync for exact sync across players/remotes.</li>
    <li>Open <b>Content Setup → Listen Live</b> — you'll land on the <b>Listen</b> tab.</li>
    <li>Start a playlist/schedule so audio is playing.</li>
    <li>Click <b>▶ Play</b>. Exact frame sync is on by default on the first visit; the status beside the player shows sync state and drift.</li>
    <li>Adjust <b>Volume</b> — stored per browser in <code>localStorage</code>. Mute is never persisted; each page load starts unmuted.</li>
</ol>

<h4>Tabs</h4>
<ul>
    <li><b>Listen</b> — Player, timing bar, volume, Exact sync toggle, and stream diagnostics. Press <b>F1</b> for player help.</li>
    <li><b>Status</b> — Live config, FPP playback state, and capture diagnostics. Refreshes every 5 s. Press <b>F1</b> for status help.</li>
    <li><b>Config</b> — Capture/encoding settings with <b>?</b> icons. Press <b>F1</b> for field-by-field help.</li>
    <li><b>Logs</b> — Tail of <code>plugin-fpp-ListenLive.log</code>. Press <b>F1</b> for log help.</li>
    <li><b>Developer</b> (Developer UI Level) — Read-only update check and diagnostics.</li>
    <li><b>Help</b> — This page.</li>
    <li><b>About</b> — Features, privacy, and links. Press <b>F1</b> for about help.</li>
</ul>

<h4>How It Works</h4>
<p>The Listen page reads <code>api/plugin/fpp-ListenLive/sync</code> (a PHP wrapper around the native MultiSync clock at <code>/api/plugin-apis/ListenLive/sync</code>, with a PHP status-poll fallback), fetches chunks from <code>api/plugin/fpp-ListenLive/media?file=...&amp;seek=...&amp;duration=0.5&amp;format=pcm</code>, builds AudioBuffers directly from the PCM samples, and schedules them with Web Audio. If chunk playback fails repeatedly, it switches to <code>api/plugin/fpp-ListenLive/stream</code> — a live MP3 capture of FPP's audio output with unmeasured browser buffering, so it is best-effort and not frame-locked.</p>

<h4>Requirements</h4>
<ul>
    <li>FPP 8.x, 9.x, 10.x, or 11.x; MultiSync enabled for exact timing; a modern Web Audio browser on the same LAN.</li>
    <li><b>FFmpeg</b> on the FPP host — declared as a dependency in <code>pluginInfo.json</code>; Plugin Manager installs it on FPP 10+ and the installer handles older releases. For manual installation on older FPP, run <code>apt update &amp;&amp; apt install -y ffmpeg</code> as root on the FPP host (FPP runs its installer as root, so no <code>sudo</code> is needed there).</li>
    <li>For the raw stream: PipeWire/PulseAudio monitor preferred, or ALSA capture; <code>File Sync</code> capture is also available.</li>
</ul>

<h4>Troubleshooting</h4>
<ul>
    <li><b>Not frame-exact:</b> enable MultiSync and check <code>#ll_exact_status</code> on the Listen page; <code>source: fallback</code> means the PHP status poll is in use; the status text is then labeled <b>NOT frame-exact</b>.</li>
    <li><b>"best-effort / NOT frame-exact" label:</b> chunk fetch/decode failed repeatedly; ensure media is playing, then press <b>↻ Reconnect</b>.</li>
    <li><b>No audio:</b> check <b>Status → Diagnostics</b>, run <b>Config → Test Capture</b>, confirm a playlist is playing, and inspect <code>tail -50 &lt;mediadir&gt;/logs/plugin-fpp-ListenLive.log</code>.</li>
    <li><b>Choppy:</b> lower the bitrate to 96k/64k and check FPP CPU load.</li>
</ul>

<h4>Privacy</h4>
<p>Audio is captured on the FPP host and streamed to your browser on the LAN. Nothing is sent off-box, audio is not stored, and there is no telemetry, advertising, or donations.</p>

<p>Press <b>F1</b> again to close. Each page's <b>?</b> icons give field-level help; F1 gives page-level help.</p>
