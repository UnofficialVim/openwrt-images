#!/bin/sh
. /lib/netifd/netifd-wireless.sh
. /lib/netifd/hostapd.sh

init_wireless_driver "$@"

MP_CONFIG_INT="mesh_retry_timeout mesh_confirm_timeout mesh_holding_timeout mesh_max_peer_links
	       mesh_max_retries mesh_ttl mesh_element_ttl mesh_hwmp_max_preq_retries
	       mesh_path_refresh_time mesh_min_discovery_timeout mesh_hwmp_active_path_timeout
	       mesh_hwmp_preq_min_interval mesh_hwmp_net_diameter_traversal_time mesh_hwmp_rootmode
	       mesh_hwmp_rann_interval mesh_gate_announcements mesh_sync_offset_max_neighor
	       mesh_rssi_threshold mesh_hwmp_active_path_to_root_timeout mesh_hwmp_root_interval
	       mesh_hwmp_confirmation_interval mesh_awake_window mesh_plink_timeout"
MP_CONFIG_BOOL="mesh_auto_open_plinks mesh_fwding"
MP_CONFIG_STRING="mesh_power_mode"

CASCADE_DEVICE_ID="0x0046"

HOSTAPD_BSS_SUPPORT=1

drv_qcawificfg80211_init_device_config() {
	hostapd_common_add_device_config

	config_add_string path phy 'macaddr:macaddr'
	config_add_string hwmode
	config_add_int beacon_int chanbw frag rts
	config_add_int rxantenna txantenna antenna_gain txpower distance
	config_add_boolean noscan ht_coex dfs_enabled
	config_add_array ht_capab
	config_add_array channels
	config_add_array dyn_bw_rts_bl
	config_add_boolean \
		rxldpc \
		short_gi_80 \
		short_gi_160 \
		tx_stbc_2by1 \
		su_beamformer \
		su_beamformee \
		mu_beamformer \
		mu_beamformee \
		vht_txop_ps \
		htc_vht \
		rx_antenna_pattern \
		tx_antenna_pattern
	config_add_int vht_max_a_mpdu_len_exp vht_max_mpdu vht_link_adapt vht160 rx_stbc tx_stbc
	config_add_boolean \
		ldpc \
		greenfield \
		short_gi_20 \
		short_gi_40 \
		max_amsdu \
		dsss_cck_40

}

drv_qcawificfg80211_init_iface_config() {
	hostapd_common_add_bss_config

	config_add_string 'macaddr:macaddr' ifname

	config_add_boolean wds powersave uapsd
	config_add_int maxassoc
	config_add_int max_listen_int
	config_add_int dtim_period
	config_add_int start_disabled
	config_add_int start_disabled_adm
	config_add_int bs_ax_only
	config_add_int bs_ax_deny
	config_add_int dyn_bw_rts_mode
	config_add_string ubnt_role
	config_add_string add_scan_ssid
	config_add_boolean multiple_hops

	# mesh
	config_add_string mesh_id
	config_add_int $MP_CONFIG_INT
	config_add_boolean $MP_CONFIG_BOOL
	config_add_string $MP_CONFIG_STRING
}

qcawificfg80211_add_capabilities() {
	local __var="$1"; shift
	local __mask="$1"; shift
	local __out= oifs

	oifs="$IFS"
	IFS=:
	for capab in "$@"; do
		set -- $capab

		[ "$(($4))" -gt 0 ] || continue
		[ "$(($__mask & $2))" -eq "$((${3:-$2}))" ] || continue
		__out="$__out[$1]"
	done
	IFS="$oifs"

	export -n -- "$__var=$__out"
}

