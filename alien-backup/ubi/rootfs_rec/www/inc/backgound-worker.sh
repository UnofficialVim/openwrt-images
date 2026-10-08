. vars
. functions


command="$1"

if [ "$command" = "uh-fw-tool_list" ]; then
	uh-fw-tool -l > /tmp/webui.rollbackwait && mv /tmp/webui.rollbackwait /tmp/webui.rollbacklist &
fi

if [ "$command" = "uh-fw-tool_switch" ]; then
	uh-fw-tool -o && sleep 1 && reboot -f &
fi

if [ "$command" = "config_teleport_wired" ]; then
	log_debug "background-worker.sh config_teleport_wired \"$2\" \"***\""
	config_teleport_wired "$2" "$3"
fi

if [ "$command" = "config_teleport_wireless" ]; then
	log_debug "background-worker.sh config_teleport_wireless \"$2\" \"***\" \"$4\" \"$5\" \"$6\" \"***\""
	config_teleport_wireless "$2" "$3" "$4" "$5" "$6" "$7"
fi

if [ "$command" = "check_password" ]; then
	freq="$2"
	ssid="$3"
	wifipassword="$4"
	open="$5"
	local_ssid="$6"
	local_wifipassword="$7"
	unicode_ssid="$8"
	unicode_password="$9"

	echo "$ssid" > "$WIFIPASSWORDCHECKLOCK"

	log_debug "background-worker.sh check_password \"$freq\" \"$ssid\" \"***\""



	if [ "$open" = "1" ]; then
		log_debug "background-worker.sh open network"
		pwderr=0

	elif [ "$freq" = "2400" -o "$freq" = "5000" ]; then
		log_debug "background-worker.sh frequency unknown"
		pwderr=0

	else
		ch="$(freq_to_channel $freq)"
		cur_ch=$(iw wlan0 info | grep channel | cut -d" " -f2)

		if [ "$cur_ch" != "$ch" ]; then
			log_debug "background-worker.sh check_password switch_channel $ch"
			usleep 250000 # give time for browser to receive an answer before Teleport dissapears
			switch_channel $ch
			sleep 4

			for i in `seq 1 10`; do
				sleep 1
				cur_ch=$(iw wlan0 info | grep channel | cut -d" " -f2)
				if [ "$cur_ch" ]; then
					break
				fi
			done
		fi

		if [ "$cur_ch" = "$ch" ]; then
			check_passwd.sh "$freq" "$ssid" "$wifipassword"
			pwderr=$?
			log_debug "background-worker.sh check_passwd.sh result: $pwderr"

		else
			pwderr=0 # workaround
			log_debug "switch_channel failed, assuming password is correct"

		fi

	fi


	if [ "$pwderr" = "0" ]; then
		log_debug "background-worker.sh config_teleport_wireless"

		[ "$unicode_ssid" ] && ssid="$unicode_ssid"
		[ "$unicode_password" ] && wifipassword="$unicode_password"

		config_teleport_wireless "$ssid" "$wifipassword" "$freq" "$open" "$local_ssid" "$local_wifipassword"
		rm -f "$SETUPSSID"
		sleep 7 # give 7 seconds for wifi setup

	else # wrong password
		if [ "$local_ssid" -a "$local_wifipassword" ]; then
			log_debug "background-worker.sh config_teleport_ap"
			config_teleport_ap "$local_ssid" "$local_wifipassword"
			sleep 5 # give 5 seconds for wifi setup
		fi
		echo "$ssid" > "$WIFIPASSWORDCHECKRESULT"

	fi
	rm "$WIFIPASSWORDCHECKLOCK"

fi

if [ "$command" = "skip_confirmation" ]; then
	ubus call ajconfd.Device SetDeviceConfig '{"config": { "MlSkipSetupConfirmation": true }}' >/dev/null
fi

if [ "$command" = "status" ]; then
	teleport_state
	cat /tmp/webui.debug.state
fi

if [ "$command" = "switch_channel" ]; then
	switch_channel "$2"
fi
