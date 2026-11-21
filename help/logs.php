<h3>Listen Live — Logs</h3>
<p>Tail of the plugin's log file. Useful for troubleshooting why a stream failed, which capture source was tried, and what FPPD reported. The canonical file is <code>&lt;mediadir&gt;/logs/plugin-fpp-ListenLive.log</code> (default: <code>/home/fpp/media/logs/plugin-fpp-ListenLive.log</code>), written by <code>api.php:llLog()</code>.</p>

<h4>Table Columns</h4>
<ul>
    <li><b>Date/Time</b> — Timestamp of the entry (<code>YYYY-MM-DD HH:MM:SS</code>).</li>
    <li><b>Level</b> — <span class="text-success">SUCCESS</span>, <span class="text-danger">ERROR</span>, <span class="text-warning">WARNING</span>, or <span class="text-body-secondary">INFO</span>. Errors include FFmpeg stderr when a capture probe yields no data.</li>
    <li><b>Source</b> — Which component logged it (e.g. <code>fpp-ListenLive api</code>).</li>
    <li><b>Message</b> — Detail such as the selected capture source, probe failures with FFmpeg stderr, settings saves, and fallback/failure reasons. Repeated probe failures are throttled to avoid spam.</li>
</ul>

<h4>Controls</h4>
<ul>
    <li><b>Log file:</b> path display — the exact file being tailed.</li>
    <li><b>↻ Refresh</b> — Re-fetches the last 100 lines via <code>api/plugin/fpp-ListenLive/logs</code> and re-renders the table.</li>
</ul>

<p><b>Tips:</b> If the log is empty, the plugin has not yet been exercised (no Save, no Play, no Test). Try <b>Test Capture</b> on the <b>Config</b> tab and open the <b>Listen</b> tab — playback starts automatically when FPP is playing. If exact sync is unreliable, enable MultiSync and check <code>#ll_exact_status</code> on the Listen page before reading capture logs. For deeper issues, run <code>tail -100 &lt;mediadir&gt;/logs/plugin-fpp-ListenLive.log</code> via SSH. The file is plain text and can be deleted/truncated safely — it will be recreated on the next log write.</p>
<p>Press <b>F1</b> again to close. See <b>Status → Diagnostics</b> for a quicker pass/fail view.</p>