qcawificfg80211_hostapd_setup_base() {
	local phy="$1"
	local dfs_enabled=$2

	json_select config

	[ "$auto_channel" -gt 0 ] && channel=acs_survey
	[ "$auto_channel" -gt 0 ] && json_get_values channel_list channels

	json_get_vars noscan ht_coex
	json_get_values ht_capab_list ht_capab

	ieee80211n=1
	ht_capab=
	case "$htmode" in
		HE20|VHT20|HT20) ;;
		HT40*|VHT40|VHT80|VHT160|\
		HE40*|HE40|HE80|HE160)
			case "$hwmode" in
				a)
					case "$(( ($channel / 4) % 2 ))" in
						1) ht_capab="[HT40+]";;
						0) ht_capab="[HT40-]";;
					esac
				;;
				*)
					case "$htmode" in
						HT40+) ht_capab="[HT40+]";;
						HT40-) ht_capab="[HT40-]";;
						*)
							if [ "$channel" -lt 7 ]; then
								ht_capab="[HT40+]"
							else
								ht_capab="[HT40-]"
							fi
						;;
					esac
				;;
			esac
			[ "$auto_channel" -gt 0 ] && ht_capab="[HT40+]"
		;;
		*) ieee80211n= ;;
	esac

	[ -n "$ieee80211n" ] && {
		append base_cfg "ieee80211n=1" "$N"

		set_default ht_coex 0
		append base_cfg "ht_coex=$ht_coex" "$N"

		json_get_vars \
			ldpc:1 \
			greenfield:0 \
			short_gi_20:1 \
			short_gi_40:1 \
			tx_stbc:1 \
			rx_stbc:3 \
			max_amsdu:1 \
			dsss_cck_40:1

		ht_cap_mask=0
		for cap in $(iw phy "$phy" info | grep 'Capabilities:' | cut -d: -f2); do
			ht_cap_mask="$(($ht_cap_mask | $cap))"
		done

		cap_rx_stbc=$((($ht_cap_mask >> 8) & 3))
		[ "$rx_stbc" -lt "$cap_rx_stbc" ] && cap_rx_stbc="$rx_stbc"
		ht_cap_mask="$(( ($ht_cap_mask & ~(0x300)) | ($cap_rx_stbc << 8) ))"

		qcawificfg80211_add_capabilities ht_capab_flags $ht_cap_mask \
			LDPC:0x1::$ldpc \
			GF:0x10::$greenfield \
			SHORT-GI-20:0x20::$short_gi_20 \
			SHORT-GI-40:0x40::$short_gi_40 \
			TX-STBC:0x80::$tx_stbc \
			RX-STBC1:0x300:0x100:1 \
			RX-STBC12:0x300:0x200:1 \
			RX-STBC123:0x300:0x300:1 \
			MAX-AMSDU-7935:0x800::$max_amsdu \
			DSSS_CCK-40:0x1000::$dsss_cck_40

		ht_capab="$ht_capab$ht_capab_flags"
		[ -n "$ht_capab" ] && append base_cfg "ht_capab=$ht_capab" "$N"
	}

	# 802.11ac
	enable_ac=0
	idx="$channel"
	case "$htmode" in
		HE20|VHT20)
			case "$hwmode" in
				a) enable_ac=1
				;;
				*) enable_ac=0
				;;
			esac
		;;
		HE40|VHT40)
			case "$hwmode" in
				a) enable_ac=1
					case "$(( ($channel / 4) % 2 ))" in
						1) idx=$(($channel + 2));;
						0) idx=$(($channel - 2));;
					esac
					append base_cfg "vht_oper_chwidth=0" "$N"
					append base_cfg "vht_oper_centr_freq_seg0_idx=$idx" "$N"
				;;
				*) enable_ac=0
				;;
			esac
		;;
		HE80|VHT80)
			case "$(( ($channel / 4) % 4 ))" in
				1) idx=$(($channel + 6));;
				2) idx=$(($channel + 2));;
				3) idx=$(($channel - 2));;
				0) idx=$(($channel - 6));;
			esac
			enable_ac=1
			append base_cfg "vht_oper_chwidth=1" "$N"
			append base_cfg "vht_oper_centr_freq_seg0_idx=$idx" "$N"
		;;
		HE160|VHT160)
			case "$channel" in
				36|40|44|48|52|56|60|64) idx=50;;
				100|104|108|112|116|120|124|128) idx=114;;
			esac
			enable_ac=1
			append base_cfg "vht_oper_chwidth=2" "$N"
			append base_cfg "vht_oper_centr_freq_seg0_idx=$idx" "$N"
		;;
	esac

	if [ "$enable_ac" != "0" ]; then
		json_get_vars \
			rxldpc:1 \
			short_gi_80:1 \
			short_gi_160:1 \
			tx_stbc_2by1:1 \
			su_beamformer:1 \
			su_beamformee:1 \
			mu_beamformer:1 \
			mu_beamformee:1 \
			vht_txop_ps:1 \
			htc_vht:1 \
			rx_antenna_pattern:1 \
			tx_antenna_pattern:1 \
			vht_max_a_mpdu_len_exp:7 \
			vht_max_mpdu:11454 \
			rx_stbc:4 \
			tx_stbc:4 \
			vht_link_adapt:3 \
			vht160:2

		append base_cfg "ieee80211ac=1" "$N"
		vht_cap=0
		for cap in $(iw phy "$phy" info | awk -F "[()]" '/VHT Capabilities/ { print $2 }'); do
			vht_cap="$(($vht_cap | $cap))"
		done

		cap_rx_stbc=$((($vht_cap >> 8) & 7))
		[ "$rx_stbc" -lt "$cap_rx_stbc" ] && cap_rx_stbc="$rx_stbc"
		ht_cap_mask="$(( ($vht_cap & ~(0x700)) | ($cap_rx_stbc << 8) ))"

		qcawificfg80211_add_capabilities vht_capab $vht_cap \
			RXLDPC:0x10::$rxldpc \
			SHORT-GI-80:0x20::$short_gi_80 \
			SHORT-GI-160:0x40::$short_gi_160 \
			TX-STBC-2BY1:0x80::$tx_stbc \
			SU-BEAMFORMER:0x800::$su_beamformer \
			SU-BEAMFORMEE:0x1000::$su_beamformee \
			MU-BEAMFORMER:0x80000::$mu_beamformer \
			MU-BEAMFORMEE:0x100000::$mu_beamformee \
			VHT-TXOP-PS:0x200000::$vht_txop_ps \
			HTC-VHT:0x400000::$htc_vht \
			RX-ANTENNA-PATTERN:0x10000000::$rx_antenna_pattern \
			TX-ANTENNA-PATTERN:0x20000000::$tx_antenna_pattern \
			RX-STBC1:0x700:0x100:1 \
			RX-STBC12:0x700:0x200:1 \
			RX-STBC123:0x700:0x300:1 \
			RX-STBC1234:0x700:0x400:1 \

		# supported Channel widths
		vht160_hw=0
		[ "$(($vht_cap & 12))" -eq 4 -a 1 -le "$vht160" ] && \
			vht160_hw=1
		[ "$(($vht_cap & 12))" -eq 8 -a 2 -le "$vht160" ] && \
			vht160_hw=2
		[ "$vht160_hw" = 1 ] && vht_capab="$vht_capab[VHT160]"
		[ "$vht160_hw" = 2 ] && vht_capab="$vht_capab[VHT160-80PLUS80]"

		# maximum MPDU length
		vht_max_mpdu_hw=3895
		[ "$(($vht_cap & 3))" -ge 1 -a 7991 -le "$vht_max_mpdu" ] && \
			vht_max_mpdu_hw=7991
		[ "$(($vht_cap & 3))" -ge 2 -a 11454 -le "$vht_max_mpdu" ] && \
			vht_max_mpdu_hw=11454
		[ "$vht_max_mpdu_hw" != 3895 ] && \
			vht_capab="$vht_capab[MAX-MPDU-$vht_max_mpdu_hw]"
			
		# maximum A-MPDU length exponent
		vht_max_a_mpdu_len_exp_hw=0
		[ "$(($vht_cap & 58720256))" -ge 8388608 -a 1 -le "$vht_max_a_mpdu_len_exp" ] && \
			vht_max_a_mpdu_len_exp_hw=1
		[ "$(($vht_cap & 58720256))" -ge 16777216 -a 2 -le "$vht_max_a_mpdu_len_exp" ] && \
			vht_max_a_mpdu_len_exp_hw=2
		[ "$(($vht_cap & 58720256))" -ge 25165824 -a 3 -le "$vht_max_a_mpdu_len_exp" ] && \
			vht_max_a_mpdu_len_exp_hw=3
		[ "$(($vht_cap & 58720256))" -ge 33554432 -a 4 -le "$vht_max_a_mpdu_len_exp" ] && \
			vht_max_a_mpdu_len_exp_hw=4
		[ "$(($vht_cap & 58720256))" -ge 41943040 -a 5 -le "$vht_max_a_mpdu_len_exp" ] && \
			vht_max_a_mpdu_len_exp_hw=5
		[ "$(($vht_cap & 58720256))" -ge 50331648 -a 6 -le "$vht_max_a_mpdu_len_exp" ] && \
			vht_max_a_mpdu_len_exp_hw=6
		[ "$(($vht_cap & 58720256))" -ge 58720256 -a 7 -le "$vht_max_a_mpdu_len_exp" ] && \
			vht_max_a_mpdu_len_exp_hw=7
		vht_capab="$vht_capab[MAX-A-MPDU-LEN-EXP$vht_max_a_mpdu_len_exp_hw]"

		# whether or not the STA supports link adaptation using VHT variant
		vht_link_adapt_hw=0
		[ "$(($vht_cap & 201326592))" -ge 134217728 -a 2 -le "$vht_link_adapt" ] && \
			vht_link_adapt_hw=2
		[ "$(($vht_cap & 201326592))" -ge 201326592 -a 3 -le "$vht_link_adapt" ] && \
			vht_link_adapt_hw=3
		[ "$vht_link_adapt_hw" != 0 ] && \
			vht_capab="$vht_capab[VHT-LINK-ADAPT-$vht_link_adapt_hw]"

		[ -n "$vht_capab" ] && append base_cfg "vht_capab=$vht_capab" "$N"
	fi

	hostapd_prepare_device_config "$hostapd_conf_file" nl80211 $dfs_enabled

	# Appropriate IE will be inserted by target
	sed -i "/ieee80211d*\|country_code*/d" $hostapd_conf_file
	# qca-wifi doesn't provide MAC address configuration
	echo "use_driver_iface_addr=1" >> $hostapd_conf_file

	cat >> "$hostapd_conf_file" <<EOF
