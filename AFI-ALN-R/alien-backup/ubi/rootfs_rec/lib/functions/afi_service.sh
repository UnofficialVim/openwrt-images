#!/bin/sh

enabled()
{
	name="$(basename ${basescript:-$initscript})"
	grep -q "^$name$" $IPKG_INSTROOT/etc/misc/disabled 2>/dev/null && return 1
	return 0
}

disable()
{
	name="$(basename ${basescript:-$initscript})"
	if enabled; then
		echo "$name" >> $IPKG_INSTROOT/etc/misc/disabled
	fi
}

# first part of enable() should be kept because it's used by build system to create symlinks
# for the first part we only add 2>/dev/null to get rid of noise in console
# then we enable service by deleting it's name from /etc/misc/disabled
enable()
{
	err=1
	name="$(basename ${basescript:-$initscript})"
	[ "$START" ] && \
		ln -sf "../init.d/$name" "$IPKG_INSTROOT/etc/rc.d/S${START}${name##S[0-9][0-9]}" 2>/dev/null && \
		err=0
	[ "$STOP" ] && \
		ln -sf "../init.d/$name" "$IPKG_INSTROOT/etc/rc.d/K${STOP}${name##K[0-9][0-9]}" 2>/dev/null && \
		err=0

	[ ! -e $IPKG_INSTROOT/etc/misc/disabled ] && return $err
	sed -ni "/^$name$/!p" $IPKG_INSTROOT/etc/misc/disabled
}

# this should be in sync with base-files rc.common
# we check if service is enabled explicitely, because we don't delete symlinks
[ -n "$USE_PROCD" ] && {
	start() {
		name="$(basename ${basescript:-$initscript})"
		if ! enabled; then
			echo "<6>Service $name is disabled" > /dev/kmsg
			return
		fi

		rc_procd start_service "$@"
		if eval "type service_started" 2>/dev/null >/dev/null; then
			service_started
		fi
	}
}
