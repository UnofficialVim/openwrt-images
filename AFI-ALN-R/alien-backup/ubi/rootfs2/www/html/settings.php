#!/bin/sh

. ../inc/vars
. functions

log_debug "settings.php"

[ -f "$SETUPFILE" ] && is=1 || is=0

. html-start
. head-start

if [ "$SESSION" = "invalid" -a "$is" = "0" ]; then
	set_returnurl "settings.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

esc_ubus() {
	# remove backslash, escape double quotation mark, remove apostrophe
	echo "${1}" |  sed -r 's/\\//g' | sed -r 's/"/\\"/g' | sed -r "s/\'//g";
}

ptype="$(get_ptype)"
role="$(get_role)"

wan_proto=$(uci get network.wan.proto 2>/dev/null)
wan_ipaddr=$(uci get network.wan.ipaddr 2>/dev/null)
wan_netmask=$(uci get network.wan.netmask 2>/dev/null)
wan_gateway=$(uci get network.wan.gateway 2>/dev/null)
wan_dns=$(uci get network.wan.dns 2>/dev/null)
wan_dns1=$(echo "$wan_dns" | awk '{print $1;}')
wan_dns2=$(echo "$wan_dns" | awk '{print $2;}')
wan_username=$(uci get network.wan.username 2>/dev/null)
wan_password=$(uci get network.wan.password 2>/dev/null)

json="$(ubus call ajconfd.Device GetNetworkConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var wan6_proto "Wan6 protocol"
json_get_var wan6_client_id "Wan6 client id"
json_get_var nw_hwnat "Hardware NAT"
json_get_var nw_bypassdnscache "BypassDnsCache"
json_get_var nw_wanbridgemode "Wan bridge mode"
json_get_var nw_allowip6tolan "AllowIp6ToLan"
json_get_var nw_wanclientid "WanClientId"
json_get_var nw_stp "SpanningTreeProtocol"
json_cleanup

[ "$nw_wanbridgemode" = "1" ] && wan_proto="bridge"

json="$(ubus call ajconfd.ClusterNode GetWifiConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_select "value" > /dev/null
json_select 2 > /dev/null
json_get_var wifi_80211k "Ieee80211kEnabled"
json_get_var wifi_80211r "Ieee80211rEnabled"
json_get_var wifi_80211v "Ieee80211vEnabled"
json_get_var wifi_amsdumode "AmsduMode"
json_get_var wifi_DisableAutomaticBandSwitching "DisableAutomaticBandSwitching"
json_get_var wifi_PerRadioUserNetworkEnabled "PerRadioUserNetworkEnabled"
json_cleanup

json="$(ubus call ajconfd.Device GetWifiRadios 2>/dev/null)"
json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select $k > /dev/null
	json_get_var dfs_supported "DfsSupported"
	[ "$dfs_supported" = "1" ] && show_dfs_checkbox=1
	json_select ..
done

json="$(ubus call ajconfd.Device GetDeviceConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var ForbidIgmpProxyService "ForbidIgmpProxyService"
json_cleanup

if [ "$FEATURE_QOS" = "1" ]; then
	json="$(ubus call ajconfd.Device GetQosConfig 2>/dev/null)"
	json_load "$json" > /dev/null
	json_get_var qos_enabled "enabled"
	json_get_var qos_runtime_enabled "runtime_enabled"
	json_get_var qos_download_bitrate "download_bitrate"
	json_get_var qos_upload_bitrate "upload_bitrate"
	json_cleanup
fi


json="$(ubus call ajconfd.Device HomeKit '{"args": {"action": "get_status"}}' 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var homekit_administratively_enabled "administratively_enabled"
json_get_var homekit_service_can_potentially_run "service_can_potentially_run"
json_cleanup

wifi_dfs="$(ubus call ajconfd.ClusterNode GetSiteConfig | jsonfilter -e '$["value"][1]["AcceptDfsRadarInterruptions"]')"

[ -z "$wan6_proto" ] && wan6_proto_txt="DHCPv6" || wan6_proto_txt="$wan6_proto"

[ "$nw_hwnat" = "1" ] || nw_hwnat='0'
[ "$nw_bypassdnscache" = "1" ] || nw_bypassdnscache='0'
[ "$wifi_dfs" = "true" ] && wifi_dfs='1' || wifi_dfs='0'
[ "$wifi_80211k" = "1" ] || wifi_80211k='0'
[ "$wifi_80211r" = "1" ] || wifi_80211r='0'
[ "$wifi_80211v" = "1" ] || wifi_80211v='0'
[ "$wifi_DisableAutomaticBandSwitching" = "1" ] || wifi_DisableAutomaticBandSwitching='0'
[ "$wifi_DisableAutomaticBandSwitching" = "1" ] && wifi_AutomaticBandSwitching='0' || wifi_AutomaticBandSwitching='1'
[ "$ForbidIgmpProxyService" = "1" ] && igmpproxy_service=0 || igmpproxy_service=1

wifi_amsdumode_backbone=1 && wifi_amsdumode_clients=0
[ "$wifi_amsdumode" = "Both" ] && wifi_amsdumode_backbone=1 && wifi_amsdumode_clients=1
[ "$wifi_amsdumode" = "Backbone" ] && wifi_amsdumode_backbone=1 && wifi_amsdumode_clients=0
[ "$wifi_amsdumode" = "Clients" ] && wifi_amsdumode_backbone=0 && wifi_amsdumode_clients=1
[ "$wifi_amsdumode" = "Disabled" ] && wifi_amsdumode_backbone=0 && wifi_amsdumode_clients=0

# if has paired Teleport (for setting external port)
teleport_paired=0
json="$(ubus call ajconfd.Device GetPairing 2>/dev/null)"
json_load "$json" > /dev/null
json_select "extenders" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select $k
	json_get_var platform "platform"
	json_get_var user "user"
	if [ "$platform" = "AFi-T" ]; then
		teleport_paired=1
		teleport_external_port=$(uci get ajconfd.@device_config[0].ml_external_port 2>/dev/null)
		[ "$teleport_external_port" = "0" ] && $teleport_external_port=''
		break
	fi
	json_select
	json_select "extenders"
done

[ "$(uci -q get dhcp.@dnsmasq[0].bind_blacklists)" ] && block_ad_sites=1 || block_ad_sites=0

