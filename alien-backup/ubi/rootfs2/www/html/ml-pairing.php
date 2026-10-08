#!/bin/sh

. ../inc/vars
. functions

log_debug "ml-pairing.php"

. html-start
. head-start

if [ ! "$(is_teleport)" ] || [ "$(ubus call ajconfd.Device GetPairing | grep -q 'router'; echo $?)" = "0" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
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
					<div class='node--pair-section'>
						<div class='item-title'>Pair Teleport</div>
						<div class='item-description'>Pair Teleport using the app. Make sure app and router firmware are up to date.</div>
						<div class='item-visual'>
							<img src='/img/pair-visual.png'>
						</div>            
						<div class='item-getapp'>
							<div class='item-getapp-title'>Don’t have AmpliFi WiFi app? Get it here:</div>
							<div class='item-getapp-list'>
								<img src='img/App-Store-Icon.png'>
								<img src='img/google_play_icon.png'>
							</div>              
						</div>
					</div>
				</div>

			</div>
		</div>
	</div>
</div>

<script src='js/clink.js'></script>
<script src='js/app.js?v=4'></script>
<script>
	function processState(xhttp) {
		stateTimer();
		offlineTimer();
		try {
			var arr = JSON.parse(xhttp.responseText);
			if (teleport_status != arr.status) {
				if (arr.status > 99) {
					setTimeout('window.location.href = \'index.php\'', 10000);
				}
			teleport_status = arr.status;
			}
		} catch (e) {
			return false;
		}
	}

	var teleport_status = 0;
	offlineTimerInterval = 60000;
	setTimeout('stateTimer()', 1);

</script>

</body>"

. html-stop
