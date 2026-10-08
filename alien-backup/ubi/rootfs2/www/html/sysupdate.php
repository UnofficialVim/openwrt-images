#!/bin/sh

. ../inc/vars
. functions

log_debug "sysupdate.php"

if [ "$SESSION" = "invalid" -a "$(get_setup_done)" = "1" ]; then
	set_returnurl "sysupdate.php"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	echo "Content-type: application/json"
	echo "Cache-Control: no-cache, no-store, must-revalidate"
	echo "Pragma: no-cache"
	echo "Expires: 0"
	echo ""

	read QUERY_STRING
	d=$(getvar "$QUERY_STRING" "do")
	if [ "$d" = "begin" ]; then

		post_token=$(getvar "$QUERY_STRING" "token")
		if [ "$post_token" != "$TOKEN" ]; then
			log_debug "sysupdate.php bad token"
			echo '{"result": "bad_token" }'
			exit
		fi

		ubus call ajconfd.FwUpdate StartClusterUpgrade >/dev/null
		echo '{"result": "ok" }'
	fi
	exit
fi

. html-start
. head-start

done=$(getvar "$QUERY_STRING" "done")
[ -z "$done" ] && ubus call ajconfd.FwUpdate CheckForFwUpdate >/dev/null

json="$(ubus call ajconfd.FwUpdate GetAvailableUpdates 2>/dev/null)"
err=$(echo $?)

if [ "$err" -eq 4 ]; then
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='fwupdate.php'\" />"
	. head-stop
	. html-stop
	exit
fi

json_load "$json" > /dev/null
json_get_var fwstatus "status" > /dev/null
if [ "$fwstatus" = "pending" ]; then
	still_checking=1
	echo "<meta http-equiv=\"refresh\" content=\"3;URL='sysupdate.php?done=1'\" />"
elif [ "$fwstatus" = "available" ]; then
	json_select "nodes" > /dev/null
	json_get_keys keys
	for m in $keys; do
		json_select "$m" > /dev/null
		json_get_var type "type"

		json_select "current" > /dev/null
		json_get_var current_v "version"
		json_get_var current_r "revision"
		json_select ..

		json_select "upgrade" > /dev/null
		json_get_var upgrade_v "version"
		json_get_var upgrade_r "revision"
		json_get_var upgrade_c "code"
		json_select ..

		peers_updates="$m $current_v $upgrade_v $current_r $upgrade_r "$'\n'"$peers_updates"

		json_select ..
	done
fi

devices_count=1
json="$(ubus call ajconfd.ClusterNode GetPeerList 2>/dev/null)"
json_load "$json" > /dev/null
json_get_keys keys
for k in $keys; do
	json_select "$k" > /dev/null
	json_get_var flags "flags"
	json_get_var ip "ip"
	peers_ip="$k $ip"$'\n'"$peers_ip"
	devices_count=$((devices_count + 1))
	json_select
done

if [ "$SHORTNAME" = "AFi-ALN-R" -o "$SHORTNAME" = "AFi-ALN-P" ]; then
	update_time=2
elif [ "$devices_count" = "1" ]; then
	update_time=3
else
	update_time=5
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
				<h1>Firmware Version Update</h1>
			</div>
		</div>
	</div>
</div>
<div class='container'>
	<div class='row'>
		<div class='col-app-container col-xs-12'>
			<div class='base-modal node--no-padding'>

				<div class='base-modal-content'>

					<div class='select-device'>
						<div class='node--body'>"

mymac=$(cat /proc/ubnthal/board | grep eth0 | cut -d'=' -f2 | tr ':' '_')
json="$(ubus call ajconfd.ClusterNode GetRegisteredNodeListJson 2>/dev/null)"


