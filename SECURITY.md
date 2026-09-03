# Security Policy

## Supported Versions

Only the `main` branch is supported. Please update to the latest commit before reporting.

## Reporting a Vulnerability

Open a private issue via [GitHub Security Advisories](https://github.com/jessica12ryan/fpp-ListenLive/security/advisories/new) or email the maintainer. Do not open a public issue for sensitive reports.

## Notes

- This plugin streams audio as `audio/mpeg` without authentication beyond the FPP UI session. Do not expose the FPP UI to the public internet without a reverse proxy or VPN.
- FFmpeg commands are built with `escapeshellarg` and a fixed allow-list; user-controlled values (source, bitrate, device names) are validated server-side before use.
- The stream endpoint disables caching and sets `Access-Control-Allow-Origin: *` for same-origin playback; restrict at the reverse proxy if needed.
