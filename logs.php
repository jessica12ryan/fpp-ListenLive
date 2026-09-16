<?php
$pluginDir = __DIR__;
$logDir = $GLOBALS['settings']['logDirectory'] ?? getenv('LOGDIR') ?: '/home/fpp/media/logs';
$logFile = rtrim($logDir, '/') . '/plugin-fpp-ListenLive.log';

$uiLevel = (int)($settings['uiLevel'] ?? 0);
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color); background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg); color: var(--bs-body-color); border-color: var(--bs-border-color); border-bottom-color: var(--bs-body-bg); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg); }
.log-table { width: 100%; max-width: 100%; border-collapse: collapse; font-family: 'Courier New', monospace; font-size: 12px; }
.log-table th { text-align: left; padding: 6px 8px; border-bottom: 2px solid var(--bs-border-color); white-space: nowrap; }
.log-table td { padding: 4px 8px; border-bottom: 1px solid var(--bs-border-color); vertical-align: top; word-break: break-word; }
.log-table tr:hover { background: var(--bs-tertiary-bg); }
.log-info { color: var(--bs-body-color); }
.log-success { color: var(--bs-success); }
.log-error { color: var(--bs-danger); }
.log-warning { color: var(--bs-warning); }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Plugin Log</legend>
        <div class="p-3">
            <p>
                <b>Log file:</b> <code><?php echo htmlspecialchars($logFile); ?></code>
                &nbsp;&nbsp;
                <input type="button" class="buttons" value="&#8635; Refresh" onclick="llLogs.refresh();">
            </p>
            <div id="log_container" class="table-responsive" style="max-height: 60vh; overflow-y: auto; border: 1px solid var(--bs-border-color); border-radius: 4px;">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th style="min-width: 10rem;">Date/Time</th>
                            <th style="min-width: 4rem;">Level</th>
                            <th style="min-width: 8rem;">Source</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody id="log_body">
                        <tr><td colspan="4" class="text-center text-secondary p-3">Loading logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </fieldset>
</div>

<script>
var llLogs = {
    refresh: function() {
        $('#log_body').html('<tr><td colspan="4" class="text-center text-secondary p-3">Loading logs...</td></tr>');
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/logs',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success && data.entries) {
                    if (data.entries.length === 0) {
                        $('#log_body').html('<tr><td colspan="4" class="text-center text-secondary p-3">No log entries found.</td></tr>');
                    } else {
                        var html = '';
                        for (var i = 0; i < data.entries.length; i++) {
                            var e = data.entries[i];
                            var cls = 'log-info';
                            if (e.level === 'SUCCESS') cls = 'log-success';
                            else if (e.level === 'ERROR') cls = 'log-error';
                            else if (e.level === 'WARNING') cls = 'log-warning';
                            html += '<tr class="' + cls + '">' +
                                '<td style="white-space:nowrap;">' + escHtml(e.timestamp) + '</td>' +
                                '<td><b>' + escHtml(e.level) + '</b></td>' +
                                '<td>' + escHtml(e.source) + '</td>' +
                                '<td>' + escHtml(e.message) + '</td>' +
                                '</tr>';
                        }
                        $('#log_body').html(html);
                    }
                } else {
                    $('#log_body').html('<tr><td colspan="4" class="text-center text-danger p-3">Error loading logs: ' + (data.error || 'Unknown error') + '</td></tr>');
                }
            },
            error: function(xhr) {
                var msg = 'Could not reach the plugin API.';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.error) msg = resp.error;
                } catch(e) {}
                $('#log_body').html('<tr><td colspan="4" class="text-center text-danger p-3">' + msg + '</td></tr>');
            }
        });
    }
};

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

$(document).ready(function() {
    llLogs.refresh();
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
