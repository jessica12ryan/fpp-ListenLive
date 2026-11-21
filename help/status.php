<h3>Listen Live — Status</h3>
<p>Live overview of the plugin's configuration, FPP playback state, and capture diagnostics. This tab refreshes every 5 seconds (the Listen page polls playback status every 500 ms). All values have <span style="display:inline-block; vertical-align:middle;"><img src="images/redesign/help-icon.svg" class="icon-help" alt="help" style="width:16px;height:16px;"></span> help to the right of the field.</p>

<h4>Status Table (top fieldset)</h4>
<ul>
    <li><b>Plugin Enabled</b> — Master on/off from Config. <b>Yes</b> (green) or <b>No</b> (red). When <b>No</b>, <code>/stream</code> returns HTTP 503.</li>
    <li><b>Audio Source</b> — Configured backend: <code>auto</code>, <code>pulse</code>, <code>pipewire</code>, <code>alsa</code>, or <code>file</code>. Auto is recommended.</li>
    <li><b>Bitrate</b> — Encoding as <code>128k / 44100 Hz / Stereo</code>, from Config.</li>
    <li><b>FPP Status</b> — Playback state from <code>/api/fppd/status</code> (<code>status_name</code> or <code>status</code>): <code>playing</code>, <code>idle</code>, <code>stopped</code>, or <code>unreachable</code>.</li>
    <li><b>Current Playlist / Sequence / Song</b> — Names from FPP status; the song is what the live mix is playing.</li>
    <li><b>Elapsed</b> — Playback position from FPP status, used by the Listen page progress bar and chunk scheduling.</li>
    <li><b>Detected media (display only)</b> — Media found when FPP is idle (e.g. from BackgroundMusic or AfterHours); informational only.</li>
    <li><b>BackgroundMusic / AfterHours</b> — Whether those optional plugins are installed and responding. Their audio appears in the capture when routed through the FPP mix.</li>
    <li><b>Stream URL</b> — Raw live endpoint <code>api/plugin/fpp-ListenLive/stream</code> with a <b>Test</b> link. It is best-effort and not frame-exact; the Listen page uses Web Audio chunk scheduling first.</li>
</ul>

<h4>Diagnostics Table (bottom fieldset)</h4>
<ul>
    <li><b>FFmpeg</b> — Whether the <code>ffmpeg</code> binary was found, its path, and capture backend support. Required for capture and chunk transcoding.</li>
    <li><b>PipeWire / PulseAudio</b> — Availability and detected sources; the FPP group monitor carries the full show mix and is preferred.</li>
    <li><b>ALSA</b> — Availability and devices (<code>default, hw:0,0, plughw:0,0 …</code>), used when other backends are unavailable.</li>
    <li><b>Fallback</b> — Detected fallback media: <code>type: media</code> plus <code>(found)</code>, <code>(stream)</code>, or <code>(not found)</code>. Separately, if Web Audio chunk playback fails repeatedly, the player switches to the raw stream and labels it <b>best-effort / NOT frame-exact</b>.</li>
    <li><b>BackgroundMusic / AfterHours</b> (diagnostics) — Whether each plugin responded to its own status API. Green = responding.</li>
</ul>

<p><b>Actions:</b> <b>▶ Listen Now</b> and <b>Configure</b> navigate to those tabs. <b>↻ Refresh</b> re-polls <code>api/plugin/fpp-ListenLive/status</code> and <code>diagnostics</code>. If timing is not exact, enable MultiSync in FPP Settings → MultiSync and check <code>#ll_exact_status</code> on the Listen page; log details are in <code>&lt;mediadir&gt;/logs/plugin-fpp-ListenLive.log</code>.</p>
<p>Press <b>F1</b> again to close. The <b>Config</b> tab's ? icons describe how to change each setting.</p>
