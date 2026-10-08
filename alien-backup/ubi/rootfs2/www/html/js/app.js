Clink.bind('amplifiJsUtil');

amplifiJsUtil

.chain(':/root/main/fwupdateInput',
	function( params, config, util ) {
		if ( params.event.target.files[0] ) {
			var filenameNode = document.getElementById('fw-file-upload-file-name');
			filenameNode.innerText = params.event.target.files[0].name;
			filenameNode.classList.add('file-is-selected');
		}
	}
)
.grip(':/root/main/fwupdateInput',
	[
		{ type: 'change', query: '#fw-file-upload' }
	]
)

.chain(':/root/main/select',
	function( params, config, util ) {
		var target;

		if ( params.args ) {
			target = document.querySelector(params.args.target);
		} else {
			target = params.event.target
		}
		if ( target && target.selectedOptions[0] && target.selectedOptions[0].label ) {
			target.nextElementSibling.innerText = target.selectedOptions[0].label;
		}
	}
)
.grip(':/root/main/select',
	[
		{ type: 'change', query: '.select-item select' },
		{ type: 'runtime', args: { target: '.select-item select' } }
	]
)


.chain(':/root/main/processing',
	function( params, config, util ) {
		target = document.querySelector(params.args.target);
		target.classList.add('active');
	}
)
.grip(':/root/main/processing',
	[
		{ type: 'click', query: '.trigger-processing', args: { target: '.processing' } }
	]
)

.chain(':/root/urlExists',
	function( params, config, util ) {
		var url = params.args.url;
		var http = new XMLHttpRequest();
		http.open('HEAD', url, false);
		http.send();
		return http.status!=404;
	}
)

.chain(':/root/main/select',
	function( params, config, util ) {
		var target;
		if ( params.args ) {
			target = document.querySelector(params.args.target);
		} else {
			target = params.event.target
		}
		if ( target ) {
			var label = target.nextElementSibling;
			if ( target.selectedOptions[0] ) {
				label.innerText = target.selectedOptions[0].label;
				label.setAttribute('data-value', target.selectedOptions[0].value);
			}
		}
	}
)

.grip(':/root/main/select',
	[
		{ type: 'change', query: '.select-item select' },
		{ type: 'runtime', args: { target: '.select-item select' } }
	]
)

.chain(':/defineDomRefs',
	function( params, config, util ) {

		config.ssidsLoading = document.getElementById('ssids-are-loading');
		config.ssidsNotFound = document.getElementById('ssids-not-found');
		config.ssidTemplateNode = document.getElementById('ssid-list-item-template');
		config.ssidForm = document.querySelector('.content--ssid-list');
		config.bandSelectionTabs = document.getElementById('band-selection');
		config.refreshNetworkListContainer = document.getElementById('refresh-network-list-container');
		config.ssidList = document.getElementById('texxxt');
		config.selectnetworkEthernet = document.getElementById('selectnetwork-ethernet');
		config.selectnetworkBody = document.querySelector('.node--selectnetwork .selectnetwork-body');
		config.namingWiFiContainer = document.getElementById('name-your-wireless-network');


	}
)

.grip(':/defineDomRefs',
	[
		{ type: 'runtime' }
	]
)


.chain(':/bandSelection',
	function( params, config, util ) {

		if ( params.event.type == 'click' ) {
			var event = params.event;
			var target = event.currentTarget;
			var band = target.dataset.band;
			var ssidListCount = 0;
			var ssidListItems = config.ssidList.querySelectorAll('.ssid-item');

			config.selectnetworkBody.scrollTop = 0;

			config.currentBand = band;

			config.ssidsNotFound.classList.remove('active');

			for (var i = 0; i < target.parentElement.children.length; i++) {
				target.parentElement.children[i].classList.remove('active');
			}

			target.classList.add('active');

			for (var i = 0; i < ssidListItems.length; i++) {
				ssidListItems[i].classList.add('hidden');
			}
			for (var i = 0; i < ssidListItems.length; i++) {
				if ( ssidListItems[i].classList.contains('band-type--' + band ) ) {
					ssidListItems[i].classList.remove('hidden');
					ssidListCount++;
				}
			}

			config.selectnetworkEthernet.classList.add('hidden');

			if ( config.currentBand == 'E' ) {
				config.refreshNetworkListContainer.classList.add('hidden');
				config.selectnetworkEthernet.classList.remove('hidden');
				config.ssidsLoading.classList.remove('active');
				config.selectnetworkBody.classList.add('no-overflow');
			} else {
				config.selectnetworkBody.classList.remove('no-overflow');
				config.ssidsLoading.classList.add('active');
				var ssidInputList = config.ssidList.querySelectorAll('.ssid-item');
				if ( ssidInputList.length > 0 ) {
					config.refreshNetworkListContainer.classList.remove('hidden');
					config.ssidsLoading.classList.remove('active');
				}
			}

		} else {
			config.currentBand = 2;
		}



	}
)

