<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## tabs.inc                                                ##
 * ## Shared tab bar rendered by all plugin pages.            ##
 * #############################################################
 */

function ll_tab_active($page)
{
	$current = $_GET['page'] ?? basename(debug_backtrace()[0]['file']);
	return $current === $page ? 'active' : '';
}

// Requires $showLogsTab and $showDevTab to be set by caller before include.
// If caller hasn't set them (e.g. direct include), derive from FPP global $settings.
// Check $GLOBALS first so a plugin-overwritten $settings (plugin config) doesn't hide tabs.
if (!isset($showLogsTab) || !isset($showDevTab)) {
	$_ll_uiLevel = 0;
	if (isset($GLOBALS['settings']['uiLevel'])) {
		$_ll_uiLevel = (int)$GLOBALS['settings']['uiLevel'];
	} elseif (isset($settings['uiLevel'])) {
		$_ll_uiLevel = (int)$settings['uiLevel'];
	}
	if (!isset($showLogsTab)) $showLogsTab = $_ll_uiLevel >= 1;
	if (!isset($showDevTab)) $showDevTab = $_ll_uiLevel >= 3;
}

$tabs = Array(
	Array('page' => 'listen.php',    'icon' => '&#9654;', 'label' => 'Listen'),
	Array('page' => 'status.php',    'icon' => '&#9632;', 'label' => 'Status'),
	Array('page' => 'config.php',    'icon' => '&#9881;', 'label' => 'Config'),
	Array('page' => 'logs.php',      'icon' => '&#9776;', 'label' => 'Logs',   'show' => $showLogsTab),
	Array('page' => 'help.php',      'icon' => '&#63;',   'label' => 'Help'),
	Array('page' => 'about.php',     'icon' => '&#9432;', 'label' => 'About'),
	Array('page' => 'developer.php', 'icon' => '&#9881;', 'label' => 'Developer', 'show' => $showDevTab)
);
?>

<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
@media only screen and (max-width: 480px) {
    .tab-bar { flex-direction: column; }
    .tab-bar a { border-radius: 4px; margin-bottom: 3px; border-bottom: 1px solid var(--bs-border-color, #dee2e6); }
}
</style>

<div class="tab-bar">
<?php foreach ($tabs as $tab): ?>
<?php if (isset($tab['show']) && !$tab['show']) continue; ?>
	<a href="plugin.php?plugin=fpp-ListenLive&page=<?php echo $tab['page']; ?>" class="<?php echo ll_tab_active($tab['page']); ?>"><?php echo $tab['icon']; ?> <?php echo $tab['label']; ?></a>
<?php endforeach; ?>
</div>
