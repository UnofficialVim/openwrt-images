#!/bin/sh

. ../inc/vars
. functions

log_debug "ml-unpair.php"

. html-start
. head-start

if [ "$REQUEST_METHOD" != "POST" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

read QUERY_STRING

post_token=$(getvar "$QUERY_STRING" "token")
mac_esc=$(getvar "$QUERY_STRING" "mac")
offline=$(getvar "$QUERY_STRING" "offline")
[ "$offline" = "1" ] || offline=0

if [ "$post_token" != "$TOKEN" ]; then
	log_debug "ml-pair.php bad token"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$mac_esc" -a "$(get_ptype)" = "Router" ]; then

	if [ "$(validate_mac_addr "$mac_esc")" != "valid" ]; then
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	if [ "$SESSION" = "invalid" ]; then
		#set_returnurl "ml-unpair.php?m=$mac_esc&offline=$offline"
		set_returnurl "index.php"
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	json="$(ubus call ajconfd.Device GetPairing 2>/dev/null)"
	json_load "$json" > /dev/null
	json_select "extenders" > /dev/null
	json_get_keys keys
	for k in $keys; do
		json_select $k
		json_get_var platform "platform"
		json_get_var user "user"
		if [ "$platform" = "AFi-T" -o "$user" = "1" ]; then
			if [ "$k" = "$mac_esc" ]; then
				mac=$(echo "$k" | tr "_" ":")
				if [ "$platform" = "AFi-T" ]; then
					platform_name='Teleport'
				else
					platform_name='Mesh Point'
				fi
			fi
		fi
		json_select
		json_select "extenders"
	done
fi

if [ "$(get_ptype)" = "Router" -a ! "$mac" ] && ( [ ! "$(is_teleport)" ] || [ "$(ubus call ajconfd.Device GetPairing | grep -q 'router'; echo $?)" != "0" ] || [ ! "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" ] ); then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi


cat head.html
. head-stop

echo "<body>"

. body-nav

echo "
<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>Welcome to AmpliFi</h1>
			</div>
		</div>
	</div>
</div>
<div class='container'>
	<div class='row'>
		<div class='col-app-container col-xs-12'>
			<div class='base-modal node--no-padding'>
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

					<form class='node--unpair-teleport' method='post'>
						<input type='hidden' name='token' value='$TOKEN'>

						<div class='item-content'>
							<div class='item-icon'>
								<img src='img/exc-mark-01.png' />
							</div>
							<div class='item-title'>Remove Teleport from the AmpliFi system</div>"
if [ "$offline" ]; then
	text_1="This will unpair the Teleport and it will lose connection with your AmpliFi system"
	text_2="You may close this window"
else
	text_1="This will completely reset the Teleport and it will lose connection with your AmpliFi system"
	text_2="Teleport will restart shortly, you may close this window"
fi

echo "
							<div class='item-description'>$text_1</div>
						</div>
						<div class='item-actions clearfix'>
							<div class='action--keep'>
								<button class='btn' onclick=\"location.href='index.php'; return false;\">Keep using Teleport</button>
							</div>
							<div class='action--or'>or</div>
							<div class='action--remove'>
								<button class='btn' id='unpair-button' onclick='unpair(); return false;'>Remove Teleport</button>
							</div>
						</div>

					</form>

					<div class='node--unpair-section hidden'>
						<div class='item-visual'>
							<img src='/img/unpair-visual.png'>
						</div>
						<div class='item-title'>Unpairing successful</div>
						<div class='item-description'>$text_2</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>

<script>
	function unpair() {
		var btn = document.getElementById('unpair-button');

		btn.classList.add('btn-is-loading');
		btn.disabled = true;
		btn.innerText = 'Please wait...';

		var xhttp;
		xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function() {
			if (this.readyState == 4) {
				if (this.status == 200) {
					if (xhttp.responseText.trim() == '0') {
						document.querySelector('.node--unpair-section').classList.remove('hidden');
						document.querySelector('.node--unpair-teleport').classList.add('hidden');
					} else {
						btn.classList.remove('btn-is-loading');
						btn.disabled = false;
						btn.innerText = 'Remove Teleport';
					}
				}
				else if ( this.status == 0 ) {
					setTimeout('unpair();', 250);
				}
			}
		};

		xhttp.open('POST', 'ml-async.php', true);
		xhttp.timeout = 15000;
		xhttp.send( 'do=unpair&m=$mac&token=$TOKEN' );

		return false;
	}

</script>
"

echo "</body>"

. html-stop
