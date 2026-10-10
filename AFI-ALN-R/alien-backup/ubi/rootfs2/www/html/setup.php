#!/bin/sh

. ../inc/vars
. functions

log_debug "setup.php"


mode=$(getvar "$QUERY_STRING" "mode")
ptype="$(get_ptype)"

if [ "$ptype" = "MeshPoint" -o "$ptype" = "PowerBox" -o "$(is_teleport)" ]; then
	. setup-mp
else
	if [ "$mode" = "2" ]; then
		. setup-mp
	else
		. setup-r
	fi
fi
