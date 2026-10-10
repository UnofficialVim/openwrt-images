PART_NAME=firmware
REQUIRE_IMAGE_METADATA=0

RAMFS_COPY_BIN='fw_printenv fw_setenv /sbin/uh-fw-tool /usr/sbin/ubirename /bin/sleep'
RAMFS_COPY_DATA='/etc/fw_env.config /var/lock/fw_printenv.lock /etc/openwrt_release'

afi_get_magic()
{
	get_image "$1" |\
		dd bs=1 count=4 2>/dev/null |\
		hexdump -v -n 4 -e '1/1 "%c"'
}

afi_get_version()
{
	get_image "$1" |\
		dd bs=1 count=256 skip=4 2>/dev/null |\
		hexdump -v -n 256 -e '1/1 "%c"'
}

afi_get_fw_tool()
{
	echo "/sbin/uh-fw-tool"
}

platform_pre_upgrade_afi()
{
	# TODO: need LCM/uictld support
	#ubus call uictld upgrade
	sleep 1
	return 0
}

platform_check_image_afi()
{
	local magic=$(afi_get_magic "$1")

	if [ "$magic" != "UBNT" ] && [ "$magic" != "OPEN" ]; then
		echo "Invalid image type."
		return 1
	fi

	for fw_tool in "$(afi_get_fw_tool)" "$(afi_get_fw_tool)-$magic"; do
		[ -f $fw_tool ] && {
			$fw_tool -ci $1 || return $?
		}
	done

	return 0
}

platform_do_upgrade_afi()
{
	local uboot=""

	sync
	if echo "$ARGV" | grep -q " -u$"; then
		uboot="-u"
		ARGV=`echo -n $ARGV | head -c -3`
	fi

	echo upgrade > /proc/afi_leds/mode
	$(afi_get_fw_tool) -f $uboot $ARGV
}

platform_check_image()
{
	case "$(board_name)" in
	ubiquiti,afi-aln*)
		platform_check_image_afi "$1"
		return $?;
		;;
	*)
		;;
	esac

	return 0;
}

platform_do_upgrade()
{
	case "$(board_name)" in
	ubiquiti,afi-aln*)
		platform_do_upgrade_afi "$ARGV"
		;;
	*)
		default_do_upgrade "$ARGV"
		;;
	esac
}

platform_nand_pre_upgrade()
{
	case "$(board_name)" in
	ubiquiti,afi-aln*)
		platform_pre_upgrade_afi "$ARGV"
		;;
	*)
		;;
	esac
}

blink_led()
{
	. /etc/diag.sh
	set_state upgrade
}

append sysupgrade_pre_upgrade blink_led