# if user wants to save settings
if [ "$REQUEST_METHOD" = "POST" ]; then

	log_debug "settings.php save"
	echo -n '' > $UBUSCALLLOG
	read QUERY_STRING

	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "settings.php bad token"
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='settings.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	timestamp="$(date +%s)000000"


	# Enable / disable beta firmware updates
	enable_beta=$(getvar "$QUERY_STRING" "enable_beta")

	if [ "$enable_beta" != "$BETA" ]; then
		if [ "$enable_beta" = "0" ]; then
			ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidBleprotoService": false}}' >> $UBUSCALLLOG
			ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidFingerprintService": false}}' >> $UBUSCALLLOG
			ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidIgmpProxyService": false}}' >> $UBUSCALLLOG
			ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidPerfMonitorService": false}}' >> $UBUSCALLLOG
			ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidUiControlService": false}}' >> $UBUSCALLLOG
		fi

		[ "$enable_beta" = "1" ] && fw_channel="beta" || fw_channel="stable"
		ubus call ajconfd.FwUpdate SetFwUpdateConfig '{"config": {"distributed_config": { "LastUpdateTimestamp": "'"$timestamp"'"}, "fw_update_config": {"channel": "'"$fw_channel"'" } } }' >> $UBUSCALLLOG
	fi

	# Enable ADS blacklist
	enable_block_ad_sites=$(getvar "$QUERY_STRING" "enable_block_ad_sites")

	if  ([ "$block_ad_sites" = "1" ] && [ "$enable_block_ad_sites" != "1" ]) || \
		([ "$block_ad_sites" != "1" ] && [ "$enable_block_ad_sites" = "1" ]); then
		[ "$enable_block_ad_sites" = "1" ] && uci add_list dhcp.@dnsmasq[0].bind_blacklists='00:00:00:00:00:00,,ads' || uci delete dhcp.@dnsmasq[0].bind_blacklists 2>/dev/null
		uci commit && \
		/etc/init.d/dnsmasq restart 2>/dev/null && \
		cfg.sh write &> /dev/null
	fi

	# Set network config
	uc_cmd="{\"config\": [ { \"LastUpdateTimestamp\":\"$timestamp\"},{ "

	conntype=$(getvar "$QUERY_STRING" "conntype")
	if [ "$conntype" = "dhcp" ]; then
		dhcp_client_id=$(getvar "$QUERY_STRING" "dhcp_client_id")

		[ "$dhcp_client_id" ] &&
			dhcp_client_id="$(esc_ubus "${dhcp_client_id}")" &&
			dhcp_client_id="$(echo "${dhcp_client_id}" | cut -c 1-250)"

		uc_cmd="$uc_cmd \"Wan type\":\"DHCP\","
		uc_cmd="$uc_cmd \"Wan bridge mode\": false,"
		uc_cmd="$uc_cmd \"WanClientId\":\"$dhcp_client_id\","
	elif [ "$conntype" = "pppoe" ]; then
		pppoe_username=$(getvar "$QUERY_STRING" "pppoe_username")
		pppoe_password=$(getvar "$QUERY_STRING" "pppoe_password")

		pppoe_username=$(esc_ubus "${pppoe_username}")
		pppoe_username=$(esc_ubus "${pppoe_username}")

		uc_cmd="$uc_cmd \"Wan type\":\"PPPoE\","
		uc_cmd="$uc_cmd \"Wan username\":\"$pppoe_username\","
		uc_cmd="$uc_cmd \"Wan password\":\"$pppoe_password\","
		uc_cmd="$uc_cmd \"Wan bridge mode\": false,"
	elif [ "$conntype" = "static" ]; then
		ipaddr=$(getvar "$QUERY_STRING" "static_ipaddr")
		netmask=$(getvar "$QUERY_STRING" "static_netmask")
		gateway=$(getvar "$QUERY_STRING" "static_gateway")
		dns1=$(getvar "$QUERY_STRING" "static_dns1")
		dns2=$(getvar "$QUERY_STRING" "static_dns2")
		prefix=$(get_prefix "$netmask")

		ipaddr=$(esc_ubus "${ipaddr}")
		netmask=$(esc_ubus "${netmask}")
		gateway=$(esc_ubus "${gateway}")
		dns1=$(esc_ubus "${dns1}")
		dns2=$(esc_ubus "${dns2}")

		uc_cmd="$uc_cmd \"Wan type\":\"Static\","
		uc_cmd="$uc_cmd \"Wan static IP addr\":\"$ipaddr/$prefix\","
		uc_cmd="$uc_cmd \"Wan gateway\":\"$gateway\","
		if [ "$dns2" ]; then
			uc_cmd="$uc_cmd \"Wan DNS\":[\"$dns1\",\"$dns2\"],"
		else
			uc_cmd="$uc_cmd \"Wan DNS\":[\"$dns1\"],"
		fi
		uc_cmd="$uc_cmd \"Wan bridge mode\": false,"
	elif [ "$conntype" = "bridge" ]; then
		uc_cmd="$uc_cmd \"Wan bridge mode\": true,"
	fi

	enable_ipv6=$(getvar "$QUERY_STRING" "enable_ipv6")
	ipv6_config=$(getvar "$QUERY_STRING" "ipv6_config")
	clientid=$(getvar "$QUERY_STRING" "clientid")

	clientid=$(esc_ubus "${clientid}")

	if [ "$enable_ipv6" = "1" ]; then
		uc_cmd="$uc_cmd \"Wan6 up\":True,"
		if [ "$ipv6_config" = "DHCPv6" ]; then
			clientid=$(echo "$clientid" | sed -e 's/[^a-fA-F0-9:]//g')
			uc_cmd="$uc_cmd \"Wan6 protocol\":\"DHCPv6\","
			uc_cmd="$uc_cmd \"Wan6 client id\":\"$clientid\","
		else
			uc_cmd="$uc_cmd \"Wan6 protocol\":\"6to4\","
		fi
	else
		uc_cmd="$uc_cmd \"Wan6 protocol\":\"None\","
	fi

	enable_hwnat=$(getvar "$QUERY_STRING" "enable_hwnat")
	enable_bypassdnscache=$(getvar "$QUERY_STRING" "enable_bypassdnscache")
	enable_allowip6tolan=$(getvar "$QUERY_STRING" "enable_allowip6tolan")

	if [ "$SHORTNAME" != "AFi-ALN-R" ]; then
		[ "$conntype" = "pppoe" ] && enable_hwnat=0 # disable hwnat on HD router if pppoe connection type used
		[ "$enable_hwnat" = '1' ] && uc_cmd="$uc_cmd \"Hardware NAT\":True," || uc_cmd="$uc_cmd \"Hardware NAT\":False,"
	fi
	[ "$enable_bypassdnscache" = '1' ] && uc_cmd="$uc_cmd \"BypassDnsCache\":True," || uc_cmd="$uc_cmd \"BypassDnsCache\":False,"
	[ "$enable_allowip6tolan" = '1' ] && uc_cmd="$uc_cmd \"AllowIp6ToLan\":True," || uc_cmd="$uc_cmd \"AllowIp6ToLan\":False,"

	enable_stp=$(getvar "$QUERY_STRING" "enable_stp")
	[ "$enable_stp" = '1' ] && uc_cmd="$uc_cmd \"SpanningTreeProtocol\":True" || uc_cmd="$uc_cmd \"SpanningTreeProtocol\":False"

	uc_cmd="$uc_cmd } ] }"
	echo "SetNetworkConfig $uc_cmd" > /tmp/debug_ubus_call.txt
	ubus call ajconfd.ClusterNode SetNetworkConfig "$uc_cmd" >> $UBUSCALLLOG

	# IGMPproxy

	enable_igmpproxy_service=$(getvar "$QUERY_STRING" "enable_igmpproxy_service")
	if [ "$enable_igmpproxy_service" != "$igmpproxy_service" ]; then
		[ "$enable_igmpproxy_service" = "1" ] && bool_enable_ForbidIgmpProxyService="false" || bool_enable_ForbidIgmpProxyService="true"
		ubus call ajconfd.Device SetDeviceConfig '{"config": {"ForbidIgmpProxyService": '$bool_enable_ForbidIgmpProxyService'}}' >> $UBUSCALLLOG
	fi

	# Delete switch buffer values from the network config left by switch buffer override setting in HD firmware v3.1.1 and v3.1.2

	if [ "$FEATURE_HNAT" = "1" -a "$(uci -q get network.@switch_port[0].ingress_buffers 2>/dev/null)" = "24" ]; then
		lines=$(uci show network | grep -E '^network.@switch_port.+port=' | cut -d '[' -f2 | cut -d ']' -f1 | sort -r)
		for line in $lines; do
			uci delete network.@switch_port[${line}] > /dev/null;
		done
		uci commit > /dev/null
	fi

	# Set Teleport external port config

	if [ "$teleport_paired" = "1" ]; then
		teleport_external_port=$(getvar "$QUERY_STRING" "teleport_external_port")
		[ "$teleport_external_port" -eq "$teleport_external_port" ] 2>/dev/null || teleport_external_port=0
		[ "$teleport_external_port" -eq 9016 -o "$teleport_external_port" -eq 10001 ] && teleport_external_port=0
		[ "$teleport_external_port" -gt 65535 -o "$teleport_external_port" -lt 1025 ] && teleport_external_port=0
		ubus call ajconfd.Device SetDeviceConfig '{"config": { "MlExternalPort":'"$teleport_external_port"'} }' >> $UBUSCALLLOG
	fi

	# Set Site Config (enable DFS channels)

	if [ "$show_dfs_checkbox" = "1" ]; then
		enable_wifi_dfs=$(getvar "$QUERY_STRING" "enable_wifi_dfs")
		if [ "$enable_wifi_dfs" != "$wifi_dfs" ]; then
			[ "$enable_wifi_dfs" = "1" ] && wifi_dfs_value="true" || wifi_dfs_value="false"
			ubus call ajconfd.ClusterNode SetSiteConfig '{"config": [ {"LastUpdateTimestamp": "'$timestamp'"}, {"AcceptDfsRadarInterruptions": '"$wifi_dfs_value"'} ] }' >> $UBUSCALLLOG
			wifi_dfs_changed=1
		fi
	fi

	# Set WiFi config

	enable_wifi_80211v=$(getvar "$QUERY_STRING" "enable_wifi_80211v")
	enable_wifi_80211k=$(getvar "$QUERY_STRING" "enable_wifi_80211k")
	enable_wifi_80211r=$(getvar "$QUERY_STRING" "enable_wifi_80211r")
	enable_wifi_AutomaticBandSwitching=$(getvar "$QUERY_STRING" "enable_wifi_AutomaticBandSwitching")

	[ "$FEATURE_80211r" != "1" ] && enable_wifi_80211r="0"

	enable_wifi_amsdumode_clients=$(getvar "$QUERY_STRING" "enable_wifi_amsdumode_clients")
	enable_wifi_amsdumode_backbone=$(getvar "$QUERY_STRING" "enable_wifi_amsdumode_backbone")

	[ ! "$enable_wifi_amsdumode_clients" = "" ] || enable_wifi_amsdumode_clients="0"
	[ ! "$enable_wifi_amsdumode_backbone" = "" ] || enable_wifi_amsdumode_backbone="1"

	[ "$enable_wifi_amsdumode_clients" = "1" -a "$enable_wifi_amsdumode_backbone" = "1" ] && enable_wifi_amsdumode="Both"
	[ "$enable_wifi_amsdumode_clients" = "0" -a "$enable_wifi_amsdumode_backbone" = "1" ] && enable_wifi_amsdumode="Backbone"
	[ "$enable_wifi_amsdumode_clients" = "1" -a "$enable_wifi_amsdumode_backbone" = "0" ] && enable_wifi_amsdumode="Clients"
	[ "$enable_wifi_amsdumode_clients" = "0" -a "$enable_wifi_amsdumode_backbone" = "0" ] && enable_wifi_amsdumode="Disabled"

	# if WiFi config has changed
	wifi_cfg=''
	if [ "$enable_wifi_AutomaticBandSwitching" != "$wifi_AutomaticBandSwitching" ] || [ "$enable_wifi_80211v" != "$wifi_80211v" ] || [ "$enable_wifi_80211k" != "$wifi_80211k" ] || [ "$enable_wifi_80211r" != "$wifi_80211r" ] || [ "$enable_wifi_amsdumode" != "$wifi_amsdumode" ]; then
		wifi_cfg="{\"config\": [ { \"LastUpdateTimestamp\":\"$timestamp\"},{ "

		[ "$enable_wifi_AutomaticBandSwitching" = '1' ] && wifi_cfg="$wifi_cfg \"DisableAutomaticBandSwitching\":False," || wifi_cfg="$wifi_cfg \"DisableAutomaticBandSwitching\":True,"
		[ "$enable_wifi_80211v" = '1' ] && wifi_cfg="$wifi_cfg \"Ieee80211vEnabled\":True," || wifi_cfg="$wifi_cfg \"Ieee80211vEnabled\":False,"
		[ "$enable_wifi_80211k" = '1' ] && wifi_cfg="$wifi_cfg \"Ieee80211kEnabled\":True," || wifi_cfg="$wifi_cfg \"Ieee80211kEnabled\":False,"
		[ "$enable_wifi_80211r" = '1' ] && wifi_cfg="$wifi_cfg \"Ieee80211rEnabled\":True,"  || wifi_cfg="$wifi_cfg \"Ieee80211rEnabled\":False,"
		[ ! -z "$enable_wifi_amsdumode" ] && wifi_cfg="$wifi_cfg \"AmsduMode\":\"$enable_wifi_amsdumode\""

		wifi_cfg="$wifi_cfg } ] }"
	fi

	radio_cfg=''
	json="$(ubus call ajconfd.Device GetWifiRadios 2>/dev/null)"
	json_load "$json" > /dev/null
	json_get_keys keys
	for k in $keys; do
		json_select "$k" > /dev/null
		json_get_var radio_name "DeviceName"
		json_get_var radio_channel_selected "Channel"
		json_get_var radio_bandwidth "Bandwidth"
		json_get_var radio_channel_runtime "RuntimeChannel"
		json_get_var radio_bandwidth_runtime "RuntimeBandwidth"
		radio_bandwidth=$(echo $radio_bandwidth | cut -d' ' -f1)
		radio_bandwidth_runtime=$(echo $radio_bandwidth_runtime | cut -d' ' -f1)

		radiochanged=0
		post_radio_channel_selected=$(getvar "$QUERY_STRING" "sc_$radio_name")
		post_radio_bandwidth=$(getvar "$QUERY_STRING" "sb_$radio_name")

		[ "$post_radio_channel_selected" -eq "$post_radio_channel_selected" ] 2>/dev/null || post_radio_channel_selected="$radio_channel_selected"
		[ "$post_radio_bandwidth" -eq "$post_radio_bandwidth" ] 2>/dev/null || post_radio_bandwidth="$radio_bandwidth"

		[ "$post_radio_channel_selected" != "$radio_channel_selected" ] && radiochanged=1 && log_debug "settings.php: $radio_name channel $radio_channel_selected changed to $post_radio_channel_selected"
		[ "$post_radio_bandwidth" != "$radio_bandwidth" ] && radiochanged=1 && log_debug "settings.php: $radio_name bandwidth $radio_bandwidth MHz changed to $post_radio_bandwidth MHz"

		[ "$radio_name" != "radio1" -a "$radiochanged" = 0 -a "$radio_channel_selected" != -1 -a "$radio_channel_selected" != "$radio_channel_runtime" ] && radiochanged=1 && log_debug "settings.php: $radio_name runtime channel $radio_channel_runtime differs from configured $radio_channel_selected"
		[ "$radio_name" != "radio1" -a "$radiochanged" = 0 -a "$radio_bandwidth_runtime" -a "$radio_bandwidth" != "$radio_bandwidth_runtime" ] && radiochanged=1 && log_debug "settings.php: $radio_name runtime bandwidth $radio_bandwidth_runtime differs from configured $radio_bandwidth"

		[ "$radiochanged" = 1 ] && radiochanged=0 && radio_cfg='"'"$radio_name"'": {"Channel": '"$post_radio_channel_selected"', "Bandwidth": "'"$post_radio_bandwidth"' MHz"},'"$radio_cfg"

		json_select
	done
	json_cleanup

	# if WiFi config changed
	if [ "$wifi_cfg" -o "$radio_cfg" ]; then
		ubus call ajconfd.Device DeferCommits >/dev/null

		if [ "$radio_cfg" ]; then
			log_debug ubus call ajconfd.Device SetWifiRadios '{"radios": {'"$radio_cfg"'}}'
			ubus call ajconfd.Device SetWifiRadios '{"radios": {'"$radio_cfg"'}}' > /dev/null
		fi

		if [ "$wifi_cfg" ]; then
			echo "SetWifiConfig $wifi_cfg" >> /tmp/debug_ubus_call.txt
			ubus call ajconfd.ClusterNode SetWifiConfig "$wifi_cfg" >> $UBUSCALLLOG
		fi

		echo "Reconfiguring Wi-Fi. Please wait..."
		echo "<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>"
		echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'settings.php\', 60000)', 30000);</script>"
		sleep 1

		ubus call ajconfd.Device CommitChanges >/dev/null
		sleep 5
	elif [ "$wifi_dfs_changed" -a "$wifi_dfs_value"="true" ]; then
		echo "Applying settings. Please wait..."
		echo "<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>"
		echo "<script>waitOffline=0; setTimeout('redirectWhenOnline(\'settings.php\', 60000)', 20000);</script>"
		sleep 18
		ubus call ajconfd.Device GetWifiChannelsInfo >/dev/null
	fi

	# Set QoS config

	if [ "$FEATURE_QOS" = "1" ]; then
		post_qos_runtime_enabled=$(getvar "$QUERY_STRING" "enable_qos_runtime_enabled")
		post_qos_download_bitrate=$(getvar "$QUERY_STRING" "qos_download_bitrate")
		post_qos_upload_bitrate=$(getvar "$QUERY_STRING" "qos_upload_bitrate")

		[ "$post_qos_runtime_enabled" = "1" ] && post_qos_runtime_enabled="true" || post_qos_runtime_enabled="false"
		[ "$post_qos_download_bitrate" -eq "$post_qos_download_bitrate" ] 2>/dev/null || post_qos_download_bitrate=$qos_download_bitrate
		[ "$post_qos_upload_bitrate" -eq "$post_qos_upload_bitrate" ] 2>/dev/null || post_qos_upload_bitrate=$qos_upload_bitrate

		[ "$post_qos_download_bitrate" -lt 100 ] && post_qos_download_bitrate=100
		[ "$post_qos_upload_bitrate" -lt 100 ] && post_qos_upload_bitrate=100

		ubus call ajconfd.ClusterNode SetSiteConfig '{"config": [ {"LastUpdateTimestamp": "'$timestamp'"}, {"AcceptQosOverhead": true} ] }' >> $UBUSCALLLOG
		ubus call ajconfd.Device SetQosConfig '{"config": { "enabled": true, "runtime_enabled": '"$post_qos_runtime_enabled"', "download_bitrate": '"$post_qos_download_bitrate"', "upload_bitrate": '"$post_qos_upload_bitrate"'}}' >> $UBUSCALLLOG
	fi

	# Redirect browser
	if [ ! "$wifi_cfg" -a ! "$radio_cfg" -a ! "$wifi_dfs_changed" ]; then
		if [ "$is" = "1" -a $? -ne 0 ]; then
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='failed.php'\" />"
		elif [ "$is" = "1" ]; then
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='setup.php?retry=1'\" />"
		else
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='settings.php'\" />"
		fi
	fi

	. head-stop
	. html-stop
	exit