.grip(':/bandSelection',
	[
		{ type: 'click', query: '#band-selection a' },
		{ type: 'runtime' }
	]
)

.chain(':/utils/XHR',
	function( params, config, util ) {
		var url = params.args.url;
		var send = params.args.send;
		var theTimeout = params.args.timeout ? params.args.timeout : 59000;
		var callback = params.args.callback;
		var fallback = params.args.fallback;
		var callbackdynamic = params.args.callbackdynamic ? params.args.callbackdynamic : '';

		config.XHRTimeout = setTimeout(function() {}, theTimeout);
		clearTimeout( config.XHRTimeout );
		var xhr;
		if ( typeof XMLHttpRequest !== 'undefined') {
			xhr = new XMLHttpRequest();
		} else {
			var versions = [
				"MSXML2.XmlHttp.5.0",
				"MSXML2.XmlHttp.4.0",
				"MSXML2.XmlHttp.3.0",
				"MSXML2.XmlHttp.2.0",
				"Microsoft.XmlHttp"
			]

			for ( var i = 0, len = versions.length; i < len; i++ ) {
				try {
					xhr = new ActiveXObject(versions[i]);
					break;
				} catch(e) {

				}
			}
		}

		function doFallback( fallback ) {
			clearTimeout( config.XHRTimeout );
			if ( fallback && typeof fallback == 'string' ) {
				util.invoke( fallback );
			}
			if ( fallback && typeof fallback == 'function' ) {
				fallback();
			} else {
				return;
			}
		}

		xhr.onreadystatechange = ensureReadiness;

		function ensureReadiness() {

			if ( callbackdynamic ) {
				if ( xhr.readyState < 4) {
					callbackdynamic( xhr );
				}
			}

			if ( xhr.readyState < 4 ) {
					return;
			}

			if ( this.readyState === 4 && this.status === 0 ) {
				doFallback( fallback );
				return;
			}

			if ( xhr.status !== 200 ) {
				 return;
			}

			// all is well
			if ( xhr.readyState === 4 ) {
				clearTimeout( config.XHRTimeout );
				callback( xhr );
			}
		}

		config.XHRTimeout = setTimeout(function() {
			if ( xhr.readyState === 4 && xhr.status != 200 ) {
				xhr.abort();
				doFallback( fallback );
				return;
			}
		}, theTimeout);

		xhr.timeout = theTimeout;

		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
		xhr.send(send);
	}
)

