#!/bin/sh

. /lib/functions/board.sh
case "$(board_name)" in
	ubiquiti,afi-aln*)
		. /lib/functions/afi-cfg-ubivol.sh
		;;
	*)
		. /lib/functions/afi-cfg-spipart.sh
		;;
esac


cfg_lock()
{
	# Lock on all UCI configuration files
	if [ "$3" != "locked" ]; then
		flock_cmd=""

		if [ "$2" == "write" ]; then
			shared_lock="-s"
		fi

		for line in $(grep -o "/etc/config/.*" /etc/persist_list | sort | uniq)
		do
			[ -e /tmp/persist/$line ] && \
				flock_cmd="$flock_cmd flock $shared_lock $line "
		done

		$flock_cmd $1 $2 locked
		exit 0
	fi
}

case "$1" in
	"read")
		cfg_lock $0 $1 $2
		cfg_read
		;;
	"write")
		cfg_lock $0 $1 $2
		cfg_write
		;;
	"erase")
		cfg_erase
		;;
	*)
		echo "Usage:"
		echo "  read  - restore configuration from persistent storage"
		echo "  write - store configuration into persistent storage"
		echo "  erase - erase persistent storage"
		exit 1
		;;
esac

exit 0
