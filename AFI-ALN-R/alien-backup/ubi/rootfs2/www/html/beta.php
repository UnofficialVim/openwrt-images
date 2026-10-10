#!/bin/sh

. ../inc/vars
. functions

log_debug "beta.php"

. html-start
. head-start

if [ "$BUILD_TYPE" = "release" ] && [ "$BETA" != "1" ] && [ "$DEV_MODE" != "1" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" ]; then
	set_returnurl "beta.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

json="$(ubus call ajconfd.Device GetProperty '{"name": "ProtocolVersion"}' 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var protoVersion "value"
json_cleanup

json="$(ubus call ajconfd.Device GetDeviceConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var ForbidBleprotoService "ForbidBleprotoService"
json_get_var ForbidFingerprintService "ForbidFingerprintService"
json_get_var ForbidIgmpProxyService "ForbidIgmpProxyService"
json_get_var ForbidPerfMonitorService "ForbidPerfMonitorService"
json_get_var ForbidUiControlService "ForbidUiControlService"
json_cleanup

json="$(ubus call ajconfd.ClusterNode GetSiteConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_select "value" > /dev/null
json_select 2 > /dev/null
json_get_var BackboneDisconnectGracePeriod "BackboneDisconnectGracePeriod"
json_get_var BackboneSocketTos "BackboneSocketTos"
json_cleanup

json="$(ubus call ajconfd.ClusterNode GetWifiConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_select "value" > /dev/null
json_select 2 > /dev/null
json_get_var wifi_DynamicBandwidthRtsMode "DynamicBandwidthRtsMode"
json_cleanup

json="$(ubus call ajconfd.Device GetNetworkConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var nw_hwnat "Hardware NAT"
json_get_var IgmpSnooping "IgmpSnooping"
json_cleanup

json="$(ubus call ajconfd.Device HomeKit '{"args": {"action": "get_status"}}' 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var homekit_administratively_enabled "administratively_enabled"
json_get_var homekit_service_can_potentially_run "service_can_potentially_run"
json_cleanup

[ "$wifi_DynamicBandwidthRtsMode" ] && wifi_DynamicBandwidthRtsMode='1' || wifi_DynamicBandwidthRtsMode='0'
[ "$BackboneDisconnectGracePeriod" = "30" ] && state_LongerBackboneGrace=0 || state_LongerBackboneGrace=1
[ "$BackboneSocketTos" = "224" ] && state_BackboneSocketTos0=0 || state_BackboneSocketTos0=1
[ "$ForbidBleprotoService" = "1" ] || ForbidBleprotoService=0
[ "$ForbidFingerprintService" = "1" ] || ForbidFingerprintService=0
[ "$ForbidIgmpProxyService" = "1" ] || ForbidIgmpProxyService=0
[ "$ForbidPerfMonitorService" = "1" ] || ForbidPerfMonitorService=0
[ "$ForbidUiControlService" = "1" ] || ForbidUiControlService=0

FEATURE_IGMPSNOOPING=0
[ "$IgmpSnooping" = "1" ] && [ "$homekit_administratively_enabled" != "1" ] && FEATURE_IGMPSNOOPING=1
[ "$IgmpSnooping" = "0" ] && [ "$homekit_service_can_potentially_run" = "1" ] && FEATURE_IGMPSNOOPING=1

# clean unused values set in previous beta versions
uci show wireless | grep -q "disable_dyn_bw_rts"
if [ $? -eq 0 ]; then
	for i in `uci show wireless | grep "].disable_dyn_bw_rts='" | cut -d [ -f2 | cut -d ] -f1`; do
		uci delete wireless.@wifi-iface[$i].disable_dyn_bw_rts
	done
	uci commit
	cfg.sh write
fi

# if user wants to save settings
if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING

	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "beta.php bad token"
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='beta.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	do=$(getvar "$QUERY_STRING" "do")

	if [ ! "$do" ]; then

		log_debug "beta.php save settings"
		echo -n '' > $UBUSCALLLOG


		timestamp="$(date +%s)000000"

		enable_BackboneSocketTos0=$(getvar "$QUERY_STRING" "enable_BackboneSocketTos0")
		enable_LongerBackboneGrace=$(getvar "$QUERY_STRING" "enable_LongerBackboneGrace")
		enable_ForbidBleprotoService=$(getvar "$QUERY_STRING" "enable_ForbidBleprotoService")
		enable_ForbidFingerprintService=$(getvar "$QUERY_STRING" "enable_ForbidFingerprintService")
		enable_ForbidIgmpProxyService=$(getvar "$QUERY_STRING" "enable_ForbidIgmpProxyService")
		enable_ForbidPerfMonitorService=$(getvar "$QUERY_STRING" "enable_ForbidPerfMonitorService")
		enable_ForbidUiControlService=$(getvar "$QUERY_STRING" "enable_ForbidUiControlService")

		[ "$enable_BackboneSocketTos0" = "1" ] && value_BackboneSocketTos=0 || value_BackboneSocketTos=224
		[ "$enable_LongerBackboneGrace" = "1" ] && value_BackboneDisconnectGracePeriod=300 || value_BackboneDisconnectGracePeriod=30
		[ "$enable_ForbidBleprotoService" = "1" ] && bool_enable_ForbidBleprotoService="true" || bool_enable_ForbidBleprotoService="false"
		[ "$enable_ForbidFingerprintService" = "1" ] && bool_enable_ForbidFingerprintService="true" || bool_enable_ForbidFingerprintService="false"
		[ "$enable_ForbidIgmpProxyService" = "1" ] && bool_enable_ForbidIgmpProxyService="true" || bool_enable_ForbidIgmpProxyService="false"
		[ "$enable_ForbidPerfMonitorService" = "1" ] && bool_enable_ForbidPerfMonitorService="true" || bool_enable_ForbidPerfMonitorService="false"
		[ "$enable_ForbidUiControlService" = "1" ] && bool_enable_ForbidUiControlService="true" || bool_enable_ForbidUiControlService="false"

		[ "$enable_BackboneSocketTos0" != "$state_BackboneSocketTos0" ] && ubus call ajconfd.ClusterNode SetSiteConfig '{"config":[{"LastUpdateTimestamp": "'"$timestamp"'"},{"BackboneSocketTos": '$value_BackboneSocketTos'}]}' >> $UBUSCALLLOG
		[ "$enable_LongerBackboneGrace" != "$state_LongerBackboneGrace" ] && ubus call ajconfd.ClusterNode SetSiteConfig '{"config":[{"LastUpdateTimestamp": "'"$timestamp"'"},{"BackboneDisconnectGracePeriod": '$value_BackboneDisconnectGracePeriod'}]}' >> $UBUSCALLLOG
		[ "$enable_ForbidBleprotoService" != "$ForbidBleprotoService" ] && ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidBleprotoService": '$bool_enable_ForbidBleprotoService'}}' >> $UBUSCALLLOG
		[ "$enable_ForbidFingerprintService" != "$ForbidFingerprintService" ] && ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidFingerprintService": '$bool_enable_ForbidFingerprintService'}}' >> $UBUSCALLLOG
		[ "$enable_ForbidIgmpProxyService" != "$ForbidIgmpProxyService" ] && ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidIgmpProxyService": '$bool_enable_ForbidIgmpProxyService'}}' >> $UBUSCALLLOG
		[ "$enable_ForbidPerfMonitorService" != "$ForbidPerfMonitorService" ] && ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidPerfMonitorService": '$bool_enable_ForbidPerfMonitorService'}}' >> $UBUSCALLLOG
		[ "$enable_ForbidUiControlService" != "$ForbidUiControlService" ] && ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidUiControlService": '$bool_enable_ForbidUiControlService'}}' >> $UBUSCALLLOG

		if [ "$FEATURE_IGMPSNOOPING" = "1" -a  "$SHORTNAME" = "AFi-ALN-R" ]; then
			enable_IgmpSnooping=$(getvar "$QUERY_STRING" "enable_IgmpSnooping")
			[ "$enable_IgmpSnooping" = '1' ] && IgmpSnooping_bool="True" || IgmpSnooping_bool="False"
			ubus call ajconfd.ClusterNode SetNetworkConfig '{"config": [ { "LastUpdateTimestamp":"'"$timestamp"'"},{ "IgmpSnooping":'"$IgmpSnooping_bool"' }]}' >> $UBUSCALLLOG
		fi
		if [ "$FEATURE_HNAT" = "1" -a "$SHORTNAME" = "AFi-ALN-R" -a "$homekit_administratively_enabled" != "1" ]; then
			enable_hwnat=$(getvar "$QUERY_STRING" "enable_hwnat")
			[ "$enable_hwnat" = '1' ] && hwnat_bool="True" || hwnat_bool="False"
			ubus call ajconfd.ClusterNode SetNetworkConfig '{"config": [ { "LastUpdateTimestamp":"'"$timestamp"'"},{ "Hardware NAT":'"$hwnat_bool"' }]}' >> $UBUSCALLLOG
		fi

		if [ "$wifi_DynamicBandwidthRtsMode" = "1"  ]; then
			uc_cmd="{\"config\": [ { \"LastUpdateTimestamp\":\"$timestamp\"},{ \"DynamicBandwidthRtsMode\":0 } ] }"
			echo "Reconfiguring Wi-Fi. Please wait...<script>setTimeout('location.href=\'beta.php\', 40000)</script>"
			ubus call ajconfd.ClusterNode SetWifiConfig "$uc_cmd" >> $UBUSCALLLOG
			sleep 5

		else
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='beta.php'\" />"

		fi

		. head-stop
		. html-stop
		exit

	else
		echo "Please wait...<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>"

		case "$do" in
		"restart_wifi")
			log_debug "beta.php command: restart_wifi"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 15000);</script>"
			wifi
			;;
		"restart_networking")
			log_debug "beta.php command: restart_networking"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 15000);</script>"
			/etc/init.d/network restart
			;;
		"restart_dns")
			log_debug "beta.php command: restart_dns"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 2000);</script>"
			/etc/init.d/dnsmasq restart
			;;
		"restart_dhcp")
			log_debug "beta.php command: restart_dhcp"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 2000);</script>"
			/etc/init.d/odhcpd restart
			;;
		"restart_conf")
			log_debug "beta.php command: restart_ajconf"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 10000);</script>"
			/etc/init.d/ajconf restart
			;;
		"restart_fw")
			log_debug "beta.php command: restart_fw"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 2000);</script>"
			/etc/init.d/fwupdate restart; rm -f $FWFILEDOWNL
			;;
		"reboot")
			log_debug "beta.php command: reboot"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 60000);</script>"
			reboot
			;;
		"senddebuginfo_start")
			log_debug "beta.php command: senddebuginfo_start"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 5000);</script>"
			if [ ! -f "/tmp/webui.pid.logsend" ]; then
				sh "../inc/backgound-worker.sh" "send_debug_info" &
				logread_PID=$!
				echo "$logread_PID" > "/tmp/webui.pid.logsend"
			fi
			;;
		"senddebuginfo_stop")
			log_debug "beta.php command: senddebuginfo_stop"
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 2000);</script>"
			cat /tmp/webui.pid.* | xargs kill -9
			rm /tmp/logfile*;
			rm /tmp/webui.pid.*
			mac=$(cat /proc/ubnthal/system.info | grep serialno | cut -d'=' -f2)
			echo "# STOPPED by user" | gzip > "/tmp/logfile-send.txt.gz"
			curl -s -F "data=@/tmp/logfile-send.txt.gz" http://riga.corp.ubnt.com/logsend218/?m=$mac >/dev/null
			rm "/tmp/logfile-send.txt.gz"
			;;
		"disable_radio")
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 10000);</script>"
			radio=$(getvar "$QUERY_STRING" "radio")
			if [ "$radio" = "radio0" -o "$radio" = "radio1" -o "$radio" = "radio2" ]; then
				log_debug "beta.php command: disable_radio $radio"
				ubus call ajconfd.Device SetWifiRadios '{ "radios": { "'"$radio"'": { "Enabled": false } } }' &>/dev/null
			fi
			;;
		"enable_radio")
			echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'beta.php\', 60000)', 10000);</script>"
			radio=$(getvar "$QUERY_STRING" "radio")
			if [ "$radio" = "radio0" -o "$radio" = "radio1" -o "$radio" = "radio2" ]; then
				log_debug "beta.php command: enable_radio $radio"
				ubus call ajconfd.Device SetWifiRadios '{ "radios": { "'"$radio"'": { "Enabled": true } } }' &>/dev/null
			fi
			;;
		*)
			;;
		esac

		. head-stop
		. html-stop
		exit
	fi
