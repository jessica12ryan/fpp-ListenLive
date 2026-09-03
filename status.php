<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## status.php                                              ##
 * #############################################################
 */
$llPluginDir = __DIR__;
$llSettingsFile = $llPluginDir . '/config/settings.json';
$llSettings = [];
if (file_exists($llSettingsFile)) $llSettings = json_decode(@file_get_contents($llSettingsFile), true) ?: [];
$enabled = !empty($llSettings['enabled']) ? 1 : 0;
// Preserve FPP global $settings for tab visibility
$_fppUiLevel = (int)($GLOBALS['settings']['uiLevel'] ?? $settings['uiLevel'] ?? 0);
$uiLevel = $_fppUiLevel;
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Listen Live — Status</legend>
        <div class="p-3">
            <div id="ll_status_table"><span class="text-secondary">Loading...</span></div>
            <p style="margin-top:10px;">
                <a class="buttons" href="plugin.php?plugin=fpp-ListenLive&page=listen.php">▶ Listen Now</a>
                <a class="buttons" href="plugin.php?plugin=fpp-ListenLive&page=config.php">Configure</a>
            </p>
        </div>
    </fieldset>

    <fieldset class="border p-3" style="margin-top:12px;">
        <legend>Diagnostics</legend>
        <div class="p-3" id="ll_diag_table"><span class="text-secondary">Loading...</span></div>
        <p style="margin-top:8px;"><input type="button" class="buttons" value="↻ Refresh" onclick="llStatus.refresh();"></p>
    </fieldset>
</div>

<script>
var llStatus = {
    refresh: function() {
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/status',
            type: 'GET',
            dataType: 'json',
            success: function(d) {
                if (!d.success) {
                    $('#ll_status_table').html('<span class="text-danger">Error: ' + escHtml(d.error || 'Unknown') + '</span>');
                    return;
                }
                var s = d.fpp_status || {};
                var settings = d.settings || {};
                var det = d.detection || {};
                var html = '<table class="fppTable" style="width:auto;">';
                html += '<tr><td style="padding:4px;"><b>Plugin Enabled:</b></td><td style="padding:4px;">' + (settings.enabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Audio Source:</b></td><td style="padding:4px;">' + escHtml(settings.source || 'auto') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Bitrate:</b></td><td style="padding:4px;">' + escHtml(settings.bitrate || '') + ' / ' + escHtml(String(settings.sample_rate || '')) + ' Hz / ' + (settings.channels===1?'Mono':'Stereo') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>FPP Status:</b></td><td style="padding:4px;">' + escHtml(s.status_name || s.status || 'unknown') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Current Playlist:</b></td><td style="padding:4px;">' + escHtml(s.current_playlist || '—') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Current Sequence:</b></td><td style="padding:4px;">' + escHtml(s.current_sequence || '—') + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Current Song:</b></td><td style="padding:4px;">' + escHtml(typeof s.current_song === 'string' ? s.current_song : JSON.stringify(s.current_song || '—')) + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Elapsed:</b></td><td style="padding:4px;">' + escHtml(String(s.seconds_elapsed || s.time_elapsed || '0')) + ' / remaining ' + escHtml(String(s.seconds_remaining || s.time_remaining || '—')) + '</td></tr>';
                html += '<tr><td style="padding:4px;"><b>Stream URL:</b></td><td style="padding:4px;"><code>api/plugin/fpp-ListenLive/stream</code> <a href="api/plugin/fpp-ListenLive/stream" target="_blank">Test</a></td></tr>';
                html += '</table>';
                $('#ll_status_table').html(html);

                // diagnostics
                var html2 = '<table class="fppTable" style="width:auto;">';
                html2 += '<tr><td style="padding:4px;"><b>FFmpeg:</b></td><td style="padding:4px;">' + (det.ffmpeg ? '<span class="text-success">Found</span> (' + escHtml(det.ffmpeg_path)+')' : '<span class="text-danger">Missing</span>') + '</td></tr>';
                html2 += '<tr><td style="padding:4px;"><b>PulseAudio:</b></td><td style="padding:4px;">' + (det.pulse ? '<span class="text-success">Yes</span>' : '<span class="text-secondary">No</span>') + (det.pulse_sources && det.pulse_sources.length ? ' — ' + escHtml(det.pulse_sources.join(', ')) : '') + '</td></tr>';
                html2 += '<tr><td style="padding:4px;"><b>ALSA:</b></td><td style="padding:4px;">' + (det.alsa ? '<span class="text-success">Yes</span>' : '<span class="text-secondary">No</span>') + (det.alsa_devices && det.alsa_devices.length ? ' — ' + escHtml(det.alsa_devices.slice(0,3).join(', ')) : '') + '</td></tr>';
                html2 += '</table>';
                $('#ll_diag_table').html(html2);
            },
            error: function() {
                $('#ll_status_table').html('<span class="text-danger">Could not reach plugin API</span>');
                $('#ll_diag_table').html('<span class="text-danger">Could not reach API</span>');
            }
        });
    }
};
function escHtml(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
$(document).ready(function(){ llStatus.refresh(); setInterval(llStatus.refresh, 5000); });
</script>

<?php include __DIR__ . '/footer.inc'; ?>
