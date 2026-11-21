#include "fpp-pch.h"

#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/un.h>
#include <unistd.h>
#include <iostream>
#include <string>
#include <cstring>
#include <cmath>
#include <mutex>
#include <atomic>
#include <chrono>
// FPP's portability shim picks the right jsoncpp header on Debian and macOS.
#include "fpp-json.h"

#include "Plugin.h"
#include "MultiSync.h"
#include "Warnings.h"

#ifdef PM_HAVE_NOARG_REGISTER_APIS
#include "fpphttp.h"
#elif __has_include("fpphttp.h")
#include "fpphttp.h"
#else
#include <httpserver.hpp>
#endif

#ifndef PM_HAVE_NOARG_REGISTER_APIS
using LlHttpResponse = decltype(std::declval<httpserver::http_resource&>().render_GET(
    std::declval<const httpserver::http_request&>()));
using HttpServerCompat = httpserver::webserver;
#endif

#define LL_SYNC_PATH "/ListenLive/sync"
#define LL_CLOCK_PATH "/ListenLive/clock"

// How old a sync packet may be before we stop calling the clock "exact".
// FPP sends media/sequence sync packets roughly every half second.
#define LL_FRESH_MS 1500
#define LL_EXTRAPOLATE_MAX_MS 2000

class FPPListenLiveSyncPlugin : public FPPPlugin,
                                public MultiSyncPlugin
#ifndef PM_HAVE_NOARG_REGISTER_APIS
    ,
                                public httpserver::http_resource