.chain(':/PopulateNetworkList',
	function( params, config, util ) {


		config.singleNetworkList = false;

		if ( config.ssidForm.classList.contains('ssid-single-list') ) {
			config.singleNetworkList = true;
		}

		if ( !config.singleNetworkList ) {
			config.bandSelectionTabs.classList.remove('hidden');
		} else {
			config.bandSelectionTabs.remove();
			config.selectnetworkEthernet.remove();
		}


		config.PopulateNetworkListParams = params.args;

		var url = params.args.url;
		var send = params.args.send;
		var fallback = params.args.fallback;
		var callbackdynamic = params.args.callbackdynamic;

		var signalToBars = function(signal) {
			if (signal > -70) return 4;
			else if (signal > -73) return 3;
			else if (signal > -76) return 2;
			else return 1;
		}

		util.invoke(':/utils/XHR', {
			args: {
				url: url,
				send: send,
				fallback: fallback,
				callbackdynamic: callbackdynamic,
				timeout: 15000,
				callback: function(xhr) {

					try {
						var results = JSON.parse( xhr.responseText );
					} catch (e) {
						console.log( e );
						if ( fallback && typeof fallback == 'string' ) {
							util.invoke( fallback );
						}
						if ( fallback && typeof fallback == 'function' ) {
							fallback();
						}
						return false;
					}

					results = results.sort( function( a, b ) {
						if ( a.hasOwnProperty('ssid') === false ) return  1;
						if ( b.hasOwnProperty('ssid') === false ) return -1;

						var a1 = a.ssid.toLowerCase();
						var b1 = b.ssid.toLowerCase();
						return a1 < b1 ? -1 : a1 > b1 ? 1 : 0;
					});

					var wifilist=[]

					for (var key in results) {
						if (results.hasOwnProperty(key)) {
							if (results[key].hasOwnProperty('ssid')) {

								var ssid = results[key].ssid;
								var freq = results[key].freq;
								var band = (freq < 5000 ? 2 : 5)
								var signal = results[key].signal;
								var bssid = results[key].bssid;

								if (results[key].encryption.enabled === false) {
									var authentication = 'open';
								} else if (results[key].encryption.authentication == 'psk') {
									var authentication = 'psk';
								} else if (results[key].encryption.authentication == '802.1x') {
									var authentication = '802.1x';
								} else {
									var authentication = 'unknown';
								}

								if (wifilist.hasOwnProperty(ssid) && wifilist[ssid].hasOwnProperty(band)) {
									if (signal > wifilist[ssid][band][signal]) {
										wifilist[ssid][band]['freq'] = freq;
										wifilist[ssid][band]['signal'] = signal;
										wifilist[ssid][band]['authentication'] = authentication;
										wifilist[ssid][band]['bssid'] = bssid;
									}
									continue;
								}

								if (! wifilist.hasOwnProperty(ssid)) wifilist[ssid] = []
								if (! wifilist[ssid].hasOwnProperty(ssid)) {
									wifilist[ssid][band] = []
									wifilist[ssid][band]['ssid'] = ssid;
									wifilist[ssid][band]['freq'] = freq;
									wifilist[ssid][band]['signal'] = signal;
									wifilist[ssid][band]['authentication'] = authentication;
									wifilist[ssid][band]['bssid'] = bssid;
								}
								else if (signal > wifilist[ssid][band]['signal']) {
									wifilist[ssid][band]['freq'] = freq;
									wifilist[ssid][band]['signal'] = signal;
									wifilist[ssid][band]['authentication'] = authentication;
									wifilist[ssid][band]['bssid'] = bssid;
								}
							}
						}
					}

					ssid = 'Other Network...';
					wifilist[ssid] = []
					wifilist[ssid][2] = []
					wifilist[ssid][2]['ssid'] = ssid;
					wifilist[ssid][2]['authentication'] = 'custom';
					wifilist[ssid][2]['signal'] = 0;
					wifilist[ssid][5] = []
					wifilist[ssid][5]['ssid'] = ssid;
					wifilist[ssid][5]['authentication'] = 'custom';
					wifilist[ssid][5]['signal'] = 0;

					for (var key in wifilist) {

						for (band in wifilist[key]) {

							var ssid = []
							if ( wifilist[key].hasOwnProperty(2) && wifilist[key].hasOwnProperty(5) ) {
								ssid.dualband = true;
								if (band == 2) {
									ssid.freqdual = wifilist[key][5].freq;
								} else {
									ssid.freqdual = wifilist[key][2].freq;
								}
							}

							ssid.ssid = wifilist[key][band].ssid;
							ssid.band = band;
							ssid.freq = wifilist[key][band].freq;
							ssid.signal = wifilist[key][band].signal;

							if ( wifilist[key][band].authentication == 'open' ) {
								ssid.open = true;
							}
							else if ( wifilist[key][band].authentication == '802.1x' ) {
								ssid.wpaenterprise = true;
							}
							else if ( wifilist[key][band].authentication == 'custom' ) {
								ssid.custom = true;
							}


							var dupNode = config.ssidTemplateNode.cloneNode(true);

							config.ssidsNotFound.classList.remove('active');

							if ( !config.singleNetworkList ) {
								dupNode.classList.add( ( ssid.band == 2 ? 'band-type--2' : 'band-type--5' ) );
								if ( ssid.band != config.currentBand ) {
									dupNode.classList.add('hidden');
								}
							}

							dupNode.removeAttribute('id');
							dupNode.removeAttribute('style');


							var radioType = dupNode.querySelector('.item-icon--radio');

							var itemSSID = dupNode.querySelector('.item-ssid');
							itemSSID.value = ssid.ssid;
							itemSSID.setAttribute('data-band', ssid.band);

							itemSSID.setAttribute('data-freq', ssid.freq);
							itemSSID.setAttribute('data-freqdual', ssid.freqdual);
							itemSSID.setAttribute('data-open', ssid.open);

							if ( ssid.custom ) {
								itemSSID.setAttribute('data-custom', true);
							}

							if ( ssid.wpaenterprise ) {
								itemSSID.setAttribute('data-wpaenterprise', true);
							}

							if ( ssid.dualband ) {
								itemSSID.setAttribute('data-dualband', true);
							}

							if ( radioType ) {
								if ( ssid.band == 2 ) {
									radioType.innerText = '2.4 GHz';
								}
								if ( ssid.band == 5 ) {
									radioType.innerText = '5 GHz';
								}
							}

							if ( !config.singleNetworkList ) {
								itemSSID.setAttribute('data-show-confirmation', false);
							}

							dupNode.querySelector('.item-band').value = ssid.band;
							dupNode.querySelector('.item-name').innerText = ssid.ssid;
							dupNode.querySelector('.item-icon--wifi').classList.add( 'signal-strength-' + signalToBars( ssid.signal ) );

							if ( ssid.open ) {
								dupNode.querySelector('.item-icon--lock').classList.add('hidden')
							}
							config.ssidList.appendChild(dupNode);

							itemSSID.addEventListener('change', function(e) {

								var target = e.currentTarget;
								var thisBand = target.dataset.band;
								var thisDualBand = target.dataset.dualband;

								var supp_freq = target.dataset.freq;
								var supp_freqdual = target.dataset.freqdual;
								var supp_open = target.dataset.open;

								setSupps({
									supp_freq: supp_freq,
									supp_freqdual: supp_freqdual,
									supp_open: supp_open
								});

								if ( target.dataset.wpaenterprise == 'true' ) {
									alert('Enterprise wireless networks are not supported');
									return false;
								}

								var proceed = function( target ) {
									var redundantInputs = config.ssidList.querySelectorAll('.item-band');
									var inputToKeep = target.parentElement.querySelector('.item-band');
									for (var i = 0; i < redundantInputs.length; i++) {
										if ( redundantInputs[i] !== inputToKeep ) {
											redundantInputs[i].remove()
										}
									}
									setTimeout(function() {
										config.ssidForm.submit();
									}, 1000);
								}

								if ( target.dataset.custom == 'true' ) {
									if (thisBand == 2) {
										document.getElementById('custom-freq').value = '2400';
									} else {
										document.getElementById('custom-freq').value = '5000';
									}
									document.querySelector('.content--ssid-list').classList.add('hidden');
									if (document.getElementById('band-selection')) {
										document.getElementById('band-selection').classList.add('hidden');
									}
									document.getElementById('connect-custom').classList.remove('hidden');
									return false;
								}

								config.ssidForm.classList.add('disabled-and-loading');
								proceed( target );

							}, false);

						}
					}

					config.ssidsLoading.classList.remove('active');
					config.refreshNetworkListContainer.classList.remove('hidden');

					if ( !config.singleNetworkList ) {
						var currentBandList = config.ssidList.querySelectorAll('.band-type--' + config.currentBand);
					} else {
						var currentBandList = config.ssidList.querySelectorAll('.ssid-item');
					}

					if ( currentBandList.length < 1 ) {
						config.ssidsNotFound.classList.add('active');
					}

					if ( config.currentBand == 'E' ) {
						config.refreshNetworkListContainer.classList.add('hidden');
						config.selectnetworkEthernet.classList.remove('hidden');
						config.ssidsNotFound.classList.remove('active');
					}
				}
			}
		});
	}
)


