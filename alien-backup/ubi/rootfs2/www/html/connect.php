#!/bin/sh

. ../inc/vars
. functions

. html-start
. head-start

validate_and_prepare() {
	if [ "$ssid" ]; then
		conn_type="wifi"
	elif [ "$(wan_addr)" -a "$(is_teleport)" ]; then
		conn_type="eth"
	elif [ "$eth" ]; then
		conn_type="eth"
	fi
}

if [ -z "$(is_teleport)" -a "$(get_setup_done)" = "1" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='download.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING

	email=$(getvar "$QUERY_STRING" "email")
	areacode=$(getvar "$QUERY_STRING" "areacode")
	phone=$(getvar "$QUERY_STRING" "phone")
	pin=$(getvar "$QUERY_STRING" "pin")
	eth=$(getvar "$QUERY_STRING" "eth")
	configure=$(getvar "$QUERY_STRING" "configure")
	admpassword=$(getvar "$QUERY_STRING" "admpassword")
fi

if [ -s "$SETUPFILE" ]; then
	QUERY_STRING="$(cat $SETUPFILE)"

	freq=$(getvar "$QUERY_STRING" "freq")
	ssid=$(getvar "$QUERY_STRING" "ssid")
	wifipassword=$(getvar "$QUERY_STRING" "wifipassword")
	samepass=$(getvar "$QUERY_STRING" "samepass")
	timezone=$(getvar "$QUERY_STRING" "timezone")
	open=$(getvar "$QUERY_STRING" "open")
	unicode_ssid=$(getvar "$QUERY_STRING" "uss") # unicode ssid
	unicode_password=$(getvar "$QUERY_STRING" "uwp") # unicode password
	admpassword=$(getvar "$QUERY_STRING" "admpassword")
	mode=$(getvar "$QUERY_STRING" "mode")
fi

[ "$ssid" ] && log_debug "connect.php \"$freq\" \"$ssid\" \"$open\" \"***\" \"$unicode_ssid\" \"***\"" || log_debug "connect.php"

validate_and_prepare

if [ "$(is_teleport)" ] && [ ! "$(uci get ajconfd.@device_config[0].ml_skip_setup_confirmation 2>/dev/null)" ]; then
	teleport_skip_setup_confirmation=1
fi

if [ "$configure" ]; then
	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "connect.php configure bad token"
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
		. head-stop
		. html-stop
		exit
	fi
fi

if [ -z "$conn_type" -a -z "$configure" -a ! -s "$WIFIPASSWORDCHECKLOCK" -a ! -s "$TELEPORTAP" -a ! "$teleport_skip_setup_confirmation" ] || [ ! "$(is_teleport)" -a "$open" = "1" -a ! "$admpassword" ]; then
	rm -f "$SETUPFILE"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

ptype="$(get_ptype)"

cat head.html
. head-stop

echo "<body>
<script src='js/clink.js'></script>
<script src='js/app.js?v=4'></script>"

. body-nav

if [ "$ptype" = "Router" ]; then
echo "\
	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1>Connect to Your New Wi-Fi Network!</h1>
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
							<h3 class='green'>Setup will complete shortly!</h3>
							<p>Go to your Wi-Fi settings and connect to your new wireless network:</p>
							<div class='blue-wrap'>$ssid</div>
							<input class='cta-blocked' id='continue' type='button' class='button_active' onclick=\"location.href='index.php';\" value='Continue in ...'>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>
<script type='text/javascript'>
var seconds = 25;
document.getElementById('continue').disabled = true;
setTimeout(function(){
	button = document.getElementById('continue');
	button.disabled = false;
	button.className = 'cta-main-center';
	button.value = 'Continue';
}, (seconds+2) * 1000);
function changeButton() {
	document.getElementById('continue').value = 'Continue in ' + seconds + ' sec.';
	seconds--;
	if (seconds < 0) {
		clearInterval(interval);
	}
}
var interval = setInterval(changeButton, 1000);
</script>
"
elif [ "$(is_teleport)" ]; then
	if [ -s "$TELEPORTAP" ]; then
		local_ssid=$(sed -n 1p "$TELEPORTAP")
		local_wifipassword=$(sed -n 2p "$TELEPORTAP")
		local_ssid_unicode=$(sed -n 3p "$TELEPORTAP")
		log_debug "connect.php TELEPORTAP $local_ssid ***"
		rm "$TELEPORTAP"
	fi
	if [ "$local_ssid" -a "$local_wifipassword" ]; then
		ssid_txt="$local_ssid_unicode"
	else
		ssid_txt=$(uci show ajconfd.@site_config[0].wifi_per_radio_user_ssid | sed -e "s/'\\\''/QZQZQZQZ/g" | cut -d"'" -f2 | sed -e "s/QZQZQZQZ/'/g")
		[ ! "$ssid_txt" ] && ssid_txt="Teleport"
	fi

echo "\
	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1>Setting up Teleport</h1>
				</div>
			</div>
		</div>
	</div>
	<div class='container'>
		<div class='row'>
			<div class='col-app-container col-xs-12'>
				<div class='base-modal' style='padding-bottom: 0px;'>
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
							  WiFi with SSID <span class='highlighted-text--green ssid-name-value' >$ssid_txt</span> will be active shortly
							</div>
							<div class='item-message'>
							  Teleport access point will restart - during that time you may be disconnected from Teleport. Wait a few seconds and connect back.
							</div>
					  </div>

						<div class='node--loading-notice notice-type-1' id='loading-notice'>
							<div class='item-status'></div>
							<div class='item-title'>Teleport access point <strong>will restart</strong> during the setup</div>
							<div class='item-description'>Setting up SSID...</div>
						</div>"

if [ ! "$configure" ]; then
	echo "
						<div class='form-footer'>
							<div class='item-icon'></div>
							<div class='item-description' style='height: auto; padding-bottom: 15px;'>Setup will <span style='font-weight:bold'>resume after restart.</span> You may have to reconnect manually, especially if using guest portal authentication.</div>
						</div>"
fi
echo "
					</div>

				</div>
			</div>
		</div>
	</div>

"

teleport_state

echo "<script type='text/javascript'>"

if [ "$configure" ]; then
	# enter isolation mode
	echo "
	setTimeout('window.location.href = \\'http://amplifi.lan\\';', 45000);
	var task = [
		[0, 'Entering setup mode'],
		[6, 'Reconfiguring Teleport'],
		[13, 'Setting up SSID'],
		[22, 'Please connect to SSID \"$ssid_txt\"']
	];"

elif [ -s "$WIFIPASSWORDCHECKLOCK" ]; then
	# background script
	echo "
	var task = [
		[0, 'Reconfiguring Teleport'],
		[10, 'Checking password'],
		[10, 'Connecting to upstream AP'],
		[15, 'Setting up SSID'],
		[18, 'Please connect to SSID \"$ssid_txt\"']
	];"

elif [ "$conn_type" = "wifi" ]; then
	# connect upstream AP
	echo "
	var task = [
		[0, 'Reconfiguring Teleport'],
		[8, 'Connecting to upstream AP'],
		[15, 'Setting up SSID'],
		[20, 'Please connect to SSID \"$ssid_txt\"']
	];"

elif [ "$(cat /sys/class/net/eth0/carrier)" = "1" ] && [ "$(ip route | grep default)" ] && [ "$local_ssid" -a "$local_wifipassword" ]; then
		# wired connection and set custom SSID
	echo "
	var task = [
		[0, 'Reconfiguring Teleport'],
		[10, 'Setting up SSID'],
		[18, 'Please connect to SSID \"$ssid_txt\"']
	];"

fi


echo "

	function processState(xhttp) {
			stateTimer();
			// offlineTimer();
		try {
			var arr = JSON.parse(xhttp.responseText);
			if (teleport_status != arr.status) {
				if (arr.status < 120) {
					window.location.href = 'index.php';
				}
				else if (arr.status == 120) {
					window.location.href = 'http://amplifi.com/';
				}
				else if (arr.status == 190) {
					window.location.href = 'setup.php?ssid='+arr.message+'&invalid=password';
				}
				else if (arr.status == 601) {
					window.location.href = 'http://'+arr.message;
				}
				else if (arr.status != 130 && arr.status != 401) {
					window.location.href = 'ml-setup-progress.php';
				}
			}
		} catch (e) {
			return false;
		}
	}

	var teleport_status=$teleport_status_id;

	offlineTimerInterval = 60000;

	setTimeout('stateTimer()', 10000);
	// offlineTimer();

	function setDescription(description) {
		amplifiJsUtil.vault().loadingNotice({
		title: 'Teleport access point <strong>will restart</strong> during the setup',
			description: description
		})
	}

	var delayTotal = 0;
	for (index = 0; index < task.length; ++index) {
		delayTotal += task[index][0];
		setTimeout('setDescription(\'' + task[index][1] + '\');', delayTotal * 1000);
	}

	//setTimeout('setBarProgress(100, \'Waiting...\');', (index + 1) * 1000);

</script>
"

else
echo "\
	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1>Connect to Your Wi-Fi Network!</h1>
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
							<h3 class='green'>Setup will complete shortly!</h3>
							<p>Go to your Wi-Fi settings and connect to your wireless network:</p>
							<div class='blue-wrap'>$ssid</div>
							<input class='cta-blocked' id='continue' type='button' class='button_active' onclick=\"location.href='index.php';\" value='Continue in ...'>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>
<script type='text/javascript'>
	var seconds = 15;
	document.getElementById('continue').disabled = true;
	setTimeout(function(){
		button = document.getElementById('continue');
		button.disabled = false;
		button.className = 'cta-main-center';
		button.value = 'Continue';
	}, (seconds+2) * 1000);
	function changeButton() {
		document.getElementById('continue').value = 'Continue in ' + seconds + ' sec.';
		seconds--;
		if (seconds < 0) {
			clearInterval(interval);
		}
	}
	var interval = setInterval(changeButton, 1000);
	setTimeout('redirectWhenOnline(\'index.php\',300000)', 20000); waitState = 2;
	</script>
"
fi

echo "</body>"

. html-stop

[ "$unicode_ssid" ] && ssid=$unicode_ssid
[ "$unicode_password" ] && wifipassword=$unicode_password

if [ "$(is_teleport)" ]; then
	if [ "$configure" ]; then
		echo 'configure' > $CONFIGMODELOCK
		ubus call ajconfd.Device SetDeviceConfig '{"config": { "MlIsolationMode": "Full", "MlConnectivityMode":"Wired" }}' >/dev/null
	elif [ -s "$WIFIPASSWORDCHECKLOCK" ]; then
		rm -f $CONFIGMODELOCK
	else
		if [ "$conn_type" = "wifi" ]; then
			sh "../inc/backgound-worker.sh" "config_teleport_wireless" "$ssid" "$wifipassword" "$freq" "$open" "$local_ssid" "$local_wifipassword" &
		elif [ "$(cat /sys/class/net/eth0/carrier)" = "1" ] && [ "$(ip route | grep default)" ]; then	# Ethernet cable connected && is default route
			sh "../inc/backgound-worker.sh" "config_teleport_wired" "$local_ssid" "$local_wifipassword" &
		fi
		rm -f $CONFIGMODELOCK
	fi
elif [ "$ptype" = "MeshPoint" -o "$mode" = "2" ]; then
	sh "../inc/backgound-worker.sh" "send_trace" "setup_complete" >/dev/null 2>&1 &
	if [ ! "$admpassword" ]; then
		[ "$wifipassword" ] && admpassword="$wifipassword" || admpassword="ubnt"
	fi
	echo "initial setup: ssid=$ssid, pass=$wifipassword, tz=$timezone, freq=$freq" > $SETUPFILE.done
	initial_setup_mp "$ssid" "$wifipassword" "$admpassword" "$timezone" "$freq" "$open"
elif [ "$ptype" = "PowerBox" ]; then
	sh "../inc/backgound-worker.sh" "send_trace" "setup_complete" >/dev/null 2>&1 &
	if [ ! "$admpassword" ]; then
		[ "$wifipassword" ] && admpassword="$wifipassword" || admpassword="ubnt"
	fi
	echo "initial setup: ssid=$ssid, pass=$wifipassword, tz=$timezone, freq=$freq" > $SETUPFILE.done
	initial_setup_pb "$ssid" "$wifipassword" "$admpassword" "$timezone" "$open"
else
	sh "../inc/backgound-worker.sh" "send_trace" "setup_complete" >/dev/null 2>&1 &
	if [ "$samepass" = "1" ]; then
		echo "initial setup: ssid=$ssid, pass=$wifipassword, tz=$timezone" > $SETUPFILE.done
		initial_setup_r "$ssid" "$wifipassword" "$wifipassword" "$timezone"
	else
		echo "initial setup: ssid=$ssid, pass=$wifipassword, admpass=$admpassword tz=$timezone" > $SETUPFILE.done
		initial_setup_r "$ssid" "$wifipassword" "$admpassword" "$timezone"
	fi
fi

rm -f "$SETUPFILE"

/etc/init.d/fwupdate restart