#endif
{
public:
    FPPListenLiveSyncPlugin()
        : FPPPlugin("fpp-ListenLive"),
          m_epochWall(wallClockMs())
    {
        LogInfo(VB_PLUGIN, "Initializing ListenLive Sync Plugin (MultiSync sequence+media, exact)\n");
        MultiSync::INSTANCE.addMultiSyncPlugin(this);
        if (!MultiSync::INSTANCE.isMultiSyncEnabled()) {
            WarningHolder::AddWarning("ListenLive Sync: MultiSync not enabled — exact frame sync requires MultiSync. Enable it in FPP Settings → MultiSync; until then the player is labeled not frame-exact.");
        }
    }

    virtual ~FPPListenLiveSyncPlugin()
    {
        MultiSync::INSTANCE.removeMultiSyncPlugin(this);
    }

    std::function<bool()> shutdown() override {
        // No threads/timers/curl to stop — quiesce immediately (§2.9)
        return nullptr;
    }

    // ------------------------------------------------------------------
    // MultiSync media callbacks — master position for a media file.
    // ------------------------------------------------------------------
    virtual void SendMediaOpenPacket(const std::string &filename) override
    {
        openPacket(m_media, filename);
    }
    virtual void SendMediaSyncStartPacket(const std::string &filename) override
    {
        startPacket(m_media, filename);
    }
    virtual void SendMediaSyncStopPacket(const std::string &filename) override
    {
        (void)filename;
        stopPacket(m_media);
    }
    virtual void SendMediaSyncPacket(const std::string &filename, float seconds) override
    {
        recordSync(m_media, filename, seconds, -1, "media");
    }

    // ------------------------------------------------------------------
    // MultiSync sequence callbacks — FPP sends these instead of media
    // sync when a sequence (fseq) is playing. Without these, a sequence
    // show has no native clock and the player falls back to status poll.
    // ------------------------------------------------------------------
    virtual void SendSeqOpenPacket(const std::string &filename) override
    {
        openPacket(m_seq, filename);
    }
    virtual void SendSeqSyncStartPacket(const std::string &filename) override
    {
        startPacket(m_seq, filename);
    }
    virtual void SendSeqSyncStopPacket(const std::string &filename) override
    {
        (void)filename;
        stopPacket(m_seq);
    }
    virtual void SendSeqSyncPacket(const std::string &filename, int frames, float seconds) override
    {
        recordSync(m_seq, filename, seconds, frames, "sequence");
    }

    // ------------------------------------------------------------------
    // Remote side: fppd drives Received* from the master's UDP packets.
    // Mirror them into the same state so remotes expose the same clock.
    // ------------------------------------------------------------------
    virtual void ReceivedMediaOpenPacket(const std::string &filename) override
    {
        openPacket(m_media, filename);
    }
    virtual void ReceivedMediaSyncStartPacket(const std::string &filename) override
    {
        startPacket(m_media, filename);
    }
    virtual void ReceivedMediaSyncStopPacket(const std::string &filename) override
    {
        (void)filename;
        stopPacket(m_media);
    }
    virtual void ReceivedMediaSyncPacket(const std::string &filename, float seconds) override
    {
        recordSync(m_media, filename, seconds, -1, "media");
    }

    virtual void ReceivedSeqOpenPacket(const std::string &filename) override
    {
        openPacket(m_seq, filename);
    }
    virtual void ReceivedSeqSyncStartPacket(const std::string &filename) override
    {
        startPacket(m_seq, filename);
    }
    virtual void ReceivedSeqSyncStopPacket(const std::string &filename) override
    {
        (void)filename;
        stopPacket(m_seq);
    }
    virtual void ReceivedSeqSyncPacket(const std::string &filename, int frames, float seconds) override
    {
        recordSync(m_seq, filename, seconds, frames, "sequence");
    }

    virtual void playlistCallback(const Json::Value& playlist, const std::string& action, const std::string& section, int item) override
    {
        std::string name;
        int size = 0;
        if (playlist.isMember("name") && playlist["name"].isString()) name = playlist["name"].asString();
        if (playlist.isMember("size") && playlist["size"].isInt()) size = playlist["size"].asInt();
        std::lock_guard<std::mutex> lock(m_mutex);
        m_lastPlaylist = name;
        m_lastPlaylistAction = action;
        m_lastPlaylistSize = size;
        m_lastPlaylistItem = item;
        m_lastPlaylistMono = monotonicMs();
    }

#ifdef PM_HAVE_NOARG_REGISTER_APIS
    void registerApis() override
    {
        FPPPlugins::registerPluginApi(LL_SYNC_PATH,
            [this](const HttpRequestPtr&, HttpCallback&& callback) {
                callback(makeStringResponse(syncJson(), 200, "application/json"));
            },
            { drogon::Get, drogon::Head });
        FPPPlugins::registerPluginApi(LL_CLOCK_PATH,
            [this](const HttpRequestPtr&, HttpCallback&& callback) {
                callback(makeStringResponse(clockJson(), 200, "application/json"));
            },
            { drogon::Get });
        LogInfo(VB_PLUGIN, "ListenLive sync registered at /api/plugin-apis%s and %s\n", LL_SYNC_PATH, LL_CLOCK_PATH);
    }
    void unregisterApis() override
    {
        FPPPlugins::unregisterPluginApi(LL_SYNC_PATH);
        FPPPlugins::unregisterPluginApi(LL_CLOCK_PATH);
    }
#else
#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wdeprecated-declarations"
    void registerApis(HttpServerCompat* ws) override
    {
        ws->register_resource(LL_SYNC_PATH, this, false);
        // clock path shares same resource — we use query param ?clock=1
        LogInfo(VB_PLUGIN, "ListenLive sync registered at /api/plugin-apis%s\n", LL_SYNC_PATH);
    }
    void unregisterApis(HttpServerCompat* ws) override
    {
        ws->unregister_resource(LL_SYNC_PATH);
    }
#pragma GCC diagnostic pop
    LlHttpResponse render_GET(const httpserver::http_request& req) override
    {
        (void)req;
        // Simple: /ListenLive/sync?clock=1 returns clock only
        std::string q = req.get_arg("clock");
        if (q == "1") return std::make_shared<httpserver::string_response>(clockJson(), 200, "application/json");
        return std::make_shared<httpserver::string_response>(syncJson(), 200, "application/json");
    }
#endif

private:
    // Media and sequence are tracked separately: a sequence show also has a
    // media file, and the media position must stay authoritative for the audio
    // chunk we fetch. The sequence state is the fallback for fseq-only shows.
    struct SyncState {
        std::string file;
        float seconds = -1.0f;
        int64_t mono = 0;
        int64_t wall = 0;
        int half = -1;
        int frames = -1;
        bool seen = false;
    };

    void openPacket(SyncState &st, const std::string &filename)
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        st.file = filename;
        st.seen = true;
    }

    void startPacket(SyncState &st, const std::string &filename)
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        st.file = filename;
        st.seconds = 0.0f;
        st.mono = monotonicMs();
        st.wall = wallClockMs();
        st.half = 0;
        st.frames = -1;
        st.seen = true;
    }

    void stopPacket(SyncState &st)
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        st.file.clear();
        st.seconds = -1.0f;
        st.mono = monotonicMs();
        st.wall = wallClockMs();
        st.half = -1;
        st.frames = -1;
    }

    void recordSync(SyncState &st, const std::string &filename, float seconds,
                    int frames, const char *fileType)
    {
        (void)fileType;
        int curHalf = static_cast<int>(seconds * 2.0f);
        bool changed = false;
        {
            std::lock_guard<std::mutex> lock(m_mutex);
            bool seek = (st.seconds >= 0 && (seconds < st.seconds || seconds - st.seconds > 0.6f));
            if (st.half != curHalf || st.file != filename || seek) {
                st.half = curHalf;
                st.file = filename;
                st.seconds = seconds;
                st.mono = monotonicMs();
                st.wall = wallClockMs();
                st.frames = frames;
                st.seen = true;
                changed = true;
            }
        }
        if (changed) persistSync();
    }

    // Called with m_mutex held. Returns the state to report, or nullptr.
    // Fresh media wins; otherwise fresh sequence; otherwise the most recent
    // stale state (so callers can still see confidence "unresolved").
    const SyncState* chosenLocked(int64_t nowMono) const
    {
        bool mFresh = m_media.seen && !m_media.file.empty() && m_media.seconds >= 0 &&
                      (nowMono - m_media.mono) < LL_FRESH_MS;
        bool sFresh = m_seq.seen && !m_seq.file.empty() && m_seq.seconds >= 0 &&
                      (nowMono - m_seq.mono) < LL_FRESH_MS;
        if (mFresh) return &m_media;
        if (sFresh) return &m_seq;
        if (m_media.seen && m_media.seconds >= 0 && m_media.mono >= m_seq.mono) return &m_media;
        if (m_seq.seen && m_seq.seconds >= 0) return &m_seq;
        return nullptr;
    }

    std::string syncJson()
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        int64_t nowMono = monotonicMs();
        int64_t nowWall = wallClockMs();
        Json::Value j;
        j["monotonic_ms"] = (Json::Int64)nowMono;
        j["wall_ms"] = (Json::Int64)nowWall;
        j["epoch_ms"] = (Json::Int64)m_epochWall;

        const SyncState *st = chosenLocked(nowMono);
        bool hasMedia = st && !st->file.empty() && st->seconds >= 0;
        j["has_media"] = hasMedia;
        j["media"] = hasMedia ? st->file : "";
        j["file_type"] = hasMedia ? (st == &m_media ? "media" : "sequence") : "";
        j["seconds"] = hasMedia ? st->seconds : -1.0f;
        j["frames"] = hasMedia ? st->frames : -1;
        j["frame"] = hasMedia ? (int)(st->seconds * 44100.0) : -1;
        j["last_media_half"] = hasMedia ? st->half : -1;
        j["packet_age_ms"] = hasMedia ? (Json::Int64)(nowMono - st->mono) : -1;

        if (hasMedia) {
            float delta = (nowMono - st->mono) / 1000.0f;
            if (delta >= 0 && delta < (LL_EXTRAPOLATE_MAX_MS / 1000.0f)) {
                j["extrapolated_seconds"] = st->seconds + delta;
                j["extrapolated"] = true;
            } else {
                j["extrapolated_seconds"] = st->seconds;
                j["extrapolated"] = false;
            }
        } else {
            j["extrapolated_seconds"] = -1.0f;
            j["extrapolated"] = false;
        }

        j["playlist"] = m_lastPlaylist;
        j["playlist_action"] = m_lastPlaylistAction;
        j["playlist_size"] = m_lastPlaylistSize;
        j["playlist_item"] = m_lastPlaylistItem;
        j["playlist_mono"] = (Json::Int64)m_lastPlaylistMono;
        bool fresh = hasMedia && (nowMono - st->mono) < LL_FRESH_MS;
        j["confidence"] = fresh ? "exact" : "unresolved";
        Json::StreamWriterBuilder b;
        b["indentation"] = "";
        return Json::writeString(b, j);
    }

    std::string clockJson()
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        int64_t nowMono = monotonicMs();
        int64_t nowWall = wallClockMs();
        Json::Value j;
        j["monotonic_ms"] = (Json::Int64)nowMono;
        j["wall_ms"] = (Json::Int64)nowWall;
        j["epoch_ms"] = (Json::Int64)m_epochWall;
        Json::StreamWriterBuilder b;
        b["indentation"] = "";
        return Json::writeString(b, j);
    }

    // Shared by Send*/Received* and media/sequence: atomic persist to
    // plugindata for the PHP fallback. Plugindata only — never /tmp (§5).
    void persistSync()
    {
        Json::Value j;
        {
            std::lock_guard<std::mutex> lock(m_mutex);
            int64_t nowMono = monotonicMs();
            const SyncState *st = chosenLocked(nowMono);
            bool hasMedia = st && !st->file.empty() && st->seconds >= 0;
            j["media"] = hasMedia ? st->file : "";
            j["file_type"] = hasMedia ? (st == &m_media ? "media" : "sequence") : "";
            j["seconds"] = hasMedia ? st->seconds : -1.0f;
            j["monotonic_ms"] = hasMedia ? (Json::Int64)st->mono : (Json::Int64)0;
            j["wall_ms"] = hasMedia ? (Json::Int64)st->wall : (Json::Int64)0;
            j["epoch_ms"] = (Json::Int64)m_epochWall;
            j["has_media"] = hasMedia;
            j["confidence"] = (hasMedia && (nowMono - st->mono) < LL_FRESH_MS) ? "exact" : "unresolved";
        }
        std::string plugindataDir = getFPPMediaDir("/plugindata/fpp-ListenLive");
        size_t slash = plugindataDir.find_last_of('/');
        if (slash != std::string::npos) mkdir(plugindataDir.substr(0, slash).c_str(), 0775);
        mkdir(plugindataDir.c_str(), 0775);
        static std::atomic<int> s_tmpCounter{0};
        std::string tmpPath = plugindataDir + "/ll_sync.json.tmp." +
                              std::to_string(monotonicMs()) + "." +
                              std::to_string(++s_tmpCounter);
        std::string finalPath = plugindataDir + "/ll_sync.json";
        FILE *f = fopen(tmpPath.c_str(), "w");
        if (f) {
            Json::StreamWriterBuilder b;
            b["indentation"] = "";
            std::string out = Json::writeString(b, j);
            fwrite(out.c_str(), 1, out.size(), f);
            fclose(f);
            if (rename(tmpPath.c_str(), finalPath.c_str()) != 0) remove(tmpPath.c_str());
        }
    }

    static int64_t monotonicMs()
    {
        return std::chrono::duration_cast<std::chrono::milliseconds>(
                   std::chrono::steady_clock::now().time_since_epoch())
            .count();
    }
    static int64_t wallClockMs()
    {
        return std::chrono::duration_cast<std::chrono::milliseconds>(
                   std::chrono::system_clock::now().time_since_epoch())
            .count();
    }

    mutable std::mutex m_mutex;
    SyncState m_media;
    SyncState m_seq;
    int64_t m_epochWall = 0;

    std::string m_lastPlaylist;
    std::string m_lastPlaylistAction;
    int m_lastPlaylistSize = 0;
    int m_lastPlaylistItem = 0;
    int64_t m_lastPlaylistMono = 0;
};

FPP_PLUGIN_SUPPORTS_UNLOAD()

extern "C"
{
    FPPPlugin *createPlugin()
    {
        return new FPPListenLiveSyncPlugin();
    }
}
