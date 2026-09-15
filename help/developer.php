<h3>Listen Live — Developer</h3>
<p>Advanced tools visible only when the FPP UI Level is set to <b>Developer</b> (FPP Settings → UI → UI Level). Normal users do not see this tab.</p>

<h4>Updates</h4>
<ul>
    <li><b>↻ Check for Updates</b> — Calls <code>api/plugin/fpp-ListenLive/check-updates</code> which runs <code>git -C $PLUGIN_DIR rev-parse HEAD</code> vs <code>git ls-remote origin main</code>. Shows <b>Local</b> and <b>Remote</b> short SHAs and whether an update is available. When an update is available the button becomes <b>↻ Install Updates</b>.</li>
    <li><b>Install Updates</b> — POSTs to <code>api/plugin/fpp-ListenLive/update</code>: preserves <code>config/settings.json</code>, fetches, resets to <code>origin/main</code>, restores config, rebuilds the native component (<code>make</code>), and sets <code>restartFlag</code> so FPPD reloads the new <code>libfpp-ListenLive.so</code>. Page reloads when done.</li>
</ul>

<h4>Plugin Management</h4>
<ul>
    <li><b>⚠ Reinstall Plugin</b> (amber) — POSTs to <code>api/plugin/fpp-ListenLive/reinstall</code> (same as update but forces a clean reset). Preserves config.</li>
    <li><b>⚠ Uninstall Plugin</b> (red) — POSTs to <code>api/plugin/fpp-ListenLive/uninstall</code> which recursively deletes the plugin directory and sets <code>restartFlag</code>. Redirects to <code>plugins.php?tab=available</code>. Config is not retained unless you backed it up.</li>
</ul>

<h4>Diagnostics</h4>
<ul>
    <li><b>Run Diagnostics</b> — POSTs to <code>api/plugin/fpp-ListenLive/test</code> which runs the same 1-second FFmpeg probe as <b>Config → Test Capture</b> and lists <b>FFmpeg, PipeWire, Pulse, ALSA, FPPD reachability, fallback media, BackgroundMusic, AfterHours</b> with pass/fail and details. Results appear in the table below the button.</li>
</ul>

<h4>Restart FPPD</h4>
<ul>
    <li><b>Restart FPPD</b> — POSTs to <code>api/plugin/fpp-ListenLive/restart-fppd</code> which sets <code>restartFlag</code> via <code>/api/settings/restartFlag</code>. FPPD will restart between sequences, picking up any native or command changes without a full reboot. Useful after changing audio routing or reinstalling.</li>
</ul>

<p><b>Note:</b> All of these call the plugin's own API under <code>api/plugin/fpp-ListenLive/*</code>. If they fail with “Could not reach the plugin API”, check that the plugin is installed and that <code>libfpp-ListenLive.so</code> built correctly (see <code>scripts/fpp_install.sh</code> log at <code>/home/fpp/media/logs/fpp_plugin_manager.log</code>).</p>
<p>Press <b>F1</b> again to close. The <b>Logs</b> tab shows the plugin log for the same operations.</p>
