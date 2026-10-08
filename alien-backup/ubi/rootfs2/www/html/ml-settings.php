#!/bin/sh

. ../inc/vars
. functions
. html-start
. head-start

if [ ! "$(is_teleport)" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

cat head.html
. head-stop

echo "<body>"

. body-nav

teleport_isolation_mode="$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)"
[ "$teleport_isolation_mode" = "Full" ] && teleport_isolated=1
[ "$teleport_isolation_mode" = "CaptiveWan" ] && teleport_isolated=1
[ "$(uci get ajconfd.@device_config[0].ml_connectivity_mode)" = "Wireless" ] && mode="Wi-Fi" || mode="Ethernet cable"

log_debug "ml-settings.php"
teleport_state

echo "
<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>AmpliFi Settings</h1>
			</div>
		</div>
	</div>
</div>
<div class='container'>
	<div class='row'>
		<div class='col-app-container col-xs-12'>
			<div class='base-modal node--no-padding'>

				<div class='base-modal-header teleport'>
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

				<form class='base-modal-content' method='POST'>
					<input type='hidden' name='token' value='$TOKEN'>

					<div class='node--body'>"
if [ "$teleport_isolated" = "1" ]; then
	echo "
						<div class='item-title generic-error'>
							No internet connection detected
						</div>
						<div class='item-message'>
							In order to make Teleport work - you<br> have to connect it to the internet.
							<br>
							<br>
							<button class='btn' formaction='ml-network-list.php'>Setup Teleport</button>
						</div>
					</div>"
else
	echo "
					<div class='item-title' style='color: #56F28A !important; padding-bottom: 30px'>
						Everything is great!
					</div>
					<div class='item-title text-type-1' style='padding-bottom: 30px'>Teleport is connected to the internet<br>using $mode</div>
					<div class='node--foot foot-type-1'>
						<div class='node--sendwidth sendwidth-type-1'>
							<div class='item-title text-type-1'>Change connection settings</div>
							<div class='item-list'>
								<ul class='list-unstyled clearfix'>
									<li>
										<span class='item'>
											<i class='item-icon icon--wifi'></i>
											<span class='item-name'>Setup</span>
											<button value='1' name='configure' formaction='connect.php'></button>
										</span>
									</li>
								</ul>
							</div>
						</div>
					</div>
						"
fi
echo "
				</form>
			</div>
		</div>
	</div>
</div>

<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>
<script>

function processState(xhttp) {
	stateTimer();
	offlineTimer();
	try {
		var arr = JSON.parse(xhttp.responseText);
		teleport_status = arr.status;
	} catch (e) {}
}

offlineTimerInterval = 20000;

stateTimer();
offlineTimer();

</script>
"

echo "</body>"

. html-stop
