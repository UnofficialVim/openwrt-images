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

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING
	eth=$(getvar "$QUERY_STRING" "eth")
	[ "$eth" ] && rm -f $SETUPFILE
fi

log_debug "ml-setup-progress.php"
teleport_state

cat head.html
. head-stop

echo "<body>"

. body-nav

echo "
<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>Teleport setup</h1>
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

				<div class='base-modal-content' id='setup-progress'>

					<form class='node--body' id='setup-progress-body' method='post'>
						<input type='hidden' name='token' value='$TOKEN'>

						<div class='loading-progress-bar hidden' style='padding-top: 30px; padding-bottom: 30px;'>
							<div class='progress-bar--progress'>
								<div class='bar-fill' style='width: 0%;'></div>
							</div>
							<div class='progress-bar--action'>Loading...</div>
							<div class='progress-bar--duration'>Please wait</div>
						</div>

						<div class='loading-progress-bar--relative' style=''>
							<div class='item-title'>
									Loading...
							</div>
							<div class='item-message'>
									Please wait
							</div>

							<div class='node--progress-loader'></div>
						</div>

						<div class='item-btn hidden'>
							<button type='' formaction=''></button>
						</div>

					</form>

					<div class='node--line'></div>

					<div class='node--foot-extension' id='setup-progress-steps'>

						<div class='progress-steps clearfix'>

							<div class='step step-1'>
								<div class='step-status'></div>
								<div class='step-label'>Local connection</div>
							</div>
							<div class='step step-2'>
								<div class='step-status'></div>
								<div class='step-label'>Internet</div>
							</div>
							<div class='step step-3'>
								<div class='step-status'></div>
								<div class='step-label'>AmpliFi Router</div>
							</div>
							<div class='step step-4'>
								<div class='step-status'></div>
								<div class='step-label'>Home network</div>
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

