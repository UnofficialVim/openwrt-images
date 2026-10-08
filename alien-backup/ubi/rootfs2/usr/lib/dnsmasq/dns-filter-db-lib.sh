#!/bin/sh

SELF=$(basename "$0")
KEY="ES5EPNBNHF5Soddt4Qy9hUonzsuw4NYo2rrwmxTh9IwS2YNtewIv1.bBMhmDvVQ4f\
fV1INfGAXasZPk0pXNsvA37-vsd939JtxRwkLsnZ0K422KTMpfxQKAs9A10MTMjlI\
IVpgcXVOaTncRQX8kX4XEVHnQbeAuKyGfzHgNKv0|vLRVfXbsDmga5rjYnlo0sp4U\
aGTOSM.QTeEmTI4cWEpdtRyq0A8v6xcHGZgxNHeRHQg4v3NcIGepaO0GqYvDXWSzm"
SYSLOG=1
ITERATIONS=6144

# Logs
log()
{
    if which logger >/dev/null && [ 0 -ne $SYSLOG ] ; then
	logger -t $SELF -p $@
    else
	echo "$@" | awk '{printf "'$SELF': %s: ", $1 ;for (i=2;i<=NF;i++){printf "%s ", $i}; printf "\n"}' >&2
    fi
}

crit()
{
	log "crit $@"
	exit 2
}

error()
{
	log "error $@"
	exit 1
}

warn()
{
	log "warn $@"
	return 0
}

debug()
{
	log "debug $@"
	return 0
}

# Misc
KEY=$(echo $KEY | awk -F'[|.-]' '{printf "Q%s5%s2%s7%sx%so", $2, $5, $4, $3, $1}')

getFreeRam()
{
    echo $((1024 * $(free | awk '{if ($1 == "Mem:") {print $4; exit}}' 2>/dev/null)))
}

getFileSize()
{
    [ -f "$1" ] || return 1
    echo $(($(du -k $1 | awk '{printf $1}')*1024))
    return 0
}

mkKeyFile()
{
    local key_file=$(mktemp)
    [ -f "$key_file" ] || return 1
    echo "$KEY" > "$key_file" || return 1
    echo $key_file
    return 0
}