fi

lanip="$(ip -4 -oneline addr | grep 'br-lan' | grep '/24' | awk '{print $4}' | cut -d'/' -f1 2>/dev/null)"

cat head.html
. head-stop

echo "<body>"

. body-nav

echo "\
	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1>$PNAME Settings</h1>
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

						<div class='form-wrap'>
	"
	if [ "$ptype" = "Router" -a "$role" = "router" ]; then
		echo "\
							<!-- FORM STARTS -->
							<form id='settings-save' action='settings.php' method='post'>
								<div class='group'>
									<label class='static-label'>Connection Type</label>

									<div class='select-item'>
										<select id='conntype' name='conntype'>
											<option id='dd_default' disabled value=''>Select Connection Type</option>
											<option id='dd_dhcp' value='dhcp'>DHCP</option>
											<option id='dd_static' value='static'>Static</option>
											<option id='dd_pppoe' value='pppoe'>PPPoE</option>
											<option id='dd_bridge' value='bridge'>Bridge mode</option>
										</select>
										<div class='select-label'>DHCP</div>
									</div>
								</div>

								<div id='conn_dhcp' class='inv'></div>

								<div id='conn_pppoe' class='inv'>
									<div class='group'>
										<input type='text' name='pppoe_username' value=\"$wan_username\">
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Username</label>
									</div>
									<div class='group'>
										<input type='password' name='pppoe_password' value=\"$wan_password\">
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Password</label>
									</div>
								</div>

								<div id='dhcp_options' style='padding-top: 10px;'>
									<div class='group'>
										<input type='text' name='dhcp_client_id' id='dhcp_client_id' class='not_required' value='$nw_wanclientid'>
										<div id='elmhex' style='width:100%;text-align:right;display:none'><a href='#' onclick='converthex(); return false;'>Convert to hexadecimal</a>
										</div>
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>DHCP Client ID<span style='color:#AAA'> (if required)</span></label>
									</div>
								</div>

								<div id='conn_static' class='inv'>
									<div class='group'>
										<input type='text' name='static_ipaddr' value=\"$wan_ipaddr\">
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>IP Address</label>
									</div>
									<div class='group'>
										<input type='text' name='static_netmask' value=\"$wan_netmask\">
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Network Mask</label>
									</div>
									<div class='group'>
										<input type='text' name='static_gateway' value=\"$wan_gateway\">
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Gateway Address</label>
									</div>
									<div class='group'>
										<input type='text' name='static_dns1' value=\"$wan_dns1\">
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Primary DNS</label>
									</div>
									<div class='group'>
										<input type='text' name='static_dns2' class=\"not_required\" value=\"$wan_dns2\">
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Secondary DNS</label>
									</div>
								</div>

								<div id='conn_bridge' class='inv'></div>

								<div id='sep_conn_ipv6' style='height: 1px;border-bottom: 1px dashed #eee;margin-bottom: -20px;margin-top: 35px;margin-left: -40px;margin-right: -40px;'></div>
								<div id='conn_ipv6'>
									<div class='group'>
										<label class='static-label'>IPv6 Settings</label>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px;'>
											<input type='hidden' id='enable_ipv6' name='enable_ipv6' value='0'>
											<input class='checkbox-input' id='checkbox_ipv6' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Enable IPv6</span></div>
										</div>

										<div id='ipv6_expand' style='padding-left: 30px'>
											<div class='select-item'>
												<select id='target_ipv6' name='ipv6_config'>
													<option id='dd_ipv6_default' disabled='' value=''>Select IPv6 Configuration</option>
													<option id='dd_DHCPv6' value='DHCPv6'>DHCPv6</option>
													<option id='dd_6to4' value='6to4'>6to4</option>
												</select>
												<div class='select-label'>$wan6_proto_txt</div>
											</div>

											<div class='group' id='clientid'>
												<input type='text' name='clientid' value='$wan6_client_id'>
												<span class='highlight'></span>
												<span class='bar'></span>
												<label>Client ID <span style='color:#AAA'>(if required)</span></label>
											</div>
										</div>
									</div>
								</div>"

