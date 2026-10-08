#!/bin/sh

cfg_read()
{
	local files=""
	for line in $(grep -o "/etc/.*" /etc/persist_list | sort | uniq)
	do
		[ -e /tmp/persist/$line ] && files="$files /tmp/persist/$line"
	done

	rm -rf $files
	tar -xzf /etc/default.tar.gz -C /tmp/persist
	/usr/sbin/cfg_tool -r | tar -xzf - -C /tmp/persist &> /dev/null || \
		( echo "Using default configuration" && /usr/bin/stats_tool -c )
}

cfg_write()
{
	local files=""
	for line in $(grep -o "etc/.*" /etc/persist_list | sort | uniq)
	do
		[ -e /tmp/persist/$line ] && files="$files $line"
	done

	tar -czf - $files -C/tmp/persist | /usr/sbin/cfg_tool -w
}

cfg_erase()
{
	/usr/sbin/cfg_tool -e
}