.chain(':/fallbackNetworkList',
	function( params, config, util ) {
		config.refreshNetworkListContainer.classList.add('hidden');
		config.ssidsNotFound.classList.remove('active');

		var ssidInputList = config.ssidList.querySelectorAll('.ssid-item');
		for (var i = 0; i < ssidInputList.length; i++) {
				config.ssidList.removeChild(ssidInputList[i]);
		}

		config.ssidsLoading.classList.add('active');
		config.PopulateNetworkListParams.send = 'scan=';
		util.invoke(':/PopulateNetworkList', { args: config.PopulateNetworkListParams } );
	}
)

.chain(':/refreshNetworkList',
	function( params, config, util ) {
		config.refreshNetworkListContainer.classList.add('hidden');
		config.ssidsNotFound.classList.remove('active');

		var ssidInputList = config.ssidList.querySelectorAll('.ssid-item');
		for (var i = 0; i < ssidInputList.length; i++) {
				config.ssidList.removeChild(ssidInputList[i]);
		}

		config.ssidsLoading.classList.add('active');
		config.PopulateNetworkListParams.send = 'scan=2';
		util.invoke(':/PopulateNetworkList', { args: config.PopulateNetworkListParams } );
	}
)

.grip(':/refreshNetworkList',
	[
		{ type: 'click', query: '#refresh-network-list' }
	]
)



