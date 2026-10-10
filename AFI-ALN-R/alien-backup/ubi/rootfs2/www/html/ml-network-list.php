#!/bin/sh

. ../inc/vars
. functions

. html-start
. head-start

if [ "$(is_teleport)" ] && [ "$(ubus call ajconfd.Device GetPairing | grep -q 'router'; echo $?)" = "0" ] && [ "$(uci get ajconfd.@device_config[0].ml_isolation_mode 2>/dev/null)" ]; then # if paired Teleport in isolation mode
	echo -n ''
else
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING
fi

active_band=$(uci get ajconfd.@device_config[0].wifi_active_band)

scan=$(getvar "$QUERY_STRING" "scan")
[ "$scan" = "2" ] || scan=

log_debug "ml-network-list.php"
teleport_state
[ "$teleport_status_id" -eq 101 ] && configure_teleport_ap=1 || configure_teleport_ap=0

cat head.html
. head-stop

echo "<body>"

. body-nav

echo "

<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>"
					get_setup_title
echo "</h1>
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
				"
				if [ "$configure_teleport_ap" -eq 1 ]; then
					echo "<div id='band-selection' class='selectnetwork-tabs clearfix hidden' style='display: none'>"
				else
					echo "<div id='band-selection' class='selectnetwork-tabs clearfix hidden'>"
				fi
				echo "
					<a href='javascript:;' class='tab active type--2' data-band='2'>2.4Ghz</a>
					<a href='javascript:;' class='tab type--5' data-band='5'>5Ghz</a>
					<a href='javascript:;' class='tab type--E' data-band='E'>Ethernet</a>
				</div>

				"
				if [ "$configure_teleport_ap" -eq 1 ]; then
					echo "<form class='base-modal-content content--ssid-list' method='POST' action='setup.php' style='display: none'>"
				else
					echo "<form class='base-modal-content content--ssid-list' method='POST' action='setup.php'>"
				fi
				echo "&nbsp;
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
									<li id='ssids-are-loading' class='active'>
										<div class='app-t'>
											<div class='app-tr'>
												<div class='app-tc'>
													<div class='loading-progress-bar'>
														<div class='progress-bar--progress'>
															<div class='bar-fill' style='width: 0%;'></div>
														</div>
														<div class='progress-bar--action'>Retrieving wireless networks list</div>
														<div class='progress-bar--duration'>Loading...</div>
													</div>
												</div>
											</div>
										</div>
									</li>
								</ul>
							</div>
						</div>
					</div>
					<div id='refresh-network-list-container' class='hidden'>
						<div class='node--line'></div>
						<div class='node--foot-extension extension-sm'>
							<button id='refresh-network-list' class='btn' type='button' onClick='progressBarReset()'>Refresh networks list</button>
						</div>
					</div>

					<input class='supp-values' type='hidden' name='supp_freq' value=''>
					<input class='supp-values' type='hidden' name='supp_freqdual' value=''>
					<input class='supp-values' type='hidden' name='supp_open' value=''>
					<input type='hidden' name='token' value='$TOKEN'>
				</form>

				<div class='base-modal-content hidden' id='name-your-wireless-network-notice' style='padding-bottom: 35px;'>
					<div class='row'>
						<div class='col-app-container col-xs-12'>
							<div class='base-22 node--no-padding'>
								<div class='base-modal-content'>
									<div class='node--pair-section'>
										<div class='item-title'>Recommendation</div>
										<div class='item-description'>Please set up Teleport using the app.</div>
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
										<div class='group group-variation-03' style='margin-left:25%;margin-right:25%;margin-top:30px;'>
											<button class='btn' style='width: 100%; text-transform: uppercase;' onclick='document.getElementById(\"name-your-wireless-network-notice\").classList.add(\"hidden\");document.getElementById(\"name-your-wireless-network\").classList.remove(\"hidden\");'>Continue anyway</button>
										</div>
									</div>
								</div>

							</div>
						</div>
					</div>
				</div>

				<div class='base-modal-content hidden' id='connect-custom' style='padding-bottom: 35px;'>
					<div class='form-header'>
						<div class='item-title'>Other Network</div>
					</div>
					<div class='form-wrap'>
						<form method='POST' id='connect-custom-form' action='setup.php'>
							<div class='group'>
								<input type='text' name='ssid' spellcheck='false' autocomplete='off' required autofocus onkeyup='getWirelessNamingValues(true);'>
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
							<input type='hidden' name='token' value='$TOKEN'>
							<input type='hidden' id='custom-freq' name='freq' value=''>
						</form>
					</div>
				</div>

				<script src='js/clink.js'></script><script src='js/app.js?v=6'></script>
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

				<div class='base-modal-content hidden' id='name-your-wireless-network' style='padding-bottom: 35px;'>
					<div class='form-header'>
						<div class='item-icon'></div>
						<div class='item-title'>Secure Teleport</div>
						<div class='item-description'>Choose name and password for Teleport's wireless network</div>
					</div>
					<div class='form-wrap'>
						<form method='POST' id='name-your-wireless-network-form' onSubmit='getWirelessNamingValues(); return false;'>
							<div class='group'>
								<input type='text' name='ssid' spellcheck='false' autocomplete='off' required autofocus onkeyup='getWirelessNamingValues(true);'>
								<span class='highlight'></span>
								<span class='bar'></span>
								<label class='has-icon'><img src='img/wi-fi-icon.svg' class='icon' alt=''>Name Your Wireless Network</label>
							</div>
								<div class='group'>
								<input type='password' name='wifipassword' spellcheck='false' autocomplete='off' required onkeyup='getWirelessNamingValues(true);'>
								<span class='highlight'></span>
								<span class='bar'></span>
								<label class='has-icon'><img src='img/key-icon.svg' class='icon' alt=''>Create Your Wireless Password</label>
							</div>
							<div class='group group-variation-03'>
								<button class='btn name-your-wireless-network-btn' type='submit' style='width: 100%; text-transform: uppercase;'>Continue setup</button>
							</div>
							<input type='hidden' name='token' value='$TOKEN'>
						</form>
					</div>
				</div>

				<li id='ssid-list-item-template' class='ssid-item' style='display: none;'>
					<input class='item-ssid' type='radio' name='ssid' value=''>
					<input class='item-band' type='hidden' name='band' value=''>
					<div class='item'>
						<div class='item-name'></div>
						<div class='item-attributes'>
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
	'use strict'
	var configureTeleportAp = $configure_teleport_ap;
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

