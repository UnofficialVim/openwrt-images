#!/bin/sh

. ../inc/vars
. functions

if [ "$DEV_MODE" != "1" ]; then
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
	. head-stop
	. html-stop
	exit
fi

is=0
this="dns-filter.php"
default_user="00:00:00:00:00:00"

dnsmasq_bls_conf="/tmp/dnsmasq.d/dns-filter-db.auto.conf"
try=0
dnsmasq_bls_conf_mod=""
while [ -z "$dnsmasq_bls_conf_mod" ] || [ "$dnsmasq_bls_conf_mod" != "$(date +%s -r $dnsmasq_bls_conf 2>/dev/null)" ]; do
	let try++
	[ $try -gt 2 ] && break
	dnsmasq_bls_conf_mod="$(date +%s -r "$dnsmasq_bls_conf" 2>/dev/null)"
	[ -f "$dnsmasq_bls_conf" ] && blacklists="$(awk -F'[[:space:],=]+' '{\
		if (NF == 3 && \
			$1 == "blacklist" && \
			substr($2,0,1) != "#" && \
			substr($3,0,1) != "#")\
			{print $3}\
	}' $dnsmasq_bls_conf 2>/dev/null)" || sleep 1
done
blacklists="$blacklists $(uci get dhcp.@dnsmasq[0].blacklist 2>/dev/null | awk '{\
	for (i=1;i<=NF;++i) {\
		split($i,s,","); print s[2]\
	}\
}')"
blacklists=$(echo $blacklists | sort | uniq)

blacklists_binds_default=$(uci get dhcp.@dnsmasq[0].bind_blacklists 2>/dev/null | awk '{\
	for (i=1;i<=NF;++i) {\
		n=split($i,s,","); \
		if (s[1] == "'$default_user'") {\
			for (j=3;j<=n;++j) {print s[j]} \
		}\
	}\
}')

blacklists_binds_other=$(uci get dhcp.@dnsmasq[0].bind_blacklists 2>/dev/null | awk '{\
	for (i=1;i<=NF;++i) {\
		n=split($i,s,","); \
		if (s[1] != "'$default_user'") {print $i}\
	}\
}')

is_in()
{
	for i in "${@:2}"; do [ "$i" = "$1" ] && return 0; done
	return 1
}

[ -f $SETUPFILE ] && is=1

. html-start
. head-start

if [ "$SESSION" = "invalid" -a "$is" = "0" ]; then
	echo "$this" > "$RETURNURL"
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

ptype="$(get_ptype)"
role="$(get_role)"

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING

	blacklists_binds=$blacklists_binds_other
	bls=""
	for bl in $(getvar "$QUERY_STRING" "balcklists"); do
		is_in $bl $blacklists && bls="$bls,$bl"
	done
	[ -n "$bls" ] && blacklists_binds="$blacklists_binds $default_user,$bls"
	uci delete dhcp.@dnsmasq[0].bind_blacklists 2>/dev/null
	for bb in $blacklists_binds; do
		uci add_list dhcp.@dnsmasq[0].bind_blacklists="$bb" 2>/dev/null
	done
	uci commit 2>/dev/null && \
	/etc/init.d/dnsmasq restart 2>/dev/null && \
	cfg.sh write &> /dev/null

	if [ "$is" = "1" -a $? -ne 0 ]; then
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='failed.php'\" />"
		. head-stop
		. html-stop
		exit
	elif [ "$is" = "1" ]; then
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='setup.php?retry=1'\" />"
		. head-stop
		. html-stop
		exit
	else
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='$this'\" />"
		. head-stop
		. html-stop
		exit
	fi
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
					<h1>$PNAME DNS Filter</h1>
				</div>
			</div>
		</div>
	</div>
	<div class='container'>
		<div class='row'>
			<div class='col-app-container col-xs-12'>
				<div class='base-modal'>
"
					get_modal_header "$ptype"
echo "
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

						<div class='form-wrap'>
"
if [ -n "$blacklists" -a "$ptype" = "Router" -a "$role" = "router" ]; then
	echo "
							<!-- FORM STARTS -->
							<form action='$this' method='post'>
								<div id='dns_filter'>
									<div class='group'>
										<label class='static-label'>Blacklists</label>
	"
	for bl in $blacklists; do
		checked=$(is_in $bl $blacklists_binds_default && echo "checked=\"checked\"")
		echo "
										<div class='checkbox-item' style='margin-top:10px; margin-bottom:10px; '>
											<input type='checkbox' class='checkbox-input' name='balcklists' value='$bl' $checked>
											<div class='checkbox-check'></div>
											<div class='checkbox-label'><span style='font-weight:normal;'>$bl</span></div>
										</div>
		"
	done
	echo "
									</div>
								</div>

								<div class='group'>
									<input class='cta-main-center' type='submit' value='Save & Continue'>
								</div>
							</form>
							<!-- FORM ENDS -->
						</div>

					</div>
				</div>
			</div>
		</div>
	</div>
	"
else
	echo "
							<div class='success-message'>
								<h3 class='red'>Blacklists not found!</h3>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	"
fi

echo "<script src='js/clink.js'></script><script src='js/app.js'></script></body></body>"

. html-stop