<script type='text/javascript'>

	setupProgressContainer = document.getElementById('setup-progress');
	setupProgressBody = document.getElementById('setup-progress-body');
	setupProgressStepsContainer = document.getElementById('setup-progress-steps');


	function setupProgress( name, description, halted, btnParams ) {

		if ( halted || btnParams ) {
			switchDisplay('txt');
		} else {
			setBarTitle(name);
			setBarDuration(description);
			switchDisplay('bar');
		}

		var loader = setupProgressBody.querySelector('.node--progress-loader');
		var title = setupProgressBody.querySelector('.item-title');
		var message = setupProgressBody.querySelector('.item-message');
		var btnContainer = setupProgressBody.querySelector('.item-btn');
		var btn = btnContainer.querySelector('button');
		var clearButton = function() {
			btnContainer.classList.add('hidden');
			btn.setAttribute('type', '');
			btn.setAttribute('formaction', '');
			btn.value = '';
			btn.innerText = '';
			btn.classList.remove('btn-is-loading');
			btn.disabled = false;
			btn.setAttribute('name', '');
		}

		btnContainer.classList.add('hidden');
		loader.classList.remove('loader-error');
		loader.classList.remove('loader-done');
		loader.classList.remove('loader-connected');
		loader.classList.remove('loader-move');
		loader.classList.remove('loader-app-screen');
		loader.classList.remove('loader-screen-error');

		if ( halted ) {
			if ( halted === true ) {
				loader.classList.add('loader-error');
			}
			if ( halted === 'screen-error' ) {
				loader.classList.add('loader-screen-error');
			}
			if ( halted === 'done' ) {
				loader.classList.add('loader-done');
			}
			if ( halted === 'connected' ) {
				loader.classList.add('loader-connected');
			}
			if ( halted === 'move' ) {
				loader.classList.add('loader-move');
			}
			if ( halted === 'screen' ) {
				loader.classList.add('loader-app-screen');
			}
		}

		if ( btnParams ) {
			clearButton();

			if ( btnParams.visible === true ) {
				btnContainer.classList.remove('hidden');
				if ( btnParams.type ) {
					btn.setAttribute('type', btnParams.type);
				}
				if ( btnParams.formaction ) {
					btn.setAttribute('formaction', btnParams.formaction);
				}
				if ( btnParams.name ) {
					btn.setAttribute('name', btnParams.name);
				}
				if ( btnParams.value ) {
					btn.value = btnParams.value;
				}
				if ( btnParams.innerText ) {
					btn.innerText = btnParams.innerText;
				}

				if ( btnParams.type == 'button' ) {
					btn.onclick = function() {

						btn.classList.add('btn-is-loading');
						btn.innerText = 'Please wait...';
						btn.disabled = true;

						amplifiJsUtil.vault().utils.XHR({
							args: {
								url: btnParams.formaction,
								send: btnParams.value,
								timeout: 3000,
								callback: function() {

								},
								fallback: function() {
									clearButton();
									setupProgress( name, description, halted, btnParams );
								}
							}
						}, {
							XHRTimeout: setTimeout(function() {}, 3000)
						});

						tDiff(1);
						stateTimer();
						offlineTimer();
					}
				} else if ( btnParams.type == 'close' ) {
					btn.onclick = function() {
						window.close();
						return false;
					}
				} else {
					btn.onclick = function() {}
				}
			} else {
				clearButton();
			}

		} else {
			clearButton();
		}

		if ( name ) {
			title.innerText = ' ';
			title.innerText = name;
		}

		if ( description ) {
			message.innerText = ' ';
			message.innerText = description;
		}

	}

	// example -  setupProgress('Doing something', 'Loading some generic data...', false, { visible: true, type: 'button', formaction: 'meh.php', value: 'scan=1', innerText: 'Do something...' });

	function setupProgressStep( stepID, status ) {
		var statusClasses = {
			clear: false,
			loading: 'step-loading',
			done: 'step-done',
			error: 'step-error'
		}
		if ( stepID && !isNaN(stepID) ) {
			var step = setupProgressStepsContainer.querySelector('.step-' + stepID);
			if ( status ) {
				step.classList.remove( statusClasses.loading );
				step.classList.remove( statusClasses.done );
				step.classList.remove( statusClasses.error );
				if ( statusClasses[status] ) {
					step.classList.add( statusClasses[status] );
				}
			}
		}
	}

	// example - setupProgressStep(2, 'loading');

	function processState(xhttp) {
		try {
			if (xhttp.responseText) {
				var arr = JSON.parse(xhttp.responseText);
			} else {
				var arr = xhttp;
			}

			teleport_status = arr.status;

			if (arr.step && arr.state) {
				for (i = 1; i <= 4; i++) {
					if (arr.step < i) {
						state_txt = ' ';
					} else if (arr.step > i) {
						state_txt = 'done';
					} else {
						if (arr.state == 1) {
							state_txt = 'done';
						}  else if (arr.state == 5) {
							if (arr.step > i) {
								state_txt = 'none';
							} else {
								state_txt = 'done';
							}
						}  else if (arr.state == 4) {
							state_txt = 'error';
						} else {
							state_txt = 'loading';
						}
					}
					setupProgressStep(i, state_txt);
				}

				if (arr.state == 1) {
					msg_title = 'Done';
				}  else if (arr.state == 5) {
					msg_title = 'Setup is finished';
				}  else if (arr.state == 4) {
					msg_title = 'Error';
				}  else if (arr.state == 3) {
					msg_title = 'Waiting...';
				} else {
					msg_title = 'Setting up...';
				}

				message = arr.message
				if (!message) {
					message = 'Please wait.';
				}

				if (teleport_status == 310) {
					setupProgress('Waiting for pairing acceptance', message, 'screen', false);
				} else if (teleport_status == 350) {
					setupProgress(msg_title, message, true, { visible: true, type: 'button', formaction: 'ml-async.php', value: '', innerText: 'Retry' });
					setBarProgress(50, '0.2s');
				} else if (teleport_status == 360) {
					setupProgress('Pairing request declined', message, 'screen-error', { visible: true, type: 'button', formaction: 'ml-async.php', value: 'do=14', innerText: 'Resend request' });
					setBarProgress(10, '0.2s');
				} else if (teleport_status == 420) {
					setupProgress('Finalizing', message, 'connected', false);
				} else if (teleport_status == 455) {
					setupProgress('Move Teleport', message, 'move', { visible: true, type: 'submit', formaction: 'connect.php', value: '1', name: 'configure', innerText: 'Change connection' });
				} else if (teleport_status == 500) {
					setupProgress('Setup complete', 'You are connected to your home network', 'connected', false);
					setTimeout(function() { window.close(); }, 4000);
				} else if (teleport_status == 501 || teleport_status == 600) {
					setupProgress('Finalizing', 'Home network connection will be available shortly', 'connected', false);
					setTimeout(function() { window.close(); }, 4000);
					offlineTimerInterval = 120000;
				} else if (teleport_status == 601) {
					setupProgress('Finalizing', 'Home network connection will be available shortly', 'connected', false);
					setTimeout(function() { window.close(); }, 4000);

				} else if (arr.state == 4) {
					setupProgress(msg_title, message, true, { visible: true, type: 'submit', formaction: 'connect.php', value: '1', name: 'configure', innerText: 'Change connection' });
				} else if (arr.state == 5) {
					setupProgress(msg_title, message, 'done', { visible: true, type: 'submit', formaction: 'connect.php', value: '1', name: 'configure', innerText: 'Change connection' });
				} else {
					setupProgress(msg_title, message, false, false);

					var percentage = 100;

					if (arr.step > 1 && arr.step < 6 && arr.step != teleport_step_prev) {
						teleport_step_prev = arr.step;
						percentage = (arr.step - 1) * 25;
						setBarProgress(percentage, '0.1s');
					}

					if (arr.step < 5) {
						percentage = arr.step * 25;
					}
					setTimeout(function() {setBarProgress(percentage, '16s');}, 300);
				}

				if (teleport_status == 100) {
					window.location.href = 'ml-network-list.php';
				} else if (teleport_status == 140) {
					window.location.href = 'connect.php';
				} else if (teleport_status == 401) {
					window.location.href = 'connect.php';
				} else if (arr.status == 190) {
					window.location.href = 'setup.php?ssid='+arr.message+'&invalid=password';
				}

				if (teleport_status_prev == teleport_status) {
					if (teleport_status == 120 && tDiff(0) > 5000) {
						window.location.href = 'http://amplifi.com';
					} else if (teleport_status == 601 && tDiff(0) > 4000) {
						window.location.href = 'http://'+message;
					} else if (arr.step == 1 && tDiff(0) > 60000) {
						window.location.href = 'ml-network-list.php';
					}
				} else {
					tDiff(1);
					teleport_status_prev = teleport_status;
				}
			}

			if (arr.state != 4 && arr.state != 5) {
				stateTimer();
				offlineTimer();
			} else {
				stopTimers();
			}

			if (arr.hasOwnProperty('update') && arr.update == 1) {
				amplifiJsUtil.vault().supportNoticeForUpdate({
					active: true, title: 'Software update is available'
				})
			}

		} catch (e) {
			console.log(e);
			stateTimer();
			offlineTimer();
			return false;
		}
	}

	// redefine local function
	function teleportOffline() {
		teleport_status = 0;
		setBarTitle('Connection lost');
		setBarDuration('Please connect to Teleport');
		switchDisplay('bar');
	}


	var progressBar = document.querySelector('.loading-progress-bar');
	var progressText = document.querySelector('.loading-progress-bar--relative');

	function switchDisplay(item) {
		if (item == 'bar') {
			progressBar.classList.remove('hidden');
			progressText.classList.add('hidden');
		} else {
			progressBar.classList.add('hidden');
			progressText.classList.remove('hidden');
		}
	}

	function setBarTitle(title) {
		amplifiJsUtil.vault().engageLoadingProgress({
			title: title
		});
	}

	function setBarDuration(durationMsg) {
		amplifiJsUtil.vault().engageLoadingProgress({
			duration: durationMsg,
		});
	}

	function setBarProgress(percentage, timing) {
		if (!timing) {
			timing = '1s'
		}
		amplifiJsUtil.vault().engageLoadingProgress({
			percentage: percentage,
			timing: timing
		});
	}

	switchDisplay('bar');

	var teleport_step_prev = 0;
	var teleport_status = teleport_status_prev = 0;

	var xhttp = []
	xhttp['step'] = '$teleport_setup_step';
	xhttp['state'] = '$teleport_setup_state';
	xhttp['status'] = '$teleport_status_id';
	xhttp['message'] = '$teleport_status_msg';

	processState(xhttp);


	preload = ['/img/router-teleport.png', '/img/router-teleport-move.png', '/img/setup-app-screen.png', '/img/done-ring.svg', '/img/error-ring.svg'];
	for (var i = 0; i < preload.length; ++i) {
		var img = new Image();
		img.src = preload[i];
	}


</script>
</body>"

. html-stop