${channel:+channel=$channel}
${channel_list:+chanlist=$channel_list}
${noscan:+noscan=$noscan}
$base_cfg

EOF
	json_select ..
}

qcawificfg80211_hostapd_setup_bss() {
	local phy="$1"
	local ifname="$2"
	local type="$3"

	hostapd_cfg=
	append hostapd_cfg "$type=$ifname" "$N"

	hostapd_set_bss_options hostapd_cfg $phy "$vif" || return 1
	json_get_vars wds dtim_period max_listen_int start_disabled start_disabled_adm ubnt_role

	set_default wds 0
	set_default start_disabled 0
	set_default start_disabled_adm 0

	[ "$wds" -gt 0 ] && append hostapd_cfg "wds_sta=1" "$N"
	[ "$staidx" -gt 0 -o "$start_disabled" -eq 1 ] && append hostapd_cfg "start_disabled=1" "$N"
	[ "$start_disabled_adm" -eq 1 ] && {
		append hostapd_cfg "start_disabled_adm=1" "$N"
		append hostapd_cfg "start_deferred_adm=1" "$N"
	}
	append hostapd_cfg "disable_probe_resp=1" "$N"

	cat >> /var/run/hostapd-$phy.$ifname.conf <<EOF
$hostapd_cfg
noscan=1
${dtim_period:+dtim_period=$dtim_period}
${max_listen_int:+max_listen_interval=$max_listen_int}
EOF
}

qcawificfg80211_find_radio() {
    local radio_phy=$1
    cd /sys/class/net
    for radio in $(ls -d radio* 2>&-); do
    	[ "$radio_phy" = "$(cat /sys/class/net/${radio}/phy80211/name)" ] || continue
        echo "$radio"
        return
    done

    return
}

qcawificfg80211_get_addr() {
	local phy="$1"
	local idx="$(($2 + 1))"

	head -n $(($macidx + 1)) /sys/class/ieee80211/${phy}/addresses | tail -n1
}

afi_radio_check_casdcade() {
	local radio=$1
	local device_id="/sys/class/net/${radio}/device/device"

	if [ -f $device_id ] && [ $(cat $device_id) = "$CASCADE_DEVICE_ID" ]; then
		return 0
	fi

	return 1
}


qcawificfg80211_interface_exists() {
	local iface_name="$1"

	if [ -d "/sys/class/net/${iface_name}" ]; then
		return 0
	fi

	return 1
}

qcawificfg80211_generate_mac() {
	local phy="$1"
	local id="${macidx:-0}"
	local radio="$(qcawificfg80211_find_radio $phy)"
	local ref="$(cat /sys/class/net/${radio}/address)"
	local mask="$(cat /sys/class/ieee80211/${phy}/address_mask)"

	[ "$mask" = "00:00:00:00:00:00" ] && {
		mask="ff:ff:ff:ff:ff:ff";

		[ "$(wc -l < /sys/class/ieee80211/${phy}/addresses)" -gt 1 ] && {
			addr="$(qcawificfg80211_get_addr "$phy" "$id")"
			[ -n "$addr" ] && {
				echo "$addr"
				return
			}
		}
	}

	local oIFS="$IFS"; IFS=":"; set -- $mask; IFS="$oIFS"

	local mask1=$1
	local mask6=$6

	local oIFS="$IFS"; IFS=":"; set -- $ref; IFS="$oIFS"

	macidx=$(($id + 1))
	[ "$((0x$mask1))" -gt 0 ] && {
		b1="0x$1"
		[ "$id" -gt 0 ] && \
			b1=$(($b1 ^ ((($id - 1) << 2) | 0x2)))
		printf "%02x:%s:%s:%s:%s:%s" $b1 $2 $3 $4 $5 $6
		return
	}

	[ "$((0x$mask6))" -lt 255 ] && {
		printf "%s:%s:%s:%s:%s:%02x" $1 $2 $3 $4 $5 $(( 0x$6 ^ $id ))
		return
	}

	off2=$(( (0x$6 + $id) / 0x100 ))
	printf "%s:%s:%s:%s:%02x:%02x" \
		$1 $2 $3 $4 \
		$(( (0x$5 + $off2) % 0x100 )) \
		$(( (0x$6 + $id) % 0x100 ))
}