<script type='text/javascript'>

	function processState(xhttp) {
		stateTimer();
		offlineTimer();
		try {
			var arr = JSON.parse(xhttp.responseText);
			teleport_status = arr.status;
			if (arr.step && arr.state) {
				if (prev_step == arr.step && prev_state == arr.state) {
					if (tDiff(0) > 15000 && teleport_status == 105) {
						amplifiJsUtil.vault().triggerEthTab({
							connected: 'checkit', message: 'No internet detected, please remove Ethernet cable'
						})
					}
				}
				else {
					if (teleport_status == 401) {
						window.location.href = 'connect.php';
					}
					else if (arr.step > 1 || (arr.step == 1 && arr.state != 3)) {
						if (teleport_status == 105) {
							amplifiJsUtil.vault().triggerEthTab({
								connected: true, message: 'Checking Ethernet cable connection...'
							})
						}
						else {
							window.location.href = 'ml-setup-progress.php';
						}
					}
					else {
						if (teleport_status == 100 && teleport_status_prev == 105) {
							amplifiJsUtil.vault().triggerEthTab({
								connected: false, message: 'Connect Ethernet cable'
							})
						}
					}
					tDiff(1);
					prev_step =  arr.step;
					prev_state = arr.state;
					teleport_status_prev = teleport_status;
				}
			}
		} catch (e) {
			return false;
		}
	}

	var prev_step = $teleport_setup_step;
	var prev_state = $teleport_setup_state;
	var teleport_status = teleport_status_prev = $teleport_status_id;

	stateTimer();
	offlineTimer();

	if (teleport_status == 105) {
		amplifiJsUtil.vault().triggerEthTab({
			connected: true, message: 'Checking Ethernet cable connection...'
		})
	}


	function isASCII( str, ext ) {
		return ( ext ? /^[\x00-\xFF]*$/ : /^[\x00-\x7F]*$/).test( str );
	}

	function stringByteSize( str ) {
	  var strLength = str.length;
	  for ( var i = str.length - 1; i >= 0; i-- ) {
		var codeAtIdx = str.charCodeAt( i );
		if ( codeAtIdx > 0x7f && codeAtIdx <= 0x7ff ) {
				strLength++;
			} else if (codeAtIdx > 0x7ff && codeAtIdx <= 0xffff) {
				strLength += 2;
			}
		if ( codeAtIdx >= 0xDC00 && codeAtIdx <= 0xDFFF) {
				i--;
			};
	  }
	  return strLength;
	}

	function validatesSSID( str ) {
		var pass = false;
		if ( stringByteSize( str ) <= 32 ) {
			if ( str.length >= 1 && str.length <= 32 ) {
				pass = true;
			}
		}
		return pass;
	}

	function validatesWPA( str ) {
		var pass = false;
		if ( isASCII( str, false ) ) {
			if ( str.length >= 8 && str.length <= 63 ) {
				pass = true;
			}
		}
		return pass;
	}

	function getWirelessNamingValues( justValidate ) {
		var form = document.getElementById('name-your-wireless-network-form');
		var ssidInput = form.querySelector('[name=ssid]');
		var pwdInput = form.querySelector('[name=wifipassword]')
		var ssid = ssidInput.value;
		var pwd = pwdInput.value;
		var failClassName = 'validation--fail';
		var btn = form.querySelector('.name-your-wireless-network-btn');
		var pass = false;

		if ( validatesSSID( ssid ) ) {
			ssidInput.classList.remove(failClassName);
		} else {
			if ( justValidate ) {
				if ( ssid.length > 0 ) {
					ssidInput.classList.add(failClassName);
				} else {
					ssidInput.classList.remove(failClassName);
				}
			} else {
				ssidInput.classList.add(failClassName);
			}
		}

		if ( validatesWPA( pwd ) ) {
			pwdInput.classList.remove(failClassName);
		} else {
			if ( justValidate ) {
				if ( pwd.length > 0 ) {
					pwdInput.classList.add(failClassName);
				} else {
					pwdInput.classList.remove(failClassName);
				}
			} else {
				pwdInput.classList.add(failClassName);
			}
		}

		if ( validatesSSID( ssid ) && validatesWPA( pwd ) ) {
			var pass = true;
		}

		if ( pass && !justValidate ) {

			btn.classList.add('btn-is-loading');
			btn.disabled = true;
			btn.innerText = 'Please wait...'

			var xhttp;
			xhttp = new XMLHttpRequest();
			xhttp.onreadystatechange = function() {
				if (this.readyState == 4) {
					if (this.status == 200) {

						amplifiJsUtil.vault().nameYourWirelessNetwork({
							hide: true
						});

						document.querySelector('.content--ssid-list').style.display = '';
						document.getElementById('band-selection').style.display = '';

					}
					else if ( this.status == 0 ) {
						setTimeout('getWirelessNamingValues();', 250);
						/*
						btn.classList.remove('btn-is-loading');
						btn.disabled = false;
						btn.innerText = 'Continue setup'
						*/
					}
				}
			};
			xhttp.ontimeout = function (e) {
				//getWirelessNamingValues();
			}
			xhttp.open('POST', 'ml-async.php', true);
			xhttp.timeout = 15000;
			xhttp.send( 'do=configureTeleportAp&token=$TOKEN&ssid=' + jsons(ssid).replace('=','%3D') + '&wifi_password=' + encodeURIComponent(pwd) + '&uss=' + encodeURIComponent(ssid) );
		}
		return false;
	}

	function jsons(s) {
		var json = JSON.stringify(s);
		return json.replace(/[\u007f-\uffff]/g, function(c) {return '\\\\\\\\u'+('0000'+c.charCodeAt(0).toString(16)).slice(-4);}).slice(1,-1);
	}

	var set10 = false;

	function progressBarReset() {
		set10 = false;
		amplifiJsUtil.vault().engageLoadingProgress({
			percentage: 1,
			title: 'Retrieving wireless networks list',
			duration: 'Loading...',
			timing: '0s'
		});
	}

	function showProgress(xhr) {
		var countLines = xhr.responseText.split(/\r\n|\r|\n/).length - 3;
		var lastLine = xhr.responseText.split(/\r\n|\r|\n/).slice(-1)[0];

		try {
			var totalLines = JSON.parse(lastLine).total
		} catch (e) {}

		if ( countLines < 1 || typeof totalLines === 'undefined') {
			if (set10 === true) {
				return false;
			}

			set10 = true;

			var progressArgs = {
				percentage: 100,
				title: 'Retrieving wireless networks list',
				duration: 'Loading...',
				timing: '10s'
			}

			setTimeout(function() {
				amplifiJsUtil.vault().engageLoadingProgress(progressArgs);
			}, 500);

		} else {
			var percentage = (countLines / totalLines * 100);
			if (percentage < 15) {
				percentage = 15;
				var timing = '12s';
			} else {
				var timing = '0.2s';
			}

			if (percentage > 100) {
				percentage = 100;
			}
			amplifiJsUtil.vault().engageLoadingProgress({
				percentage: percentage.toFixed(0),
				title: 'Retrieving wireless networks list',
				duration: percentage.toFixed(0) + '% loaded',
				timing: timing
			});
		}

	}

	var TELEPORT_GLOBAL_VARS = {};
	TELEPORT_GLOBAL_VARS.current_wifi_band = $active_band;
	amplifiJsUtil.grip(':/PopulateNetworkList', [ { type: 'runtime', args: { url: '/scanlist.php', send: 'scan=$scan', callbackdynamic: showProgress, fallback: ':/fallbackNetworkList', timeout: 15000 } } ] ).dispatch();

</script>
</body>
"

. html-stop
