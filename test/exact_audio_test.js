#!/usr/bin/env node
/*
 * Host regression test for the Web Audio block slicer.
 * Extracts scheduleBlock() from listen.php and runs it with stubbed AudioContext
 * objects. Catches unbound `this`, NaN buffer lengths and non-contiguous chunks.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const root = path.dirname(__dirname);
const php = fs.readFileSync(path.join(root, 'listen.php'), 'utf8');
const marker = '    scheduleBlock: function(pcm, sr, ch, baseWhen, baseSeek) {';
const start = php.indexOf(marker);
if (start < 0) { console.error('exact_audio_test: scheduleBlock not found'); process.exit(1); }
const endMarker = '\n    // First block (or recovery from an empty queue)';
const end = php.indexOf(endMarker, start);
if (end < 0) { console.error('exact_audio_test: scheduleBlock end not found'); process.exit(1); }
let method = php.slice(start, end).trim();
if (method.endsWith(',')) method = method.slice(0, -1);

const engine = eval('({' + method + '})');
const frames = 4 * 44100;
const pcm = new Int16Array(frames * 2);
for (let i = 0; i < pcm.length; i++) pcm[i] = Math.sin(i / 100) * 30000;

engine.ctx = {
  currentTime: 9,
  destination: {},
  createBuffer(ch, len, sr) {
    if (!Number.isInteger(len) || len <= 0) throw new Error('bad buffer length ' + len);
    const data = [];
    for (let c = 0; c < ch; c++) data.push(new Float32Array(len));
    return { getChannelData: (c) => data[c] };
  },
  createBufferSource() { return { connect() {}, start(w) { if (!isFinite(w)) throw new Error('bad start ' + w); } }; },
  createGain() { return { gain: { value: 1 }, connect() {} }; }
};
engine.gain = null;
engine.scheduled = [];
engine.late = 0;
engine.nextSeek = -1;
engine.nextWhen = -1;
engine.chunkSeconds = 0.5;
engine.armAudible = function () {};

const ok = engine.scheduleBlock(pcm, 44100, 2, 10, 10);
if (!ok) fail('scheduleBlock returned false');
if (engine.scheduled.length !== 8) fail('expected 8 slices, got ' + engine.scheduled.length);
let cursorWhen = 10;
let cursorSeek = 10;
for (const s of engine.scheduled) {
  if (!isFinite(s.when) || !isFinite(s.end) || !isFinite(s.seek)) fail('non-finite slice: ' + JSON.stringify(s));
  if (Math.abs(s.when - cursorWhen) > 0.001) fail('gap before slice at ' + s.when);
  if (Math.abs(s.seek - cursorSeek) > 0.001) fail('content gap before slice at ' + s.seek);
  if (Math.abs((s.end - s.when) - 0.5) > 0.001) fail('bad slice length ' + (s.end - s.when));
  cursorWhen = s.end;
  cursorSeek = s.seek + (s.end - s.when);
}
if (Math.abs(engine.nextWhen - cursorWhen) > 0.001) fail('nextWhen not contiguous');
if (Math.abs(engine.nextSeek - cursorSeek) > 0.001) fail('nextSeek not contiguous');

// Fallback must revive the native stream. The bridge is no longer suspended,
// so resumeNative() may not be gated on a flag that is never set true.
const rnMarker = '    resumeNative: function() {';
const rnStart = php.indexOf(rnMarker);
if (rnStart < 0) fail('resumeNative not found');
const rnEndMarker = '\n    },';
const rnEnd = php.indexOf(rnEndMarker, rnStart);
if (rnEnd < 0) fail('resumeNative end not found');
let rnMethod = php.slice(rnStart, rnEnd + rnEndMarker.length).trim();
if (rnMethod.endsWith(',')) rnMethod = rnMethod.slice(0, -1);
const player = eval('({' + rnMethod + '})');
player.audio = {
  muted: true,
  paused: true,
  src: '',
  load() { this.loaded = true; },
  play() { this.played = true; return Promise.resolve(); }
};
player.isPlaying = true;
player.reconnectAttempts = 3;
player.buildUrl = () => 'stream?t=123';
global.llPlayer = player;
player.resumeNative();
if (player.audio.src !== 'stream?t=123') fail('resumeNative did not set a fresh cache-busted src');
if (!player.audio.loaded) fail('resumeNative did not reload the native element');
if (!player.audio.played) fail('resumeNative did not start playback');
if (player.reconnectAttempts !== 0) fail('resumeNative did not reset reconnect attempts');

function fail(msg) { console.error('exact_audio_test: FAIL ' + msg); process.exit(1); }
console.log('exact_audio_test: all checks passed');
