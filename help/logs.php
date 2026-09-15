<h3>Listen Live — Logs</h3>
<p>Tail of the plugin's log file. Useful for troubleshooting why a stream failed, which capture source was tried, and what FPPD reported. The file lives at <code>/home/fpp/media/logs/plugin-fpp-ListenLive.log</code> (or <code>$LOGDIR/plugin-fpp-ListenLive.log</code>) and is written by <code>api.php:llLog()</code>.</p>

<h4>Table Columns</h4>
<ul>
    <li><b>Date/Time</b> — Timestamp of the entry (<code>YYYY-MM-DD HH:MM:SS</code>).</li>
    <li><b>Level</b> — <span style="color:#198754;">SUCCESS</span>, <span style="color:#dc3545;">ERROR</span>, <span style="color:#fd7e14;">WARNING</span>, or <span style="color:inherit;">INFO</span>. Errors include FFmpeg stderr when a probe yields no data.</li>
    <li><b>Source</b> — Which component logged it (e.g. <code>fpp-ListenLive api</code> or <code>diagnostics</code>).</li>
    <li><b>Message</b> — Detail: “Stream started from 192.168.1.x source=pipewire-pulse:fpp_group_default.monitor”, “Stream probe no data: pulse:default exit=1 stderr=…”, “Settings saved”, etc. Probe failures are throttled after 10 (mirroring PulseMesh <code>m_sendErrorCount</code>) to avoid spam.</li>
</ul>

<h4>Controls</h4>
<ul>
    <li><b>Log file:</b> path display — the exact file being tailed.</li>
    <li><b>↻ Refresh</b> — Re-fetches the last 100 lines via <code>api/plugin/fpp-ListenLive/logs</code> and re-renders the table.</li>
</ul>

<p><b>Tips:</b> If the log is empty, the plugin has not yet been exercised (no Save, no Play, no Test). Try <b>Test Capture</b> on the <b>Config</b> tab and then <b>▶ Play</b> on the <b>Listen</b> tab. For deeper issues, run <code>tail -100 /home/fpp/media/logs/plugin-fpp-ListenLive.log</code> via SSH. The file is plain text and can be deleted/truncated safely — it will be recreated on the next log write.</p>
<p>Press <b>F1</b> again to close. See <b>Status → Diagnostics</b> for a quicker pass/fail view.</p>
