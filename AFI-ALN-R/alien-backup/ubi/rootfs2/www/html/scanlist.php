#!/bin/sh

. ../inc/vars
. functions
ERROR_TEXT='{"Error":42}'

log_debug "scanlist.php"

echo "Content-type: application/json"
echo "Cache-Control: no-cache, no-store, must-revalidate"
echo "Pragma: no-cache"
echo "Expires: 0"
echo ""

if [ -z "$(is_teleport)" -a "$(get_setup_done)" = "1" ]; then
	echo "$ERROR-TEXT"
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING
fi

if [ -z "$QUERY_STRING" ]; then
	echo "$ERROR_TEXT"
	exit
fi

scan=$(getvar "$QUERY_STRING" "scan")
[ "$scan" = "2" ] || scan=

SCANLIST_FULL="/tmp/scanlist_full.webui"
get_scanlist() {
	local iface="$1"
	ubus call iwinfo scan "{\"device\":\"$iface\", \"compact\":0}" > $SCANLIST_FULL.$iface
}

SCANLIST_SQUASHED="/tmp/scanlist_squashed.webui"

get_scanlist_squashed() {
	cat $SCANLIST_FULL.$1 $SCANLIST_FULL.$1 2>/dev/null | grep '"ssid"' | cut -d '"' -f 4 | sort | sort -u > $SCANLIST_SQUASHED
}


get_scanlist_json() {
	local x=
	local y=
	local comma=

	if [ -s "$SCANLIST_FULL.$1" ]; then
		x="$(tail -n +3 $SCANLIST_FULL.$1 | head -n -2 | sed 's/[[:space:]]*$//')"
	fi

	if [ -s "$SCANLIST_FULL.$2" ]; then
		y="$(tail -n +3 $SCANLIST_FULL.$2 | head -n -2 | sed 's/[[:space:]]*$//')"
	fi

	[ "$x" -a "$y" ] && comma=','
	echo -n "[ ${x} ${comma} ${y} ]" > "/tmp/scanlist_json"
}

if [ -n "$scan" ] || ([ -s "$SCANLIST_SQUASHED" ] && [ "$(date -r "$SCANLIST_SQUASHED" +%s)" -le "$(( $(date +%s) - 120 ))" ]); then
	scan="2"
	rm /tmp/scanlist* >/dev/null
fi

if [ ! -f "$SCANLIST_SQUASHED" -o ! -f "/tmp/scanlist_json" ]; then
	if [ -s "/tmp/firmware.bin" ]; then
		rm "/tmp/firmware.bin"
	fi

	iface0="wlan0-hlpr"
	iface1="wlan1-hlpr"

	[ ! "$(ifconfig $iface0 2>/dev/null)" ] && iface0="wlan0"
	[ ! "$(ifconfig $iface1 2>/dev/null)" ] && iface1="wlan1"

	if [ "$(ifconfig $iface0 2>/dev/null)" ] && [ ! -s "$SCANLIST_FULL.$iface0" -o -n "$scan" ]; then
		echo -e " " # fixes net::ERR_CONNECTION_RESET
		get_scanlist $iface0
	fi

	if [ "$(ifconfig $iface1 2>/dev/null)" ] && [ ! -s "$SCANLIST_FULL.$iface1" -o -n "$scan" ]; then
		echo -e " "
		get_scanlist $iface1
	fi

	echo -e " "
	get_scanlist_squashed $iface0 $iface1

	get_scanlist_json $iface0 $iface1

fi

cat /tmp/scanlist_json