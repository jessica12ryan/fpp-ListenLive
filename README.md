# <img src="icon.png" alt="icon" width="32" style="vertical-align: middle;"> Listen Live Plugin for FPP (fpp-ListenLive)

> **Listen to your show's live audio directly from within the FPP UI — no extra hardware or external server required.**

## What It Does

FPP plays your sequences and media to the audience. **Listen Live** captures that same audio output and streams it to your browser as MP3, so you can monitor the show from the FPP web interface — from the couch, the garage, or anywhere on your LAN.

- One-click HTML5 player on **Content Setup → Listen Live**
- Streams via `api/plugin/fpp-ListenLive/stream` as `audio/mpeg` — works in any modern browser, plus VLC/external players
- Live status shows current playlist, sequence, song, and elapsed time (from ` /api/fppd/status`)
- Auto-reconnect, volume + mute (remembered per browser), ~1–3s latency on LAN
- Automatic fallback to **File Sync** — streams the current media file in sync when live capture isn't available

## Requirements

- FPP 8+ (8.x, 9.x, 10.x)
- `ffmpeg` on the FPP host (declared as a dependency; Plugin Manager installs it, or `sudo apt install ffmpeg`)
- For true live capture: PulseAudio (preferred, with `.monitor` source) or an ALSA capture device
  - Without either, the plugin automatically uses File Sync mode

## Installation

### Plugin Manager (Recommended)

1. In FPP UI, go to **Content Setup → Plugin Manager**
2. Paste this URL and click **Install**:
   ```
   https://raw.githubusercontent.com/jessica12ryan/fpp-ListenLive/main/pluginInfo.json
   ```
3. Or search for "Listen Live" in the available plugins list.

### Manual

```bash
cd /home/fpp/media/plugins
git clone https://github.com/jessica12ryan/fpp-ListenLive.git fpp-ListenLive
sudo bash fpp-ListenLive/scripts/fpp_install.sh
```

## Usage

1. Start a playlist or schedule (so audio is playing).
2. Open **Content Setup → Listen Live** → click **▶ Play**.
3. Adjust volume; it’s stored in `localStorage` per browser.
4. The header shows **● LIVE** when streaming, and the **Now Playing** line shows the current media. Status is polled every 3s from FPPD.

You can also open the raw stream in VLC:
```
http://<fpp-ip>/api/plugin/fpp-ListenLive/stream
```

## Configuration

Open **Content Setup → Listen Live → Config** tab:

| Setting | Description |
|---------|-------------|
| **Enable Live Streaming** | Master on/off. When off, the stream returns HTTP 503. |
| **Audio Source** | `Auto` (Pulse → ALSA), `PulseAudio`, `ALSA`, or `File Sync` |
| **Pulse Source / ALSA Device** | `auto` / `default` is usually correct; override if you know the device name |
| **Bitrate / Sample Rate / Channels** | Default `128k / 44100 Hz / Stereo` balances quality and CPU |

Click **Save Settings**, then **Test Capture** to verify FFmpeg can open the selected source (1-second probe).

## How It Works

```
Browser <audio>  ──GET api/plugin/fpp-ListenLive/stream──▶  PHP (api.php)
                                                           │
                                              ffmpeg -f pulse -i <monitor>
                                                     -f alsa  -i <device>
                                                     -codec:a libmp3lame -b:a 128k -f mp3 -
                                                           │
                                                           ▼
                                                    audio/mpeg (chunked)
```

- On each client connection, PHP builds an `ffmpeg` command from the configured source and bitrate, then `popen()`s it and pipes MP3 to the response with `Content-Type: audio/mpeg`.
- The `Auto` source tries Pulse monitor sources first (exact copy of output, including all mixed audio), then ALSA `default` / `hw:0,0`.
- **Fallback:** If no capture works (or FFmpeg is missing), the handler proxies `http://localhost/api/fppd/status` to find the current media file in `/home/fpp/media/music`, then streams that file with a byte-offset seek based on `seconds_elapsed` for rough sync.
- Status, diagnostics, and logs are served as JSON via `api/plugin/fpp-ListenLive/status|diagnostics|logs`.

## Troubleshooting

**No audio / stream error**
- Check **Status → Diagnostics** — is FFmpeg found? Is Pulse/ALSA detected?
- Use **Test Capture** in Config.
- Switch source to **File Sync** — if that works, the issue is capture (not networking).
- Ensure a playlist is **playing** on FPP (Status/Control page).

**Choppy / high CPU**
- Lower bitrate to `96k` or `64k`.
- Use File Sync mode (lowest overhead) or check `top` — FFmpeg is ~5–10% on Pi 4.

**Latency**
- Expect 1–3s. Keep browser and FPP on the same LAN. Lower bitrates help slightly.

**Logs**
```bash
tail -20 /home/fpp/media/logs/plugin-fpp-ListenLive.log
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `api/plugin/fpp-ListenLive/status` | Plugin settings + FPP status + detection |
| GET | `api/plugin/fpp-ListenLive/diagnostics` | FFmpeg / audio device checks |
| GET | `api/plugin/fpp-ListenLive/nowplaying` | Raw `/api/fppd/status` proxy |
| GET | `api/plugin/fpp-ListenLive/stream` | MP3 live stream |
| GET | `api/plugin/fpp-ListenLive/media?file=xxx` | Direct media file stream (with Range/Seek) |
| POST | `api/plugin/fpp-ListenLive/save` | Save config JSON |
| POST | `api/plugin/fpp-ListenLive/test` | 1s capture probe |
| GET | `api/plugin/fpp-ListenLive/logs` | Last 100 log lines |
| GET | `api/plugin/fpp-ListenLive/check-updates` | Git SHA check |
| POST | `api/plugin/fpp-ListenLive/update` / `reinstall` / `uninstall` | Maintenance |

## Development

Enable **Developer** UI level (FPP Settings → UI → Developer) to see the **Developer** tab for update/reinstall/uninstall and full diagnostics.

## License

MIT — see [LICENSE.md](LICENSE.md). FPP is © Falcon Christmas. This plugin is not affiliated with or endorsed by the Falcon Christmas project.
