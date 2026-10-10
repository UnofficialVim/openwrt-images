#!/bin/sh

. ../inc/vars
. functions

log_debug "qos.php"

. html-start
. head-start

if [ "$DEV_MODE" != "1" ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$SESSION" = "invalid" -a "$(get_setup_done)" = "1" ]; then
	set_returnurl "qos.php"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then

	read QUERY_STRING	
	do=$(getvar "$QUERY_STRING" "do")

	post_token=$(getvar "$QUERY_STRING" "token")
	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "qos.php configure bad token"
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	if [ "$do" = "add" ]; then
		class=$(getvar "$QUERY_STRING" "class")
		proto=$(getvar "$QUERY_STRING" "proto")
		src_host=$(getvar "$QUERY_STRING" "src_host")
		src_ports=$(getvar "$QUERY_STRING" "src_ports")
		dst_host=$(getvar "$QUERY_STRING" "dst_host")
		dst_ports=$(getvar "$QUERY_STRING" "dst_ports")
		comment=$(getvar "$QUERY_STRING" "comment")

		case "$class" in
		Voice|Video|BestEffort|Background)
			;;
		*)
			class="Video"
			;;
		esac

		case "$proto" in
		tcp|udp|icmp)
			;;
		*)
			proto=""
			;;
		esac

		src_ports="$(echo "$src_ports" | sed -e 's/[^[:digit:],-]//g;s/\(\d\+\)-\(\d\+\)/\[\1,\2\]/g')"
		dst_ports="$(echo "$dst_ports" | sed -e 's/[^[:digit:],-]//g;s/\(\d\+\)-\(\d\+\)/\[\1,\2\]/g')"
		src_host="$(echo "$src_host" | sed -e 's/[^0-9.]//g')"
		dst_host="$(echo "$dst_host" | sed -e 's/[^0-9.]//g')"
		comment="$(echo "$comment" | cut -c 1-80)"

		echo 'ubus call ajconfd.Device AddQosRule {"rule":{"class":"'"$class"'","proto":"'"$proto"'","src_host":"'"$src_host"'","src_ports":['"$src_ports"'],"dst_host":"'"$dst_host"'","dst_ports":['"$dst_ports"'],"comment":"'"$comment"'" }}' >> /tmp/debug_qos.log
		ubus call ajconfd.Device AddQosRule '{"rule":{"class":"'"$class"'","proto":"'"$proto"'","src_host":"'"$src_host"'","src_ports":['"$src_ports"'],"dst_host":"'"$dst_host"'","dst_ports":['"$dst_ports"'],"comment":"'"$comment"'" }}' > /dev/null 2>&1
	fi

	echo "<meta http-equiv=\"refresh\" content=\"0;URL='qos.php'\" />"
	. head-stop
	. html-stop
	exit

else
	do=$(getvar "$QUERY_STRING" "do")
	if [ "$do" ]; then

		get_token=$(getvar "$QUERY_STRING" "token")
		if [ "$get_token" != "$TOKENGET" ]; then
			log_debug "restore.php bad token"
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='restore.php'\" />"
			. head-stop
			. html-stop
			exit
		fi

		if [ "$do" = "del" ]; then
			rule=$(getvar "$QUERY_STRING" "rule")
			ubus call ajconfd.Device DelQosRules '{"rule_ids":["'"$rule"'"]}' > /dev/null 2>&1
		elif [ "$do" = "dis" ]; then
			rule=$(getvar "$QUERY_STRING" "rule")
			ubus call ajconfd.Device ModQosRule '{"rule":{"id": "'"$rule"'","enabled": false}}' > /dev/null 2>&1
		elif [ "$do" = "en" ]; then
			rule=$(getvar "$QUERY_STRING" "rule")
			ubus call ajconfd.Device ModQosRule '{"rule":{"id": "'"$rule"'","enabled": true}}' > /dev/null 2>&1
		fi
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='qos.php'\" />"
		. head-stop
		. html-stop
		exit
	fi
fi


