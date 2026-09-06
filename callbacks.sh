#!/bin/bash
# ListenLive C++ sync plugin — tells FPP to load libfpp-ListenLive.so
for var in "$@"
do
    case $var in
        -l|--list)
            echo "c++"
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