if [ "$teleport_paired" = "1" ]; then
	echo "
								<div id='sep_teleport_settings' style='height: 1px;border-bottom: 1px dashed #eee;margin-bottom: -20px;margin-top: 35px;margin-left: -40px;margin-right: -40px;'></div>

								<div id='teleport_settings' style='padding-top: 20px'>
									<div class='group'>
										<input type='text' name='teleport_external_port' id='teleport_external_port' value='$teleport_external_port' placeholder='Auto'>
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>External port for Teleport connection</label>
									</div>
								</div>"

fi
echo "
								<div id='sep_advanced_settings' style='height: 1px;border-bottom: 1px dashed #eee;margin-bottom: -20px;margin-top: 35px;margin-left: -40px;margin-right: -40px;'></div>

								<div id='advanced_settings'>
									<div class='group'>
										<label class='static-label'>Advanced Settings</label>"


if [ "$FEATURE_HNAT" = "1" -a "$SHORTNAME" != "AFi-ALN-R" ]; then
	echo "

									<div id='adv_hwnat' class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
										<input type='hidden' id='enable_hwnat' name='enable_hwnat' value='0'>
										<input class='checkbox-input' id='checkbox_hwnat' type='checkbox'>
										<div class='checkbox-check'></div>
										<div class='checkbox-label'><span style='font-weight:normal;'>Enable Hardware NAT</span></div>
										<div class='option-desc'>Accelerate throughput for wired stations</div>
									</div>"
