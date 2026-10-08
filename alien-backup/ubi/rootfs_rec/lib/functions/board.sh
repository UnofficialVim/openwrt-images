#!/bin/sh

sysinfo()
{
        local file=$1
        [ -e /tmp/sysinfo/$file ] && cat /tmp/sysinfo/$file || echo "generic"
}

board_name()
{
        sysinfo board_name
}

board_model()
{
        sysinfo model
}
