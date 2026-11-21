# Contributing

Thanks for considering a contribution to fpp-ListenLive!

## Development

1. Fork and clone.
2. Edit code; on FPP the plugin lives under `/home/fpp/media/plugins/fpp-ListenLive`.
3. Run `bash scripts/fpp_install.sh` to apply permissions and check ffmpeg. FPP runs its installer as root — do not use `sudo`.
4. Refresh the FPP UI and open Content Setup → Listen Live.

## Host tests

These run on a normal development machine with PHP and Python 3 (no FPP required):

```bash
# Syntax-check every PHP file you touched (and the rest of the plugin)
find . -name '*.php' -o -name '*.inc' | xargs -n1 php -l

# Timing helper unit tests
php test/timing_test.php

# Glue/lint checks (requires test/timing_test.php and consistent JS/PHP wiring)
python3 scripts/lint_glue.py
```

All three must pass before opening a pull request.

## Web integration tests (fpp-tests)

The browser/end-to-end suite is the **fpp-tests** web suite. It requires **Docker**: the suite provisions an FPP environment in containers, so Docker must be installed and running on the host. Run the suite from your fpp-tests checkout by following its README to bring up the FPP container and execute the web tests, then point it at this plugin checkout. If Docker is unavailable, at minimum verify the JSON endpoints on a real FPP host, e.g.:

```bash
curl http://<fpp-host>/api/plugin/fpp-ListenLive/status
```

The native component (`libfpp-ListenLive.so`) builds with `make` against FPP's headers and toolchain (`/opt/fpp/src`), so a native build also needs a real FPP host or an FPP/Docker image — it does not build from a stock macOS/Linux checkout.

## Pull Requests

- Keep changes focused; one feature/fix per PR.
- Match existing code style (PHP: 4-space indents, `//` comments concise, headers with author block).
- Test on FPP 8.x–11.x if possible; at minimum verify the JSON endpoints via `curl` on a supported FPP host.
- Update the docs you own (README, help fragments, about, API docs) when behavior changes.

## Reporting Issues

Include: FPP version, ffmpeg version (`ffmpeg -version`), capture backend detection output (Config → Test Capture / Status → Diagnostics), the `#ll_exact_status` text and whether the best-effort / NOT frame-exact fallback label appeared, and relevant lines from `<mediadir>/logs/plugin-fpp-ListenLive.log`.
