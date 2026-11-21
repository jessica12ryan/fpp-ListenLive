#!/bin/bash

#############################################################
## Listen Live Plugin for FPP (fpp-ListenLive)             ##
## Author: jessica12ryan                                   ##
## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
#############################################################
## Uninstall Script                                        ##
#############################################################

: "${FPPDIR:=/opt/fpp}"

# FPP's common defines MEDIADIR/FPPUSER/FPPGROUP and setSetting. Source it
# defensively so this script also works when run by hand. No `set -u` here, and
# nothing from common is written to disk by this script.
if [ -f "${FPPDIR}/scripts/common" ]; then
    . "${FPPDIR}/scripts/common" 2>/dev/null || true
elif [ -f "/opt/fpp/scripts/common" ]; then
    . "/opt/fpp/scripts/common" 2>/dev/null || true
fi

# Request fppd restart so the removed native component is unloaded. PluginManager
# only reads native plugins at startup (see lint_plugin.py RESTART_FLAG_SNIPPET).
if [ -f "${FPPDIR:-/opt/fpp}/scripts/common" ]; then
    ( set +u; source "${FPPDIR:-/opt/fpp}/scripts/common" && setSetting restartFlag 1 ) || true
elif [ -f "/opt/fpp/scripts/common" ]; then
    ( set +u; source "/opt/fpp/scripts/common" && setSetting restartFlag 1 ) || true
fi

# Kill any lingering ffmpeg streams from this plugin. Target our stream pattern
# only, skip non-numeric PIDs and never signal ourselves; safe to run twice.
if command -v pgrep >/dev/null 2>&1; then
    for pattern in "fpp-ListenLive/stream" "ffmpeg.*fpp-ListenLive"; do
        for pid in $(pgrep -f "$pattern" 2>/dev/null); do
            case "$pid" in ''|*[!0-9]*) continue ;; esac
            [ "$pid" = "$$" ] && continue
            kill "$pid" 2>/dev/null || true
        done
    done
fi

# Undo side effects outside the plugin directory only on a real uninstall.
# During a reinstall (FPP_PLUGIN_REINSTALL=1) the config and plugindata must
# survive so settings are not lost.
MEDIA_DIR="${MEDIADIR:-/home/fpp/media}"
case "$MEDIA_DIR" in ''|'/') MEDIA_DIR='/home/fpp/media' ;; esac
if [ "${FPP_PLUGIN_REINSTALL:-0}" != "1" ]; then
    rm -f "${MEDIA_DIR}/config/plugin.fpp-ListenLive" 2>/dev/null || true
    rm -rf "${MEDIA_DIR}/plugindata/fpp-ListenLive" 2>/dev/null || true
    echo "fpp-ListenLive: Removed plugin config and plugindata (not a reinstall)."
else
    echo "fpp-ListenLive: Reinstall detected - plugin config and plugindata retained."
fi

echo "fpp-ListenLive: Plugin uninstalled."
echo "fpp-ListenLive: Remove ${PLUGIN_DIR:-/home/fpp/media/plugins/fpp-ListenLive} to delete the plugin files."
