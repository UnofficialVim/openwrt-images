#!/bin/sh

if [ -d /tmp/hap-wifirouter ]
then
	tar cf /tmp/persist/hap-wifirouter.tar.tmp -C /tmp hap-wifirouter
	mv /tmp/persist/hap-wifirouter.tar.tmp /tmp/persist/hap-wifirouter.tar
	(cfg.sh write &)
fi
