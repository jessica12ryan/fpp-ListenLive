SRCDIR ?= /opt/fpp/src
include $(SRCDIR)/makefiles/common/setup.mk
include $(SRCDIR)/makefiles/platform/*.mk

all: libfpp-ListenLive.$(SHLIB_EXT)
debug: all

OBJECTS_fpp_ListenLive_so += src/FPPListenLiveSync.o
LIBS_fpp_ListenLive_so += -L$(SRCDIR) -lfpp -ljsoncpp -lcurl
CXXFLAGS_src/FPPListenLiveSync.o += -I$(SRCDIR)

# Detect playlistInserted and no-arg registerApis like fpp-pulsemesh
ifneq ($(shell grep -c playlistInserted $(SRCDIR)/Plugin.h 2>/dev/null),0)
CXXFLAGS_src/FPPListenLiveSync.o += -DPM_HAVE_PLAYLIST_INSERTED=1
endif

ifneq ($(shell grep -c 'virtual void registerApis()' $(SRCDIR)/Plugin.h 2>/dev/null),0)
ifneq ($(shell grep -c 'registerPluginApi' $(SRCDIR)/fpphttp.h 2>/dev/null),0)
CXXFLAGS_src/FPPListenLiveSync.o += -DPM_HAVE_NOARG_REGISTER_APIS=1
LIBS_fpp_ListenLive_so += -ldrogon -ltrantor
endif
endif

%.o: %.cpp Makefile $(wildcard src/*.h)
	$(CCACHE) $(CC) $(CFLAGS) $(CXXFLAGS) $(CXXFLAGS_$@) -c $< -o $@

libfpp-ListenLive.$(SHLIB_EXT): $(OBJECTS_fpp_ListenLive_so) $(SRCDIR)/libfpp.$(SHLIB_EXT)
	$(CCACHE) $(CC) -shared $(CFLAGS_$@) $(OBJECTS_fpp_ListenLive_so) $(LIBS_fpp_ListenLive_so) $(LDFLAGS) -o $@

clean:
	rm -f libfpp-ListenLive.$(SHLIB_EXT) $(OBJECTS_fpp_ListenLive_so)

.PHONY: all debug clean
