<h3>Listen Live — Listen</h3>
<p>Play FPP's audio in your browser. If a show is already on air when you open the page, playback starts automatically; if your browser blocks autoplay, tap the <b>Tap to start audio</b> button that appears. A Pause applies only to the current page load.</p>
<p>The primary path is Web Audio <b>Exact frame sync</b>: short raw-PCM chunks are scheduled sample-accurately against FPP's native MultiSync clock, with only ~0.5 s buffered (more when the tab is hidden). If chunk fetch/decode fails a few times, the player switches to the raw <code>audio/mpeg</code> live stream and labels it <b>best-effort / NOT frame-exact</b> — browser buffering adds unmeasured latency, so that path cannot be frame-locked to MultiSync.</p>

<h4>Player</h4>
<ul>
    <li><b>Idle / Live badge</b> — <b>● LIVE</b> (red, pulsing) means audio is streaming. <b>Idle</b> (grey) means no stream. <b>Warn</b> (amber) indicates an error — see the status text.</li>
    <li><b>Now Playing</b> — Current media from <code>/api/fppd/status</code> (<code>current_song</code> or <code>current_sequence</code>). Shows "Nothing playing — FPP is idle" when no media is active; status is polled every 500 ms.</li>
    <li><b>Wave animation</b> — Visual feedback that the page is live. Animated while playing, paused when stopped.</li>
    <li><b>&lt;audio&gt; element</b> — Native HTML5 controls. With Exact frame sync active, Web Audio owns the sound and the native element is kept muted as a connection/fallback monitor.</li>
    <li><b>Timing bar</b> — Shows elapsed / duration and remaining time. Duration comes from FPP status or BackgroundMusic track length.</li>
</ul>

<h4>Controls</h4>
<ul>
    <li><b>▶ Play</b> — Starts playback; with Exact sync active this anchors the Web Audio schedule to the FPP clock.</li>
    <li><b>■ Stop</b> — Stops playback, clears the source, resets the badge to <b>Stopped</b>, and hides the timing bar.</li>
    <li><b>↻ Reconnect</b> — Forces a fresh connection and re-syncs the audio graph — useful after a network drop, a track change, or a fallback to the raw stream.</li>
    <li><b>Volume slider</b> — Sets volume 0–100%. Stored per browser in <code>localStorage</code> (<code>fpp-ListenLive-volume</code>).</li>
    <li><b>Mute / Unmute</b> — Mutes output but is <b>never persisted</b>: every page load starts unmuted.</li>
    <li><b>Exact frame sync</b> (checkbox) — Web Audio chunk scheduling, <b>on by default on the first visit</b>. Needs MultiSync enabled (FPP Settings → MultiSync) for exact timing across players/remotes. The status text <code>#ll_exact_status</code> shows <code>source</code>, RTT, queue, and measured drift. "Exact" is shown only when the native clock is in use; otherwise it is labeled <b>NOT frame-exact</b>. Uncheck it to use the best-effort native stream.</li>
</ul>

<h4>Status Grid (read-only info)</h4>
<ul>
    <li><b>Source</b> — Configured capture source (<code>auto</code>, <code>pulse</code>, <code>pipewire</code>, <code>alsa</code>, <code>file</code>).</li>
    <li><b>Bitrate</b> — Encoding setting (<code>128k / 44100 Hz / Stereo</code>, etc.) from Config.</li>
    <li><b>Elapsed</b> — Playback position from FPP status.</li>
    <li><b>Playlist / Sequence</b> — Current playlist and sequence names from FPP status.</li>
</ul>

<h4>Stream Info (diagnostics table)</h4>
<ul>
    <li><b>FFmpeg</b> — Whether FFmpeg was found and its path, plus capture backend support.</li>
    <li><b>PipeWire / PulseAudio / ALSA</b> — Availability and detected sources/devices; the FPP group monitor is preferred for full-show capture.</li>
    <li><b>Detected media</b> — Media found when FPP is idle; display only.</li>
    <li><b>BackgroundMusic / AfterHours</b> — Whether those optional plugins are responding.</li>
    <li><b>Stream URL</b> — <code>api/plugin/fpp-ListenLive/stream</code> with an "Open directly" link. This raw stream is best-effort, not frame-exact.</li>
</ul>

<p><b>Tip:</b> Keep this tab open on the same LAN as FPP. For exact timing, enable MultiSync, watch <code>#ll_exact_status</code>, and if you see the best-effort / NOT frame-exact label, verify media is playing and run <b>Test Capture</b> on the <b>Config</b> tab. Logs: <code>&lt;mediadir&gt;/logs/plugin-fpp-ListenLive.log</code>.</p>
<p>Press <b>F1</b> again to close this help. The detailed guide is also on the <b>Help</b> tab.</p>
