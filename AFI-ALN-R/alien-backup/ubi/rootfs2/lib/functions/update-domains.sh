#!/bin/sh

. /usr/share/libubox/jshn.sh
. /lib/functions/afi.sh

update_domain() {
        local section=$1
        local domain=$2
        local ip_was=$(uci -q get dhcp.$section.ip)
        local name_was=$(uci -q get dhcp.$section.name)

        json_load "$(ubus call network.interface.lan status)"
        json_select ipv4-address
        json_select "1"
        json_get_var ip_now "address"

        if [ "$ip_was" != "$ip_now" ] || [ "$name_was" != "$domain" ]; then
                uci set dhcp.$section=domain
                uci set dhcp.$section.ip="$ip_now"
                uci set dhcp.$section.name="$domain"
        fi
}

captive_portal_update_domains() {
        if [ "$captive_mode" != "1" ]; then
                uci -q delete dhcp.afi_wildcard
                uci -q delete dhcp.android_captive1
                uci -q delete dhcp.android_captive2
        else
                uci set dhcp.afi_wildcard=address
                uci set dhcp.afi_wildcard.domain="#"
                uci set dhcp.afi_wildcard.address="1.2.3.4"

                uci set dhcp.android_captive1=address
                uci set dhcp.android_captive1.domain="connectivitycheck.gstatic.com"
                uci set dhcp.android_captive1.address="1.2.3.4"

                uci set dhcp.android_captive2=address
                uci set dhcp.android_captive2.domain="clients3.google.com"
                uci set dhcp.android_captive2.address="1.2.3.4"
        fi
}

exec 1000>/var/lock/update_domains.lock
flock 1000

mydom="undef"
captive_mode=0
node_role=$(uci -q get ajconfd.@device_config[0].node_role)

[ "$(uci -q get ajconfd.@device_config[0].ml_isolation_mode)" = "Full" ] && captive_mode=1
[ "$(uci -q get ajconfd.@device_config[0].initial_setup_done)" != "1" ] && captive_mode=1

if [ "$captive_mode" = "1" -o "$node_role" = "router" ]; then
        shortname=$(afi_board_shortname)
        if [ "${shortname:0:4}" = "ULTE" ]; then
            mydom="unifi"
        else
            mydom="amplifi"
        fi
else
        mydom=$(uci get system.@system[0].hostname)
fi

update_domain "afi_hostname" "$mydom"
captive_portal_update_domains
ubus call uci commit '{"config":"dhcp"}'
flock -u 1000