qcawificfg80211_setup_htmode() {
	local phy="$1"
	local ifname="$2"

	case "$htmode:$hwmode" in
		# 11n modes
		HT20:*g) 	qca_htmode=11NGHT20;;
		HT40-:*g) 	qca_htmode=11NGHT40MINUS;;
		HT40+:*g) 	qca_htmode=11NGHT40PLUS;;
		HT40:*g) 	qca_htmode=11NGHT40;;
		HT20:*a) 	qca_htmode=11NAHT20;;
		HT40:*a) 	qca_htmode=11NAHT40;;
		HT40-:*a) 	qca_htmode=11NAHT40MINUS;;
		HT40+:*a) 	qca_htmode=11NAHT40PLUS;;
		# 11ac modes
		VHT20:*a)	qca_htmode=11ACVHT20;;
		VHT40-:*a) 	qca_htmode=11ACVHT40MINUS;;
		VHT40+:*a) 	qca_htmode=11ACVHT40PLUS;;
		VHT40:*a) 	qca_htmode=11ACVHT40;;
		VHT80:*a) 	qca_htmode=11ACVHT80;;
		VHT160:*a) 	qca_htmode=11ACVHT160;;
		VHT80_80:*a) 	qca_htmode=11ACVHT80_80;;
		# 11ax modes
		HE20:*g) 	qca_htmode=11GHE20;;
		HE40-:*g) 	qca_htmode=11GHE40MINUS;;
		HE40+:*g) 	qca_htmode=11GHE40PLUS;;
		HE40:*g) 	qca_htmode=11GHE40;;
		HE20:*a) 	qca_htmode=11AHE20;;
		HE40-:*a) 	qca_htmode=11AHE40MINUS;;
		HE40+:*a) 	qca_htmode=11AHE40PLUS;;
		HE40:*a) 	qca_htmode=11AHE40;;
		HE80:*a) 	qca_htmode=11AHE80;;
		HE160:*a) 	qca_htmode=11AHE160;;
		HE80_80:*a) 	qca_htmode=11AHE80_80;;
		# Legacy modes:
		*:*a) 		qca_htmode=11A;;
		*:*b) 		qca_htmode=11B;;
		*:*g) 		qca_htmode=11G; pureg=1;;
	esac

	[ -n "$qca_htmode" ] && cfg80211tool "$ifname" mode "$qca_htmode" >/dev/null 2>&1
	[ -n $pureg ] && cfg80211tool "$ifname" pureg "$pureg" >/dev/null 2>&1
}

find_phy() {

	#hardcoded phy searching
	echo "Search PHY for device '$1'"
	phy=`cat /sys/class/net/$1/phy80211/name`
	macaddr=`cat /sys/class/net/$1/address`
	echo "FOUND for '$1' '$phy' : '$macaddr'"
	return 0

	[ -n "$phy" -a -d /sys/class/ieee80211/$phy ] && return 0
	[ -n "$path" ] && {
		for phy in $(ls /sys/class/ieee80211 2>/dev/null); do
			case "$(readlink -f /sys/class/ieee80211/$phy/device)" in
				*$path) return 0;;
			esac
		done
	}
	[ -n "$macaddr" ] && {
		for phy in $(ls /sys/class/ieee80211 2>/dev/null); do
			grep -i -q "$macaddr" "/sys/class/ieee80211/${phy}/macaddress" && return 0
		done
	}
	return 1
}

qcawificfg80211_check_ap() {
	has_ap=1
}

qcawificfg80211_prepare_vif() {
	json_select config

	json_get_vars ifname mode ssid wds macaddr hidden dynamicbeacon \
		shortgi backhaul stafwd isolate

	[ -n "$ifname" ] || ifname="wlan${phy#phy}${if_idx:+-$if_idx}"
	if_idx=$((${if_idx:-0} + 1))

	set_default wds 0
	set_default hidden 0
	set_default dynamicbeacon 0
	set_default shortgi 1
	set_default backhaul 0
	set_default stafwd 0
	set_default isolate 0

	json_select ..

	[ -n "$macaddr" ] || {
		macaddr="$(qcawificfg80211_generate_mac $phy)"
		macidx="$(($macidx + 1))"
	}

	json_add_object data
	json_add_string ifname "$ifname"
	json_close_object
	json_select config

	if [ "$HOSTAPD_BSS_SUPPORT" -ne "0" ]; then
		# Sanity clean-up just in case
		iw $ifname del >/dev/null 2>&1
		rm -f /var/run/hostapd-$phy.$ifname.conf
	fi

	# It is far easier to delete and create the desired interface
	case "$mode" in
		adhoc)
			iw phy "$phy" interface add "$ifname" type adhoc
		;;
		ap)
			if [ "$HOSTAPD_BSS_SUPPORT" -ne "0" ]; then
				# Hostapd will handle recreating the interface and 
				# subsequent virtual APs belonging to the same PHY 
				if [ -n "$hostapd_ctrl" ]; then
					type=bss
				else
					iw phy "$phy" interface add "$ifname" type __ap

					type=interface
				fi
			else
				type=interface

				hostapd_ctrl="${hostapd_ctrl:-/var/run/hostapd/$ifname}"
				cat /var/run/hostapd-$phy.conf >/var/run/hostapd-$phy.$ifname.conf

			fi

			qcawificfg80211_hostapd_setup_bss "$phy" "$ifname" "$type" || return

			if [ "$HOSTAPD_BSS_SUPPORT" -ne "0" ]; then
				[ -n "$hostapd_ctrl" ] || {
					hostapd_ctrl="${hostapd_ctrl:-/var/run/hostapd/$ifname}"
				}

				# Append interface/bss configuration to PHY config
				cat /var/run/hostapd-$phy.$ifname.conf >> /var/run/hostapd-$phy.conf
			fi
		;;
		mesh)
			iw phy "$phy" interface add "$ifname" type mp
		;;
		monitor)
			iw phy "$phy" interface add "$ifname" type monitor
		;;
		sta)
			local wdsflag=
			staidx="$(($staidx + 1))"
			[ "$wds" -gt 0 ] && wdsflag="4addr on"
			iw phy "$phy" interface add "$ifname" type managed $wdsflag
		;;
	esac

	# In MBSS mode some interfaces could be created by hostapd,
	# so we shouldn't try to configure them here
	qcawificfg80211_interface_exists "$ifname" && {
		# WDS
		case "$wds" in
			1|on|enabled|true)	wds=1
				iw "$ifname" set 4addr on >/dev/null 2>&1
				;;
			*)	wds=0
				;;
		esac
		cfg80211tool "$ifname" wds "$wds" >/dev/null 2>&1	

		qcawificfg80211_setup_htmode "$phy" "$ifname" || return

		cfg80211tool "$ifname" channel "$channel" >/dev/null 2>&1

		cfg80211tool "$ifname" hide_ssid "$hidden"

		# Hidden SSID
		cfg80211tool "$ifname" hide_ssid "$hidden" >/dev/null 2>&1
		[ $hidden = 1 ] && cfg80211tool "$ifname" dynamicbeacon "$dynamicbeacon" >/dev/null 2>&1
		
		# ShortGI
		cfg80211tool "$ifname" shortgi "$shortgi" >/dev/null 2>&1

		# Backhaul
		cfg80211tool "$ifname" backhaul "$backhaul" >/dev/null 2>&1
		
		# stafwd
		cfg80211tool "$ifname" stafwd "$stafwd" >/dev/null 2>&1

		case "$mode" in
			monitor|mesh)
				[ "$auto_channel" -gt 0 ] || iw dev "$ifname" set channel "$channel" $htmode
			;;
		esac
	}

	if [ "$mode" != "ap" ]; then
		# ALL ap functionality will be passed to hostapd
		# All interfaces must have unique mac addresses
		# which can either be explicitly set in the device
		# section, or automatically generated
		ip link set dev "$ifname" address "$macaddr"
	fi

	json_select ..
}

