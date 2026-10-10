#!/bin/sh

. ../inc/vars
. functions

log_debug "tools.php"

if [ "$(uci get ajconfd.@device_config[0].node_role)" != "router" -o "$(get_setup_done)" != "1"  ]; then
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" ]; then
	set_returnurl "tools.php"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING

	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "tools.php bad token"
		. html-start
		. head-start
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='tools.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	d=$(getvar "$QUERY_STRING" "do")

	if [ "$d" = "remove_account" ]; then
		. html-start
		. head-start
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
		. head-stop
		. html-stop
		delete_webrtc 2>/dev/null
		exit
	fi
fi

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
					<h1>Tools</h1>
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

					<div class='base-modal-content'>
						<div class='contacts'>

							<div class='contact-row'>
								<div class='item-label'>WPS</div>
								<div class='item-value'>
									<a href='wps.php'>Wi-Fi Protected Setup</a>
								</div>
							</div>"

if [ "$(uci -q get ajconfd.@site_config[0].webrtc_owner_id)" ]; then
	echo "

							<div class='contact-row'>
								<div class='item-label'>Remote management access</div>
								<div class='item-value'>
									<form id='remove_account' action='tools.php' method='post'>
										<input type='hidden' name='do' value='remove_account' />
										<input type='hidden' name='token' value='$TOKEN'>
										<a href='#' onclick='if (confirm(\"Are you sure?\")) document.getElementById(\"remove_account\").submit();'>Remove remote management access to the router</a>
									</form>
								</div>
							</div>"
fi

echo "

						</div>
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
