<h3>Listen Live — Configuration</h3>
<p>Tune how FPP's audio is captured and encoded. Settings are stored in FPP's plugin configuration (a legacy <code>config/settings.json</code> copy is kept for migration) and apply to the MP3 fallback stream right away; no FPPD restart is needed. The exact Web Audio path uses raw PCM at 44.1 kHz stereo and is not affected by these encoding settings. The <b>Exact frame sync</b> toggle is on the <b>Listen</b> page, next to the player. After saving, use <b>Test Capture</b> to verify FFmpeg can open the source.</p>

<h4>Fields (each value has ? help to its right, matching FPP Settings)</h4>
<ul>
    <li><b>Enable Live Streaming</b> (checkbox) — Master on/off. When disabled, <code>api/plugin/fpp-ListenLive/stream</code> returns HTTP 503.</li>
    <li><b>Audio Source</b> (select) — How the fallback MP3 stream obtains audio. <b>Auto</b> (recommended) tries the FPP PipeWire/PulseAudio monitor first, then ALSA. Choose <b>PulseAudio</b> or <b>ALSA</b> to force a backend, or <b>File Sync</b> to stream the current media file directly. This does not affect the exact Web Audio path.</li>
    <li><b>Pulse Source</b> (text) — PipeWire/PulseAudio source to capture. Use <code>auto</code> to let the plugin pick the best monitor (prefers the FPP group monitor, which carries the full show mix). Detected sources are listed below the form.</li>
    <li><b>ALSA Device</b> (text) — ALSA capture device. Common values: <code>default</code> (mixer default), <code>hw:0,0</code> (first card), <code>plughw:0,0</code> (with conversion). Some hardware needs an <code>snd-aloop</code> loopback device. Detected devices are listed below.</li>
    <li><b>Bitrate</b> (select) — MP3 encoding bitrate for the fallback stream only. Higher = better quality but more CPU/bandwidth; <code>128k</code> is the default. The exact PCM path ignores this setting.</li>
    <li><b>Sample Rate</b> (select) — Audio sample rate; <code>44100 Hz</code> matches most media. Use <code>48000 Hz</code> for 48 kHz media or <code>22050 Hz</code> for low bandwidth.</li>
    <li><b>Channels</b> (select) — <b>Stereo (2)</b> preserves left/right; <b>Mono (1)</b> halves bandwidth.</li>
</ul>

<h4>Actions</h4>
<ul>
    <li><b>Save Settings</b> — Writes the configuration via <code>api/plugin/fpp-ListenLive/save</code>. Shows "Saved!" or an error.</li>
    <li><b>Test Capture</b> — POSTs to <code>api/plugin/fpp-ListenLive/test</code>, which probes whether FFmpeg can open the selected source and reports FFmpeg, capture backends, FPPD reachability, and media detection.</li>
</ul>

<h4>Exact sync and the raw stream</h4>
<p>Enable <b>MultiSync</b> in FPP Settings → MultiSync for exact timing across players and remotes. The Listen page fetches multi-second raw PCM blocks and slices them into 0.5 s AudioBuffers scheduled with Web Audio; if that fails repeatedly it falls back to the <code>audio/mpeg</code> stream (file-sync first, then live capture), clearly labeled <b>best-effort / NOT frame-exact</b>. Audio is captured on the FPP host and streamed on the LAN only — nothing is sent off-box or stored.</p>

<p>Press <b>F1</b> again to close. See also the tabs <b>Listen</b> (player), <b>Status</b> (live diagnostics), and <b>Help</b> for the full guide.</p>