qcawificfg80211_setup_supplicant() {
	json_get_vars multiple_hops
	set_default multiple_hops 0

	wpa_supplicant_prepare_interface "$ifname" nl80211 || return 1
	if [ "$multiple_hops" -ne 0 ]; then
		wpa_supplicant_add_network "$ifname" "" "" "" -65 "" 4
		wpa_supplicant_add_network "$ifname" "" "" "" -65 "MP_" 3
		wpa_supplicant_add_network "$ifname" "" "" "" -75 "" 2
		wpa_supplicant_add_network "$ifname" "" "" "" -75 "MP_" 1
	else
		wpa_supplicant_add_network "$ifname" "" "" "" -100 "" 1
	fi
	wpa_supplicant_run "$ifname" ${hostapd_ctrl:+-H $hostapd_ctrl}
}

qcawificfg80211_setup_adhoc_htmode() {
	case "$htmode" in
		VHT20|HT20) ibss_htmode=HT20;;
		HT40*|VHT40|VHT160)
			case "$hwmode" in
				a)
					case "$(( ($channel / 4) % 2 ))" in
						1) ibss_htmode="HT40+" ;;
						0) ibss_htmode="HT40-";;
					esac
				;;
				*)
					case "$htmode" in
						HT40+) ibss_htmode="HT40+";;
						HT40-) ibss_htmode="HT40-";;
						*)
							if [ "$channel" -lt 7 ]; then
								ibss_htmode="HT40+"
							else
								ibss_htmode="HT40-"
							fi
						;;
					esac
				;;
			esac
			[ "$auto_channel" -gt 0 ] && ibss_htmode="HT40+"
		;;
		VHT80)
			ibss_htmode="80MHZ"
		;;
		NONE|NOHT)
			ibss_htmode="NOHT"
		;;
		*) ibss_htmode="" ;;
	esac

}

qcawificfg80211_setup_adhoc() {
	json_get_vars bssid ssid key mcast_rate

	keyspec=
	[ "$auth_type" = "wep" ] && {
		set_default key 1
		case "$key" in
			[1234])
				local idx
				for idx in 1 2 3 4; do
					json_get_var ikey "key$idx"

					[ -n "$ikey" ] && {
						ikey="$(($idx - 1)):$(prepare_key_wep "$ikey")"
						[ $idx -eq $key ] && ikey="d:$ikey"
						append keyspec "$ikey"
					}
				done
			;;
			*)
				append keyspec "d:0:$(prepare_key_wep "$key")"
			;;
		esac
	}

	brstr=
	for br in $basic_rate_list; do
		wpa_supplicant_add_rate brstr "$br"
	done

	mcval=
	[ -n "$mcast_rate" ] && wpa_supplicant_add_rate mcval "$mcast_rate"

	iw dev "$ifname" ibss join "$ssid" $freq $ibss_htmode fixed-freq $bssid \
		${beacon_int:+beacon-interval $beacon_int} \
		${brstr:+basic-rates $brstr} \
		${mcval:+mcast-rate $mcval} \
		${keyspec:+keys $keyspec}
}

