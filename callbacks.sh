#!/bin/bash
# ListenLive C++ sync plugin — tells FPP to load libfpp-ListenLive.so
for var in "$@"
do
    case $var in
        -l|--list)
            # Only advertise C++ if the native library was actually built.
            # File-sync fallback works without it, so avoid Warning ID 5
            # ("Could not load plugin") when headers/build tools were missing.
            DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
            if [ -f "${DIR}/libfpp-ListenLive.so" ] || [ -f "${DIR}/libfpp-ListenLive.dylib" ]; then
                echo "c++"
            fi
            exit 0
        ;;
        -h|--help)
            echo "ListenLive callbacks"
            exit 0
        ;;
        -v|--version)
            echo "1.0"
            exit 0
        ;;
        --)
            break
        ;;
        *)
            echo "Unknown option $var" >&2
            exit 1
        ;;
    esac
done
