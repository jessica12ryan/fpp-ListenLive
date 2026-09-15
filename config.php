<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## config.php                                              ##
 * #############################################################
 */
$llPluginDir = __DIR__;
$llSettingsFile = $llPluginDir . '/config/settings.json';

$defaults = [
    'enabled' => 1,
    'source' => 'auto',
    'bitrate' => '128k',
    'sample_rate' => 44100,
    'channels' => 2,
    'alsa_device' => 'default',
    'pulse_source' => 'auto',
    'volume' => 100
];
$llSettings = $defaults;
if (file_exists($llSettingsFile)) {
    $s = json_decode(@file_get_contents($llSettingsFile), true);
    if (is_array($s)) $llSettings = array_merge($defaults, $s);
}
// Do NOT overwrite FPP global $settings — keep it for UI level detection
// Tabs.inc will read $GLOBALS['settings']['uiLevel'] correctly
$_fppUiLevel = (int)($GLOBALS['settings']['uiLevel'] ?? 0);
if ($_fppUiLevel === 0 && isset($settings['uiLevel'])) {
    $_fppUiLevel = (int)$settings['uiLevel'];
}
$uiLevel = $_fppUiLevel;
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
// Provide $settings alias for existing HTML that expects plugin settings in $settings
// But also keep $llSettings authoritative; HTML below will be updated to use $llSettings
$settings = $llSettings;
?>
<style>
@media only screen and (max-width: 480px) {
    fieldset { padding: 5px !important; }
    table { width: 100%; table-layout: fixed; word-wrap: break-word; }
    td { display: block; width: 100% !important; box-sizing: border-box; }
    input[type="text"], select { width: 100% !important; box-sizing: border-box; }
    input.buttons { width: 100%; margin-bottom: 4px; box-sizing: border-box; }
}
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Listen Live — Configuration</legend>
        <div class="p-3">
            <table>
                <tr>
                    <td style="padding: 4px;"><b>Enable Live Streaming:</b> <span id="ll_enabled_tip" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="When enabled, FPP captures its live audio output and streams it to your browser via api/plugin/fpp-ListenLive/stream as audio/mpeg. When disabled, that endpoint returns HTTP 503 Service Unavailable and the player shows &quot;Listen Live is disabled.&quot;"><img id="ll_enabled_img" src="images/redesign/help-icon.svg" class="icon-help" alt="help"></span></td>
                    <td style="padding: 4px;">
                        <input type="checkbox" id="ll_enabled" <?php echo !empty($settings['enabled']) ? 'checked' : ''; ?>>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Audio Source:</b> <span id="ll_source_tip" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="How FPP&#39;s audio is captured. Auto (recommended) tries PipeWire/PulseAudio monitor sources first — exact copy of what the audience hears, including sequences, media and effects — then falls back to ALSA loopback. Choose PulseAudio only or ALSA only to force a backend, or File Sync to stream the current media file directly (seeks to elapsed position)."><img id="ll_source_img" src="images/redesign/help-icon.svg" class="icon-help" alt="help"></span></td>
                    <td style="padding: 4px;">
                        <select id="ll_source">
                            <option value="auto" <?php echo ($settings['source']==='auto'?'selected':''); ?>>Auto (try Pulse → ALSA)</option>
                            <option value="pulse" <?php echo ($settings['source']==='pulse'?'selected':''); ?>>PulseAudio only</option>
                            <option value="alsa" <?php echo ($settings['source']==='alsa'?'selected':''); ?>>ALSA only</option>
                            <option value="file" <?php echo ($settings['source']==='file'?'selected':''); ?>>File Sync (stream media file)</option>
                        </select>
                    </td>
                </tr>
                <tr id="row_pulse" style="<?php echo $settings['source']==='alsa' ? 'display:none;' : ''; ?>">
                    <td style="padding: 4px;"><b>Pulse Source:</b> <span id="ll_pulse_source_tip" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="PipeWire/PulseAudio source to capture. Use &quot;auto&quot; to let the plugin pick the best monitor (prefers fpp_group_default.monitor on FPP 9+ with PipeWire, which carries the full show mix). Or enter a specific source name like alsa_output.platform-bcm2835_audio.stereo-fallback.monitor. Detected sources are listed below."><img id="ll_pulse_source_img" src="images/redesign/help-icon.svg" class="icon-help" alt="help"></span></td>
                    <td style="padding: 4px;">
                        <input type="text" id="ll_pulse_source" size="30" value="<?php echo htmlspecialchars($settings['pulse_source'] ?? 'auto'); ?>" placeholder="auto">
                        <div id="ll_pulse_list" class="text-secondary" style="font-size:12px; margin-top:4px;"></div>
                    </td>
                </tr>
                <tr id="row_alsa" style="<?php echo $settings['source']==='pulse' ? 'display:none;' : ''; ?>">
                    <td style="padding: 4px;"><b>ALSA Device:</b> <span id="ll_alsa_device_tip" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="ALSA capture device. Common values: default (mixer default), hw:0,0 (first card, first device), plughw:0,0 (with format conversion). On some hardware you may need the snd-aloop loopback device (hw:Loopback,1,0) if your audio hardware doesn&#39;t support concurrent capture. Detected devices are listed below."><img id="ll_alsa_device_img" src="images/redesign/help-icon.svg" class="icon-help" alt="help"></span></td>
                    <td style="padding: 4px;">
                        <input type="text" id="ll_alsa_device" size="30" value="<?php echo htmlspecialchars($settings['alsa_device'] ?? 'default'); ?>" placeholder="default">
                        <div id="ll_alsa_list" class="text-secondary" style="font-size:12px; margin-top:4px;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Bitrate:</b> <span id="ll_bitrate_tip" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="MP3 encoding bitrate. Higher = better quality but more CPU and bandwidth. 128k is recommended for show audio (5–10% CPU on Pi 4). Use 64k/96k if audio is choppy or CPU is high."><img id="ll_bitrate_img" src="images/redesign/help-icon.svg" class="icon-help" alt="help"></span></td>
                    <td style="padding: 4px;">
                        <select id="ll_bitrate">
                            <?php foreach (['64k','96k','128k','160k','192k','256k','320k'] as $br): ?>
                            <option value="<?php echo $br; ?>" <?php echo ($settings['bitrate']===$br?'selected':''); ?>><?php echo $br; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Sample Rate:</b> <span id="ll_samplerate_tip" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="Audio sample rate. 44100 Hz (CD quality) is recommended and matches most show media. Use 48000 Hz if your media is 48 kHz, or 22050 Hz for low-bandwidth."><img id="ll_samplerate_img" src="images/redesign/help-icon.svg" class="icon-help" alt="help"></span></td>
                    <td style="padding: 4px;">
                        <select id="ll_samplerate">
                            <option value="44100" <?php echo ($settings['sample_rate']==44100?'selected':''); ?>>44100 Hz (CD)</option>
                            <option value="48000" <?php echo ($settings['sample_rate']==48000?'selected':''); ?>>48000 Hz</option>
                            <option value="32000" <?php echo ($settings['sample_rate']==32000?'selected':''); ?>>32000 Hz</option>
                            <option value="22050" <?php echo ($settings['sample_rate']==22050?'selected':''); ?>>22050 Hz (low)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"><b>Channels:</b> <span id="ll_channels_tip" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="Number of audio channels. Stereo (2) preserves left/right. Mono (1) halves bandwidth and is fine for voice announcements."><img id="ll_channels_img" src="images/redesign/help-icon.svg" class="icon-help" alt="help"></span></td>
                    <td style="padding: 4px;">
                        <select id="ll_channels">
                            <option value="2" <?php echo ($settings['channels']==2?'selected':''); ?>>Stereo (2)</option>
                            <option value="1" <?php echo ($settings['channels']==1?'selected':''); ?>>Mono (1)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px;"></td>
                    <td style="padding: 4px;">
                        <input type="button" class="buttons" value="Save Settings" onclick="llConfig.save();">
                        <input type="button" class="buttons" value="Test Capture" onclick="llConfig.test();">
                        <span id="ll_save_result" style="margin-left:8px;"></span>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td><div id="ll_test_result" style="margin-top:4px;"></div></td>
                </tr>
            </table>
        </div>
    </fieldset>

    <br />

    <fieldset class="border p-3">
        <legend>How Audio Capture Works</legend>
        <div class="p-3">
            <p>This plugin streams the FPP's <b>live audio output</b> to your browser. It works best when FPP's audio is routed through an OS mixer that ffmpeg can capture:</p>
            <ul>
                <li><b>PulseAudio (recommended):</b> captures the <code>.monitor</code> source of the output device — exact copy of what the audience hears, including sequences + media + effects. Enable PulseAudio in FPP Settings → Audio if available.</li>
                <li><b>ALSA:</b> captures the hardware device (e.g. <code>default</code> or <code>hw:0,0</code>). You may need an ALSA loopback device (<code>snd-aloop</code>) if your audio hardware doesn't support concurrent capture.</li>
                <li><b>File Sync fallback:</b> if no capture device is available (or FFmpeg is missing), the plugin streams the <b>current media file</b> directly, seeking to the elapsed position so it's roughly in sync with the lights.</li>
            </ul>
            <p class="text-secondary" style="font-size:13px;">
                The stream is served as <code>audio/mpeg</code> at <code>api/plugin/fpp-ListenLive/stream</code> and played with a standard HTML5 &lt;audio&gt; element — no extra ports or Icecast server required.
                For best results, keep the browser tab on the same LAN as FPP to minimize latency (~1–3 seconds).
            </p>
        </div>
    </fieldset>

    <br />

    <fieldset class="border p-3">
        <legend>Quick Links</legend>
        <div class="p-3">
            <a href="plugin.php?plugin=fpp-ListenLive&page=listen.php" class="buttons">▶ Go to Listen Live</a>
            <a href="plugin.php?plugin=fpp-ListenLive&page=status.php" class="buttons">View Status</a>
        </div>
    </fieldset>