qcawificfg80211_setup_vif() {
	local name="$1"
	local failed
	local is_2ghz=0

	json_select data
	json_get_vars ifname
	json_select ..

	json_select config
	json_get_vars mode wds uapsd powersave ubnt_role encryption \
		bs_ax_only bs_ax_deny bss_transition dyn_bw_rts_mode

	json_get_var vif_txpower txpower

	ip link set dev "$ifname" up || {
		wireless_setup_vif_failed IFUP_ERROR
		json_select ..
		return
	}

	set_default wds 0
	set_default vif_txpower "$txpower"
	set_default powersave 1				# Configures every power-save mode
	set_default uapsd 1					# Configures U-APSD power-save
	set_default bs_ax_only 0
	set_default bs_ax_deny 0
	set_default bss_transition 0
	set_default dyn_bw_rts_mode 0

	case "$hwmode" in
		*g) is_2ghz=1;;
	esac

	qcawificfg80211_interface_exists "$ifname" && {
		case "$mode" in
			ap) # WDS
				case "$wds" in
					1|on|enabled|true)	wds=1
						iw "$ifname" set 4addr on >/dev/null 2>&1
						;;
					*)	wds=0
						;;
				esac
				cfg80211tool "$ifname" wds "$wds" >/dev/null 2>&1

				# Band-steering
				cfg80211tool "$ifname" bs_ax_only_assoc "$bs_ax_only" >/dev/null 2>&1
				cfg80211tool "$ifname" bs_ax_deny_assoc "$bs_ax_deny" >/dev/null 2>&1

				# # Disable Dynamic bandwidth RTS - first bit, Automatic mode - is second one
				# cfg80211tool "$ifname" dyn_bw_rts "$dyn_bw_rts_mode" >/dev/null 2>&1

				case "$bss_transition" in
					1|on|enabled|true)
						bss_transition=1
					;;
					*)
						bss_transition=0
					;;
				esac

				# BSS Transition
				cfg80211tool "$ifname" wnm_bsstrans "$bss_transition" >/dev/null 2>&1
				cfg80211tool "$ifname" dbgLVL_high 0x40000 > /dev/null 2>&1
			;;
		esac

		[ -z "$vif_txpower" ] || iw dev "$ifname" set txpower fixed "${vif_txpower%%.*}00"

		case "$mode" in
			ap|mesh)
				case "$ubnt_role" in
					user|device_specific|internal*)
						cfg80211tool "$ifname" ap_bridge 0 >/dev/null 2>&1
					;;
					*)
						cfg80211tool "$ifname" ap_bridge 0 >/dev/null 2>&1
					;;
				esac
			;;
		esac

		# Read-out power-save settings
		case "$powersave" in
			1|on|enabled)
				powersave=1
			;;
			*)
				powersave=0
				uapsd=0
			;;
		esac

		# Set default inactivity time-out to 1 min for 5GHz and 5 min for 2.4GHz
		cfg80211tool "$ifname" inact 60 >/dev/null 2>&1
		[ $is_2ghz -gt 0 ] && cfg80211tool "$ifname" inact 300 >/dev/null 2>&1

		case "$ubnt_role" in
			internal*)
				# Enable backhaul option
				cfg80211tool "$ifname" backhaul 1 >/dev/null 2>&1

				# Set internal inactivity time-out to 5 minutes
				cfg80211tool "$ifname" inact 300 >/dev/null 2>&1

				# Override power-save settings for backhaul
				powersave=0
				uapsd=0
			;;
		esac

		# Enable RTS/CTS only for SW retries to get more stability
		cfg80211tool "$ifname" enablertscts 0x21 >/dev/null 2>&1

		# Configure legacy power-save
		if [ "$powersave" -ne "0" ]; then
			iw "$ifname" set power_save "on" >/dev/null 2>&1
		else
			iw "$ifname" set power_save "off" >/dev/null 2>&1
		fi

		# Configure U-APSD power-save
		cfg80211tool "$ifname" uapsd "$uapsd" >/dev/null 2>&1

		cfg80211tool "$ifname" chutil_enab 1 >/dev/null 2>&1
	}

	case "$mode" in
		mesh)
			# authsae or wpa_supplicant
			json_get_vars key
			if [ -n "$key" ]; then
				if [ -e "/lib/wifi/authsae.sh" ]; then
					. /lib/wifi/authsae.sh
					authsae_start_interface || failed=1
				else
					wireless_vif_parse_encryption
					qcawificfg80211_setup_supplicant || failed=1
				fi
			else
				json_get_vars mesh_id mcast_rate

				mcval=
				[ -n "$mcast_rate" ] && wpa_supplicant_add_rate mcval "$mcast_rate"

				iw dev "$ifname" mesh join "$mesh_id" ${mcval:+mcast-rate $mcval}
			fi

			for var in $MP_CONFIG_INT $MP_CONFIG_BOOL $MP_CONFIG_STRING; do
				json_get_var mp_val "$var"
				[ -n "$mp_val" ] && iw dev "$ifname" set mesh_param "$var" "$mp_val"
			done
		;;
		adhoc)
			wireless_vif_parse_encryption
			qcawificfg80211_setup_adhoc_htmode
			if [ "$wpa" -gt 0 -o "$auto_channel" -gt 0 ]; then
				qcawificfg80211_setup_supplicant || failed=1
			else
				qcawificfg80211_setup_adhoc
			fi
		;;
        sta)
			# FIXME: Downgrade encryption from SAE to PSK2 w/o changing the configuration
			case "$ubnt_role" in
				internal)
					if [ "$encryption" == "sae-mixed" ] ; then
						json_add_string encryption "psk2"
					fi
				;;
			esac

			# Configure STA related power-save settings
			cfg80211tool "$ifname" wnm_sleep "$powersave" >/dev/null 2>&1
			cfg80211tool "$ifname" powersave "$powersave" >/dev/null 2>&1

			qcawificfg80211_setup_supplicant || failed=1                                   
        ;; 
	esac

	json_select ..
	[ -n "$failed" ] || wireless_add_vif "$name" "$ifname"
}

qcawificfg80211_run_vif() {
	json_select data
	json_get_vars ifname
	json_select ..
	local iface=$ifname

	[ -f "/sys/class/net/$iface/parent" ] || {
		iw phy "$phy" interface add "$iface" type __ap
	}

	parent=`cat /sys/class/net/$iface/parent`

	/usr/sbin/hostapd -P /var/run/wifi-$phy.$iface.pid -B "/var/run/hostapd-$phy.$iface.conf"
	ret="$?"
	if [ -f "/var/run/wifi-$phy.$iface.pid" ]; then
		wireless_add_process "$(cat /var/run/wifi-$phy.$iface.pid)" "/usr/sbin/hostapd" 1
		[ "$ret" != 0 ] && {
			# UBNT: Do not reset setup retries to 0
			#wireless_setup_failed HOSTAPD_START_FAILED
			echo "Device setup failed: HOSTAPD_START_FAILED"
			return
		}
	else
		echo "Could not read PID file for device '$phy'"
		# UBNT: Do not reset setup retries to 0
		#wireless_setup_failed HOSTAPD_START_FAILED
		echo "Device setup failed: HOSTAPD_START_FAILED"
		return
	fi

}

