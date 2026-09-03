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

# --- Preserve user config ---
if [ -f "${PLUGIN_DIR}/config/settings.json" ]; then
    cp "${PLUGIN_DIR}/config/settings.json" "/tmp/fpp-ListenLive-settings-backup.json" 2>/dev/null || true
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
if [ -f "/tmp/fpp-ListenLive-settings-backup.json" ]; then
    mkdir -p "${PLUGIN_DIR}/config" 2>/dev/null || true
    mv "/tmp/fpp-ListenLive-settings-backup.json" "${PLUGIN_DIR}/config/settings.json" 2>/dev/null || true
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
    echo "fpp-ListenLive: WARNING - ffmpeg not found. Install with: sudo apt update && sudo apt install -y ffmpeg"
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

echo "fpp-ListenLive: Plugin installed successfully."
echo "fpp-ListenLive: Go to Content Setup -> Listen Live to start listening."
