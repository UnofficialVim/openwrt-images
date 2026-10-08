#!/bin/sh

afi_ubnthal()
{
	local f=$1
	local key=$2
	awk -F= -v key=$key '$1 == key {print $2}' /proc/ubnthal/$f
}

afi_board_name()
{
	afi_ubnthal board name
}

afi_board_shortname()
{
	afi_ubnthal board shortname
}

afi_board_eth0_macaddr()
{
	afi_ubnthal board eth0.macaddr
}

afi_board_bomrev()
{
	local bomid=$(afi_ubnthal board bomrev)
	printf "%d\n" 0x${bomid:6:2}
}

afi_get_eth_uplink_port()
{
	local port=1

	case "$(afi_board_shortname)" in
		AFi-INS-R*)
			port=2
			;;
		AFi-INS-P)
			uci -q get ajconfd.@device_config[0].ethernet_backbone |\
				grep -q 1 && port=3
			;;
		AFi-ALN-R)
			port=5
			;;
		*)
			port=1
			;;
	esac

	echo $port
}