cat head.html
echo "
<style>
	body {
		line-height: 1.25;
	}
	table {
		border: 1px solid #ccc;
		border-collapse: collapse;
		margin: 0;
		padding: 0;
		width: 100%;
		box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
	}
	table caption {
		font-size: 1em;
		margin: .5em 0 .75em;
	}
	table tr {
		background: #f8f8f8;
		border: 1px solid #ddd;
		padding: .35em;
	}
	table th,
	table td {
		padding: .625em;
		font-size: .75em;
		text-align: center;
	}
	table th {
		font-size: .75em;
		letter-spacing: .1em;
		text-transform: uppercase;
	}
	select {
		height: auto;
		margin: 0px;
		-webkit-appearance: menulist;
		-moz-appearance: menulist;
		appearance: menulist;
	}
	input {
		width: 100%;
		min-width:100px;
    	border: 1px solid #888;
    	padding: 1px;
    	font-size: 12px;
    	color: #000;
	}
	input:disabled {
		background-color: #bbb;
	}

	.node {
		/*cursor: pointer;*/
	}
	.node circle {
		fill: #fff;
		stroke: #009FE2;
		stroke-width: 4px;
	}
	.node text {
		font: 13px sans-serif;
		margin: 10px;
		fill: #135;
	}
	.link {
		fill: none;
		stroke: #009fe369;
		stroke-width: 3px;
	}

	#divgraph {
		margin-top: 20px;
		margin-bottom: 20px;
		background-color: #ffffffcc;
		border: #ddd 1px solid;
		box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
	}

	#map {
		height: 500px;
	}


</style>"

. head-stop
echo "<body>"
. body-nav
echo "<script src='js/clink.js'></script><script src='js/app.js?v=4'></script></body>"
. html-stop

echo "

<div class='app-section-title'>
	<div class='container'>
		<div class='row'>
			<div class='col-md-16 callout'>
				<h1>Quality of Service Rules</h1>
			</div>
		</div>
	</div>
</div>
<div class='container'>
<div id='data'>
<form id='qos' method='post' action='qos.php'>
<input type='hidden' name='token' value='$TOKEN'>
<input type='hidden' name='do' value='add' />
<table><tr><th>Priority<th>Protocol<th>Source host<th>Source ports<th>Destination host<th>Destination ports<th>Comment<th>Action
"

json="$(ubus call ajconfd.Device GetQosRules 2>/dev/null)"
json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k"
	json_get_var qos_enabled "enabled"
	json_get_var qos_class "class"
	json_get_var qos_proto "proto"
	json_get_var qos_src_host "src_host"	
	json_get_var qos_dst_host "dst_host"

	src_ports_list=
	json_get_type type "src_ports"
	if [ "$type" = "array" ]; then
		json_select "src_ports" > /dev/null
		i=1
		while json_get_var type $i; do #  && [ "$type" = int ]; do
			json_get_type type "$i"
			if [ "$type" = "array" ]; then
				json_select "$i"				
				json_get_var port_from 1
				json_get_var port_to 2
				src_ports_list="$src_ports_list,$port_from-$port_to"
				json_select ..
			else
				json_get_var port "$i"
				src_ports_list="$src_ports_list,$port"
			fi
			i=$((i+1))
		done
		json_select ..
		src_ports_list="$(echo "$src_ports_list" | tail -c +2)"
	fi

	dst_ports_list=
	json_get_type type "dst_ports"
	if [ "$type" = "array" ]; then
		json_select "dst_ports" > /dev/null
		i=1
		while json_get_var type $i; do #  && [ "$type" = int ]; do
			json_get_type type "$i"
			if [ "$type" = "array" ]; then
				json_select "$i"				
				json_get_var port_from 1
				json_get_var port_to 2
				dst_ports_list="$dst_ports_list,$port_from-$port_to"
				json_select ..
			else
				json_get_var port "$i"
				dst_ports_list="$dst_ports_list,$port"
			fi
			i=$((i+1))
		done
		json_select ..
		dst_ports_list="$(echo "$dst_ports_list" | tail -c +2)"
	fi

	json_get_var qos_comment "comment"

	if [ "$qos_class" = "Voice" ]; then qos_class="Highest";
	elif [ "$qos_class" = "Video" ]; then qos_class="High";
	elif [ "$qos_class" = "BestEffort" ]; then qos_class="Normal";
	elif [ "$qos_class" = "Background" ]; then qos_class="Background";
	fi

	[ "$qos_proto" ] || qos_proto="*"
	qos_proto="$(echo "$qos_proto" | tr a-z A-Z)"
	

	if [ "$qos_enabled" -eq 1 ]; then
		echo '<tr>'
	else
		echo '<tr style="background-color:#eee; color:#888">'
	fi

	echo "
	<td>$qos_class
	<td>$qos_proto
	<td>$qos_src_host
	<td>$src_ports_list
	<td>$qos_dst_host
	<td>$dst_ports_list
	<td>$qos_comment
	<td>"
	if [ "$qos_enabled" -eq 1 ]; then
		echo "<a href='qos.php?do=dis&rule=$k&token=$TOKENGET'>Disable</a>"
	else
		echo "<a href='qos.php?do=en&rule=$k&token=$TOKENGET'>Enable</a>"
	fi
	echo " | <a href='qos.php?do=del&rule=$k&token=$TOKENGET'>Delete</a>
	"

	json_select