qcawificfg80211_set_country() {
	radio=$1
	country=$2
	dfs_enabled=$3
	dfs_enabled=$((1-dfs_enabled))
	cfg80211tool "$radio" blockdfslist 0

	cfg80211tool "$radio" setCountry "$country" >/dev/null 2>&1
}

qcawificfg80211_setup_phy() {
	radio=$1

	local board_name
	local ecm_enabled

	# Setup per-radio parameters
	cfg80211tool "$radio" enable_ol_stats 1 >/dev/null 2>&1

	# Setup default sounding interval
	cfg80211tool "$radio" txbf_snd_int 100 >/dev/null 2>&1

	# Switch beacon to bursted mode instead of staggered
	cfg80211tool "$radio" set_bcnburst 1 >/dev/null 2>&1

	# Disable VLAN tagging in driver
	cfg80211tool "$radio" no_vlan 1 >/dev/null 2>&1

	# Check whether ECM module was loaded
	ecm_enabled=false
	[ ! -z "$(lsmod | grep -i 'ecm ')" ] && {
			ecm_enabled=true
	}

	# Query board name
	[ -f /tmp/sysinfo/board_name ] && {
		board_name=$(cat /tmp/sysinfo/board_name)
	}

	# Setup NSS ECM buffer sizes
	if [ "$ecm_enabled" = true ]; then
		echo "qca-wifi: ECM is enabled for ${board_name}" > /dev/console

		case "$board_name" in
			ubiquiti,afi-aln-*)
				if [ -f /sys/class/net/${radio}/nssoffload ] && [ $(cat /sys/class/net/${radio}/nssoffload) == "capable" ]; then
					local soc_version_major
					[ -f /sys/firmware/devicetree/base/soc_version_major ] && {
						soc_version_major="$(hexdump -n 1 -e '"%1d"' /sys/firmware/devicetree/base/soc_version_major)"
					}

					if [ $soc_version_major = 2 ]; then
						# Hawkeye 2.0 configuration
						case "$radio" in
							radio0|radio2)
								cfg80211tool "$radio" fc_buf0_max 32768 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf1_max 32768 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf2_max 32768 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf3_max 32768 >/dev/null 2>&1
							;;
							*)
								cfg80211tool "$radio" fc_buf0_max 16384 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf1_max 16384 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf2_max 16384 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf3_max 32768 >/dev/null 2>&1
							;;
						esac
					else
						# Hawkeye 1.2 configuration
						case "$radio" in
							radio0|radio2)
								cfg80211tool "$radio" fc_buf0_max 8192 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf1_max 12288 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf2_max 16384 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf3_max 16384 >/dev/null 2>&1
							;;
							*)
								cfg80211tool "$radio" fc_buf0_max 4096 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf1_max 8192 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf2_max 12288 >/dev/null 2>&1
								cfg80211tool "$radio" fc_buf3_max 16384 >/dev/null 2>&1
							;;
						esac
					fi
				fi
			;;
			*)
				cfg80211tool "$radio" fc_buf0_max 5120 >/dev/null 2>&1
				cfg80211tool "$radio" fc_buf1_max 8192 >/dev/null 2>&1
				cfg80211tool "$radio" fc_buf2_max 12288 >/dev/null 2>&1
				cfg80211tool "$radio" fc_buf3_max 16384 >/dev/null 2>&1
			;;
		esac

		sysctl -w dev.nss.n2hcfg.n2h_queue_limit_core0=256 >/dev/null 2>&1
		sysctl -w dev.nss.n2hcfg.n2h_queue_limit_core1=256 >/dev/null 2>&1		
	else
		echo "qca-wifi: ECM is disabled for ${board_name}" > /dev/console

		sysctl -w dev.nss.n2hcfg.n2h_queue_limit_core0=2048 >/dev/null 2>&1
		sysctl -w dev.nss.n2hcfg.n2h_queue_limit_core1=2048 >/dev/null 2>&1
	fi

	cfg80211tool "$radio" set_fw_recovery 1

	afi_radio_check_casdcade $radio || {
		cfg80211tool "$radio" he_bsscolor 0 0
	}
}

qcawificfg80211_run_phy() {
	# Run hostapd instance per each PHY
	/usr/sbin/hostapd -P /var/run/wifi-$phy.pid -B "$hostapd_conf_file" 
	ret="$?" 
	if [ -f "/var/run/wifi-$phy.pid" ]; then 
		wireless_add_process "$(cat /var/run/wifi-$phy.pid)" "/usr/sbin/hostapd" 1 
		[ "$ret" != 0 ] && { 
			# UBNT: Do not reset setup retries to 0 
			#wireless_setup_failed HOSTAPD_START_FAILED 
			echo "Device setup failed: HOSTAPD_START_FAILED" 
		}
	else 
		echo "Could not read PID file for device '$phy'" 
		# UBNT: Do not reset setup retries to 0 
		#wireless_setup_failed HOSTAPD_START_FAILED 
		echo "Device setup failed: HOSTAPD_START_FAILED" 
	fi
}

qcawificfg80211_setup_nss() {
	local board_name

	# Query board name
	[ -f /tmp/sysinfo/board_name ] && {
		board_name=$(cat /tmp/sysinfo/board_name)
	}

	case "$board_name" in
		ubiquiti,afi-aln-*)
			# Total pbuf size is 160 bytes, allocate memory for 77176 pbufs
			sysctl -w dev.nss.n2hcfg.extra_pbuf_core0=13000000 >/dev/null 2>&1
			sysctl -w dev.nss.n2hcfg.n2h_high_water_core0=88896 >/dev/null 2>&1
			# Initially after init 4k buf for 5G and 4k for 2G will be allocated, 
			# then range will be configured
			sysctl -w dev.nss.n2hcfg.n2h_wifi_pool_buf=53248 >/dev/null 2>&1
		;;
		*)
			# Total pbuf size is 160 bytes, allocate memory for 48456 pbufs
			sysctl -w dev.nss.n2hcfg.extra_pbuf_core0=7800000 >/dev/null 2>&1
			sysctl -w dev.nss.n2hcfg.n2h_high_water_core0=60176 >/dev/null 2>&1
			sysctl -w dev.nss.n2hcfg.n2h_wifi_pool_buf=35840 >/dev/null 2>&1
		;;
	esac
}