fi

echo "
<style>
	button.beta {
		border: none;
		background-color: transparent;
		color: #BAB167;
	}

	button.beta:hover {
		color: #908000
	}
</style>
"



cat head.html
. head-stop

echo "<body>"

. body-nav

echo "\
	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1>$PNAME Early Access Options</h1>
				</div>
			</div>
		</div>
	</div>
	<div class='container'>
		<div class='row'>
			<div class='col-app-container col-xs-12'>
				<div class='base-modal'>
					"
					get_modal_header "$(get_ptype)"
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

						<div class='form-wrap'>

							<!-- FORM STARTS -->
							<form id='settings-save' action='beta.php' method='post'>
								<div class='group'>


									<h3>Resources Utilization</h3>
										<div id='utilization'>"
echo "Memory: "$((100-100*$(cat /proc/meminfo | grep MemAvailable | awk '{print $2}')/$(cat /proc/meminfo | grep MemTotal | awk '{print $2}')))"%<br />"
echo "CPU: "$((100-$(top -b -n1 | grep CPU | grep idle | awk '{print $8}' | cut -d'%' -f1)))"%"
echo "
										</div>
									<h3>Diagnostic Settings</h3>"


if [ "$FEATURE_IGMPSNOOPING" = "1" -a  "$SHORTNAME" = "AFi-ALN-R" ]; then
	echo "
										<div id='adv_hwacc' class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_IgmpSnooping' name='enable_IgmpSnooping' value='0'>
											<input class='checkbox-input' id='checkbox_IgmpSnooping' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Enable IGMP Snooping</span></div>
											<div class='option-desc'>Improves multicast traffic handling</div>
										</div>
