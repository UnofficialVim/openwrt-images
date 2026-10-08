#!/bin/sh

. ../inc/vars
. functions

echo "Content-type: application/json"
echo "Cache-Control: no-cache, no-store, must-revalidate"
echo "Pragma: no-cache"
echo "Expires: 0"
echo ""

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING
	d=$(getvar "$QUERY_STRING" "do")
	skip=$(getvar "$QUERY_STRING" "skip")
	teleport_status_id_prev=$(getvar "$QUERY_STRING" "status")
	seconds_elapsed=$(getvar "$QUERY_STRING" "elapsed")
fi

if [ "$(get_ptype)" = "Router" -a "$d" = "unpair" ]; then
	asd="skip"
elif [ ! "$(is_teleport)" ]; then
	sleep 2 # delay is needed because teleport IP can change after it exits isolation mode, but ajconfd may not be synced yet
	teleport_ip=$(get_teleport_ip)
	if [ "$teleport_ip" ]; then
		echo '{'\
		'"step":5,' \
		'"state":1,' \
		'"status":601,' \
		'"message":"'"$teleport_ip"'"' \
		'}';
	else
		echo '{'\
		'"step":5,' \
		'"state":1,' \
		'"status":600,' \
		'"message":"Everything is great!"' \
		'}';
	fi
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then

	if [ "$d" = "2" ] && [ "$(is_teleport)" ]; then # exit isolation mode
		if [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" = "Full" ]; then
			ubus call ajconfd.Device SetDeviceConfig '{"config": { "MlIsolationMode": "Unknown" }}' >/dev/null
		fi

	elif [ "$d" = "14" ] && [ "$(is_teleport)" ] && [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" ]; then # retry declined pairing
		ubus call ajconfd.MagicLink MlRetryAdoption >/dev/null

	elif [ "$d" = "unpair" ]; then
		if [ "$(is_teleport)" ] && [ "$(ubus call ajconfd.Device GetPairing | grep -q 'router'; echo $?)" = "0" ] && [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" ]; then # if is paired Teleport in isolation mode
			mac="$(cat /proc/ubnthal/board | grep -m1 macaddr | cut -d'=' -f2)"
		elif [ "$(get_ptype)" = "Router" ]; then
			if [ "$SESSION" = "invalid" ]; then
				echo 1
				exit
			fi

			post_token=$(getvar "$QUERY_STRING" "token")
			if [ "$post_token" != "$TOKEN" ]; then
				log_debug "ml-async.php bad token"
				echo 1
				exit
			fi

			mac=$(getvar "$QUERY_STRING" "m")
			if [ "$(validate_mac_addr "$mac")" != "valid" ]; then
				echo 1
				exit
			fi
		else
			echo 1
			exit
		fi

		log_debug "ml-async.php RemovePairedExtenders $mac"
		result=`ubus call ajconfd.ClusterNode RemovePairedExtenders '{"macs":["'"$mac"'"]}'`
		if [ "$(echo "$result" | grep "$mac")" ]; then
			echo 0
		else
			echo 1
		fi

		exit

	elif [ "$d" = "configureTeleportAp" ] && [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" ]; then
		local_ssid=$(getvar "$QUERY_STRING" "ssid")
		local_wifipassword=$(getvar "$QUERY_STRING" "wifi_password")
		local_ssid_unicode=$(getvar "$QUERY_STRING" "uss")
		echo "$local_ssid" > "$TELEPORTAP"
		echo "$local_wifipassword" >> "$TELEPORTAP"
		echo "$local_ssid_unicode" >> "$TELEPORTAP"
		log_debug "ml-async.php configureTeleportAp \"$local_ssid\" \"***\" \"$local_ssid_unicode\""
	fi

fi

if [ ! "$d" ]; then
	[ ! "$seconds_elapsed" ] && seconds_elapsed=0
	teleport_state "$seconds_elapsed"
	if [ "$teleport_status_id_prev" ]; then
		start=$(date +%s)
		while true; do
			if [ "$teleport_status_id" = "$teleport_status_id_prev" ]; then
				now=$(date +%s)
				if [ "$(($now - $start))" -gt 8 ]; then # wait 8 seconds for new info, then output what we have
					break
				fi
				seconds_elapsed_new=$(($now - $start + $seconds_elapsed))
				teleport_state "$seconds_elapsed_new"
			else
				break
			fi
			usleep 500000
		done
	fi
	debug="$(cat /tmp/webui.debug.state | tr '\n' ', ')"

	fwwupdate_channel=$(uci get fwupdate.@fwupdate[0].channel 2>/dev/null)
	echo -n '{'
	[ "$fwwupdate_channel" -a "$fwwupdate_channel" != "stable" ] && echo -n '"debug":"'"$debug"'",'
	[ "$(check_update)" = "1" ] && echo -n '"update":1,'
	echo '"step":'"$teleport_setup_step"',' \
	'"state":'"$teleport_setup_state"',' \
	'"status":'"$teleport_status_id"',' \
	'"message":"'"$teleport_status_msg"'"' \
	'}';
fi
