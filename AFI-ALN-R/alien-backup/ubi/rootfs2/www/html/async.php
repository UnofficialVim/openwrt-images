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
	d=$(getvar "$QUERY_STRING" "do")
fi

if [ "$d" = "hello" ]; then
	echo -n '{"message":"hello"}'
fi
