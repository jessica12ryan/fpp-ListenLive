# <img src="icon.png" alt="icon" width="32" style="vertical-align: middle;"> Listen Live Plugin for FPP (fpp-ListenLive)

> **Hear your show in the FPP UI — frame-locked to FPP's clock with Web Audio when MultiSync is available, with a clearly labeled best-effort live-stream fallback.**

## What It Does

Listen Live lets you monitor the audio FPP is sending to your audience from **Content Setup → Listen Live**, without extra hardware or an external server.

- **Exact frame sync (primary path):** Web Audio is on by default on the first visit. The player reads FPP's native MultiSync clock, estimates its offset with a monotonic round-trip measurement (never by comparing browser and server wall clocks), and anchors a Web Audio timeline so each chunk's content time equals the FPP master position at the moment it is scheduled to sound. It fetches **0.5 s raw PCM chunks** from `api/plugin/fpp-ListenLive/media?file=...&seek=...&duration=0.5&format=pcm` and builds `AudioBuffer`s directly — no MP3 encoder/decoder priming. About **0.5 s** is queued (more when the tab is hidden). Drift is measured against the master clock and corrected with small nudges or a hard resync; the live value is shown in `#ll_exact_status`.
- **MultiSync:** the native plugin hooks both **media** and **sequence** sync packets, so a playlist of `.fseq` sequences is timed from the same master clock as an MP3. Enable **MultiSync** in FPP Settings for exact timing across players and remotes. "Exact" is only claimed when the API reports `source: c++` and `confidence: exact`; otherwise the status is explicitly labeled **NOT frame-exact** even though audio keeps playing.
- **Automatic raw-stream fallback:** if Web Audio cannot fetch or decode media chunks (for example, no media file or a decode failure), after a few failures the player switches to the raw live stream `api/plugin/fpp-ListenLive/stream` (`audio/mpeg`) and clearly labels it **best-effort / NOT frame-exact**. That stream is a live capture of FPP's audio output: it carries whatever is playing now, but browser buffering adds unmeasured latency, so it cannot be frame-locked to MultiSync.
- Live status shows the current playlist, sequence/song, and elapsed time; the Listen page polls status every **500 ms**.
- Volume is saved per browser in `localStorage`. **Mute is never persisted** — every page load starts unmuted.

## Requirements

- **FPP 8.x, 9.x, 10.x, or 11.x** (all four version ranges are declared in `pluginInfo.json`).
- **ffmpeg** on the FPP host. It is declared as a dependency in `pluginInfo.json`: FPP Plugin Manager installs it on FPP 10+, and the installer handles older releases. If you need to install it yourself on an older release, run `apt update && apt install -y ffmpeg` as root on the FPP host.
- **MultiSync enabled** (FPP Settings → MultiSync) for exact frame sync across players/remotes. Without it the player still works using the PHP status-poll fallback.
- A capture backend for the raw stream: PipeWire/PulseAudio monitor sources are preferred, or an ALSA capture device. File Sync can also be selected as the capture source.
- A modern browser with Web Audio support, on the same LAN as FPP.

## Installation

### Plugin Manager (recommended)

1. In the FPP UI, go to **Content Setup → Plugin Manager**.
2. Paste this URL and click **Install**:
   ```
   https://raw.githubusercontent.com/jessica12ryan/fpp-ListenLive/main/pluginInfo.json
   ```
3. Or find "Listen Live" in the available plugins list. FPP runs the installer as root and installs declared dependencies.

### Manual

```bash
cd /home/fpp/media/plugins
git clone https://github.com/jessica12ryan/fpp-ListenLive.git fpp-ListenLive
bash fpp-ListenLive/scripts/fpp_install.sh
```

Do **not** use `sudo`: FPP runs its plugin installer as root already. If you install by hand over SSH, run the installer while logged in as root.

## Usage

1. In FPP Settings → MultiSync, **enable MultiSync** if you want exact sync across players/remotes.
2. Start a playlist or schedule so audio is playing.
3. Open **Content Setup → Listen Live** and click **▶ Play**. Exact frame sync is already on by default on the first visit.
4. The **Exact frame sync** checkbox on the Listen page turns Web Audio scheduling on or off. While it is active, `#ll_exact_status` shows the sync state and drift. If chunk playback fails a few times, the status text shows the best-effort / NOT frame-exact fallback label.
5. Adjust **Volume** with the slider — it is stored in `localStorage` per browser. **Mute is not stored**; every page load starts unmuted.
6. The header shows **● LIVE** while audio is streaming, and the **Now Playing** line shows the current media. Status is polled every 500 ms.

The raw stream can also be opened directly in VLC or another player:

```
http://<fpp-host>/api/plugin/fpp-ListenLive/stream
```

Treat this URL as best-effort monitoring: it is not frame-exact and has unmeasured browser/network buffering.

## Configuration

Open **Content Setup → Listen Live → Config**:

