#include "fpp-pch.h"

#include <sys/socket.h>
#include <sys/un.h>
#include <unistd.h>
#include <iostream>
#include <string>
#include <cstring>
#include <mutex>
#include <chrono>
#include <jsoncpp/json/json.h>

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
#endif

#define LL_SYNC_PATH "/ListenLive/sync"
#define LL_CLOCK_PATH "/ListenLive/clock"

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
          m_lastSeconds(-1.0f),
          m_lastMono(0),
          m_lastWall(0),
          m_epochWall(wallClockMs()),
          m_lastMediaHalf(-1)
    {
        LogInfo(VB_PLUGIN, "Initializing ListenLive Sync Plugin (frame-exact, versatile)\n");
        MultiSync::INSTANCE.addMultiSyncPlugin(this);
        if (!MultiSync::INSTANCE.isMultiSyncEnabled()) {
            WarningHolder::AddWarning("ListenLive Sync: MultiSync not enabled — exact frame sync will use fallback (status poll) not master clock. Enable MultiSync (FPP Settings → MultiSync) for versatile exact across Player/Remote.");
        }
    }

    virtual ~FPPListenLiveSyncPlugin()
    {
        MultiSync::INSTANCE.removeMultiSyncPlugin(this);
    }

    // MultiSync callbacks — master clock, exact to FPP's media pipeline
    virtual void SendMediaOpenPacket(const std::string &filename) override
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        m_lastFileOpen = filename;
        m_lastOpenMono = monotonicMs();
        LogDebug(VB_PLUGIN, "ListenLive sync: open %s\n", filename.c_str());
    }

    virtual void SendMediaSyncStartPacket(const std::string &filename) override
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        m_lastFile = filename;
        m_lastSeconds = 0.0f;
        m_lastMono = monotonicMs();
        m_lastWall = wallClockMs();
        m_lastMediaHalf = 0;
        LogDebug(VB_PLUGIN, "ListenLive sync: start %s\n", filename.c_str());
    }

    virtual void SendMediaSyncStopPacket(const std::string &filename) override
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        m_lastFile = "";
        m_lastSeconds = -1.0f;
        m_lastMono = monotonicMs();
        m_lastWall = wallClockMs();
        LogDebug(VB_PLUGIN, "ListenLive sync: stop %s\n", filename.c_str());
    }

    virtual void SendMediaSyncPacket(const std::string &filename, float seconds) override
    {
        int curHalf = static_cast<int>(seconds * 2.0f);
        {
            std::lock_guard<std::mutex> lock(m_mutex);
            if (m_lastMediaHalf == curHalf && m_lastFile == filename) return;
            m_lastMediaHalf = curHalf;
            m_lastFile = filename;
            m_lastSeconds = seconds;
            m_lastMono = monotonicMs();
            m_lastWall = wallClockMs();
        }
        // Also persist to tmp file for PHP fallback (no FPP API needed)
        // Best-effort, no lock needed beyond mutex
        Json::Value j;
        {
            std::lock_guard<std::mutex> lock(m_mutex);
            j["media"] = m_lastFile;
            j["seconds"] = m_lastSeconds;
            j["monotonic_ms"] = (Json::Int64)m_lastMono;
            j["wall_ms"] = (Json::Int64)m_lastWall;
            j["epoch_ms"] = (Json::Int64)m_epochWall;
            j["has_media"] = !m_lastFile.empty() && m_lastSeconds >= 0;
        }
        // Write atomically via tmp rename
        FILE *f = fopen("/tmp/ll_sync.json.tmp", "w");
        if (f) {
            Json::StreamWriterBuilder b;
            b["indentation"] = "";
            std::string out = Json::writeString(b, j);
            fwrite(out.c_str(), 1, out.size(), f);
            fclose(f);
            rename("/tmp/ll_sync.json.tmp", "/tmp/ll_sync.json");
        }
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
    void registerApis(httpserver::webserver* ws) override
    {
        ws->register_resource(LL_SYNC_PATH, this, false);
        // clock path shares same resource — we use query param ?clock=1
        LogInfo(VB_PLUGIN, "ListenLive sync registered at /api/plugin-apis%s\n", LL_SYNC_PATH);
    }
    void unregisterApis(httpserver::webserver* ws) override
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
    std::string syncJson()
    {
        std::lock_guard<std::mutex> lock(m_mutex);
        int64_t nowMono = monotonicMs();
        int64_t nowWall = wallClockMs();
        Json::Value j;
        j["monotonic_ms"] = (Json::Int64)nowMono;
        j["wall_ms"] = (Json::Int64)nowWall;
        j["epoch_ms"] = (Json::Int64)m_epochWall;
        j["has_media"] = !m_lastFile.empty() && m_lastSeconds >= 0;
        j["media"] = m_lastFile;
        j["seconds"] = m_lastSeconds;
        j["frame"] = (m_lastSeconds >= 0) ? (int)(m_lastSeconds * 44100.0f) : -1;
        j["last_media_half"] = m_lastMediaHalf;
        // Extrapolate current seconds based on monotonic delta (frame-exact between packets)
        if (!m_lastFile.empty() && m_lastSeconds >= 0 && m_lastMono > 0) {
            float delta = (nowMono - m_lastMono) / 1000.0f;
            // Only extrapolate if last packet was recent (<2s) — else stale
            if (delta >= 0 && delta < 2.0f) {
                j["extrapolated_seconds"] = m_lastSeconds + delta;
                j["extrapolated"] = true;
            } else {
                j["extrapolated_seconds"] = m_lastSeconds;
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
        // Confidence: if last sync was recent, exact, else unresolved (stale)
        bool recent = (nowMono - m_lastMono) < 1000;
        j["confidence"] = (recent && !m_lastFile.empty()) ? "exact" : "unresolved";
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
    std::string m_lastFile;
    std::string m_lastFileOpen;
    float m_lastSeconds = -1.0f;
    int64_t m_lastMono = 0;
    int64_t m_lastWall = 0;
    int64_t m_epochWall = 0;
    int m_lastMediaHalf = -1;
    int64_t m_lastOpenMono = 0;

    std::string m_lastPlaylist;
    std::string m_lastPlaylistAction;
    int m_lastPlaylistSize = 0;
    int m_lastPlaylistItem = 0;
    int64_t m_lastPlaylistMono = 0;
};

extern "C"
{
    FPPPlugin *createPlugin()
    {
        return new FPPListenLiveSyncPlugin();
    }
}