.chain(':/speedtestDontShowThisScreen',
	function( params, config, util ) {
		var notice = document.getElementById('speedtest-teleport-restart-notice');
		var checkbox = document.getElementById('dont-show-this-screen');
		var speedtest = document.getElementById('speedtest-teleport-body');
		var isChecked = checkbox.checked;
		var event = params.event;
		var target = event.currentTarget;

		if ( event.type == 'click' ) {

			if ( target.disabled == true ) {
				return false;
			}

			event.preventDefault();
			target.classList.add('btn-is-loading');
			target.innerText = 'Please wait while loading...';
			target.disabled = true;
			target.classList.add('btn-is-loading');
			checkbox.disabled = true;
		}

		util.invoke(':/utils/XHR', {
			args: {
				url: '/ml-async.php',
				send: 'do=2&skip=' + isChecked,
				fallback: ':/speedtestDontShowThisScreen',
				callback: function(xhr) {
					notice.classList.remove('hidden');
					speedtest.classList.add('hidden');
				},
				timeout: 10000
			}
		});

	}
)

.grip(':/speedtestDontShowThisScreen',
	[
		{ type: 'click', query: '#speedtest-start-using-internet' }
	]
)


.chain(':/resizeNetworkList',
	function( params, config, util ) {
		var base = config.selectnetworkBody;
		if ( base ) {
			var topSpace, bottomSpace;
			var wh = util.viewport.height();
			var ww = util.viewport.width();
			topSpace = 240;
			bottomSpace = 120;
			if ( wh >= 860 ) {
				topSpace = 460;
			}
			if ( wh >= 860 && ww <= 992 ) {
				topSpace = 340 + 70;
			}
			if ( wh >= 860 && ww <= 640 ) {
				topSpace = 340;
			}
			if ( wh <= 860 && ww <= 480 ) {
				topSpace = 180;
			}
			var speculatedHeight = wh - topSpace - bottomSpace;
			base.style.maxHeight = speculatedHeight + 'px';
		}
	}
)
.grip(':/resizeNetworkList',
	[
		{ type: 'runtime resize' }
	]
)


.chain(':/connectionLostNotice',
	function( params, config, util ) {
		var message = document.getElementById('connection-lost-message');
		var nessageText = document.getElementById('connection-lost-message-text');
		if ( typeof params == 'string' || params == undefined ) {
			document.body.classList.add('connection-lost');
			message.classList.remove('hidden');
			if ( params == undefined ) {
				nessageText.innerText = 'Disconnected!';
			} else {
				nessageText.innerText = params;
			}
			window.scroll( 0, 0 );
		} else if ( typeof params == 'boolean' && params === false ) {
			document.body.classList.remove('connection-lost');
			message.classList.add('hidden');
			nessageText.innerText = '';
		}
	}
)



