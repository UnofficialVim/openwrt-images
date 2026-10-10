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

if [ "$command" = "send_debug_info" ]; then
	rm -f "/tmp/webui.debugsendmessage"
	mac=$(cat /proc/ubnthal/system.info | grep serialno | cut -d'=' -f2)
	debugsendresult="$(curl -s http://riga.corp.ubnt.com/logsend218/?m=$mac)"
	if [ "$(echo "$debugsendresult" | head -n1)" != 'OK' ]; then
		[ ! "$debugsendresult" ] && debugsendresult="Server not ready."
		message="$(echo "$debugsendresult" | tail -n1)"
		echo "$message" > "/tmp/webui.debugsendmessage"
		log_debug 'background-worker.sh: Stop. Logsend result is not OK. Message: '"$message"
		sleep 1
		p=$(cat /tmp/webui.pid.*); rm -f /tmp/webui.pid.*; echo "$p" | xargs kill -9
		exit
	fi

	echo '# START ------------------------------------------------' >> /tmp/logfile.txt;

	cat /etc/openwrt_release | awk '{print "# openwrt_release " $0}' >> /tmp/logfile.txt
	cat /proc/ubnthal/system.info | awk '{print "# ubnthal " $0}' >> /tmp/logfile.txt
	dmesg | awk '{print "# dmesg " $0}' >> /tmp/logfile.txt

	logread -f | awk '{print "# logread " $0}' >> /tmp/logfile.txt &

	logread_PID=$!
	echo "$logread_PID" > "/tmp/webui.pid.logread"

	cat "/proc/ubnthal/board" | grep -q "Alien"
	[ $? = 0 ] && alien=1
	while true; do
		echo '# SPLIT ------------------------------------------------';
		date +%s | awk '{print "# timestamp " $0}';
		date | awk '{print "# date " $0}';
		uptime | awk '{print "# uptime " $0}';
		cat /proc/meminfo | awk '{print "# meminfo " $0}';
		df -h | awk '{print "# df " $0}';
		ls -la /tmp | awk '{print "# ls " $0}';
		top -bn1 | awk '{print "# top " $0}';
		ps ww | awk '{print "# ps " $0}';
		[ "$alien" ] && /sbin/ip -s link | awk '{print "# iplink " $0}' || /usr/bin/ip -s link | awk '{print "# iplink " $0}';
		for interface in `iw dev | grep Interface | cut -f 2 -s -d" "`; do iw dev $interface station dump; done | awk '{print "# station_dump " $0}';
		cat /proc/net/nf_conntrack | grep tcp | awk {'print $6'} | sort | uniq -c | awk '{print "# conntrack tcp" $0}'; cat /proc/net/nf_conntrack | grep udp | grep -v UNR | wc -l | awk '{print "# conntrack udp replied " $0}'; cat /proc/net/nf_conntrack | grep udp | grep UNR | wc -l | awk '{print "# conntrack udp unreplied " $0}'; cat /proc/net/nf_conntrack | grep -vE 'udp|tcp' | awk {'print $3'} | sort | uniq -c | awk '{print "# conntrack other" $0}'
		tc -s qdisc show | awk '{print "# tc " $0}';
		[ -f /proc/slabinfo ] && cat /proc/slabinfo | awk '{print "# slabinfo " $0}';

		sleep 60;
	done >> /tmp/logfile.txt &
	loop_PID=$!
	echo "$loop_PID" > "/tmp/webui.pid.loop"

	trap 'p=$(cat /tmp/webui.pid.*); rm -f /tmp/logfile*; rm -f /tmp/webui.pid.*; echo "$p" | xargs kill -9;' SIGINT SIGTERM EXIT

	sleep 10

	while true; do
		[ $(cat /proc/meminfo | grep MemAvailable | awk {'print $2'}) -lt 3000 ] && log_debug "background-worker.sh: send_debug_info terminated on low available memory." && exit
		[ ! -f "/tmp/logfile.txt" ] && log_debug "background-worker.sh: no logfile found, terminated." && exit
		[ $(du -k "/tmp/logfile.txt" | cut -f1) -gt 10000 ] && log_debug "background-worker.sh: logfile size > 10000 Kb, terminated." && exit

		[ ! -f "/tmp/logfile-send.txt.gz" ] && cp /tmp/logfile.txt /tmp/logfile-send.txt && true > /tmp/logfile.txt && gzip /tmp/logfile-send.txt

		debugsendresult=$(curl -s -F "data=@/tmp/logfile-send.txt.gz" http://riga.corp.ubnt.com/logsend218/?m=$mac)
		err=$?
		if [ "$err" -eq 0 ]; then
			if [ "$(echo "$debugsendresult" | head -n1)" = 'OK' ]; then
				rm -f "/tmp/logfile-send.txt.gz"
			fi
			if [ "$(echo "$debugsendresult" | head -n1)" = 'NOK' ]; then
				message="$(echo "$debugsendresult" | tail -n1)"
				echo "$message" > "/tmp/webui.debugsendmessage"
				log_debug 'background-worker.sh: Stop. Result is NOK. Message: '"$message"
				echo "# STOPPED by server: $message" | gzip > "/tmp/logfile-send.txt.gz"
				curl -s -F "data=@/tmp/logfile-send.txt.gz" http://riga.corp.ubnt.com/logsend218/?m=$mac >/dev/null
				rm -f "/tmp/logfile-send.txt.gz"
				exit
			fi
		else
			log_debug 'background-worker.sh: upload FAILED, error '"$err"
		fi
		sleep 30
	done
fi

if [ "$command" = "send_trace" ]; then
	trace_type="$2"

	[ "$trace_type" != "setup_start" -a "$trace_type" != "setup_complete" ]	&& exit
	[ "$trace_type" = "setup_start" ] && [ -s "/tmp/webui.trace_start_time" ] && exit

	if [ -s "/tmp/webui.trace_setup_id" ]; then
		trace_setup_id=$(cat "/tmp/webui.trace_setup_id")
	else
		trace_setup_id=$(cat "/proc/sys/kernel/random/uuid")
		echo "$trace_setup_id" > "/tmp/webui.trace_setup_id"
	fi

	trace_time=$(date +%s)
	trace_agent=$(echo "$HTTP_USER_AGENT" | tr -d '"' | head -n1)
	trace_model="$SHORTNAME"
	trace_systemid=$(cat /proc/ubnthal/system.info | grep -E "^systemid" | cut -d'=' -f2)
	trace_board_rev=$(cat /proc/ubnthal/system.info | grep -E "^boardrevision" | cut -d'=' -f2)

	json=$(ubus call ajconfd.Device GetProperty '{"name":"Uuid"}')
	json_load "$json"
	json_get_var trace_setup_device_id "value"
	json_cleanup

	trace_version=$(cat /etc/openwrt_release | grep -m1 DISTRIB_RELEASE | cut -d'=' -f2 | tr -d "'")
	trace_revision=$(cat /etc/openwrt_release | grep -m1 DISTRIB_REVISION | cut -d'=' -f2 | tr -d "'")

	uptime_seconds=$(cat /proc/uptime | cut -d' ' -f1 | cut -d'.' -f1)

	if [ "$trace_type" = "setup_start" ]; then
		trace_payload_extra='\"start_time\": '${trace_time}
		cat /proc/uptime | cut -d' ' -f1 | cut -d'.' -f1
		echo "$uptime_seconds" > "/tmp/webui.trace_start_time"
	else
		trace_start_time=$(cat "/tmp/webui.trace_start_time")
		[ "$trace_start_time" ] && trace_duration=$((uptime_seconds-trace_start_time)) || trace_duration=0
		trace_payload_extra='\"end_time\": '${trace_time}', \"duration\": '${trace_duration}
	fi

	json='{
    "meta": {
        "meta": {
            "browser.ua": "'${trace_agent}'",
            "anonymous_device_id": "'${trace_setup_device_id}'",
            "model": "'${trace_model}'",
            "systemid": "'${trace_systemid}'",
            "board_rev": "'${trace_board_rev}'",
            "version": "'${trace_version}'",
            "revision": "'${trace_revision}'"
        }
    },
    "traces": [
        {
            "namespace": "amplifi:firmware:setup",
            "type": "'${trace_type}'",
            "payload": {
                "contentType": "application/json",
                "data": "{\"setup_device_id\":\"'${trace_setup_device_id}'\",\"setup_id\":\"'${trace_setup_id}'\",\"setup_step\":\"'${trace_type}'\",'${trace_payload_extra}'}",
                "encoding": "UTF-8"
            },
            "timestamp": '${trace_time}'000
        }
    ]
}'

	r=$(head -n3 /dev/urandom | md5sum | cut -c1-4)
	f="/tmp/webui.trace_send.${trace_type}.${r}"
	echo "${json}" > "${f}"

	[ -f "/tmp/webui.trace.lock" ] && exit
	echo 1 > "/tmp/webui.trace.lock"

	while true; do
		list=$(ls -ltr /tmp/webui.trace_send.* | grep -v result | awk '{print $9}')  2> /dev/null
		if [ ! "$list" ]; then
			rm -f "/tmp/webui.trace.lock"
			exit
		fi

		echo "$list" | while read -r f; do
			tracesend=$(curl -s -i -X PUT -H "Content-Type: application/json" --connect-timeout 15 -T "${f}" "https://trace.svc.ui.com/traces")
			err=$?
			if [ "$err" -eq 0 ]; then
				log_debug "background-worker.sh: ${f} sent"
				mv "${f}" "${f}.result"
				echo "$tracesend" >> "${f}.result"
				rm -f "${f}"
			else
				log_debug "background-worker.sh: ${f} send error: ${err}. Retry in 15 seconds."
			fi
		done
		sleep 15
	done

