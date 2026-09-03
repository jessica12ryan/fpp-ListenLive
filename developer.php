<?php
$pluginDir = __DIR__;

$uiLevel = (int)($settings['uiLevel'] ?? 0);
$showLogsTab = $uiLevel >= 1;
$showDevTab = $uiLevel >= 3;
?>
<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
.btn-danger { background: #dc3545; color: #fff; border: 1px solid #dc3545; padding: 10px 28px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; box-sizing: border-box; white-space: nowrap; display: inline-block; }
.btn-danger:hover { background: #bb2d3b; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-warning { background: #e67e22; color: #fff; border: 1px solid #e67e22; padding: 10px 28px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; box-sizing: border-box; white-space: nowrap; display: inline-block; }
.btn-warning:hover { background: #d35400; }
.btn-warning:disabled { opacity: 0.6; cursor: not-allowed; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
.modal-box { background: #fff; color: #333; border-radius: 8px; padding: 24px 32px; max-width: 420px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); text-align: center; }
.modal-message { font-size: 15px; line-height: 1.5; margin-bottom: 20px; }
.modal-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.modal-btn { padding: 10px 24px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; border: none; }
.modal-btn-primary { background: #dc3545; color: #fff; }
.modal-btn-primary:hover { background: #bb2d3b; }
.modal-btn-warning { background: #e67e22; color: #fff; }
.modal-btn-warning:hover { background: #d35400; }
.modal-btn-default { background: #6c757d; color: #fff; }
.modal-btn-default:hover { background: #5c636a; }
.modal-btn-info { background: #0d6efd; color: #fff; }
.modal-btn-info:hover { background: #0b5ed7; }
.btn-info { background: #0d6efd; color: #fff; border: 1px solid #0d6efd; padding: 10px 28px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; box-sizing: border-box; white-space: nowrap; display: inline-block; }
.btn-info:hover { background: #0b5ed7; }
.btn-info:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<?php include __DIR__ . '/tabs.inc'; ?>

<div style="margin:0 auto;">
    <fieldset class="border p-3">
        <legend>Developer Tools</legend>
        <div class="p-3">

            <h3 style="color:#0d6efd;">Updates</h3>
            <p>
                Check whether the plugin is up to date with the latest version on GitHub.
            </p>
            <div style="display:flex; gap:10px; align-items:start;">
                <div>
                    <button type="button" class="btn-info" id="check_updates_btn" onclick="llDev.checkUpdates();">&#8635; Check for Updates</button>
                </div>
                <div id="update_result"></div>
            </div>

            <hr style="margin:20px 0;">

            <h3 style="color:#e67e22;">Plugin Management</h3>
            <p>
                Reinstall the plugin to apply file updates, or uninstall it from the system.
            </p>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-warning" id="reinstall_btn" onclick="llDev.reinstall();">&#9888; Reinstall Plugin</button>
                <button type="button" class="btn-danger" id="uninstall_btn" onclick="llDev.uninstall();">&#9888; Uninstall Plugin</button>
            </div>

            <hr style="margin:20px 0;">

            <h3 style="color:#dc3545;">Diagnostics</h3>
            <p>
                Run a quick check of FFmpeg, PulseAudio, ALSA, and FPPD reachability.
            </p>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-info" id="diag_btn" onclick="llDev.diagnose();">Run Diagnostics</button>
            </div>
            <div id="diag_result" style="margin-top:10px;"></div>

            <hr style="margin:20px 0;">

            <h3>Restart FPPD</h3>
            <p>Prompt FPPD to restart (useful after changing audio settings).</p>
            <div style="display:flex; gap:10px;">
                <button type="button" class="buttons" id="restart_btn" onclick="llDev.restartFPPD();">Restart FPPD</button>
            </div>
            <div id="restart_result" style="margin-top:8px;"></div>
        </div>
    </fieldset>
</div>

<div id="modal_overlay" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-message" id="modal_message"></div>
        <div class="modal-actions" id="modal_actions"></div>
    </div>
</div>

<script>
var llDev = {
    showModal: function(message, buttons) {
        $('#modal_message').html(message);
        var $actions = $('#modal_actions').empty();
        $.each(buttons, function(i, btn) {
            $actions.append(
                $('<button>', {
                    text: btn.label,
                    class: 'modal-btn ' + (btn['class'] || 'modal-btn-default'),
                    click: function() {
                        if (btn.onClick) btn.onClick();
                        else llDev.hideModal();
                    }
                })
            );
        });
        $('#modal_overlay').show();
    },
    hideModal: function() {
        $('#modal_overlay').hide();
    },
    showConfirm: function(message, onConfirm, confirmClass) {
        llDev.showModal(message, [
            { label: 'Cancel', 'class': 'modal-btn-default', onClick: llDev.hideModal },
            { label: 'Confirm', 'class': confirmClass || 'modal-btn-primary', onClick: function() { llDev.hideModal(); if (onConfirm) onConfirm(); } }
        ]);
    },
    showAlert: function(message) {
        llDev.showModal(message, [
            { label: 'OK', 'class': 'modal-btn-primary' }
        ]);
    },
    diagnose: function() {
        $('#diag_result').html('<span class="text-warning">Running diagnostics...</span>');
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
                $('#diag_result').html(html);
            },
            error: function() { $('#diag_result').html('<span class="text-danger">Failed to run diagnostics</span>'); }
        });
    },
    restartFPPD: function() {
        $('#restart_result').html('<span class="text-warning">Restarting...</span>');
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/restart-fppd',
            type: 'POST',
            contentType: 'application/json',
            data: '{}',
            dataType: 'json',
            success: function(d) { $('#restart_result').html('<span class="text-success">' + escHtml(d.message || 'Restart flag set') + '</span>'); },
            error: function() { $('#restart_result').html('<span class="text-danger">Failed</span>'); }
        });
    },
    checkUpdates: function() {
        $('#check_updates_btn').html('&#8635; Check for Updates').prop('disabled', true).attr('onclick', 'llDev.checkUpdates();');
        $('#update_result').html('<span style="color:#6c757d;">Checking...</span>');
        $.ajax({
            url: 'api/plugin/fpp-ListenLive/check-updates',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.updateAvailable) {
                    $('#check_updates_btn').html('&#8635; Install Updates').prop('disabled', false).attr('onclick', 'llDev.installUpdates();');
                    $('#update_result').html('<span style="color:#e67e22;font-weight:600;">&#9888; Update available!</span><br><span style="color:#6c757d;font-size:13px;">Local: ' + data.localSha + '<br>Remote: ' + data.remoteSha + '<br><a href="https://github.com/jessica12ryan/fpp-ListenLive" target="_blank">View on GitHub</a></span>');
                } else {
                    $('#check_updates_btn').html('&#8635; Check for Updates').prop('disabled', false).attr('onclick', 'llDev.checkUpdates();');
                    $('#update_result').html('<span style="color:#28a745;font-weight:600;">&#10003; Plugin is up to date</span><br><span style="color:#6c757d;font-size:13px;">' + data.localSha + '</span>');
                }
            },
            error: function(xhr) {
                $('#check_updates_btn').html('&#8635; Check for Updates').prop('disabled', false).attr('onclick', 'llDev.checkUpdates();');
                var msg = 'Could not reach the plugin API.';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.error) msg = resp.error;
                } catch(e) {}
                $('#update_result').html('<span style="color:#dc3545;">' + msg + '</span>');
            }
        });
    },
    installUpdates: function() {
        llDev.showConfirm('This will update the plugin to the latest version from GitHub. Your configuration will be preserved. Are you sure?', function() {
            $('#check_updates_btn').prop('disabled', true);
            $.ajax({
                url: 'api/plugin/fpp-ListenLive/update',
                type: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                success: function() {
                    location.reload();
                },
                error: function(xhr) {
                    var msg = 'Could not reach the plugin API.';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.error) msg = resp.error;
                    } catch(e) {}
                    $('#check_updates_btn').prop('disabled', false);
                    llDev.showAlert(msg);
                }
            });
        }, 'modal-btn-info');
    },
    reinstall: function() {
        llDev.showConfirm('This will reinstall the plugin. Your configuration will be preserved. Are you sure?', function() {
            $('#reinstall_btn').prop('disabled', true);
            $.ajax({
                url: 'api/plugin/fpp-ListenLive/reinstall',
                type: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                success: function() {
                    location.reload();
                },
                error: function(xhr) {
                    var msg = 'Could not reach the plugin API.';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.error) msg = resp.error;
                    } catch(e) {}
                    $('#reinstall_btn').prop('disabled', false);
                    llDev.showAlert(msg);
                }
            });
        }, 'modal-btn-warning');
    },
    uninstall: function() {
        llDev.showConfirm('This will completely remove the plugin and all its files. Configuration will be lost. FPPD will be prompted to restart. Are you sure?', function() {
            $('#uninstall_btn').prop('disabled', true);
            $.ajax({
                url: 'api/plugin/fpp-ListenLive/uninstall',
                type: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                success: function() {
                    window.location.href = 'plugins.php?tab=available';
                },
                error: function(xhr) {
                    var msg = 'Could not reach the plugin API.';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.error) msg = resp.error;
                    } catch(e) {}
                    $('#uninstall_btn').prop('disabled', false);
                    llDev.showAlert(msg);
                }
            });
        }, 'modal-btn-primary');
    }
};

function escHtml(s){ if(s==null)return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

$(document).on('click', '#modal_overlay', function(e) {
    if (e.target === this) llDev.hideModal();
});
</script>

<?php include __DIR__ . '/footer.inc'; ?>
