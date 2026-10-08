#!/bin/sh

. ../inc/vars
. functions

log_debug "si.php"

read QUERY_STRING
post_token=$(getvar "$QUERY_STRING" "token")
if [ "$post_token" != "$TOKEN" ]; then
	log_debug "si.php bad token"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='support.php?e=si'\" />"
	. head-stop
	. html-stop
	exit
fi

extended=$(getvar "$QUERY_STRING" "extended")
if [ "$extended" = "1" ]; then
	log_debug "si.php Extended support info"
	path=$(support-info -c -e | tail -n1 2>/dev/null)
	size=$(ls -l "$path" | awk '{print $5}')

else
	path=
	json="$(ubus -t 180 call ajconfd.Device GenerateSupportInfo '{ "pause_wifi_proto" : 180 }' 2>/dev/null)"
	err=$(echo $?)

	if [ "$err" -eq 0 ]; then
		json_load "$json" > /dev/null
		json_get_var path "file"
		json_get_var size "size"
		json_cleanup

	elif [ "$err" -eq 9 ]; then
		log_debug "si.php Support-info generation is already in progress"
		c=0
		while true; do
			ls -l /tmp/support_cluster/*enc-cluster.tar > /dev/null 2> /dev/null
			if [ $? -eq 0 ]; then
				path=$(ls -l /tmp/support_cluster/*enc-cluster.tar | awk '{print $9}')
				size=$(ls -l /tmp/support_cluster/*enc-cluster.tar | awk '{print $5}')
				break
			fi
			c=$((c+1))
			[ $c -eq 180 ] && break
			sleep 1
		done

	elif [ "$err" -eq 4 ]; then
		log_debug "si.php ajconf not found"
		path=$(support-info -e | tail -n1 2>/dev/null)
		size=$(ls -l "$path" | awk '{print $5}')

	fi
fi

if [ -z "$path" ]; then
	log_debug "si.php Support info failed"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='support.php?e=si'\" />"
	. head-stop
	. html-stop
	exit
fi

filename=$(basename $path)
log_debug "si.php Support info created: ${filename}"

echo "Cache-Control: public"
echo "Content-Description: File Transfer"
echo "Content-Disposition: attachment; filename=$filename"
echo "Content-Type: application/zip"
echo "Content-Transfer-Encoding: binary"
echo "Content-Length: $size"
echo ""
cat "$path"
