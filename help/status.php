<h3>Listen Live — Status</h3>
<p>Live overview of the plugin's configuration, FPP playback state, and capture diagnostics. Auto-refreshes every 5 seconds. All values have <span style="display:inline-block; vertical-align:middle;"><img src="images/redesign/help-icon.svg" class="icon-help" alt="help" style="width:16px;height:16px;"></span> help to the right of the field (same as FPP Settings).</p>

<h4>Status Table (top fieldset)</h4>
<ul>
    <li><b>Plugin Enabled</b> — Master on/off from Config. <b>Yes</b> (green) or <b>No</b> (red). When <b>No</b>, the stream endpoint returns HTTP 503.</li>
    <li><b>Audio Source</b> — Configured backend: <code>auto</code>, <code>pulse</code>, <code>alsa</code>, or <code>file</code>. Auto is recommended.</li>
    <li><b>Bitrate</b> — Encoding as <code>128k / 44100 Hz / Stereo</code>. From Config.</li>
    <li><b>FPP Status</b> — FPP playback state from <code>/api/fppd/status</code> (<code>status_name</code> or <code>status</code>): <code>playing</code>, <code>idle</code>, <code>stopped</code>, or <code>unreachable</code> if fppd did not respond.</li>
    <li><b>Current Playlist / Sequence / Song</b> — Names from fppd status (<code>current_playlist</code>, <code>current_sequence</code>, <code>current_song</code>). Song is what the live mix is playing.</li>
    <li><b>Elapsed</b> — Playback position as <code>seconds_elapsed / seconds_remaining</code> (or <code>time_elapsed / time_remaining</code>). Used for the Listen page progress bar and file-sync seek.</li>
    <li><b>Fallback (type)</b> — Shown only when FPP is idle and a fallback exists: media from BackgroundMusic or AfterHours plugin (<code>background</code> or <code>afterhours</code>) that would be streamed when the show is idle.</li>
    <li><b>BackgroundMusic / AfterHours</b> — Whether those optional plugins are installed and responding. BackgroundMusic plays during idle and is included in the live mix via PipeWire.</li>
    <li><b>Stream URL</b> — Live endpoint <code>api/plugin/fpp-ListenLive/stream</code> with a <b>Test</b> link to open it in a new tab. Captures the live mix including background when available, falls back to file.</li>
</ul>

<h4>Diagnostics Table (bottom fieldset)</h4>
<ul>
    <li><b>FFmpeg</b> — Whether the <code>ffmpeg</code> binary was found, its path (<code>/usr/bin/ffmpeg</code> etc), and whether the PipeWire demuxer is available. Required for live capture; missing shows red.</li>
    <li><b>PipeWire</b> — PipeWire availability (FPP 9+). Required for exact capture via <code>fpp_group_default.monitor</code>. Lists up to two detected monitor sources.</li>
    <li><b>PulseAudio</b> — PulseAudio / pipewire-pulse availability and sources. Auto mode uses <code>.monitor</code> sources for an exact copy of output.</li>
    <li><b>ALSA</b> — ALSA availability and devices (<code>default, hw:0,0, plughw:0,0 …</code>). Used when Pulse is unavailable.</li>
    <li><b>Fallback</b> — Fallback media detection: <code>type: media</code> plus <code>(found)</code>, <code>(stream)</code>, or <code>(not found)</code>. Indicates whether the fallback file exists on disk.</li>
    <li><b>BackgroundMusic / AfterHours</b> (diagnostics) — Whether each plugin responded to its own status API. Green = responding.</li>
</ul>

<p><b>Actions:</b> <b>▶ Listen Now</b> and <b>Configure</b> buttons navigate to those tabs. <b>↻ Refresh</b> re-polls <code>api/plugin/fpp-ListenLive/status</code> and <code>diagnostics</code>.</p>
<p>Press <b>F1</b> again to close. The <b>Config</b> tab's ? icons describe how to change each setting.</p>
