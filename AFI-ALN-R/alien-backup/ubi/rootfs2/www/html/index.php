#!/bin/sh

. ../inc/vars
. functions
. html-start
. head-start

ptype="$(get_ptype)"

if [ "$(is_teleport)" ]; then
	if [ -f $SELECTED_SSID ]; then
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='setup.php'\" />"
	else
		teleport_state
		if [ "$teleport_status_id" -eq 10 ]; then
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='ml-pairing.php'\" />"
		else
			if [ "$teleport_setup_step" -eq 1 ]; then
				ubus call ajconfd.Device SetProperty '{"name":"WifiProtoEnabled", "value":false}' &> /dev/null
				if [ "$teleport_status_id" -eq 120 ]; then # captive portal
					echo "<meta http-equiv=\"refresh\" content=\"0;URL='ml-setup-progress.php'\" />"
				elif [ "$teleport_status_id" -eq 190 ]; then # wrong password
					echo "<meta http-equiv=\"refresh\" content=\"0;URL='setup.php'\" />"
				else
					echo "<meta http-equiv=\"refresh\" content=\"0;URL='ml-network-list.php'\" />"
				fi
			elif [ "$teleport_setup_step" -gt 1 -a "$teleport_setup_step" -lt 5 ]; then
				echo "<meta http-equiv=\"refresh\" content=\"0;URL='ml-setup-progress.php'\" />"
			else
				echo "<meta http-equiv=\"refresh\" content=\"0;URL='ml-settings.php'\" />"
			fi
		fi
	fi
elif [ "$(get_setup_done)" = "0" ]; then
	if [ -z "$(grep "shortname=AFi-ALN-P" /proc/ubnthal/board)" ]; then
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='setup.php'\" />"
	else
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='download.php'\" />"
	fi
elif [ "$(uci get ajconfd.@device_config[0].node_role)" = "router" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='select-device.php'\" />"
else
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='download.php'\" />"
fi
. head-stop
. html-stop