fi
echo "

										<div id='adv_bypassdnscache' class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_bypassdnscache' name='enable_bypassdnscache' value='0'>
											<input class='checkbox-input' id='checkbox_bypassdnscache' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Bypass DNS cache</span></div>
											<div class='option-desc'>Use upstream DNS for DHCP clients</div>
										</div>

										<div id='adv_allowip6tolan' class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_allowip6tolan' name='enable_allowip6tolan' value='0'>
											<input class='checkbox-input' id='checkbox_allowip6tolan' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Allow incoming IPv6 connections</span></div>
											<div class='option-desc'>Allows incoming connection requests to IPv6 addresses on LAN</div>
										</div>"


echo "

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_wifi_AutomaticBandSwitching' name='enable_wifi_AutomaticBandSwitching' value='0'>
											<input class='checkbox-input' id='checkbox_wifi_AutomaticBandSwitching' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Automatic backbone band switching</span></div>
											<div class='option-desc'>Mesh points will try to switch backbone band if bad signal</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_igmpproxy_service' name='enable_igmpproxy_service' value='0'>
											<input class='checkbox-input' id='checkbox_igmpproxy_service' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>IGMP proxy service for IPTV</span></div>
											<div class='option-desc'>Disable if Wi-Fi calling does not work</div>
										</div>
										"

if [ "$FEATURE_STP" = "1" ]; then
	echo "

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_stp' name='enable_stp' value='0'>
											<input class='checkbox-input' id='checkbox_stp' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Enable STP</span></div>
											<div class='option-desc'>Spanning tree protocol – a protection against Ethernet loops</div>
										</div>"
fi
echo "

										<div id='adv_block_ad_sites' class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_block_ad_sites' name='enable_block_ad_sites' value='0'>
											<input class='checkbox-input' id='checkbox_block_ad_sites' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Ad blocker</span></div>
											<div class='option-desc'>DNS-based ad blocker</div>
										</div>


									</div>
								</div>

								<div id='sep_advancedradio_settings' style='height: 1px;border-bottom: 1px dashed #eee;margin-bottom: -20px;margin-top: 35px;margin-left: -40px;margin-right: -40px;'></div>

								<div id='radio_settings'>
									<div class='group'>
										<label class='static-label'>Radio Settings</label>"

if [ "$FEATURE_AMSDUC" = "1" ]; then
	echo "

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_wifi_amsdumode_clients' name='enable_wifi_amsdumode_clients' value='0'>
											<input class='checkbox-input' id='checkbox_wifi_amsdumode_clients' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>A-MSDU for wireless clients</span></div>
											<div class='option-desc'>Improves wireless clients throughput</div>
										</div>"