.chain(':/customPageSet',
	function( params, config, util ) {
		var selector = params.selector;
		var value = params.value;
		var attr = params.attr;
		var hidden = params.hidden;
		var className = params.className;
		if ( selector ) {
			var el = document.querySelector(selector);
			if ( hidden ) {
				el.style.display = 'none';
			} else {
				el.style.display = '';
			}
			if ( value ) {
				el.innerText = value;
			}
			if ( attr ) {
				el.setAttribute(attr.name, attr.value);
			}
			if ( className ) {
				if ( typeof className == 'object' ) {
					if ( className.add ) {
						el.classList.add(className.add);
					}
					if ( className.remove ) {
						el.classList.remove(className.remove);
					}
				} else {
					el.classList.add(className);
				}
			}
		}
	}
)


.chain(':/triggerEthTab',
	function( params, config, util ) {
		var message = params.message;
		var connected = params.connected;
		var messageTitle = document.querySelector('.ethernet-action-title');
		var img = document.querySelector('.ethernet-action-figure img');

		if ( connected === true ) {
			img.setAttribute('src', 'img/connect-teleport-connected.png');
		}

		if ( connected === false ) {
			img.setAttribute('src', 'img/connect-teleport.png');
		}

		if ( connected == 'checkit' ) {
			img.setAttribute('src', 'img/connect-teleport-disconnected.png');
		}

		messageTitle.innerText = message;

		setTimeout(function() {
			var evt = document.createEvent('HTMLEvents');
			evt.initEvent('click', false, true);
			document.querySelector('.tab.type--E').dispatchEvent(evt);
		}, 1000);
	}
)








.chain(':/resposiveTitle',
	function( params, config, util ) {
		var resposiveTitle = document.getElementById('responsive-page-title');
		var mainTitle = document.querySelector('.app-section-title h1');
		if (resposiveTitle) {
			resposiveTitle.innerText = mainTitle.innerText;
		}
	}
)
.grip(':/resposiveTitle',
	[
		{ type: 'runtime' }
	]
)




.chain(':/revealPasswordField',
	function( params, config, util ) {
		var input = document.querySelector('#show-password-container input');
		var target = params.event.currentTarget;
		TELEPORT_GLOBAL_VARS.passwordRevealTriggered = true;
		if ( input.type == 'password' ) {
			target.classList.add('active');
			input.type = 'text';
		} else {
			target.classList.remove('active');
			input.type = 'password';
		}
	}
)

.grip(':/revealPasswordField',
	[
		{ type: 'click', query: '#show-password-trigger' }
	]
)


.chain(':/nameYourWirelessNetwork',
	function( params, config, util ) {

		var formNotice = document.getElementById('name-your-wireless-network-notice');
		var form = document.getElementById('name-your-wireless-network');
		var list = document.querySelector('.content--ssid-list');
		var tabs = document.getElementById('band-selection');

		if ( params.event && params.event.type =='runtime' && ( typeof configureTeleportAp !== 'undefined' && configureTeleportAp == 1 ) ) {
			setTimeout(function() {
				form.classList.remove('hidden');
				//formNotice.classList.remove('hidden');
			}, 100);
		}

		if ( !params.event && params.hide === true ) {
			tabs.classList.remove('hidden');
			list.classList.remove('hidden');
			form.classList.add('hidden');
		}

		if ( !params.event && params.hide === false ) {
			setTimeout(function() {
				form.classList.remove('hidden');
				list.classList.add('hidden');
				tabs.classList.add('hidden');
			}, 100);
		}

	}
)
.grip(':/nameYourWirelessNetwork',
	[
		{ type: 'runtime' }
	]
)



