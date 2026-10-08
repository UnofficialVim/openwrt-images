#!/bin/sh

. ../inc/vars
. functions

log_debug "rollback.php"

json="$(ubus call ajconfd.ClusterNode GetPeerList 2>/dev/null)"
err=$(echo $?)

if [ "$err" -eq 4 ]; then
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='support.php'\" />"
	. head-stop
	. html-stop
	exit
fi

json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k"
	json_get_var flags "flags"
	json_get_var ip "ip"
	peers_ip="$k $ip"$'\n'"$peers_ip"
	json_select
done

if [ "$REQUEST_METHOD" = "POST" ]; then
	echo "Content-type: application/json"
	echo "Cache-Control: no-cache, no-store, must-revalidate"
	echo "Pragma: no-cache"
	echo "Expires: 0"
	echo ""

	read QUERY_STRING
	d=$(getvar "$QUERY_STRING" "do")
	mac=$(getvar "$QUERY_STRING" "mac")
	pkey=$(uci get ajconfd.admin.private_key);
	ip=
	if [ "$SESSION" = "invalid" -a "$(get_setup_done)" = "1" ]; then
		key=$(getvar "$QUERY_STRING" "key")
		if [ ! -z "$key" ]; then
			keydec=$(echo -n "$key" | tr "(" "=" | tr ")" "+" | base64 -d | openssl enc -aes-256-cbc -d -k "$pkey")
			if [ "$keydec" != "$(date +"%y%m%d")" ]; then
				output="{\"result\": \"key_error\" }"
				echo -n "$output"
				log_debug 'rollback.php Key error, '"$key"
				exit
			else
				log_debug 'rollback.php Remote authentificated'
			fi
		else
			output="{\"result\": \"auth_error\" }"
			echo -n "$output"
			exit
		fi
	fi

	# check token
	if [ "$SESSION" != "invalid" ]; then
		post_token=$(getvar "$QUERY_STRING" "token")
		if [ "$post_token" != "$TOKEN" ]; then
			log_debug "rollback.php bad token"
			output="{\"result\": \"bad_token\" }"
			echo -n "$output"
			exit
		fi
	fi

	if [ "$d" = "list" -o "$d" = "rollback" ]; then
		if [ ! -z "$mac" ]; then
			ip=$(echo "$peers_ip" | grep "$mac" | cut -d' ' -f2)
		fi

		if [ -z "$ip" ]; then
			if [ -f "/tmp/webui.rollbackbegin" ]; then
				echo '{"result":"rollback"}'
			fi
			if [ "$d" = "list" ]; then
				if [ -s "/tmp/webui.rollbacklist" ]; then
					fwactive=$(cat "/tmp/webui.rollbacklist" | grep " active" | awk {'print $3'} | cut -d'.' -f3-5)
					fwinactive=$(cat "/tmp/webui.rollbacklist" | grep " inactive" | grep -v "CRC ERROR" | awk {'print $3'} | cut -d'.' -f3-5)
					if [ "$fwinactive" ]; then
						fwinactive_major=$(cat "/tmp/webui.rollbacklist" | grep " inactive" | grep -v "CRC ERROR" | awk {'print $3'} | cut -d'.' -f3-4)
						rollback_versions="v2.9 v3.0 v3.1 v3.2 v3.3 v3.4 v3.5 v3.6 v3.7"
						if [ "$(echo "$rollback_versions" | grep -q "$fwinactive_major"; echo $?)" = "0" ]; then
							output="{\"active\": \"$fwactive\", \"inactive\": \"$fwinactive\", \"result\": \"ok\" }"
						else
							output="{\"active\": \"$fwactive\", \"inactive\": \"$fwinactive\", \"result\": \"version_old\" }"
						fi
					else
						output="{\"active\": \"$fwactive\", \"result\": \"inactive_bad\" }"
					fi
					echo -n "$output"
				else
					if [ ! -f "/tmp/webui.rollbackwait" ]; then
						touch /tmp/webui.rollbackwait
						sh ../inc/backgound-worker.sh uh-fw-tool_list &
					fi
					echo '{}'
				fi
			elif [ "$d" = "rollback" ]; then
				log_debug "rollback.php Begin rollback"
				echo '{"result":"rollback"}'
				touch /tmp/webui.rollbackbegin

				# migrate some settings to older firmware versions
				fwinactive_major=$(cat "/tmp/webui.rollbacklist" | grep " inactive" | awk {'print $3'} | cut -d'.' -f3-4)
				if [ "$(echo "v2.9 v3.0 v3.1" | grep -q "$fwinactive_major"; echo $?)" = "0" ]; then
					/etc/init.d/ajconf stop
					ssid=$(uci show ajconfd.@site_config[0].wifi_per_radio_user_ssid | sed -e "s/'\\\''/QZQZQZQZ/g" | cut -d"'" -f2 | sed -e "s/QZQZQZQZ/'/g")
					lines=$(uci show wireless | grep ssid | grep DYNAMIC-USER | cut -d '[' -f2 | cut -d ']' -f1)
					for line in $lines; do
						uci set wireless.@wifi-iface[${line}].ssid="$ssid"
					done
					ds_ssid=$(uci show ajconfd.@device_config[0].wifi_per_radio_ds_ssid | sed -e 's/-2G//' | sed -e "s/'\\\''/QZQZQZQZ/g" | cut -d"'" -f2 | sed -e "s/QZQZQZQZ/'/g")
					uci set ajconfd.@device_config[0].wifi_ds_ssid="$ds_ssid"
					uci set ajconfd.@device_config[0].config_version='86'
					uci delete ajconfd.@device_config[0].wifi_per_radio_ds_ssid > /dev/null
					uci delete ajconfd.@site_config[0].wifi_per_radio_user_ssid > /dev/null
					uci delete ajconfd.@site_config[0].wifi_per_radio_user_enabled > /dev/null
					uci delete ajconfd.@device_config[0].wifi_per_radio_ds_enabled > /dev/null
					uci delete ajconfd.@device_config[0].config_version > /dev/null
					uci delete dhcp.odhcpd.loglevel > /dev/null
				fi
				fwactive_full=$(cat "/tmp/webui.rollbacklist" | grep " active" | awk {'print $3'} | cut -d'.' -f3-)
				fwinactive_full=$(cat "/tmp/webui.rollbacklist" | grep " inactive" | awk {'print $3'} | cut -d'.' -f3-)
				uci set ajconfd.@device_config[0].rollback="${fwactive_full} to ${fwinactive_full}"
				uci commit > /dev/null
				cfg.sh write > /dev/null
				sh ../inc/backgound-worker.sh uh-fw-tool_switch &
			fi
		else
			log_debug "rollback.php Remote connection to IP $ip"
			key=$(date +"%y%m%d" | openssl enc -aes-256-cbc -e -k "$pkey" | base64 | tr "=" "(" | tr "+" ")")
			url="http://$ip/rollback.php"
			remote_response=$(curl -d "do=$d&key=$key" -X POST "$url" 2> /dev/null)
			if [ "$(echo "$remote_response" | grep "Not Found" -q; echo $?)" = "0" ]; then
				echo "{\"result\": \"rollback_unsupported\" }"
			else
				echo "$remote_response"
			fi
		fi
		exit
	fi