fi

if [ "$FEATURE_AMSDUB" = "1" ]; then
	echo "

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_wifi_amsdumode_backbone' name='enable_wifi_amsdumode_backbone' value='0'>
											<input class='checkbox-input' id='checkbox_wifi_amsdumode_backbone' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>A-MSDU for wireless backbone</span></div>
											<div class='option-desc'>Improves wireless mesh throughput</div>
										</div>"
fi

if [ "$FEATURE_80211r" = "1" ]; then
	echo "
										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_wifi_80211r' name='enable_wifi_80211r' value='0'>
											<input class='checkbox-input' id='checkbox_wifi_80211r' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>802.11r - Fast BSS Transition</span></div>
											<div class='option-desc'>Faster authentication when roaming</div>
										</div>"
fi
echo "

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_wifi_80211k' name='enable_wifi_80211k' value='0'>
											<input class='checkbox-input' id='checkbox_wifi_80211k' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>802.11k - Neighbor Report</span></div>
											<div class='option-desc'>Improves AP scan speed</div>
										</div>

										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_wifi_80211v' name='enable_wifi_80211v' value='0'>
											<input class='checkbox-input' id='checkbox_wifi_80211v' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>802.11v - BSS Transition Management</span></div>
											<div class='option-desc'>Directs stations to better AP</div>
										</div>"

if [ "$show_dfs_checkbox" = "1" ]; then
	echo "
										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_wifi_dfs' name='enable_wifi_dfs' value='0'>
											<input class='checkbox-input' id='checkbox_wifi_dfs' type='checkbox' onchange='inform_to_save(this.checked)'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Enable DFS channels</span></div>
											<div class='option-desc'>Additional 5 GHz Wi-Fi channels and 160 MHz bandwidth</div>
										</div>"
fi
echo "

										<div id=\"data\"></div>
									</div>
								</div>
<script>
radios={}"

json="$(ubus call ajconfd.Device GetWifiRadios 2>/dev/null)"
json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k" > /dev/null
	json_get_var radio_name "DeviceName"
	json_get_var radio_channel_selected "Channel"
	json_get_var radio_channel_runtime "RuntimeChannel"
	json_get_var radio_bandwidth "Bandwidth"
	json_get_var radio_bandwidth_runtime "RuntimeBandwidth"
	json_get_var radio_bandwidth_max "MaxBandwidth"
	json_get_var radio_band "Band"
	json_get_var radio_mode "Mode"
	json_get_var radio_up "Up"

	# if radio down, show configured values instead of runtime values
	[ "$radio_up" = "0" ] && radio_channel_runtime="${radio_channel_selected}" && radio_bandwidth_runtime="${radio_bandwidth}"

	radio_bandwidth="$(echo "${radio_bandwidth}" | cut -d' ' -f1)"
	radio_bandwidth_runtime="$(echo "${radio_bandwidth_runtime}" | cut -d' ' -f1)"
	radio_bandwidth_max="$(echo "${radio_bandwidth_max}" | cut -d' ' -f1)"
	[ "$radio_bandwidth_runtime" ] || radio_bandwidth_runtime=${radio_bandwidth}
	[ "$radio_name" = "radio1" ] && radio_bandwidth_runtime=${radio_bandwidth}
	echo "radios[\"${radio_name}\"]={\"channel_selected\":${radio_channel_selected},\"channel_runtime\":${radio_channel_runtime},\"bandwidth\":${radio_bandwidth},\"bandwidth_runtime\":\"${radio_bandwidth_runtime}\",\"bandwidth_max\":\"${radio_bandwidth_max}\",\"band\":\"${radio_band}\",\"mode\":\"${radio_mode}\",\"up\":\"${radio_up}\",\"channels\":{}};"
	json_select
done
json_cleanup

json="$(ubus call ajconfd.Device GetWifiChannelsInfo 2>/dev/null)"
json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k" > /dev/null
	radio="$k"
	i=1
	while true; do
		json_select $i > /dev/null
		json_get_type type "chan" > /dev/null
		[ "$type" != "int" ] && break
		json_get_var chan "chan"
		json_get_var tx_power "tx_power"
		json_get_var width "width"
		json_get_var chan "chan"
		json_get_var dfs_state "dfs_state"
		json_get_var dfs_time "dfs_time"
		json_get_var dfs_cac_time "dfs_cac_time"
		json_get_var radar "radar"
		[ $radar ] && radios_extra=",\"dfs_state\":${dfs_state},\"dfs_time\":${dfs_time},\"dfs_cac_time\":${dfs_cac_time},\"radar\":${radar}" || radios_extra=
		echo "radios[\"${radio}\"][\"channels\"][${chan}]={\"chan\":${chan},\"tx_power\":${tx_power},\"width\":${width}${radios_extra}};"
		json_select ..
		i=$((i+1))
		[ $i -gt 100 ] && break
	done

	json_select ..
done
json_cleanup

echo "
var PerRadioUserNetworkEnabled = $wifi_PerRadioUserNetworkEnabled
var wifi_dfs = $wifi_dfs
</script>"


if [ "$FEATURE_QOS" = "1" ]; then
	[ "$SHORTNAME" = "AFi-R-G" ] && qos_label="AMPLIFI GAMER'S EDITION QOS SETTINGS" || qos_label="QOS SETTINGS"
	echo "
								<div id='sep_qos_settings' style='height: 1px;border-bottom: 1px dashed #eee;margin-bottom: -20px;margin-top: 35px;margin-left: -40px;margin-right: -40px;'></div>

								<div id='qos_settings'>
									<div class='group'>
										<label class='static-label'>$qos_label</label>
										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_qos_runtime_enabled' name='enable_qos_runtime_enabled' value='0'>
											<input class='checkbox-input' id='checkbox_qos_runtime_enabled' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Enable Latency Optimization</span></div>
										</div>
									</div>
									<div class='group' style='margin-left:30px'>
										<input type='text' name='qos_download_bitrate' id='qos_download_bitrate' value='$qos_download_bitrate' placeholder='$qos_download_bitrate'>
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Broadband download speed (Kbps)</label>
									</div>
									<div class='group' style='margin-left:30px'>
										<input type='text' name='qos_upload_bitrate' id='qos_upload_bitrate' value='$qos_upload_bitrate' placeholder='$qos_upload_bitrate'>
										<span class='highlight'></span>
										<span class='bar'></span>
										<label>Broadband upload speed (Kbps)</label>
									</div>
								</div>"
fi

echo "

								<div id='sep_beta' style='height: 1px;border-bottom: 1px dashed #eee;margin-bottom: -20px;margin-top: 35px;margin-left: -40px;margin-right: -40px;'></div>

								<div id='beta_settings'>
									<div class='group'>
										<label class='static-label'>AmpliFi Early Access Firmware</label>
										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='hidden' id='enable_beta' name='enable_beta' value='0'>
											<input class='checkbox-input' id='checkbox_beta' type='checkbox'>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>Receive Early Access firmware updates</span></div>
										</div>
									</div>
								</div>"

