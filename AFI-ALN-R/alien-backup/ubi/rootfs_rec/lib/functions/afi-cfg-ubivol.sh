#!/bin/sh

find_ubi_by_name()
{
	local id
	id=$(ubinfo /dev/ubi0 -N $1 | awk '/Volume ID:/ {print $3}')
	echo "/dev/ubi0_$id"
}

cfg_read()
{
	local files=""
	for line in $(grep -o "/etc/.*" /etc/persist_list | sort | uniq)
	do
		[ -e /tmp/persist/$line ] && files="$files /tmp/persist/$line"
	done

	rm -rf $files
	tar -xzf /etc/default.tar.gz -C /tmp/persist

	( tar -xzf $(find_ubi_by_name cfg) -C /tmp/persist &> /dev/null ) ||
	( tar -xzf $(find_ubi_by_name cfg2) -C /tmp/persist &> /dev/null ) ||
	( echo "Using default configuration" &&
		( grep -q /tmp/stats /proc/mounts ||
		  ubiupdatevol $(find_ubi_by_name stats) -t ) )
}

cfg_write()
{
	local files=""
	for line in $(grep -o "etc/.*" /etc/persist_list | sort | uniq)
	do
		[ -e /tmp/persist/$line ] && files="$files $line"
	done

	tar -czf /tmp/persist.tar.gz $files -C/tmp/persist

	# check if 'cfg' contents are valid
	tar -Oxzf $(find_ubi_by_name cfg) &> /dev/null
	if [ $? -eq 0 ]; then
		# update 'cfg2' and swap with 'cfg'
		ubiupdatevol $(find_ubi_by_name cfg2) /tmp/persist.tar.gz
		ubirename /dev/ubi0 cfg2 cfg cfg cfg2
	else
		# 'cfg' invalid -> only update cfg
		ubiupdatevol $(find_ubi_by_name cfg) /tmp/persist.tar.gz
	fi

	rm -f /tmp/persist.tar.gz
}

cfg_erase()
{
	ubiupdatevol $(find_ubi_by_name cfg) -t
	ubiupdatevol $(find_ubi_by_name cfg2) -t
}