fi

. html-start
. head-start

shortname="$(get_pshortname)"
if [ "$shortname" = "AFi-ALN-R" -o "$shortname" = "AFi-ALN-P" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" -a "$(get_setup_done)" = "1" ]; then
	set_returnurl "rollback.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

cat head.html
. head-stop

echo "<body>"

. body-nav

echo "\
<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>Firmware version rollback</h1>
			</div>
		</div>
	</div>
</div>
<div class='container'>
	<div class='row'>
		<div class='col-app-container col-xs-12'>
			<div class='base-modal node--no-padding'>

				<div class='base-modal-content'>

					<div class='select-device'>
						<div class='node--body'>"

mymac=$(cat /proc/ubnthal/board | grep eth0 | cut -d'=' -f2 | tr ':' '_')
json="$(ubus call ajconfd.ClusterNode GetRegisteredNodeListJson 2>/dev/null)"

for show_role in "Router" "Extender"; do
	json_load "$json" > /dev/null
	json_select "nodes"
	json_get_keys keys
	for mac in $keys; do
		json_select "$mac"
		json_get_var role "role" # Router, Extender
		json_get_var friendly_name "friendly_name"
		json_get_var platform_name "platform_name"
		json_get_var platform_modification "platform_modification"
		if [ "$role" = "$show_role" ]; then
			ip=$(echo "$peers_ip" | grep "$mac" | cut -d' ' -f2)
			if [ ! -z "$ip" -o "$mac" = "$mymac" ]; then
				jsGetData="getData('$mac');$jsGetData"

				item_name=$(platform_info "shorter_name" "$platform_name$platform_modification")
				item_image=$(platform_info "image" "$platform_name$platform_modification")

				friendly_name="$(esc_html "${friendly_name}")"

				echo "
									<div class='item'>
										<div class='item-image $item_image'></div>
										<div class='app-t'>
											<div class='app-tr'>
												<div class='app-tc'>
													<div class='item-name smaller'>$item_name</div>
													<div class='item-id'>$friendly_name</div>
													<div id='action-$mac'>&nbsp;</div>
												</div>
											</div>
										</div>
									</div>
									"
			fi
		fi
		json_select ..
	done
done

echo "
								<div class='group' id='rollback-button' style='display:none;margin-top:30px;padding:10px'>
									<span>This will restore AmpliFi firmware to the previous version. It is recommended to factory reset AmpliFi devices after the rollback because system configuration might not be compatible.</span>
									<input class='cta-main-center-fix' type='button' style='margin-top:35px' value='Roll back' onclick='beginRollBack()'>
								</div>"
echo "

						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>

<script>

rollBackArr={}

function markRollBack(mac) {
	rollBackArr[mac]=mac;
	document.getElementById('action-'+mac).getElementsByTagName('a')[0].outerHTML='<a class=\'item-action action--rollback-ready\' href=\'#\' onclick=\'unMarkRollBack(\"'+mac+'\");return false;\'>Ready for rollback</a>'
	document.getElementById('rollback-button').style.display = 'block'
}

function unMarkRollBack(mac) {
	delete rollBackArr[mac];
	if (Object.keys(rollBackArr).length < 1) {
		document.getElementById('rollback-button').style.display = 'none'
	}
	document.getElementById('action-'+mac).getElementsByTagName('a')[0].outerHTML='<a class=\'item-action action--rollback\' href=\'#\' onclick=\'markRollBack(\"'+mac+'\");return false;\'>Roll back</a>'
}

function beginRollBack() {
	document.getElementById('rollback-button').style.display = 'none'
	for(var key in rollBackArr) {
		mac=rollBackArr[key];
		document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\'><span class=\'image\'><img src=\'img/loading-ring--1.gif\' style=\'margin-top;height:32px;width:32px\'><br /></span><span style=\'color:#AAAAAA\'>Please wait ...</span></span>';
		postRollBack(mac);
		setTimeout('redirectWhenOnline(\'$(get_homeurl)\',300000)', 30000);
	}
}

function postRollBack(mac) {
	var xhttp;
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				processDataResponse(mac, xhttp);
			}
			else if ( this.status == 0 ) {
				setTimeout('postRollBack();', 20000);
			}
		}
	};
	xhttp.open('POST', 'rollback.php', true);
	xhttp.timeout = 20000;
	xhttp.send('mac='+mac+'&do=rollback&token=$TOKEN');
}

