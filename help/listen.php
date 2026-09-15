<h3>Listen Live — Listen</h3>
<p>Play the FPP's live audio in your browser. What you hear is what the audience hears — the live mix captured from PipeWire/PulseAudio or ALSA and streamed as <code>audio/mpeg</code> via <code>api/plugin/fpp-ListenLive/stream</code>. When capture is unavailable the plugin falls back to streaming the current media file in sync.</p>

<h4>Player</h4>
<ul>
    <li><b>Idle / Live badge</b> — Shows connection state. <b>● LIVE</b> (red, pulsing) means audio is streaming. <b>Idle</b> (grey) means no stream. <b>Warn</b> (amber) indicates an error — see the status text. Hover the badge for no extra detail; state is self-explanatory.</li>
    <li><b>Now Playing</b> — Current media from <code>/api/fppd/status</code> (<code>current_song</code> or <code>current_sequence</code>). Shows “Nothing playing — FPP is idle” when no media is active. Help icon to the right of the field explains the source.</li>
    <li><b>Wave animation</b> — Visual feedback that the page is live. Animated when playing, paused when stopped.</li>
    <li><b>&lt;audio&gt; element</b> — Standard HTML5 player with native controls. Use it or the buttons below.</li>
    <li><b>Timing bar</b> — Shows <b>elapsed / duration</b> and remaining time with a progress bar. Visible only while playing. Duration comes from FPP status or BackgroundMusic track length.</li>
</ul>

<h4>Controls</h4>
<ul>
    <li><b>▶ Play</b> — Starts streaming. Appends a cache-buster (<code>?t=Date.now()</code>) to force a fresh FFmpeg pipeline. Shows “Connecting…” then “Streaming live audio”.</li>
    <li><b>■ Stop</b> — Stops playback, clears <code>src</code>, resets badge to <b>Stopped</b> and hides the timing bar.</li>
    <li><b>↻ Reconnect</b> — Forces a reconnect with a new URL — useful after a network drop or track change. When Exact Audio is enabled it re-syncs the AudioContext graph.</li>
    <li><b>Volume slider</b> — Sets <code>audio.volume</code> 0–100%, label shows percent. Stored per browser in <code>localStorage fpp-ListenLive-volume</code>.</li>
    <li><b>Mute / Unmute</b> — Toggles <code>audio.muted</code>; remembers pre-mute volume.</li>
    <li><b>Exact frame sync</b> (checkbox) — When checked, enables AudioContext gapless scheduling from the monotonic master clock (<code>/api/plugin-apis/ListenLive/sync</code>). Status shows <code>exact • …</code> with drift. Uncheck for native &lt;audio&gt; drift.</li>
</ul>

<h4>Status Grid (read-only info, each value has ? help to its right)</h4>
<ul>
    <li><b>Source</b> — Configured audio source (<code>auto</code>, <code>pulse</code>, <code>alsa</code>, <code>file</code>).</li>
    <li><b>Bitrate</b> — Encoding setting (<code>128k / 44100 Hz / Stereo</code> etc) from Config.</li>
    <li><b>Elapsed</b> — Playback position <code>elapsed / remaining</code> from FPP status.</li>
    <li><b>Playlist / Sequence</b> — Current playlist and sequence names from FPP status.</li>
</ul>

<h4>Stream Info (diagnostics table — each row has ? help to the right of the value except Stream URL)</h4>
<ul>
    <li><b>FFmpeg</b> — Whether FFmpeg was found and its path, plus PipeWire demuxer support.</li>
    <li><b>PipeWire</b> — PipeWire availability and detected monitor sources (<code>fpp_group_default.monitor</code> preferred).</li>
    <li><b>PulseAudio</b> — PulseAudio / pipewire-pulse availability and sources.</li>
    <li><b>ALSA</b> — ALSA availability and devices (<code>default, hw:0,0 …</code>).</li>
    <li><b>Detected media (display only)</b> — Fallback media found when FPP idle — display only, live capture streams the live mix regardless.</li>
    <li><b>BackgroundMusic / AfterHours</b> — Whether those optional plugins are responding.</li>
    <li><b>Stream URL</b> — <code>api/plugin/fpp-ListenLive/stream</code> with “Open directly” link. No tooltip — the link itself is the action.</li>
</ul>

<p><b>Tip:</b> Keep this tab open on the same LAN as FPP for 1–3s latency. If you hear silence, start a playlist/schedule so audio is playing, then hit <b>Test Capture</b> on the <b>Config</b> tab.</p>
<p>Press <b>F1</b> again to close this help. Detailed guide is also on the <b>Help</b> tab.</p>
