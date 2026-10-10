#!/bin/sh

. ../inc/vars
. functions

log_debug "update.php"

ptype="$(get_ptype)"

. html-start
. head-start

if [ "$REQUEST_METHOD" != "POST" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='fwupdate.php'\" />"
	. head-stop
	. html-stop
	exit
fi

read QUERY_STRING

post_token=$(getvar "$QUERY_STRING" "token")
if [ "$post_token" != "$TOKEN" ]; then
	log_debug "update.php bad token"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='fwupdate.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$(is_teleport)" -a "uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null" ]; then
	upgrade=$(getvar "$QUERY_STRING" "upgrade")
	if [ "$upgrade" = "1" ]; then
		ubus call firmware upgrade 2>/dev/null
		upgrading=1
	fi

elif [ "$SESSION" = "invalid" -a "$(get_setup_done)" = "1" ]; then
	set_returnurl "fwupdate.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit

elif [ "$REMOTE_ADDR" != "$(cat /tmp/webui.fwupdate)" -a "$(get_setup_done)" = "1" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='fwupdate.php'\" />"
	. head-stop
	. html-stop
	exit
fi

[ "$(is_teleport)" ] && device_name="Teleport" || device_name="Device"
[ "$SHORTNAME" = "AFi-ALN-R" -o "$SHORTNAME" = "AFi-ALN-P" ] && update_time=2 || update_time=3

cat head.html
. head-stop

echo "<body>"

. body-nav

echo "\
<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>Firmware Update</h1>
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

			<div class='base-modal-content content--restart-notice' id='speedtest-teleport-restart-notice' style='padding-bottom: 0px;'>
				<div class='node--body' class='hidden' style='display: none'>
					<div class='item-title'>
					  Updating firmware
					</div>
					<div class='item-message'>
					  $device_name will restart. Estimated update time is $update_time minutes.
					</div>
				</div>

				<div class='node--loading-notice notice-type-1' id='loading-notice'>
					<div class='item-status'></div>
					<div class='item-title'>Updating firmware</div>
					<div class='item-description'>$device_name will restart. Estimated update time is 3 minutes.</div>
				</div>
				<div class='form-footer'>
					<div class='item-icon icon--type-2'></div>
					<div class='item-description' style='height: auto; padding-bottom: 15px;'>After restart you may have to <span style='font-weight:bold'>reconnect manually</span>.</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script type='text/javascript'>
	setTimeout('window.location.href = \'$(get_homeurl)\';', $update_time*60000+30000);
	setTimeout('redirectWhenOnline(\'$(get_homeurl)\',300000)', 30000);
</script>
<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>
</body>
"

if [ ! "$upgrading" ]; then
	afiupgrade "$FWFILE" > /dev/null
	if [ $? -ne 0 ]; then
		echo "
		<script>
			window.location.href = 'index.php'
		</script>

		<div class='base-modal-content'>
			<div class='success-message'>
				<h3 class='red'>Update failed</h3>
			</div>
		</div>"
	fi
fi

. html-stop