fi

if [ "$command" = "delay_set_wifi_country" ]; then # use when country not known during initial setup
	log_debug "background-worker.sh delay_set_wifi_country"
	i=0
	sleep 15
	/etc/init.d/icheckd restart
	sleep 5
	while [ $i -lt 180 ]; do
		isp_country="$(get_isp_country)"

		if [ "$isp_country" ]; then
			set_country="$(validate_wifi_country $isp_country)"
			json="$(ubus call ajconfd.ClusterNode GetSiteConfig 2>/dev/null)"
			json_load "$json" > /dev/null
			json_select "value" > /dev/null
			json_select 2 > /dev/null
			json_get_var wifi_country "Country"
			json_cleanup

			if [ "$wifi_country" != "$set_country" ]; then
				timestamp="$(date +%s)000000"
				log_debug "background-worker.sh delay_set_wifi_country: set wifi country to ${set_country}"
				ubus call ajconfd.ClusterNode SetSiteConfig '{"config": [ {"LastUpdateTimestamp": "'${timestamp}'"}, {"Country": "'"${set_country}"'"} ] }' >> $UBUSCALLLOG
			else
				log_debug "background-worker.sh delay_set_wifi_country: already set to ${set_country}"
			fi
			exit
		fi
		sleep 20
		i=$((i+1))
	done
	log_debug "background-worker.sh delay_set_wifi_country: timed out, giving up."
fi