"
fi

if [ "$FEATURE_HNAT" = "1" -a "$SHORTNAME" = "AFi-ALN-R" -a "$homekit_administratively_enabled" != "1" ]; then
	echo "
										<div id='adv_hwacc' class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_hwnat' name='enable_hwnat' value='0'>
											<input class='checkbox-input' id='checkbox_hwnat' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Enable hardware acceleration</span></div>
											<div class='option-desc'>Improves system performance</div>
										</div>
"
fi

echo "
										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_BackboneSocketTos0' name='enable_BackboneSocketTos0' value='0'>
											<input class='checkbox-input' id='checkbox_BackboneSocketTos0' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Disable internal NetworkControl QoS</span></div>
											<div class='option-desc'>Use BestEffort class for internal communication</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_LongerBackboneGrace' name='enable_LongerBackboneGrace' value='0'>
											<input class='checkbox-input' id='checkbox_LongerBackboneGrace' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Keep AP enabled on mesh points for longer on link loss</span></div>
											<div class='option-desc'>Mesh points will keep AP enabled for 5 minutes when link to router is lost</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_ForbidIgmpProxyService' name='enable_ForbidIgmpProxyService' value='0'>
											<input class='checkbox-input' id='checkbox_ForbidIgmpProxyService' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Forbid IgmpProxy Service</span></div>
											<div class='option-desc'>Enable if IPTV is not used</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_ForbidFingerprintService' name='enable_ForbidFingerprintService' value='0'>
											<input class='checkbox-input' id='checkbox_ForbidFingerprintService' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Forbid Fingerprint Service</span></div>
											<div class='option-desc'>Enable if devices icon detection is not important</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_ForbidBleprotoService' name='enable_ForbidBleprotoService' value='0'>
											<input class='checkbox-input' id='checkbox_ForbidBleprotoService' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Forbid Bleproto Service</span></div>
											<div class='option-desc'>Enable to turn off bluetooth daemon</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_ForbidPerfMonitorService' name='enable_ForbidPerfMonitorService' value='0'>
											<input class='checkbox-input' id='checkbox_ForbidPerfMonitorService' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Forbid PerfMonitor Service</span></div>
											<div class='option-desc'>Enable to turn off interface throughput monitoring</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_ForbidUiControlService' name='enable_ForbidUiControlService' value='0'>
											<input class='checkbox-input' id='checkbox_ForbidUiControlService' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Forbid UiControl Service</span></div>
											<div class='option-desc'>Enable to turn off LCD, touch and sound</div>
										</div>

									<h3>Special Commands</h3>

									<button value='restart_wifi' class='beta' name='do' formaction='beta.php' onclick='return confirm(\"Are you sure you want to restart Wi-Fi?\")'>Restart Wi-Fi</button><br />
									<button value='restart_networking' class='beta' name='do' formaction='beta.php' onclick='return confirm(\"Are you sure you want to restart Networking?\")'>Restart Networking</button><br />
									<button value='restart_dns' class='beta' name='do' formaction='beta.php' onclick='return confirm(\"Are you sure you want to restart DNS server?\")'>Restart DNS server</button><br />
									<button value='restart_dhcp' class='beta' name='do' formaction='beta.php' onclick='return confirm(\"Are you sure you want to restart DHCP server?\")'>Restart DHCP server</button><br />
									<button value='restart_conf' class='beta' name='do' formaction='beta.php' onclick='return confirm(\"Are you sure you want to restart configuration daemon?\")'>Restart configuration daemon</button><br />
									<button value='restart_fw' class='beta' name='do' formaction='beta.php' onclick='return confirm(\"Are you sure you want to restart firmware updates daemon?\")'>Restart firmware updates daemon</button><br />
									<button value='reboot' class='beta' name='do' formaction='beta.php' onclick='return confirm(\"Are you sure you want to reboot this device?\")'>Reboot device</button><br />"


