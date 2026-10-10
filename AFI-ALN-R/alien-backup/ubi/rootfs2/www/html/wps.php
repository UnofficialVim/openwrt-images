#!/bin/sh

. ../inc/vars
. functions

log_debug "wps.php"

if [ "$(uci get ajconfd.@device_config[0].node_role)" != "router" -o "$(get_setup_done)" != "1"  ]; then
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" ]; then
	set_returnurl "wps.php"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

r=$(getvar "$QUERY_STRING" "r")

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING

	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "wps.php bad token"
		. html-start
		. head-start
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='wps.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	d=$(getvar "$QUERY_STRING" "do")

	if [ "$d" = "enable" ]; then
		result="$(ubus call ajconfd.ClusterNode WpsPushButtonActivate 2>/dev/null)"
	elif [ "$d" = "disable" ]; then
		result="$(ubus call ajconfd.ClusterNode WpsPushButtonCancel 2>/dev/null)"
	fi
fi

json="$(ubus call ajconfd.ClusterNode WpsPushButtonStatus)" 2>/dev/null
json_load "$json" 2>/dev/null
json_get_var wps_active "active" 2>/dev/null
json_get_var wps_ok "ok" 2>/dev/null
json_get_var wps_timeout "timeout" 2>/dev/null
json_get_var wps_mac "mac" 2>/dev/null

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
					<h1>Wi-Fi Protected Setup</h1>
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
						<div class='success-message'>"							
if [ "$wps_active" = "1" ]; then
	echo "
							<div>
								<p style='color:#747474'>WPS enabled for <span id='timeout'>$wps_timeout</span> seconds</p>
							</div>
							<form action='wps.php' method='post'>
								<input type='hidden' name='do' value='disable'>
								<input type='hidden' name='token' value='$TOKEN'>
								<input class='cta-main-center' type='submit' value='Disable WPS'/>
							</form>

							<script>
								setTimeout('window.location.href = \'wps.php?r=1\';', 10000);
								var timeout = $wps_timeout;
								var interval = setInterval(function() {
									timeout--;
									document.getElementById('timeout').innerHTML = timeout;									
									if (timeout < 1) {
										clearInterval(interval);
										setTimeout('window.location.href = \'wps.php\';', 1000);
									}
								}, 1000);
							</script>
							"
elif [ "$r" = "1" -a "$wps_mac" ]; then
	echo "
							<div>
								<p style='color:#747474'>Device with MAC address <span style='font-weight: 300; color: #009FE2'>$wps_mac</span> added to network</p>
							</div>
							<form action='wps.php' method='post'>
								<input type='hidden' name='do' value='enable'>
								<input type='hidden' name='token' value='$TOKEN'>
								<input class='cta-main-center' type='submit' value='Enable WPS'/>
							</form>"
else
	echo "
							<div>
								<p style='color:#747474'>Wi-Fi Protected Setup (WPS) allows to add wireless devices to network without entering password</p>
							</div>
							<form action='wps.php' method='post'>
								<input type='hidden' name='do' value='enable'>
								<input type='hidden' name='token' value='$TOKEN'>
								<input class='cta-main-center' type='submit' value='Enable WPS'/>
							</form>"
fi

echo "
						</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script src='js/clink.js'></script><script src='js/app.js?v=4'></script></body>"

. html-stop
