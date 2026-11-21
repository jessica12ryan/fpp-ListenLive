<?php
/**
 * Shared UI settings loader for plugin pages.
 *
 * Single source of truth, mirroring llLoadSettings() in api.php:
 *   1. FPP's plugin config store via ReadSettingFromFile() when available
 *      (common.php is loaded by the FPP page framework — do not overwrite
 *      the global $settings; read via function_exists guard).
 *   2. Legacy JSON at config/settings.json (migration fallback).
 *   3. Built-in defaults.
 *
 * Usage in pages (after FPP framework include):
 *   require_once __DIR__ . '/ui_settings.php';
 *   $llSettings = llUISettings();
 */
function llUISettings() {
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
    if (function_exists('ReadSettingFromFile')) {
        $found = false;
        $loaded = [];
        foreach (array_keys($defaults) as $k) {
            $v = @ReadSettingFromFile($k, 'fpp-ListenLive');
            if ($v !== false && $v !== null && $v !== '') {
                if (in_array($k, ['enabled','sample_rate','channels','volume'], true)) {
                    $loaded[$k] = is_numeric($v) ? (int)$v : $v;
                } else {
                    $loaded[$k] = $v;
                }
                $found = true;
            }
        }
        if ($found) {
            return array_merge($defaults, $loaded);
        }
    }
    // Migration fallback: the installer moves a legacy plugin-dir settings.json
    // into plugindata. Canonical settings are always FPP's plugin config store.
    $md = isset($GLOBALS['settings']['mediaDirectory']) ? rtrim($GLOBALS['settings']['mediaDirectory'], '/') : (getenv('MEDIADIR') ?: '/home/fpp/media');
    $f = $md . '/plugindata/fpp-ListenLive/settings.json';
    if (!is_readable($f)) $f = __DIR__ . '/config/settings.json';
    if (is_readable($f)) {
        $s = json_decode(@file_get_contents($f), true);
        if (is_array($s)) return array_merge($defaults, array_intersect_key($s, $defaults));
    }
    return $defaults;
}