cat "/proc/ubnthal/board" | grep -q "Alien"
if [ $? = 1 ]; then
	echo "<h3>Wi-Fi radios</h3>"
	json="$(ubus call ajconfd.Device GetWifiRadios 2>/dev/null)"
	json_load "$json" > /dev/null
	json_get_keys keys
	for k in $keys; do
		json_select "$k" > /dev/null
		json_get_var band "Band"
		#json_get_var mode "Mode"
		json_get_var enabled "Enabled"
		json_select
		name="$band"
		if [ "$enabled" = "1" ]; then
			echo "<a href='?do=disable_radio&radio=$k&token=$TOKENGET' onclick='return confirm(\"Wi-Fi will not be available on this radio until it enabled here or device is rebooted. Are you sure you want to disable this radio ($name)?\")'>Disable radio: $name</a><br />"
		else
			echo "<a href='?do=enable_radio&radio=$k&token=$TOKENGET'>Enable radio: $name (disabled)</a><br />"
		fi
	done
	json_cleanup
fi

peersfound=0
json="$(ubus call ajconfd.ClusterNode GetPeerList 2>/dev/null)"
json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k" > /dev/null
	json_get_var flags "flags"
	json_get_var ip "ip"
	json_get_var friendly_name "friendly_name"
	friendly_name="$(esc_html "${friendly_name}")"
	json_select

	if [ "$peersfound" = "0" ]; then
		peersfound=1
		echo "<h3>Other Peers</h3>"
	fi
	echo "<a href='http://$ip/beta.php'>$friendly_name</a><br />"

