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
fi

post_token=$(getvar "$QUERY_STRING" "token")
if [ "$post_token" != "$TOKEN" ]; then
	log_debug "settings.php bad token"
	echo '{"result": "bad_token"}'
	exit
fi

step=$(getvar "$QUERY_STRING" "step")
plugin="uisp"

if [ "$step" -eq 1 ]; then
	if [ "$(is_teleport)" ]; then
		if [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode)" = "Full" ]; then
			nso=$(ubus -S call network.interface.mlhlpr status)
			router_lan_ip=$(expr "$nso" : '.*dns-server":\["\([0-9|.]*\)"')
			result=$(ubus call $plugin do_test "{\"bind_iface\":\"tinc\",\"nameserver\":\"$router_lan_ip\"}")
		else
			result=$(ubus call $plugin do_test '{"bind_iface":"br-lan"}')
		fi
	else
		result=$(ubus call $plugin do_test)
	fi
fi

result=$(ubus call $plugin get_status);
test_state=`expr "$result" : '.*"test_state": \(\d*\)'`
test_state_str=`expr "$result" : '.*"test_state_str": "\(\S*\)"'`
progress=`expr "$result" : '.*"progress": \(\d*\)'`
latency=`expr "$result" : '.*"latency": \(\d*\)'`
speed_dl=`expr "$result" : '.*"speed_dl": \(\d*\)'`
speed_ul=`expr "$result" : '.*"speed_ul": \(\d*\)'`

[ ! $latency ] && latency=0
[ ! $speed_dl ] && speed_dl=0
[ ! $speed_ul ] && speed_ul=0

echo '{' \
'"test_state":'"$test_state"',' \
'"test_state_str":"'"$test_state_str"'",' \
'"progress":'"$progress"',' \
'"latency":'"$latency"',' \
'"speed_dl":'"$speed_dl"',' \
'"speed_ul":'"$speed_ul" \
'}';