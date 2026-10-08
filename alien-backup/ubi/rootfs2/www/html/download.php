#!/bin/sh

. ../inc/vars
. functions
ptype="$(get_ptype)"
role="$(get_role)"

log_debug "download.php"

. html-start
. head-start
cat head.html
. head-stop

echo "<body>"

. body-nav

echo "\

    <div class='app-section-title'>
        <div class='container'>
            <div class='row'>
                <div class='col-md-16 callout'>
                    <h1>Download the $PNAME App</h1>
                </div>
            </div>
        </div>
    </div>

    <div class='container'>
        <div class='row'>
            <div class='col-app-container col-xs-12'>
                <div class='base-modal'>
                    <div class='base-modal-header-app'>
                    </div>
                    <div class='base-modal-content'>
                        <div class='success-message'>
                            <p>The $PNAME app is available for iOS and Android devices</p>
                            <div class='app-icons'>
                                <a href='https://itunes.apple.com/us/app/amplifi-wifi/id1115225220?ls=1&mt=8'><img class='app-icon' src='img/App-Store-Icon.png' alt=''></a>
                                <a href='https://play.google.com/store/apps/details?id=com.ubnt.unifihome'><img class='app-icon' src='img/google_play_icon.png' alt=''></a>
"
if [ "$ptype" = "Router" -a "$role" = "router" ]; then
echo "\
                                <input class='cta-main-center' type='button' class='button_active' onclick=\"location.href='settings.php';\" value='Settings'/>
"
fi
echo "\
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
"

echo "</body>"

. html-stop
