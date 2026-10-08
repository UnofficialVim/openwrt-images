#!/bin/sh

. ../inc/vars
. functions

log_debug "info.php"

. html-start
. head-start

if [ "$SESSION" = "invalid" ]; then
	set_returnurl "info.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

cat head.html

mode=$(getvar "$QUERY_STRING" "m")
[ -z "$mode" ] && mode=1
[ "$DEV_MODE" != "1" ] && mode=1
show=$(getvar "$QUERY_STRING" "sh")
[ "$show" = "topo" ] || show="dev"

[ $show = "dev" -a "$DEV_MODE" = "1" ] && fts=1 || fts=0

json="$(ubus call ajconfd.Device GetNetworkConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var brmode "Wan bridge mode"
json_get_var nw_wanruntimegateway "Wan runtime gateway"
json_cleanup

if [ "$brmode" = "1" ]; then
	json="$(ubus call arp hosts 2>/dev/null)"
	json_load "$json" > /dev/null
	json_select "hosts" > /dev/null
	json_get_keys keys
	for k in $keys; do
		json_select "$k"
		json_get_var ip "ip"
		json_get_var mac "mac"
		if [ "$ip" = "$nw_wanruntimegateway" ]; then
			tprmac=$mac
			tprip=$ip
			break
		fi
		json_select ..
	done
fi

rmac="$(grep "eth0.macaddr" "/proc/ubnthal/board" | cut -d '=' -f2)"

ubus call ajconfd.ClusterNode ModifyEthernetPortInfoPollConfig '{"config": {"mode": "On", "interval": 2000}}' >/dev/null

echo "<link rel='stylesheet' href='css/info.css?v=1'>"

. head-stop

echo "<body>"

. body-nav

echo "
<div id='overlay' onclick='overlayoff();'></div>
<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1 style='margin-bottom:5px'>System Info</h1>
				<div style='margin-bottom:10px;'><a href='?sh=dev'>Devices</a> | <a href='?sh=topo'>Topology</a>"
if [ "$DEV_MODE" = "1" ]; then
	echo "
				 | <a href='?m=2'>Connections</a> | <a href='?m=3'>QoS</a> | <a href='?m=4'>Diagnostics</a>"
fi

echo "
				 </div>
			</div>
		</div>
	</div>
</div>"

if [ "$mode" = "1" ]; then
echo "
<div class='container' style='padding:0; '>
	<div id='data' style='display:none;'>&nbsp;</div>
	<div id='divgraph' style='display:none;'><svg id='graph' style='overflow: visible;'></svg></div>
	<div id='clientdetails' style='opacity: 0; transition: opacity 500ms; z-index: 1002; top:50%; left:50%; transform:translate(-50%, -50%); max-width:100%; width:500px; position:fixed; display:none; background: radial-gradient(#eee, #ccc, transparent);'>
		&nbsp;
	</div>
	<div id='selecticon' style='z-index: 1003; width:1140px; max-width: 100%; position:absolute; top:0px; display:none; background-color: #eee; padding:20px; border:1px solid #aaa; color: #000'>
		<input style='color:#000' placeholder='Enter a model name to search for a new icon' onkeyup='selecticon()' id='ss'/>
		<div id='iconlist'></div>
	</div>
</div>

<script src='js/clink.js'></script><script src='js/app.js?v=7'></script>
<script src='http://d3js.org/d3.v3.min.js'></script>

<script type='text/javascript'>
var tprmac='$tprmac'
var brmode='$brmode'
var show = '$show'
var token='$TOKEN'
var fts='$fts'
var rmac='$rmac'
</script>
<script src='js/info.js?v=9'></script>"

echo "</body>"

###############################################################################

elif [ "$mode" = "2" ]; then
[ "$DEV_MODE" != "1" ] && exit
echo "

