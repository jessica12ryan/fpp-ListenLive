# Contributing

Thanks for considering a contribution to fpp-ListenLive!

## Development

1. Fork and clone.
2. Edit code; plugin lives under `/home/fpp/media/plugins/fpp-ListenLive` on FPP.
3. Run `bash scripts/fpp_install.sh` to apply permissions and check ffmpeg.
4. Refresh the FPP UI and open Content Setup → Listen Live.

## Pull Requests

- Keep changes focused; one feature/fix per PR.
- Match existing code style (PHP: 4-space indents, `//` comments concise, headers with author block).
- Test on FPP 8+ if possible; at minimum verify JSON endpoints via `curl http://fpp/api/plugin/fpp-ListenLive/status`.

## Reporting Issues

Include: FPP version, ffmpeg version (`ffmpeg -version`), Pulse/ALSA detection output (Config → Test Capture), and relevant lines from `/home/fpp/media/logs/plugin-fpp-ListenLive.log`.
