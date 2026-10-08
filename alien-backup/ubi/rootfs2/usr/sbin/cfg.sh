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

usage()
{
	echo "Usage:"
	echo "	read  - restore configuration from persistent storage"
	echo "	write - store configuration into persistent storage"
	echo "	erase - erase persistent storage"
}

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

		$flock_cmd $1 cfgd $2 locked
		exit 0
	fi
}

use_cfgd=1

ubus -t1 wait_for cfgd 2> /dev/null || {
	use_cfgd=0
}

if [ "$use_cfgd" = "1" ]; then
	case "$1" in
		"read"|"write"|"erase")
			ubus call cfgd $1
			exit $?
			;;
		"cfgd")
			;;
		*)
			usage
			exit 1
			;;
	esac
fi

if [ "$1" = "cfgd" ]; then
	shift 1
elif [ "$use_cfgd" = "0" ]; then
	logger "cfgd not running, doing $1 anyway"
fi

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
		touch /tmp/cfg_erased
		cfg_erase
		;;
	*)
		usage
		exit 1
		;;
esac

exit 0
