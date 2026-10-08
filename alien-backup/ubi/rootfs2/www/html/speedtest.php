#!/bin/sh

. ../inc/vars
. functions

log_debug "speedtest.php"

. html-start
. head-start

if [ "$DEV_MODE" != "1" ]; then
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
				<h1>Speed Test</h1>
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



				<form class='base-modal-content' id='speedtest-teleport-body'>

					<div class='speedtest-meter'>

						<div class='item-meter'>

							<div class='item-meter-back'></div>

							<div class='item-meter-progress-bars-container' id='speedtest-meter-progress'>
								<div class='item-meter-progress-bars'>
									<div class='item-meter-progress-bars bar--1'></div>
									<div class='item-meter-progress-bars bar--2'></div>
								</div>
							</div>

							<div class='hand' id='speedtest-meter-hand'></div>
						</div>

						<div class='speedtest-meter-numbers clearfix'>
							<div class='numbers numbers-down clearfix'>
								<div class='numbers-container clearfix'>
									<div class='numbers-value' id='speedtest-meter-value-down'>0</div>
									<div class='numbers-side'>
										<div class='numbers-designation' id='speedtest-meter-value-down-designation'>Mbps</div>
										<div class='numbers-label'>Download</div>
									</div>
								</div>
							</div>
							<div class='numbers numbers-up clearfix'>
								<div class='numbers-container clearfix'>
									<div class='numbers-value' id='speedtest-meter-value-up'>0</div>
									<div class='numbers-side'>
										<div class='numbers-designation' id='speedtest-meter-value-up-designation'>Mbps</div>
										<div class='numbers-label'>Upload</div>
									</div>
								</div>
							</div>
						</div>

						<div class='item-services'>

							<div class='service-item availability-loading clearfix' id='service-quality-status--netflix'>
								<div class='service-icon'>
									<img src='img/service-netflix.png' style='width: auto; height: 20px;'>
								</div>
								<div class='service-availability'>
									<div class='service-quality' id='service-quality-desc--netflix'></div>
									<div class='service-status'></div>
								</div>
							</div>

							<div class='service-item availability-loading clearfix' id='service-quality-status--itunes'>
								<div class='service-icon'>
									<img src='img/service-itunes.svg' style='width: auto; height: 20px;'>
								</div>
								<div class='service-availability'>
									<div class='service-quality' id='service-quality-desc--itunes'></div>
									<div class='service-status'></div>
								</div>
							</div>

							<div class='service-item availability-loading clearfix' id='service-quality-status--xbox'>
								<div class='service-icon'>
									<img src='img/service-xbox.png' style='width: auto; height: 20px;'>
								</div>
								<div class='service-availability'>
									<div class='service-quality' id='service-quality-desc--xbox'></div>
									<div class='service-status'></div>
								</div>
							</div>


						</div>

						<div class='item-rerun hidden' id='speedtest-rerun-container'>
							<a href='javascript:speedTest(1);' id='speedtest-rerun-trigger'>Run test again</a>
						</div>

					</div>

				</form>

			</div>
		</div>
	</div>
</div>

<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>
<script>


	var speedtestMeterValueObj = {
		down: {
			value: document.getElementById('speedtest-meter-value-down'),
			designation: document.getElementById('speedtest-meter-value-down-designation')
		},
		up: {
			value: document.getElementById('speedtest-meter-value-up'),
			designation: document.getElementById('speedtest-meter-value-up-designation')
		}
	}

	function speedtestMeterValue( value, designation, direction ) {
		speedtestMeterValueObj[direction].value.innerText = value;
		speedtestMeterValueObj[direction].designation.innerText = designation;
	}

	function speedtestRerunShow( visibility ) {
		var el = document.getElementById('speedtest-rerun-container');
		if ( visibility ) {
			el.classList.remove('hidden');
		} else {
			el.classList.add('hidden');
		}
	}

	var speedtestHand = document.getElementById('speedtest-meter-hand');

	function speedtestHandManipulation( hand, percent ) {
		var value = (percent * (90 - (-90)) / 100) + (-90);
		var rotate = 'rotate(' + value.toFixed(2) + 'deg) translateZ(0)';
		hand.style.transform = rotate;
		hand.style.MozTransform = rotate;
		hand.style.webkitTransform = rotate;
	}

	var speedtestProgressBar = document.getElementById('speedtest-meter-progress');

	function speedtestProgress( bar, percent ) {
		var value = (percent * -((-360) - (-180)) / 100) + (-180);
		var rotate = 'rotate(' + value.toFixed(2) + 'deg) translateZ(0)';
		bar.style.transform = rotate;
		bar.style.MozTransform = rotate;
		bar.style.webkitTransform = rotate;
	}

	function serviceSet( service, availability, quality ) {
		var el = document.getElementById('service-quality-status--' + service);
		var descEl = document.getElementById('service-quality-desc--' + service);
		var availabilityClasses = {
			loading: 'availability-loading',
			good: 'availability-good',
			poor: 'availability-poor'
		}
		descEl.innerText = '';
		el.classList.remove(availabilityClasses.loading);
		el.classList.remove(availabilityClasses.good);
		el.classList.remove(availabilityClasses.poor);
		el.classList.add(availabilityClasses[availability]);
		if ( quality ) {
			descEl.innerText = quality;
		}
	}

