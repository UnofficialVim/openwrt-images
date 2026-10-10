#!/bin/sh
# ajconfd qos integration for firewall3

. /lib/functions/network.sh

DATAFILE="/var/run/ajconfd/dscp.map"

delete_existing_rules() {
    # Replace add to chain with matching delete a rule from chain
    iptables -w -t mangle -S | grep -e "^-A.*-j QOS" | grep -v "^-A QOS" |
    while read add_command; do
        delete_command=$(echo $add_command | sed -e 's/^-A/iptables -w -t mangle -D/')

        $delete_command
    done

    iptables -w -t mangle -F QOS   # Delete all rules in chain
    iptables -w -t mangle -X QOS   # Delete a user-defined chain
}

create_fixed_part() {
    local out_iface=$1

    iptables -w -t mangle -N QOS                              # Create a new user-defined chain
    iptables -w -t mangle -A FORWARD -o $out_iface -j QOS     # Append to chain
}

create_variable_part() {
    while read line; do
        [ -z "$line" ] && continue

        mac=`echo $line | awk '{ print $1 }'`
        dscp=`echo $line | awk '{ print $2 }'`

        iptables -w -t mangle -A QOS -m mac --mac-source $mac -j DSCP --set-dscp $dscp
    done < $DATAFILE
}

generate_iptables_rules() {
    local out_iface=$1

    delete_existing_rules

    if [ -e $DATAFILE -a -s $DATAFILE ]; then
        create_fixed_part $out_iface
        create_variable_part
    fi
}

network_find_wan wan_iface
network_get_device wan_device $wan_iface

generate_iptables_rules $wan_device