.chain(':/engageLoadingProgress',
	function( params, config, util ) {

		var loadingBarContainer = document.querySelector('.loading-progress-bar');

		var visible = params.visible;

		if ( visible === false ) {
			loadingBarContainer.style.display = 'none';
			return false;
		}
		if ( visible === true ) {
			loadingBarContainer.style.display = '';
		}

		var fill = loadingBarContainer.querySelector('.bar-fill');

		var percentage = params.percentage;

		if ( percentage || percentage == 0 ) {
			fill.style.width = percentage +  '%';
		}

		var timing = params.timing
		if ( timing ) {
			fill.style.transitionDuration = timing;
		}

		var title = params.title;
		if ( title ) {
			var actionTitle = loadingBarContainer.querySelector('.progress-bar--action');
			actionTitle.innerText = title;
		}

		var duration = params.duration;
		if ( duration ) {
			var durationName = loadingBarContainer.querySelector('.progress-bar--duration');
			durationName.innerText = duration;
		}

		var callback = params.callback;
		if ( callback && typeof callback == 'function' ) {
			callback();
		}

	}
)


.chain(':/loadingNotice',
	function( params, config, util ) {
		var notice = document.getElementById('loading-notice');
		if ( notice ) {
			var title = notice.querySelector('.item-title');
			var description = notice.querySelector('.item-description');
			title.innerHTML = params.title || '';
			description.innerText = params.description || '';
		}
	}
)

.chain(':/supportNoticeForUpdate',
	function( params, config, util ) {
		var notificationHolder = document.querySelector('.node--notification-holder');
		var notification = notificationHolder.querySelector('.node--notification');
		if ( notification ) { //notification--update active
			if ( params.active === true ) {
				notification.classList.add('notification--update');
				notification.classList.add('active');
				if ( params.title ) {
					notificationHolder.setAttribute('title', params.title);
				}
			}
			if ( params.active === false ) {
				notification.classList.remove('notification--update');
				notification.classList.remove('active');
				notificationHolder.setAttribute('title', '');
			}
		}
	}
)

.dispatch();



var tNow;
function tDiff(mode) {
	if (mode == 1) {
		tStart = new Date();
	} else {
		tNow = new Date();
		return (tNow - tStart);
	}
}

function getState() {
	var xhttp;
	xhttp=new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				processState(this);
			}
			else if (this.status == 0) {
				teleport_status = 0;
				setTimeout('stateTimer();', 250);
			}
		}
	};
	xhttp.ontimeout = function (e) {
		//stateTimer();
	}
	xhttp.open('POST', 'ml-async.php', true);
	xhttp.timeout = xhttpTimeout;
	xhttp.send('status=' + teleport_status + '&elapsed=' + Math.round(tDiff(0)/1000));

}

function stateTimer() {
	clearTimeout(timeout);
	timeout = setTimeout('getState();', 100);
	clearInterval(timer);
	timer = setInterval('getState();', stateTimerInterval);
}

function offlineTimer() {
	clearTimeout(offlineTimeout);
	offlineTimeout = setTimeout('teleportOffline();', offlineTimerInterval);
	teleportOnline();
}

function stopTimers() {
	clearTimeout(timeout);
	clearInterval(timer);
	clearTimeout(offlineTimeout);
}

function teleportOnline() {
	amplifiJsUtil.vault().connectionLostNotice(false);
}

function teleportOffline() {
	teleport_status = 0;
	if (ignoreTeleportOffline == false) {
		amplifiJsUtil.vault().connectionLostNotice('Connection lost. Please check that you are connected to Teleport.');
	}
}

var waitState = 0;
var waitOffline = 1;
function redirectWhenOnline(url, maxWaitTime) {
	if (waitState == 0) {
		waitState = 1;
		setTimeout('window.location.href = "' + url + '";', maxWaitTime);
	}
	var xhttp=new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200 || this.status == 404) {
				if (waitState == 2 || waitOffline == 0 ) {
					window.location.href = url;
					return true;
				} else {
					setTimeout('redirectWhenOnline("' + url + '", ' + maxWaitTime + ');', 5000);
				}
			}
			else if (this.status == 0) {
				waitState = 2;
				setTimeout('redirectWhenOnline("' + url + '", ' + maxWaitTime + ');', 1000);
			}
		}
	};
	xhttp.open('POST', 'async.php', true);
	xhttp.timeout = xhttpTimeout;
	xhttp.send('d=hello');
}


var tStart = tNow = new Date();

var xhttpTimeout = 12000;
var stateTimerInterval = 20000;
var offlineTimerInterval = 30000;

var timeout, timer, offlineTimeout;
var ignoreTeleportOffline = false;

var teleport_status = 1;
