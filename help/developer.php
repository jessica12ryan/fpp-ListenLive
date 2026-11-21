<h3>Listen Live — Developer</h3>
<p>Advanced tools visible only when the FPP UI Level is set to <b>Developer</b> (FPP Settings → UI → UI Level). Normal users do not see this tab.</p>

<h4>Updates (read-only)</h4>
<ul>
    <li><b>↻ Check for Updates</b> — Calls <code>api/plugin/fpp-ListenLive/check-updates</code>, which compares the local Git SHA with <code>main</code> on the remote. The result shows the local and remote short SHAs and whether an update is available. Installing or updating the plugin is done through FPP's Plugin Manager.</li>
</ul>

<h4>Diagnostics</h4>
<ul>
    <li><b>Run Diagnostics</b> — Calls <code>api/plugin/fpp-ListenLive/test</code>, the same check as <b>Config → Test Capture</b>. It reports whether FFmpeg is installed and whether the capture backends, FPPD, and optional background plugins are reachable. Results appear in the table below the button.</li>
</ul>

<h4>Notes</h4>
<p>The read-only update check and diagnostics call the plugin API under <code>api/plugin/fpp-ListenLive/*</code>. If they fail with "Could not reach the plugin API", check that the plugin is installed correctly. For low-level capture problems, use <b>Config → Test Capture</b> and the plugin log at <code>&lt;mediadir&gt;/logs/plugin-fpp-ListenLive.log</code>.</p>
<p>Press <b>F1</b> again to close. The <b>Logs</b> tab shows the plugin log for the same operations.</p>