done
json_cleanup

echo "
<tr>
<td><select name='class'><option value='Voice'>Highest</option><option value='Video' selected>High</option><option value='BestEffort'>Normal</option><option value='Background'>Background</option></select>
<td><select name='proto'><option value='any'>Any</option><option value='tcp'>TCP</option><option value='udp'>UDP</option></select>
<td><input name='src_host' id='src_host' autocomplete='off'/>
<td><input name='src_ports' id='src_ports' autocomplete='off'/>
<td><input name='dst_host' id='dst_host' autocomplete='off'/>
<td><input name='dst_ports' id='dst_ports' autocomplete='off'/>
<td><input name='comment' id='comment' autocomplete='off'/>
<td><a id='create' href='#'>Create a new rule</a>
</form>
</table>
</div>
<div style='background-color:#fff;padding:4px;margin-top:8px;color:#444'><p>Important: Custom QoS rules may interfere with Nvidia GeForce Now</p>
</div>

<script>

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

function validateSrc_ports() {
	var elm = document.getElementById('src_ports');
	var str = elm.value;
	if (str.length > 0) {
		document.getElementById('dst_ports').disabled = true;
	} else {
		document.getElementById('dst_ports').disabled = false;
	}
	str = str.replace(/[^\d|,|-]/g, '');
	elm.value = str;
}

function validateDst_ports() {
	var elm = document.getElementById('dst_ports');
	var str = elm.value;
	if (str.length > 0) {
		document.getElementById('src_ports').disabled = true;
	} else {
		document.getElementById('src_ports').disabled = false;
	}
	str = str.replace(/[^\d|,|-]/g, '');
	elm.value = str;
}

function validIp(ip) {
	if ( /^(?!0)(?!.*\.$)((1?\d?\d|25[0-5]|2[0-4]\d)(\.|$)){4}$/.test( ip ) ) {
		return true;
	}
	return false;
}

function validateSrc_host() {
	var elm = document.getElementById('src_host');
	var str = elm.value;
	if (str.length == 0) {
		elm.style.backgroundColor = '#fff';
		return;
	}
	if (validIp(str)) {
		elm.style.backgroundColor = '#8f8';
	} else {
		elm.style.backgroundColor = '#f88';
	}
}

function validateDst_host() {
	var elm = document.getElementById('dst_host');
	var str = elm.value;
	if (str.length == 0) {
		elm.style.backgroundColor = '#fff';
		return;
	}
	if (validIp(str)) {
		elm.style.backgroundColor = '#8f8';
	} else {
		elm.style.backgroundColor = '#f88';
	}
}

comment_prev = '';
function validateComment() {
	var elm = document.getElementById('comment')
	var str = elm.value;
	if (stringByteSize(str) < 81) {
		comment_prev = str;
	} else {
		elm.value = comment_prev;
	}
}


document.getElementById('src_ports').addEventListener('keyup', validateSrc_ports);
document.getElementById('dst_ports').addEventListener('keyup', validateDst_ports);
document.getElementById('src_host').addEventListener('keyup', validateSrc_host);
document.getElementById('dst_host').addEventListener('keyup', validateDst_host);
document.getElementById('comment').addEventListener('keyup', validateComment);

document.getElementById('create').addEventListener('click', function () {
	document.getElementById('qos').submit();
});

</script>
"