</div>

<script>
var llConfig = {
    save: function() {
        var data = {
            enabled: $('#ll_enabled').is(':checked') ? 1 : 0,
            source: $('#ll_source').val(),
            bitrate: $('#ll_bitrate').val(),
            sample_rate: parseInt($('#ll_samplerate').val(), 10),
            channels: parseInt($('#ll_channels').val(), 10),
            alsa_device: $('#ll_alsa_device').val() || 'default',
            pulse_source: $('#ll_pulse_source').val() || 'auto'
        };
        $('#ll_save_result').html('<span class="text-warning">Saving...</span>');
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/save',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    $('#ll_save_result').html('<span class="text-success">Saved!</span>');
                    $.jGrowl('Settings saved', { themeState: 'success' });
                } else {
                    $('#ll_save_result').html('<span class="text-danger">' + (resp.error || 'Failed') + '</span>');
                }
            },
            error: function(xhr) {
                var msg = 'Could not save';
                try { var r = JSON.parse(xhr.responseText); if (r.error) msg = r.error; } catch(e){}
                $('#ll_save_result').html('<span class="text-danger">' + msg + '</span>');
            }
        });
    },
    test: function() {
        $('#ll_test_result').html('<span class="text-warning">Testing audio capture (this may take a few seconds)...</span>');
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/test',
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(d) {
                var html = '<table class="fppTable" style="width:auto;">';
                if (d.results) {
                    for (var i=0;i<d.results.length;i++) {
                        var r=d.results[i];
                        html += '<tr><td>' + escHtml(r.check) + '</td><td>' + (r.ok ? '<span class="text-success">&#10003;</span>' : '<span class="text-danger">&#10007;</span>') + (r.detail ? ' <span class="text-secondary">(' + escHtml(r.detail) + ')</span>' : '') + '</td></tr>';
                    }
                }
                html += '</table>';
                if (d.success) html = '<span class="text-success"><b>All checks passed.</b></span><br>' + html;
                else html = '<span class="text-warning"><b>Some checks failed — see details.</b></span><br>' + html;
                $('#ll_test_result').html(html);
            },
            error: function() { $('#ll_test_result').html('<span class="text-danger">Test failed: could not reach API</span>'); }
        });
    },
    refreshSources: function() {
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/diagnostics',
            type: 'GET',
            dataType: 'json',
            success: function(d) {
                var det = d.detection || {};
                if (det.pulse_sources && det.pulse_sources.length) {
                    $('#ll_pulse_list').html('Detected Pulse sources: ' + escHtml(det.pulse_sources.join(', ')));
                } else {
                    $('#ll_pulse_list').html('No Pulse sources detected — is PulseAudio running?');
                }
                if (det.alsa_devices && det.alsa_devices.length) {
                    $('#ll_alsa_list').html('Detected ALSA devices: ' + escHtml(det.alsa_devices.slice(0,4).join(', ')));
                }
            }
        });
    }
};

function escHtml(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

$(document).ready(function(){
    $('#ll_source').on('change', function(){
        var v=$(this).val();
        $('#row_pulse').toggle(v !== 'alsa' && v !== 'file');
        $('#row_alsa').toggle(v !== 'pulse' && v !== 'file');
    });
    llConfig.refreshSources();
    if (typeof SetupToolTips === 'function') SetupToolTips();
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