for show_role in "Router" "Extender"; do
	json_load "$json" > /dev/null
	json_select "nodes" > /dev/null
	json_get_keys keys
	for mac in $keys; do
		json_select "$mac" > /dev/null
		json_get_var role "role" # Router, Extender
		json_get_var friendly_name "friendly_name"
		json_get_var platform_name "platform_name"
		json_get_var platform_modification "platform_modification"
		sep=
		if [ "$role" = "$show_role" ]; then
			ip=$(echo "$peers_ip" | grep "$mac" | cut -d' ' -f2)
			if [ ! -z "$ip" -o "$mac" = "$mymac" ]; then
				[ ! -z "$js" ] && sep=","

				if [ "$fwstatus" = "available" ]; then
					current_v=$(echo "$peers_updates" | grep "$mac" | cut -d' ' -f2)
					current_r=$(echo "$peers_updates" | grep "$mac" | cut -d' ' -f4)
					upgrade_v=$(echo "$peers_updates" | grep "$mac" | cut -d' ' -f3)
					upgrade_r=$(echo "$peers_updates" | grep "$mac" | cut -d' ' -f5)
				fi

				if [ ! -z "$upgrade_v" ]; then
					update_is_available=1
					upgrade_string="\
																<div class='update_msg'>
																	<span class='image' style='color:#AAAAAA'>Update available<br /></span>
																	<span class='version-current' title='v$current_v $current_r'>v$current_v</span>
																	<span class='version-direction direction--next'></span>
																	<span class='version-next' title='v$upgrade_v $upgrade_r'>v$upgrade_v</span>
																</div>"
				elif [ "$still_checking" = "1" ]; then
					upgrade_string="\
																<div>
																	<span class='node--rollback-section'><span class='item-version'><span class='image'><img src='img/loading-ring--1.gif' style='margin-top;height:32px;width:32px'><br /></span><span style='color:#AAAAAA'>Checking</span></span>
																</div>"
				else
					upgrade_string="\
																<div>
																	<span style='color:#AAAAAA'>No update available</span>
																</div>"

				fi

				item_name=$(platform_info "shorter_name" "$platform_name$platform_modification")
				item_image=$(platform_info "image" "$platform_name$platform_modification")

				[ -z "$ip" ] && fwurl="fwupdate.php" || fwurl="http://$ip/fwupdate.php"

				friendly_name="$(esc_html "${friendly_name}")"

				echo "
									<div class='item'>
										<a class='item-link' href='$fwurl'></a>
										<div class='item-image $item_image'></div>
										<div class='app-t'>
											<div class='app-tr'>
												<div class='app-tc'>
													<div class='item-name smaller'>$item_name</div>
													<div class='item-id'>$friendly_name</div>
													<div id='action-$mac'>
														<span class='node--rollback-section'>
															<span class='item-version'>
																$upgrade_string
															</span>
														</span>
													</div>

												</div>
											</div>
										</div>
									</div>
									"
			fi
		fi
		json_select ..
	done
done


if [ ! -z "$update_is_available" ]; then
	echo "
								<div class='group' id='update-button' style='display:block;margin-top:30px;padding:10px'>
									<span> Estimated update time is $update_time minutes. AmpliFi devices will restart during the update.</span>
									<input class='cta-main-center-fix' type='button' style='margin-top:35px' value='Begin update' onclick='beginUpdate()'>
								</div>"
fi
echo "

						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script src='js/clink.js'></script><script src='js/app.js?v=4'></script>

<script>

function beginUpdate() {
	var xhttp;
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				var result = JSON.parse(xhttp.responseText);
				if (result['result']) {
					if (result['result'] == 'ok') {
						console.log('Update begin');
						var elements = document.getElementsByClassName('update_msg');
						for(var i=0; i < elements.length; i++) { 
							elements[i].innerHTML = '<span class=\'node--rollback-section\'><span class=\'item-version\'><span class=\'image\'><img src=\'img/loading-ring--1.gif\' style=\'margin-top;height:32px;width:32px\'><br /></span><span style=\'color:#AAAAAA\'>Updating</span></span>';
						}
						document.getElementById('update-button').style.display = 'none';
						setTimeout('redirectWhenOnline(\'$(get_homeurl)\',300000)', 30000);
					}
				}
			}
			else if ( this.status == 0 ) {
				setTimeout('beginUpdate();', 20000);
			}
		}
	};
	xhttp.open('POST', 'sysupdate.php', true);
	xhttp.timeout = 20000;
	xhttp.send('do=begin&token=$TOKEN');
}

</script>
"

echo "\
					</div>

				</div>
			</div>
		</div>
	</div>
"

echo "<script src='js/clink.js'></script><script src='js/app.js?v=4'></script></body>"

. html-stop
