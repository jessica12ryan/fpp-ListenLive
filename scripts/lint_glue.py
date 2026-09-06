#!/usr/bin/env python3
"""
Structural checks on Listen Live plugin glue — mirrors PulseMesh test/glue_lint.py.

The Timing helper is unit-tested (test/timing_test.php); the ~30 lines that
wire it into api.php/listen.php are not, because exercising them needs a real
fppd+PULSE mixer. These properties are SILENT and TOTAL if broken — the UI
keeps answering, it just answers from the wrong clock — so they are asserted
structurally.

Run:  python3 scripts/lint_glue.py
"""

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
API = ROOT / "api.php"
LISTEN = ROOT / "listen.php"
TIMING = ROOT / "src" / "Timing.php"

failures = []

def fail(msg):
    failures.append(msg)

def read(p):
    return p.read_text() if p.exists() else ""

api = read(API)
listen = read(LISTEN)
timing = read(TIMING)

# 1. Timing helper is required early in api.php, not inside a function
if "require_once __DIR__ . '/src/Timing.php'" not in api and "src/Timing.php" not in api:
    fail("api.php does not require src/Timing.php at top-level")
else:
    # must appear before first function definition
    req_pos = api.find("Timing.php")
    first_fn = api.find("function llLog")
    if req_pos > first_fn:
        fail("api.php requires Timing.php AFTER first function — early require needed so helper is available to all endpoints")

# 2. llStreamFileSync computes seek via helper, not hardcoded max(0,elapsed-1.3)
if "LLSyncTiming::seekPosition" not in api:
    fail("api.php never calls LLSyncTiming::seekPosition — seek math bypasses helper")
if re.search(r"max\s*\(\s*0\s*,\s*\$elapsed\s*-\s*1\.3\s*\)", api):
    fail("api.php still contains hardcoded max(0, $elapsed - 1.3) — should use LLSyncTiming::seekPosition")
if "LLSyncTiming::byteOffset" not in api:
    fail("api.php never calls LLSyncTiming::byteOffset — byte-offset fallback not wired through helper")

# 3. llStatusEndpoint exposes monotonic timing split (deadline-at-read)
if "monotonic_ms" not in api or "wall_ms" not in api or "epoch_ms" not in api:
    fail("api.php llStatusEndpoint does not expose monotonic_ms/wall_ms/epoch_ms — monotonic vs wall split missing")
if "timing" not in api or "confidence" not in api:
    fail("api.php does not expose timing.confidence — server-side settle window not exported")
if "LLSyncTiming::confidence" not in api:
    fail("api.php never calls LLSyncTiming::confidence — confidence not computed via helper")
if "LLSyncTiming::monotonicMs" not in api:
    fail("api.php never calls LLSyncTiming::monotonicMs — server not using monotonic clock")

# 4. listen.php defines llClock.monotonic before llPlayer and uses performance.now
if "llClock" not in listen or "monotonicMs" not in listen:
    fail("listen.php does not define llClock.monotonicMs — monotonic vs wall split missing on client")
if "performance.now" not in listen:
    fail("listen.php does not use performance.now — should use monotonic clock, not Date.now")
# llClock must appear before llMSE/llPlayer so init can use it
if listen.find("llClock") > listen.find("var llPlayer"):
    fail("listen.php defines llClock AFTER llPlayer — must be before so playing handler can use llClock.monotonicMs()")
# playing handler must use monotonic, not Date.now
if re.search(r"streamStartTime\s*=\s*Date\.now\(\)", listen):
    fail("listen.php still assigns streamStartTime = Date.now() — should be llClock.monotonicMs()")

# 5. Half-second dedup — mirrors FPPPulseMesh.cpp:131 curTS = seconds*2
if "lastElapsedHalf" not in listen or "curHalf" not in listen:
    fail("listen.php does not implement half-second dedup (lastElapsedHalf/curHalf) — should mirror PulseMesh curTS*2")
if "shouldSend" not in timing and "BYTES_PER_SEC" not in timing:
    pass  # not required
if "* 2" not in listen and "Math.floor" not in listen:
    fail("listen.php dedup does not use *2 half-second quantization")
# poll interval must be 500, not 2000
if "setInterval(llPlayer.refreshStatus, 2000)" in listen:
    fail("listen.php still polls at 2000ms — should be 500ms with dedup")
if "setInterval(llPlayer.refreshStatus, 500)" not in listen:
    fail("listen.php does not poll at 500ms — half-second dedup requires faster poll")

# 6. Track-change announced timestamp for confidence (deadline-at-read)
if "lastMediaAnnouncedAtMono" not in listen:
    fail("listen.php does not track lastMediaAnnouncedAtMono — confidence deadline cannot be evaluated at read time")
if "IDLE_SETTLE_MS" not in timing and "250" not in timing:
    fail("src/Timing.php does not define IDLE_SETTLE_MS 250 — settle window missing")

# 7. Log throttle like PulseMesh m_sendErrorCount
if "Stream probe no data" not in api or "errCount" not in api:
    fail("api.php does not throttle probe log spam — should mirror PulseMesh m_sendErrorCount>10 suppress")

# 8. Timing helper is dependency-free (no curl/file reads) — structural
if "curl_init" in timing.lower() or "curl_exec" in timing.lower() or "file_get_contents(" in timing.lower():
    fail("src/Timing.php must be dependency-free (no curl/file reads) so it stays host-testable")
if "fppd" in timing.lower() and "no fpp" not in timing.lower():
    fail("src/Timing.php must not reference fppd — that binds it to a Pi")

# 9. Host test exists and exercises shouldSend
test_file = ROOT / "test" / "timing_test.php"
if not test_file.exists():
    fail("test/timing_test.php missing — host test for Timing helper required")
else:
    t = test_file.read_text()
    if "shouldSend" not in t:
        fail("test/timing_test.php does not exercise shouldSend half-second dedup")
    if "seekPosition" not in t:
        fail("test/timing_test.php does not exercise seekPosition")
    if "confidence" not in t:
        fail("test/timing_test.php does not exercise confidence")

if failures:
    print("glue lint FAILED:")
    for f in failures:
        print(f"  - {f}")
    sys.exit(1)
else:
    print("glue lint: all checks passed")
    sys.exit(0)
