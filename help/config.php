<h3>Listen Live — Configuration</h3>
<p>Tune how the live stream is captured and encoded. Settings are stored in <code>config/settings.json</code> and applied on the next <b>▶ Play</b> (no restart needed). After saving, use <b>Test Capture</b> to verify FFmpeg can open the source.</p>

<h4>Fields (each value has ? help to its right, matching FPP Settings)</h4>
<ul>
    <li><b>Enable Live Streaming</b> (checkbox) — Master on/off. When disabled, <code>api/plugin/fpp-ListenLive/stream</code> returns HTTP 503 and the Listen page shows “Listen Live is disabled”.</li>
    <li><b>Audio Source</b> (select) — How FPP's audio is captured. <b>Auto</b> (recommended) tries PipeWire/PulseAudio monitor sources first — exact copy of what the audience hears, including sequences, media and effects — then falls back to ALSA loopback. Choose <b>PulseAudio only</b> or <b>ALSA only</b> to force a backend, or <b>File Sync</b> to stream the current media file directly (seeks to elapsed position).</li>
    <li><b>Pulse Source</b> (text) — PipeWire/PulseAudio source to capture. Use <code>auto</code> to let the plugin pick the best monitor (prefers <code>fpp_group_default.monitor</code> on FPP 9+ with PipeWire, which carries the full show mix). Or enter a specific source name like <code>alsa_output.platform-bcm2835_audio.stereo-fallback.monitor</code>. The detected sources line below lists what was found on this host.</li>
    <li><b>ALSA Device</b> (text) — ALSA capture device. Common values: <code>default</code> (mixer default), <code>hw:0,0</code> (first card), <code>plughw:0,0</code> (with conversion). On some hardware you may need <code>snd-aloop</code> loopback (<code>hw:Loopback,1,0</code>) if the hardware doesn't support concurrent capture. Detected devices are listed below.</li>
    <li><b>Bitrate</b> (select) — MP3 encoding bitrate. Higher = better quality but more CPU/bandwidth. <code>128k</code> is recommended for show audio (5–10% CPU on Pi 4). Use <code>64k/96k</code> if choppy.</li>
    <li><b>Sample Rate</b> (select) — Audio sample rate. <code>44100 Hz</code> (CD) matches most media. Use <code>48000 Hz</code> for 48 kHz media, <code>22050 Hz</code> for low bandwidth.</li>
    <li><b>Channels</b> (select) — <b>Stereo (2)</b> preserves left/right; <b>Mono (1)</b> halves bandwidth, fine for voice.</li>
</ul>

<h4>Actions</h4>
<ul>
    <li><b>Save Settings</b> — Writes the JSON above via <code>api/plugin/fpp-ListenLive/save</code>. Shows “Saved!” or an error.</li>
    <li><b>Test Capture</b> — POSTs to <code>api/plugin/fpp-ListenLive/test</code> which probes the selected source for ~1s and reports FFmpeg, PipeWire, Pulse, ALSA, FPPD reachability and fallback media.</li>
</ul>

<h4>How Audio Capture Works (info box below the form)</h4>
<p>Explains the three capture modes and that the stream is <code>audio/mpeg</code> at <code>api/plugin/fpp-ListenLive/stream</code> with no extra ports. Keep the browser on the same LAN for minimal latency.</p>

<p>Press <b>F1</b> again to close. See also the tabs <b>Listen</b> (player), <b>Status</b> (live diagnostics), and <b>Help</b> for the full guide.</p>