<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js'></script>
<script type='text/javascript'>

	var showMap = false;

	var map;
	var poly;
	var path;

	homeLocation = [56.9458279,24.080503];

	function initialize() {
		if (!showMap) return;

		map = '';
		poly = '';
		path = '';

		// Initialise the map
		var map_options = {
			center: new google.maps.LatLng(homeLocation[0], homeLocation[1]),
			zoom: 2,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};

		map = new google.maps.Map(document.getElementById('map'), map_options);

		var circle = {
			path: google.maps.SymbolPath.CIRCLE,
			fillColor: 'orange',
			fillOpacity: 1,
			scale: 5,
			strokeColor: 'white',
			strokeWeight: 1
		};

		var marker = new google.maps.Marker({
			position: new google.maps.LatLng(homeLocation[0], homeLocation[1]),
			icon: circle,
			map: map
		});

	}

	function mapShow() {
		document.getElementById('map').style.display = 'block';
		showMap = true;
		initialize();
	}


</script>
<script async defer
	src='https://maps.googleapis.com/maps/api/js?key=AIzaSyByrgAtWpei65izUKYhdyr9-r54rrMZ8Zc&callback=initialize'>
</script>
"

echo "

<div class='container'>
	<div id='map' style='display:none'>&nbsp;</div>
	<div id='data'>&nbsp;</div>
	<div id='filter' style='background-color: #fff'>SRC IP<input id='src'> DST IP<input id='dst'> Min bytes<input id='bytes'></div>
	<div><a href='#' onclick='mapShow();' return false;>Show map</a></div>
</div>