function speedToString(speed) {
	if (speed == 0) {
		return 0;
	} else if (speed < 10000) {
		return (speed/1000).toFixed(1)
	} else {
		return Math.round(speed/1000)
	}
}

function speedTest(step){
	if (step == 1) {
		serviceSet( 'netflix', 'loading' )
		serviceSet( 'itunes', 'loading' )
		serviceSet( 'xbox', 'loading' )
	}
	var xhttp;
	xhttp=new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				var arr = JSON.parse(this.responseText)
				if(arr.hasOwnProperty('progress')) {
					if (arr.test_state == 1 && test_reset == false) {
						speedtestHandManipulation(speedtestHand, 0);
						speedtestMeterValue(0, 'Mbps', 'down');
						speedtestMeterValue(0, 'Mbps', 'up');
						speedtestRerunShow( false );
						test_reset = true;
					}
					if (arr.test_state >= 2) {
						updateHand(arr.speed_dl, arr.speed_ul, arr.test_state_str);
						speedtestMeterValue(speedToString(arr.speed_dl), 'Mbps', 'down');
						speedtestMeterValue(speedToString(arr.speed_ul), 'Mbps', 'up');
					}
					speedtestProgress( speedtestProgressBar, arr.progress );
				}
				speedTestLoopCount++;
				if (arr.test_state == 0) { // speedtest is complete
					if (speedTestLoopCount < 20 && arr.speed_dl == 0) { // failsafe - run again
						setTimeout('speedTest(1);',1000);
					} else {
						updateHand(arr.speed_dl, arr.speed_ul, arr.test_state_str);
						// test failed
						if (arr.speed_dl == 0 && arr.speed_ul == 0) {
							serviceSet( 'netflix', 'poor', 'test failed' );
							serviceSet( 'itunes', 'poor', 'test failed' );
							serviceSet( 'xbox', 'poor', 'test failed' );
							speedtestProgress( speedtestProgressBar, 100 );
						} else {
							// Netflix
							if (arr.speed_dl > 15000 && arr.speed_ul > 500 ) {
								serviceSet( 'netflix', 'good', 'Ultra high definition' );
							} else if (arr.speed_dl > 4000 && arr.speed_ul > 400 ) {
								serviceSet( 'netflix', 'good', 'High definition' );
							} else if (arr.speed_dl > 2000 && arr.speed_ul > 200 ) {
								serviceSet( 'netflix', 'good', 'Standard definition' );
							} else if (arr.speed_dl > 1000 && arr.speed_ul > 100 ) {
								serviceSet( 'netflix', 'good', 'Low definition' );
							} else {
								serviceSet( 'netflix', 'poor', 'Poor' );
							}
							// itunes
							if (arr.speed_dl > 320 && arr.speed_ul > 100 ) {
								serviceSet( 'itunes', 'good', 'Extreme quality' );
							} else if (arr.speed_dl > 160 && arr.speed_ul > 50 ) {
								serviceSet( 'itunes', 'good', 'High quality' );
							} else if (arr.speed_dl > 96 && arr.speed_ul > 40 ) {
								serviceSet( 'itunes', 'poor', 'Standard quality' );
							} else {
								serviceSet( 'itunes', 'poor', 'Poor' );
							}
							// Xbox
							if (arr.latency < 50) {
								serviceSet( 'xbox', 'good', 'Latency ' + arr.latency + ' ms' );
							} else if (arr.latency < 150) {
								serviceSet( 'xbox', 'good', 'Latency ' + arr.latency + ' ms' );
							} else if (arr.latency < 500) {
								serviceSet( 'xbox', 'poor', 'Latency ' + arr.latency + ' ms' );
							} else {
								serviceSet( 'xbox', 'poor', 'Latency ' + arr.latency + ' ms' );
							}
						}
						speedtestRerunShow( true );
						test_reset = false;
					}
				} else { // speedtest still running
					setTimeout('speedTest(2);',1000);
				}
			} else { // xhttp status != 200
				setTimeout('speedTest(2);',1000);
			}
		}
	};
	xhttp.open('POST', 'speedtest-run.php', true);
	xhttp.setRequestHeader('Content-type','application/x-www-form-urlencoded');
	xhttp.send('step='+step+'&token=$TOKEN');
}

function updateHand(dls, ups, state) {
	console.log(dls, ups, state);
	if (state == 'TS_UL' || state == 'UTS_UL') {
		sp = ups;
	} else {
		sp = dls;
	}

	deg = (Math.pow(sp, 0.5)) / (Math.pow(100000, 0.5)) * 100;
	if (deg > 100) {
		deg = 100;
	}
	speedtestHandManipulation(speedtestHand, deg);
}

var test_reset = false;
var speedTestLoopCount = 0;
speedTest(1);

</script>
"

echo "</body>"

. html-stop
