#!/bin/bash
set -euo pipefail

#############################################################
## Listen Live Plugin for FPP (fpp-ListenLive)             ##
## Author: jessica12ryan                                   ##
## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
#############################################################
## Install/Update Script                                   ##
#############################################################

PLUGIN_DIR="/home/fpp/media/plugins/fpp-ListenLive"
if [ ! -d "$PLUGIN_DIR" ] && [ -n "${MEDIADIR:-}" ]; then
    PLUGIN_DIR="${MEDIADIR}/plugins/fpp-ListenLive"
fi
# Fallback: script is in plugin dir
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -z "$PLUGIN_DIR" ] || [ ! -d "$(dirname "$PLUGIN_DIR")" ]; then
    PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
fi

# --- Plugin-owned data lives under ${MEDIADIR}/plugindata/fpp-ListenLive (§5) ---
# Canonical settings are FPP's config/plugin.fpp-ListenLive written by
# WriteSettingToFile(); we never create config/settings.json in the plugin dir.
PLUGINDATA_DIR="${MEDIADIR:-/home/fpp/media}/plugindata/fpp-ListenLive"
mkdir -p "$PLUGINDATA_DIR" 2>/dev/null || true
chmod 0755 "$PLUGINDATA_DIR" 2>/dev/null || true

# One-time migration: move a legacy plugin-local config/settings.json into
# plugindata so it survives a reinstall. It is NOT restored into the plugin dir.
if [ -f "${PLUGIN_DIR}/config/settings.json" ] && [ ! -f "${PLUGINDATA_DIR}/settings.json" ]; then
    mv "${PLUGIN_DIR}/config/settings.json" "${PLUGINDATA_DIR}/settings.json" 2>/dev/null || true
    chmod 0644 "${PLUGINDATA_DIR}/settings.json" 2>/dev/null || true
fi

# Tighten any leftover config dir from older installs (never group-write).
if [ -d "${PLUGIN_DIR}/config" ]; then
    chmod 0755 "${PLUGIN_DIR}/config" 2>/dev/null || true
    find "${PLUGIN_DIR}/config" -type f -exec chmod 0644 {} + 2>/dev/null || true
fi

# Ensure scripts are executable (0755 files) without loosening other permissions.
chmod 0755 "${PLUGIN_DIR}/scripts" 2>/dev/null || true
for f in "${PLUGIN_DIR}/scripts/"*.sh "${PLUGIN_DIR}/scripts/"*.php; do
    [ -f "$f" ] && chmod 0755 "$f" 2>/dev/null || true
done

# Check ffmpeg (FPP installs the declared dependency on supported versions)
if ! command -v ffmpeg >/dev/null 2>&1; then
    echo "fpp-ListenLive: WARNING - ffmpeg not found. Install with: apt update && apt install -y ffmpeg"
    echo "fpp-ListenLive: File-sync fallback will be used until ffmpeg is available."
else
    echo "fpp-ListenLive: ffmpeg found at $(command -v ffmpeg)"
fi

# Check for pulse/alsa
if command -v pactl >/dev/null 2>&1; then
    echo "fpp-ListenLive: PulseAudio tools available"
fi
if [ -f /proc/asound/cards ]; then
    echo "fpp-ListenLive: ALSA detected"
fi

# Build native sync plugin (frame-exact MultiSync) if build tools and FPP headers
# are present. This provides /api/plugin-apis/ListenLive/sync and /ListenLive/clock
# with a monotonic clock; the file-sync fallback works without it, but exact sync
# requires the .so. Pass SRCDIR explicitly so the build uses the installed FPP
# headers regardless of where FPPDIR points.
FPP_SRC_DIR="${FPPDIR:-/opt/fpp}/src"
if [ -f "${FPP_SRC_DIR}/Plugin.h" ] && [ -f "${PLUGIN_DIR}/Makefile" ]; then
    echo "fpp-ListenLive: Building native component..."
    if make -C "${PLUGIN_DIR}" SRCDIR="${FPP_SRC_DIR}" -j"$(nproc 2>/dev/null || echo 1)" 2>&1; then
        echo "fpp-ListenLive: Native component built."
        # dlopen needs only read access, so 0644 suffices for fppd (fpp or root).
        # No chown is needed; if a future FPP needs one, target only these files.
        find "${PLUGIN_DIR}" -maxdepth 1 -type f -name 'libfpp-ListenLive.*' -exec chmod 0644 {} + 2>/dev/null || true
    else
        echo "fpp-ListenLive: Native build failed - file-sync fallback will be used. Check ${FPP_SRC_DIR} exists and build tools are installed (build-essential)."
    fi
else
    echo "fpp-ListenLive: Skipping native build (FPP headers not found at ${FPP_SRC_DIR}/Plugin.h) - file-sync fallback will be used."
fi

# Request fppd restart so new native component is picked up. PluginManager only
# reads native plugins at startup, and hot-load is FPP 10+ only; FPP 8/9 still
# need a full restart. The safe snippet handles set -u and missing FPPDIR (see
# lint_plugin.py RESTART_FLAG_SNIPPET).
if [ -f "${FPPDIR:-/opt/fpp}/scripts/common" ]; then
    ( set +u; source "${FPPDIR:-/opt/fpp}/scripts/common" && setSetting restartFlag 1 ) || true
elif [ -f "/opt/fpp/scripts/common" ]; then
    ( set +u; source "/opt/fpp/scripts/common" && setSetting restartFlag 1 ) || true
fi

echo "fpp-ListenLive: Plugin installed successfully."
echo "fpp-ListenLive: Go to Content Setup -> Listen Live to start listening."
