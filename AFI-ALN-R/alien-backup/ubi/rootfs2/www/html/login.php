#!/bin/sh

. ../inc/vars
. functions

log_debug "login.php"

if [ "$(get_setup_done)" = "0" ]; then
	. html-start
	. head-start
	echo "<meta http-equiv=\"refresh\" content=\"0;URL='setup.php'\" />"
	. head-stop
	. html-stop
	exit
fi

if [ "$REQUEST_METHOD" = "POST" ]; then
	read QUERY_STRING

	password=$(getvar "$QUERY_STRING" "password")
	post_token=$(getvar "$QUERY_STRING" "token")

	if [ "$post_token" != "$TOKEN" ]; then
		log_debug "login.php bad token"
		. html-start
		. head-start
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php'\" />"
		. head-stop
		. html-stop
		exit
	fi

	exec 777<> /tmp/lock/webui-login.lock
	flock 777

	json_src=$(ubus call session login "{\"username\":\"ubnt\",\"password\":\"$password\",\"timeout\":1800}")
	if [ "$json_src" ]; then
		json_load "$json_src"
		json_get_var SESSION "ubus_rpc_session"
	fi
	if [ -z "$SESSION" -o "$SESSION" = "invalid" ]; then
		. html-start
		. head-start
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='login.php?err=1'\" />"
		sleep 5
	else
		. html-start-session
		. head-start
		redirecturl=$(get_returnurl)
		remove_token
		echo "<meta http-equiv=\"refresh\" content=\"0;URL='$redirecturl'\" />"
	fi

	flock -u 777 2>/dev/null

	. head-stop
	. html-stop
	exit
else
	if [ -n "$QUERY_STRING" ]; then
		logout=$(getvar "$QUERY_STRING" "out")
		iserr=$(getvar "$QUERY_STRING" "err")
	fi
	. html-start
	. head-start
	if [ "$SESSION" != "invalid" ]; then
		if [ "$logout" = "1" ]; then
			ubus call session destroy "{\"ubus_rpc_session\":\"$SESSION\"}"
			remove_token
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='index.php'\" />"
		else
			echo "<meta http-equiv=\"refresh\" content=\"0;URL='settings.php'\" />"
		fi
		. head-stop
		. html-stop
		exit
	fi
fi

ptype="$(get_ptype)"

cat head.html
. head-stop

echo "<body>"

. body-nav

echo "\

	<div class='app-section-title'>
		<div class='container'>
			<div class='row'>
				<div class='col-md-16 callout'>
					<h1>$PNAME Settings Login</h1>
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
						<div class='form-wrap'>
							<form action='login.php' method='POST'>
								<input type='hidden' name='token' value='$TOKEN'>
								<div class='group'>
									"
									if [ "$iserr" = "1" ]; then
									echo "\
										<input id='red' type='password' name='password' autofocus required>
									"
									else
									echo "\
										<input type='password' name='password' autofocus required>
									"
									fi
									echo "\
									<span class='highlight'></span>
									<span class='bar'></span>
									<label class='has-icon'><img src='img/key-icon.png' class='icon' alt=''>Password</label>
									"
									if [ "$iserr" = "1" ]; then
									echo "\
										<div class='error-text'>Password incorrect</div>
									"
									fi
									echo "\
								</div>
								<div class='group'>
									<input class='cta-main' type='submit' value='LOGIN'>
								</div>
							</form>
						</div>

					</div>
				</div>
			</div>
		</div>
	</div>
"

echo "</body>"

. html-stop