get_freq() {
	local phy="$1"
	local chan="$2"
	iw "$phy" info | grep -E -m1 "(\* ${chan:-....} MHz${chan:+|\\[$chan\\]})" | grep MHz | awk '{print $2}'
}

qcawificfg80211_interface_cleanup() {
	local phy="$1"

	for wdev in `ls -d /sys/class/net/* | grep -i wlan`; do
		phyname=`cat $wdev/phy80211/name`
		if [ "$phy" = "$phyname" ]; then
			local interface=`basename $wdev`
			ip link set dev "$interface" down 2>/dev/null
			iw "$interface" del
			echo "qca-wifi: clean-up wireless interface: "$interface"" >/dev/console
		fi
	done
}

drv_qcawificfg80211_cleanup() {
	echo "qca-wifi: cleanup" > /dev/console
}

drv_qcawificfg80211_setup() {
	json_select config
	json_get_values dyn_bw_rts_bl dyn_bw_rts_bl
	json_get_vars \
		phy macaddr path \
		country dfs_enabled chanbw distance \
		txpower antenna_gain \
		rxantenna txantenna \
		frag rts beacon_int htmode
	json_get_values basic_rate_list basic_rate
	json_select ..

	radio=$1

	find_phy "$radio" || {
		echo "Could not find PHY for device '$1'"
		wireless_set_retry 0
		return 1
	}

	echo "qca-wifi: setup ${phy}" > /dev/console	

	wireless_set_data phy="$phy"
	qcawificfg80211_interface_cleanup "$phy"

	# convert channel to frequency
	[ "$auto_channel" -gt 0 ] || freq="$(get_freq "$phy" "$channel")"

	[ -n "$country" ] && [ "$country" = "XZ" -o "$country" = "YY" ] && {
		country=DE
	}

	# Set default country code
	[ -n "$country" ] && {
		iw reg get | grep -q "^country $country:" || {
			iw reg set "$country"
			sleep 1
		}
	}

	# UBNT: disable 40 MHz bandwidth in 2.4 GHz band
	# because of multiple interoperability issues.
	# See APT-555, APT-546 for details.
	if [ "${hwmode}" = "g" -a "${htmode}" = "HE40" ]
	then
		htmode="HE20"
	fi

	# random channel selection (1 - only from nonDFS channel list)
	cfg80211tool "$radio" CSwOpts 0x00000001 >/dev/null 2>&1

	# # adjust Hawkeye carrier sense threshold
	# case "$radio" in
	# 	radio0|radio2)
	# 		cfg80211tool radio0 setSenseLVL -75
	# 		;;
	# 	radio1)
	# 		cfg80211tool radio1 setSenseLVL -70
	# 		;;
	# esac

	echo 1 > /sys/module/tz_log/parameters/paniconaccessviolation

	qcawificfg80211_setup_nss

	hostapd_conf_file="/var/run/hostapd-$phy.conf"
	no_ap=1
	macidx=0
	staidx=0

	[ -n "$chanbw" ] && {
		for file in /sys/kernel/debug/ieee80211/$phy/ath9k/chanbw /sys/kernel/debug/ieee80211/$phy/ath5k/bwmode; do
			[ -f "$file" ] && echo "$chanbw" > "$file"
		done
	}

	set_default rxantenna all
	set_default txantenna all
	set_default distance 0
	set_default antenna_gain 0

	iw phy "$phy" set antenna $txantenna $rxantenna >/dev/null 2>&1
	iw phy "$phy" set antenna_gain $antenna_gain
	iw phy "$phy" set distance "$distance"

	[ -n "$frag" ] && iw phy "$phy" set frag "${frag%%.*}"
	[ -n "$rts" ] && iw phy "$phy" set rts "${rts%%.*}"

	has_ap=
	hostapd_ctrl=
	for_each_interface "ap" qcawificfg80211_check_ap

	rm -f "$hostapd_conf_file"
	[ -n "$has_ap" ] && qcawificfg80211_hostapd_setup_base "$phy" "$dfs_enabled"

	qcawificfg80211_set_country "$radio" "$country" "$dfs_enabled"

	for_each_interface "sta adhoc mesh monitor" qcawificfg80211_prepare_vif
	for_each_interface "ap" qcawificfg80211_prepare_vif

	qcawificfg80211_setup_phy "$radio"

	if [ "$HOSTAPD_BSS_SUPPORT" -ne "0" ]; then
		qcawificfg80211_run_phy $1
	else
		for_each_interface "ap" qcawificfg80211_run_vif
	fi

	for_each_interface "sta ap adhoc mesh monitor" qcawificfg80211_setup_vif

	# Put radioX up after all interface operations
	ifconfig "$radio" up >/dev/null 2>&1

	wireless_set_up
	( ubus -t 120 wait_for network.initialized && sleep 10 && /etc/init.d/scored restart ) &
}

list_phy_interfaces() {
	local phy="$1"
	if [ -d "/sys/class/ieee80211/${phy}/device/net" ]; then
		ls "/sys/class/ieee80211/${phy}/device/net" 2>/dev/null;
	else
		ls "/sys/class/ieee80211/${phy}/device" 2>/dev/null | grep net: | sed -e 's,net:,,g'
	fi
}

drv_qcawificfg80211_teardown() {
    wireless_process_kill_all

    json_select data
    json_get_vars phy
    json_select ..

    echo "qca-wifi: teardown ${phy}" > /dev/console

    # Tear-down hostapd PHY instance
    kill -9 $(pgrep -f ".*hostapd.*${phy}.*")

	# Iterate over all interfaces of the PHY
    for wdev in `ls -d /sys/class/net/* | grep -i wlan`; do
        phyname=`cat $wdev/phy80211/name`
        if [ "$phy" = "$phyname" ]; then
            local interface=`basename $wdev`
            ip link set dev "$interface" down 2>/dev/null

            # Tear-down wpa_supplicant interface instance
            kill -9 $(pgrep -f ".*wpa_supplicant.*${interface}.*")

            iw "$interface" del
            echo "qca-wifi: delete wireless interface ${interface}" >/dev/console
        fi
    done
}

add_driver qcawificfg80211
