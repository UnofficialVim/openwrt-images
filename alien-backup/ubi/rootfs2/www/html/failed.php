#!/bin/sh

. ../inc/vars
. functions

log_debug "failed.php"

. html-start
. head-start

if [ "$(get_setup_done)" = "1" ]; then
    echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
    . head-stop
    . html-stop
    exit
fi

cat head.html
. head-stop

reason=$(getvar "$QUERY_STRING" "reason")
ptype="$(get_ptype)"
pshortname="$(get_pshortname)"

if [ "$ptype" = "MeshPoint" ]; then
	echo "Error 24"
	exit
fi

echo "<body>"

. body-nav

echo "\

	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1 class='red'>An Issue Has Been Detected</h1>
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

                        <div class='success-message'>
"
if [ "$reason" = "nolink" ]; then
echo "\
                            <h3 class='red'>No Cable Connected</h3>
                            <p>Ensure your Ethernet cable is connected from the $PNAME Router to your modem. Verify the modem is powered on.</p>
                            <br>
"
get_wan_cable_img "$pshortname"

elif [ "$reason" = "noip" ]; then
echo "\
                            <h3 class='red'>No IP Address Detected</h3>
                            <p>Ensure your Ethernet cable is connected from the $PNAME Router to your modem. Verify the modem is powered on.</p>
                            <br>
"
get_wan_cable_img "$pshortname"

else
echo "\
                            <h3 class='red'>No Internet Connection</h3>
                            <p>Ensure your Ethernet cable is connected from the $PNAME Router to your modem. Verify the modem is powered on.</p>
                            <br>
"
get_wan_cable_img "$pshortname"

fi
echo "\
                            <div class="custom-conf">
                            <p class='small-text'>Configure DHCP, Static or PPPoE settings manually?</p>
                            <input class='cta-main-center-fix' type='button' class='button_active' onclick=\"location.href='settings.php';\" value='Configure Manually'>
                            </div>
                            <p class='small-text'>or</p>
                            <input class='cta-main-center-fix' type='button' class='button_active' onclick=\"location.href='setup.php?retry=1';\" value='Retry'>
                            <p align='center'><a href='setup.php?ic=0'>Skip</a></p>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>
"

echo "</body>"

. html-stop
