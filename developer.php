<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## developer.php                                           ##
 * #############################################################
 */
$uiLevel = (int)($settings['uiLevel'] ?? 0);
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Developer Tools</legend>
        <div class="p-3">

            <h3 class="text-primary">Updates</h3>
            <p>Check whether a newer version is available on GitHub. To install updates, reinstall, or remove the plugin, use <b>Content Setup → Plugin Manager</b> — the plugin never performs those actions itself.</p>
            <div>
                <button type="button" class="buttons" id="check_updates_btn" onclick="llDev.checkUpdates();">&#8635; Check for Updates</button>
                <span id="update_result" class="ms-2"></span>
            </div>

            <hr class="my-3">

            <h3>Diagnostics</h3>
            <p>Run a quick check of FFmpeg, PulseAudio, ALSA, PipeWire, and FPPD reachability.</p>
            <div>
                <button type="button" class="buttons" id="diag_btn" onclick="llDev.diagnose();">Run Diagnostics</button>
            </div>
            <div id="diag_result" class="mt-2"></div>

            <hr class="my-3">

            <h3>Plugin Management</h3>
            <p class="text-secondary">Installation, updates, reinstalls, uninstalls and FPPD restarts are managed by FPP. This page never restarts FPPD or changes files outside this plugin.</p>
        </div>
    </fieldset>
</div>

<script>
var llDev = {
    diagnose: function() {
        $('#diag_result').html('<span class="text-warning">Running diagnostics...</span>');
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/test',
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(d) {
                var html = '<div class="table-responsive"><table class="fppTable table">';
                if (d.results) {
                    for (var i = 0; i < d.results.length; i++) {
                        var r = d.results[i];
                        html += '<tr><td>' + escHtml(r.check) + '</td><td>' +
                            (r.ok ? '<span class="text-success">&#10003;</span>' : '<span class="text-danger">&#10007;</span>') +
                            (r.detail ? ' <span class="text-secondary small">(' + escHtml(r.detail) + ')</span>' : '') +
                            '</td></tr>';
                    }
                }
                html += '</table></div>';
                $('#diag_result').html(html);
            },
            error: function() { $('#diag_result').html('<span class="text-danger">Failed to run diagnostics</span>'); }
        });
    },
    checkUpdates: function() {
        $('#check_updates_btn').prop('disabled', true);
        $('#update_result').html('<span class="text-secondary">Checking...</span>');
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/check-updates',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var msg = data.updateAvailable
                    ? '<span class="text-warning fw-semibold">Update available</span>'
                    : '<span class="text-success fw-semibold">Up to date</span>';
                if (data.localSha) msg += ' <span class="text-secondary small">' + escHtml(data.localSha) + '</span>';
                msg += ' <a href="https://github.com/jessica12ryan/fpp-ListenLive" target="_blank" rel="noopener">View on GitHub</a>';
                $('#update_result').html(msg);
            },
            error: function() { $('#update_result').html('<span class="text-danger">Could not check for updates</span>'); },
            complete: function() { $('#check_updates_btn').prop('disabled', false); }
        });
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php include __DIR__ . '/footer.inc'; ?>