done
json_cleanup

echo "

								<input type='hidden' name='token' value='$TOKEN'>
								<div class='group'>
									<input class='cta-main-center' type='submit' value='Save & Continue'>
								</div>
							</form>
							<!-- FORM ENDS -->
						</div>

					</div>
				</div>
			</div>
		</div>
	</div>"


echo "<script src='js/clink.js'></script><script src='js/app.js?v=4b'></script></body></body>"
echo "<script>
		function hideField(elemName) {
			var elem = document.getElementById(elemName);
			if (elem !== null) {
				elem.classList.add('inv');
				elem.classList.remove('vis');
			}
		}

		function showField(elemName) {
			var elem = document.getElementById(elemName);
			if (elem !== null) {
				elem.classList.add('vis');
				elem.classList.remove('inv');
			}
		}

		function addCheckboxAction(elem, state) {
			var this_enable = document.getElementById('enable_' + elem);
			var this_checkbox = document.getElementById('checkbox_' + elem);
			if (this_enable !== null && this_checkbox !== null) {
				this_checkbox.addEventListener('click', function () {
					if (this_checkbox.checked == true) {
						this_enable.value = '1';
					} else {
						this_enable.value = '0';
					}
				});
				if (state == '1') {
					this_enable.value = 1;
					this_checkbox.checked = true;
				} else {
					this_enable.value = 0;
					this_checkbox.checked = false;
				}
			}
		}"

