#!/bin/bash

#############################################################
## Listen Live Plugin for FPP (fpp-ListenLive)             ##
## Author: jessica12ryan                                   ##
## URL: https://github.com/jessica12ryan/fpp-ListenLive    ##
#############################################################
## Uninstall Script                                        ##
#############################################################

: "${FPPDIR:=/opt/fpp}"
if [ -f "${FPPDIR}/scripts/common" ]; then
    . "${FPPDIR}/scripts/common"
    setSetting restartFlag 1
fi

# Kill any lingering ffmpeg streams from this plugin (avoid killing this script itself)
# Use pgrep to find ffmpeg with our stream URL, not the script name
if command -v pgrep >/dev/null 2>&1; then
    for pid in $(pgrep -f "fpp-ListenLive/stream" 2>/dev/null); do
        kill "$pid" 2>/dev/null || true
    done
    for pid in $(pgrep -f "ffmpeg.*fpp-ListenLive" 2>/dev/null); do
        kill "$pid" 2>/dev/null || true
    done
else
    pkill -f "ffmpeg.*fpp-ListenLive/stream" 2>/dev/null || true
fi

echo "fpp-ListenLive: Plugin uninstalled (config retained in case of reinstall)."
echo "fpp-ListenLive: Remove /home/fpp/media/plugins/fpp-ListenLive manually to delete all files."
