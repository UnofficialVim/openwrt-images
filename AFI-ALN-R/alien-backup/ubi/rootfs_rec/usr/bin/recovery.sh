#!/bin/sh

start_tftp() {
    killall -q udpsvd
    mkdir -p /tmp/tftp
    rm -rf /tmp/tftp.log
    rm -f /tmp/tftp/*
    udpsvd -vE 0.0.0.0 69 tftpd -cl /tmp/tftp &> /tmp/tftp.log &
}

start_tftp
touch /tmp/tftp.log

# Workaround for recevery.sh being restarted on sysupgrade (procd issue?)
grep -q upgrade /proc/afi_leds/mode && sleep 20

echo recovery > /proc/afi_leds/mode

while true
do
    if grep -q " exit 0" /tmp/tftp.log; then
        echo cfg_done1 > /proc/afi_leds/mode
        if [ -e /tmp/tftp/as.txt ]; then
            authscript `cat /tmp/tftp/as.txt`
            rm /tmp/tftp/as.txt
            continue;
        fi
        echo sysupgrade -n /tmp/tftp/`ls /tmp/tftp`
        sysupgrade -n /tmp/tftp/`ls /tmp/tftp` &> /dev/console && exit 0
        start_tftp
    fi
    sleep 1
done

exit 1
