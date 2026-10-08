#!/bin/sh
#
# Copyright (c) 2017-2018 Qualcomm Technologies, Inc.
# All Rights Reserved.
# Confidential and Proprietary - Qualcomm Technologies, Inc.
#
# Copyright (c) 2014, 2016, The Linux Foundation. All rights reserved.
#
append DRIVERS "qcawificfg80211"

log() {
    logger -s -t "qcawificfg80211" "$*"
}

lockfile() {
        TEMPFILE="$1.$$"
        LOCKFILE="$1.lock"
        ( ( echo $$ > $TEMPFILE ) > /dev/null 2>&1 ) || \
        {
        log "[permission denied] fail to lock $1"
                return 1
        }
        ln $TEMPFILE $LOCKFILE > /dev/null 2>&1 && \
        {
                rm -f $TEMPFILE
                return 0
        }
        kill -0 `cat $LOCKFILE` > /dev/null 2>&1 && \
        {
                rm -f $TEMPFILE
                return 1
        }
        log "[stale lock] removing stale lock file"
        rm -f $LOCKFILE
        ln $TEMPFILE $LOCKFILE > /dev/null 2>&1 && \
        {
                rm -f $TEMPFILE
                return 0
        }
        rm -f $TEMPFILE
        return 1
}

state_lock() {
	until lockfile /var/run/wifi.state; do
		log "[state is locked] waiting for lock"
		sleep 1
	done
}

state_unlock() {
	/bin/rm -f "/var/run/wifi.state.lock"
}


start_recovery_daemon() {
	[ -n "${DEBUG}" ] && echo starting recovery daemon
	killall acfg_tool
	[ -f /usr/sbin/acfg_tool ] && /usr/sbin/acfg_tool -e -s > /dev/console &
}

find_qcawifi_phy() {
	local device="$1"

	local macaddr="$(config_get "$device" macaddr | tr 'A-Z' 'a-z')"
	config_get phy "$device" phy
	[ -z "$phy" -a -n "$macaddr" ] && {
		cd /sys/class/net
		for phy in $(ls -d radio* 2>&-); do
			[ "$macaddr" = "$(cat /sys/class/net/${phy}/address)" ] || continue
			config_set "$device" phy "$phy"
			break
		done
		config_get phy "$device" phy
	}
	[ -n "$phy" -a -d "/sys/class/net/$phy" ] || {
		echo "phy for wifi device $1 not found"
		return 1
	}
	[ -z "$macaddr" ] && {
		config_set "$device" macaddr "$(cat /sys/class/net/${phy}/address)"
	}
	return 0
}

scan_qcawificfg80211() {
	local device="$1"
	local wds
	local adhoc sta ap monitor lite_monitor ap_monitor ap_smart_monitor mesh ap_lp_iot disabled

	[ ${device%[0-9]} = "radio" ] && config_set "$device" phy "$device"

	local ifidx=0
	local radioidx=${device#radio}

	config_get vifs "$device" vifs
	for vif in $vifs; do
		config_get_bool disabled "$vif" disabled 0
		[ $disabled = 0 ] || continue

		local vifname
		[ $ifidx -gt 0 ] && vifname="wlan${radioidx}$ifidx" || vifname="wlan${radioidx}"

		config_set "$vif" ifname $vifname

		config_get mode "$vif" mode
		case "$mode" in
		adhoc |\
		sta |\
		ap |\
		monitor |\
		lite_monitor |\
		wrap |\
		ap_monitor |\
		ap_smart_monitor |\
		mesh |\
		ap_lp_iot)
			append "$mode" "$vif"
			;;
		wds)
			config_get ssid "$vif" ssid
			[ -z "$ssid" ] && continue

			config_set "$vif" wds 1
			config_set "$vif" mode sta
			mode="sta"
			addr="$ssid"
			${addr:+append "$mode" "$vif"}
			;;
		*)
			echo "$device($vif): Invalid mode, ignored."
			continue
			;;
		esac

		ifidx=$(($ifidx + 1))
	done

	case "${adhoc:+1}:${sta:+1}:${ap:+1}" in
		# valid mode combinations
		1::) wds="";;
		1::1);;
		:1:1)config_set "$device" nosbeacon 1;; # AP+STA, cant use beacon timers for STA
		:1:);;
		::1);;
		::);;
		*) echo "$device: Invalid mode combination in config"; return 1;;
	esac

	config_set "$device" vifs "${ap:+$ap }${ap_monitor:+$ap_monitor }${mesh:+$mesh }${ap_smart_monitor:+$ap_smart_monitor }${wrap:+$wrap }${sta:+$sta }${adhoc:+$adhoc }${wds:+$wds }${monitor:+$monitor}${lite_monitor:+$lite_monitor }${ap_lp_iot:+$ap_lp_iot}"
}

disable_recover_qcawificfg80211() {
	disable_qcawificfg80211 $@ 1
}

enable_recover_qcawificfg80211() {
	enable_qcawificfg80211 $@ 1
}

