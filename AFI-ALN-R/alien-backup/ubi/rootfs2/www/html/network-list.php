#!/bin/sh

. ../inc/vars
. functions

log_debug "network-list.php"

. html-start
. head-start

if [ -z "$(is_teleport)" -a "$(get_setup_done)" = "1" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='download.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$(is_teleport)" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='ml-network-list.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING
fi

scan=$(getvar "$QUERY_STRING" "scan")

cat head.html
. head-stop

echo "<body>"

. body-nav

echo "

<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>$PNAME™ Wi-Fi Setup Wizard</h1>
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
				<div id='band-selection' class='selectnetwork-tabs clearfix hidden'>
					<a href='javascript:;' class='tab active type--2' data-band='2'>2.4Ghz</a>
					<a href='javascript:;' class='tab type--5' data-band='5'>5Ghz</a>
					<a href='javascript:;' class='tab type--E' data-band='E'>Ethernet</a>
				</div>

				<div class='base-modal-content hidden' id='connect-custom' style='padding-bottom: 35px;'>
					<div class='form-header'>
						<div class='item-title'>Other Network</div>
					</div>
					<div class='form-wrap'>
						<form method='POST' id='connect-custom-form' action='setup.php?mode=2'>
							<input type='hidden' name='token' value='$TOKEN'>
							<input type='hidden' id='custom-freq' name='freq' value=''>
							<input type='hidden'name='mode' value='2'>
							<div class='group'>
								<input type='text' name='ssid' spellcheck='false' autocomplete='off' required autofocus>
								<span class='highlight'></span>
								<span class='bar'></span>
								<label class='has-icon'><img src='img/wi-fi-icon.svg' class='icon' alt=''>Name</label>							
								<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px;'>
									<input type='hidden' id='custom_open' name='open' value='0'>
									<input class='checkbox-input' id='checkbox_open' type='checkbox'>
									<div class='checkbox-check'></div>
									<div class='checkbox-label'><span style='font-weight:normal; color:#888'>Open network</span></div>
								</div>
							</div>
							<div class='group group-variation-03'>
								<button class='btn name-your-wireless-network-btn' type='submit' style='width: 100%; text-transform: uppercase;'>Continue</button>
							</div>
						</form>
					</div>
				</div>

				<script>
					var custom_open = document.getElementById('custom_open');
					var checkbox_open = document.getElementById('checkbox_open');
					checkbox_open.addEventListener('click', function () {
						if (checkbox_open.checked) {
							custom_open.value = '1';
						} else {
							custom_open.value = '0';
						}
					});
				</script>

				<form class='base-modal-content content--ssid-list ssid-single-list' method='POST' action='setup.php?mode=2'>
				<input type='hidden' name='token' value='$TOKEN'>
				<div class='node--body'>
					<div class='node--selectnetwork'>
						<div class='selectnetwork-body'>
							<ul class='list-unstyled clearfix' id='texxxt'>
								<li id='ssids-not-found'>
									Getting wireless networks list, plese wait...
								</li>
								<li id='selectnetwork-ethernet' class='hidden'>
									<div class='ethernet-action-title'>Connect Ethernet cable to<br> Teleport WAN port</div>
									<div class='ethernet-action-figure'>
										<img src='img/connect-teleport.png'>
									</div>
								</li>
								<li id='ssids-are-loading' class='active'><img src='img/ring.gif'></li>
							</ul>
						</div>
					</div>
				</div>
				<div id='refresh-network-list-container' class='hidden'>
					<div class='node--line'></div>
					<div class='node--foot-extension extension-sm'>
						<button id='refresh-network-list' class='btn' type='button'>Refresh networks list</button>
					</div>

					<input class='supp-values' type='hidden' name='supp_freq' value=''>
					<input class='supp-values' type='hidden' name='supp_freqdual' value=''>
					<input class='supp-values' type='hidden' name='supp_open' value=''>

					</form>
				</div>
				<li id='ssid-list-item-template' class='ssid-item' style='display: none;'>
					<input class='item-ssid' type='radio' name='ssid' value=''>
					<input class='item-band' type='hidden' name='band' value=''>
					<div class='item'>
						<div class='item-name'></div>
						<div class='item-attributes'>
							<div class='item-icon item-icon--radio'></div>
							<div class='item-icon item-icon--lock'></div>
							<div class='item-icon item-icon--wifi'></div>
						</div>
					</div>
				</li>
			</div>
		</div>
	</div>
</div>
<script>
	function setSupps( obj ) {
		var supps = {};
		for ( var prop in obj ) {
			var selector = document.querySelector('input.supp-values[name=\"' + prop + '\"]');
			if ( selector ) {
				selector.value = obj[prop];
				supps[prop] = obj[prop];
			}
		}
		return supps;
	}
</script>
<script src='js/clink.js'></script>
<script src='js/app.js?v=5'></script>
<script>
	amplifiJsUtil.grip(':/PopulateNetworkList', [ { type: 'runtime', args: { url: '/scanlist.php', send: 'scan=$scan', fallback: ':/refreshNetworkList', timeout: 20000 } } ] ).dispatch();
</script>
"

echo "</body>"

. html-stop
rm -f "$SETUPSSID"
