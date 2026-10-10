#!/bin/sh

. ../inc/vars
. functions

log_debug "backup.php"

if [ "$FEATURE_BACKUP" != "1" ]; then
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" ]; then
	set_returnurl "backup.php"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

d=$(getvar "$QUERY_STRING" "d")

if [ "$d" = "1" ]; then

	# FIRMWARE VERSION
	firmwareVersion=$(cat /etc/openwrt_release | grep -m1 DISTRIB_RELEASE | cut -d'=' -f2 | tr -d "'")" "$(cat /etc/openwrt_release | grep -m1 DISTRIB_REVISION | cut -d'=' -f2 | tr -d "'")

	# PROTO VERSION
	json="$(ubus call ajconfd.Device GetProperty '{"name": "ProtocolVersion"}' 2>/dev/null)"
	json_load "$json" > /dev/null
	json_get_var protoVersion "value"
	json_cleanup

	# DEVICE MAC
	deviceMac="$(cat /proc/ubnthal/board | grep -m1 macaddr | cut -d'=' -f2)"

	# DHCP IP and range
	lanGateway=$(uci get network.lan.ipaddr)

	json="$(ubus call ajconfd.Device GetDhcpPools 2>/dev/null)"
	json_load "$json" > /dev/null
	json_select "lan" > /dev/null
	json_get_var lanAddressOffset "AddressOffset"
	json_get_var lanAddressRangeLength "AddressRangeLength"
	json_get_var lanLeaseTime "LeaseTime"
	json_cleanup

	# DHCP static leases
	GetDhcpStaticLeases=$(ubus call ajconfd.Device GetDhcpStaticLeases | sed 's/\t//g' | tr -d '\n' 2>/dev/null)

	# Scheduled Access Control
	ScheduledAccessControl=$(ubus call ajconfd.Device ScheduledAccessControl '{"args": { "get": { "devices": {}, "profiles": {}, "rules": {} } } }' | sed '1d; $d; s/"get": {/{/g; s/\t//g' | tr -d '\n' 2>/dev/null)

	# Client Custom Info
	GetClientCustomInfo=$(ubus call ajconfd.ClusterNode GetClientCustomInfo | sed 's/\t//g' | tr -d '\n' 2>/dev/null)

	# Port Forwarding Config
	GetPortForwardingConfig=$(ubus call ajconfd.Device GetPortForwardingConfig | sed 's/\t//g' | tr -d '\n' 2>/dev/null)

	# OUTPUT
	output="$output""Backup {\"date\": \"$(date)\", \"mac\": \"$deviceMac\", \"firmware version\": \"$firmwareVersion\", \"protocol version\": \"$protoVersion\"}""\n"
	output="$output""LanDhcpPool {\"Lan gateway\": \"$lanGateway\", \"AddressOffset\": \"$lanAddressOffset\", \"AddressRangeLength\": \"$lanAddressRangeLength\", \"LeaseTime\": \"$lanLeaseTime\"}""\n"
	output="$output""DhcpStaticLeases $GetDhcpStaticLeases""\n"
	output="$output""ScheduledAccessControl $ScheduledAccessControl""\n"
	output="$output""ClientCustomInfo $GetClientCustomInfo""\n"
	output="$output""PortForwardingConfig $GetPortForwardingConfig"
	hash=$(echo -e "$output [XA*65aHpG7W^Q#$" | sed '/^\s*$/d' | md5sum | cut -d' ' -f1)
	output="$output""\n""Hash $hash""\n"

	filename='amplifi-settings-'"$(date +%Y%m%d)"'.backup'

	echo "Cache-Control: public"
	echo "Content-Description: File Transfer"
	echo "Content-Disposition: attachment; filename=$filename"
	echo "Content-Type: application/zip"
	echo "Content-Transfer-Encoding: binary"
	echo ""
	echo -e "$output" | gzip -c

else
	. html-start
	. head-start

	ptype="$(get_ptype)"

	cat head.html
	. head-stop

	echo "<body>"

	. body-nav

	echo "\

		<div class='app-section-title'>
			<div class='container'>
				<div class='row'>
					<div class='col-md-16 callout'>
						<h1>Backup settings</h1>
					</div>
				</div>
			</div>
		</div>

		<div class='container'>
			<div class='row'>
				<div class='col-app-container col-xs-12'>
					<div class='base-modal'>
	"
	get_modal_header "$ptype"
	echo "\
							<div class='item-image'></div>
							<div class='app-t'>
								<div class='app-tr'>
									<div class='app-tc'>
										<div class='item-title'>$PNAME</div>
										<div class='item-id'>$AFIID</div>
									</div>
								</div>
							</div>
						</div>

						<div class='base-modal-content'>
							<div class='success-message'>
								<div>
									<p style='color:#747474'>This will create a backup file with DHCP server settings, static leases, family profiles, custom icons and port forwarding rules</p>
								</div>
								<form action='support.php' method='post'>
									<input type='hidden' name='token' value='$TOKEN'>
									<input type='hidden' name='backup' value='1'>
									<input class='cta-main-center' type='submit' value='Download'/>
								</form>
							</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<script src='js/clink.js'></script><script src='js/app.js?v=4'></script></body>"

. html-stop

fi