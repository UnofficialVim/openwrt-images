#!/bin/sh

. ../inc/vars
. functions

if [ "$SESSION" = "invalid" ]; then
	set_returnurl "info.php"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

esc_dq() {
	local txt="$1"
	echo "$1" | sed -r 's/"/\\"/g' | head -n1
}

echo "Content-type: application/json"
echo "Cache-Control: no-cache, no-store, must-revalidate"
echo "Pragma: no-cache"
echo "Expires: 0"
echo ""

if [ "$SESSION" = "invalid" ]; then
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING
	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "info-async.php bad token"
		. head-start
		. html-start
		. head-start
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	d=$(getvar "$QUERY_STRING" "do")
	if   [ "$d" = "full" ]; then

		if [ "$(ubus call ajconfd.Device GetEthernetPortInfoPollConfig | jsonfilter -e '$["mode"]')" != "On" ]; then
			ubus call ajconfd.ClusterNode ModifyEthernetPortInfoPollConfig '{"config": {"mode": "On", "interval": 2000}}' >/dev/null
		fi

		GetFingerprint="$(ubus call ajconfd.ClusterNode GetFingerprint)"
		[ ! "$GetFingerprint" ] && GetFingerprint="{}"

		r="["
		r="$r""$(ubus call ajconfd.ClusterNode GetClusterNodeTopology)"","
		r="$r""$(ubus call ajconfd.ClusterNode GetWifiClientInfo 2>/dev/null)"","
		r="$r""$(ubus call ajconfd.ClusterNode GetClientInfo 2>/dev/null)"","
		r="$r""$(ubus call ajconfd GetClusterEthernetClients 2>/dev/null)"","
		r="$r""$(ubus call ajconfd.ClusterNode GetEthernetPortInfo 2>/dev/null)"","
		#r="$r""$(ubus call ajconfd.ClusterNode GetClientCustomInfo 2>/dev/null)"","
		r="$r""$GetFingerprint""]"
		r="$(echo "$r" | sed 's/\t//g' | tr -d '\n')"
		echo "$r"
		# ubus call ajconfd.ClusterNode GetPeerList 2>/dev/null
		# ubus call ajconfd.ClusterNode GetRegisteredNodeList 2>/dev/null

	elif [ "$d" = "GetWifiClientInfo" ]; then
		ubus call ajconfd.ClusterNode GetWifiClientInfo 2>/dev/null

	elif [ "$d" = "conntrack" ]; then
		cat /proc/net/nf_conntrack 2>/dev/null

	elif [ "$d" = "qosstat" ]; then
		otc=$(tc -s qdisc show);
		otci=$(echo "$otc" | grep -E 'qdisc' | cut -d' ' -f5 | tr '\n' ';' | sed 's/.$//')
		otcn=$(echo "$otc" | grep qdisc | cut -d' ' -f2,3,6,7 | tr -d ':' | tr '\n' ';' | sed 's/.$//')
		otcs=$(echo "$otc" | grep Sent | sed -e 's/[^0-9]/ /g' -e 's/^ *//g' -e 's/ *$//g' | tr -s ' ' | tr ' ' ',' | tr '\n' ';' | sed 's/.$//')
		echo "tci $otci"
		echo "tcn $otcn"
		echo "tcs $otcs"
		o="$(cat /proc/uptime | cut -d' ' -f1 | tr -d .)"; o=$((o*10)); echo "ts $o"
		o="$(uci get qos.Default.classes 2>/dev/null | tr ' ' ';')"; echo "classes $o"

	elif [ "$d" = "devicelist" ]; then
		if [ ! -f "/tmp/www-extra/devicelist.json" ] || [ "$(date -r "/tmp/www-extra/devicelist.json" +%s)" -le "$(( $(date +%s) - 86400 ))" ]; then
			mkdir -p '/tmp/www-extra/'
			curl -s 'https://static.ubnt.com/fingerprint/0/devicelist.json' --output /tmp/www-extra/devicelist.json
		fi
		cat '/tmp/www-extra/devicelist.json'

	elif [ "$d" = "utilization" ]; then
		echo -n '{"memory":"'"$((100-100*$(cat /proc/meminfo | grep MemAvailable | awk '{print $2}')/$(cat /proc/meminfo | grep MemTotal | awk '{print $2}')))"'",'
		echo -n '"cpu":"'"$((100-$(top -b -n1 | grep CPU | grep idle | awk '{print $8}' | cut -d'%' -f1)))"'"}'

	elif [ "$d" = "saveicon" ]; then
		mac=$(getvar "$QUERY_STRING" "mac"); mac=$(esc_dq "$mac")
		if [ "$(validate_mac_addr "${mac}")" != "valid" ]; then
			echo '{"error": "invalid MAC address: '"$mac"'"}'
			exit
		fi
		icon_id=$(getvar "$QUERY_STRING" "icon_id"); icon_id=$(esc_dq "$icon_id")
		ubus call ajconfd.ClusterNode SetClientCustomInfo '{"'"${mac}"'": {"fingerprint": {"device_id": '"${icon_id}"'}}}' &>/dev/null
		echo '{"result": "OK"}'

	elif [ "$d" = "renamedevice" ]; then
		mac=$(getvar "$QUERY_STRING" "mac"); mac=$(esc_dq "$mac")
		if [ "$(validate_mac_addr "${mac}")" != "valid" ]; then
			echo '{"error": "invalid MAC address: '"$mac"'"}'
			exit
		fi
		devicesName=$(getvar "$QUERY_STRING" "name"); devicesName=$(esc_dq "$devicesName"); devicesName=$(echo "$devicesName" | cut -c -1023)
		json="$(ubus call ajconfd.ClusterNode GetClientInfo 2>/dev/null)"
		json_load "$json" > /dev/null
		json_select "$mac" > /dev/null
		json_get_var description "description"
		if [ "$description" ]; then
			ubus call ajconfd.Device ModifyScheduledAccessControlDevices '{"devices": {"'"$mac"'": {"name": "'"$devicesName"'"} } }' >/dev/null
		else
			ubus call ajconfd.Device AddScheduledAccessControlDevices '{"devices": {"'"$mac"'": {"name": "'"$devicesName"'"} } }' >/dev/null
		fi
		echo '{"result": "OK"}'

	elif [ "$d" = "savestaticlease" ]; then
		mac=$(getvar "$QUERY_STRING" "mac"); mac=$(esc_dq "$mac")
		if [ "$(validate_mac_addr "${mac}")" != "valid" ]; then
			echo '{"error": "invalid MAC address: '"$mac"'"}'
			exit
		fi
		ipaddr=$(getvar "$QUERY_STRING" "ipaddr"); ipaddr=$(esc_dq "$ipaddr")
		if [ "$(ubus call ajconfd.Device GetDhcpStaticLeases |  grep -q '"Address": "'"${ipaddr}"'"'; echo $?)" = "0" ]; then
			if [ "$(ubus call ajconfd.Device GetDhcpStaticLeases | grep -B2 '"Address": "'"${ipaddr}"'"' | grep -q '"'"${mac}"'"'; echo $?)" = "1" ]; then
				echo '{"result": "fail", "error": "IP address already reserved for other device"}'
				exit
			fi
		fi
		lastoctet="$(echo "$ipaddr" | cut -d'.' -f4)"
		if [ "$lastoctet" -eq "$lastoctet" ] 2>/dev/null; then # if number
			if [ "$lastoctet" -lt 2 -o "$lastoctet" -gt 254 ]; then
				echo '{"result": "fail", "error": "Incorrect IP address"}'
				exit
			fi
		else
				echo '{"result": "fail", "error": "Incorrect IP address"}'
				exit
		fi
		ubus call ajconfd.Device DeferCommits >/dev/null
		if [ "$(ubus call ajconfd.Device GetDhcpStaticLeases |  grep -q "${mac}"; echo $?)" = "0" ]; then
			ubus call ajconfd.Device DeleteDhcpStaticLeases '{"leases": [ "'"${mac}"'" ]}' >/dev/null
		fi
		ubus call ajconfd.Device AddDhcpStaticLeases '{"leases": {"'"${mac}"'": {"Address": "'"${ipaddr}"'", "Mac": "'"${mac}"'" }}}' >/dev/null
		echo '{"result": "OK", "mac" : "'"${mac}"'", "ipaddr" : "'"${ipaddr}"'"}'
		ubus call ajconfd.Device CommitChanges >/dev/null

	fi
fi