| Setting | Description |
|---------|-------------|
| **Enable Live Streaming** | Master on/off. When off, `/stream` returns HTTP 503. |
| **Audio Source** | `Auto`, `PulseAudio`, `ALSA`, or `File Sync`. `Auto` prefers the FPP PipeWire/Pulse monitor source (the full show mix), then falls back to other capture devices. |
| **Pulse Source / ALSA Device** | `auto` / `default` is usually correct; override only if you know the exact source or device name. |
| **Bitrate / Sample Rate / Channels** | Defaults: `128k`, `44100 Hz`, stereo — a good quality/CPU balance. Lower the bitrate if CPU load is a problem. |

Click **Save Settings**, then **Test Capture** to verify that ffmpeg can open the selected source (a short capture probe).

The **Exact frame sync** toggle lives on the **Listen** page, next to the player, not in Config.

## How It Works

```
Web Audio (exact, default)                      Raw stream (best-effort fallback)
--------------------------------                ------------------------------------
/sync -> native MultiSync media/sequence clock  /stream -> ffmpeg captures the
   |      master_seconds at response time                  FPP audio output
   |      + RTT/2 (monotonic), no wall clocks              (PipeWire/Pulse/ALSA)
   |                                                       |
/media?file=...&seek=...&duration=0.5&format=pcm          |
   |  Int16Array -> AudioBuffer (no decoding)              v
   v                                            audio/mpeg (live capture)
AudioBufferSourceNode at master-anchored time
   |  ~0.5 s queue, drift shown in #ll_exact_status
   v
Speakers / headphones
```

- The PHP `/sync` and `/clock` endpoints are wrappers around the native plugin routes (`/api/plugin-apis/ListenLive/sync` and `/api/plugin-apis/ListenLive/clock`). They prefer the native MultiSync clock and degrade to a PHP status-poll fallback (or PHP clocks) when the native library is unavailable. The wrapper re-anchors the snapshot to its response time exactly once; the browser adds only its measured RTT/2.
- Chunks are cut on demand from the media file inside FPP's media tree as raw signed 16-bit PCM (no encoder priming, so sample 0 is exactly the requested seek); media paths are validated so files outside that tree cannot be served.
- If Web Audio chunk fetch/decode fails repeatedly, playback switches to `/stream` and is labeled as best-effort, not frame-exact.

See [apiDocs.json](apiDocs.json) for the native sync/clock route schemas.

## Privacy

Audio is captured on the FPP host and streamed to your browser **on your LAN**. Nothing is sent off-box, audio is not stored, and there is no telemetry, advertising, or donations. Your browser keeps only its volume preference in `localStorage` (mute is never saved). Settings stay in FPP's plugin configuration and the log stays under `<mediadir>/logs/`.

## API Endpoints

All endpoints are under `api/plugin/fpp-ListenLive/`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `status` | Plugin settings, FPP status, capture detection, timing |
| GET | `diagnostics` | ffmpeg / audio device checks and candidate capture commands |
| GET | `nowplaying` | Raw `/api/fppd/status` proxy |
| GET | `stream` | MP3 live capture of FPP's audio output (best-effort, not frame-exact) |
| GET | `media?file=...&seek=...&duration=...` | Timestamped media chunk for Web Audio exact scheduling (also supports Range) |
| GET | `sync` | PHP wrapper for the native MultiSync clock / status-poll fallback |
| GET | `clock` | PHP wrapper for the native monotonic/wall clock |
| POST | `save` | Save configuration |
| POST | `test` | Short ffmpeg capture probe (Test Capture) |
| GET | `logs` | Last 100 plugin log lines |
| GET | `check-updates` | Read-only local/remote SHA check |

Use FPP's Plugin Manager for plugin management and FPP's own UI for FPPD controls.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for host-side checks (`php -l`, `test/timing_test.php`, `scripts/lint_glue.py`), the fpp-tests web suite (Docker required), and the native build.

## Troubleshooting

- **Not frame-exact / players or remotes drift:** enable MultiSync in FPP Settings → MultiSync and check `#ll_exact_status` on the Listen page; `source: fallback` means the PHP status poll is in use.
- **"best-effort / NOT frame-exact" label:** Web Audio could not fetch/decode media chunks; the raw `/stream` fallback is active. Check that media is actually playing and that the media file can be decoded.
- **No audio / stream error:** confirm a playlist is playing, verify ffmpeg capture with **Config → Test Capture**, and check **Status → Diagnostics** for capture devices.
- **Choppy audio / high CPU:** lower the bitrate to 96k or 64k, or select a source with less overhead.
- **Logs:** `tail -50 <mediadir>/logs/plugin-fpp-ListenLive.log` (default: `/home/fpp/media/logs/plugin-fpp-ListenLive.log`).

## License

MIT — see [LICENSE.md](LICENSE.md). FPP is © Falcon Christmas. This plugin is not affiliated with or endorsed by the Falcon Christmas project.
