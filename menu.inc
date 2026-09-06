<?php
/**
 * #############################################################
 * ## Listen Live Plugin for FPP (fpp-ListenLive)             ##
 * ## Author: jessica12ryan                                   ##
 * ## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
 * #############################################################
 * ## menu.inc                                                ##
 * #############################################################
 */

$llIconUrl = 'api/plugin/fpp-ListenLive/icon?v=' . filemtime(__DIR__ . '/icon.png');
$menuEntries = Array(
	Array(
		'text' => '<img src="' . $llIconUrl . '" width="16" height="16" class="align-middle me-1"> Listen Live',
		'type' => 'content',
		'page' => 'config.php',
		'wrap' => 1
	),
	Array(
		'text' => '<img src="' . $llIconUrl . '" width="16" height="16" class="align-middle me-1"> Listen Live',
		'type' => 'status',
		'page' => 'listen.php',
		'wrap' => 1
	),
	Array(
		'text' => '<img src="' . $llIconUrl . '" width="16" height="16" class="align-middle me-1"> Listen Live',
		'type' => 'help',
		'page' => 'about.php',
		'wrap' => 1
	)
);

foreach ($menuEntries as $entry)
{
	if ($entry['type'] != $menu)
		continue;

	if (preg_match('/^http.?:\/\//', $entry['page']))
	{
		printf("<li><a href='%s' target='_blank'>%s</a></li>\n",
			$entry['page'], $entry['text']);
	}
	else
	{
		$nopage = '';
		if (isset($entry['wrap']) && ($entry['wrap'] == 0))
			$nopage = '&nopage=1';

		printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n",
			$plugin, $entry['page'], $nopage, $entry['text']);
	}
}
?>
