#!/bin/sh

. ../inc/vars
. functions

log_debug "support.php"

json=$(ubus call firmware info)
json_load "$json"
json_select current
json_get_var current_fw_version "version"
json_get_var current_fw_revision "revision"
json_select ..
json_select upgrade
json_get_var fwupgr "version"

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING

	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "support.php bad token"
	else
		backup=$(getvar "$QUERY_STRING" "backup")
	fi
fi

error=$(getvar "$QUERY_STRING" "e")

ptype="$(get_ptype)"

if [ "$(is_teleport)" -a "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" = "Full" ]; then
	fwupdate_info=$(ubus call firmware info | grep 'upgrade":' -A4)
	fwupdate_status=$(echo "$fwupdate_info" | grep '"status": "Ready to upgrade"')
	if [ "$fwupdate_status" ]; then
		fwupdate_version=$(echo "$fwupdate_info" | grep 'version' | cut -d'"' -f4)
		#fwupdate_revision=$(echo "$fwupdate_info" | grep 'revision' | cut -d'"' -f4)
		current_version="$(ubus call firmware info | grep 'current":' -A2 | grep 'version' | cut -d'"' -f4)"
	fi
fi

device_mac="$(cat /proc/ubnthal/board | grep -m1 macaddr | cut -d'=' -f2)"
[ "$BUILD_TYPE" = "release" -o "$BUILD_TYPE" = "rc" ] && current_version_string="$current_fw_version" || current_version_string="$current_fw_version $current_fw_revision"
[ ! "$current_version_string" ] && current_version_string="$(cat /etc/openwrt_release | grep DISTRIB_RELEASE | cut -d"'" -f2)"

. html-start
. head-start
cat head.html
. head-stop

echo "<body>"

. body-nav

echo "\
	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1>Support</h1>
				</div>
			</div>
		</div>
	</div>

	<div class='container'>
		<div class='row'>
			<div class='col-app-container col-xs-12'>
				<div class='base-modal' style='padding-bottom:0px;'>
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

					<div class='base-modal-content'>"

if [ "$fwupdate_status" ]; then echo "
						<form class='node--update-section' action='update.php' method='POST'>
							<input type='hidden' name='token' value='$TOKEN'>
							<input type='hidden' name='upgrade' value='1' />
							<div class='item-title'>Firmware update</div>
							<div class='item-description'>Firmware update is avaiable for your Teleport. It is recommended to update.</div>
							<div class='item-version'>
								<span class='version-current'>$current_version</span><span class='version-direction direction--next'></span><span class='version-next'>$fwupdate_version</span>
							</div>
							<div class='item-visual'>
								<img src='/img/update-visual.png'>
							</div>
							<div class='item-action'>
								<button type='submit'>Update</button>
							</div>
						</form>"
fi
echo "
						<div class='contacts'>
							<div class='contact-row' id='hide'>
								<div class='item-label'>Chat With Us Live 24/7</div>
								<div class='item-value'>
									<button class='cta-chat' onclick=\"location.href='https://$SUPPORT_SITE';\">Chat Now</button>
								</div>
							</div>
							<div class='contact-row'>
								<div class='item-label'>E-mail us</div>
								<div class='item-value'>
									<a class='blue' href='mailto:$SUPPORT_EMAIL?subject=$PNAME'>$SUPPORT_EMAIL</a>
								</div>
							</div>
							<div class='contact-row'>
								<div class='item-label'>Troubleshooting & FAQ’s</div>
								<div class='item-value'>
									<a class='blue' href='https://$SUPPORT_SITE'>$SUPPORT_SITE</a>
								</div>
							</div>"

# no rollback for Alien devices
if [ "$SHORTNAME" != "AFi-ALN-R" -a "$SHORTNAME" != "AFi-ALN-P" ]; then
	fwlink=" (<a href='sysupdate.php'>update</a>, <a href='rollback.php'>roll back</a>)"
else
	fwlink=" <a href='sysupdate.php'>(update)</a>"
fi

if [ ! "$fwupdate_status" ]; then echo "
							<div class='contact-row'>
								<div class='item-label'>Current firmware version</div>
								<div class='item-value'>
									<strong>$current_version_string</strong>$fwlink
								</div>
							</div>"
fi
echo "
							<div class='contact-row'>
								<div class='item-label'>MAC address</div>
								<div class='item-value'>
									$device_mac
								</div>
							</div>"


if [ "$FEATURE_BACKUP" = "1" -a "$(uci get ajconfd.@device_config[0].node_role)" = "router" ]; then
echo "
							<div class='contact-row'>
								<div class='item-label'>Settings</div>
								<div class='item-value'>
									<a href='backup.php'>back up</a>, <a href='restore.php'>restore</a>
								</div>
							</div>"
fi

if [ "$(uci get ajconfd.@device_config[0].node_role)" = "router" -a "$(get_setup_done)" = "1" ]; then
echo -n "
							<div class='contact-row'>
								<div class='item-label'>Other</div>
								<div class='item-value'>
									<a href='tools.php'>Tools</a>"
if [ "$BETA" = "1" -o "$DEV_MODE" = "1" ]; then
	echo ", <a href='beta.php'>Early Access options</a>"
fi

echo "
								</div>
							</div>"
fi
echo "
							<div class='contact-row'>"
if [ "$error" = "si" ]; then
	echo "
	<span id='error'>Could not download Support Info, please try again.</span>"
fi
echo "
								<form action='si.php' method='post'>
									<input type='hidden' name='token' value='$TOKEN'>
									<button class='btn' id='btnsi' type='submit' value='Download Support Info' onclick='clear_error();'>Download Support Info</button>
								</form>
							</div>"

if [ "$(stats_tool -m wifi-crash-dump 2>/dev/null)" ]; then
	echo "
							<div class='contact-row'>
								<form action='si.php' method='post'>
									<input type='hidden' name='token' value='$TOKEN'>
									<input type='hidden' name='extended' value='1'>
									<button class='btn' id='btnsie' type='submit' value='Extended' onclick='clear_error();'>Download Extended Support Info</button>
								</form>
							</div>"
fi

echo "
						</div>"

if [ "$(is_teleport)" ] && [ "$(ubus call ajconfd.Device GetPairing | grep -q 'router'; echo $?)" = "0" ] && [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" ]; then # if Teleport && is paired && isolation mode is active
echo "
						<div class='support--unpair-teleport'>
							<form action='ml-unpair.php' method='post'>
								<input type='hidden' name='token' value='$TOKEN'>
								<button type='submit' class='unpair-teleport--url' style='background-color:#FAFAFA;border:none'>Unpair Teleport from AmpliFi router</button>
							</form>
						</div>"
						#<a class='unpair-teleport--url' href='ml-unpair.php'>Unpair Teleport from AmpliFi router</a>
fi
echo "
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>
	<script>
		function clear_error() {
			span_error = document.getElementById('error');
			if (span_error !== null) {
				span_error.innerHTML=''
			}
		}
	</script>
"

if [ "$FEATURE_BACKUP" = "1" -a "$backup" = "1" ]; then
	echo "<script>setTimeout(function () { window.location = 'backup.php?d=1'; }, 100)</script>"
fi

echo "</body>"

. html-stop
