#!/bin/sh

uploaded=0
. ../inc/vars
. functions

log_debug "restore.php"

settingsFile="/tmp/restore.txt"

. html-start
. head-start

esc_dq() {
	local txt="$1"
	echo "$1" | sed -r 's/"/\\"/g'
}

if [ "$FEATURE_BACKUP" != "1" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" ]; then
	set_returnurl "restore.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then

	get_token=$(getvar "$QUERY_STRING" "token")
	if [ "$get_token" != "$TOKENGET" ]; then
		log_debug "restore.php bad token"
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='restore.php'\" />"
		. head-stop
		. html-stop
		exit
	fi
	cat | sed 1,4d | sed '$d' | head -c-2 | gzip -d > "$settingsFile"
	backupHash=$(sed -n '/Hash /p' "$settingsFile" | cut -d' ' -f2 )
	contents=$(sed '/Hash /d; /^\s*$/d' "$settingsFile")
	restoreHash=$(echo "$contents [XA*65aHpG7W^Q#$" | sed '/^\s*$/d' | md5sum | cut -d' ' -f1)
	if [ "$backupHash" = "$restoreHash" ]; then
		result=3
	else
		result=4
	fi
else
	result=$(getvar "$QUERY_STRING" "r")
fi

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
					<h1>Restore settings</h1>
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
"


if [ "$result" = "1" ]; then
echo "\
	<div class='success-message'>
		<h3 class='green'>Operation complete</h3>
		<p>Settings <strong>restored</strong></p>
		<form action='index.php' method='get'>
			<input class='cta-main-center' type='submit' value='Continue'/>
		</form>
	</div>
	"

elif [ "$REQUEST_METHOD" != "POST" ]; then
	echo "\
	<div class='form-wrap form-wrap-restore'>
		<form action='restore.php?token=$TOKENGET' method='post' enctype='multipart/form-data'>
			<div class='group'>
				<h4>Select a file to upload</h4>
				<div class='upload-item'>
					<div class='upload-input'>
						<input id='fw-file-upload' type='file' name='file'>
					</div>
					<div class='upload-label'>Click to select a file</div>
				</div>
				<div class='upload-item-extension' id='fw-file-upload-file-name'>No file selected.</div>
				<div class='processing'></div>
				<input class='cta-main-center trigger-processing' type='submit' value='Upload'>
				<div>
					<p>This will restore DHCP server settings, static leases, family profiles, custom icons and port forwarding rules</p>
				</div>
			</div>
		</form>
	</div>
	"

elif [ "$result" != "3" ]; then
	echo "\
	<div class='success-message'>
		<h3 class='red'>Invalid file!</h3>
		<input class='cta-main-center' type='button' class='button_active' onclick=\"location.href='restore.php';\" value='Retry'/>
	</div>
	"

else
	echo "\
	<div class='success-message'>
		<h3 class='green'>Upload successful</h3>
		<div class='loading'><img src='img/ring.gif' alt=''></div>\
		<p>Restore operation in progress...</p>
	</div>
	<script type='text/javascript'>
		setTimeout('window.location.href = \'restore.php?r=1\';', 90000);
		setTimeout('redirectWhenOnline(\'restore.php?r=1\',60000); waitState=2;', 5000);
	</script>
	"

	contents=$(cat "$settingsFile")

	ubus call ajconfd.Device DeferCommits >/dev/null
	echo "$contents" | while read -r line; do
		backupType=$(echo "$line" | cut -d' ' -f1)
		backupJson=$(echo "$line" | cut -d' ' -f2-)

		if [ "$backupType" = "LanDhcpPool" ]; then
			json_load "$backupJson" > /dev/null
			json_get_var lanGateway "Lan gateway"; lanGateway=$(esc_dq "$lanGateway")
			json_get_var lanAddressOffset "AddressOffset"; lanAddressOffset=$(esc_dq "$lanAddressOffset")
			json_get_var lanAddressRangeLength "AddressRangeLength"; lanAddressRangeLength=$(esc_dq "$lanAddressRangeLength")
			json_get_var lanLeaseTime "LeaseTime"; lanLeaseTime=$(esc_dq "$lanLeaseTime")
			json_cleanup

			json="$(ubus call ajconfd.Device GetNetworkConfig 2>/dev/null)"
			json_load "$json" > /dev/null
			json_get_var currentLanGateway "Lan gateway"
			json_cleanup

			if [ "$lanGateway" -a "$lanGateway" != "$currentLanGateway" ]; then
				timestamp="$(date +%s)000000"
				ubus call ajconfd.ClusterNode SetNetworkConfig '{"config": [ { "LastUpdateTimestamp":"'$timestamp'"},{ "Lan gateway": "'"$lanGateway"'/24" } ] }' >/dev/null
			fi

			ubus call ajconfd.Device SetDhcpPools '{"pools": {"lan": {"AddressOffset":'"$lanAddressOffset"', "AddressRangeLength":'"$lanAddressRangeLength"', "LeaseTime":'"$lanLeaseTime"'}}}' >/dev/null

		elif [ "$backupType" = "DhcpStaticLeases" ]; then
			json="$(ubus call ajconfd.Device GetDhcpStaticLeases 2>/dev/null)"
			json_load "$json" > /dev/null
			json_get_keys keys
			for k in $keys; do
				json_select "$k"
				json_get_var leaseMac "Mac"; leaseMac=$(esc_dq "$leaseMac")
				leaseExists="$leaseMac $leaseExists"
				json_select
			done
			json_cleanup

			json_load "$backupJson" > /dev/null
			json_get_keys keys
			for k in $keys; do
				json_select "$k"
				json_get_var leaseAddress "Address"; leaseAddress=$(esc_dq "$leaseAddress")
				json_get_var leaseDescription "Description"; leaseDescription=$(esc_dq "$leaseDescription")
				json_get_var leaseMac "Mac"; leaseMac=$(esc_dq "$leaseMac")
				json_select

				if [ "$(echo $leaseExists | grep $leaseMac)" ] ; then
					ubus call ajconfd.Device DeleteDhcpStaticLeases '{"leases": [ "'"$leaseMac"'" ]}' >/dev/null
				fi

				ubus call ajconfd.Device AddDhcpStaticLeases '{"leases": {"'"$leaseMac"'": {"Address": "'"$leaseAddress"'", "Description": "'"$leaseDescription"'", "Mac": "'"$leaseMac"'" }}}' >/dev/null
			done
			json_cleanup

		elif [ "$backupType" = "ScheduledAccessControl" ]; then
			json_load "$backupJson" > /dev/null
			json_select "devices" > /dev/null
			json_get_keys keys
			for k in $keys; do
				json_select "$k"
				json_get_var devicesName "name"; devicesName=$(esc_dq "$devicesName")
				json_select ..
				mac=$(echo "$k" | tr '_' ':')
				ubus call ajconfd.Device AddScheduledAccessControlDevices '{"devices": {"'"$mac"'": {"name": "'"$devicesName"'"} } }' >/dev/null
			done
			json_select

			json_select "profiles" > /dev/null
			json_get_keys keys
			for k in $keys; do
				json_select "$k"
				json_get_var profilesEnabled "enabled"
				json_get_var profilesName "name"; profilesName=$(esc_dq "$profilesName")
				json_get_var profilesPaused "paused"
				json_select "devices" > /dev/null
				i=1
				profilesDevices=
				while json_get_type type $i && [ "$type" = string ]; do
					json_get_var profilesDevice "$((i++))"; profilesDevice=$(esc_dq "$profilesDevice")
					if [ "$profilesDevices" = "" ]; then
						profilesDevices='"'"$profilesDevice"'"'
					else
						profilesDevices="$profilesDevices, \"$profilesDevice\""
					fi
				done
				json_select ..
				json_select ..

				[ "$profilesEnabled" = "1" ] && profilesEnabled="true" || profilesEnabled="false"
				[ "$profilesPaused" = "1" ]  && profilesPaused="true"  || profilesPaused="false"

				ubus call ajconfd.Device AddScheduledAccessControlProfiles '{"profiles": {"'"$k"'": {"devices": ['"$profilesDevices"'], "enabled": '"$profilesEnabled"', "name": "'"$profilesName"'", "paused": '"$profilesPaused"' } } }' >/dev/null

			done
			json_select

			json_select "rules" > /dev/null
			json_get_keys keys
			for k in $keys; do
				json_select "$k"
				json_get_var rulesDuration "duration"; rulesDuration=$(esc_dq "$rulesDuration")
				json_get_var rulesEnabled "enabled"
				json_get_var rulesStart_time "start_time"; rulesStart_time=$(esc_dq "$rulesStart_time")
				json_select "week_days" > /dev/null
				i=1
				rulesWeek_days=
				while json_get_type type $i && [ "$type" = int ]; do
					json_get_var rulesWeek_day "$((i++))"; rulesWeek_day=$(esc_dq "$rulesWeek_day")
					if [ "$rulesWeek_days" = "" ]; then
						rulesWeek_days="$rulesWeek_day"
					else
						rulesWeek_days="$rulesWeek_days,$rulesWeek_day"
					fi
				done

				rulesId=$(echo "$k" | tr '_' '.')
				[ "$rulesEnabled" = "1" ]  && rulesEnabled="true"  || rulesEnabled="false"
				ubus call ajconfd.Device AddScheduledAccessControlRules '{"rules": {"'"$rulesId"'": {"duration": "'"$rulesDuration"'", "enabled": '"$rulesEnabled"', "start_time": "'"$rulesStart_time"'", "week_days": ['"$rulesWeek_days"'] }}}' >/dev/null
				json_select ..
				json_select ..
			done
			json_cleanup

		elif [ "$backupType" = "PortForwardingConfig" ]; then
			json_load "$backupJson" > /dev/null
			json_get_keys keys
			for k in $keys; do
				json_select "$k"
				json_select "options"
				json_get_var Enabled "Enabled"
				json_get_var LanIpAddress "LanIpAddress"; LanIpAddress=$(esc_dq "$LanIpAddress")
				json_get_var Name "Name"; Name=$(esc_dq "$Name")
				json_select ..

				[ "$Enabled" = "1" ] && Enabled="true" || Enabled="false"
				rule='{"rules": [{"options": {"Enabled": '"$Enabled"', "LanIpAddress": "'"$LanIpAddress"'", "Name": "'"$Name"'"}, "port_mappings": ['

				json_select "port_mappings"
				json_get_keys keys2
				for k2 in $keys2; do
					json_select "$k2"
					json_select "options"
					json_get_var Protocol "Protocol"; Protocol=$(esc_dq "$Protocol")
					json_get_var WanDestinationPort "WanDestinationPort"; WanDestinationPort=$(esc_dq "$WanDestinationPort")
					json_get_var LanDestinationPort "LanDestinationPort"; LanDestinationPort=$(esc_dq "$LanDestinationPort")
					json_get_var WanPortRangeLength "WanPortRangeLength"; WanPortRangeLength=$(esc_dq "$WanPortRangeLength")
					json_get_var LanPortRangeLength "LanPortRangeLength"; LanPortRangeLength=$(esc_dq "$LanPortRangeLength")
					json_select ..
					json_select ..

					rule="$rule"'{"options": {"Protocol": '"$Protocol"', "WanDestinationPort": '"$WanDestinationPort"
					[ $LanDestinationPort ] && rule="$rule"', "LanDestinationPort": '"$LanDestinationPort"
					[ $WanPortRangeLength ] && rule="$rule"', "WanPortRangeLength": '"$WanPortRangeLength"
					[ $LanPortRangeLength ] && rule="$rule"', "LanPortRangeLength": '"$LanPortRangeLength"

					rule="$rule"'}},'

				done
				json_select ..
				json_select ..

				rule="$(echo "$rule" | sed 's/.$//')"
				rule="$rule"']}]}'

				ubus call ajconfd.Device AddPortForwardingRules "$rule" > /dev/null

			done
			json_cleanup

		elif [ "$backupType" = "ClientCustomInfo" ]; then
			json_load "$backupJson" > /dev/null
			json_get_keys keys
			for k in $keys; do
				json_select "$k"
				json_select "fingerprint"
				json_get_var device_id "device_id"; device_id=$(esc_dq "$device_id")
				mac=$(esc_dq "$k"); mac=$(echo "$mac" | tr '_' ':')
				json_select ..
				json_select ..
				ubus call ajconfd.ClusterNode SetClientCustomInfo '{"'"${mac}"'": {"fingerprint": {"device_id": '"${device_id}"'}}}' >/dev/null
			done

		fi
	done

	ubus call ajconfd.Device CommitChanges >/dev/null
fi

echo "\
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>
"

. html-stop
