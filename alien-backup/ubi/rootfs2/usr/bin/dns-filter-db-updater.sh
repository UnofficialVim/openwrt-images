#!/bin/sh

SELF=$(basename "$0")
CFGDIR="/tmp/dnsmasq.d/"
DBDIRNAME="dns-filter-db"
DBCFGNAME="$DBDIRNAME.auto.conf"
DBLINK="/tmp/$DBDIRNAME"

set -o pipefail

if [ -f "/usr/lib/dnsmasq/dns-filter-db-lib.sh" ]; then
    . /usr/lib/dnsmasq/dns-filter-db-lib.sh
else
    . $(dirname $(readlink -f $0))/dns-filter-db-lib.sh
fi

SYSLOG=1

usage()
{
    echo "$SELF <path/to/dns/filter/db>"
    exit 1
}

# Cleanup
cleanup()
{
    debug "Cleanup"
    [ ! -d "$CLEANUPDIR" ] || rm -rf "$CLEANUPDIR" || warn "Cannot remove directory \"$CLEANUPDIR\""
    [ ! -f "$KEYFILE" ] || rm -f "$KEYFILE" || warn "Cannot remove key file \"$KEYFILE\""
}

finish()
{
	exit 3
}

[ $# -eq 1 ] || usage

PKG=$(readlink -f $1)
[ -f "$PKG" ] || usage

trap cleanup EXIT
trap finish INT SIGTERM

debug "Prepare environment"
WORKERDIR=$(mktemp -d -t "${DBDIRNAME}.XXXXXX")
CLEANUPDIR=$WORKERDIR
[ -d "$WORKERDIR" ] || crit "Cannot create TMP directory"
chmod +rx "$WORKERDIR" || crit "Cannot change permission for \"$WORKERDIR\""
cd "$WORKERDIR" || crit "Cannot change directory to \"$WORKERDIR\""
[ -d "$CFGDIR" ] || mkdir -p "$CFGDIR" || crit "Cannot create \"$CFGDIR\" directory"
KEYFILE=$(mkKeyFile)
[ -f "$KEYFILE" ] || error "Cannot create key file"

debug "Check and prepare DB"
size=$(getFileSize "$PKG")
[ -n "$size" -a $size -gt 0 ] || error "Cannot determine size of \"$PKG\"."
[ $(getFreeRam) -gt $(($size*2)) ] || error "No free memory for extract"
tar -xf "$PKG" 2>/dev/null || error "Failed to extract update \"$PKG\""
[ -f "$WORKERDIR/package.enc" ] || error "Missing package.enc"
openssl enc -d -aes-256-cbc -iter $ITERATIONS -kfile "$KEYFILE" -in "$WORKERDIR/package.enc" -out "$WORKERDIR/package.plain" || error "Failed decryption"
rm -f "$WORKERDIR/package.enc" || error "Failed to remove package.enc"
tar -xf "$WORKERDIR/package.plain" || error "Failed to extract package.plain"
rm -f "$WORKERDIR/package.plain" || error "Failed to remove package.plain"
[ -f "$WORKERDIR/db" ] || error "Missing DB"
[ -f "$WORKERDIR/sha256sum" ] || error "Missing checksum"
checksum=$(openssl dgst -sha256 -r "$WORKERDIR/db" | awk '{print $1}')
[ -n "$checksum" ] || error "Failed to calculate checksum for DB"
[ "$checksum" == "$(awk '{print $1}' "$WORKERDIR/sha256sum")" ] || error "Invalide DB"
rm -f "$WORKERDIR/sha256sum" || error "Failed to remove sha256sum"
size=$(tar -xzOf "$WORKERDIR/db" 2>/dev/null | wc -c)
[ -n "$size" -a $size -gt 0 ] || error "Cannot determine size of uncompressed DB."
[ $(getFreeRam) -gt $size ] || error "No free memory for update"
tar -xzf "$WORKERDIR/db" || error "Failed to extract DB"
rm -f "$WORKERDIR/db" || error "Failed to remove DB"
chmod 666 "$WORKERDIR/version" || error "Failed to change version permissions"
[ -d "$WORKERDIR/blacklists" ] || error "Missing blacklists"
[ -f "$WORKERDIR/version" ] || error "Missing version"

debug "Create DB config"
for i in $WORKERDIR/blacklists/*
do
    bl=$(basename $i)
    echo "blacklist=$i,$bl" >> "$DBCFGNAME"
    chmod 666 "$i" || error "Failed to change blacklist \"$bl\" permissions"
done

debug "Install DB ($(cat ./version))"
OLDWORKERDIR=$(readlink "$DBLINK")
flock "/tmp/dns-filter-db.lock" /bin/sh -c "[ \"$OLDWORKERDIR\" == \"\$(readlink \"$DBLINK\")\" ] &&
					    ([ ! -d \"$DBLINK\" ] || rm -f \"$DBLINK\") &&
					    ln -sf \"$WORKERDIR/$DBCFGNAME\" \"$CFGDIR/$DBCFGNAME\" &&
					    ln -sf \"$WORKERDIR\" \"$DBLINK\"" ||
					    crit "Failed to set new DB link [$?]"
CLEANUPDIR="$OLDWORKERDIR"

dnsmasq_pids=$(pidof dnsmasq)
if [ -n "$dnsmasq_pids" ]; then
    need_reload=0
    for p in $dnsmasq_pids; do
	[ $need_reload -eq 1 ] && break
	for i in /var/run/dnsmasq/dnsmasq.*.pid; do
	    if [ -f "$i" -a "$p" == "$(cat "$i")" ]; then
		need_reload=1
		break
	    fi
	done
    done
    if [ $need_reload -eq 1 ]; then
        debug "Reload dnsmasq ($dnsmasq_pids)"
	/etc/init.d/dnsmasq reload || crit "Failed to reload dnsmasq"
    fi
fi
