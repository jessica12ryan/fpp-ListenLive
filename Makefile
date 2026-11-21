SRCDIR ?= /opt/fpp/src
include $(SRCDIR)/makefiles/common/setup.mk
include $(SRCDIR)/makefiles/platform/*.mk

all: libfpp-ListenLive.$(SHLIB_EXT)
debug: all

OBJECTS_fpp_ListenLive_so += src/FPPListenLiveSync.o
LIBS_fpp_ListenLive_so += -L$(SRCDIR) -lfpp -ljsoncpp -lcurl
CXXFLAGS_src/FPPListenLiveSync.o += -I$(SRCDIR)

ifeq ($(wildcard $(SRCDIR)/Plugin.h),)
$(error SRCDIR=$(SRCDIR) has no Plugin.h - set SRCDIR to the FPP source directory)
endif

# FPP 10+ uses the no-arg registerApis()/registerPluginApi() API. If the
# headers advertise it but the compatibility path is incomplete, fail loudly
# instead of silently compiling the legacy (uncompilable) branch.
ifneq ($(shell grep -c 'virtual void registerApis()' $(SRCDIR)/Plugin.h 2>/dev/null),0)
ifeq ($(wildcard $(SRCDIR)/fpphttp.h),)
$(error Plugin.h has no-arg registerApis() but fpphttp.h is missing - check SRCDIR)
endif
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
