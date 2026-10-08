#!/bin/sh

. ../inc/vars
. functions

log_debug "ml-pair.php"

. html-start
. head-start

if [ "$REQUEST_METHOD" != "POST" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

read QUERY_STRING

post_token=$(getvar "$QUERY_STRING" "token")
mac=$(getvar "$QUERY_STRING" "mac")
band=$(getvar "$QUERY_STRING" "band")

if [ "$post_token" != "$TOKEN" ]; then
	log_debug "ml-pair.php bad token"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$(get_ptype)" != "Router" -o ! "$mac" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$(validate_mac_addr "$mac")" != "valid" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" ]; then
	# set_returnurl "ml-pair.php?m=$mac"
	set_returnurl "index.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$band" = "2" ]; then
	ubus call ajconfd.Device StartWifiProtoClientInteraction '{"action": "setup", "mac":"'"$mac"'", "band":"2.4 GHz"}' > /dev/null
elif [ "$band" = "5" ]; then
	ubus call ajconfd.Device StartWifiProtoClientInteraction '{"action": "setup", "mac":"'"$mac"'", "band":"5 GHz"}' > /dev/null
else
	ubus call ajconfd.Device StartWifiProtoClientInteraction '{ "mac":"'"$mac"'", "action":"ml_pairing", "trusted": true }' > /dev/null
fi

echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
. head-stop
. html-stop