if [ "$BETA" = "1" -o "$DEV_MODE" = "1" ]; then
	echo "
										    <div class='option-desc'><a href='beta.php'><span style='font-size: 16px;'>Early Access Options</span></div>"
fi

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
	</div>

	<script>

		function toggleInputValidation() {
			var elm = document.getElementById('conntype').value;
			var inputs = document.getElementsByTagName('input');
			for(var i = 0; i < inputs.length; i++) {
				if(inputs[i].name.indexOf(elm + '_') == 0 && inputs[i].className != 'not_required') {
				   inputs[i].required = true;
				} else {
					inputs[i].required = false;
				}
			}
		}

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

		function toggleFields() {
			var conntype = document.getElementById('conntype');
			if (conntype.value == 'bridge') {
				hideField('conn_ipv6');
				hideField('sep_conn_ipv6');
				hideField('adv_hwnat');
				hideField('adv_bypassdnscache');
				hideField('adv_allowip6tolan');

				hideField('qos_settings');
				hideField('sep_qos_settings');

				hideField('adv_block_ad_sites');
			} else {
				showField('adv_bypassdnscache');
				showField('qos_settings');
				showField('sep_qos_settings');

				if (document.getElementById('enable_bypassdnscache').value == 1) {
					hideField('adv_block_ad_sites');
				} else {
					showField('adv_block_ad_sites');
				}

				if (conntype.value == 'pppoe') {
					hideField('adv_allowip6tolan');
					hideField('conn_ipv6');
					hideField('sep_conn_ipv6');
					hideField('adv_hwnat');
				} else {
					showField('conn_ipv6');
					showField('sep_conn_ipv6');
					showField('adv_hwnat');
					if (document.getElementById('enable_ipv6').value == 1) {
						showField('adv_allowip6tolan');
					} else {
						hideField('adv_allowip6tolan');
					}
				}
			}

			if (conntype.value == 'dhcp') {
				showField('dhcp_options');
			} else {
				hideField('dhcp_options');
			}
		}

		document.getElementById('checkbox_bypassdnscache').addEventListener('change', function() {
			toggleFields();
		});

		conntype.addEventListener('change', function() {
			'use strict';
			var vis = document.querySelector('.vis');
			var target = document.getElementById('conn_' + this.value);
			if (vis !== null) {
				vis.className = 'inv';
			}
			if (target !== null) {
				target.className = 'vis';
			}
			toggleFields()
			toggleInputValidation()
		});

		document.getElementById('conn_$wan_proto').className = 'vis';
		document.getElementById('dd_$wan_proto').selected = true;

		var clientid = document.getElementById('clientid');

		var target_ipv6 = document.getElementById('target_ipv6');
		target_ipv6.addEventListener('change', function() {
			if (target_ipv6.value == 'DHCPv6') {
				clientid.style.display = 'block';
			} else {
				clientid.style.display = 'none';
			}
		});

		var enable_ipv6 = document.getElementById('enable_ipv6');
		var checkbox = document.getElementById('checkbox_ipv6');
		var ipv6_expand = document.getElementById('ipv6_expand');
		checkbox.addEventListener('click', function () {
			if (ipv6_expand.style.display != 'block') {
				ipv6_expand.style.display = 'block';
				enable_ipv6.value = '1';
			} else {
				ipv6_expand.style.display = 'none';
				enable_ipv6.value = '0';
			}
			toggleFields();
		});

		var wan6_proto = '$wan6_proto';
		var wan6_client_id = '$wan6_client_id';

		if (wan6_proto == 'DHCPv6' || wan6_proto == '6to4') {
			enable_ipv6.value = 1;
			checkbox.checked = true;
			if (wan6_proto == '6to4') {
				clientid.style.display = 'none';
			}
		} else {
			enable_ipv6.value = 0;
		}

		if (enable_ipv6.value == 0) {
			ipv6_expand.style.display = 'none';
		} else {
			ipv6_expand.style.display = 'block';
		}

		document.getElementById('dd_$wan6_proto_txt').selected = true;

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

if [ "$FEATURE_HNAT" = "1" -a "$SHORTNAME" != "AFi-ALN-R" ]; then
	echo "
		addCheckboxAction('hwnat', '$nw_hwnat');"
fi
echo "
		addCheckboxAction('bypassdnscache', '$nw_bypassdnscache');
		addCheckboxAction('allowip6tolan', '$nw_allowip6tolan');
		addCheckboxAction('wifi_dfs', '$wifi_dfs');
		addCheckboxAction('wifi_80211r', '$wifi_80211r');
		addCheckboxAction('wifi_80211k', '$wifi_80211k');
		addCheckboxAction('wifi_80211v', '$wifi_80211v');
		addCheckboxAction('wifi_amsdumode_clients', '$wifi_amsdumode_clients');
		addCheckboxAction('wifi_amsdumode_backbone', '$wifi_amsdumode_backbone');
		addCheckboxAction('qos_runtime_enabled', '$qos_runtime_enabled');
		addCheckboxAction('beta', '$BETA');
		addCheckboxAction('block_ad_sites', '$block_ad_sites');
		addCheckboxAction('stp', '$nw_stp');
		addCheckboxAction('wifi_AutomaticBandSwitching', '$wifi_AutomaticBandSwitching');
		addCheckboxAction('igmpproxy_service', '$igmpproxy_service');

		var dci = document.getElementById('dhcp_client_id');
		var elmhex = document.getElementById('elmhex');
		dci.addEventListener('keyup', function(){testhex()});

		function testhex() {
			if (dci.value && ! /^[0-9a-fA-F]+$/.test(dci.value)) {
				elmhex.style.display = 'block';
			} else {
				elmhex.style.display = 'none';
			}
		}

		function converthex() {
			if (dci.value) {
				dci.value = dci.value.split('').map(function(c){return c.charCodeAt(0).toString(16)}).join('');
				elmhex.style.display = 'none';
			}
		}

		"

if [ "$teleport_paired" = "1" ]; then
	echo "
		// validate Teleport external port input
		var tep = document.getElementById('teleport_external_port');
		tep.addEventListener('keyup', function() {
			if ((tep.value)
				&& (tep.value % 1 !== 0
					|| tep.value < 1025
					|| tep.value > 65535
					|| tep.value == 9016
					|| tep.value == 10001
				)
			) {
				tep.style.color = '#ff0000';
			} else {
				tep.style.color = '';
			}
		});"
