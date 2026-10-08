#!/bin/sh

. /lib/functions.sh
. /lib/netifd/netifd-proto.sh
init_proto "$@"

proto_zeroconf_init_config() {
        true
}

proto_zeroconf_setup() {
        local config="$1"
        local iface="$2"

        # Defer setup or gratuitous arp might be lost
        sleep 10

        # Pick MAC address from ubnthal
        local MAC=`grep ^eth0 /proc/ubnthal/board`
        local B=`echo $MAC | cut -f 5 -d ":" | tr 'A-F' 'a-f'`
        local A=`echo $MAC | cut -f 6 -d ":" | tr 'A-F' 'a-f'`
        if [[ ".$B" == ".00" && ".$A" == ".00" ]]; then
            A="80"
        elif [[ ".$B" == ".ff" && ".$A" == ".ff" ]]; then
            A="7f"
        elif [[ ".$B" == ".01" && ".$A" == ".13" ]]; then
            A="12"
        fi

        local AUTO_IP=`printf "%d.%d" 0x${B} 0x${A}`
        echo 169.254.$AUTO_IP > /tmp/auto_ip

        proto_export "INTERFACE=$config"
        proto_run_command "$config" zcip \
                -f -v -r 169.254.$AUTO_IP \
                "$iface" \
                /lib/netifd/zcip.script | logger -t zcip -s
}

proto_zeroconf_teardown() {
        local interface="$1"
        proto_kill_command "$interface"
}

add_protocol zeroconf
