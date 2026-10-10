#!/bin/sh

. ../inc/vars
. functions

echo "Content-type: text/html"
echo "Cache-Control: no-cache, no-store, must-revalidate"
echo "Pragma: no-cache"
echo "Expires: 0"
echo ""

rm -rf $SELECTED_SSID

if [ "$(is_teleport)" ] && [ "$(ubus call ajconfd.Device GetPairing | grep -q 'router'; echo $?)" = "0" ] && [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" ]; then
	echo 'continue' > /dev/null
elif [ "$(get_setup_done)" != "1" ]; then
	echo 'continue' > /dev/null
else
	echo "1"
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING
fi

if [ -z "$QUERY_STRING" ]; then
	echo "1"
	exit
fi

pwderr=0
freq=$(getvar "$QUERY_STRING" "freq")
freqdual=$(getvar "$QUERY_STRING" "freqdual")
ssid=$(getvar "$QUERY_STRING" "ssid")
open=$(getvar "$QUERY_STRING" "open")
wifipassword=$(getvar "$QUERY_STRING" "wifipassword")
unicode_ssid=$(getvar "$QUERY_STRING" "uss")
unicode_password=$(getvar "$QUERY_STRING" "uwp")

if [ "$(is_teleport)" ]; then

	# if background password check has result, return it
	if [ -s "$WIFIPASSWORDCHECKRESULT" ]; then
		cat "$WIFIPASSWORDCHECKRESULT"
		rm "$WIFIPASSWORDCHECKRESULT"
		exit
	fi

	# if background password check in progress
	if [ -s "$WIFIPASSWORDCHECKLOCK" ] && [ "$(date -r "$WIFIPASSWORDCHECKLOCK" +%s)" -ge "$(( $(date +%s) - 30 ))" ]; then
		echo "12" # return code 12, background password check in progress (and if ok autoconnect to ssid)
		exit
	fi

	ch=$(freq_to_channel "$freq")
	cur_ch=$(iw wlan0 info | grep channel | cut -d" " -f2)
	log_debug "cp.php cur_ch:$cur_ch ch:$ch"

	if [ "$cur_ch" != "$ch" -a "$open" != "1" ]; then # if Teleport and upstream AP wireless channels differ
		pr=$(getvar "$QUERY_STRING" "pr")

		if [ "$freq" = "2400" ]; then
			if [ "$cur_ch" -gt 14 ]; then
				log_debug "cp.php precise frequency unknown"
				bg_check=1
			fi

		elif [ "$freq" = "5000" ]; then
			if [ "$cur_ch" -lt 36 ]; then
				log_debug "cp.php precise frequency unknown"
				bg_check=1
			fi

		elif [ "$pr" = "true" ]; then # if password reveal function used
			log_debug "cp.php password reveal was used"
			bg_check=1

		elif [ "$cur_ch" -le 15 -a "$ch" -ge 15 ] || [ "$cur_ch" -ge 15 -a "$ch" -le 15 ]; then # if bands differ, password can not be checked without restart

			# if dual band SSID
			if [ "$freqdual" ]; then
				password_check_freq="$freqdual"
			else
				log_debug "cp.php band differs and not dual band"
				bg_check=1
			fi

		elif [ -s "$WIFIPASSWORDCHECKFAIL" ]; then # if previous password check failed
			prev_ssid=$(sed -n 1p "$WIFIPASSWORDCHECKFAIL")
			prev_wifipassword=$(sed -n 2p "$WIFIPASSWORDCHECKFAIL")
			rm "$WIFIPASSWORDCHECKFAIL"

			if [ "$ssid" = "$prev_ssid" -a "$wifipassword" = "$prev_wifipassword" ]; then # if the same ssid and password was tried previously
				bg_check=1
				log_debug "cp.php same password failed previously"
			fi
		fi

		if [ "$bg_check" ]; then
			echo "$ssid" > "$WIFIPASSWORDCHECKLOCK"
			log_debug "cp.php backgound-worker.sh check_password"
			echo "11" # return code 11, background password check begins (and, if ok, auto-connects to ssid)

			if [ -s "$TELEPORTAP" ]; then
				local_ssid=$(sed -n 1p "$TELEPORTAP")
				local_wifipassword=$(sed -n 2p "$TELEPORTAP")
			fi

			if [ "$local_ssid" -a "$local_wifipassword" ]; then
				sh ../inc/backgound-worker.sh check_password "$freq" "$ssid" "$wifipassword" "" "$local_ssid" "$local_wifipassword" "$unicode_ssid" "$unicode_password" &

			else
				sh ../inc/backgound-worker.sh check_password "$freq" "$ssid" "$wifipassword" "" "" "" "$unicode_ssid" "$unicode_password" &

			fi

			exit

		fi

	fi

fi

[ "$password_check_freq" ] && freq="$password_check_freq"
log_debug "check_passwd.sh \"$freq\" \"$ssid\" \"***\""

if [ ! "$freq" ]; then
	echo 1
	exit
fi

if [ "$open" = "1" ]; then
	pwderr=0
else
	if [ "$freq" = "2400" -o "$freq" = "5000" ]; then
		log_debug "Channel unknown, checking without specific frequency"
		if [ "$(is_teleport)" ]; then
			echo "11"
			sh ../inc/backgound-worker.sh check_password "$freq" "$ssid" "$wifipassword" "" "$local_ssid" "$local_wifipassword" "$unicode_ssid" "$unicode_password" &
			exit
		else
			check_passwd.sh "" "$ssid" "$wifipassword"
		fi
	else
		check_passwd.sh "$freq" "$ssid" "$wifipassword"
	fi
	pwderr=$?
	log_debug "cp.php result: $pwderr"
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	echo $pwderr
	if [ "$pwderr" = 0 ]; then
		echo "$QUERY_STRING" > $SETUPFILE
		rm -f "$SETUPSSID"
	else
		echo "$ssid" > "$WIFIPASSWORDCHECKFAIL"
		echo "$wifipassword" >> "$WIFIPASSWORDCHECKFAIL"
	fi
	exit
fi

echo "<!DOCTYPE html>"
echo "<html lang='en'>"
. head-start
cat head.html
. head-stop
echo "<body>"

echo $pwderr

echo "</body>"
. html-stop