<script src='js/clink.js'></script><script src='js/app.js?v=7'></script>
<script type='text/javascript'>


	function isPrivateIp(ip) {
		regex = /(^127\.)|(^10\.)|(^172\.1[6-9]\.)|(^172\.2[0-9]\.)|(^172\.3[0-1]\.)|(^192\.168\.)|(^255\.255\.)|(^239\.255\.)/gm;
		if ( ip.match(regex) ) {
			return true;
		} else {
			return false;
		}
	}

	stateTimerInterval = 20000;

	function stateTimer() {
		clearTimeout(timeout);
		timeout = setTimeout('getState();', 5000);
		clearInterval(timer);
		timer = setInterval('getState();', stateTimerInterval);
	}

	var ttt = []; // graph data
	var ipToLocation = [];

	function getGeoLocation(ip) {
		if (!showMap) return;
		if (ipToLocation[ip]) {
			addMarker(ip);
			return ipToLocation[ip];
		}
		ipToLocation[ip] = 'Please wait..';

		var xhttp;
		xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function() {
			if (this.readyState == 4) {
				if (this.status == 200) {
					processGeoIpResponse(xhttp, ip);
				}
			}
		};
		xhttp.open('GET', 'http://riga.corp.ubnt.com/geoip/dev.php?ip='+ip, true);
		xhttp.send();
	}

	function processGeoIpResponse(xhttp, ip) {
		try {
			var results = JSON.parse(xhttp.responseText);
			var x = [];
			x['lat'] = results[0]['latitude'];
			x['lon'] = results[0]['longitude'];
			ipToLocation[ip] = x;
			addMarker(ip)
		} catch (e) {
			console.log('error:', e);
			console.log(ip, xhttp.responseText);
		}
	}

	var prev = {};
	function processResponse(xhttp) {
		stateTimer();
		offlineTimer();

		var all = []; // flat client data
		//var data = [];

		initialize();

		filter['src'] = document.getElementById('src').value;
		filter['dst'] = document.getElementById('dst').value;
		filter['bytes'] = Number(document.getElementById('bytes').value);

		try {
			var str = xhttp.responseText;
			//ipv4     2 tcp      1 1234 ESTABLISHED src=1.2.3.4 dst=5.6.7.8 sport=23456 dport=111 packets=1234 bytes=123456 src=5.6.7.8 dst=9.10.11.12 sport=111 dport=23456 packets=3456 bytes=34567 [ASSURED] mark=3 zone=0 use=2
			const regex = /^(\S+)\s+\S+\s+(\S+).+src=(\S+)\s+dst=(\S+)\ssport=(\S+)\sdport=(\S+)\s.+bytes=(\S+)\s.+bytes=(\S+).+mark=(\S+)\s/gm;
			let m;
			while ((m = regex.exec(str)) !== null) {
				if (m.index === regex.lastIndex) {
					regex.lastIndex++;
				}

				var x = [];

				x['proto'] = proto = m[2]
				x['src'] = src = m[3]
				x['dst'] = dst = m[4]
				x['sprt'] = sprt = m[5]
				x['dprt'] = dprt = m[6]
				x['btx'] = btx = m[7]
				x['brx'] = brx = m[8]

				thisid = proto + '-' + src + '-' + dst + '-' + sprt + '-' + dprt;
				if (prev[thisid]) {
					x['ptx'] = btx - prev[thisid]['btx']
					x['prx'] = brx - prev[thisid]['brx']
					prev[thisid]['time']++
				} else {
					x['ptx'] = 0;
					x['prx'] = 0;
					prev[thisid] = {}
					prev[thisid]['time']=0
				}

				x['mrk'] = m[9]
				x['time'] = prev[thisid]['time']

				prev[thisid]['btx'] = btx
				prev[thisid]['brx'] = brx

				if (filter['src'] && filter['src'] != src) continue;
				if (filter['dst'] && filter['dst'] != dst) continue;
				if (filter['bytes'] && filter['bytes'] > btx && filter['bytes'] > brx) continue;

				if (dst && !isPrivateIp(dst)) {
					getGeoLocation(dst)
					all.push(x);
					//data[dst] = x;
				}

				//console.log(src, dst, prt, btx, brx)
			}

			makeTable(all);


		} catch (e) {
			console.log('error:', e);
		}
	}

	function addMarker(ip) {
		if (!showMap) return;
		if (!ip) {
			return false;
		}

		lat = ipToLocation[ip]['lat'];
		lon = ipToLocation[ip]['lon'];

		if (lat && lon) {
			var circle = {
				path: google.maps.SymbolPath.CIRCLE,
				fillColor: 'red',
				fillOpacity: .3,
				scale: 4.5,
				strokeColor: 'white',
				strokeWeight: 1
			};

			var marker = new google.maps.Marker({
				position: new google.maps.LatLng(lat, lon),
				icon: circle,
				map: map
			});

			var contentString = '<div>' + ip + '</div>';

			var infowindow = new google.maps.InfoWindow({
				content: contentString
			});

			marker.addListener('click', function() {
				infowindow.open(map, marker);
			});
			addPath(ip);
		}
	}


	function addPath(ip) {
		if (!showMap) return;
		lat = ipToLocation[ip]['lat'];
		lon = ipToLocation[ip]['lon'];

		poly = new google.maps.Polyline({
			geodesic: true,
			strokeColor: '#f80',
			strokeOpacity: 0.3,
			strokeWeight:3,
			map:map
		});
		path = poly.getPath();

		path.push(new google.maps.LatLng(homeLocation[0], homeLocation[1]));
		path.push(new google.maps.LatLng(lat, lon));

	}

	function makeTable(data) {
		var table = '<table border=1 style=\'background-color:#fff\'>';
		table += '<tr>';
		for (var row in data) {
			for (var item in data[row]) {
				table += '<th>' + item + '</th>';
			}
			break;
		}

		for (var row in data) {
			table += '<tr>';
			for (var item in data[row]) {
				table += '<td>' + data[row][item] + '</td>';
			}
			table += '</tr>';
		}

		document.getElementById('data').innerHTML = table;
	}

	function roundTens(n) {
		if (n == 0) return 0;
		if (n < 10) return (n).toFixed(1);
		return Math.round(n);
	}

	function bkmg(b, append='') {
		if (b < 1000) return b + ' b' + append;
		if (b < 1000*1000) return roundTens(b/1000) + ' kb' + append;
		if (b < 1000*1000*1000) return roundTens(b/1000/1000) + ' Mb' + append;
		return roundTens(b/1000/1000/1000) + ' Gb' + append;
	}

	function getState() {
		var xhttp;
		xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function() {
			if (this.readyState == 4) {
				if (this.status == 200) {
					processResponse(xhttp);

				}
				else if ( this.status == 0 ) {
					setTimeout('getState();', 5000);

				}
			}
		};
		xhttp.open('POST', 'info-async.php', true);
		xhttp.timeout = 5000;
		//xhttp.send('do=GetWifiClientInfo');
		xhttp.send('do=conntrack&token=$TOKEN');
	}

	function offlineTimer() {
		clearTimeout(offlineTimeout);
		offlineTimeout = setTimeout('deviceOffline();', offlineTimerInterval);
		deviceOnline();
	}

	function deviceOnline() {
		amplifiJsUtil.vault().connectionLostNotice(false);
	}

	function deviceOffline() {
		amplifiJsUtil.vault().connectionLostNotice('Connection lost. Please check you are connected to AmpliFi Mesh system.');
	}

	getState();
	offlineTimer();