function processDataResponse(mac, xhttp) {
	try {
		var result = JSON.parse(xhttp.responseText);
		if (result['result']) {
			if (result['result'] == 'ok') {
				if (! result['active']) result['active'] = 'current';
				if (result['inactive']) {
					document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\'><span class=\'version-current\'>'+result['active']+'</span><span class=\'version-direction direction--next\'></span><span class=\'version-next\'>'+result['inactive']+'</span></span></span><a class=\'item-action action--rollback\' href=\'#\' onclick=\'markRollBack(\"'+mac+'\");return false;\'>Roll back</a>';
				} else {
					document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\' style=\'color:#AAAAAA\'>Rollback not possible</span>';
				}
			} else if (result['result'] == 'rollback') {
				document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\'><span class=\'image\'><img src=\'img/loading-ring--1.gif\' style=\'margin-top;height:32px;width:32px\'><br /></span><span style=\'color:#AAAAAA\'>Rollback in progress ...</span></span>';
			} else {
				document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\' style=\'color:#AAAAAA\'>Rollback not possible</span>';
			}
		} else {
			document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\'><span class=\'image\'><img src=\'img/loading-ring--1.gif\' style=\'margin-top;height:32px;width:32px\'><br /></span><span style=\'color:#AAAAAA\'>Please wait ...</span></span>';
			setTimeout('getData(\''+mac+'\');', 7500);
		}

	} catch (e) {
		console.log('error:', e);
		document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\' style=\'color:#AAAAAA\'>Rollback not possible</span>';
	}
}

function getData(mac) {
	var xhttp;
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				processDataResponse(mac, xhttp);
			}
			else if ( this.status == 0 ) {
				setTimeout('getData();', 20000);

			}
			else {
				document.getElementById('action-'+mac).innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\'>Rollback not supported</span>';
			}
		}
	};
	xhttp.open('POST', 'rollback.php', true);
	xhttp.timeout = 20000;
	xhttp.send('mac='+mac+'&do=list&token=$TOKEN');
}

$jsGetData
</script>
"

echo "\
					</div>

				</div>
			</div>
		</div>
	</div>
"

echo "<script src='js/clink.js'></script><script src='js/app.js?v=4'></script></body>"

. html-stop
