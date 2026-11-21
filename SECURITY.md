# Security Policy

## Supported Versions

Only the `main` branch is supported on FPP 8.x, 9.x, 10.x, and 11.x. Please update to the latest commit before reporting.

## Reporting a Vulnerability

Open a private issue via [GitHub Security Advisories](https://github.com/jessica12ryan/fpp-ListenLive/security/advisories/new) or email the maintainer. Do not open a public issue for sensitive reports.

## Notes

- The audio stream (`api/plugin/fpp-ListenLive/stream`) and media/API endpoints are **same-origin**: the browser talks only to the FPP host that serves the UI. No cross-origin headers are set, and the plugin opens no listening ports of its own.
- Audio is captured on the FPP host and streamed to the browser **on the LAN only**. Nothing is sent off-box, audio is not stored, and there is no telemetry, advertising, or donation tracking. The browser stores only its volume and Exact-sync toggle preferences in `localStorage`; mute is never persisted.
- There is no authentication beyond the FPP UI session. Do not expose the FPP UI (or this stream) to the public internet without a reverse proxy or VPN.
- FFmpeg commands are built with `escapeshellarg` and a fixed allow-list; user-controlled values (source, bitrate, device names) are validated server-side before use.
- Media requests are confined to FPP's media tree; paths outside it are rejected.
- Plugin management and FPPD controls are handled by FPP's own UI (Plugin Manager / FPP Settings).