</script>

</body>
"

###############################################################################

elif [ "$mode" = "3" ]; then
[ "$DEV_MODE" != "1" ] && exit

json="$(ubus call ajconfd.Device GetQosConfig 2>/dev/null)"
json_load "$json" > /dev/null
json_get_var qos_runtime_enabled "runtime_enabled"
json_cleanup

echo "

<div class='container'>"

if [ "$qos_runtime_enabled" != "1" ]; then
	echo "
	<div style='text-align: center;'><a href='settings.php'>QoS not enabled, enable in settings</a></div>"
	. html-stop
	exit
else
	echo "
	<div style='text-align: right;'><a href='qos.php'>Configure QoS</a></div>"
fi

echo "
	<div id='data'></div>
</div>
<script type='text/javascript'>

	period = 2;

	stateTimerInterval = 60000;
	timeout = '';
	timer = '';

	function stateTimer() {
		clearTimeout(timeout);
		timeout = setTimeout('getState();', period * 1000);
		clearInterval(timer);
		timer = setInterval('getSatte();', stateTimerInterval);
	}

	var data = [];
	var data_ts = [];

	function processResponse(xhttp) {
		stateTimer();

		var QoSConfig = [];
		var ttt = [];
		var temp = [];

		if (data['this'] !== undefined) {
			data['prev'] = [];
			data['prev'] = data['this'];
			data['calc'] = data['this'];
			data_ts['prev'] = data_ts['this'];
		}

		data['this'] = [];
		data_ts['this'] = [];

		try {
			var str = xhttp.responseText;
			lines = str.split(\"\n\");
			lines.forEach(function(line) {
				if (line) {
					name = line.split(' ')[0];
					items = line.split(' ').slice(1).join(' ').split(';');

					if (name == 'ts') {
						data_ts['this'] = line.split(' ')[1];
					}
					else if (name == 'classes' || name == 'tcname') {
						QoSConfig[name] = items;
					}
					else {
						temp[name] = items;
					}
				}
			});

			//tci - interfaces, tcn - names, tcs - statistics
			for (var index in temp['tci']) {
				iface = temp['tci'][index];
				name = temp['tcn'][index];
				stat = temp['tcs'][index].split(',');

				//console.log(iface, name, stat);
				if (data['this'][iface] === undefined) {
					data['this'][iface] = [];
					data['this'][iface]['name'] = [];
					data['this'][iface]['stat'] = [];
				}

				data['this'][iface]['name'][index] = name;
				data['this'][iface]['stat'][index] = stat;

			}

			if (typeof data['prev'] !== 'undefined' && Object.keys(data['prev']).length > 0) {
				var period_delta = data_ts['this'] - data_ts['prev'];
				for (var iface in data['prev']) {
					class_idx = 0;
					for (var index in data['prev'][iface]['name']) {
						data['prev'][iface]['stat'][index].forEach(function(value, valueindex) {
							data['calc'][iface]['stat'][index][valueindex] = Math.round((data['this'][iface]['stat'][index][valueindex] - data['prev'][iface]['stat'][index][valueindex]) * 1000 / period_delta, 0);
						});
						x = {};
						x['iface'] = iface;

						tcn = data['calc'][iface]['name'][index].replace(' refcnt','');
						if (tcn.includes('parent')) {
							x['qdisc'] = tcn + ' ( ' + QoSConfig['classes'][class_idx] + ' )';
							class_idx++;
						} else {
							x['qdisc'] = tcn;
						}

						x['tput'] = bkmg(data['calc'][iface]['stat'][index][0]*8,'ps')
						x['pkts'] = data['calc'][iface]['stat'][index][1]
						x['dropped'] = data['calc'][iface]['stat'][index][2]
						x['overlimits'] = data['calc'][iface]['stat'][index][3]
						x['requeues'] = data['calc'][iface]['stat'][index][4]
						ttt.push(x);
					}
				}
			}
			makeTable(ttt);
		} catch (e) {
			console.log('error:', e);
		}
	}


	function roundTens(n) {
		if (n == 0) return 0;
		if (n < 10) return (n).toFixed(1);
		return Math.round(n);
	}

	function bkmg(b, append='') {
		if (b < 1000) return b + ' b' + append;
		if (b < 1000*1000) return roundTens(b/1000) + ' kb' + append;
		if (b < 1000*1000*1000) return roundTens(b/1000/1000) + ' Mb' + append;
		return roundTens(b/1000/1000/1000) + ' Gb' + append;
	}

	function getState() {
		var xhttp;
		xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function() {
			if (this.readyState == 4) {
				if (this.status == 200) {
					processResponse(xhttp);
				}
				else if ( this.status == 0 ) {
					setTimeout('getState();', 20000);

				}
			}
		};
		xhttp.open('POST', 'info-async.php', true);
		xhttp.timeout = 20000;
		xhttp.send('do=qosstat&token=$TOKEN');
	}
	getState();

	function makeTable(data) {
		var table = '<table border=1 style=\'background-color:#fff\'>';
		table += '<tr>';
		for (var row in data) {
			for (var item in data[row]) {
				table += '<th>' + item + '</th>';
			}
			break;
		}

		for (var row in data) {
			table += '<tr>';
			for (var item in data[row]) {
				table += '<td>' + data[row][item] + '</td>';
			}
			table += '</tr>';
		}

		document.getElementById('data').innerHTML = table;
	}
</script>
"

###############################################################################

elif [ "$mode" = "4" ]; then
[ "$DEV_MODE" != "1" ] && exit
echo "
<div class='container'>
	<div id='data' style='background:white; opacity: 90%; padding: 10px; border:1px solid #888'>
		<pre style='font-size: 13px; white-space: pre-wrap'>"

echo "<strong>ubus call ajconfd DiagnosticInfo</strong>"
ubus call ajconfd DiagnosticInfo | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g; s/"/\&quot;/g; s/'"'"'/\&#39;/g'

echo "<hr><strong>ubus call ajconfd.ClusterNode GetClusterNodeTopology</strong>"
ubus call ajconfd.ClusterNode GetClusterNodeTopology | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g; s/"/\&quot;/g; s/'"'"'/\&#39;/g'

echo "<hr><strong>ubus call ajconfd.Device GetNetworkConfig</strong>"
ubus call ajconfd.Device GetNetworkConfig | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g; s/"/\&quot;/g; s/'"'"'/\&#39;/g'

echo "<hr><strong>ps ww</strong>"
ps ww | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g; s/"/\&quot;/g; s/'"'"'/\&#39;/g'

echo "<hr><strong>top -b -n1</strong>"
top -b -n1 | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g; s/"/\&quot;/g; s/'"'"'/\&#39;/g'

echo "<hr><strong>logread -l200</strong>"
logread -l200 | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g; s/"/\&quot;/g; s/'"'"'/\&#39;/g'

echo "<hr><strong>uci show | sort</strong>"
uci show | sort | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g; s/"/\&quot;/g; s/'"'"'/\&#39;/g'

echo "</pre>
	</div>
</div>"


fi
. html-stop
