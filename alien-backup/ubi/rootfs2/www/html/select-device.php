#!/bin/sh

. ../inc/vars
. functions
ptype="$(get_ptype)"
role="$(get_role)"

log_debug "select-device.php"

. html-start
. head-start
cat head.html
. head-stop

# ifconfig eth0 | grep HWaddr | awk '{print tolower($5)}' | tr -d :
if [ "$(uci get ajconfd.@device_config[0].node_role)" != 'router' ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='download.php'\" />"
	. html-stop
	exit
fi

echo "<body>"

. body-nav

echo "\

<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>Select device to access its settings</h1>
			</div>
		</div>
	</div>
</div>
<div class='container'>
	<div class='row'>
		<div class='col-app-container col-xs-12'>
			<div class='base-modal node--no-padding'>

				<div class='base-modal-content'>"

# get info about router
router_item_id="$(esc_html "$(uci get ajconfd.@device_config[0].friendly_name 2>/dev/null)")"
router_item_url="settings.php"
router_item_image=$(platform_info "image" "$SHORTNAME")
router_item_name=$(platform_info "shorter_name" "$SHORTNAME")

# get info about paired Teleports that are online
json="$(ubus call ajconfd.ClusterNode GetPeerList 2>/dev/null)"

json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k"
	json_get_var flags "flags"
	json_get_var friendly_name "friendly_name"
	json_get_var ip "ip"

	friendly_name="$(esc_html "${friendly_name}")"

	if [ "$((8 & $flags))" = "8" ]; then # if remote extender (TELEPORT)
		item_image="teleport"
		item_name="Teleport"
		item_id="$friendly_name"
		item_url="http://$ip"

		t_online="$k $t_online"

		output="$output

							<div class='item'>
								<a class='item-link' href='$item_url'></a>
								<div class='item-image teleport'></div>
								<div class='app-t'>
									<div class='app-tr'>
										<div class='app-tc'>
											<div class='item-name'>$item_name</div>
											<div class='item-id'>$item_id <span class='item-state state--paired'>Paired</span></div>
											<form action='ml-unpair.php' method='post'>
												<input type='hidden' name='mac' value='$k'>
												<input type='hidden' name='token' value='$TOKEN'>
												<button type='submit' class='item-action action--unpair'>Unpair</button>
											</form>
										</div>
									</div>
								</div>
							</div>"
							#<a class='item-action action--unpair' href='ml-unpair.php?m=$k'>Unpair</a>
	fi
	json_select
done

# create the list of paired Teleports, needed for listing managed Teleports
json="$(ubus call ajconfd.Device GetPairing 2>/dev/null)"
json_load "$json" > /dev/null
json_select "extenders" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select $k
	json_get_var platform "platform"
	json_get_var user "user"
	if [ "$platform" = "AFi-T" ]; then
		t_paired="$k $t_paired"
	fi
	json_select
	json_select "extenders"
done


# get info about unpaired Teleports
json="$(ubus call ajconfd.Device GetWifiProtoClients 2>/dev/null)"
json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k"
	json_get_var platform "platform"
	json_get_var state "state"
	mac=$(echo "$k" | tr "_" ":")
	if [ "$platform" = "AFi-T" ]; then

		message_extra=
		if [ "$state" = "factory_reset" ]; then
			message="Ready to pair"
		elif [ "$state" = "setup_init" -o "$state" = "setup_requested" ]; then
			message="Pairing"
			message_extra="<span class='node--rollback-section'><span class='item-version'><span class='image'><img src='img/loading-ring--1.gif' style='margin-top;height:32px;width:32px'><br /></span><span style='color:#AAAAAA'>Please wait ...</span></span>"
			refresh_page=1

		elif [ "$state" = "managed" -o "$state" = "factory_reset_paired" ]; then
			# check if Teleport is paired to this router
			if [ "$(echo $t_paired | grep $k)" ] ; then
				ssid="Teleport "$(echo "$k" | tr -d "_" | tr 'a-z' 'A-Z' | cut -c7-12)
				message="<span title='Connect to SSID \"$ssid\" to setup Teleport'>Paired, setup required</span>"
			else
				message="Paired to another router"
			fi

		else
			message="$state"
			refresh_page=1

		fi

		t_unpaired="$k $t_unpaired"
		output="$output

							<div class='item'>
								<div class='item-image teleport'></div>
								<div class='app-t'>
									<div class='app-tr'>
										<div class='app-tc'>
											<div class='item-name'>Teleport</div>
											<div class='item-id'>$mac <span class='item-state state--ready'>$message</span></div>$message_extra"
		if [ "$state" = "factory_reset" ]; then
			output="$output
											<form action='ml-pair.php' method='post'>
												<input type='hidden' name='mac' value='$mac'>
												<input type='hidden' name='token' value='$TOKEN'>
												<button type='submit' class='item-action action--pair'>Pair</button>
											</form>"
											#<a class='item-action action--pair' href='ml-pair.php?m=$mac'>Pair</a>
		fi
		output="$output
										</div>
									</div>
								</div>
							</div>"
	else
		item_name=$(platform_info "shorter_name" "$platform")
		item_image=$(platform_info "image" "$platform")
		output="$output


							<div class='item'>
								<div class='item-image $item_image'></div>
								<div class='app-t'>
									<div class='app-tr'>
										<div class='app-tc'>
											<div class='item-name'>$item_name</div>"
		if [ "$state" = "factory_reset" ]; then
			output="$output
											<div class='item-id'>$mac<span class='item-state state--ready'>New AmpliFi device</span></div>
											<form action='ml-pair.php' method='post'>
												<input type='hidden' name='mac' value='$mac'>
												<input type='hidden' name='band' value='5'>
												<input type='hidden' name='token' value='$TOKEN'>
												<button type='submit' class='item-action action--pair'>Add to network</button>
											</form>"
											#<a class='item-action action--pair' href='ml-pair.php?m=$mac&band=5'>Add to network</a>

		elif [ "$state" = "setup_init" -o "$state" = "setup_requested" -o "$state" = "first_sync_done" ]; then
			refresh_page=1
			output="$output
											<div class='item-id'>$mac<span class='item-state state--ready'>Adding to network</span></div>
											<span class='node--rollback-section'><span class='item-version'><span class='image'><img src='img/loading-ring--1.gif' style='margin-top;height:32px;width:32px'><br /></span><span style='color:#AAAAAA'>Please wait ...</span></span>"
		fi
		output="$output
										</div>
									</div>
								</div>
							</div>"
	fi
	json_select
done


# offline Teleports from pairing data
json="$(ubus call ajconfd.Device GetPairing 2>/dev/null)"
json_load "$json" > /dev/null
json_select "extenders" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select $k
	json_get_var platform "platform"
	json_get_var user "user"
	if [ "$platform" = "AFi-T" -o "$user" = "1" ]; then
		if [ ! "$(echo $t_online | grep $k)" ] && [ ! "$(echo $t_unpaired | grep $k)" ] ; then
			if [ "$platform" = "AFi-T" ]; then
				mac=$(echo "$k" | tr "_" ":")
				output="$output

							<div class='item'>
								<div class='item-image teleport item-offline'></div>
								<div class='app-t'>
									<div class='app-tr'>
										<div class='app-tc'>
											<div class='item-name'>Teleport</div>
											<div class='item-id'>$mac <span class='item-state state--offline'>Offline</span></div>
											<form action='ml-unpair.php' method='post'>
												<input type='hidden' name='mac' value='$k'>
												<input type='hidden' name='offline' value='1'>
												<input type='hidden' name='token' value='$TOKEN'>
												<button type='submit' class='item-action action--unpair'>Unpair</button>
											</form>
										</div>
									</div>
								</div>
							</div>"
							#<a class='item-action action--unpair' href='ml-unpair.php?m=$k&offline=1'>Unpair</a>
			fi
		fi
	fi
	json_select
	json_select "extenders"
done


if [ "$output" ]; then
	echo "
					<div class='select-device'>
						<div class='node--body'>

							<div class='item'>
								<a class='item-link' href='$router_item_url'></a>
								<div class='item-image $router_item_image'></div>
								<div class='app-t'>
									<div class='app-tr'>
										<div class='app-tc'>
											<div class='item-name'>$router_item_name</div>
											<div class='item-id'>$router_item_id</div>
										</div>
									</div>
								</div>
							</div>"

	echo $output
else
	echo "
					<div class='select-device-expanded'>
						<div class='node--body'>

							<div class='item'>
								<a class='item-link' href='$router_item_url'></a>
								<div class='item-image $router_item_image'></div>
								<div class='item-name'>$router_item_name</div>
								<div class='item-id'>$router_item_id</div>
							</div>"
fi

echo "
						</div>
					</div>

				</div>

			</div>
		</div>
	</div>
</div>
<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>

"
if [ "$refresh_page" ]; then
	echo "
<script>
	setTimeout(function() { window.location.href = 'select-device.php'; }, 10000);
</script>

"
fi

if [ "$(ubus call ajconfd.FwUpdate GetAvailableUpdates 2>/dev/null | grep '"status": "available"' -q; echo $?)" = "0" ]; then
	echo "
<script>
setTimeout(function() {
	amplifiJsUtil.vault().supportNoticeForUpdate({
		active: true, title: 'Software update is available'
	})
}, 1000);
</script>

"
fi

echo "</body>"

. html-stop
