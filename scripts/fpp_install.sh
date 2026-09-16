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

# --- Preserve user config (FPP plugin config + legacy JSON) ---
# Keep backup in plugindata (§5) so it survives git clean and is not in /tmp
PLUGINDATA_DIR="${MEDIADIR:-/home/fpp/media}/plugindata/fpp-ListenLive"
mkdir -p "$PLUGINDATA_DIR" 2>/dev/null || true
if [ -f "${PLUGIN_DIR}/config/settings.json" ]; then
    cp "${PLUGIN_DIR}/config/settings.json" "$PLUGINDATA_DIR/settings-backup.json" 2>/dev/null || true
fi
# Also preserve FPP's own plugin config file if present
if [ -n "${MEDIADIR:-}" ] && [ -f "${MEDIADIR}/config/plugin.fpp-ListenLive" ]; then
    cp "${MEDIADIR}/config/plugin.fpp-ListenLive" "$PLUGINDATA_DIR/plugin.fpp-ListenLive.bak" 2>/dev/null || true
elif [ -f "/home/fpp/media/config/plugin.fpp-ListenLive" ]; then
    cp "/home/fpp/media/config/plugin.fpp-ListenLive" "$PLUGINDATA_DIR/plugin.fpp-ListenLive.bak" 2>/dev/null || true
fi

# Self-update from git if available
if [ -d "${PLUGIN_DIR}/.git" ]; then
    git -C "$PLUGIN_DIR" fetch origin 2>/dev/null || true
    LOCAL=$(git -C "$PLUGIN_DIR" rev-parse HEAD 2>/dev/null || echo "")
    REMOTE=$(git -C "$PLUGIN_DIR" rev-parse origin/main 2>/dev/null || echo "")
    if [ -n "$LOCAL" ] && [ -n "$REMOTE" ] && [ "$LOCAL" != "$REMOTE" ]; then
        echo "fpp-ListenLive: Self-updating from GitHub..."
        git -C "$PLUGIN_DIR" checkout -- . 2>/dev/null || true
        git -C "$PLUGIN_DIR" clean -fd 2>/dev/null || true
        git -C "$PLUGIN_DIR" reset --hard origin/main 2>/dev/null || true
        # Re-exec updated script
        exec "${PLUGIN_DIR}/scripts/fpp_install.sh" "$@"
    fi
fi

# Restore config
if [ -f "$PLUGINDATA_DIR/settings-backup.json" ]; then
    mkdir -p "${PLUGIN_DIR}/config" 2>/dev/null || true
    mv "$PLUGINDATA_DIR/settings-backup.json" "${PLUGIN_DIR}/config/settings.json" 2>/dev/null || true
fi
if [ -f "$PLUGINDATA_DIR/plugin.fpp-ListenLive.bak" ]; then
    if [ -n "${MEDIADIR:-}" ] && [ -d "${MEDIADIR}/config" ]; then
        mv "$PLUGINDATA_DIR/plugin.fpp-ListenLive.bak" "${MEDIADIR}/config/plugin.fpp-ListenLive" 2>/dev/null || true
    elif [ -d "/home/fpp/media/config" ]; then
        mv "$PLUGINDATA_DIR/plugin.fpp-ListenLive.bak" "/home/fpp/media/config/plugin.fpp-ListenLive" 2>/dev/null || true
    else
        rm -f "$PLUGINDATA_DIR/plugin.fpp-ListenLive.bak" 2>/dev/null || true
    fi
fi

# Ensure config exists
mkdir -p "${PLUGIN_DIR}/config" 2>/dev/null || true
if [ ! -f "${PLUGIN_DIR}/config/settings.json" ]; then
    cat > "${PLUGIN_DIR}/config/settings.json" <<'EOF'
{
  "enabled": 1,
  "source": "auto",
  "bitrate": "128k",
  "sample_rate": 44100,
  "channels": 2,
  "alsa_device": "default",
  "pulse_source": "auto",
  "volume": 100
}
EOF
fi

# Fix permissions so FPP web server (fpp user) can read/write
if chown -R fpp:fpp "${PLUGIN_DIR}/config" 2>/dev/null || chown -R :fpp "${PLUGIN_DIR}/config" 2>/dev/null; then
    chmod 775 "${PLUGIN_DIR}/config" 2>/dev/null || true
    find "${PLUGIN_DIR}/config" -type f -exec chmod 664 {} + 2>/dev/null || true
else
    chmod 775 "${PLUGIN_DIR}/config" 2>/dev/null || true
    find "${PLUGIN_DIR}/config" -type f -exec chmod 664 {} + 2>/dev/null || true
fi

# Ensure scripts are executable
chmod +x "${PLUGIN_DIR}/scripts/"*.sh 2>/dev/null || true
chmod +x "${PLUGIN_DIR}/scripts/"*.php 2>/dev/null || true

# Check ffmpeg
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

# Build native sync plugin (frame-exact MultiSync) if build tools and FPP headers are present
# This provides /api/plugin-apis/ListenLive/sync and /ListenLive/clock with monotonic clock;
# file-sync fallback works without it, but exact sync requires the .so.
if [ -f "${FPPDIR:-/opt/fpp}/src/Plugin.h" ] && [ -f "${PLUGIN_DIR}/Makefile" ]; then
    echo "fpp-ListenLive: Building native component..."
    if make -C "${PLUGIN_DIR}" -j$(nproc 2>/dev/null || echo 1) 2>&1; then
        echo "fpp-ListenLive: Native component built."
        # Ensure .so is owned by fpp so fppd (running as fpp or root) can dlopen it
        chown fpp:fpp "${PLUGIN_DIR}/libfpp-ListenLive.so" 2>/dev/null || chown :fpp "${PLUGIN_DIR}/libfpp-ListenLive.so" 2>/dev/null || true
        chmod 644 "${PLUGIN_DIR}/libfpp-ListenLive.so" 2>/dev/null || true
    else
        echo "fpp-ListenLive: Native build failed - file-sync fallback will be used. Check /opt/fpp/src exists and build tools are installed (build-essential)."
    fi
else
    echo "fpp-ListenLive: Skipping native build (FPP headers not found at ${FPPDIR:-/opt/fpp}/src/Plugin.h) - file-sync fallback will be used."
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