_disable_qcawificfg80211() {
	local device="$1"
	local parent
	local retval=0
	local recover="$2"

	echo "$DRIVERS disable radio $1" >/dev/console

	find_qcawifi_phy "$device" >/dev/null || return 1

	# If qrfs is disabled in enable_qcawifi(),need to enable it
	if [ -f /var/qrfs_disabled_by_wifi ] && [ $(cat /var/qrfs_disabled_by_wifi) == 1 ]; then
		echo "1" > /proc/qrfs/enable
		echo "0" > /var/qrfs_disabled_by_wifi
	fi

	config_get phy "$device" phy

	set_wifi_down "$device"

	return 0
}

disable_qcawificfg80211() {
	local device="$1"
	state_lock
	_disable_qcawificfg80211 "$device" $2
	state_unlock
}

_enable_qcawificfg80211() {
	local device="$1"
	local count=0
	echo "$DRIVERS: enable radio $1" >/dev/console
	local num_radio_instamode=0
	local recover="$2"

	find_qcawifi_phy "$device" || return 1

	if [ ! -f /lib/wifi/wifi_nss_override ]; then
		if [ -f /lib/wifi/wifi_nss_olcfg ] && [ $(cat /lib/wifi/wifi_nss_olcfg) != 0 ]; then
			touch /lib/wifi/wifi_nss_override
			echo 0 > /lib/wifi/wifi_nss_override
		fi
	fi

	if [ -f /lib/wifi/wifi_nss_override ]; then
		cd /sys/class/net
		for all_device in $(ls -d radio* 2>&-); do
			config_get_bool disabled "$all_device" disabled 0
			[ $disabled = 0 ] || continue
			config_get vifs "$all_device" vifs

			for vif in $vifs; do
				config_get mode "$vif" mode
				if [ $mode = "sta" ]; then
					num_radio_instamode=$(($num_radio_instamode + 1))
					break
				fi
			done
			if [ $num_radio_instamode = "0" ]; then
				break
			fi
		done

		nss_override="$(cat /lib/wifi/wifi_nss_override)"
		if [ $num_radio_instamode = "3" ]; then
			config_get nss_wifi_olcfg qcawifi nss_wifi_olcfg
			if [ -n "$nss_wifi_olcfg" ] && [ $nss_wifi_olcfg != 0 ]; then
				echo " Invalid Configuration: 3 stations in offload not supported"
				return 1
			fi
			if [ $nss_override = "0" ]; then
				echo 1 > /lib/wifi/wifi_nss_override
				device=$1
			fi
		else
			if [ $nss_override != "0" ]; then
				echo 0 > /lib/wifi/wifi_nss_override
				device=$1
			fi
		fi
	fi
}

enable_qcawificfg80211() {
	local device="$1"
	state_lock
	_enable_qcawificfg80211 "$device" $2
	state_unlock
}

check_qcawifi_device() {
	[ ${1%[0-9]} = "radio" ] && config_set "$1" phy "$1"
	config_get phy "$1" phy
	[ -z "$phy" ] && {
		find_qcawifi_phy "$1" >/dev/null || return 1
		config_get phy "$1" phy
	}
	[ "$phy" = "$dev" ] && found=1
}

detect_qcawificfg80211() {
	devidx=0
	sleep 3

	config_load wireless

	while :; do
		config_get type "radio$devidx" type
		[ -n "$type" ] || break
		devidx=$(($devidx + 1))
	done

	cd /sys/class/net
	[ -d radio0 ] || return

	local system_info="/proc/ubnthal/system.info"
	for dev in $(ls -d radio* 2>&-); do
		found=0
		config_foreach check_qcawifi_device wifi-device
		[ "$found" -gt 0 ] && continue

		hwcaps=$(cat ${dev}/hwcaps)
		case "${hwcaps}" in
			*11an)          mode_11=a; mode_ht=HT20;;
			*11an/ac)       mode_11=a; mode_ht=VHT20;;
			*11an/ac/ax)    mode_11=a; mode_ht=HE20;;
			*11abgn/ac)     mode_11=a; mode_ht=HT20;;
			*11abgn/ac/ax)  mode_11=g; mode_ht=HE20;;
			*11abgn)        mode_11=g; mode_ht=HT20;;
			*11bgn)         mode_11=g; mode_ht=HT20;;
			*11bgn/ax)      mode_11=g; mode_ht=HE20;;
		esac

		role="unknown"
		if [ -f $system_info ]; then
			role=$(cat ${system_info} | grep "wifi${devidx}.role" | cut -f 2 -d '=')
		fi

		uci -q batch <<-EOF
			set wireless.radio${devidx}=wifi-device
			set wireless.radio${devidx}.type=qcawificfg80211
			set wireless.radio${devidx}.channel=auto
			set wireless.radio${devidx}.macaddr=$(cat /sys/class/net/${dev}/address)
			set wireless.radio${devidx}.hwmode=11${mode_11}
			set wireless.radio${devidx}.htmode=${mode_ht}
			set wireless.radio${devidx}.disabled=1
			set wireless.radio${devidx}.ubnt_role=${role}
EOF
		uci -q commit wireless
		devidx=$(($devidx + 1))
	done

	start_recovery_daemon
}

# Handle traps here
trap_qcawifi() {
	# Release any locks taken
	state_unlock
}
