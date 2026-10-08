#!/bin/sh

. ../inc/vars
. functions

log_debug "fwupdate.php"

validated=0

if [ "$SESSION" = "invalid" -a "$(get_setup_done)" = "1" ]; then
	if [ "$REQUEST_METHOD" = "POST" ]; then
		cat >/dev/null
	fi
	set_returnurl "fwupdate.php"
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then

	get_token=$(getvar "$QUERY_STRING" "token")

	if [ -n "$get_token" ]; then
		if [ "$get_token" != "$TOKENGET" ]; then
			log_debug "fwupdate.php bad token"
			cat >/dev/null
			. html-start
			. head-start
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='fwupdate.php'\" />"
			. head-stop
			. html-stop
			exit
		fi
	else
		read QUERY_STRING

		post_token=$(getvar "$QUERY_STRING" "token")

		if [ "$post_token" != "$TOKEN" ]; then
			log_debug "fwupdate.php bad post token"
			cat >/dev/null
			. html-start
			. head-start
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='fwupdate.php'\" />"
			. head-stop
			. html-stop
			exit
		fi

		do=$(getvar "$QUERY_STRING" "do")

		if [ "$do" = "remove_factory_pairing" ]; then
			. html-start
			. head-start
			echo "Removing pairing info and restarting..."
			. head-stop
			. html-stop

			prst_tool -e pairing &> /dev/null
			reboot &> /dev/null

			exit
		fi
	fi

	rm -rf $FWFILEDOWNL &> /dev/null
	cat | sed 1,4d | sed '$d' | head -c-2 >$FWFILE

	uh-fw-tool -c $FWFILE &> /dev/null
	if [ $? -eq 0 ]; then
		validated=1
		echo "$REMOTE_ADDR" > "/tmp/webui.fwupdate"
	else
		rm -f $FWFILE
	fi
fi

ptype="$(get_ptype)"

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
					<h1>Firmware Update</h1>
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
"

if [ "$validated" = "1" ]; then
echo "\
<div class='success-message'>
	<h3 class='green'>Successful upload</h3>
	<p>Firmware file <strong>validated</strong></p>
	<form action='update.php' method='post'>
		<input type='hidden' name='token' value='$TOKEN'>
		<input class='cta-main-center' type='submit' value='Update Now'/>
	</form>
</div>
"
elif [ "$REQUEST_METHOD" = "POST" ]; then
echo "\
<div class='success-message'>
	<h3 class='red'>Invalid firmware file!</h3>
	<input class='cta-main-center' type='button' class='button_active' onclick=\"location.href='fwupdate.php';\" value='Retry'/>
</div>
"
else
echo "\
<div class='form-wrap form-wrap-fwupdate'>
	<form action='fwupdate.php?token=$TOKENGET' method='post' enctype='multipart/form-data'>
		<div class='group'>
			<h4>Select a file to upload</h4>
			<div class='upload-item'>
				<div class='upload-input'>
					<input id='fw-file-upload' type='file' name='file'>
				</div>
				<div class='upload-label'>Click to select a file</div>
			</div>
			<div class='upload-item-extension' id='fw-file-upload-file-name'>No file selected.</div>
			<div class='processing'></div>
			<input class='cta-main-center trigger-processing' type='submit' value='Upload'>
		</div>
	</form>
</div>
"
fi

if [ -f /etc/init.d/recovery -a "$(prst_tool -r pairing 2> /dev/null | grep -iq "}"; echo "$?")" = "0" ]; then
echo "\
<div class='contacts'>
	<div class='contact-row'>
		<div class='item-label'>Factory pairing</div>
		<div class='item-value'>
			<form id='remove_factory_pairing' action='fwupdate.php' method='post'>
				<input type='hidden' name='do' value='remove_factory_pairing' />
				<input type='hidden' name='token' value='$TOKEN'>
				<a href='#' onclick='if (confirm(\"Are you sure you want to remove pairing info?\")) document.getElementById(\"remove_factory_pairing\").submit();'>Remove factory pairing from a kit device</a>
			</form>
		</div>
	</div>
</div>
"
fi

echo "\
                    </div>

                </div>
            </div>
        </div>
    </div>
"

echo "<script src='js/clink.js'></script><script src='js/app.js?v=4'></script></body>"

. html-stop
