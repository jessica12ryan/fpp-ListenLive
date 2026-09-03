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

# Kill any lingering ffmpeg streams from this plugin
pkill -f "fpp-ListenLive" 2>/dev/null || true

echo "fpp-ListenLive: Plugin uninstalled (config retained in case of reinstall)."
echo "fpp-ListenLive: Remove /home/fpp/media/plugins/fpp-ListenLive manually to delete all files."
