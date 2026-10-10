#!/bin/sh

CHECK_INTERVAL=10
CHECK_COUNT=3

check_count=0

while true
do
	ps w | grep -v grep | grep -q "/usr/sbin/dnsmasq "
	if [ $? = 0 ]
	then
		nslookup amplifi.lan 127.0.0.1 >/dev/null 2>&1
		if [ $? = 0 ]
		then
			check_count=0
		else
			check_count=$(($check_count+1))
		fi
	else
		check_count=0
	fi
	if [ $check_count -ge $CHECK_COUNT ]
	then
		logger -t dnsmasq_wd.sh "restart dnsmasq"
		/etc/init.d/dnsmasq restart
		sleep 5
	fi
	sleep ${CHECK_INTERVAL}
done