if [ "$SHORTNAME" = "AFi-ALN-R" ]; then
	echo "
		addCheckboxAction('IgmpSnooping', '$IgmpSnooping');"
fi

if [ "$FEATURE_HNAT" = "1" -a "$SHORTNAME" = "AFi-ALN-R" -a "$homekit_administratively_enabled" != "1" ]; then
	echo "
		addCheckboxAction('hwnat', '$nw_hwnat');"
fi

echo "
		addCheckboxAction('BackboneSocketTos0', '$state_BackboneSocketTos0');
		addCheckboxAction('LongerBackboneGrace', '$state_LongerBackboneGrace');
		addCheckboxAction('ForbidBleprotoService', '$ForbidBleprotoService');
		addCheckboxAction('ForbidFingerprintService', '$ForbidFingerprintService');
		addCheckboxAction('ForbidIgmpProxyService', '$ForbidIgmpProxyService');
		addCheckboxAction('ForbidPerfMonitorService', '$ForbidPerfMonitorService');
		addCheckboxAction('ForbidUiControlService', '$ForbidUiControlService');

	var token='$TOKEN'
	function getutilization() {
		var xhttp
		xhttp = new XMLHttpRequest()
		xhttp.responseType = 'json'
		xhttp.open('POST', 'info-async.php', true)
		xhttp.onreadystatechange = function() {
			if (this.readyState == 4) {
				if (this.status == 200) {
					setTimeout('getutilization()', 2000)
					updateUtilization(xhttp.response)
				}
			}
		}
		xhttp.timeout = 20000;
		xhttp.send('do=utilization&token=' + token)
	}
	setTimeout('getutilization()', 2000)

	var prev = {}
	function updateUtilization(response) {
		if (!prev.memory) prev = response
		var memory = Math.round((parseInt(prev.memory) + parseInt(response.memory)) / 2)
		var cpu = Math.round((parseInt(prev.cpu) + parseInt(response.cpu)) / 2)
		prev = response
		document.getElementById('utilization').innerHTML = 'Memory: ' + memory + '%<br />CPU: ' + cpu + ' %'
	}

</script>"

. html-stop
