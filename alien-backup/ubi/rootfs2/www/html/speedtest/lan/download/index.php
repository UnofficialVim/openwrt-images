#!/bin/sh

getvar() {
	local var
	var=$(echo "$1" | awk -F'&' '{for(i=1;i<=NF;i++){print $i}}' |\
		grep "^$2=" | awk -F'=' '{print $2;}')
	uhttpd -d "$var" 2>/dev/null
}

size=$(getvar "$QUERY_STRING" "size")
if [ -n "$size" ] && [ "$size" -eq "$size" ] 2>/dev/null; then
	continue
else
	size=104857600 # 100MB
fi

bsize=10485760
ccount=$((size/bsize))
remainder=$((size%bsize))

echo "Content-Description: File Transfer"
echo "Content-Type: application/octet-stream"
echo "Content-Disposition: attachment; filename=test.bin"
echo "Content-Transfer-Encoding: binary"
echo "Content-Length: $size"
echo "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
echo "Cache-Control: post-check=0, pre-check=0"
echo "Pragma: no-cache"
echo ""

[ $ccount ] && dd if=/dev/zero bs=$bsize count=$ccount 2>/dev/null
[ $remainder ] && dd if=/dev/zero bs=$remainder count=1 2>/dev/null