fi
echo "

		var warningModal = {
			markup: {
				classNames: {
					active: 'modal--active',
					visible: 'modal--visible',
					tableVisible: 'modal-table--visible'
				}
			},
			show: function() {
				var self = this;
				var markup = self.markup;
				document.body.classList.add(markup.classNames.active);
				setTimeout(function() {
					markup.modal.classList.add(markup.classNames.visible);
				}, 200);
				setTimeout(function() {
					markup.table.classList.add(markup.classNames.tableVisible);
				}, 400);
			},
			close: function() {
				var self = this;
				var markup = self.markup;
				markup.table.classList.remove(markup.classNames.tableVisible);
				setTimeout(function() {
					markup.modal.classList.remove(markup.classNames.visible);
					document.body.classList.remove(markup.classNames.active);
				}, 600);
				setTimeout(function() {
					markup.modal.remove();
				}, 1000);
			},
			generateMarkup: function( params ) {
				var self = this;
				var markup = self.markup;

				var parts = {
					modal: {
						tagName: 'div',
						className: 'modal'
					},
					table: {
						tagName: 'div',
						className: 'modal-table'
					},
					row: {
						tagName: 'div',
						className: 'modal-row'
					},
					cell: {
						tagName: 'div',
						className: 'modal-cell'
					},
					head: {
						tagName: 'div',
						className: 'modal-head'
					},
					body: {
						tagName: 'div',
						className: 'modal-body'
					},
					foot: {
						tagName: 'div',
						className: 'modal-foot'
					},
					content: {
						tagName: 'div',
						className: 'modal-content'
					},
					title: {
						tagName: 'div',
						className: 'modal-title'
					},
					message: {
						tagName: 'div',
						className: 'modal-message'
					},
					cancel: {
						tagName: 'div',
						className: 'modal-cancel'
					},
					action: {
						tagName: 'div',
						className: 'modal-action'
					},
					actionBtn: {
						tagName: 'a',
						attrs: params.action.attrs
					},
					cancelBtn: {
						tagName: 'a',
						attrs: params.cancel.attrs
					}
				}

				for ( var part in parts ) {
					var tagName = parts[part].tagName;
					var className = parts[part].className;
					var attrs = parts[part].attrs;
					markup[part] = document.createElement(tagName);
					if ( className ) {
						markup[part].classList.add(className);
					}
					if ( attrs ) {
						for ( var attr in attrs ) {
							markup[part].setAttribute(attr, attrs[attr]);
						}
					}
				}

				markup.modal.appendChild(markup.table);
				markup.table.appendChild(markup.row);
				markup.row.appendChild(markup.cell);
				markup.cell.appendChild(markup.content);
				markup.content.appendChild(markup.head);
				markup.content.appendChild(markup.body);
				markup.content.appendChild(markup.foot);
				markup.head.appendChild(markup.title);
				markup.body.appendChild(markup.message);
				markup.foot.appendChild(markup.action);
				markup.foot.appendChild(markup.cancel);
				markup.action.appendChild(markup.actionBtn);
				markup.cancel.appendChild(markup.cancelBtn);
				document.body.appendChild(markup.modal);

				markup.actionBtn.addEventListener('click', function() {
					self.close();
				});

				markup.cancelBtn.addEventListener('click', function() {
					self.close();
				});

				if ( typeof params.action.callback == 'function' ) {
					markup.actionBtn.addEventListener('click', function() {
						params.action.callback( params );
					});
				}
				if ( typeof params.cancel.callback == 'function' ) {
					markup.cancelBtn.addEventListener('click', function() {
						params.cancel.callback( params );
					});
				}

			},
			injectContent: function( params ) {
				var self = this;
				var markup = self.markup;
				markup.title.innerHTML = params.title;
				markup.message.innerHTML = params.message;
				markup.actionBtn.innerHTML = params.action.title;
				markup.cancelBtn.innerHTML = params.cancel.title;
			},
			init: function( params ) {
				var self = this;
				if ( params.title && params.action && params.message ) {
					self.generateMarkup( params );
					self.injectContent( params );
					self.show();
				}
			}
		};

		// display warnings

		function createWarning(elemName, msg) {
			var this_enable = document.getElementById('enable_' + elemName);
			var this_checkbox = document.getElementById('checkbox_' + elemName);
			if (this_enable === null || this_checkbox === null) {
				return false;
			}

			this_checkbox.addEventListener('click', function () {
				if (this_checkbox.checked == true) {
					warningModal.init({
						title: 'WARNING',
						message: '<span style=\'font-size:16px\'>' + msg + '</span><br><br>Are you sure you want to continue?',
						action: {
							title: 'Yes, continue'
						},
						cancel: {
							title: 'Cancel',
							callback: function( params ) {
								this_enable.value = '0';
								this_checkbox.checked = 0;
								toggleFields();
								return false;
							}
						}
					});
				}
			});
		}

		createWarning('wifi_dfs', 'Dynamic Frequency Selection (DFS) enables additional Wi-Fi channels and 160 MHz bandwidth, but Wi-Fi interruptions are possible.<br /><br />Clients will not be able to connect to DFS channel until channel availability check is complete (1 to 10 minutes). In case of radar detection, different settings will be selected automatically.');
		createWarning('wifi_80211r', '802.11r is known to cause connectivity problems with some devices. Please disable it if you experience any problems.');"
if [ "$homekit_administratively_enabled" != "1" ]; then
	echo "
		createWarning('bypassdnscache', 'Bypass DNS cache setting will make http://amplifi.lan inaccessible. Use http://$lanip to access web portal when needed.');"
else
	echo "
		warninghomekit = 'This feature is not compatible with Apple HomeKit. If you proceed, the HomeKit will be disabled for AmpliFi router which may lead to some HomeKit paired devices lose WiFi connection.';
		createWarning('bypassdnscache', warninghomekit);
		createWarning('qos_runtime_enabled', warninghomekit);
		document.getElementById('conntype').addEventListener('change', function() {
		var conntype = document.getElementById('conntype');
		if (conntype.value == 'bridge') {
			warningModal.init({
				title: 'WARNING',
				message: '<span style=\'font-size:16px\'>' + warninghomekit + '</span><br><br>Are you sure you want to continue?',
				action: {
					title: 'Yes, continue'
				},
				cancel: {
					title: 'Cancel',
					callback: function( params ) {
						location.reload();
						return false;
					}
				}
			});
		}
	});"
fi
echo "
		createWarning('allowip6tolan', 'Services on your home network supporting IPv6 might become accessible from the internet.');
		createWarning('wifi_amsdumode_clients', 'Disable this option if devices are losing wireless connectivity.');
		createWarning('beta', 'Early access versions have additional functionality and improvements, but may be less stable. Use at your own risk.<br /><br />Create an account at <a href=\'http://community.ui.com\' target=\'_blank\'>community.ui.com</a> to access Early Access releases section.');

		toggleInputValidation();
		toggleFields();
		testhex();

	</script>
"
else
echo "\
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
"
fi

echo "<script src='js/radio.js?v=3'></script><script src='js/clink.js'></script><script src='js/app.js?v=4'></script></body></body>"

. html-stop
