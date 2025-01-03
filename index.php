<?php

/*
* status codes
* 0 - Kicked/Banned
* 1 - Guest
* 2 - Applicant
* 3 - Member
* 4 - System message
* 5 - Moderator
* 6 - Super-Moderator
* 7 - Admin
* 8 - Super-Admin
* 9 - Private messages
*/

// Dasho (https://dasho.dev)

send_headers();
// initialize and load variables/configuration
$I=[];// Translations
$L=[];// Languages
$U=[];// This user data
$db;// Database connection
$memcached;// Memcached connection
$language;// user selected language
load_config();
// set session variable to cookie if cookies are enabled
if(!isset($_REQUEST['session']) && isset($_COOKIE[COOKIENAME])){
    
   //Modification that prevents users from doing unwanted things (for exmaple unintenionally deleting their account), if someone else posts a malicious link.
	// MODIFICATION added logout to list of unwanted things
    if(isset($_REQUEST['action']) && ($_REQUEST['action']==='profile'||$_REQUEST['action']==='post'|| $_REQUEST['action']==='admin' || $_REQUEST['action']==='setup'||$_REQUEST['action']==='logout')){
        $_REQUEST['action']='login';
    }
    $_REQUEST['session']=$_COOKIE[COOKIENAME];
}
$_REQUEST['session'] = preg_replace('/[^0-9a-zA-Z]/', '', $_REQUEST['session'] ?? '');
load_lang();
check_db();
cron();
route();

//  main program: decide what to do based on queries
function route(){
	global $U;
	if(!isset($_REQUEST['action'])){
		send_login();
	}elseif($_REQUEST['action']==='view'){
		check_session();
		//Modification chat rooms
		if(isset($_REQUEST['room'])){
			change_room();
			check_session();
		}
		// show_rooms('true');
		send_messages();
	}elseif($_REQUEST['action']==='redirect' && !empty($_REQUEST['url'])){
		send_redirect($_REQUEST['url']);
	}elseif($_REQUEST['action']==='rooms'){
        check_session();
        rooms();
	}elseif($_REQUEST['action']==='wait'){
		parse_sessions();
		send_waiting_room();
	}elseif($_REQUEST['action']==='post'){
		check_session();
		if(isset($_REQUEST['kick']) && isset($_REQUEST['sendto']) && $_REQUEST['sendto']!==('s 48' || 's 56' || 's 65')){
            //Modification to allow members to kick guests, if memdel (DEL-Buttons) enabled
			if($U['status']>=5 || ($U['status']>=3 && get_count_mods()==0 && get_setting('memkick')) || ($U['status']>=3 && (int)get_setting('memdel')===2)){
				if(isset($_REQUEST['what']) && $_REQUEST['what']==='purge'){
					kick_chatter([$_REQUEST['sendto']], $_REQUEST['message'], true);
				}else{
					kick_chatter([$_REQUEST['sendto']], $_REQUEST['message'], false);
				}
			}
		}elseif(isset($_REQUEST['message']) && isset($_REQUEST['sendto'])){
			send_post(validate_input());
		}
		send_post();
	}elseif($_REQUEST['action']==='login'){
		check_login();
		send_frameset();
	}elseif($_REQUEST['action']==='controls'){
		check_session();
		send_controls();
	}elseif($_REQUEST['action']==='greeting'){
		check_session();
		send_greeting();
	}elseif($_REQUEST['action']==='delete'){
		check_session();
		if($_REQUEST['what']==='all'){
			if(isset($_REQUEST['confirm'])){
				del_all_messages($U['nickname'], $U['status']==1 ? $U['entry'] : 0);
			}else{
				send_del_confirm();
			}
		}elseif($_REQUEST['what']==='last'){
			del_last_message();
		}
		send_post();
	}elseif($_REQUEST['action']==='profile'){
		check_session();
		$arg='';
		if(!isset($_REQUEST['do'])){
		}elseif($_REQUEST['do']==='save'){
			$arg=save_profile();
		}elseif($_REQUEST['do']==='delete'){
			if(isset($_REQUEST['confirm'])){
				delete_account();
			}else{
				send_delete_account();
			}
		}
		send_profile($arg);
	}elseif($_REQUEST['action']==='logout'){
		kill_session();
		send_logout();
	}elseif($_REQUEST['action']==='colours'){
		check_session();
		send_colours();
	}elseif($_REQUEST['action']==='notes'){
		check_session();
		$sparenotesaccess = (int) get_setting('sparenotesaccess');
		if(isset($_REQUEST['do']) && $_REQUEST['do']==='admin' && $U['status']>6){
			send_notes(0);
		}elseif(isset($_REQUEST['do']) && $_REQUEST['do']==='staff' && $U['status']>=5){
			send_notes(1);
		// Modification Spare Notes
		}elseif (isset($_REQUEST['do']) && $_REQUEST['do']==='spare' && $U['status']>=$sparenotesaccess) {
			send_notes(3);
		}
		if($U['status']<3 || !get_setting('personalnotes')){
			send_access_denied();
		}
		send_notes(2);
	}elseif($_REQUEST['action']==='help'){
		check_session();
		send_help();
	}elseif($_REQUEST['action']==='inbox'){
		check_session();
		if(isset($_REQUEST['do'])){
			clean_inbox_selected();
		}
		send_inbox();
	}elseif($_REQUEST['action']==='download'){
		send_download();
	}elseif($_REQUEST['action']==='admin'){
		check_session();
		send_admin(route_admin());
    //MODIFICATION DEL-BUTTONS 3 Lines added to enable delete buttons in front of each message.
    }elseif($_REQUEST['action']==='admin_clean_message'){
		check_session();
		
		//These lines allows members to use the DEL-buttons according to the memdel setting (0 = not allowed , 2 =  allowed, 1 = allowed if not mod is present and if DEL-Buttons are activated for members.)
        $memdel = (int)get_setting('memdel');
        if (($U['status']>= 5) || ($U['status']>=3 && $memdel===2) || ($U['status']>=3 && get_count_mods()==0 && $memdel===1)){
            clean_selected($U['status'], $U['nickname']);
        }		
		send_messages();
		
	//MODIFICATION gallery
	}elseif($_REQUEST['action']==='gallery'){
  		check_session(); //to get $U['status']
        if(!isset($_REQUEST['do'])){
            send_gallery();
        }else{
            send_gallery($_REQUEST['do']);
        }
    //MODIFICATION links page
	}elseif($_REQUEST['action']==='links'){
  		check_session(); //to allow links page only for logged in users.
  		send_links_page();	 
    
    //Forum Button was moved to the post box (function send_post)
    /*
    }elseif($_REQUEST['action']==='forum'){
  		check_session(); //to allow link to form only for logged in users.
  		send_to_forum();	
	*/
	}elseif($_REQUEST['action']==='setup'){
		route_setup();
	}else{
		send_login();
	}
}

function route_admin(){
	global $U, $db;
	
    if($U['status']<5){
		send_access_denied();
	}
	//Modification chat rooms
	$roomcreateaccess = (int) get_setting('roomcreateaccess');
	if(!isset($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='clean'){
		if($_REQUEST['what']==='choose'){
			send_choose_messages();
		}elseif($_REQUEST['what']==='selected'){
			clean_selected($U['status'], $U['nickname']);
		}elseif($_REQUEST['what']==='chat'){
			clean_chat();
		}elseif($_REQUEST['what']==='room'){
			clean_room();
		}elseif($_REQUEST['what']==='nick'){
			$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=? AND status>=?;');
			$stmt->execute([$_REQUEST['nickname'], $U['status']]);
			if(!$stmt->fetch(PDO::FETCH_ASSOC)){
				del_all_messages($_REQUEST['nickname'], 0);
			}
		}
	}elseif($_REQUEST['do']==='kick'){
		if(isset($_REQUEST['name'])){
			if(isset($_REQUEST['what']) && $_REQUEST['what']==='purge'){
				kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], true);
			}else{
				kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], false);
			}
		}
	}elseif($_REQUEST['do']==='logout'){
		if(isset($_REQUEST['name'])){
			logout_chatter($_REQUEST['name']);
		}
	}elseif($_REQUEST['do']==='sessions'){
		if(isset($_REQUEST['kick']) && isset($_REQUEST['nick'])){
			kick_chatter([$_REQUEST['nick']], '', false);
		}elseif(isset($_REQUEST['logout']) && isset($_REQUEST['nick'])){
			logout_chatter([$_REQUEST['nick']], '', false);
		}
		send_sessions();
	// MODIFICATION only admins can register to member
	}elseif($_REQUEST['do']==='register' && $U['status']>6){
		return register_guest(3, $_REQUEST['name']);
	}elseif($_REQUEST['do']==='superguest'){
		return register_guest(2, $_REQUEST['name']);
	// MODIFICATION only admins can change status
	}elseif($_REQUEST['do']==='status' && $U['status']>6){
		return change_status($_REQUEST['name'], $_REQUEST['set']);
	// MODIFICATION only admins can register new members
	}elseif($_REQUEST['do']==='regnew' && $U['status']>6){
		return register_new($_REQUEST['name'], $_REQUEST['pass']);
	}elseif($_REQUEST['do']==='approve'){
		approve_session();
		send_approve_waiting();
	}elseif($_REQUEST['do']==='guestaccess'){
		if(isset($_REQUEST['guestaccess']) && preg_match('/^[0123]$/', $_REQUEST['guestaccess'])){
			update_setting('guestaccess', $_REQUEST['guestaccess']);
		}
	//MODIFICATION 2019-08-28 line changed. only smods (status = 6) with name Jonie , admins and above can view or change filters.
	}elseif(($_REQUEST['do']==='filter' && $U['status']>=7) || ($_REQUEST['do']==='filter' && $U['status']>=6 && $U['nickname']==='Jonie')) {
		send_filter(manage_filter());
    //MODIFICATION 2019-08-28 line changed. only smods (status = 6) with name Jonie , admins and above can view or change linkfilters.
	}elseif(($_REQUEST['do']==='linkfilter' && $U['status']>=7) || ($_REQUEST['do']==='linkfilter' && $U['status']>=6 && $U['nickname']==='Jonie')){
		send_linkfilter(manage_linkfilter());
	}elseif($_REQUEST['do']==='lastlogin' && $U['status']>=7){
        send_lastlogin();
	}elseif($_REQUEST['do']==='topic'){
         //Modification "topic with html-code" (2 Lines)
		if(isset($_REQUEST['topic']) && $U['status'] >= 7){
			update_setting('topic', $_REQUEST['topic']);
		}
	// MODIFICATION only admins can reset passwords
	}elseif($_REQUEST['do']==='passreset' && $U['status']>6){
		return passreset($_REQUEST['name'], $_REQUEST['pass']);
	//Modification chat rooms
	}elseif ($_REQUEST['do']==='rooms' && $U['status']>=$roomcreateaccess) {
		send_rooms(manage_rooms());
	}
	
}

function route_setup(){
	global $U;
	if(!valid_admin()){
		send_alogin();
	}
	//MODIFICATION incognito setting only for super admin
	$C['bool_settings']=['suguests', 'imgembed', 'timestamps', 'trackip', 'memkick', 'forceredirect', 'sendmail', 'modfallback', 'disablepm', 'eninbox', 'enablegreeting', 'sortupdown', 'hidechatters', 'personalnotes', 'filtermodkick'];
	$C['colour_settings']=['colbg', 'coltxt'];
	$C['msg_settings']=['msgenter', 'msgexit', 'msgmemreg', 'msgsureg', 'msgkick', 'msgmultikick', 'msgallkick', 'msgclean', 'msgsendall', 'msgsendmem', 'msgsendmod', 'msgsendadm', 'msgsendprv', 'msgattache'];
	$C['number_settings']=['memberexpire', 'guestexpire', 'kickpenalty', 'entrywait', 'captchatime', 'messageexpire', 'messagelimit', 'maxmessage', 'maxname', 'minpass', 'defaultrefresh', 'numnotes', 'maxuploadsize', 'enfileupload'];
	$C['textarea_settings']=['rulestxt', 'css', 'disabletext'];
	$C['text_settings']=['dateformat', 'captchachars', 'redirect', 'chatname', 'mailsender', 'mailreceiver', 'nickregex', 'passregex', 'externalcss'];
	
	//MODIFICATION for links page. setting links and linksenabled added.
	//MODIFICATION for DEL-Buttons: setting memdel added.
    //MODIFICATION for galleryaccess: setting galleryaccess added.
    //MODIFICATION for forumbtnaccess: setting forumbtnaccess added.
    //MODIFICATION for forumbtnlink: setting forumbtnlink added.
    //MODIFICATION for frontpagetext: setting frontpagetext added.
    //MODIFICATION for adminjoinleavemsg: setting adminjoinleavemsg
    //MODIFICATION for clickablne nicknames: setting clickablenicknamesglobal
    //MODIFICATION for spare notes: setting sparenotesname, setting sparenotesaccess
    //MODIFICATION for chat rooms: setting roomcreateaccess, setting roomexpire, setting channelvisinroom
	$C['settings']=array_merge(['guestaccess', 'englobalpass', 'globalpass', 'captcha', 'dismemcaptcha', 'topic', 'guestreg', 'defaulttz', 'links', 'linksenabled', 'memdel', 'galleryaccess', 'forumbtnaccess', 'forumbtnlink', 'frontpagetext', 'adminjoinleavemsg', 'clickablenicknamesglobal', 'sparenotesname', 'sparenotesaccess', 'roomcreateaccess', 'roomexpire', 'channelvisinroom'], $C['bool_settings'], $C['colour_settings'], $C['msg_settings'], $C['number_settings'], $C['textarea_settings'], $C['text_settings']); // All settings in the database
	
	
	//Modification Super Admin settings
    //MODIFICATION for modsdeladminmsg: Super Admin setting modsdeladminmsg added
    $C_SAdmin = $C;
    $C_SAdmin['settings']= array_merge($C['settings'],['modsdeladminmsg', 'incognito']);
	
	
	//Modificatoin Super Admin settings
	if(!isset($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='save' && $U['status']==8){
		save_setup($C_SAdmin);
    
	}elseif($_REQUEST['do']==='save'){
		save_setup($C);
	
	}elseif($_REQUEST['do']==='backup' && $U['status']==8){
		send_backup($C);
	}elseif($_REQUEST['do']==='restore' && $U['status']==8){
		restore_backup($C);
		send_backup($C);
	}elseif($_REQUEST['do']==='destroy' && $U['status']==8){
		if(isset($_REQUEST['confirm'])){
			destroy_chat($C);
		}else{
			send_destroy_chat();
		}
	}
	send_setup($C);
}

//  html output subs
function print_stylesheet($init=false){
	global $U;
	//default css
	echo '<style type="text/css">';
	echo 'body{background-color:#000000;color:#FFFFFF;font-size:14px;text-align:center;} body .rooms {background-color: transparent !important;}';
	echo 'a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} #messages{word-wrap:break-word;} ';
	echo 'input,select,textarea{color:#FFFFFF;background-color:#000000;} .messages a img{width:15%} .messages a:hover img{width:35%} ';
	echo '.error{color:#FF0033;text-align:left;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;} ';
	echo '.setup table table,.admin table table,.profile table table{width:100%;text-align:left} ';
	echo '.alogin table,.init table,.destroy_chat table,.delete_account table,.sessions table,.filter table,.linkfilter table,.notes table,.approve_waiting table,.del_confirm table,.profile table,.admin table,.backup table,.setup table{margin-left:auto;margin-right:auto;} ';
	echo '.setup table table table,.admin table table table,.profile table table table{border-spacing:0px;margin-left:auto;margin-right:unset;width:unset;} ';
	echo '.setup table table td,.backup #restoresubmit,.backup #backupsubmit,.admin table table td,.profile table table td,.login td+td,.alogin td+td{text-align:right;} ';
	echo '.init td,.backup #restorecheck td,.admin #clean td,.admin #regnew td,.session td,.messages,.inbox,.approve_waiting td,.choose_messages,.greeting,.help,.login td,.alogin td{text-align:left;} ';
	echo '.messages #chatters{max-height:100px;overflow-y:auto;} .messages #chatters a{text-decoration-line:none;} .messages #chatters table{border-spacing:0px;} ';
	echo '.messages #chatters th,.messages #chatters td,.post #firstline{vertical-align:top;} ';
	echo '.approve_waiting #action td:only-child,.help #backcredit,.login td:only-child,.alogin td:only-child,.init td:only-child{text-align:center;} .sessions td,.sessions th,.approve_waiting td,.approve_waiting th{padding: 5px;} ';
	echo '.sessions td td{padding: 1px;} .messages #bottom_link{position:fixed;top:0.5em;right:0.5em;} .messages #top_link{position:fixed;bottom:0.5em;right:0.5em;} ';
	echo '.post table,.controls table,.login table{border-spacing:0px;margin-left:auto;margin-right:auto;} .login table{border:2px solid;} .controls{overflow-y:none;} ';
	echo '#manualrefresh{display:block;position:fixed;text-align:center;left:25%;width:50%;top:-200%;animation:timeout_messages ';
	if(isset($U['refresh'])){
		echo $U['refresh']+20;
	}else{
		echo '160';
	}
	echo 's forwards;z-index:2;background-color:#500000;border:2px solid #ff0000;} ';
	echo '@keyframes timeout_messages{0%{top:-200%;} 99%{top:-200%;} 100%{top:0%;}} ';
	echo '.notes textarea{height:80vh;width:80%;}iframe{width:100%;height:100%;margin:0;padding:0;border:none}';
	echo '@import url("style.css");';
	echo '</style>';
	if($init){
		return;
	}
	$css=get_setting('css');
	$coltxt=get_setting('coltxt');
	if(!empty($U['bgcolour'])){
		$colbg=$U['bgcolour'];
	}else{
		$colbg=get_setting('colbg');
	}
	echo "<link rel=\"shortcut icon\" href=\"https://cdn.sokka.dev/global/images/favicon.svg\">";
	//overwrite with custom css
	echo "<style type=\"text/css\">body{background-color:#$colbg;color:#$coltxt;} $css</style>";
	echo "<link rel=\"preload\" href=\"style.css\" as=\"style\"><link rel=\"stylesheet\" type=\"text/css\" href=\"style.css\">";
}

function print_end(){
	echo '</body></html>';
	exit;
}

function credit(){
	return '<small><br><br><a target="_blank" style="color:var(--accent); text-decoration: underline dotted var(--accent)" href="https://onionz.dev/">The Onionz Project</a></small>';
}

function meta_html(){
	return '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, max-age=0"><meta http-equiv="expires" content="0"><meta name="referrer" content="no-referrer">';
}

function form($action, $do=''){
	global $language;
	$form="<form action=\"$_SERVER[SCRIPT_NAME]\" enctype=\"multipart/form-data\" method=\"post\">".hidden('lang', $language).hidden('nc', substr(time(), -6)).hidden('action', $action);
	if(!empty($_REQUEST['session'])){
		$form.=hidden('session', $_REQUEST['session']);
	}
	if($do!==''){
		$form.=hidden('do', $do);
	}
	return $form;
}

function form_target($target, $action, $do=''){
	global $language;
	$form="<form action=\"$_SERVER[SCRIPT_NAME]\" enctype=\"multipart/form-data\" method=\"post\" target=\"$target\">".hidden('lang', $language).hidden('nc', substr(time(), -6)).hidden('action', $action);
	if(!empty($_REQUEST['session'])){
		$form.=hidden('session', $_REQUEST['session']);
	}
	if($do!==''){
		$form.=hidden('do', $do);
	}
	return $form;
}

function hidden($arg1='', $arg2=''){
	return "<input type=\"hidden\" name=\"$arg1\" value=\"$arg2\">";
}

function submit($arg1='', $arg2=''){
	return "<input type=\"submit\" value=\"$arg1\" $arg2>";
}

function thr(){
	echo '<tr><td><hr></td></tr>';
}

function print_start($class='', $ref=0, $url=''){
	global $I;
	if(!empty($url)){
		$url=str_replace('&amp;', '&', $url);// Don't escape "&" in URLs here, it breaks some (older) browsers and js refresh!
		header("Refresh: $ref; URL=$url");
	}
	echo '<!DOCTYPE html><html><head>'.meta_html();
	if(!empty($url)){
		echo "<meta http-equiv=\"Refresh\" content=\"$ref; URL=$url\">";
		$ref+=5;//only use js if browser refresh stopped working
		$ref*=1000;//js uses milliseconds

		// MODIFICATION removed window refresh js
		/* echo "<script type=\"text/javascript\">setTimeout(function(){window.location.replace(\"$url\");}, $ref);</script>";*/
	}
	if($class==='init'){
		echo "<title>$I[init]</title>";
		print_stylesheet(true);
	}else{
		echo '<title>'.get_setting('chatname').'</title>';
		print_stylesheet();
	}
	if($class!=='init' && ($externalcss=get_setting('externalcss'))!=''){
		//external css - in body to make it non-renderblocking
	}
	echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"style.css\">";
	echo '<meta http-equiv="onion-location" content="http://cboxkuuxrtulkkxhod2pxo3la25tztcp4cdjmc75wc5airqqliq2srad.onion" />';
	echo "</head><body class=\"$class\">";

}

function send_redirect($url){
	global $I;
	$url=trim(htmlspecialchars_decode(rawurldecode($url)));
	preg_match('~^(.*)://~u', $url, $match);
	$url=preg_replace('~^(.*)://~u', '', $url);
	$escaped=htmlspecialchars($url);
	if(isset($match[1]) && ($match[1]==='http' || $match[1]==='https')){
		print_start('redirect', 0, $match[0].$escaped);
		echo "<p>$I[redirectto] <a href=\"$match[0]$escaped\">$match[0]$escaped</a>.</p>";
	}else{
		print_start('redirect');
		if(!isset($match[0])){
			$match[0]='';
		}
		if(preg_match('~^(javascript|blob|data):~', $url)){
			echo "<p>$I[dangerousnonhttp] $match[0]$escaped</p>";
		} else {
			echo "<p>$I[nonhttp] <a href=\"$match[0]$escaped\">$match[0]$escaped</a>.</p>";
		}
		echo "<p>$I[httpredir] <a href=\"http://$escaped\">http://$escaped</a>.</p>";
	}
	print_end();
}

function send_access_denied(){
	global $I, $U;
	header('HTTP/1.1 403 Forbidden');
	print_start('access_denied');
	echo "<h1>$I[accessdenied]</h1>".sprintf($I['loggedinas'], style_this(htmlspecialchars($U['nickname']), $U['style'])).'<br>';
	echo form('logout');
	if(!isset($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo submit($I['logout'], 'id="exitbutton"')."</form>";
	print_end();
}

function send_captcha(){
	global $I, $db, $memcached;
	$difficulty=(int) get_setting('captcha');
	if($difficulty===0 || !extension_loaded('gd')){
		return;
	}
	$captchachars=get_setting('captchachars');
	$length=strlen($captchachars)-1;
	$code='';
	for($i=0;$i<5;++$i){
		$code.=$captchachars[mt_rand(0, $length)];
	}
	$randid=mt_rand();
	$time=time();
	if(MEMCACHED){
		$memcached->set(DBNAME . '-' . PREFIX . "captcha-$randid", $code, get_setting('captchatime'));
	}else{
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'captcha (id, time, code) VALUES (?, ?, ?);');
		$stmt->execute([$randid, $time, $code]);
	}
	echo "<tr id=\"captcha\"><td><span class=\"centerWrap sprite-decaptcha-logo-night\"></span> ";
	if($difficulty===1){
		$im=imagecreatetruecolor(55, 24);
        $bg=imagecolorallocatealpha($im, 0, 0, 0, 127);
        $fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		imagesavealpha($im, true);
        echo '<img class="captchalogincbox" width="55" height="24" src="data:image/gif;base64,';
        }elseif($difficulty===2){
		$im=imagecreatetruecolor(55, 24);
        $bg=imagecolorallocatealpha($im, 0, 0, 0, 0);
        $fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		$line=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<2;++$i){
			imageline($im, 0, mt_rand(0, 24), 55, mt_rand(0, 24), $line);
		}
		$dots=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<100;++$i){
			imagesetpixel($im, mt_rand(0, 55), mt_rand(0, 24), $dots);
		}
        echo '<img class="captchalogincbox" width="55" height="24" src="data:image/gif;base64,';
        }else{
		$im=imagecreatetruecolor(150, 200);
        $bg=imagecolorallocatealpha($im, 0, 0, 0, 0);
        $fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$chars=[];
		for($i=0;$i<10;++$i){
			$found=false;
			while(!$found){
				$x=mt_rand(10, 140);
				$y=mt_rand(10, 180);
				$found=true;
				foreach($chars as $char){
					if($char['x']>=$x && ($char['x']-$x)<25){
						$found=false;
					}elseif($char['x']<$x && ($x-$char['x'])<25){
						$found=false;
					}
					if(!$found){
						if($char['y']>=$y && ($char['y']-$y)<25){
							break;
						}elseif($char['y']<$y && ($y-$char['y'])<25){
							break;
						}else{
							$found=true;
						}
					}
				}
			}
			$chars[]=['x', 'y'];
			$chars[$i]['x']=$x;
			$chars[$i]['y']=$y;
			if($i<5){
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $captchachars[mt_rand(0, $length)], $fg);
			}else{
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $code[$i-5], $fg);
			}
		}
		$follow=imagecolorallocate($im, 200, 0, 0);
		imagearc($im, $chars[5]['x']+4, $chars[5]['y']+8, 16, 16, 0, 360, $follow);
		for($i=5;$i<9;++$i){
			imageline($im, $chars[$i]['x']+4, $chars[$i]['y']+8, $chars[$i+1]['x']+4, $chars[$i+1]['y']+8, $follow);
		}
		$line=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<5;++$i){
			imageline($im, 0, mt_rand(0, 200), 150, mt_rand(0, 200), $line);
		}
		$dots=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<1000;++$i){
			imagesetpixel($im, mt_rand(0, 150), mt_rand(0, 200), $dots);
		}
        echo '<img class="captchalogincbox" width="150" height="200" src="data:image/gif;base64,';
    }
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'">';
	echo '</td><td>'.hidden('challenge', $randid).'<input type="text" name="captcha" size="15" autocomplete="off"></td></tr>';
}

function send_setup($C){
	global $I, $U;
	print_start('setup');
	echo "<h2>$I[setup]</h2>".form('setup', 'save');
	if(!isset($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo '<table id="guestaccess">';
	thr();
	$ga=(int) get_setting('guestaccess');
	echo "<tr><td><table><tr><th>$I[guestacc]</th><td>";
	echo '<select name="guestaccess">';
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo ">$I[guestallow]</option>";
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo ">$I[guestwait]</option>";
	echo '<option value="3"';
	if($ga===3){
		echo ' selected';
	}
	echo ">$I[adminallow]</option>";
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo ">$I[guestdisallow]</option>";
	echo '<option value="4"';
	if($ga===4){
		echo ' selected';
	}
	echo ">$I[disablechat]</option>";
	echo '</select></td></tr></table></td></tr>';
	thr();
	$englobal=(int) get_setting('englobalpass');
	echo "<tr><td><table id=\"globalpass\"><tr><th>$I[globalloginpass]</th><td>";
	echo '<table>';
	echo '<tr><td><select name="englobalpass">';
	echo '<option value="0"';
	if($englobal===0){
		echo ' selected';
	}
	echo ">$I[disabled]</option>";
	echo '<option value="1"';
	if($englobal===1){
		echo ' selected';
	}
	echo ">$I[enabled]</option>";
	echo '<option value="2"';
	if($englobal===2){
		echo ' selected';
	}
	echo ">$I[onlyguests]</option>";
	echo '</select></td><td>&nbsp;</td>';
	echo '<td><input type="text" name="globalpass" value="'.htmlspecialchars(get_setting('globalpass')).'"></td></tr>';
	echo '</table></td></tr></table></td></tr>';
	thr();
	$ga=(int) get_setting('guestreg');
	echo "<tr><td><table id=\"guestreg\"><tr><th>$I[guestreg]</th><td>";
	echo '<select name="guestreg">';
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo ">$I[disabled]</option>";
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo ">$I[assuguest]</option>";
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo ">$I[asmember]</option>";
	echo '</select></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"sysmessages\"><tr><th>$I[sysmessages]</th><td>";
	echo '<table>';
	foreach($C['msg_settings'] as $setting){
		echo "<tr><td>&nbsp;$I[$setting]</td><td>&nbsp;<input type=\"text\" name=\"$setting\" value=\"".get_setting($setting).'"></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	foreach($C['text_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<input type=\"text\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['colour_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<input type=\"color\" name=\"$setting\" value=\"#".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	thr();
	echo "<tr><td><table id=\"captcha\"><tr><th>$I[captcha]</th><td>";
	echo '<table>';
	if(!extension_loaded('gd')){
		echo "<tr><td>$I[gdextrequired]</td></tr>";
	}else{
		echo '<tr><td><select name="dismemcaptcha">';
		$dismemcaptcha=(bool) get_setting('dismemcaptcha');
		echo '<option value="0"';
		if(!$dismemcaptcha){
			echo ' selected';
		}
		echo ">$I[enabled]</option>";
		echo '<option value="1"';
		if($dismemcaptcha){
			echo ' selected';
		}
		echo ">$I[onlyguests]</option>";
		echo '</select></td><td><select name="captcha">';
		$captcha=(int) get_setting('captcha');
		echo '<option value="0"';
		if($captcha===0){
			echo ' selected';
		}
		echo ">$I[disabled]</option>";
		echo '<option value="1"';
		if($captcha===1){
			echo ' selected';
		}
		echo ">$I[simple]</option>";
		echo '<option value="2"';
		if($captcha===2){
			echo ' selected';
		}
		echo ">$I[moderate]</option>";
		echo '<option value="3"';
		if($captcha===3){
			echo ' selected';
		}
		echo ">$I[extreme]</option>";
		echo '</select></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"defaulttz\"><tr><th>$I[defaulttz]</th><td>";
	echo "<select name=\"defaulttz\">";
	$tzs=timezone_identifiers_list();
	$defaulttz=get_setting('defaulttz');
	foreach($tzs as $tz){
		echo "<option value=\"$tz\"";
		if($defaulttz==$tz){
			echo ' selected';
		}
		echo ">$tz</option>";
	}
	echo '</select>';
	echo '</td></tr></table></td></tr>';
	foreach($C['textarea_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<textarea name=\"$setting\" rows=\"4\" cols=\"60\">".htmlspecialchars(get_setting($setting)).'</textarea>';
		echo '</td></tr></table></td></tr>';
	}
    //MODIFICATION textarea to edit links page
	thr();
    echo "<tr><td><table id=\"links\"><tr><th>Links Page (html)</th><td>";
	echo "<textarea name=\"links\" rows=\"4\" cols=\"60\">".htmlspecialchars(get_setting('links')).'</textarea>';
	echo '</td></tr></table></td></tr>';
	//End of Modification
	
    //MODIFICATION frontpagetext: textarea to edit front page
	thr();
    echo "<tr><td><table id=\"frontpagetext\"><tr><th>Text on front page (html)</th><td>";
	echo "<textarea name=\"frontpagetext\" rows=\"4\" cols=\"60\">".htmlspecialchars(get_setting('frontpagetext')).'</textarea>';
	echo '</td></tr></table></td></tr>';
	//End of Modification
	
	foreach($C['number_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<input type=\"number\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['bool_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<select name=\"$setting\">";
		$value=(bool) get_setting($setting);
		echo '<option value="0"';
		if(!$value){
			echo ' selected';
		}
		echo ">$I[disabled]</option>";
		echo '<option value="1"';
		if($value){
			echo ' selected';
		}
		echo ">$I[enabled]</option>";
		echo '</select></td></tr>';
		echo '</table></td></tr>';
	}
	//thr();
	
    //MODIFICATION to enable links page 
	thr();
	echo "<tr><td><table id=\"linksenabled\"><tr><th>Links Page</th><td>";
	echo "<select name=\"linksenabled\">";
	$value=(bool) get_setting('linksenabled');
	echo '<option value="0"';
	if(!$value){
		echo ' selected';
	}
	echo ">$I[disabled]</option>";
	echo '<option value="1"';
	if($value){
	echo ' selected';
	}
	echo ">$I[enabled]</option>";
	echo '</select></td></tr>';
    echo '</table></td></tr>';
	thr();	
	//End of Modification
	
    //MODIFICATION to enable DEL-Buttons for members (2 = always, 1 =  if no mod is present.)
	//thr();
	echo "<tr><td><table id=\"memdel\"><tr><th>Members can delete messages (DEL) and can kick</th><td>";
	echo "<select name=\"memdel\">";
	$value=(int) get_setting('memdel');
	echo '<option value="0"';
	if($value == 0){
		echo ' selected';
	}
	echo ">$I[disabled]</option>";
	
	echo '<option value="1"';
	if($value == 1){
	echo ' selected';
	}
	echo ">DEL-Buttons enabled, if no mod is present</option>";
	
	/*
	echo '</select></td></tr>';
    echo '</table></td></tr>';
    */
    
    echo '<option value="2"';
	if($value == 2){
	echo ' selected';
	}
	echo ">$I[enabled]</option>";
	echo '</select></td></tr>';
    echo '</table></td></tr>';
	
	thr();	
	//End of Modification
	
	//Modification gallery access
	echo "<tr><td><table id=\"galleryaccess\"><tr><th>Gallery access</th><td>";
	echo "<select name=\"galleryaccess\">";
	$value=(int) get_setting('galleryaccess');
	
	$options = array(1, 2, 3, 5, 6, 7, 10);
	
    foreach($options as $option){
		echo "<option value=\"$option\"";
		
		if($value==$option){
			echo ' selected';
		}
		
		if ($option == 1) echo ">All</option>";
		elseif($option == 2) echo ">Registered guests</option>";
		elseif($option == 3) echo ">Members</option>";
		elseif($option == 5) echo ">Moderators</option>";
		elseif($option == 6) echo ">Super Moderators</option>";
		elseif($option == 7) echo ">Admins</option>";
		elseif($option == 10) echo ">Disabled</option>";
	}
	
    echo '</select></td></tr>';
    echo '</table></td></tr>';
    thr();	
    //End of modification
    
    //Modification forum button visibility
	echo "<tr><td><table id=\"forumbtnaccess\"><tr><th>Forum Button visibility</th><td>";
	echo "<select name=\"forumbtnaccess\">";
	$value=(int) get_setting('forumbtnaccess');
	
	$options = array(1, 2, 3, 5, 6, 7, 10);
	
    foreach($options as $option){
		echo "<option value=\"$option\"";
		
		if($value==$option){
			echo ' selected';
		}
		
		if ($option == 1) echo ">All</option>";
		elseif($option == 2) echo ">Registered guests</option>";
		elseif($option == 3) echo ">Members</option>";
		elseif($option == 5) echo ">Moderators</option>";
		elseif($option == 6) echo ">Super Moderators</option>";
		elseif($option == 7) echo ">Admins</option>";
		elseif($option == 10) echo ">Disabled</option>";
	}
	
    echo '</select></td></tr>';
    echo '</table></td></tr>';
    thr();	
    //End of modification
    
    //Modification forum button link
    
	echo "<tr><td><table id=\"forumbtnlink\"><tr><th>Forum Button link</th><td>";
	echo "<input type=\"text\" name=\"forumbtnlink\" value=\"".htmlspecialchars(get_setting('forumbtnlink')).'">';
	echo '</td></tr></table></td></tr>';
	thr();	
    //End of modification
	
    //MODIFICATION adminjoinleavemsg to not create a system message if an admins arrives or leaves the chat
    echo "<tr><td><table id=\"adminjoinleavemsg\"><tr><th>Show system message if an admin joined or left the chat</th><td>";
    echo "<select name=\"adminjoinleavemsg\">";
    $value=(bool) get_setting('adminjoinleavemsg');
    echo '<option value="0"';
    if(!$value){
        echo ' selected';
    }
    echo ">$I[disabled]</option>";
    echo '<option value="1"';
    if($value){
    echo ' selected';
    }
    echo ">$I[enabled]</option>";
    echo '</select></td></tr>';
    echo '</table></td></tr>';
    thr();	
    //End of Modification
	
    //MODIFICATION clickablenicknamesglobal to enable/disable clickablenicknames, e. g. in case of errors
    echo "<tr><td><table id=\"clickablenicknamesglobal\"><tr><th>Clickable nicknames</th><td>";
    echo "<select name=\"clickablenicknamesglobal\">";
    $value=(bool) get_setting('clickablenicknamesglobal');
    echo '<option value="0"';
    if(!$value){
        echo ' selected';
    }
    echo ">$I[disabled]</option>";
    echo '<option value="1"';
    if($value){
    echo ' selected';
    }
    echo ">$I[enabled]</option>";
    echo '</select></td></tr>';
    echo '</table></td></tr>';
    thr();	
    //End of Modification

    // Modification Spare Notes.
    // Spare Notes name
    echo '<tr><td><table id="sparenotesname"><tr><th>Spare Notes Name</th><td>';
	echo '<input type="text" name="sparenotesname" value="'.htmlspecialchars(get_setting('sparenotesname')).'">';
	echo '</td></tr></table></td></tr>';
	thr();
	// Spare Notes Access
	echo "<tr><td><table id=\"sparenotesaccess\"><tr><th>Spare Notes Access</th><td>";
	echo "<select name=\"sparenotesaccess\">";
	$value=(int) get_setting('sparenotesaccess');
	
	$options = array(3, 5, 6, 7, 10);
	
    foreach($options as $option){
		echo "<option value=\"$option\"";
		
		if($value==$option){
			echo ' selected';
		}
		
		if($option == 3) echo ">Members</option>";
		elseif($option == 5) echo ">Moderators</option>";
		elseif($option == 6) echo ">Super Moderators</option>";
		elseif($option == 7) echo ">Admins</option>";
		elseif($option == 10) echo ">Disabled</option>";
	}
	// End of Modification
    echo '</select></td></tr>';
    echo '</table></td></tr>';
    thr();	

    // Modificatin create chat rooms
    echo "<tr><td><table id=\"roomcreateaccess\"><tr><th>Rooms can be created by:</th><td>";
	echo "<select name=\"roomcreateaccess\">";
	$value=(int) get_setting('roomcreateaccess');
	
	$options = array(5, 6, 7);
	
    foreach($options as $option){
		echo "<option value=\"$option\"";
		
		if($value==$option){
			echo ' selected';
		}
		
		if($option == 5) echo ">Moderators</option>";
		elseif($option == 6) echo ">Super Moderators</option>";
		elseif($option == 7) echo ">Admins</option>";
	}
	echo '</select></td></tr>';
    echo '</table></td></tr>';
    thr();	
	echo "<tr><td><table id=\"roomexpire\"><tr><th>Room Timeout (minutes)</th><td>";
	echo "<input type=\"number\" name=\"roomexpire\" value=\"".get_setting('roomexpire').'">';
	echo '</td></tr></table></td></tr>';
	thr();

    echo "<tr><td><table id=\"channelvisinroom\"><tr><th>Channels visible in all rooms</th><td>";
	echo "<select name=\"channelvisinroom\">";
	$value=(int) get_setting('channelvisinroom');
	$options = array(2, 3, 5, 6, 7, 9);
	
    foreach($options as $option){
		echo "<option value=\"$option\"";
		
		if($value==$option){
			echo ' selected';
		}
		
		if($option == 2) echo ">All Channels</option>";
		elseif($option == 3) echo ">Member Channels</option>";
		elseif($option == 5) echo ">Staff Channels</option>";
		elseif($option == 6) echo ">SMod Channels</option>";
		elseif($option == 7) echo ">Admin Channel</option>";
		elseif($option == 9) echo ">No channels</option>";
	}
	echo '</select></td></tr>';
    echo '</table></td></tr>';
    thr();
	// End of Modification

    

	
	
	/*****************************************
	*SETTINGS ONLY FOR SUPER ADMIN ARE BELOW
	******************************************/
	if($U['status']==8){
	
        echo '<tr><td><table>';
        echo "<font color='red'>↓ Setting(s) below can only be viewed and edited by Super Admin  ↓</font>";
        echo '</td></tr></table>';
        
        thr();
        //MODIFICATION modsdeladminmsg to allow mods deleting admin messages
        echo "<tr><td><table id=\"modsdeladminmsg\"><tr><th>Staff members can delete messages of higher ranked staff members</th><td>";
        echo "<select name=\"modsdeladminmsg\">";
        $value=(bool) get_setting('modsdeladminmsg');
        echo '<option value="0"';
        if(!$value){
            echo ' selected';
        }
        echo ">$I[disabled]</option>";
        echo '<option value="1"';
        if($value){
        echo ' selected';
        }
        echo ">$I[enabled]</option>";
        echo '</select></td></tr>';
        echo '</table></td></tr>';
        thr();	
        //End of Modification
        
        //MODIFICATION incognitomode setting only for super admin.
        echo "<tr><td><table id=\"incognito\"><tr><th>".$I['incognito']."</th><td>";
        echo "<select name=\"incognito\">";
        $value=(bool) get_setting('incognito');
        echo '<option value="0"';
        if(!$value){
            echo ' selected';
        }
        echo ">$I[disabled]</option>";
        echo '<option value="1"';
        if($value){
        echo ' selected';
        }
        echo ">$I[enabled]</option>";
        echo '</select></td></tr>';
        echo '</table></td></tr>';
        thr();	
        //End of Modification
        
        echo '<tr><td><table>';
        echo "<font color='red'> ↑ Setting(s) above can only be viewed and edited by Super Admin ↑</font>";
        echo '</td></tr></table>';
        thr();	
    }//End if
		
	/*****************************************
	*SETTINGS ONLY FOR SUPER ADMIN ARE ABOVE
	******************************************/
	
	echo '<tr><td>'.submit($I['apply']).'</td></tr></table></form><br>';
	if($U['status']==8){
		echo '<table id="actions"><tr><td>';
		echo form('setup', 'backup');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['backuprestore']).'</form></td><td>';
		echo form('setup', 'destroy');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['destroy'], 'class="delbutton"').'</form></td></tr></table><br>';
	}
	echo form_target('_parent', 'logout');
	if(!isset($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo submit($I['logout'], 'id="exitbutton"').'</form>'.credit();
	print_end();
}

function restore_backup($C){
	global $db, $memcached;
	if(!extension_loaded('json')){
		return;
	}
	$code=json_decode($_REQUEST['restore'], true);
	if(isset($_REQUEST['settings'])){
		foreach($C['settings'] as $setting){
			if(isset($code['settings'][$setting])){
				update_setting($setting, $code['settings'][$setting]);
			}
		}
	}
	if(isset($_REQUEST['filter']) && (isset($code['filters']) || isset($code['linkfilters']))){
		$db->exec('DELETE FROM ' . PREFIX . 'filter;');
		$db->exec('DELETE FROM ' . PREFIX . 'linkfilter;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES (?, ?, ?, ?, ?, ?);');
		foreach($code['filters'] as $filter){
			if(!isset($filter['cs'])){
				$filter['cs']=0;
			}
			$stmt->execute([$filter['match'], $filter['replace'], $filter['allowinpm'], $filter['regex'], $filter['kick'], $filter['cs']]);
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
		foreach($code['linkfilters'] as $filter){
			$stmt->execute([$filter['match'], $filter['replace'], $filter['regex']]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
			$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		}
	}
	if(isset($_REQUEST['members']) && isset($code['members'])){
		$db->exec('DELETE FROM ' . PREFIX . 'inbox;');
		$db->exec('DELETE FROM ' . PREFIX . 'members;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($code['members'] as $member){
			$new_settings=['nocache', 'tz', 'eninbox', 'sortupdown', 'hidechatters', 'nocache_old'];
			foreach($new_settings as $setting){
				if(!isset($member[$setting])){
					$member[$setting]=0;
				}
			}
			$stmt->execute([$member['nickname'], $member['passhash'], $member['status'], $member['refresh'], $member['bgcolour'], $member['regedby'], $member['lastlogin'], $member['timestamps'], $member['embed'], $member['incognito'], $member['style'], $member['nocache'], $member['tz'], $member['eninbox'], $member['sortupdown'], $member['hidechatters'], $member['nocache_old']]);
		}
	}
	if(isset($_REQUEST['notes']) && isset($code['notes'])){
		$db->exec('DELETE FROM ' . PREFIX . 'notes;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
		foreach($code['notes'] as $note){
			if($note['type']==='admin'){
				$note['type']=0;
			}elseif($note['type']==='staff'){
				$note['type']=1;
			}
			if(MSGENCRYPTED){
                $note['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($note['text'], '', AES_IV, ENCRYPTKEY));
			}
			$stmt->execute([$note['type'], $note['lastedited'], $note['editedby'], $note['text']]);
		}
	}
}

function send_backup($C){
	global $I, $db;
	$code=[];
	if($_REQUEST['do']==='backup'){
		if(isset($_REQUEST['settings'])){
			foreach($C['settings'] as $setting){
				$code['settings'][$setting]=get_setting($setting);
			}
		}
		if(isset($_REQUEST['filter'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . 'filter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['filters'][]=['match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick'], 'cs'=>$filter['cs']];
			}
			$result=$db->query('SELECT * FROM ' . PREFIX . 'linkfilter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['linkfilters'][]=['match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']];
			}
		}
		if(isset($_REQUEST['members'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
			while($member=$result->fetch(PDO::FETCH_ASSOC)){
				$code['members'][]=$member;
			}
		}
		if(isset($_REQUEST['notes'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . "notes;");
			while($note=$result->fetch(PDO::FETCH_ASSOC)){
				if(MSGENCRYPTED){
                    $note['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($note['text']), null, AES_IV, ENCRYPTKEY);
				}
				$code['notes'][]=$note;
			}
		}
	}
	if(isset($_REQUEST['settings'])){
		$chksettings=' checked';
	}else{
		$chksettings='';
	}
	if(isset($_REQUEST['filter'])){
		$chkfilters=' checked';
	}else{
		$chkfilters='';
	}
	if(isset($_REQUEST['members'])){
		$chkmembers=' checked';
	}else{
		$chkmembers='';
	}
	if(isset($_REQUEST['notes'])){
		$chknotes=' checked';
	}else{
		$chknotes='';
	}
	print_start('backup');
	echo "<h2>$I[backuprestore]</h2><table>";
	thr();
	if(!extension_loaded('json')){
		echo "<tr><td>$I[jsonextrequired]</td></tr>";
	}else{
		echo '<tr><td>'.form('setup', 'backup');
		echo '<table id="backup"><tr><td id="backupcheck">';
		echo "<label><input type=\"checkbox\" name=\"settings\" id=\"backupsettings\" value=\"1\"$chksettings>$I[settings]</label>";
		echo "<label><input type=\"checkbox\" name=\"filter\" id=\"backupfilter\" value=\"1\"$chkfilters>$I[filter]</label>";
		echo "<label><input type=\"checkbox\" name=\"members\" id=\"backupmembers\" value=\"1\"$chkmembers>$I[members]</label>";
		echo "<label><input type=\"checkbox\" name=\"notes\" id=\"backupnotes\" value=\"1\"$chknotes>$I[notes]</label>";
		echo '</td><td id="backupsubmit">'.submit($I['backup']).'</td></tr></table></form></td></tr>';
		thr();
		echo '<tr><td>'.form('setup', 'restore');
		echo '<table id="restore">';
		echo "<tr><td colspan=\"2\"><textarea name=\"restore\" rows=\"4\" cols=\"60\">".htmlspecialchars(json_encode($code)).'</textarea></td></tr>';
		echo "<tr><td id=\"restorecheck\"><label><input type=\"checkbox\" name=\"settings\" id=\"restoresettings\" value=\"1\"$chksettings>$I[settings]</label>";
		echo "<label><input type=\"checkbox\" name=\"filter\" id=\"restorefilter\" value=\"1\"$chkfilters>$I[filter]</label>";
		echo "<label><input type=\"checkbox\" name=\"members\" id=\"restoremembers\" value=\"1\"$chkmembers>$I[members]</label>";
		echo "<label><input type=\"checkbox\" name=\"notes\" id=\"restorenotes\" value=\"1\"$chknotes>$I[notes]</label>";
		echo '</td><td id="restoresubmit">'.submit($I['restore']).'</td></tr></table>';
		echo '</form></td></tr>';
	}
	thr();
	echo '<tr><td>'.form('setup').submit($I['initgosetup'], 'class="backbutton"')."</form></tr></td>";
	echo '</table>';
	print_end();
}

function send_destroy_chat(){
	global $I;
	print_start('destroy_chat');
	echo "<table><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	echo form_target('_parent', 'setup', 'destroy').hidden('confirm', 'yes').submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	echo form('setup').submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_delete_account(){
	global $I;
	print_start('delete_account');
	echo "<table><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	echo form('profile', 'delete').hidden('confirm', 'yes').submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	echo form('profile').submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_init(){
	global $I, $L;
	print_start('init');
	echo "<h2>$I[init]</h2>";
	echo form('init')."<table><tr><td><h3>$I[sulogin]</h3><table>";
	echo "<tr><td>$I[sunick]</td><td><input type=\"text\" name=\"sunick\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[supass]</td><td><input type=\"password\" name=\"supass\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[suconfirm]</td><td><input type=\"password\" name=\"supassc\" size=\"15\"></td></tr>";
	echo '</table></td></tr><tr><td><br>'.submit($I['initbtn']).'</td></tr></table></form>';
	echo "<p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&amp;lang=$lang\">$name</a>";
	}
	echo '</p>'.credit();
	print_end();
}

function send_update($msg){
	global $I;
	print_start('update');
	echo "<h2>$I[dbupdate]</h2><br>".form('setup').submit($I['initgosetup'])."</form>$msg<br>".credit();
	print_end();
}

function send_alogin(){
	global $I, $L;
	print_start('alogin');
	echo form('setup').'<table>';
	echo "<tr><td>$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
	echo "<tr><td>$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
	send_captcha();
	echo '<tr><td colspan="2">'.submit($I['login']).'</td></tr></table></form>';
	echo "<p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&amp;lang=$lang\">$name</a>";
	}
	echo '</p>'.credit();
	print_end();
}

function send_admin($arg=''){
	global $I, $U, $db;
	$ga=(int) get_setting('guestaccess');
	print_start('admin');
	$chlist="<select name=\"name[]\" size=\"5\" multiple><option value=\"\">$I[choose]</option>";
	$chlist.="<option value=\"s &amp;\">$I[allguests]</option>";
	$users=[];
	$stmt=$db->query('SELECT nickname, style, status FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 ORDER BY LOWER(nickname);');
	while($user=$stmt->fetch(PDO::FETCH_NUM)){
		$users[]=[htmlspecialchars($user[0]), $user[1], $user[2]];
	}
	foreach($users as $user){
		if($user[2]<$U['status']){
			$chlist.="<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
		}
	}
	$chlist.='</select>';
	echo "<h2>$I[admfunc]</h2><i>$arg</i><table>";
	if($U['status']>=7){
		thr();
		echo '<tr><td>'.form_target('view', 'setup').submit($I['initgosetup']).'</form></td></tr>';
	}
	thr();
	echo "<tr><td><table id=\"clean\"><tr><th>$I[cleanmsgs]</th><td>";
	echo form('admin', 'clean');
	echo '<table><tr><td><label><input type="radio" name="what" id="room" value="chat">';
	echo $I['chat']."</label></td><td>&nbsp;</td><td><label><input type=\"radio\" name=\"what\" id=\"choose\" value=\"room\" checked>";
	echo $I['room']."</label></td><td>&nbsp;</td><td></tr><tr><td colspan=\"3\"><label><input type=\"radio\" name=\"what\" id=\"choose\" value=\"choose\" checked>";
	echo $I['selection']."</label></td><td>&nbsp;</td></tr><tr><td colspan=\"3\"><label><input type=\"radio\" name=\"what\" id=\"nick\" value=\"nick\">";
	echo $I['cleannick']."</label> <select name=\"nickname\" size=\"1\"><option value=\"\">$I[choose]</option>";
	$stmt=$db->prepare('SELECT poster FROM ' . PREFIX . "messages WHERE delstatus<? AND poster!='' GROUP BY poster;");
	$stmt->execute([$U['status']]);
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo '<option value="'.htmlspecialchars($nick[0]).'">'.htmlspecialchars($nick[0]).'</option>';
	}
	echo '</select></td><td>';
	echo submit($I['clean'], 'class="delbutton"').'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table id="kick"><tr><th>'.sprintf($I['kickchat'], get_setting('kickpenalty')).'</th></tr><tr><td>';
	echo form('admin', 'kick');
	echo "<table><tr><td>$I[kickreason]</td><td><input type=\"text\" name=\"kickmessage\" size=\"30\"></td><td>&nbsp;</td></tr>";
	echo "<tr><td><label><input type=\"checkbox\" name=\"what\" value=\"purge\" id=\"purge\">$I[kickpurge]</label></td><td>$chlist</td><td>";
	echo submit($I['kick']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"logout\"><tr><th>$I[logoutinact]</th><td>";
	echo form('admin', 'logout');
	echo "<table><tr><td>$chlist</td><td>";
	echo submit($I['logout']).'</td></tr></table></form></td></tr></table></td></tr>';
	
	//MODIFICATION 2019-09-06 last-login table (show when members logged in the last time.
	$view_lastlogin = 'lastlogin';
	if ($U['status']>=7){
        thr();
		echo "<tr><td><table id=\"$view_lastlogin\"><tr><th>"."Last logins".'</th><td>';
		echo form('admin', $view_lastlogin);
		echo submit($I['view']).'</form></td></tr></table></td></tr>';
        
	}
	//MODIFICATION 2019-08-28 one line replaced with 6 lines of code
	//filter button and linkfilter button will only be shown to smod 'Jonie' (status = 6), admins and higher.
    if (($U['status']>=7) || ($U['status']>=6 && $U['nickname']=='Jonie')){
        $views=['sessions', 'filter', 'linkfilter'];
    }
    else{
        $views=['sessions'];
    }
	
	foreach($views as $view){
		thr();
		echo "<tr><td><table id=\"$view\"><tr><th>".$I[$view].'</th><td>';
		echo form('admin', $view);
		echo submit($I['view']).'</form></td></tr></table></td></tr>';
	}
	thr();
	//Modification chat rooms.
	$roomcreateaccess = (int) get_setting('roomcreateaccess');
	if($U['status']>=$roomcreateaccess){
		echo "<tr><td><table id=\"chatrooms\"><tr><th>".'Chat Rooms</th><td>';
		echo form('admin', 'rooms');
		echo submit($I['view']).'</form></td></tr></table></td></tr>';
		thr();
	}
	
	//Modification "html topic" (Topic can only be set by admins)
    if ($U['status']>=7){
	
        echo "<tr><td><table id=\"topic\"><tr><th>$I[topic]</th><td>";
        echo form('admin', 'topic');
        echo '<table><tr><td><input type="text" name="topic" size="20" value="'.htmlspecialchars(get_setting('topic')).'"></td><td>';
        echo submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
        thr();
	}
	
	echo "<tr><td><table id=\"guestaccess\"><tr><th>$I[guestacc]</th><td>";
	echo form('admin', 'guestaccess');
	echo '<table>';
	echo '<tr><td><select name="guestaccess">';
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo ">$I[guestallow]</option>";
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo ">$I[guestwait]</option>";
	echo '<option value="3"';
	if($ga===3){
		echo ' selected';
	}
	echo ">$I[adminallow]</option>";
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo ">$I[guestdisallow]</option>";
	if($ga===4){
		echo '<option value="4" selected';
		echo ">$I[disablechat]</option>";
	}
	echo '</select></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	if(get_setting('suguests')){
		echo "<tr><td><table id=\"suguests\"><tr><th>$I[addsuguest]</th><td>";
		echo form('admin', 'superguest');
		echo "<table><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($users as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=7){
		echo "<tr><td><table id=\"status\"><tr><th>$I[admmembers]</th><td>";
		echo form('admin', 'status');
		echo "<table><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		$members=[];
		$result=$db->query('SELECT nickname, style, status FROM ' . PREFIX . 'members ORDER BY LOWER(nickname);');
		while($temp=$result->fetch(PDO::FETCH_NUM)){
			$members[]=[htmlspecialchars($temp[0]), $temp[1], $temp[2]];
		}
		foreach($members as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]";
			if($member[2]==0){
				echo ' (!)';
			}elseif($member[2]==2){
				echo ' (G)';
			}elseif($member[2]==3){
			}elseif($member[2]==5){
				echo ' (M)';
			}elseif($member[2]==6){
				echo ' (SM)';
			}elseif($member[2]==7){
				echo ' (A)';
			}else{
				echo ' (SA)';
			}
			echo '</option>';
		}
		echo "</select><select name=\"set\" size=\"1\"><option value=\"\">$I[choose]</option><option value=\"-\">$I[memdel]</option><option value=\"0\">$I[memdeny]</option>";
		if(get_setting('suguests')){
			echo "<option value=\"2\">$I[memsuguest]</option>";
		}
		echo "<option value=\"3\">$I[memreg]</option>";
		echo "<option value=\"5\">$I[memmod]</option>";
		echo "<option value=\"6\">$I[memsumod]</option>";
		if($U['status']>=8){
			echo "<option value=\"7\">$I[memadm]</option>";
		}
		echo '</select></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table id=\"passreset\"><tr><th>$I[passreset]</th><td>";
		echo form('admin', 'passreset');
		echo "<table><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($members as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]</option>";
		}
		echo '</select></td><td><input type="password" name="pass"></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table id=\"register\"><tr><th>$I[regguest]</th><td>";
		echo form('admin', 'register');
		echo "<table><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($users as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		////Modification Register new Applicant
		echo "<tr><td><table id=\"regnew\"><tr><th>".(get_setting('suguests') ? "Register new Applicant" : $I['regmem'])."</th></tr><tr><td>";
		echo form('admin', 'regnew');
		echo "<table><tr><td>$I[nick]</td><td>&nbsp;</td><td><input type=\"text\" name=\"name\" size=\"20\"></td><td>&nbsp;</td></tr>";
		echo "<tr><td>$I[pass]</td><td>&nbsp;</td><td><input type=\"password\" name=\"pass\" size=\"20\"></td><td>";
		echo submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	echo "</table><br>";
	echo form('admin').submit($I['reload']).'</form>';
	print_end();
}

function send_sessions(){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT nickname, style, lastpost, status, useragent, ip FROM ' . PREFIX . 'sessions WHERE entry!=0 AND (incognito=0 OR status<? OR nickname=?) ORDER BY status DESC, lastpost DESC;');
	$stmt->execute([$U['status'], $U['nickname']]);
	if(!$lines=$stmt->fetchAll(PDO::FETCH_ASSOC)){
		$lines=[];
	}
	print_start('sessions');
	echo "<h1>$I[sessact]</h1><table>";
	echo "<tr><th>$I[sessnick]</th><th>$I[sesstimeout]</th><th>$I[sessua]</th>";
	$trackip=(bool) get_setting('trackip');
	$memexpire=(int) get_setting('memberexpire');
	$guestexpire=(int) get_setting('guestexpire');
	if($trackip) echo "<th>$I[sesip]</th>";
	echo "<th>$I[actions]</th></tr>";
	foreach($lines as $temp){
		if($temp['status']==0){
			$s=' (K)';
		}elseif($temp['status']<=2){
			$s=' (G)';
		}elseif($temp['status']==3){
			$s='';
		}elseif($temp['status']==5){
			$s=' (M)';
		}elseif($temp['status']==6){
			$s=' (SM)';
		}elseif($temp['status']==7){
			$s=' (A)';
		}else{
			$s=' (SA)';
		}
		echo '<tr><td class="nickname">'.style_this(htmlspecialchars($temp['nickname']).$s, $temp['style']).'</td><td class="timeout">';
		if($temp['status']>2){
			echo get_timeout($temp['lastpost'], $memexpire);
		}else{
			echo get_timeout($temp['lastpost'], $guestexpire);
		}
		echo '</td>';
		if($U['status']>$temp['status'] || $U['nickname']===$temp['nickname']){
			echo "<td class=\"ua\">$temp[useragent]</td>";
			if($trackip){
				echo "<td class=\"ip\">$temp[ip]</td>";
			}
			echo '<td class="action">';
			if($temp['nickname']!==$U['nickname']){
				echo '<table><tr>';
				if($temp['status']!=0){
					echo '<td>';
					echo form('admin', 'sessions');
					echo hidden('kick', '1').hidden('nick', htmlspecialchars($temp['nickname'])).submit($I['kick']).'</form>';
					echo '</td>';
				}
				echo '<td>';
				echo form('admin', 'sessions');
				echo hidden('logout', '1').hidden('nick', htmlspecialchars($temp['nickname'])).submit($temp['status']==0 ? $I['unban'] : $I['logout']).'</form>';
				echo '</td></tr></table>';
			}else{
				echo '-';
			}
			echo '</td></tr>';
		}else{
			echo '<td class="ua">-</td>';
			if($trackip){
				echo '<td class="ip">-</td>';
			}
			echo '<td class="action">-</td></tr>';
		}
	}
	echo "</table><br>";
	echo form('admin', 'sessions').submit($I['reload']).'</form>';
	print_end();
}

//MODIFICATION 2019-09-06 Featrue: last login table. function send_lastlogin() added.
function send_lastlogin(){
    global $I, $U, $db;
    
    if($U['status']>=7){
        $stmt=$db->prepare('SELECT nickname, status, lastlogin, style FROM ' . PREFIX . 'members ORDER BY status DESC, lastlogin DESC');
        $stmt->execute();
        
        if(!$lines=$stmt->fetchAll(PDO::FETCH_ASSOC)){
           $lines=[];
        }
        print_start('lastlogin');
        echo "<h1>Last logins</h1><table id=table_lastlogins>";
               
        echo "<tr><th>Nickname</th><th>Last login</th>";
        
        foreach($lines as $temp){
        
            if($temp['status']==0){
                $s=' (K)';
            }elseif($temp['status']<=2){
                $s=' (G)';
            }elseif($temp['status']==3){
                $s='';
            }elseif($temp['status']==5){
                $s=' (M)';
            }elseif($temp['status']==6){
                $s=' (SM)';
            }elseif($temp['status']==7){
                $s=' (A)';
            }else{
                $s=' (SA)';
            }
        
            echo '<tr><td class="nickname">'.style_this(htmlspecialchars($temp['nickname']).$s, $temp['style'].'</td>');
            if($temp['lastlogin'] === '0'){
                echo '<td class="lastlogin">unknown</td>';
            }
            else{
                echo '<td class="lastlogin">'.date('l jS \of F Y h:i:s A', $temp['lastlogin']).'</td>';
            }
      }
        echo "</table><br>";
        echo form('admin', 'lastlogin').submit($I['reload']).'</form>';
        print_end();
        
    }
}
    
function send_gallery($site = 1){
    global $I, $U, $db; 
    
    //gallery version v0.9.3
    
    $link = '';
    $links = [];
    $reallinks = [];
    $tempmessage = '';
    $gallery_reload_button = 0;
    
    //Use the following patterns, if "force redirection" is enabled in the chat setup.
    //pattern to find the hyperlinks. you can add the allowed file extensions here.
    $pattern = 'url=http.*\.(jpg|jpeg|png|gif|bmp)';
    
    //pattern to find just the url (within the hyperlinks). Finds http and https links.
    $pattern2 = 'https?\%3A\%2F\%2F.*\.(jpg|jpeg|png|gif|bmp)';
    
    
    $GalleryAdminStatus = '5';
    
    print_start('gallery');
    echo "<h1>The Underground Gallery</h1>";
    
    if($U['status']<(int)get_setting('galleryaccess')){
    
        echo "You are not allowed to view the gallery";
    }

    
    if(!get_setting('forceredirect')){
    
        echo "Please enable \"force redirect\" in the chat setup. The gallery  won't work otherwise.";
    }
    
    elseif($U['status']>=(int)get_setting('galleryaccess')){
        
        
        // Modification chat rooms so that images in channels not visible to the chatter are not shown
        $stmt=$db->prepare('SELECT id, text FROM ' . PREFIX . 'messages WHERE poststatus<=? AND (roomid= ANY (SELECT id FROM ' . PREFIX .'rooms WHERE access<=?) OR roomid IS NULL OR poststatus>1)  ORDER BY id DESC;');
        $stmt->execute([$U['status'], $U['status']]);
        
        
        //START OF LINKS DETECTION Here we start to detect the links in the messages 
        /*************************/
        
        $matches =  [];
        $number_of_matches = 0;
        
        while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
            
            prepare_message_print($message, true);//decrypt message if encrypted.
            
            $tempmessage = $message['text'];
             
            
             
             //Debug
             //echo "Debug. ".htmlspecialchars($message['text'])."<br><br>";
                 
            //Description: Finds all picture-hyperlinks wihtin one message 
            preg_match('/'.$pattern.'/i', $tempmessage, $matches);
            if (!empty($matches['0'])){
                 
                 $tempmatch = $matches['0'];     ;
                
                //Description: Finds all urls within one message
                preg_match('/'.$pattern2.'/i', $tempmatch, $matches);
                 if (!empty($matches['0'])){
                    $link = $matches['0'];
                 }
                 
                 
                 $token = strtok($link, " ");

                while ($token !== false)
                {
                                      
                    $links[] = $token;
                    $token = strtok(" ");
                    
                } 
                 
                $number_of_matches++;
            }
        }    

            $arrlength = count($links);
            for($x = 0; $x < $arrlength; $x++) {
                preg_match('/'.$pattern2.'/i', $links[$x], $matches);
                if (!empty($matches['0'])){
                    $reallinks[] = $matches['0'];
                }
            }
            
            
            //END OF LINKS DETECTION. Now we can build the gallery with the detected links.
        
            
            
            //START OF PRINTING GALLERY
            /**************************/
            $posts_per_site = 12; //default value is 12. Change this value to set the number of pictures per gallery site.
            $start = ($site-1)*$posts_per_site;
            
            
            //Hyperlinks and embedded images
            //count number of links
            $number_of_links = count($reallinks);
            
            
            //PAGINATION CONTROLS TO NAVIGATRE THROUGH GALLERY
            //Calculate the number of sites
            $number_of_sites = ceil($number_of_links / floatval($posts_per_site));
        
            //Print links to the different sites
            //---------------------------------------------
            echo "<p></p><div id='pagination_controls'>";
            echo "<table id='pagination_controls_table'><tr>";
            if (($site > 1)&&($site <= $number_of_sites)){
                echo '<td>'.form('gallery', $site-1).submit('Previous').'</form>&nbsp;&nbsp;&nbsp;&nbsp</td>';
            }
        
            for($a=1; $a <= $number_of_sites; $a++) {   
        
                //If user is on _this_ site, do not print a link		
                if($site == $a){
                    echo '<td>'.form('gallery', $a).submit($a, "id='current_site_button'").'</form>&nbsp;&nbsp</td>';
                    $gallery_reload_button = $a;
                } 
                else {
                    //user is not on _this_ site, so print a link.
                    echo '<td>'.form('gallery', $a).submit(' '.$a.' ').'</form>&nbsp;&nbsp</td>';
                    
                }
            }
        
            if (($site >= 1)&&($site < $number_of_sites)){
                echo '<td>'.form('gallery', $site+1).submit('Next').'</form>&nbsp;&nbsp;&nbsp;&nbsp</td>';
                
            }			
            echo '</tr></table>';
            echo "</div>";	
            //END OF PAGNINATION CONTROLS
            
            echo "<div id='live_gallery_div'><table id='live_gallery_table' width='1000' cellpadding='10'><tbody><tr>";
            if (($start + $posts_per_site) < $number_of_links){

                $arrlength2 = $start + $posts_per_site;
                
            }else{
                $arrlength2 = $number_of_links;
            }
            $column_number = 0;
       
            
            for($x = $start; $x < $arrlength2; $x++) {
                
                $y = $x+1;
          
                echo "<td align='center'><a href='#imgdiv".$y."'><img id='img".$y."' class='img' src='".urldecode($reallinks[$x])."' height='100'></a><br>";
                          
                //echo "<br>";
                $column_number++;	
                if ($column_number === 6){//default value is 6. change this value to show more pictures in one row
					echo "</tr><tr>";
					$column_number = 0;
                }
                
                
                
            }
           
            echo "</tbody></table></div>";
            
            //gallery reload button
            //debug
            //echo "gallery reload button value: ".$gallery_reload_button;
            
            echo "<table id='gallery_navigation_table'><tr><td>";
            if($gallery_reload_button >= 1){
                echo "<br>".form('gallery', $gallery_reload_button).submit($I['reload']).'</form>'; 
            }else{
                echo "<br>".form('gallery').submit($I['reload']).'</form>'; 
            }
            
            echo "</td><td>";
            echo form_target('view', 'view').submit('Back to chat').'</form>';
            echo "</td></tr></table>";
        
             
           
            
            echo "<br>";
            //the variables use here are define avove pagination controls  in the code. see above.
            //Print a div for each image. Each of them must be hided through css. A div that becomes target must be displayed (with css).
            for($x = $start; $x < $arrlength2; $x++){
                
                 $y = $x+1;
                 echo "<a href='#live_gallery_table'><div id='imgdiv".$y."' class='imgdiv'><img class='galleryimg' src='".urldecode($reallinks[$x])."'></a><br>".apply_linkfilter(create_hotlinks(urldecode($reallinks[$x])))."</div>";
                    
            }
            //END OF PRINTING GALLERY
       
    }
    print_end();
}

//MODIFICATION links page
function send_links_page(){
    
    global $I;
    
    if(get_setting('linksenabled')==='1'){
        $links=get_setting('links');
        
        print_start('links');
        $links=get_setting('links');
        if(!empty($links)){
            echo "<div id=\"links\"><h2>The Underground Links</h2><br>$links<br>".form_target('view', 'view').submit('Back to chat')."</form></div>";
        }
    }
    else{
        return;
    }
}

// Modification change chat rooms
function change_room(){
	global $U, $db;
	if($_REQUEST['room']==='*'){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET roomid=NULL WHERE id=?;');
		$stmt->execute([$U['id']]);
		return;
	}
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET roomid=(SELECT id FROM ' . PREFIX . 'rooms WHERE id=? AND access<=?) WHERE id=?;');
	$stmt->execute([$_REQUEST['room'],$U['status'], $U['id']]);

}

// Modification select chat rooms
function print_rooms(){
	global $db, $U;
	echo '<div id="roomblock"><h4>Rooms:</h4>';
	echo form_target('view', 'view');
	echo "<select name=\"room\">";
	echo '<option value="*">[Main Chat]</option>';
	$stmt=$db->prepare('SELECT id, name FROM ' . PREFIX . 'rooms WHERE access<=? ORDER BY id ASC;');
	$stmt->execute([$U['status']]);
	if(!$rooms=$stmt->fetchAll(PDO::FETCH_ASSOC)){
		$rooms=[];
	}
	foreach($rooms as $room){
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'sessions WHERE roomid=?;');
		$stmt->execute([$room['id']]);
		$num = count($stmt->fetchAll());
		echo "<option value=\"$room[id]\"";
		if($U['roomid']===$room['id']){
			echo ' selected';
		}
		echo ">$room[name] ($num)</option>";
	}
	echo '</select>'.submit('Switch').'</form>';
	echo '</div>';
}

// Modification rooms in admin page
function send_rooms($arg=''){
	global $I, $U, $db;
	print_start('linkfilter');
	echo "<h2>Chat Rooms</h2><i>$arg</i><table>";
	thr();
	echo '<tr><th><table style="width:100%;"><tr>';
	echo "<td style=\"width:8em;\">Room ID:</td>";
	echo "<td style=\"width:12em;\">Name</td>";
	echo "<td style=\"width:12em;\">Access</td>";
	if($U['status']>6){
		echo "<td style=\"width:10em;\">Permanent</td>";
	}
	echo "<td style=\"width:5em;\">$I[apply]</td>";
	echo "<td style=\"width:8em;\">Expires in</td>";
	echo '</tr></table></th></tr>';
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'rooms WHERE access<=? ORDER BY id ASC;');
	$stmt->execute([$U['status']]);
	if(!$rooms=$stmt->fetchAll(PDO::FETCH_ASSOC)){
		$rooms=[];
	}
	foreach($rooms as $room){
		if($room['permanent'] && $U['status']<=6){
			continue;
		}
		if($room['permanent']){
			$checkedpm = ' checked';
		}else{
			$checkedpm = '';
		}
		echo '<tr><td>';
		echo form('admin', 'rooms').hidden('id', $room['id']);
		echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">Room $room[id]:</th>";
		echo "<td style=\"width:12em;\"><input type=\"text\" name=\"name\" value=\"$room[name]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em;">';
			echo "<select name=\"access\">";
			
			$options = array(1, 2, 3, 5, 6, 7, 8, 10);
			
		    foreach($options as $option){
		    	if($U['status'] < $option){
		    		break;
		    	}
				echo "<option value=\"$option\"";
				
				if($room['access']==$option){
					echo ' selected';
				}
				
				if ($option == 1) echo ">All</option>";
				elseif($option == 2) echo ">Registered guests</option>";
				elseif($option == 3) echo ">Members</option>";
				elseif($option == 5) echo ">Moderators</option>";
				elseif($option == 6) echo ">Super Moderators</option>";
				elseif($option == 7) echo ">Admins</option>";
				elseif($option == 8) echo ">Super Admins</option>";
				elseif($option == 10) echo ">Disabled</option>";
			}

	    echo '</select></td>';
	    if($U['status']>6){
			echo "<td style=\"width:10em;\"><label><input type=\"checkbox\" name=\"permanent\" value=\"1\"$checkedpm>Permanent</label></td>";
	    }
		echo '<td class="roomsubmit" style="width:5em;">'.submit($I['change']).'</td>';
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'sessions WHERE roomid=?;');
		$stmt->execute([$room['id']]);
		if($stmt->fetch(PDO::FETCH_NUM) || $room['permanent']){
			echo"<th style=\"width:8em;\">--:--</th>";
		}else{
			$expire= (int) get_setting('roomexpire');
			echo"<th style=\"width:8em;\">".get_timeout($room['time'], $expire).'</th>';
		}
		echo "</tr></table></form></td></tr>";

	}
	echo '<tr><td>';
	echo form('admin', 'rooms').hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">New Room</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"name\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo '<td style="width:12em;">';
		echo "<select name=\"access\">";
		
		$options = array(1, 2, 3, 5, 6, 7, 8, 10);
		
	    foreach($options as $option){
	    	if($U['status'] < $option){
	    		break;
	    	}
			echo "<option value=\"$option\"";
			
			if ($option == 1) echo ">All</option>";
			elseif($option == 2) echo ">Registered guests</option>";
			elseif($option == 3) echo ">Members</option>";
			elseif($option == 5) echo ">Moderators</option>";
			elseif($option == 6) echo ">Super Moderators</option>";
			elseif($option == 7) echo ">Admins</option>";
			elseif($option == 8) echo ">Super Admins</option>";
			elseif($option == 10) echo ">Disabled</option>";
		}
		
    echo '</select></td>';
    if($U['status']>6){
		echo "<td style=\"width:10em;\"><label><input type=\"checkbox\" name=\"permanent\" value=\"1\">Permanent</label></td>";
    }
	echo '<td class="roomsubmit" style="width:5em;">'.submit($I['add']).'</td>';
	echo"<th style=\"width:8em;\"></th>";
	echo "</tr></table></form></td></tr></table><br>";
	echo form('admin', 'rooms').submit($I['reload']).'</form>';
	print_end();
}

//Forum Link was moved to the post box (function send_post)
/*
function send_to_forum(){

    echo "Add redirect to forum here";

}
*/

// Modification chat rooms.
function manage_rooms(){
	global $U, $db;
	if(!isset($_REQUEST['id']) || !isset($_REQUEST['access']) || !isset($_REQUEST['name']) || $U['status']<$_REQUEST['access']){
		return;
	}
	if(!preg_match('/^[A-Za-z0-9\-]{0,50}$/', $_REQUEST['name'])){
		return "Invalid Name.";
	}
	if(isset($_REQUEST['permanent']) && $_REQUEST['permanent'] && $U['status']>6){
		$permanent = 1;
	}else{
		$permanent = 0;
	}
	if($_REQUEST['id']==='+' && $_REQUEST['name']!==''){
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'rooms WHERE name=?');
		$stmt->execute([$_REQUEST['name']]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			return;
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'rooms (name, access, time, permanent) VALUES (?, ?, ?, ?);');
		$stmt->execute([$_REQUEST['name'], $_REQUEST['access'], time(), $permanent]);
	}elseif($_REQUEST['name'] !== ''){
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'rooms WHERE name=? AND id!=?;');
		$stmt->execute([$_REQUEST['name'], $_REQUEST['id']]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			return;
		}
		if($U['status']<7){
			$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'rooms WHERE id=? AND permanent=1;');
			$stmt->execute([$_REQUEST['id']]);
			if($stmt->fetch(PDO::FETCH_NUM)){
				return;
			}
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'rooms SET name=?, access=?, permanent=? WHERE id=? AND access<=?;');
		$stmt->execute([$_REQUEST['name'], $_REQUEST['access'], $permanent,$_REQUEST['id'], $U['status']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET roomid=NULL WHERE roomid=? AND status<?;');
		$stmt->execute([$_REQUEST['id'], $_REQUEST['access']]);
	}else{
		remove_room(false, $_REQUEST['id'], $U['status']);
	}

}

function remove_room($all=false, $id='', $status=10){
	global $db;
	if($all){
		//placeholder
	}else{
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . "rooms WHERE id=? AND access<=?;");
		$stmt->execute([$id, $status]);
		if($room=$stmt->fetch(PDO::FETCH_ASSOC)){
			$name=$stmt->fetch(PDO::FETCH_NUM);
			$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'rooms WHERE id=?;');
			$stmt->execute([$room['id']]);
			$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE roomid=?;');
			$stmt->execute([$room['id']]);
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET roomid=NULL WHERE roomid=?;');
			$stmt->execute([$room['id']]);
		}
	}

}


function check_filter_match(&$reg){
	global $I;
	$_REQUEST['match']=htmlspecialchars($_REQUEST['match']);
	if(isset($_REQUEST['regex']) && $_REQUEST['regex']==1){
		if(!valid_regex($_REQUEST['match'])){
			return "$I[incorregex]<br>$I[prevmatch]: $_REQUEST[match]";
		}
		$reg=1;
	}else{
		$_REQUEST['match']=preg_replace('/([^\w\d])/u', "\\\\$1", $_REQUEST['match']);
		$reg=0;
	}
	if(mb_strlen($_REQUEST['match'])>255){
		return "$I[matchtoolong]<br>$I[prevmatch]: $_REQUEST[match]";
	}
	return false;
}

function manage_filter(){
	global $db, $memcached;
	if(isset($_REQUEST['id'])){
		$reg=0;
		if($tmp=check_filter_match($reg)){
			return $tmp;
		}
		if(isset($_REQUEST['allowinpm']) && $_REQUEST['allowinpm']==1){
			$pm=1;
		}else{
			$pm=0;
		}
		if(isset($_REQUEST['kick']) && $_REQUEST['kick']==1){
			$kick=1;
		}else{
			$kick=0;
		}
		if(isset($_REQUEST['cs']) && $_REQUEST['cs']==1){
			$cs=1;
		}else{
			$cs=0;
		}
		if(preg_match('/^[0-9]+$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'filter WHERE id=?;');
				$stmt->execute([$_REQUEST['id']]);
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'filter SET filtermatch=?, filterreplace=?, allowinpm=?, regex=?, kick=?, cs=? WHERE id=?;');
				$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $cs, $_REQUEST['id']]);
			}
		}elseif($_REQUEST['id']==='+'){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES (?, ?, ?, ?, ?, ?);');
			$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $cs]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
		}
	}
}

function manage_linkfilter(){
	global $db, $memcached;
	if(isset($_REQUEST['id'])){
		$reg=0;
		if($tmp=check_filter_match($reg)){
			return $tmp;
		}
		if(preg_match('/^[0-9]+$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'linkfilter WHERE id=?;');
				$stmt->execute([$_REQUEST['id']]);
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'linkfilter SET filtermatch=?, filterreplace=?, regex=? WHERE id=?;');
				$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $reg, $_REQUEST['id']]);
			}
		}elseif($_REQUEST['id']==='+'){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
			$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $reg]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		}
	}
}

function get_filters(){
	global $db, $memcached;
	if(MEMCACHED){
		$filters=$memcached->get(DBNAME . '-' . PREFIX . 'filter');
	}
	if(!MEMCACHED || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=[];
		$result=$db->query('SELECT id, filtermatch, filterreplace, allowinpm, regex, kick, cs FROM ' . PREFIX . 'filter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=['id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick'], 'cs'=>$filter['cs']];
		}
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . 'filter', $filters);
		}
	}
	return $filters;
}

function get_linkfilters(){
	global $db, $memcached;
	if(MEMCACHED){
		$filters=$memcached->get(DBNAME . '-' . PREFIX . 'linkfilter');
	}
	if(!MEMCACHED || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=[];
		$result=$db->query('SELECT id, filtermatch, filterreplace, regex FROM ' . PREFIX . 'linkfilter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=['id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']];
		}
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . 'linkfilter', $filters);
		}
	}
	return $filters;
}

function send_filter($arg=''){
	global $I, $U;
	print_start('filter');
	echo "<h2>$I[filter]</h2><i>$arg</i><table>";
	thr();
	echo '<tr><th><table style="width:100%;"><tr>';
	echo "<td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:9em;\">$I[allowpm]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[kick]</td>";
	echo "<td style=\"width:5em;\">$I[cs]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td>";
	echo '</tr></table></th></tr>';
	$filters=get_filters();
	foreach($filters as $filter){
		if($filter['allowinpm']==1){
			$check=' checked';
		}else{
			$check='';
		}
		if($filter['regex']==1){
			$checked=' checked';
		}else{
			$checked='';
			$filter['match']=preg_replace('/(\\\\(.))/u', "$2", $filter['match']);
		}
		if($filter['kick']==1){
			$checkedk=' checked';
		}else{
			$checkedk='';
		}
		if($filter['cs']==1){
			$checkedcs=' checked';
		}else{
			$checkedcs='';
		}
		echo '<tr><td>';
		echo form('admin', 'filter').hidden('id', $filter['id']);
		echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[filter] $filter[id]:</th>";
		echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"$filter[match]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em;"><input type="text" name="replace" value="'.htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
		echo "<td style=\"width:9em;\"><label><input type=\"checkbox\" name=\"allowinpm\" value=\"1\"$check>$I[allowpm]</label></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" value=\"1\"$checked>$I[regex]</label></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"kick\" value=\"1\"$checkedk>$I[kick]</label></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"cs\" value=\"1\"$checkedcs>$I[cs]</label></td>";
		echo '<td class="filtersubmit" style="width:5em;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	echo form('admin', 'filter').hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em\">$I[newfilter]</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:9em;\"><label><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm\" value=\"1\">$I[allowpm]</label></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" id=\"regex\" value=\"1\">$I[regex]</label></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"1\">$I[kick]</label></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"cs\" id=\"cs\" value=\"1\">$I[cs]</label></td>";
	echo '<td class="filtersubmit" style="width:5em;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>";
	echo form('admin', 'filter').submit($I['reload']).'</form>';
	print_end();
}

function send_linkfilter($arg=''){
	global $I, $U;
	print_start('linkfilter');
	echo "<h2>$I[linkfilter]</h2><i>$arg</i><table>";
	thr();
	echo '<tr><th><table style="width:100%;"><tr>';
	echo "<td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td>";
	echo '</tr></table></th></tr>';
	$filters=get_linkfilters();
	foreach($filters as $filter){
		if($filter['regex']==1){
			$checked=' checked';
		}else{
			$checked='';
			$filter['match']=preg_replace('/(\\\\(.))/u', "$2", $filter['match']);
		}
		echo '<tr><td>';
		echo form('admin', 'linkfilter').hidden('id', $filter['id']);
		echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[filter] $filter[id]:</th>";
		echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"$filter[match]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em;"><input type="text" name="replace" value="'.htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" value=\"1\"$checked>$I[regex]</label></td>";
		echo '<td class="filtersubmit" style="width:5em;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	echo form('admin', 'linkfilter').hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[newfilter]</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" value=\"1\">$I[regex]</label></td>";
	echo '<td class="filtersubmit" style="width:5em;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>";
	echo form('admin', 'linkfilter').submit($I['reload']).'</form>';
	print_end();
}

function send_frameset(){
	global $I, $U, $db, $language;
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd"><html><head>'.meta_html();
	echo '<title>'.get_setting('chatname').'</title>';
	print_stylesheet();
	echo '</head><body>';
	if(isset($_REQUEST['sort'])){
		if($_REQUEST['sort']==1){
			$U['sortupdown']=1;
			$tmp=$U['nocache'];
			$U['nocache']=$U['nocache_old'];
			$U['nocache_old']=$tmp;
		}else{
			$U['sortupdown']=0;
			$tmp=$U['nocache'];
			$U['nocache']=$U['nocache_old'];
			$U['nocache_old']=$tmp;
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET sortupdown=?, nocache=?, nocache_old=? WHERE nickname=?;');
		$stmt->execute([$U['sortupdown'], $U['nocache'], $U['nocache_old'], $U['nickname']]);
		if($U['status']>1){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET sortupdown=?, nocache=?, nocache_old=? WHERE nickname=?;');
			$stmt->execute([$U['sortupdown'], $U['nocache'], $U['nocache_old'], $U['nickname']]);
		}
	}	if(($U['status']>=5 || ($U['status']>2 && get_count_mods()==0)) && get_setting('enfileupload')>0 && get_setting('enfileupload')<=$U['status']){
		$postheight='120px';
	}else{
		$postheight='100px';
	}
	$bottom='';
	if(get_setting('enablegreeting')){
		$action_mid='greeting';
	} else {
		if($U['sortupdown']){
			$bottom='#bottom';
		}
		$action_mid='view';
	}
	if((!isset($_REQUEST['sort']) && !$U['sortupdown']) || (isset($_REQUEST['sort']) && $_REQUEST['sort']==0)){
		$action_top='post';
		$action_bot='controls';
		$sort_bot='&sort=1';
		$frameset_mid_style="position:fixed;top:$postheight;bottom:45px;left:0;right:0;margin:0;padding:0;overflow:hidden;";
		$frameset_top_style="position:fixed;top:0;left:0;right:0;height:$postheight;margin:0;padding:0;overflow:hidden;border-bottom: 1px solid;";
		$frameset_bot_style="position:fixed;bottom:0;left:0;right:0;height:45px;margin:0;padding:0;overflow:hidden;border-top:1px solid;";
        $noscroll_bot = "scrolling=\"yes\" style=\"overflow-y:hidden !important;\"";
        $noscroll_top ="";
	}else{
		$action_top='controls';
		$action_bot='post';
		$sort_bot='';
		$frameset_mid_style="position:fixed;top:45px;bottom:$postheight;left:0;right:0;margin:0;padding:0;overflow:hidden;";
		$frameset_top_style="position:fixed;top:0;left:0;right:0;height:45px;margin:0;padding:0;overflow:hidden;border-bottom:1px solid;";
		$frameset_bot_style="position:fixed;bottom:0;left:0;right:0;height:$postheight;margin:0;padding:0;overflow:hidden;border-top:1px solid;";
		$noscroll_top = "scrolling=\"yes\" style=\"overflow-y:hidden !important;\"";
		$noscroll_bot ="";
	}
	echo "<div id=\"frameset-mid\" style=\"$frameset_mid_style\"><iframe name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=$action_mid&session=$U[session]&lang=$language$bottom\">".noframe_html()."</iframe></div>";
	echo "<div id=\"frameset-top\" style=\"$frameset_top_style\"><iframe $noscroll_top name=\"$action_top\" src=\"$_SERVER[SCRIPT_NAME]?action=$action_top&session=$U[session]&lang=$language\">".noframe_html()."</iframe></div>";
	echo "<div id=\"frameset-bot\" style=\"$frameset_bot_style\"><iframe $noscroll_bot name=\"$action_bot\" src=\"$_SERVER[SCRIPT_NAME]?action=$action_bot&session=$U[session]&lang=$language$sort_bot\">".noframe_html()."</iframe></div>";
	echo "<div style=\"position: absolute; bottom: 9%; right: 4%; width: 190px; height: 95px;\"><iframe allowtransparency = \"true\" name=\"rooms\" style=\"overflow:none\" src=\"$_SERVER[SCRIPT_NAME]?action=rooms&session=$U[session]&lang=$language$sort_bot\">".noframe_html()."</iframe></div>";
	echo '</body></html>';
	exit;
}

function rooms(){
    print_start('rooms');
    // if(show_rooms()){
        print_rooms();
    //}
    print_end();
}

function show_rooms($true="false"){
    $handle = curl_init();
    curl_setopt_array($handle, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 0,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,));
    curl_exec($handle);
    if($true){
        return true;
    }else{
        return false;
    }
}
function noframe_html() : string {
	global $I;
	return "$I[noframes]".form_target('_parent', '').submit($I['backtologin'], 'class="backbutton"').'</form>';
}

function send_messages(){
	global $I, $U, $language;
	if($U['nocache']){
		$nocache='&nc='.substr(time(), -6);
	}else{
		$nocache='';
	}
	if($U['sortupdown']){
		$sort='#bottom';
	}else{
		$sort='';
	}
	$modroom="";
	if(isset($_REQUEST['modroom']) && $_REQUEST['modroom']){
		$modroom='&modroom=1';
	}
	print_start('messages', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language$nocache$sort$modroom" . uniqid('?r='));
	echo '<a id="top"></a>';
	echo "<a id=\"bottom_link\" href=\"#bottom\">$I[bottom]</a>";
	//MODIFICATION We don't like the manual refresh box.
	//echo "<div id=\"manualrefresh\"><br>$I[manualrefresh]<br>".form('view').submit($I['reload']).'</form><br></div>';
	//Modification for mod room for rooms
	/*if(isset($_REQUEST['modroom']) && $_REQUEST['modroom']=1 && $U['status']>=5){
		echo '<div id="modroomreload">';
		echo form('view').hidden('modroom','1').submit($I['reload']).'</form>';
		echo '</div>';
		print_messages(0,1);
		
	}else{*/
		if(!$U['sortupdown']){
			echo '<div id="topic">';
			echo get_setting('topic');
			echo '</div>';
			print_chatters();
			print_notifications();
			print_messages();
		}else{
			print_messages();
			print_notifications();
			print_chatters();
			echo '<div id="topic">';
			echo get_setting('topic');
			echo '</div>';
		}
	//}
	echo "<a id=\"bottom\"></a><a id=\"top_link\" href=\"#top\">$I[top]</a>";
	print_end();
}

function send_inbox(){
	global $I, $U, $db;
	print_start('inbox');
	echo form('inbox', 'clean').submit($I['delselmes'], 'class="delbutton"').'<br><br>';
	$dateformat=get_setting('dateformat');
	if(!$U['embed'] && get_setting('imgembed')){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	if($U['sortupdown']){
		$direction='ASC';
	}else{
		$direction='DESC';
	}
	$stmt=$db->prepare('SELECT id, postdate, text FROM ' . PREFIX . "inbox WHERE recipient=? ORDER BY id $direction;");
	$stmt->execute([$U['nickname']]);
	while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
		prepare_message_print($message, $removeEmbed);
		echo "<div class=\"msg\"><label><input type=\"checkbox\" name=\"mid[]\" value=\"$message[id]\">";
		if($timestamps){
			echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
		}
		echo " $message[text]</label></div>";
	}
	echo '</form><br>'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>';
	print_end();
}

// Modification type 3 is spare notes

function send_notes($type){
	global $I, $U, $db;
	print_start('notes');
	$personalnotes=(bool) get_setting('personalnotes');
	$sparenotesaccess = (int) get_setting('sparenotesaccess');
	// Modification Spare notes
	if(($U['status']>=5 && ($personalnotes || $U['status']>6 )) || ($personalnotes && $U['status']>=$sparenotesaccess)){
		echo '<table><tr>';
		if($U['status']>6){
			echo '<td>'.form_target('view', 'notes', 'admin').submit($I['admnotes']).'</form></td>';
		}
		if($U['status']>=5){
			echo '<td>'.form_target('view', 'notes', 'staff').submit($I['staffnotes']).'</form></td>';
		}
		if($personalnotes){
			echo '<td>'.form_target('view', 'notes').submit($I['personalnotes']).'</form></td>';
		}
		if($U['status']>=$sparenotesaccess){
			echo '<td>'.form_target('view', 'notes', 'spare').submit(get_setting('sparenotesname')).'</form></td>';
		}
		echo '</tr></table>';
	}
	if($type===1){
		echo "<h2>$I[staffnotes]</h2><p>";
		$hiddendo=hidden('do', 'staff');
	}elseif($type===0){
		echo "<h2>$I[adminnotes]</h2><p>";
		$hiddendo=hidden('do', 'admin');
	// Modification spare notes
	}elseif ($type===3) {
		echo '<h2>'.get_setting('sparenotesname').'</h2><p>';
		$hiddendo=hidden('do', 'spare');
	}else{
		echo "<h2>$I[personalnotes]</h2><p>";
		$hiddendo='';
	}
	if(isset($_REQUEST['text'])){
		if(MSGENCRYPTED){
            $_REQUEST['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($_REQUEST['text'], '', AES_IV, ENCRYPTKEY));
		}
		$time=time();
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
		$stmt->execute([$type, $time, $U['nickname'], $_REQUEST['text']]);
		echo "<b>$I[notessaved]</b> ";
	}
	$dateformat=get_setting('dateformat');
	if($type!==2){
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'notes WHERE type=?;');
		$stmt->execute([$type]);
	}else{
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'notes WHERE type=? AND editedby=?;');
		$stmt->execute([$type, $U['nickname']]);
	}
	$num=$stmt->fetch(PDO::FETCH_NUM);
	if(!empty($_REQUEST['revision'])){
		$revision=intval($_REQUEST['revision']);
	}else{
		$revision=0;
	}
	if($type!==2){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . "notes WHERE type=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
		$stmt->execute([$type]);
	}else{
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . "notes WHERE type=? AND editedby=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
		$stmt->execute([$type, $U['nickname']]);
	}
	if($note=$stmt->fetch(PDO::FETCH_ASSOC)){
		printf($I['lastedited'], htmlspecialchars($note['editedby']), date($dateformat, $note['lastedited']));
	}else{
		$note['text']='';
	}
	if(MSGENCRYPTED){
        $note['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($note['text']), null, AES_IV, ENCRYPTKEY);
	}
	echo "</p>".form('notes');
	echo "$hiddendo<textarea name=\"text\">".htmlspecialchars($note['text']).'</textarea><br>';
	echo submit($I['savenotes']).'</form><br>';
	if($num[0]>1){
		echo "<br><table><tr><td>$I[revisions]</td>";
		if($revision<$num[0]-1){
			echo '<td>'.form('notes').hidden('revision', $revision+1);
			echo $hiddendo.submit($I['older']).'</form></td>';
		}
		if($revision>0){
			echo '<td>'.form('notes').hidden('revision', $revision-1);
			echo $hiddendo.submit($I['newer']).'</form></td>';
		}
		echo '</tr></table>';
	}
	print_end();
}

function send_approve_waiting(){
	global $I, $db;
	print_start('approve_waiting');
	echo "<h2>$I[waitingroom]</h2>";
	$result=$db->query('SELECT * FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1 ORDER BY id LIMIT 100;');
	if($tmp=$result->fetchAll(PDO::FETCH_ASSOC)){
		echo form('admin', 'approve');
		echo '<table>';
		echo "<tr><th>$I[sessnick]</th><th>$I[sessua]</th></tr>";
		foreach($tmp as $temp){
			echo '<tr>'.hidden('alls[]', htmlspecialchars($temp['nickname']));
			echo '<td><label><input type="checkbox" name="csid[]" value="'.htmlspecialchars($temp['nickname']).'">';
			echo style_this(htmlspecialchars($temp['nickname']), $temp['style']).'</label></td>';
			echo "<td>$temp[useragent]</td></tr>";
		}
		echo "</table><br><table id=\"action\"><tr><td><label><input type=\"radio\" name=\"what\" value=\"allowchecked\" id=\"allowchecked\" checked>$I[allowchecked]</label></td>";
		echo "<td><label><input type=\"radio\" name=\"what\" value=\"allowall\" id=\"allowall\">$I[allowall]</label></td>";
		echo "<td><label><input type=\"radio\" name=\"what\" value=\"denychecked\" id=\"denychecked\">$I[denychecked]</label></td>";
		echo "<td><label><input type=\"radio\" name=\"what\" value=\"denyall\" id=\"denyall\">$I[denyall]</label></td></tr><tr><td colspan=\"8\">$I[denymessage] <input type=\"text\" name=\"kickmessage\" size=\"45\"></td>";
		echo '</tr><tr><td colspan="8">'.submit($I['butallowdeny']).'</td></tr></table></form>';
	}else{
		echo "$I[waitempty]<br>";
	}
	echo '<br>'.form('admin', 'approve');
	echo submit($I['reload']).'</form>';
	echo '<br>'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>';
	print_end();
}

function send_waiting_room(){
	global $I, $U, $db, $language;
	$ga=(int) get_setting('guestaccess');
	if($ga===3 && (get_count_mods()>0 || !get_setting('modfallback'))){
		$wait=false;
	}else{
		$wait=true;
	}
	check_expired();
	check_kicked();
	$timeleft=get_setting('entrywait')-(time()-$U['lastpost']);
	if($wait && ($timeleft<=0 || $ga===1)){
		$U['entry']=$U['lastpost'];
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE session=?;');
		$stmt->execute([$U['session']]);
		send_frameset();
	}elseif(!$wait && $U['entry']!=0){
		send_frameset();
	}else{
		$refresh=(int) get_setting('defaultrefresh');
		print_start('waitingroom', $refresh, "$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]&lang=$language&nc=".substr(time(),-6));
		echo "<h2>$I[waitingroom]</h2><p>";
		if($wait){
			printf($I['waittext'], style_this(htmlspecialchars($U['nickname']), $U['style']), $timeleft);
		}else{
			printf($I['admwaittext'], style_this(htmlspecialchars($U['nickname']), $U['style']));
		}
		echo '</p><br><p>';
		printf($I['waitreload'], $refresh);
		echo '</p><br><br>';
		echo '<hr>'.form('wait');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['reload']).'</form><br>';
		echo form('logout');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['exit'], 'id="exitbutton"').'</form>';
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo "<div id=\"rules\"><h2>$I[rules]</h2><b>$rulestxt</b></div>";
		}
		print_end();
	}
}

function send_choose_messages(){
	global $I, $U;
	print_start('choose_messages');
	echo form('admin', 'clean');
	echo hidden('what', 'selected').submit($I['delselmes'], 'class="delbutton"').'<br><br>';
	print_messages($U['status']);
	echo '<br>'.submit($I['delselmes'], 'class="delbutton"')."</form>";
	print_end();
}

function send_del_confirm(){
	global $I;
	print_start('del_confirm');
	echo "<table><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>".form('delete');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isset($_REQUEST['sendto'])){
		echo hidden('sendto', $_REQUEST['sendto']);
	}
	echo hidden('confirm', 'yes').hidden('what', $_REQUEST['what']).submit($I['yes'], 'class="delbutton"').'</form></td><td>'.form('post');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isset($_REQUEST['sendto'])){
		echo hidden('sendto', $_REQUEST['sendto']);
	}
	echo submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}



function send_post($rejected=''){
	global $I, $U, $db;
	print_start('post');
	
	if(!isset($_REQUEST['sendto'])){
		$_REQUEST['sendto']='';
	}
	echo '<table><tr><td>'.form('post');
	echo hidden('postid', substr(time(), -6));
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo '<table><tr><td><table><tr id="firstline"><td>'.style_this(htmlspecialchars($U['nickname']), $U['style']).'</td><td>:</td>';
	if(isset($_REQUEST['multi'])){
		echo "<td><textarea name=\"message\" rows=\"3\" cols=\"40\" style=\"$U[style]\" autofocus>$rejected</textarea></td>";
	}else{
		//some lines changed for clickable nicknames that select username in the text box
		if(($rejected==='')&&(!empty($_REQUEST['nickname']))){
            echo "<td><input type=\"text\" name=\"message\" value=\"".$_REQUEST['nickname']."\" size=\"40\" style=\"$U[style]\" autofocus></td>";
		}
		else{
            echo "<td><input type=\"text\" name=\"message\" value=\"$rejected\" size=\"40\" style=\"$U[style]\" autofocus></td>";
		}
		
	}
	echo '<td>'.submit($I['talkto']).'</td><td><select name="sendto" size="1">';
	echo '<option ';
	if($_REQUEST['sendto']==='s 17'){
		echo 'selected ';
	}
	
	echo "value=\"s 17\">- All Chatters -</option>";
	
	//Modification added an option to send to all rooms.
	if($U['status']>=5){
		echo '<option ';
		if($_REQUEST['sendto']==='r @'){
			echo 'selected ';
		}
		echo "value=\"r @\">- All Rooms -</option>";
	}

	//MODIFICATION 7 lines added for the RG channel (option to write to registered guests)
	if($U['status']>=2){
		echo '<option ';
		if($_REQUEST['sendto']==='s 24'){
			echo 'selected ';
		}
		echo "value=\"s 24\">- Junior Members -</option>";
	}
	
	if($U['status']>=3){
		echo '<option ';
		if($_REQUEST['sendto']==='s 31'){
			echo 'selected ';
		}
		echo "value=\"s 31\">- Senior Members -</option>";
	}
	if($U['status']>=5){
		echo '<option ';
		if($_REQUEST['sendto']==='s 48'){
			echo 'selected ';
		}
		echo "value=\"s 48\">- Staff -</option>";
	}
	//MODICATION description of item in dropdown menu changed to SMods only
	if($U['status']>=6){
		echo '<option ';
		if($_REQUEST['sendto']==='s 56'){
			echo 'selected ';
		}
		echo "value=\"s 56\">- Admins -</option>";
	}

	//MODIFICATION 7 lines added for the new admin channel (option to admins only)
	if($U['status']>=7){
		echo '<option ';
		if($_REQUEST['sendto']==='s 65'){
			echo 'selected ';
		}
		echo "value=\"s 65\">- Gods -</option>";
	}
	$disablepm=(bool) get_setting('disablepm');
	if(!$disablepm){
		$users=[];
		$stmt=$db->prepare('SELECT * FROM (SELECT nickname, style, 0 AS offline FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 UNION SELECT nickname, style, 1 AS offline FROM ' . PREFIX . 'members WHERE eninbox!=0 AND eninbox<=? AND nickname NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions WHERE incognito=0)) AS t WHERE nickname NOT IN (SELECT ign FROM '. PREFIX . 'ignored WHERE ignby=? UNION SELECT ignby FROM '. PREFIX . 'ignored WHERE ign=?) ORDER BY LOWER(nickname);');
		$stmt->execute([$U['status'], $U['nickname'], $U['nickname']]);
		while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			if($tmp['offline']){
				$users[]=["$tmp[nickname] $I[offline]", $tmp['style'], $tmp['nickname']];
			}else{
				$users[]=[$tmp['nickname'], $tmp['style'], $tmp['nickname']];
			}
		}
		foreach($users as $user){
			if($U['nickname']!==$user[2]){
				echo '<option ';
				if($_REQUEST['sendto']==$user[2]){
					echo 'selected ';
				}
				echo 'value="'.htmlspecialchars($user[2])."\" style=\"$user[1]\">".htmlspecialchars($user[0]).'</option>';
			}
		}
	}
	echo '</select></td>';
	if(get_setting('enfileupload')>0 && get_setting('enfileupload')<=$U['status']){
		if(!$disablepm && ($U['status']>=5 || ($U['status']>=3 && get_count_mods()==0 && get_setting('memkick')))){
			echo '</tr></table><table><tr id="secondline">';
		}
		printf("<td><input type=\"file\" name=\"file\"><small>$I[maxsize]</small></td>", get_setting('maxuploadsize'));
	}
	
	//Modification to enable kick function, if memdel hast value 2
	if(!$disablepm && ($U['status']>=5 || ($U['status']>=3 && get_count_mods()==0 && get_setting('memkick')) || ($U['status']>=3  && (int)get_setting('memdel')===2))){
		echo "<td><label><input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"kick\">$I[kick]</label></td>";
		echo "<td><label><input type=\"checkbox\" name=\"what\" id=\"what\" value=\"purge\" checked>$I[alsopurge]</label></td>";
	}
	echo '</tr></table></td></tr></table></form></td></tr><tr><td><table><tr id="thirdline"><td>'.form('delete');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo hidden('sendto', $_REQUEST['sendto']).hidden('what', 'last');
	echo submit($I['dellast'], 'class="delbutton"').'</form></td><td>'.form('delete');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo hidden('sendto', $_REQUEST['sendto']).hidden('what', 'all');
	echo submit($I['delall'], 'class="delbutton"').'</form></td><td style="width:10px;"></td><td>'.form('post');
	if(isset($_REQUEST['multi'])){
		echo submit($I['switchsingle']);
	}else{
		echo hidden('multi', 'on').submit($I['switchmulti']);
	}
	echo hidden('sendto', $_REQUEST['sendto']).'</form></td>';
	echo '</tr></table></td></tr></table>';
	
	//External Links section start
		//div left for links section
	echo "<div align='left'>";
	//one line added (emoji-link with id for css)
	    echo "<a id='emoji_link' target='view' rel='noopener noreferrer' href='emojis.html'>Emojis</a>";
    	echo "&nbsp";
	
	
	//modification forum button 
	if($U['status']>= (int)get_setting('forumbtnaccess')){
         echo "<a id='forum_link' target='_blank' href='".get_setting('forumbtnlink')."'>Forum</a>";
	}
	
	//echo "</div>";
	//External Links section end	
	
	
	print_end();
}

function send_greeting(){
	global $I, $U, $language;
	print_start('greeting', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language");
	printf("<h1>$I[greetingmsg]</h1>", style_this(htmlspecialchars($U['nickname']), $U['style']));
	printf("<hr><small>$I[entryhelp]</small>", $U['refresh']);
	$rulestxt=get_setting('rulestxt');
	if(!empty($rulestxt)){
		echo "<hr><div id=\"rules\"><h2>$I[rules]</h2>$rulestxt</div>";
	}
	print_end();
}

function send_help(){
	global $I, $U;
	print_start('help');
	$rulestxt=get_setting('rulestxt');
	if(!empty($rulestxt)){
		echo "<div id=\"rules\"><h2>$I[rules]</h2>$rulestxt<br></div><hr>";
	}
	echo "<h2>$I[help]</h2>$I[helpguest]";
	if(get_setting('imgembed')){
		echo "<br>$I[helpembed]";
	}
	if($U['status']>=3){
		echo "<br>$I[helpmem]<br>";
		if($U['status']>=5){
			echo "<br>$I[helpmod]<br>";
			if($U['status']>=7){
				echo "<br>$I[helpadm]<br>";
			}
		}
	}
	// MODIFICATION removed script version.
	echo '<br><hr><div id="backcredit">'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>'/*.credit()*/.'</div>';
	print_end();
}

function send_profile($arg=''){
	global $I, $L, $U, $db, $language;
	print_start('profile');
	echo form('profile', 'save')."<h2>$I[profile]</h2><i>$arg</i><table>";
	thr();
	$ignored=[];
	$stmt=$db->prepare('SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=? ORDER BY LOWER(ign);');
	$stmt->execute([$U['nickname']]);
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$ignored[]=htmlspecialchars($tmp['ign']);
	}
	if(count($ignored)>0){
		echo "<tr><td><table id=\"unignore\"><tr><th>$I[unignore]</th><td>";
		echo "<select name=\"unignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($ignored as $ign){
			echo "<option value=\"$ign\">$ign</option>";
		}
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table id=\"ignore\"><tr><th>$I[ignore]</th><td>";
	echo "<select name=\"ignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
	$stmt=$db->prepare('SELECT poster, style FROM ' . PREFIX . 'messages INNER JOIN (SELECT nickname, style FROM ' . PREFIX . 'sessions UNION SELECT nickname, style FROM ' . PREFIX . 'members) AS t ON (' .  PREFIX . 'messages.poster=t.nickname) WHERE poster!=? AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) GROUP BY poster ORDER BY LOWER(poster);');
	$stmt->execute([$U['nickname'], $U['nickname']]);
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo '<option value="'.htmlspecialchars($nick[0])."\" style=\"$nick[1]\">".htmlspecialchars($nick[0]).'</option>';
	}
	echo '</select></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"refresh\"><tr><th>$I[refreshrate]</th><td>";
	echo "<input type=\"number\" name=\"refresh\" size=\"3\" maxlength=\"3\" min=\"5\" max=\"150\" value=\"$U[refresh]\"></td></tr></table></td></tr>";
	thr();
	preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
	echo "<tr><td><table id=\"colour\"><tr><th>$I[fontcolour] (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]&amp;lang=$language\" target=\"view\">$I[viewexample]</a>)</th><td>";
	echo "<input type=\"color\" value=\"#$matches[1]\" name=\"colour\"></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table id=\"bgcolour\"><tr><th>$I[bgcolour] (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]&amp;lang=$language\" target=\"view\">$I[viewexample]</a>)</th><td>";
	echo "<input type=\"color\" value=\"#$U[bgcolour]\" name=\"bgcolour\"></td></tr></table></td></tr>";
	thr();
	if($U['status']>=3){
		echo "<tr><td><table id=\"font\"><tr><th>$I[fontface]</th><td><table>";
		echo "<tr><td>&nbsp;</td><td><select name=\"font\" size=\"1\"><option value=\"\">* $I[roomdefault] *</option>";
		$F=load_fonts();
		foreach($F as $name=>$font){
			echo "<option style=\"$font\" ";
			if(strpos($U['style'], $font)!==false){
				echo 'selected ';
			}
			echo "value=\"$name\">$name</option>";
		}
		echo '</select></td><td>&nbsp;</td><td><label><input type="checkbox" name="bold" id="bold" value="on"';
		if(strpos($U['style'], 'font-weight:bold;')!==false){
			echo ' checked';
		}
		echo "><b>$I[bold]</b></label></td><td>&nbsp;</td><td><label><input type=\"checkbox\" name=\"italic\" id=\"italic\" value=\"on\"";
		if(strpos($U['style'], 'font-style:italic;')!==false){
			echo ' checked';
		}
		echo "><i>$I[italic]</i></label></td><td>&nbsp;</td><td><label><input type=\"checkbox\" name=\"small\" id=\"small\" value=\"on\"";
		if(strpos($U['style'], 'font-size:smaller;')!==false){
			echo ' checked';
		}
		echo "><small>$I[small]</small></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo '<tr><td>'.style_this(htmlspecialchars($U['nickname'])." : $I[fontexample]", $U['style']).'</td></tr>';
	thr();
	$bool_settings=['timestamps', 'nocache', 'sortupdown', 'hidechatters'];
	if(get_setting('imgembed')){
		$bool_settings[]='embed';
	}
	if($U['status']>=5 && get_setting('incognito')){
		$bool_settings[]='incognito';
	}
	foreach($bool_settings as $setting){
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<label><input type=\"checkbox\" name=\"$setting\" value=\"on\"";
		if($U[$setting]){
			echo ' checked';
		}
		echo "><b>$I[enabled]</b></label></td></tr></table></td></tr>";
		thr();
	}
	if($U['status']>=2 && get_setting('eninbox')){
		echo "<tr><td><table id=\"eninbox\"><tr><th>$I[eninbox]</th><td>";
		echo "<select name=\"eninbox\" id=\"eninbox\">";
		echo '<option value="0"';
		if($U['eninbox']==0){
			echo ' selected';
		}
		echo ">$I[disabled]</option>";
		echo '<option value="1"';
		if($U['eninbox']==1){
			echo ' selected';
		}
		echo ">$I[eninall]</option>";
		echo '<option value="3"';
		if($U['eninbox']==3){
			echo ' selected';
		}
		echo ">$I[eninmem]</option>";
		echo '<option value="5"';
		if($U['eninbox']==5){
			echo ' selected';
		}
		echo ">$I[eninstaff]</option>";
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table id=\"tz\"><tr><th>$I[tz]</th><td>";
	echo "<select name=\"tz\">";
	$tzs=timezone_identifiers_list();
	foreach($tzs as $tz){
		echo "<option value=\"$tz\"";
		if($U['tz']==$tz){
			echo ' selected';
		}
		echo ">$tz</option>";
	}
	echo '</select></td></tr></table></td></tr>';
	
	//MODIFICATION nicklinks setting (setting for clickable nicknames in the message frame
	//REMOVE LATER (Remove 18 LINES (Modification no longer needed)
	/*
    thr();
	echo "<tr><td><table id=\"clickablenicknames\"><tr><th>Clickable nicknames</th><td>";
	echo "<select name=\"clickablenicknames\">";
	

	$options = array(0, 1, 2);
	foreach($options as $option){
		echo "<option value=\"$option\"";
		
		if($U['clickablenicknames']==$option){
			echo ' selected';
		}
		
		if ($option == 0) echo ">Disabled</option>";
		elseif($option == 1) echo ">Select nickname from dropdown menu</option>";
		elseif($option == 2) echo ">Copy nickname to post box</option>";
	}
	echo '</select></td></tr></table></td></tr>';	
	*/
	
	thr();
	if($U['status']>=2){
		echo "<tr><td><table id=\"changepass\"><tr><th>$I[changepass]</th></tr>";
		echo '<tr><td><table>';
		echo "<tr><td>&nbsp;</td><td>$I[oldpass]</td><td><input type=\"password\" name=\"oldpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[newpass]</td><td><input type=\"password\" name=\"newpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[confirmpass]</td><td><input type=\"password\" name=\"confirmpass\" size=\"20\"></td></tr>";
		echo '</table></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table id=\"changenick\"><tr><th>$I[changenick]</th><td><table>";
		echo "<tr><td>&nbsp;</td><td>$I[newnickname]</td><td><input type=\"text\" name=\"newnickname\" size=\"20\">";
		echo '</table></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td>'.submit($I['savechanges']).'</td></tr></table></form>';
	if($U['status']>1 && $U['status']<8){
		echo '<br>'.form('profile', 'delete').submit($I['deleteacc'], 'class="delbutton"').'</form>';
	}
	echo "<br><p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang&amp;session=$U[session]&amp;action=controls\" target=\"controls\">$name</a>";
	}
	echo '</p><br>'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>';
	print_end();
}

function send_controls(){
	global $I, $U;
	print_start('controls');
	$personalnotes=(bool) get_setting('personalnotes');
	echo '<table><tr>';
	echo '<td>'.form_target('post', 'post').submit($I['reloadpb']).'</form></td>';
	echo '<td>'.form_target('view', 'view').submit($I['reloadmsgs']).'</form></td>';
	echo '<td>'.form_target('view', 'profile').submit($I['chgprofile']).'</form></td>';
	//MODIFICATION Links Page
	if(get_setting('linksenabled')==='1'){
        echo '<td>'.form_target('view', 'links').submit('Links').'</form></td>';
	}
	
	//Forum Button was moved to the postbox (function send_post) 
	/*
	if($U['status']>= (int)get_setting('forumbtnaccess')){
         echo '<td>'.form_target('_blank', 'forum').submit('Forum').'</form></td>';
	}
	//ToDo handle forum request in function validate_input (redirect to forum page)
	*/
	
	//MODIFICATION for feature gallery
	if($U['status']>= (int)get_setting('galleryaccess')){
         echo '<td>'.form_target('view', 'gallery').submit('Gallery').'</form></td>';
	}

	if($U['status']>= 5){
		echo '<td>'.form_target('view', 'view').hidden('modroom','1').submit('Mod Rooms').'</form></td>';
	}
	
	
	if($U['status']>=5){
		echo '<td>'.form_target('view', 'admin').submit($I['adminbtn']).'</form></td>';
		//MODIFICATION for feature gallery. one line changed.
		//echo '<td>'.form_target('_blank', 'gallery').submit('Gallery').'</form></td>';
		if(!$personalnotes){
			echo '<td>'.form_target('view', 'notes', 'staff').submit($I['notes']).'</form></td>';
		}
	}
	// Modification spare notes
	$sparenotesaccess = (int) get_setting('sparenotesaccess');
	if($U['status']>=3){
		if($personalnotes){
			echo '<td>'.form_target('view', 'notes').submit($I['notes']).'</form></td>';
		}elseif ($U['status']>=$sparenotesaccess && $U['status']===3) {
			echo '<td>'.form_target('view', 'notes', 'spare').submit($I['notes']).'</form></td>';
		}
		echo '<td>'.form_target('_blank', 'login').submit($I['clone']).'</form></td>';
	}
	if(!isset($_REQUEST['sort'])){
		$sort=0;
	}else{
		$sort=$_REQUEST['sort'];
	}
	echo '<td>'.form_target('_parent', 'login').hidden('sort', $sort).submit($I['sortframe']).'</form></td>';
	echo '<td>'.form_target('view', 'help').submit($I['randh']).'</form></td>';
	echo '<td>'.form_target('_parent', 'logout').submit($I['exit'], 'id="exitbutton"').'</form></td>';
	echo '</tr></table>';
	print_end();
}

function send_download(){
	global $I, $db;
	if(isset($_REQUEST['id'])){
		$stmt=$db->prepare('SELECT filename, type, data FROM ' . PREFIX . 'files WHERE hash=?;');
		$stmt->execute([$_REQUEST['id']]);
		if($data=$stmt->fetch(PDO::FETCH_ASSOC)){
			header("Content-Type: $data[type]");
			header("Content-Disposition: filename=\"$data[filename]\"");
			header('Pragma: no-cache');
			header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
			header('Expires: 0');
			echo base64_decode($data['data']);
		}else{
			send_error($I['filenotfound']);
		}
	}else{
		send_error($I['filenotfound']);
	}
}

function send_logout(){
	global $I, $U;
	print_start('logout');
	echo '<h1>'.sprintf($I['bye'], style_this(htmlspecialchars($U['nickname']), $U['style'])).'</h1>'.form_target('_parent', '').submit($I['backtologin'], 'class="backbutton"').'</form>';
	print_end();
}

function send_colours(){
	global $I;
	print_start('colours');
	echo "<h2>$I[colourtable]</h2><kbd><b>";
	for($red=0x00;$red<=0xFF;$red+=0x33){
		for($green=0x00;$green<=0xFF;$green+=0x33){
			for($blue=0x00;$blue<=0xFF;$blue+=0x33){
				$hcol=sprintf('%02X%02X%02X', $red, $green, $blue);
				echo "<span style=\"color:#$hcol\">$hcol</span> ";
			}
			echo '<br>';
		}
		echo '<br>';
	}
	echo '</b></kbd>'.form('profile').submit($I['backtoprofile'], ' class="backbutton"').'</form>';
	print_end();
}

function nav(){
	echo '
	<div class="navbartitle"><a href="#" style="text-decoration: none; color: #fff;">The Underground Railroad</a></div>
	<nav class="topnav">
	<ul class="topnav">
	<li><a href="#ABOUT" target="_self">About</a></li>
	<li><a href="#CNGLOG" target="_self">Changelog and News</a></li>
	<li><a href="#LINKS" target="_self">Links</a></li>
	<li><a href="https://github.com/d-a-s-h-o/chat" target="_blank">Source</a></li>
	<a class="wgbtn" href="#logincbox">Login</a><a class="wgbtn" href="https://host.sokka.io/" target="_blank">Hosting</a>
	</ul> </nav>';
}

function send_login(){
	global $I, $L;
	$ga=(int) get_setting('guestaccess');
	if($ga===4){
		send_chat_disabled();
	}
	print_start('login');
	nav();
	$englobal=(int) get_setting('englobalpass');
			//MODIFICATION frontpagetext
		//Frontpage text added
		/* $frontpagetext=get_setting('frontpagetext');
		if(!empty($frontpagetext)){
			echo "<span id=\"\">$frontpagetext</span>";
		} */
	//MODIFICATION Javascript check.
	//ToDo (Maybe later)
	
	//MODIFICATION Topic on Frontpage disabled
	//echo '<h1 id="chatname">'.get_setting('chatname').'</h1>';
	echo '<div id="logincbox" class="overlaycbx"><div class="popupcbx"><h2>Login</h2><a class="closecbx" href="#">&times;</a><div class="contentcbx">';
	echo form_target('_parent', 'login').'<table>';
	if($englobal===1 && isset($_REQUEST['globalpass'])){
		echo hidden('globalpass', $_REQUEST['globalpass']);
	}
	if($englobal!==1 || (isset($_REQUEST['globalpass']) && $_REQUEST['globalpass']==get_setting('globalpass'))){
		echo "<tr><td>$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
		echo "<tr><td>$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
		send_captcha();
		if($ga!==0){
			if(get_setting('guestreg')!=0){
				echo "<tr><td>$I[regpass]</td><td><input type=\"password\" name=\"regpass\" size=\"15\" placeholder=\"$I[optional]\"></td></tr>";
			}
			if($englobal===2){
				echo "<tr><td>$I[globalloginpass]</td><td><input type=\"password\" name=\"globalpass\" size=\"15\"></td></tr>";
			}
			echo "<tr><td colspan=\"2\">$I[choosecol]<br><select name=\"colour\"><option value=\"\">* $I[randomcol] *</option>";
			print_colours();
			echo '</select></td></tr>';
		}else{
			echo "<tr><td colspan=\"2\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2">'.submit($I['enter']).'</td></tr></table></form>';
		echo '<br>';
		get_nowchatting();
		// echo '<br><div id="topic">';
		//MODIFICATION Topic on Frontpage disabled. 1 lines "removed"
		//echo get_setting('topic');
		// echo '</div>';
		$rulestxt=get_setting('rulestxt');
		
		//MODIFICATION Rules on Frontpage disabled. 3 lines "removed"
		/*
		if(!empty($rulestxt)){
			echo "<div id=\"rules\"><h2>$I[rules]</h2><b>$rulestxt</b></div>";
		}
		*/
	}else{
		echo "<tr><td>$I[globalloginpass]</td><td><input type=\"password\" name=\"globalpass\" size=\"15\" autofocus></td></tr>";
		if($ga===0){
			echo "<tr><td colspan=\"2\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2">'.submit($I['enter']).'</td></tr></table></form>';
	}
	/*echo "<p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang\">$name</a>";
	}*/
	
	//MODIFICATION we hide our script version for security reasons and because it was modificated. 1 line replaced.
	//echo '</p>'.credit();
	//echo '</p>'; 
	echo '</table>';
	    $hostlink = 'https://host.sokka.io/';
	    $git = 'https://git.sokka.io/';
	    $uploadlink = 'https://upload.sokka.io/';
	    $danslink = 'https://chat.danwin1210.me/';
	    $class = 'clearnet';
	
	echo '</div></div></div>';
	echo "<div class=\"odiv\"><div class=\"splash\"><h2><strong>Welcome to The Underground Railroad</strong></h2><div class=\"splashcard\"><br>
	<h3><ins>Shocking News</ins>: New Updates!!!! Check the Changelog. Please. Do it .... go on!!!</h3><br>
	<strong>Welcome to The Underground Railroad - <em>The most over-compensating chat on tor</em>.</strong>
	<br>Are you looking for a fun - stress free, user friendly - totally secret awesome badass cool darkweb chat? That's such a coincidence, because that's what this is. All you have to do is press the <strong>Login</strong> button in the top right hand corner, enter your credentials, and start chatting. If you want to chat anonymously, just enter any nickname press <strong>Enter Chat</strong> straight away and get at it. We hope you have fun!
	<br><br>
	<div class=\"callout alert\" style=\"background: none; border: 2px; border-style: solid; border-color: #ffff80; border-radius: 0.5em; padding: 1em; color: white; margin-left: 10%; margin-right: 10%;\">  <p style=\"color: white; text-align: center\"><center>The <span style=\"color: #ffff80;\">Underground</span> Rules</center></p><hr><ol>  <li><span style=\"color: #f4e80c;\">Nothing gross or illegal.</span></li>  <li>Freedom of speech is welcomed, but be nice.</li>  <li>Please <span style=\"color: #f4e80c;\">be respectful</span> to other chatters</li>  <li>Please <span style=\"color: #f4e80c;\">use meaningful</span> and <span style=\"color: #f4e80c;\">non-offensive nicknames</span>. No pedo nicks.</li>  <li>Please <span style=\"color: #f4e80c;\">use English</span> in the Main Chat please.</li>  <li>Please <span style=\"color: #f4e80c;\">no advertising</span> with out staff approval .</li>  <li>No drug or gun endorsements, or endorsements of other illegal markets.</li></ol> <hr /></div>
	<br><br>
	</div><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
	<div class=\"tip\" style=\"position: fixed; bottom : 0; width: 100%\"><h4 style=\"color:white;\">~ Dasho ~</h4></div>
	</div><br><br><br>
	<div class=\"idiv\" id=\"LINKS\"><div class=\"idivs1\">&nbsp;</div>
        
        <div class=\"idivc\">
        
        <div style=\"text-align:center\"><h3><a name=\"Links\">Some Useful Links</a></h3></div>
        <br>
        <h4>Important Contacts:</h4>
        <ul style=\"list-style-type: &quot; &quot;\">
          <li><a href=\"mailto:o_o@dasho.dev\" class=\"ImContact\" title=\"o_o@dasho.dev\">@Dasho</a> - Hosting person thing ..? (F**KEN GENIUS)</li>
          <ul style=\"list-style-type: &quot; &#10150; &quot;\">";
            if(is_onion()){
                echo "<li><a href=\"http://sonarmsng5vzwqezlvtu2iiwwdn3dxkhotftikhowpfjuzg7p3ca5eid.onion/contact/Dasho\" class=\"ImContact\" title=\"Sonar\" target=\"_blank\">Sonar</a></li>";
            }
            echo "<li><a href=\"https://keybase.io/d_a_s_h_o\" class=\"ImContact\" title=\"Keybase\" target=\"_blank\">Keybase</a></li>

         </ul>
        </ul>
		<h4>Others from The Onionz Project:</h4>
		<ul style=\"list-style-type: &quot; &rarr; &quot;\">
		  <li><a target=\"_blank\" href=\"https://hub.onionz.dev/\">hub@onionz (clearnet@Onionz)</a> - Command Ops for The Onionz Project <span class=\"clearnetlink\"><strong>WWW</strong></span></li>
		  <li><a target=\"_blank\" href=\"https://app.onionz.dev/\">app@Onionz (clearnet@Onionz)</a> - Dasho's center for managing the project<span class=\"clearnetlink\"><strong>WWW</strong></span></li>
          <li><a target=\"_blank\" href=\"$hostlink\">Dashed Droplets ($class@Onionz)</a> - Free onion hosting with only some limitations<span class=\"onionlink\"><strong>TOR</strong></span></li>
          <li><a target=\"_blank\" href=\"$gitlink\">Code Curiosity ($class@Onionz)</a> - Free Anonymous Git Solution <strong>(NEW)</strong><span class=\"onionlink\"><strong>TOR</strong></span></li>
		  <li><a target=\"_blank\" href=\"$uploadlink\">Dashed Uploads ($class@Onionz)</a> - A powerful pastebin and file-hosting solution for darknet <strong>(NEW)</strong><span class=\"onionlink\"><strong>TOR</strong></span></li>
		  <li><a target=\"_blank\" href=\"https://dnk.onionz.dev/share\">Pastebin (clearnet@Onionz)</a> - The official DNK pastebin for clearnet<span class=\"clearnetlink\"><strong>WWW</strong></span></li>
		</ul>
        <h4>Other Links:</h4>
		<ul>
          <li><a target=\"_blank\" href=\"$danslink\">DansChat</a>  - A popular chatroom<span class=\"onionlink\"><strong>TOR</strong></span></li>
          <li><a target=\"_blank\" href=\"http://blkhatjxlrvc5aevqzz5t6kxldayog6jlx5h7glnu44euzongl4fh5ad.onion/\">Black Hat Chat</a> - <span class=\"line\">A popular chatroom</span> <span class=\"line\">for cybersecurity enthusiasts</span><span class=\"onionlink\"><strong>TOR</strong></span></li>
          <li><a target=\"_blank\" href=\"http://index-of.es/\">Index Of</a> - <span class=\"line\">A repository of exploits and guides</span><span class=\"clearnetlink\"><strong>WWW</strong></span></li>
          <li><a target=\"_blank\" href=\"http://mixedbody2ymkgze.onion/test/ggggggg.html\">NETWORKZ</a> - <span class=\"line\">A site analysing and</span> <span class=\"line\">explaing various forms of networking</span><span class=\"onionlink\"><strong>TOR</strong></span></li>
          <li><a target=\"_blank\" href=\"https://irc.evilcorp.ga/\">EvilCorp</a> - <span class=\"line\">A popular IRC server</span> <span class=\"line\">and webclient</span><span class=\"clearnetlink\"><strong>WWW</strong></span></li>
          <li><a target=\"_blank\" href=\"http://kx5thpx2olielkihfyo4jgjqfb7zx7wxr3sd4xzt26ochei4m6f7tayd.onion/\">Imperial Library</a> - <span class=\"line\">Free ebook library</span><span class=\"onionlink\"><strong>TOR</strong></span></li>
          <li><a target=\"_blank\" href=\"http://qubesosfasa4zl44o4tws22di6kepyzfeqv3tg4e3ztknltfxqrymdad.onion/\">Qubes OS</a> - <span class=\"line\">A reasonably</span> <span class=\"line\">secure operating system</span><span class=\"onionlink\"><strong>TOR</strong></span></li>
          <li><a target=\"_blank\" href=\"http://www.dds6qkxpwdeubwucdiaord2xgbbeyds25rbsgr73tbfpqpt4a6vjwsyd.onion/\">Whonix</a> - <span class=\"line\">Software that can anonymize</span> <span class=\"line\">everything you do online</span><span class=\"onionlink\"><strong>TOR</strong></span></li>
		  <li><a target=\"_blank\" href=\"http://galaxy3bhpzxecbywoa2j4tg43muepnhfalars4cce3fcx46qlc6t3id.onion/\">Galaxy 3</a> - A popular social network<span class=\"onionlink\"><strong>TOR</strong></span></li>
		  <li><a target=\"_blank\" href=\"http://fam2pl7s2lao2ixp3wxoprhw5mxrkcjfpckfz33vqwvbssow6wa566yd.onion/\">Postor</a> - A popular social network<span class=\"onionlink\"><strong>TOR</strong></span></li>
		  <li><a target=\"_blank\" href=\"http://dreadytofatroptsdj6io7l3xptbet6onoyno2yv7jicoxknyazubrad.onion/\">Dread</a> - A reddit tor alternative<span class=\"onionlink\"><strong>TOR</strong></span></li>
        </ul>
		</div><div class=\"idivs2\">&nbsp;</div><br>
		<br><br><br></div>
	<div class=\"idiv\" id=\"CNGLOG\">
	<div class=\"idivs1\">&nbsp;</div><div class=\"idivc\"><br>
	
	<div style=\"text-align:center\"><h3><a name=\"Links\">Changelog and News</a></h3></div>
	<br>
	<div style=\"text-align:center\">(Yes, it's literally the changelog and news. Like just that.)</div>
	<h5>Changelog</h5>
	 <p>This is a record of improvements or milestones of the Chat. It could be very detailed, but only big things will be listed.</p> <div class=\"scrollbox\"><div class=\"sbc\"><pre>
	 <code> 2021-06-17: - The Chatterbox has been updated to -&gt; The Underground Railroad, in respect to the G2 update.</code><br>
	 <code> 2021-01-06: - The Chatterbox source code has been released <a href=\"https://github.com/d-a-s-h-o/chat\" target=\"_blank\">here</a>.</code><br>
	 <code> 2021-01-01: - Happy New Year!!!!</code><br>
	 <code> 2020-12-28: - Server Updates and New Improvements.</code><br>
	 <code> 2020-11-19: - Chatterbox v2 is coming soon with a all new built in (and JS free) git solution. Also, a new version of the Dashed Hosting is now in use with bug fixes and better configuration. Existing hostees can request an upgrade either here or via <a href=\"http://sonarmsng5vzwqezlvtu2iiwwdn3dxkhotftikhowpfjuzg7p3ca5eid.onion/contact/Dasho\" target=\"_blank\">Sonar</a> (make sure to include your project codename).</code><br />
	 <code> 2020-10-27: - Chatterbox v1 is officially out!</code><br>
	 <code> 2020-10-18: - Site in progress</code></pre></div></div>
	
	<h5>News</h5>
	<div class=\"insb\"> - May 2021: The initiative of the G2 update began.</div>
	<div class=\"insb\"> - Apr 2021: Curious Hosting updated to Dashed Droplets (Dashed Hosting)</div>
	<div class=\"insb\"> - Mar 2021: Absolutly nothing interesting to share.</div>
	<div class=\"insb\"> - Feb 2021: Absolutly nothing interesting to share.</div>
	<div class=\"insb\"> - Jan 2021: <span style=\"color:#ff0000\"><strong>Curious</strong></span> has a new alias ---&gt; <span style=\"color:rgb(255,0,0);\"><strong>Dasho</strong></span>.</div>
	<div class=\"insb\"> - Dec 2020: Second major update for Curious Hosting.</div>
	<div class=\"insb\"> - Nov 2020: More Exciting News... Chat is released, and Curious Hosting has not only been released, but has also undergone it's first major update!!!</div>
	<div class=\"insb\"> - Oct 2020: Exciting News... Chat is almost ready for release, and Curious Hosting is being released also!!!</div>
	<div class=\"insb\"> - Sep 2020: Absolutly nothing interesting to share.</div>
	<div class=\"insb\"> - Aug 2020: <span style=\"color:#ff0000\"><strong>Curious</strong></span> has a new alias ---&gt; <span style=\"color:rgb(255,0,128);\">Sokka</span>.</div>
	<div class=\"insb\"> - Jul 2020: Absolutly nothing interesting to share.</div>
	<div class=\"insb\"> - Jun 2020: Absolutly nothing interesting to share.</div>
	<div class=\"insb\"> - May 2020: Absolutly nothing interesting to share.</div>
	<br><br>
	</div>
	<div class=\"idivs2\">&nbsp;</div><br><br><br></div>
	<div class=\"idiv\" id=\"ABOUT\"><div class=\"idivs1\">&nbsp;</div>
        
        <div class=\"idivc\">
        
        <div style=\"text-align:center\"><h3><a name=\"Links\">About The Chat</a></h3></div>
        <br><br>
	<div class=\"insb\">Spread the word</div>
	<br>
	If you are passionate about promoting this chat, why not put a link to the site/chat in your regular forum profile signature? Go to your signature edit box and use something like this:<br><br>
	<div class=\"scrollbox\"><div class=\"sbc\"><pre><code>[b][size=100][bgcolor=#121525][color=#166FA6][/color][color=#F7F7F7]The Underground Railroad:[/color][color=#166FA6] [/color][/bgcolor][/size] [size=100][bgcolor=#C13B5B][color=#F7F7F7]The dopest chat of darkweb.[/color][color=#C13B5B][/color][/bgcolor][/size][/b]</code></pre></div></div> <br>
	
	<div class=\"insb\">Ideas and to-do's that need your input</div>
	<br>
	If you have theme ideas for the chat - or other improvements you'd like to see implemented, just contact a member of staff. Your feedback is highly appreciated.<br><br>
        </div><div class=\"idivs2\">&nbsp;</div><br>
	</div></div>";
	print_end();
}

function send_chat_disabled(){
	print_start('disabled');
	echo get_setting('disabletext');
	print_end();
}

function send_error($err){
	global $I;
	print_start('error');
	echo "<h2>$I[error]: $err</h2>".form_target('_parent', '').submit($I['backtologin'], 'class="backbutton"').'</form>';
	print_end();
}

function send_fatal_error($err){
	global $I;
	echo '<!DOCTYPE html><html><head>'.meta_html();
	echo "<title>$I[fatalerror]</title>";
	echo "<style type=\"text/css\">body{background-color:#000000;color:#FF0033;}</style>";
	echo '</head><body>';
	echo "<h2>$I[fatalerror]: $err</h2>";
	print_end();
}

function print_notifications(){
	global $I, $U, $db;
	echo '<span id="notifications">';
	if($U['status']>=2 && $U['eninbox']!=0){
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute([$U['nickname']]);
		$tmp=$stmt->fetch(PDO::FETCH_NUM);
		if($tmp[0]>0){
			echo '<p>'.form('inbox').submit(sprintf($I['inboxmsgs'], $tmp[0])).'</form></p>';
		}
	}
	if($U['status']>=5 && get_setting('guestaccess')==3){
		$result=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1;');
		$temp=$result->fetch(PDO::FETCH_NUM);
		if($temp[0]>0){
			echo '<p>';
			echo form('admin', 'approve');
			echo submit(sprintf($I['approveguests'], $temp[0])).'</form></p>';
		}
	}
	echo '</span>';
}
function print_chatters(){
	global $I, $U, $db, $language;
	
     $icon_star_red = "<img border='0' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4wgIACgc4JxSRwAAARBJREFUKM99kL1KA1EQhb/Z6+4GJQQbDQi+Q7CPYMBGKxtrVyRsaSkEg4iCBGxMuQQ2kGewzxtYiW+gpSD5u94dq4hsLp5u5jtnhhnwaJ6mu4tOZ93H1nxNJpNjrDXAUxkF+HWi1p75wErAjkZGnGsBDdvtbpa5zNN0X6bTDQBVFTFmT527ARCRnqqORaQgCNA4nsq83d5mNnsGGvyvFyqVI1lWiyS5UufufU4Jw9soy64B5C9YJMmlOvdYMneiLLvzHq3OHZSnq7VN75e+h8MAaAGIMRcShueAAofu7TVYCRTjcRP4kmq1Hg0GWZRlA6nVdoAP99A7XQmoMXGc51tRv//xu78o3uM8r2sYfi5bP+VcXsOKMjGVAAAAAElFTkSuQmCC'/>";
    
	 $icon_star_gold = "<img border='0' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAABKElEQVQoz32QvUoDQRSFz53ZjfEnMTFgkkYQrOyCragYsdFCLAIpRXwASyEIIoog2PgQiUbRTsXON4i1D7AKmhUk2d3MzLWKhOzgKb9vhnvvASwJb2dnOo+LYzbnWGHkbUr9JQFcDjsBa3ibdLhjM7EP7deaBKsymbDUeV7LDnvq3RSXhfoZBzFgiFjIBaH8IwAwcuKcmF5AbAiAdlJdipqFvFT+A5mghH/CItnSTnqD+qB3NXUgVfsU4Nhj42aOnYp/CAA0KFQjsy+0fzHItJuruZXPE+vRxMFqrBXdWbK25LXOBHGvDBIwTnZPu5O7gGTicP0p4Hj96jq3ouuJj+79XL7Pgrv5oq4nPN1IV2MTtEyOyGo0Pbr19v63IkJPVqOCclLfffYLGXVpfXSgIhUAAAAASUVORK5CYII='/>";
	
	 $icon_star_silver = "<img border='0' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAA8ElEQVQoz42STSuEURiGr/tgw8pOTfkPk61GIQorG6uz0CRri1moSUmklF8gm/PuZI1m5x/MrPwEH8VGQym3zVHT6yw8y/ujnufqgcKklGaHt/OTJS+URNsb1y+77X8XgE3b2/8qvA26Y8AS0Bz2lqfrvlJKC8BUXkWS5mwfAkg6s30v6TvnP8aBB9s3QDOXRm/pAJ2s9SWta4TMvu2T0t6SjmKMBwCq4dyzfV4Ld2OMx8WjbS8WELeKlB77pyHTQdKOpDZgYOXu0+FPoTdotID3EMJMjPEixng58fXaAJ6er6qt0jus1rWqqpS9tV/tB1UBYQLU/vuEAAAAAElFTkSuQmCC'/>";
	
     $icon_star_bronze = "<img border='0' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAABEElEQVQoz42QL0hDYRTFf/d7s2jSYhN8w2YZVpmgsoGaDL5ZBBl2hRVhCCoMYWw2g8UijIF9yprYZQOjrymaBiLTsPddgz7Bx4fspnvPuX/OPeCIesGf6l/Pj7o44wIHka6dXTwXhx4A1q3V7aEGet2yp7AEZPrt5fEkL/WCvxBZHQNQRURkTlUPAUSoqnIrggXBM3xILfAnI6stIMP/0fGMrEpcVTf8fVWtOHUbOS41wwMALwbbD7273OzEG5BPNJdLzfDI+bSqLia3W6tZp0svnRPDtzuIyI4xUgQUyN186m9fKk4uK+dZ4H0kZab3Go+vAFe7M63waXDf3UoHQOPP6Vrg55NyTjfT8sOtxNgX5ehXBVg4i6sAAAAASUVORK5CYII='/>";
	
	 $icon_heart_red = "<img border='0' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAABs0lEQVQoz22Ry2sTURSHv3vvPNLMTBLTiiVIFSsuxFCIuBJEKi58LN3730k0BRcVBVEpXSloN1IrrYJSHyQTZ5pn03nc6yJpF+K3/J3v/BbnCIDnNqeWa9WrjuN4xlJbF3Z+7gNsX1pccjPdyI3u7+13P9zP6InXc1SvXDy/UQn8upSSLM97nU7nzmGaumdrtXVHWb7BEPf6W+8/f1+1Fqqle+XAr9u2DYBlWWW3OPfCEYHrFQoFYwwAlVLQOHcmuGVpaEgpOUYbw+nqfNkYw7EMoKREIlZkotlO9HRghABAGIMUApSCWWa0xlFqR/7pDzZ+t8NxNhojkgSpNUJMPZFmiMkEMRqQxPFwPBi+lXdH5steO3zZ7Ubo8RjybNYqwOSQpiSDEV/b4bOVOP2mAKzEtBZ1+jAwVHzHBrcASkKuSQ4GfOzGnxrR0SqABHjgCx1m+vpubxiFYYzp9WFyRBYdsBvFnR+T9OZm2REnF3hcVAA0PevyZqVgftUqJlpaMO/mPb0e2MsAT2bOCS1/+oeWZ197U3IOX5XcYdOz6wBPA4f/sjZbahbV7UdFdQNg7R/5Lza2vZnfg8j9AAAAAElFTkSuQmCC'/>";
	 
	 //Color was changed from blue to light blue
	 $icon_heart_blue = "<img border='0' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAABqUlEQVQoz2WQu2tTYRiHn/c7JyeXJpbopnAU9yJEHFSUEHUoTiIOXQTXtouLf4K46RBHVxcLHQwIIhKdXOwi6iISW/Ba06QnObmc7/teB220+My/Gz8BaNz9WK2W8yejiDkR2Xh4/fAWwNKDrdgGpmas3/2a+Ncvbh7ty8X7nYNHyrl2qWgWAq9YY/r9JFu0nvyhueBxaCgDJBPd6HRtI6wUgsulolmIAIwQoPNRaJ5EkM+HUuAPpaLUqgeCCyFQC7yCEQCcCpVCMC8o/xJ4JXT+hHEqb60xM/EeRkBE9plE5L1JJ7bdS2w6yhTUIyiB/E53XnFeGakwmPjBKNNX5tnKsQ/bY/t0J1MmVlH9O8UrTB2Mpo7toW+1luOOAfjSD64mvazTszBB9uoBmFrPTuLetZbjJQADcOd27MehOTsY2O7uwJJ6yJwynCrd1H9Plfpi85PMDKk3tFfjzyPkXG8MvcTyc+j4NnI6VDnzfCX+cePWcQWY3fConOPaIKNxr3MqDMxL47wbh+Z0ezV+s16JuJJM+Y+1cg6AenPzUr25eR5grRLt0/wCkH7H2hCmE9kAAAAASUVORK5CYII='/>";
	 
	
	if(!$U['hidechatters']){
		echo '<div id="chatters">';
		if($U['status']>=5){
            $stmt=$db->prepare('SELECT nickname, style, status, roomid FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 AND nickname NOT IN (SELECT ign FROM '. PREFIX . 'ignored WHERE ignby=? UNION SELECT ignby FROM '. PREFIX . 'ignored WHERE ign=?) ORDER BY status DESC, lastpost DESC;');
        }else{
		    $stmt=$db->prepare('SELECT nickname, style, status, roomid FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 AND nickname NOT IN (SELECT ign FROM '. PREFIX . 'ignored WHERE ignby=? UNION SELECT ignby FROM '. PREFIX . 'ignored WHERE ign=?) ORDER BY lastpost DESC;');
		}
		$stmt->execute([$U['nickname'], $U['nickname']]);
		$nc=substr(time(), -6);
        $G=$RG=$M=$S=[];		
            while($user=$stmt->fetch(PDO::FETCH_BOTH)){
                //MODIFICATION chat rooms
            	$roomclass='notinroom';
            	if($U['roomid']===$user['roomid']){
            		$roomclass='inroom';
            	}
            	$stmt1=$db->prepare('SELECT name FROM ' . PREFIX . 'rooms WHERE id=? AND access<=? ;');
				$stmt1->execute([$user['roomid'], $U['status']]);
				if($room=$stmt1->fetch(PDO::FETCH_NUM)){
					$roomname=$room[0];
				}else{
					$roomname=" ";
					if($user['roomid']===null){
						$roomname="[Main Chat]";
					}
				}
				$roomprefix = "<span class=\"$roomclass\" title=\"$roomname\">";
				$roompostfix = '</span>';

               $link="<a style=\"text-decoration: none;\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=".htmlspecialchars($user[0]).'" target="post">'.style_this(htmlspecialchars($user[0]), $user[1]).'</a>';
            
                //staff can see the different rank icons of the staff people
                if($U['status']>=5){    //if logged in user is moderator or higher            
                    
                    if($user[2]>=8){
                        $link = "<nobr>".rank_this($user[2]).$roomprefix.$link.$roompostfix."</nobr>"; //adds red star icon in front of the nick.
                        $S[]=$link;
                    }
                    
                    elseif($user[2]==7){
                        $link = "<nobr>".rank_this($user[2]).$roomprefix.$link.$roompostfix."</nobr>"; //adds gold star icon in front of the nick.
                        $S[]=$link;
                        
                    }elseif($user[2]==6){
                        $link = "<nobr>".rank_this($user[2]).$roomprefix.$link.$roompostfix."</nobr>"; //adds silver star icon in front of the nick.
                        $S[]=$link;
                    
                    }elseif($user[2]==5){
                        $link = "<nobr>".rank_this($user[2]).$roomprefix.$link.$roompostfix."</nobr>"; //adds bronze star icon in front of the nick.
                        $S[]=$link;
                        
                    }elseif($user[2]==3){
                        $link = "<nobr>".rank_this($user[2]).$roomprefix.$link.$roompostfix."</nobr>"; //adds "heart icon red" in front of the nick.
                        $M[]=$link;
                    
                    }elseif($user[2]==2){
                        $link = "<nobr>".rank_this($user[2]).$roomprefix.$link.$roompostfix."</nobr>"; //adds "heart icon pink" in front of the nick.
                        $RG[]=$link;
                        
                    }else{
                        $G[]=$roomprefix.$link.$roompostfix;
                    }
                
                //guests and members can't see the different rank icons of the staff
                }else{
                    if($user[2]>=5){
                        $link = "<nobr>".rank_this('5').$roomprefix.$link.$roompostfix."</nobr>"; //adds star icon in front of the nick. No break tags (deprecated) to prevent line break between icon and nickname.
                        $M[]=$link;
                        
                    }elseif($user[2]==3){
                        $link = "<nobr>".rank_this('3').$roomprefix.$link.$roompostfix."</nobr>"; //adds "heart icon red" in front of the nick.
                        $M[]=$link;
                        
                    }elseif($user[2]==2){
                        $link = "<nobr>".rank_this('2').$roomprefix.$link.$roompostfix."</nobr>"; //adds "heart icon" pink in front of the nick.
                        $RG[]=$link;
                        
                    }else{
                        $G[]=$roomprefix.$link.$roompostfix;
                    }
                }//end if
                    
            }//end while
            if($U['status']>=5){
                $chanlinks = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 48\" target=\"post\">$I[staff]</a>";
                $chanlinkm = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 31\" target=\"post\">$I[members2]</a>";
                $chanlinkrg = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 24\" target=\"post\">$I[regguests]</a>";
                $chanlinkg = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 17\" target=\"post\">$I[guests]</a>";
            }elseif($U['status']==3){
                $chanlinks = "$I[staff]";
                $chanlinkm = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 31\"  target=\"post\">$I[members2]</a>";
                $chanlinkrg = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 24\" target=\"post\">$I[regguests]</a>";
                $chanlinkg = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 17\" target=\"post\">$I[guests]</a>";
            }elseif($U['status']==2){
                $chanlinks = "$I[staff]";
                $chanlinkm = "$I[members2]";
                $chanlinkrg = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 24\" target=\"post\">$I[regguests]</a>";
                $chanlinkg = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 17\" target=\"post\">$I[guests]</a>";
            }else{
                $chanlinks = "$I[staff]";
                $chanlinkm = "$I[members2]";
                $chanlinkrg = "$I[regguests]";
                $chanlinkg = "<a style=\"color:#fff; text-decoration: none\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=s 17\" target=\"post\">$I[guests]</a>";
            }
		if(!empty($S)){
			echo "<span class='group'>".$chanlinks." (".count($S).")</span><div>".implode('</span><br><span>', $S).'</div>';
			if(!empty($M) || !empty($R) || !empty($G)){
				echo '<div>&nbsp;&nbsp;</div>';
			}
		}
		if(!empty($M)){
			echo "<span class='group'>".$chanlinkm." (".count($M).")</span><div>".implode('</span><br><span>', $M).'</div>';
			if(!empty($R) || !empty($G)){
				echo '<div>&nbsp;&nbsp;</div>';
			}
		}
		if(!empty($RG)){
			echo "<span class='group'>".$chanlinkrg." (".count($RG).")</span><div>".implode('</span><br><span>', $RG).'</div>';
			if(!empty($G)){
				echo '<div>&nbsp;&nbsp;</div>';
			}
		}
		if(!empty($G)){
			echo "<span class='group'>".$chanlinkg." (".count($G).")</span><div>".implode('</span><br><span>', $G).'</div>';
		}
		echo '</div>';
	}//end if
}//end function print_chatters

//  session management

function create_session($setup, $nickname, $password){
	global $I, $U;
	$U['nickname']=preg_replace('/\s/', '', $nickname);
	if(check_member($password)){
		if($setup && $U['status']>=7){
			$U['incognito']=1;
		}
		$U['entry']=$U['lastpost']=time();
	}else{
		add_user_defaults($password);
		check_captcha(isset($_REQUEST['challenge']) ? $_REQUEST['challenge'] : '', isset($_REQUEST['captcha']) ? $_REQUEST['captcha'] : '');
		$ga=(int) get_setting('guestaccess');
		if(!valid_nick($U['nickname'])){
			send_error(sprintf($I['invalnick'], get_setting('maxname'), get_setting('nickregex')));
		}
		if(!valid_pass($password)){
			send_error(sprintf($I['invalpass'], get_setting('minpass'), get_setting('passregex')));
		}
		if($ga===0){
			send_error($I['noguests']);
		}elseif($ga===3){
			$U['entry']=0;
		}
		if(get_setting('englobalpass')!=0 && isset($_REQUEST['globalpass']) && $_REQUEST['globalpass']!=get_setting('globalpass')){
			send_error($I['wrongglobalpass']);
		}
	}
	write_new_session($password);
}

function rank_this($status){

/*
1 .rank.g { background-image: url('green-1.png'); }
2 .rank.ra { background-image: url('green-2.png'); }
3 .rank.m { background-image: url('blue-1.png'); }
5 .rank.mod { background-image: url('red-1.png'); }
6 .rank.sm { background-image: url('red-2.png'); }
7 .rank.a { background-image: url('red-3.png'); }
8 .rank.sa { background-image: url('yellow-1.png'); }
*/

	$rank="";

	switch ($status) {
		case 1:
			$rank="g";
			break;
		case 2:
			$rank="ra";
			break;
		case 3:
			$rank="m";
			break;
		case 5:
			$rank="mod";
			break;
		case 6:
			$rank="sm";
			break;
		case 7:
			$rank="a";
			break;
		case 8:
			$rank="sa";
			break;
		case 9:
			$rank="sa";
			break;
		default:
			$rank="";
	}

	if(strlen($rank)){
		return sprintf("<span class=\"rank %s\"></span><bdi class=\"spacer\"></bdi>", $rank);
    }
	return '';
}

function check_captcha($challenge, $captcha_code){
	global $I, $db, $memcached;
	$captcha=(int) get_setting('captcha');
	if($captcha!==0){
		if(empty($challenge)){
			send_error($I['wrongcaptcha']);
		}
		if(MEMCACHED){
			if(!$code=$memcached->get(DBNAME . '-' . PREFIX . "captcha-$_REQUEST[challenge]")){
				send_error($I['captchaexpire']);
			}
			$memcached->delete(DBNAME . '-' . PREFIX . "captcha-$_REQUEST[challenge]");
		}else{
			$stmt=$db->prepare('SELECT code FROM ' . PREFIX . 'captcha WHERE id=?;');
			$stmt->execute([$challenge]);
			$stmt->bindColumn(1, $code);
			if(!$stmt->fetch(PDO::FETCH_BOUND)){
				send_error($I['captchaexpire']);
			}
			$time=time();
			$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'captcha WHERE id=? OR time<(?-(SELECT value FROM ' . PREFIX . "settings WHERE setting='captchatime'));");
			$stmt->execute([$challenge, $time]);
		}
		if($captcha_code!==$code){
			if($captcha!==3 || strrev($captcha_code)!==$code){
				send_error($I['wrongcaptcha']);
			}
		}
	}
}

function is_definitely_ssl() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
        return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && ('https' == $_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return true;
    }
    return false;
}

function set_secure_cookie($name, $value){
	if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
		setcookie($name, $value, ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => is_definitely_ssl(), 'httponly'=>true, 'samesite' => 'Strict']);
	}else{
		setcookie($name, $value, 0, '/', '', is_definitely_ssl(), true);
	}
}

function write_new_session($password){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		// check whether alrady logged in
		if(password_verify($password, $temp['passhash'])){
			$U=$temp;
			check_kicked();
			set_secure_cookie(COOKIENAME, $U['session']);
		}else{
			send_error("$I[userloggedin]<br>$I[wrongpass]");
		}
	}else{
		// create new session
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'sessions WHERE session=?;');
		do{
			if(function_exists('random_bytes')){
				$U['session']=bin2hex(random_bytes(16));
			}else{
				$U['session']=md5(uniqid($U['nickname'], true).mt_rand());
			}
			$stmt->execute([$U['session']]);
		}while($stmt->fetch(PDO::FETCH_NUM)); // check for hash collision
		if(isset($_SERVER['HTTP_USER_AGENT'])){
			$useragent=htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
		}else{
			$useragent='';
		}
		if(get_setting('trackip')){
			$ip=$_SERVER['REMOTE_ADDR'];
		}else{
			$ip='';
		}
        $stmt=$db->prepare('INSERT INTO ' . PREFIX . 'sessions (session, nickname, status, refresh, style, lastpost, passhash, useragent, bgcolour, entry, timestamps, embed, incognito, ip, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'sessions (session, nickname, status, refresh, style, lastpost, passhash, useragent, bgcolour, entry, timestamps, embed, incognito, ip, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$U['session'], $U['nickname'], $U['status'], $U['refresh'], $U['style'], $U['lastpost'], $U['passhash'], $useragent, $U['bgcolour'], $U['entry'], $U['timestamps'], $U['embed'], $U['incognito'], $ip, $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['nocache_old']]);
		set_secure_cookie(COOKIENAME, $U['session']);
		
		//MDIFICATION for clickable nicknames setting. (clickablenicknames value added)
		/* REMVOE LATER
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'sessions (session, nickname, status, refresh, style, lastpost, passhash, useragent, bgcolour, entry, timestamps, embed, incognito, ip, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old, clickablenicknames) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$U['session'], $U['nickname'], $U['status'], $U['refresh'], $U['style'], $U['lastpost'], $U['passhash'], $useragent, $U['bgcolour'], $U['entry'], $U['timestamps'], $U['embed'], $U['incognito'], $ip, $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['nocache_old'],$U['clickablenicknames'] ]);
		setcookie(COOKIENAME, $U['session']);
		*/
		
		//MODIFICATION adminjoinleavemsg setting for join/leave message for admins
		if(($U['status']>=3 && $U['status']<=6 && !$U['incognito']) || ($U['status']>=7 && !$U['incognito'] && (bool) get_setting('adminjoinleavemsg'))){
			add_system_message(sprintf(get_setting('msgenter'), style_this_clickable(htmlspecialchars($U['nickname']), $U['style'])));
		}
	}
}

function approve_session(){
	global $db;
	if(isset($_REQUEST['what'])){
		if($_REQUEST['what']==='allowchecked' && isset($_REQUEST['csid'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute([$nick]);
			}
		}elseif($_REQUEST['what']==='allowall' && isset($_REQUEST['alls'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute([$nick]);
			}
		}elseif($_REQUEST['what']==='denychecked' && isset($_REQUEST['csid'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute([$time, $_REQUEST['kickmessage'], $nick]);
			}
		}elseif($_REQUEST['what']==='denyall' && isset($_REQUEST['alls'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute([$time, $_REQUEST['kickmessage'], $nick]);
			}
		}
	}
}

function check_login(){
	global $I, $U, $db;
	$ga=(int) get_setting('guestaccess');
	if(isset($_REQUEST['session'])){
		parse_sessions();
	}
	if(isset($U['session'])){
		check_kicked();
	}elseif(get_setting('englobalpass')==1 && (!isset($_REQUEST['globalpass']) || $_REQUEST['globalpass']!=get_setting('globalpass'))){
		send_error($I['wrongglobalpass']);
	}elseif(!isset($_REQUEST['nick']) || !isset($_REQUEST['pass'])){
		send_login();
	}else{
		if($ga===4){
			send_chat_disabled();
		}
		if(!empty($_REQUEST['regpass']) && $_REQUEST['regpass']!==$_REQUEST['pass']){
			send_error($I['noconfirm']);
		}
		create_session(false, $_REQUEST['nick'], $_REQUEST['pass']);
		if(!empty($_REQUEST['regpass'])){
			$guestreg=(int) get_setting('guestreg');
			if($guestreg===1){
				register_guest(2, $_REQUEST['nick']);
				$U['status']=2;
			}elseif($guestreg===2){
				register_guest(3, $_REQUEST['nick']);
				$U['status']=3;
			}
		}
	}
	if($U['status']==1){
		if($ga===2 || $ga===3){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=0 WHERE session=?;');
			$stmt->execute([$U['session']]);
			send_waiting_room();
		}
	}
}

function kill_session(){
	global $U, $db;
	parse_sessions();
	check_expired();
	check_kicked();
	setcookie(COOKIENAME, false);
	$_REQUEST['session']='';
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE session=?;');
	$stmt->execute([$U['session']]);
	
	//Modification adminjoinleavemsg
	if(($U['status']>=3 && $U['status']<=6 && !$U['incognito']) || ($U['status']>=7 && !$U['incognito'] && (bool) get_setting('adminjoinleavemsg'))){
        //MODIFICATION for clickablenicknames stlye_this_clickable instead of style_this
		add_system_message(sprintf(get_setting('msgexit'), style_this_clickable(htmlspecialchars($U['nickname']), $U['style'])));
	}
}

function kick_chatter($names, $mes, $purge){
	global $U, $db;
	$lonick='';
	$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
	$check=$db->prepare('SELECT style, entry FROM ' . PREFIX . 'sessions WHERE nickname=? AND status!=0 AND (status<? OR nickname=?);');
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=?;');
	$all=false;
	if($names[0]==='s &'){
		$tmp=$db->query('SELECT nickname FROM ' . PREFIX . 'sessions WHERE status=1;');
		$names=[];
		while($name=$tmp->fetch(PDO::FETCH_NUM)){
			$names[]=$name[0];
		}
		$all=true;
	}
	$i=0;
	foreach($names as $name){
		$check->execute([$name, $U['status'], $U['nickname']]);
		if($temp=$check->fetch(PDO::FETCH_ASSOC)){
			$stmt->execute([$time, $mes, $name]);
			if($purge){
				del_all_messages($name, $temp['entry']);
			}
			//MODIFICATION style_thins replaced with style_this_clickable
			$lonick.=style_this_clickable(htmlspecialchars($name), $temp['style']).', ';
			++$i;
		}
	}
	if($i>0){
		if($all){
			add_system_message(get_setting('msgallkick'));
		}else{
			$lonick=substr($lonick, 0, -2);
			if($i>1){
				add_system_message(sprintf(get_setting('msgmultikick'), $lonick));
			}else{
				add_system_message(sprintf(get_setting('msgkick'), $lonick));
			}
		}
		return true;
	}
	return false;
}

function logout_chatter($names){
	global $U, $db;
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE nickname=? AND status<?;');
	if($names[0]==='s &'){
		$tmp=$db->query('SELECT nickname FROM ' . PREFIX . 'sessions WHERE status=1;');
		$names=[];
		while($name=$tmp->fetch(PDO::FETCH_NUM)){
			$names[]=$name[0];
		}
	}
	foreach($names as $name){
		$stmt->execute([$name, $U['status']]);
	}
}

function check_session(){
	global $U;
	parse_sessions();
	check_expired();
	check_kicked();
	if($U['entry']==0){
		send_waiting_room();
	}
}

function check_expired(){
	global $I, $U;
	if(!isset($U['session'])){
		setcookie(COOKIENAME, false);
		$_REQUEST['session']='';
		send_error($I['expire']);
	}
}

function get_count_mods(){
	global $db;
	$c=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE status>=5')->fetch(PDO::FETCH_NUM);
	return $c[0];
}

function check_kicked(){
	global $I, $U;
	if($U['status']==0){
		setcookie(COOKIENAME, false);
		$_REQUEST['session']='';
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
}

function get_nowchatting(){
	global $I, $db;
	parse_sessions();
	$stmt=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0;');
	$count=$stmt->fetch(PDO::FETCH_NUM);
	echo '<div id="chatters">'.sprintf($I['curchat'], $count[0]).'<br>';
	if(!get_setting('hidechatters')){
		
		//MODIFICATION hidden ranks on frontpage. Some lines changed and some lines added.
		$stmt=$db->query('SELECT nickname, style FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>=3 AND incognito=0 ORDER BY lastpost DESC;');
		while($user=$stmt->fetch(PDO::FETCH_NUM)){
			echo style_this(htmlspecialchars($user[0]), $user[1]).' &nbsp; ';
		}
		
		$stmt=$db->query('SELECT nickname, style FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND status<3 AND incognito=0 ORDER BY status DESC, lastpost DESC;');
		while($user=$stmt->fetch(PDO::FETCH_NUM)){
			echo style_this(htmlspecialchars($user[0]), $user[1]).' &nbsp; ';
		}
		
	}
	
	echo '</div>';
}

function parse_sessions(){
	global $U, $db;
	// look for our session
	if(isset($_REQUEST['session'])){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE session=?;');
		$stmt->execute([$_REQUEST['session']]);
		if($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$U=$tmp;
		}
	}
	set_default_tz();
}

//  member handling

function check_member($password){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if(get_setting('dismemcaptcha')==0){
			check_captcha(isset($_REQUEST['challenge']) ? $_REQUEST['challenge'] : '', isset($_REQUEST['captcha']) ? $_REQUEST['captcha'] : '');
		}
		if($temp['passhash']===md5(sha1(md5($U['nickname'].$password)))){
			// old hashing method, update on the fly
			$temp['passhash']=password_hash($password, PASSWORD_DEFAULT);
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
			$stmt->execute([$temp['passhash'], $U['nickname']]);
		}
		if(password_verify($password, $temp['passhash'])){
			$U=$temp;
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET lastlogin=? WHERE nickname=?;');
			$stmt->execute([time(), $U['nickname']]);
			return true;
		}else{
			send_error("$I[regednick]<br>$I[wrongpass]");
		}
	}
	return false;
}

function delete_account(){
	global $U, $db;
	if($U['status']<8){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1, incognito=0 WHERE nickname=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE type=2 AND editedby=?;');
		$stmt->execute([$U['nickname']]);
		$U['status']=1;
	}
}

function register_guest($status, $nick){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE nickname=?');
	$stmt->execute([$nick]);
	if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['alreadyreged'], style_this(htmlspecialchars($nick), $tmp[0]));
	}
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=? AND status=1;');
	$stmt->execute([$nick]);
	if($reg=$stmt->fetch(PDO::FETCH_ASSOC)){
		$reg['status']=$status;
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=? WHERE session=?;');
		$stmt->execute([$reg['status'], $reg['session']]);
	}else{
		return sprintf($I['cantreg'], htmlspecialchars($nick));
	}
    
    $stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, embed, style, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
    
    //MODIFICATION for clickable nicknames
    /* REMOVE LATER
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, embed, style, incognito, nocache, tz, eninbox, sortupdown, hidechatters, clickablenicknames, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	*/
	
	//MODIFICATION for clickable nicknames
	/* REMOVE LATER
	$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $U['nickname'], $reg['timestamps'], $reg['embed'], $reg['style'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['clickablenicknames'], $reg['nocache_old']]);
	*/ 
	$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $U['nickname'], $reg['timestamps'], $reg['embed'], $reg['style'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
	if($reg['status']==3){
		//MODIFICATION stlye_this_clickable instead of style_this
		add_system_message(sprintf(get_setting('msgmemreg'), style_this_clickable(htmlspecialchars($reg['nickname']), $reg['style'])));
	}else{
		//MODIFICATION stlye_this_clickable instead of style_this
		add_system_message(sprintf(get_setting('msgsureg'), style_this_clickable(htmlspecialchars($reg['nickname']), $reg['style'])));
	}
	return sprintf($I['successreg'], style_this(htmlspecialchars($reg['nickname']), $reg['style']));
}

function register_new($nick, $pass){
	global $I, $U, $db;
	$nick=preg_replace('/\s/', '', $nick);
	if(empty($nick)){
		return '';
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'sessions WHERE nickname=?');
	$stmt->execute([$nick]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['cantreg'], htmlspecialchars($nick));
	}
	if(!valid_nick($nick)){
		return sprintf($I['invalnick'], get_setting('maxname'), get_setting('nickregex'));
	}
	if(!valid_pass($pass)){
		return sprintf($I['invalpass'], get_setting('minpass'), get_setting('passregex'));
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=?');
	$stmt->execute([$nick]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['alreadyreged'], htmlspecialchars($nick));
	}
	
	$reg=[
		'nickname'	=>$nick,
		'passhash'	=>password_hash($pass, PASSWORD_DEFAULT),
		//Modification Register new Applicant
		'status'	=>(get_setting('suguests') ? 2 : 3),
		
		'refresh'	=>get_setting('defaultrefresh'),
		'bgcolour'	=>get_setting('colbg'),
		'regedby'	=>$U['nickname'],
		'timestamps'	=>get_setting('timestamps'),
		'style'		=>'color:#'.get_setting('coltxt').';',
		'embed'		=>1,
		'incognito'	=>0,
		'nocache'	=>0,
		'nocache_old'	=>1,
		'tz'		=>get_setting('defaulttz'),
		'eninbox'	=>0,
		'sortupdown'	=>get_setting('sortupdown'),
		'hidechatters'	=>get_setting('hidechatters'),
		
		//MODIFICATION clickable nicknames
		/* REMOVE LATER
		'clickablenicknames'	=>0,
		*/
	];
	/*REMOVE LATER
    $stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, style, embed, incognito, nocache, tz, eninbox, sortupdown, hidechatters, clickablenicknames, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	*/
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, style, embed, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['regedby'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
	return sprintf($I['successreg'], htmlspecialchars($reg['nickname']));
}

function change_status($nick, $status){
	global $I, $U, $db;
	if(empty($nick)){
		return '';
	}elseif($U['status']<=$status || !preg_match('/^[023567\-]$/', $status)){
		return sprintf($I['cantchgstat'], htmlspecialchars($nick));
	}
	$stmt=$db->prepare('SELECT incognito, style FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute([$nick, $U['status']]);
	if(!$old=$stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['cantchgstat'], htmlspecialchars($nick));
	}
	if($_REQUEST['set']==='-'){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute([$nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1, incognito=0 WHERE nickname=?;');
		$stmt->execute([$nick]);
		return sprintf($I['succdel'], style_this(htmlspecialchars($nick), $old[1]));
	}else{
		if($status<5){
			$old[0]=0;
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute([$status, $old[0], $nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute([$status, $old[0], $nick]);
		return sprintf($I['succchg'], style_this(htmlspecialchars($nick), $old[1]));
	}
}

function passreset($nick, $pass){
	global $I, $U, $db;
	if(empty($nick)){
		return '';
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute([$nick, $U['status']]);
	if($stmt->fetch(PDO::FETCH_ASSOC)){
		$passhash=password_hash($pass, PASSWORD_DEFAULT);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute([$passhash, $nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE nickname=?;');
		$stmt->execute([$passhash, $nick]);
		return sprintf($I['succpassreset'], htmlspecialchars($nick));
	}else{
		return sprintf($I['cantresetpass'], htmlspecialchars($nick));
	}
}

function amend_profile(){
	global $U;
	if(isset($_REQUEST['refresh'])){
		$U['refresh']=$_REQUEST['refresh'];
	}
	if($U['refresh']<5){
		$U['refresh']=5;
	}elseif($U['refresh']>150){
		$U['refresh']=150;
	}
	if(preg_match('/^#([a-f0-9]{6})$/i', $_REQUEST['colour'], $match)){
		$colour=$match[1];
	}else{
		preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
		$colour=$matches[1];
	}
	if(preg_match('/^#([a-f0-9]{6})$/i', $_REQUEST['bgcolour'], $match)){
		$U['bgcolour']=$match[1];
	}
	$U['style']="color:#$colour;";
	if($U['status']>=3){
		$F=load_fonts();
		if(isset($F[$_REQUEST['font']])){
			$U['style'].=$F[$_REQUEST['font']];
		}
		if(isset($_REQUEST['small'])){
			$U['style'].='font-size:smaller;';
		}
		if(isset($_REQUEST['italic'])){
			$U['style'].='font-style:italic;';
		}
		if(isset($_REQUEST['bold'])){
			$U['style'].='font-weight:bold;';
		}
	}
	if($U['status']>=5 && isset($_REQUEST['incognito']) && get_setting('incognito')){
		$U['incognito']=1;
	}else{
		$U['incognito']=0;
	}
	if(isset($_REQUEST['tz'])){
		$tzs=timezone_identifiers_list();
		if(in_array($_REQUEST['tz'], $tzs)){
			$U['tz']=$_REQUEST['tz'];
		}
	}
	
	//MODIFICATION for clicable nicknames setting
	/* REMOVE LATER
	$clickablelinks_allowedvalues = array(0, 1, 2);
	if(isset($_REQUEST['clickablenicknames']) && in_array($_REQUEST['clickablenicknames'], $clickablelinks_allowedvalues)){
			$U['clickablenicknames'] = (int) $_REQUEST['clickablenicknames'];
    }
	*/
	
	if(isset($_REQUEST['eninbox']) && $_REQUEST['eninbox']>=0 && $_REQUEST['eninbox']<=5){
		$U['eninbox']=$_REQUEST['eninbox'];
	}
	$bool_settings=['timestamps', 'embed', 'nocache', 'sortupdown', 'hidechatters'];
	foreach($bool_settings as $setting){
		if(isset($_REQUEST[$setting])){
			$U[$setting]=1;
		}else{
			$U[$setting]=0;
		}
	}
}

function save_profile(){
	global $I, $U, $db;
	amend_profile();
	//MODIFICATION for clickable nicknames setting
	/* REMOVE LATER
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET refresh=?, style=?, bgcolour=?, timestamps=?, embed=?, incognito=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=?, clickablenicknames=? WHERE session=?;');
	*/
    $stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET refresh=?, style=?, bgcolour=?, timestamps=?, embed=?, incognito=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=? WHERE session=?;');
	
	//MODIFICATION for clickable nicknames (clickablenicknames)
	/* REMOVE LATER
	$stmt->execute([$U['refresh'], $U['style'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['clickablenicknames'], $U['session']]);
	*/
    $stmt->execute([$U['refresh'], $U['style'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['session']]);
	
	if($U['status']>=2){
	/* REMOVE LATER
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET refresh=?, bgcolour=?, timestamps=?, embed=?, incognito=?, style=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=?, clickablenicknames=? WHERE nickname=?;');
		$stmt->execute([$U['refresh'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['style'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['clickablenicknames'], $U['nickname']]);
    */
        $stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET refresh=?, bgcolour=?, timestamps=?, embed=?, incognito=?, style=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=? WHERE nickname=?;');
		$stmt->execute([$U['refresh'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['style'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['nickname']]);
	}
	if(!empty($_REQUEST['unignore'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE ign=? AND ignby=?;');
		$stmt->execute([$_REQUEST['unignore'], $U['nickname']]);
	}
	if(!empty($_REQUEST['ignore'])){
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'messages WHERE poster=? AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?);');
		$stmt->execute([$_REQUEST['ignore'], $U['nickname']]);
		if($U['nickname']!==$_REQUEST['ignore'] && $stmt->fetch(PDO::FETCH_NUM)){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'ignored (ign, ignby) VALUES (?, ?);');
			$stmt->execute([$_REQUEST['ignore'], $U['nickname']]);
		}
	}
	if($U['status']>1 && !empty($_REQUEST['newpass'])){
		if(!valid_pass($_REQUEST['newpass'])){
			return sprintf($I['invalpass'], get_setting('minpass'), get_setting('passregex'));
		}
		if(!isset($_REQUEST['oldpass'])){
			$_REQUEST['oldpass']='';
		}
		if(!isset($_REQUEST['confirmpass'])){
			$_REQUEST['confirmpass']='';
		}
		if($_REQUEST['newpass']!==$_REQUEST['confirmpass']){
			return $I['noconfirm'];
		}else{
			$U['newhash']=password_hash($_REQUEST['newpass'], PASSWORD_DEFAULT);
		}
		if(!password_verify($_REQUEST['oldpass'], $U['passhash'])){
			return $I['wrongpass'];
		}
		$U['passhash']=$U['newhash'];
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE session=?;');
		$stmt->execute([$U['passhash'], $U['session']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute([$U['passhash'], $U['nickname']]);
	}
	if($U['status']>1 && !empty($_REQUEST['newnickname'])){
		$msg=set_new_nickname();
		if($msg!==''){
			return $msg;
		}
	}
	return $I['succprofile'];
}

function set_new_nickname(){
	global $I, $U, $db;
	$_REQUEST['newnickname']=preg_replace('/\s/', '', $_REQUEST['newnickname']);
	if(!valid_nick($_REQUEST['newnickname'])){
		return sprintf($I['invalnick'], get_setting('maxname'), get_setting('nickregex'));
	}
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'sessions WHERE nickname=? UNION SELECT id FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$_REQUEST['newnickname'], $_REQUEST['newnickname']]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return $I['nicknametaken'];
	}else{
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET nickname=? WHERE nickname=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET nickname=? WHERE nickname=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET poster=? WHERE poster=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET recipient=? WHERE recipient=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ignby=? WHERE ignby=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ign=? WHERE ign=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'inbox SET poster=? WHERE poster=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET editedby=? WHERE editedby=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$U['nickname']=$_REQUEST['newnickname'];
	}
	return '';
}

//sets default settings for guests
function add_user_defaults($password){
	global $U;
	$U['refresh']=get_setting('defaultrefresh');
	$U['bgcolour']=get_setting('colbg');
	if(!isset($_REQUEST['colour']) || !preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['colour']) || abs(greyval($_REQUEST['colour'])-greyval(get_setting('colbg')))<75){
		do{
			$colour=sprintf('%06X', mt_rand(0, 16581375));
		}while(abs(greyval($colour)-greyval(get_setting('colbg')))<75);
	}else{
		$colour=$_REQUEST['colour'];
	}
	$U['style']="color:#$colour;";
	$U['timestamps']=get_setting('timestamps');
	$U['embed']=1;
	$U['incognito']=0;
	$U['status']=1;
	$U['nocache']=get_setting('sortupdown');
	if($U['nocache']){
		$U['nocache_old']=0;
	}else{
		$U['nocache_old']=1;
	}
	$U['tz']=get_setting('defaulttz');
	$U['eninbox']=1;
	$U['sortupdown']=get_setting('sortupdown');
	$U['hidechatters']=get_setting('hidechatters');
	$U['passhash']=password_hash($password, PASSWORD_DEFAULT);
	$U['entry']=$U['lastpost']=time();
	
	//MODIFICATION for clickable nicknames
	/* REMOVE LATER
	$U['clickablenicknames']=0;
	*/
}

// message handling

function validate_input(){
	//global $U, $db;
	global $U, $db, $language;
	
	$inbox=false;
	$maxmessage=get_setting('maxmessage');
	$message=mb_substr($_REQUEST['message'], 0, $maxmessage);
	$rejected=mb_substr($_REQUEST['message'], $maxmessage);
	if($U['postid']===$_REQUEST['postid']){// ignore double post=reload from browser or proxy
		$message='';
	}elseif((time()-$U['lastpost'])<=1){// time between posts too short, reject!
		$rejected=$_REQUEST['message'];
		$message='';
	}
	if(!empty($rejected)){
		$rejected=trim($rejected);
		$rejected=htmlspecialchars($rejected);
	}
	$message=htmlspecialchars($message);
	$message=preg_replace("/(\r?\n|\r\n?)/u", '<br>', $message);
	if(isset($_REQUEST['multi'])){
		$message=preg_replace('/\s*<br>/u', '<br>', $message);
		$message=preg_replace('/<br>(<br>)+/u', '<br><br>', $message);
		$message=preg_replace('/<br><br>\s*$/u', '<br>', $message);
		$message=preg_replace('/^<br>\s*$/u', '', $message);
	}else{
		$message=str_replace('<br>', ' ', $message);
	}
	$message=trim($message);
	$message=preg_replace('/\s+/u', ' ', $message);
	$recipient='';
	
	//This ist the the place where the username is added to $displaysend (and later to the message).
	
	/*
		'r @'
        's 17'
        's 24'
        's 31'
        's 48'
        's 56'
        's 65'
	*/
	
	if($_REQUEST['sendto']==='s 17'){
		$poststatus=1;
		//MODIFICATION for clickablenicknames stlye_this_clickable instead of style_this
		$displaysend=sprintf(get_setting('msgsendall'), style_this_clickable(htmlspecialchars($U['nickname']), $U['style']));
	//MODIFICATION 3 lines added for the RG channel
    }elseif($_REQUEST['sendto']==='s 24' && $U['status']>=2){
		$poststatus=2;
        //MODIFICATION for clickablenicknames stlye_this_clickable instead of style_this
        $displaysend=sprintf('[RG] %s - ', style_this_clickable(htmlspecialchars($U['nickname']), $U['style']));

    }elseif($_REQUEST['sendto']==='s 31' && $U['status']>=3){
		$poststatus=3;
        //MODIFICATION for clickablenicknames stlye_this_clickable instead of style_this
		$displaysend=sprintf(get_setting('msgsendmem'), style_this_clickable(htmlspecialchars($U['nickname']), $U['style']));
    }elseif($_REQUEST['sendto']==='s 48' && $U['status']>=5){
		$poststatus=5;
        //MODIFICATION for clickablenicknames stlye_this_clickable instead of style_this
		$displaysend=sprintf(get_setting('msgsendmod'), style_this_clickable(htmlspecialchars($U['nickname']), $U['style']));
   	// Modifcation added all rooms channel
	}elseif($_REQUEST['sendto']==='r @' && $U['status']>=5){
		$poststatus=1;
		$displaysend=sprintf('[All Rooms] %s - ', style_this_clickable(htmlspecialchars($U['nickname']), $U['style']));
	//MODIFICATION 1 line replaced for the new SMod channel ([SMods] in front of the line.
	}elseif($_REQUEST['sendto']==='s 56' && $U['status']>=6){
		$poststatus=6;
        //MODIFICATION for clickablenicknames stlye_this_clickable instead of style_this
		$displaysend=sprintf('[SMods] %s - ', style_this_clickable(htmlspecialchars($U['nickname']), $U['style']));

    //MODIFICATION 3 lines added for the new admin channel (admins only, no smods)
    }elseif($_REQUEST['sendto']==='s 65' && $U['status']>=7){
		$poststatus=7;
        //MODIFICATION for clickablenicknames stlye_this_clickable instead of style_this
        $displaysend=sprintf(get_setting('msgsendadm'), style_this_clickable(htmlspecialchars($U['nickname']), $U['style']));
	}else{ // known nick in room?
		if(get_setting('disablepm')){
			//PMs disabled
			return;
		}
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'ignored WHERE (ignby=? AND ign=?) OR (ign=? AND ignby=?);');
		$stmt->execute([$_REQUEST['sendto'], $U['nickname'], $_REQUEST['sendto'], $U['nickname']]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			//ignored
			return;
		}
		$tmp=false;
		$stmt=$db->prepare('SELECT s.style, 0 AS inbox FROM ' . PREFIX . 'sessions AS s LEFT JOIN ' . PREFIX . 'members AS m ON (m.nickname=s.nickname) WHERE s.nickname=? AND (s.incognito=0 OR (m.eninbox!=0 AND m.eninbox<=?));');
		$stmt->execute([$_REQUEST['sendto'], $U['status']]);
		if(!$tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$stmt=$db->prepare('SELECT style, 1 AS inbox FROM ' . PREFIX . 'members WHERE nickname=? AND eninbox!=0 AND eninbox<=?;');
			$stmt->execute([$_REQUEST['sendto'], $U['status']]);
			if(!$tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
				//nickname left or disabled offline inbox for us
				return;
			}
		}
		$recipient=$_REQUEST['sendto'];
		$poststatus=9;

		$displaysend=sprintf(get_setting('msgsendprv'), style_this_clickable(htmlspecialchars($U['nickname']), $U['style']), style_this_clickable(htmlspecialchars($recipient), $tmp['style']));
		$inbox=$tmp['inbox'];
	}
	if($poststatus!==9 && preg_match('~^/me~iu', $message)){
		$displaysend=style_this(htmlspecialchars("$U[nickname] "), $U['style']);
		$message=preg_replace("~^/me\s?~iu", '', $message);
	}
	$message=apply_filter($message, $poststatus, $U['nickname']);
	$message=create_hotlinks($message);
	$message=apply_linkfilter($message);
	if(isset($_FILES['file']) && get_setting('enfileupload')>0 && get_setting('enfileupload')<=$U['status']){
		if($_FILES['file']['error']===UPLOAD_ERR_OK && $_FILES['file']['size']<=(1024*get_setting('maxuploadsize'))){
			$hash=sha1_file($_FILES['file']['tmp_name']);
			$name=htmlspecialchars($_FILES['file']['name']);
			$message=sprintf(get_setting('msgattache'), "<a class=\"attachement\" href=\"$_SERVER[SCRIPT_NAME]?action=download&amp;id=$hash\" target=\"_blank\">$name</a>", $message);
		}
	}
	if(add_message($message, $recipient, $U['nickname'], $U['status'], $poststatus, $displaysend, $U['style'])){
		$U['lastpost']=time();
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, postid=? WHERE session=?;');
		$stmt->execute([$U['lastpost'], $_REQUEST['postid'], $U['session']]);
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poster=? ORDER BY id DESC LIMIT 1;');
		$stmt->execute([$U['nickname']]);
		$id=$stmt->fetch(PDO::FETCH_NUM);
		if($inbox && $id){
			$newmessage=[
				'postdate'	=>time(),
				'poster'	=>$U['nickname'],
				'recipient'	=>$recipient,
				
				'text'		=>"<span class=\"usermsg\">$displaysend".style_this($message, $U['style']).'</span>'
			];
			if(MSGENCRYPTED){
                $newmessage['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($newmessage['text'], '', AES_IV, ENCRYPTKEY));
			}
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?)');
			$stmt->execute([$newmessage['postdate'], $id[0], $newmessage['poster'], $newmessage['recipient'], $newmessage['text']]);
		}
		if(isset($hash) && $id){
			if(!empty($_FILES['file']['type']) && preg_match('~^[a-z0-9/\-\.\+]*$~i', $_FILES['file']['type'])){
				$type=$_FILES['file']['type'];
			}else{
				$type='application/octet-stream';
			}
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'files (postid, hash, filename, type, data) VALUES (?, ?, ?, ?, ?);');
			$stmt->execute([$id[0], $hash, str_replace('"', '\"', $_FILES['file']['name']), $type, base64_encode(file_get_contents($_FILES['file']['tmp_name']))]);
			unlink($_FILES['file']['tmp_name']);
		}
	}
	return $rejected;
}

function apply_filter($message, $poststatus, $nickname){
	global $I, $U;
	$message=str_replace('<br>', "\n", $message);
	$message=apply_mention($message);
	$filters=get_filters();
	foreach($filters as $filter){
        //MODIFICATION line changed (! removed)
		if($poststatus!==9 || $filter['allowinpm']){
			if($filter['cs']){
				$message=preg_replace("/$filter[match]/u", $filter['replace'], $message, -1, $count);
			}else{
				$message=preg_replace("/$filter[match]/iu", $filter['replace'], $message, -1, $count);
			}
		}
		if(isset($count) && $count>0 && $filter['kick'] && ($U['status']<5 || get_setting('filtermodkick'))){
			kick_chatter([$nickname], $filter['replace'], false);
			setcookie(COOKIENAME, false);
			$_REQUEST['session']='';
			send_error("$I[kicked]<br>$filter[replace]");
		}
	}
	$message=str_replace("\n", '<br>', $message);
	return $message;
}

function apply_linkfilter($message){
	$filters=get_linkfilters();
	foreach($filters as $filter){
		$message=preg_replace_callback("/<a href=\"([^\"]+)\" target=\"_blank\"( rel=\"noreferrer noopener\")?>(.*?(?=<\/a>))<\/a>/iu",
			function ($matched) use(&$filter){
				return "<a href=\"$matched[1]\" target=\"_blank\"$matched[2]>".preg_replace("/$filter[match]/iu", $filter['replace'], $matched[3]).'</a>';
			}
		, $message);
	}
	$redirect=get_setting('redirect');
	if(get_setting('imgembed')){
		$message=preg_replace_callback('/\[img\]\s?<a href="([^"]+)" target="_blank"( rel=\"noreferrer noopener\")?>(.*?(?=<\/a>))<\/a>/iu',
			function ($matched){
				return str_ireplace('[/img]', '', "<br><a href=\"$matched[1]\" target=\"_blank\"$matched[2]><img src=\"$matched[1]\"></a><br>");
			}
		, $message);
	}
	if(empty($redirect)){
		$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&amp;url=";
	}
	if(get_setting('forceredirect')){
		$message=preg_replace_callback('/<a href="([^"]+)" target="_blank"( rel=\"noreferrer noopener\")?>(.*?(?=<\/a>))<\/a>/u',
			function ($matched) use($redirect){
				return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\"$matched[2]>$matched[3]</a>";
			}
		, $message);
	}elseif(preg_match_all('/<a href="([^"]+)" target="_blank"( rel=\"noreferrer noopener\")?>(.*?(?=<\/a>))<\/a>/u', $message, $matches)){
		foreach($matches[1] as $match){
			if(!preg_match('~^http(s)?://~u', $match)){
				$message=preg_replace_callback('/<a href="('.preg_quote($match, '/').')\" target=\"_blank\"( rel=\"noreferrer noopener\")?>(.*?(?=<\/a>))<\/a>/u',
					function ($matched) use($redirect){
						return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\"$matched[2]>$matched[3]</a>";
					}
				, $message);
			}
		}
	}
	return $message;
}

function create_hotlinks($message){
	//Make hotlinks for URLs, redirect through dereferrer script to prevent session leakage
	// 1. all explicit schemes with whatever xxx://yyyyyyy
	$message=preg_replace('~(^|[^\w"])(\w+://[^\s<>]+)~iu', "$1<<$2>>", $message);
	// 2. valid URLs without scheme:
	$message=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d*)?/[^\s<>]*)(?![^<>]*>)~iu', "<<$1>>", $message); // server/path given
	$message=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+:\d+)(?![^<>]*>)~iu', "<<$1>>", $message); // server:port given
	$message=preg_replace('~([^\s<>]*:[^\s<>]*@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d+)?)(?![^<>]*>)~iu', "<<$1>>", $message); // au:th@server given
	// 3. likely servers without any hints but not filenames like *.rar zip exe etc.
	$message=preg_replace('~((?:[a-z0-9\-]+\.)*(?:[a-z2-7]{55}d|[a-z2-7]{16})\.onion)(?![^<>]*>)~iu', "<<$1>>", $message);// *.onion
	$message=preg_replace('~([a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?:\.(?!rar|zip|exe|gz|7z|bat|doc)[a-z]{2,}))(?=[^a-z0-9\-\.]|$)(?![^<>]*>)~iu', "<<$1>>", $message);// xxx.yyy.zzz
	// Convert every <<....>> into proper links:
	$message=preg_replace_callback('/<<([^<>]+)>>/u',
		function ($matches){
			if(strpos($matches[1], '://')===false){
				return "<a href=\"http://$matches[1]\" target=\"_blank\" rel=\"noreferrer noopener\">$matches[1]</a>";
			}else{
				return "<a href=\"$matches[1]\" target=\"_blank\" rel=\"noreferrer noopener\">$matches[1]</a>";
			}
		}
	, $message);
	return $message;
}

function apply_mention($message){
	return preg_replace_callback('/\@([^\s]+)/iu', function ($matched){
		global $db;
		$nick=htmlspecialchars_decode($matched[1]);
		$rest='';
		for($i=0;$i<=3;++$i){
			//match case-sensitive present nicknames
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'sessions WHERE nickname=?;');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-insensitive present nicknames
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'sessions WHERE LOWER(nickname)=LOWER(?);');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-sensitive members
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE nickname=?;');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-insensitive members
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE LOWER(nickname)=LOWER(?);');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			if(strlen($nick)===1){
				break;
			}
			$rest=mb_substr($nick, -1).$rest;
			$nick=mb_substr($nick, 0, -1);
		}
		return $matched[0];
	}, $message);
}

function add_message($message, $recipient, $poster, $delstatus, $poststatus, $displaysend, $style){
	global $db, $U;
	if($message===''){
		return false;
	}
	//Modifications for chat rooms
	$roomid=$U['roomid'];
	if(isset($_REQUEST['sendto']) && $_REQUEST['sendto']==='r @' && $U['status']>=5){
		$allrooms = 1;
		$roomid = null;
	}else{
		$allrooms = 0;
	}
	$newmessage=[
		'postdate'	=>time(),
		'poststatus'	=>$poststatus,
		'poster'	=>$poster,
		'recipient'	=>$recipient,
		'text'		=>"<span class=\"usermsg\">$displaysend".style_this($message, $style).'</span>',
		'delstatus'	=>$delstatus,
		'roomid'	=>$roomid,
		'allrooms'	=> $allrooms
	];
	//Modifcation chat rooms
	if($newmessage['roomid']===NULL){
		//prevent posting the same message twice, if no other message was posted in-between.
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poststatus=? AND poster=? AND recipient=? AND text=? AND roomid IS NULL AND id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . 'messages ORDER BY id DESC LIMIT 1) AS t);');
		$stmt->execute([$newmessage['poststatus'], $newmessage['poster'], $newmessage['recipient'], $newmessage['text']]);
	}else{
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poststatus=? AND poster=? AND recipient=? AND text=? AND roomid=? AND id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . 'messages ORDER BY id DESC LIMIT 1) AS t);');
		$stmt->execute([$newmessage['poststatus'], $newmessage['poster'], $newmessage['recipient'], $newmessage['text'], $newmessage['roomid']]);
	}
	if($stmt->fetch(PDO::FETCH_NUM)){
		return false;
	}
	write_message($newmessage);
	return true;
}
//Modification chat rooms
function add_system_message($mes, $roomid=NULL){
	if($mes===''){
		return;
	}
	$sysmessage=[
		'postdate'	=>time(),
		'poststatus'	=>1,
		'poster'	=>'',
		'recipient'	=>'',
		'text'		=>"<span class=\"sysmsg\">$mes</span>",
		'delstatus'	=>4,
		'roomid'    =>$roomid,
		'allrooms'	=> 0
	];
	write_message($sysmessage);
}

function write_message($message){
	global $db;
	if(MSGENCRYPTED){
        $message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
	}
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'messages (postdate, poststatus, poster, recipient, text, delstatus, roomid, allrooms) VALUES (?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute([$message['postdate'], $message['poststatus'], $message['poster'], $message['recipient'], $message['text'], $message['delstatus'], $message['roomid'], $message['allrooms']]);
	if($message['poststatus']<9 && get_setting('sendmail')){
		$subject='New Chat message';
		$headers='From: '.get_setting('mailsender')."\r\nX-Mailer: PHP/".phpversion()."\r\nContent-Type: text/html; charset=UTF-8\r\n";
		$body='<html><body style="background-color:#'.get_setting('colbg').';color:#'.get_setting('coltxt').";\">$message[text]</body></html>";
		mail(get_setting('mailreceiver'), $subject, $body, $headers);
	}
}

//Modified
function clean_chat(){
	global $db;
	$db->query('DELETE FROM ' . PREFIX . 'messages;');
	add_system_message(sprintf(get_setting('msgclean'), get_setting('chatname')));
}
//Modified
function clean_room(){
	global $db, $U;
	$stmt = $db->prepare('DELETE FROM ' . PREFIX . 'messages where roomid=?;');
	$stmt->execute([$U['roomid']]);
}

function clean_selected($status, $nick){
	global $db;
	if(isset($_REQUEST['mid'])){
        
        //Modification modsdeladminmsg - moderators can delete admin messages (but he can only delete the messages he is able to read.)
		if((get_setting('modsdeladminmsg') == 1) && ($status >= 5)){
                      
            $stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=? AND (poster=? OR recipient=? OR (poststatus<= '.$status.' AND delstatus<9));');
            foreach($_REQUEST['mid'] as $mid){
                $stmt->execute([$mid, $nick, $nick]);
            }
		}
        else{    
            $stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=? AND (poster=? OR recipient=? OR (poststatus<? AND delstatus<?));');
            foreach($_REQUEST['mid'] as $mid){
                $stmt->execute([$mid, $nick, $nick, $status, $status]);
            }
        }
	}
}

function clean_inbox_selected(){
	global $U, $db;
	if(isset($_REQUEST['mid'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE id=? AND recipient=?;');
		foreach($_REQUEST['mid'] as $mid){
			$stmt->execute([$mid, $U['nickname']]);
		}
	}
}

function del_all_messages($nick, $entry){
	global $db;
	if($nick==''){
		return;
	}
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE poster=? AND postdate>=?;');
	$stmt->execute([$nick, $entry]);
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE poster=? AND postdate>=?;');
	$stmt->execute([$nick, $entry]);
}

function del_last_message(){
	
	global $U, $db;
	if($U['status']>1){
		$entry=0;
	}else{
		$entry=$U['entry'];
	}
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poster=? AND postdate>=? ORDER BY id DESC LIMIT 1;');
	$stmt->execute([$U['nickname'], $entry]);
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=?;');
		$stmt->execute($id);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE postid=?;');
		$stmt->execute($id);
	}
}

function print_messages($delstatus=0, $modroom=0){
	//line changed
	global $U, $I, $db, $language;
	
	
	$dateformat=get_setting('dateformat');
	if(!$U['embed'] && get_setting('imgembed')){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	if($U['sortupdown']){
		$direction='ASC';
	}else{
		$direction='DESC';
	}
	if($U['status']>1){
		$entry=0;
	}else{
		$entry=$U['entry'];
	}
	if(isset($_REQUEST['modroom']) && $_REQUEST['modroom'] && $U['status']>=5){
		$modroom=1;
	}else{
		$modroom=0;
	}
	
	//MODIFCATION chat rooms to only show messages of the all rooms

	//MODIFICATION DEL-BUTTONS some lines added to enable delete buttons in front of messages for mods and above.
	// look at function send_choose_messages for better understanding 
	$modmode = false; 
	
	

	
	
	//modmode (DEL-Buttons) for mods. and for members (according to the memdel setting (always OR if no mod is present and if memkick setting enabled.)
	$memdel = (int)get_setting('memdel');
	if(($delstatus === 0 && $U['status']>= 5) || ($U['status']>=3 && $memdel == 2) || ($U['status']>=3 && get_count_mods()==0 && $memdel == 1)){
	  $modmode = true;
	  $delstatus = $U['status'];
	  //debug 
	  //echo "modmode active";
	}
	

	//Modification for visibility of channels in all roooms
	$channelvisinroom = (int) get_setting('channelvisinroom');
	if($channelvisinroom == 0){
		$channelvisinroom = 2;
	}


	echo '<div id="messages">';
	
	if($modmode === true){
        echo form('admin_clean_message', 'clean');
        echo hidden('what', 'selected');
        echo hidden('modroom',$modroom); // so that deleting a message does not cause exiting modroom
        
        $stmt=$db->prepare('SELECT id, postdate, text, poststatus, delstatus, poster, recipient, roomid, allrooms FROM ' . PREFIX . 'messages WHERE (poststatus<=? OR '.
		'(poststatus=9 AND ( (poster=? AND recipient NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) ) OR recipient=?) AND postdate>=?)'.
		') AND poster NOT IN (SELECT ign FROM ' . PREFIX . "ignored WHERE ignby=?) ORDER BY id $direction;");
		$stmt->execute([$U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $entry, $U['nickname']]);
        
       
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			
			//Modification for chat rooms
 			if($message['poststatus']<$channelvisinroom && $message['roomid']!==$U['roomid'] && !$message['allrooms'] && !$modroom){
 				continue;
			}

			//Modification for modrooms in chat rooms
			$roomname = "";
			if($modroom && !$message['allrooms']){
				$roomname = 'Main Chat';
				if($message['roomid']!=null){
					$stmt1=$db->prepare('SELECT name FROM ' . PREFIX . 'rooms WHERE id=? AND access<=?');
					$stmt1->execute([$message['roomid'], $U['status']]);
					if(!$name=$stmt1->fetch(PDO::FETCH_NUM)){
						continue;
					}
					$roomname = $name[0];
				}
				$roomname = '['.$roomname.']';
			}

 			prepare_message_print($message, $removeEmbed);
			//MODIFICATION modsdeladminmsg (mods can delete admins messages)
			if(get_setting('modsdeladminmsg')==1 && $U['status'] >= 5){
                if(($message['poststatus']<=$U['status'] &&  $message['delstatus']<9) || ($message['poster']===$U['nickname'] || ($message['recipient']===$U['nickname']) && $message['postdate']>=$entry)){
                    echo "<div class=\"msg\"><button title = \"Delete message\" class = \"delbutton_inline_removable\" name=\"mid[]\" type=\"submit\" value=\"$message[id]\">DEL</button>";
                }
			
			}elseif(($message['poststatus']<$U['status'] &&  $message['delstatus']<$U['status']) || ($message['poster']===$U['nickname'] || ($message['recipient']===$U['nickname']) && $message['postdate']>=$entry))	{
                echo "<div class=\"msg\"><button title = \"Delete message\" class = \"delbutton_inline_removable\" name=\"mid[]\" type=\"submit\" value=\"$message[id]\">DEL</button>";
            
			}
			else{
                //next line is for debug output (to check if permissions to delete messages are correct. test passed! (everything okay)
                //echo "<div class=\"msg\"><button class = \"delbutton_inline_unremovable\" name=\"mid[]\" type=\"submit\" value=\"$message[id]\">-----</button>";
                echo "<div class=\"msg\">&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp";
                
			}
			
			
			
			//if((int)$U['clickablenicknames']>0){   //REMOVE LINE LATER
			if((bool) get_setting('clickablenicknamesglobal')){
        
                    $message_new = make_nicknames_clickable($message['text']);
                
            }else{     
			
                $message_new = $message['text'];
			}
			
			if($timestamps){
                    echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
            }

            if($modroom){
            	echo "<span class=\"modroom\">$roomname</span>";
            }
			echo " $message_new</div>";
		}
        echo "</form>";
	}
	
	elseif($delstatus>0){
        //Modification modsdeladminmsg

        if(get_setting('modsdeladminmsg')==1){
             $stmt=$db->prepare('SELECT postdate, id, poststatus, roomid, allrooms text FROM ' . PREFIX . 'messages WHERE '.
            "(poststatus<=? AND delstatus<9) OR ((poster=? OR recipient=?) AND postdate>=?) ORDER BY id $direction;");
            $stmt->execute([$U['status'], $U['nickname'], $U['nickname'], $entry]);
            while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
            	//Modification for chat rooms
            	if($message['poststatus']<$channelvisinroom && $message['roomid']!==$U['roomid'] && !$message['allrooms']){
 					continue;
				}

                prepare_message_print($message, $removeEmbed);
                echo "<div class=\"msg\"><label><input type=\"checkbox\" name=\"mid[]\" value=\"$message[id]\">";
                if($timestamps){
                    echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
                }
                echo " $message[text]</label></div>";
            }
        }
        else{
            $stmt=$db->prepare('SELECT postdate, id, text, poststatus, roomid, allrooms FROM ' . PREFIX . 'messages WHERE '.
            "(poststatus<? AND delstatus<?) OR ((poster=? OR recipient=?) AND postdate>=?) ORDER BY id $direction;");
            $stmt->execute([$U['status'], $delstatus, $U['nickname'], $U['nickname'], $entry]);
            while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
            	//Modification for chat rooms
            	if($message['poststatus']<$channelvisinroom && $message['roomid']!==$U['roomid'] && !$message['allrooms']){
 					continue;
				}
                prepare_message_print($message, $removeEmbed);
                echo "<div class=\"msg\"><label><input type=\"checkbox\" name=\"mid[]\" value=\"$message[id]\">";
                if($timestamps){
                    echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
                }
                echo " $message[text]</label></div>";
            }
		}
	}else{
		$stmt=$db->prepare('SELECT id, postdate, text, roomid, allrooms, poststatus FROM ' . PREFIX . 'messages WHERE (poststatus<=? OR '.
		'(poststatus=9 AND ( (poster=? AND recipient NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) ) OR recipient=?) AND postdate>=?)'.
		') AND poster NOT IN (SELECT ign FROM ' . PREFIX . "ignored WHERE ignby=?) ORDER BY id $direction;");
		$stmt->execute([$U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $entry, $U['nickname']]);
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			//Modification for chat rooms
			if($message['poststatus']<$channelvisinroom && $message['roomid']!==$U['roomid'] && !$message['allrooms']){
 					continue;
			}

			prepare_message_print($message, $removeEmbed);
			echo '<div class="msg">';
			
			//MODIFICATION to make nicknames clickable //REMOVE LINE LATER
			//if((int)$U['clickablenicknames']>0){//REMOVE LINE LATER
			//MODIFICATION to make nicknames clickable (global setting
			if((bool) get_setting('clickablenicknamesglobal')){         
                    $message_new = make_nicknames_clickable($message['text']);
            }else{     
			
                $message_new = $message['text'];
			}
			
			
			
			if($timestamps){
				echo '<small>'.date($dateformat, $message['postdate']).' - </small>';
			}
			echo "$message_new</div>";
		}
	}
	echo '</div>';
}

//MODIFICATION for clickable nicknames
function make_nicknames_clickable($message){
    
    global $U, $language;
    $nc=substr(time(), -6);
    
    $channel = "";
    $sender = "";
    $recipient = "";
    $pm = false;
    
    $channel_encoded = "";
    
    //pattern for default system message settings in chat setup. If system messages are changed in the setup, this pattern has to be changed as well. 
    $pattern_channel_detect = "(\[RG\]\ |\[M\]\ |\[Staff\]\ |\[SMods\]\ |\[Admin\]\ )";
    
    $pattern_pm_detect = "\[(\<span\ style\=\"[^\"]{1,}\"\><span\ class\=\"clickablenickname\"\>[A-Za-z0-9]{1,}\<\/span\>\<\/span\>)\ to\ ((?1))\]";

    $pattern = "(\<span\ style\=\"[^\"]{1,}\"\>\<span\ class\=\"clickablenickname\"\>([A-Za-z0-9]{1,})\<\/span\>)";
    

    preg_match('/'.$pattern_pm_detect.'/i', $message, $matches);
    if (!empty($matches['0'])){
         $pm = true;
    }
    
    preg_match('/'.$pattern_channel_detect.'/i', $message, $matches);
    if (!empty($matches['0'])){
        if($matches['0'] === "[RG] "){
                $channel = "s 24";
        }elseif($matches['0'] === "[M] "){
                $channel = "s 31";
        }elseif($matches['0'] === "[Staff] "){
                $channel = "s 48";
        }elseif($matches['0'] === "[Admins] "){
                $channel = "s 56";
        }elseif($matches['0'] === "[Gods] "){
                $channel = "s 65";
        }
    }else{
        $channel = "s 17"; // send to all 
    }   
    
    //channel must be encoded because of special character + and & and space
    $channel_encoded = urlencode($channel);

    /* REMOVE LATER
    //option 1
    if($pm || ((int)$U['clickablenicknames']===1)){
          $replacement = "<a class=\"nicklink\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=".htmlspecialchars('$2').'" target="post">'.'$1'.'</a>';    
    }

    //option 2
    if(!$pm && ((int)$U['clickablenicknames']===2)){
    $replacement = "<a class=\"nicklink\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=".$channel_encoded."&amp;nickname=@".htmlspecialchars('$2').'&nbsp" target="post">'.'$1'.'</a>';
    }
    */
    
    if($pm){//IF PM DETECTED
    $replacement = "<a class=\"nicklink\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=".htmlspecialchars('$2').'" target="post">'.'$1'.'</a>';    
    }else{ //Message to all or to one of the channels
        $replacement = "<a class=\"nicklink\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=".$channel_encoded."&amp;nickname=@".htmlspecialchars('$2').'&nbsp" target="post">'.'$1'.'</a>';
    }
    
    //regex for option 1 and option 2 and PM
    $message = preg_replace("/$pattern/", $replacement, $message); 
      
    return $message;
}


function prepare_message_print(&$message, $removeEmbed){
	if(MSGENCRYPTED){
        $message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
	}
	if($removeEmbed){
		$message['text']=preg_replace_callback('/<img src="([^"]+)"><\/a>/u',
			function ($matched){
				return "$matched[1]</a>";
			}
		, $message['text']);
	}
}

// this and that

function send_headers(){
	header('Content-Type: text/html; charset=UTF-8');
	header('Pragma: no-cache');
	header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
	header('Expires: 0');
	header('Referrer-Policy: no-referrer');
	header("Content-Security-Policy: default-src 'self'; img-src * data:; media-src * data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: sameorigin');
    header('X-XSS-Protection: 1; mode=block');
	if($_SERVER['REQUEST_METHOD']==='HEAD'){
		exit; // headers sent, no further processing needed
	}
}

function save_setup($C){
	global $db;
	//sanity checks and escaping
	foreach($C['msg_settings'] as $setting){
		$_REQUEST[$setting]=htmlspecialchars($_REQUEST[$setting]);
	}
	foreach($C['number_settings'] as $setting){
		settype($_REQUEST[$setting], 'int');
	}
	foreach($C['colour_settings'] as $setting){
		if(preg_match('/^#([a-f0-9]{6})$/i', $_REQUEST[$setting], $match)){
			$_REQUEST[$setting]=$match[1];
		}else{
			unset($_REQUEST[$setting]);
		}
	}
	settype($_REQUEST['guestaccess'], 'int');
	if(!preg_match('/^[01234]$/', $_REQUEST['guestaccess'])){
		unset($_REQUEST['guestaccess']);
	}elseif($_REQUEST['guestaccess']==4){
		$db->exec('DELETE FROM ' . PREFIX . 'sessions WHERE status<7;');
	}
	settype($_REQUEST['englobalpass'], 'int');
	settype($_REQUEST['captcha'], 'int');
	settype($_REQUEST['dismemcaptcha'], 'int');
	settype($_REQUEST['guestreg'], 'int');
	if(isset($_REQUEST['defaulttz'])){
		$tzs=timezone_identifiers_list();
		if(!in_array($_REQUEST['defaulttz'], $tzs)){
			unset($_REQUEST['defualttz']);
		}
	}
	$_REQUEST['rulestxt']=preg_replace("/(\r?\n|\r\n?)/u", '<br>', $_REQUEST['rulestxt']);
	$_REQUEST['chatname']=htmlspecialchars($_REQUEST['chatname']);
	$_REQUEST['redirect']=htmlspecialchars($_REQUEST['redirect']);
	if($_REQUEST['memberexpire']<5){
		$_REQUEST['memberexpire']=5;
	}
	if($_REQUEST['captchatime']<30){
		$_REQUEST['memberexpire']=30;
	}
	if($_REQUEST['defaultrefresh']<5){
		$_REQUEST['defaultrefresh']=5;
	}elseif($_REQUEST['defaultrefresh']>150){
		$_REQUEST['defaultrefresh']=150;
	}
	if($_REQUEST['maxname']<1){
		$_REQUEST['maxname']=1;
	}elseif($_REQUEST['maxname']>50){
		$_REQUEST['maxname']=50;
	}
	if($_REQUEST['maxmessage']<1){
		$_REQUEST['maxmessage']=1;
	}elseif($_REQUEST['maxmessage']>16000){
		$_REQUEST['maxmessage']=16000;
	}
		if($_REQUEST['numnotes']<1){
		$_REQUEST['numnotes']=1;
	}
	if(!valid_regex($_REQUEST['nickregex'])){
		unset($_REQUEST['nickregex']);
	}
	if(!valid_regex($_REQUEST['passregex'])){
		unset($_REQUEST['passregex']);
	}
	// Modification spare notes
	if(!preg_match('/^[3567]$/', $_REQUEST['sparenotesaccess'])){
		$_REQUEST['sparenotesaccess'] = '10';
	}
	$_REQUEST['sparenotesname'] = htmlspecialchars($_REQUEST['sparenotesname']);
	// End modification
	// Modification chat rooms
	if(!preg_match('/^[567]$/', $_REQUEST['roomcreateaccess'])){
		unset($_REQUEST['roomcreateaccess']);
	}
	settype($_REQUEST['roomexpire'], 'int');
	if(!preg_match('/^[235679]$/', $_REQUEST['channelvisinroom'])){
		unset($_REQUEST['channelvisinroom']);
	}
	// End modification

	//save values
	foreach($C['settings'] as $setting){
		if(isset($_REQUEST[$setting])){
			update_setting($setting, $_REQUEST[$setting]);
		}
	}
}

function set_default_tz(){
	global $U;
	if(isset($U['tz'])){
		date_default_timezone_set($U['tz']);
	}else{
		date_default_timezone_set(get_setting('defaulttz'));
	}
}

function valid_admin(){
	global $U;
	if(isset($_REQUEST['session'])){
		parse_sessions();
	}
	if(!isset($U['session']) && isset($_REQUEST['nick']) && isset($_REQUEST['pass'])){
		create_session(true, $_REQUEST['nick'], $_REQUEST['pass']);
	}
	if(isset($U['status'])){
		if($U['status']>=7){
			return true;
		}
		send_access_denied();
	}
	return false;
}

function valid_nick($nick){
	$len=mb_strlen($nick);
	if($len<1 || $len>get_setting('maxname')){
		return false;
	}
	return preg_match('/'.get_setting('nickregex').'/u', $nick);
}

function valid_pass($pass){
	if(mb_strlen($pass)<get_setting('minpass')){
		return false;
	}
	return preg_match('/'.get_setting('passregex').'/u', $pass);
}

function valid_regex(&$regex){
	$regex=preg_replace('~(^|[^\\\\])/~', "$1\/u", $regex); // Escape "/" if not yet escaped
	return (@preg_match("/$_REQUEST[match]/u", '') !== false);
}

function get_timeout($lastpost, $expire){
	$s=($lastpost+60*$expire)-time();
	$m=floor($s/60);
	$s%=60;
	if($s<10){
		$s="0$s";
	}
	if($m>60){
		$h=floor($m/60);
		$m%=60;
		if($m<10){
			$m="0$m";
		}
		return "$h:$m:$s";
	}else{
		return "$m:$s";
	}
}

function print_colours(){
	global $I;
	// Prints a short list with selected named HTML colours and filters out illegible text colours for the given background.
	// It's a simple comparison of weighted grey values. This is not very accurate but gets the job done well enough.
	// name=>[colour, greyval(colour)]
	$colours=['Beige'=>['F5F5DC', 242.25], 'Black'=>['000000', 0], 'Blue'=>['0000FF', 28.05], 'BlueViolet'=>['8A2BE2', 91.63], 'Brown'=>['A52A2A', 78.9], 'Cyan'=>['00FFFF', 178.5], 'DarkBlue'=>['00008B', 15.29], 'DarkGreen'=>['006400', 59], 'DarkRed'=>['8B0000', 41.7], 'DarkViolet'=>['9400D3', 67.61], 'DeepSkyBlue'=>['00BFFF', 140.74], 'Gold'=>['FFD700', 203.35], 'Grey'=>['808080', 128], 'Green'=>['008000', 75.52], 'HotPink'=>['FF69B4', 158.25], 'Indigo'=>['4B0082', 36.8], 'LightBlue'=>['ADD8E6', 204.64], 'LightGreen'=>['90EE90', 199.46], 'LimeGreen'=>['32CD32', 141.45], 'Magenta'=>['FF00FF', 104.55], 'Olive'=>['808000', 113.92], 'Orange'=>['FFA500', 173.85], 'OrangeRed'=>['FF4500', 117.21], 'Purple'=>['800080', 52.48], 'Red'=>['FF0000', 76.5], 'RoyalBlue'=>['4169E1', 106.2], 'SeaGreen'=>['2E8B57', 105.38], 'Sienna'=>['A0522D', 101.33], 'Silver'=>['C0C0C0', 192], 'Tan'=>['D2B48C', 184.6], 'Teal'=>['008080', 89.6], 'Violet'=>['EE82EE', 174.28], 'White'=>['FFFFFF', 255], 'Yellow'=>['FFFF00', 226.95], 'YellowGreen'=>['9ACD32', 172.65]];
	$greybg=greyval(get_setting('colbg'));
	foreach($colours as $name=>$colour){
		if(abs($greybg-$colour[1])>75){
			echo "<option value=\"$colour[0]\" style=\"color:#$colour[0];\">$I[$name]</option>";
		}
	}
}

function greyval($colour){
	return hexdec(substr($colour, 0, 2))*.3+hexdec(substr($colour, 2, 2))*.59+hexdec(substr($colour, 4, 2))*.11;
}

function style_this($text, $styleinfo){
	return "<span style=\"$styleinfo\">$text</span>";
}

//new function for clickablenicknames
function style_this_clickable($text, $styleinfo){
	return "<span style=\"$styleinfo\"><span class=\"clickablenickname\">$text</span></span>";
}

function check_init(){
	global $db;
	return @$db->query('SELECT null FROM ' . PREFIX . 'settings LIMIT 1;');
}

// run every minute doing various database cleanup task
function cron(){
	global $db;
	$time=time();
	if(get_setting('nextcron')>$time){
		return;
	}
	update_setting('nextcron', $time+10);
	// delete old sessions
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE (status<=2 AND lastpost<(?-60*(SELECT value FROM ' . PREFIX . "settings WHERE setting='guestexpire'))) OR (status>2 AND lastpost<(?-60*(SELECT value FROM " . PREFIX . "settings WHERE setting='memberexpire')));");
	$stmt->execute([$time, $time]);
	// delete old messages
	$limit=get_setting('messagelimit');
	$stmt=$db->query('SELECT id FROM ' . PREFIX . "messages WHERE poststatus=1 AND roomid IS NULL ORDER BY id DESC LIMIT 1 OFFSET $limit;");
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id<=?;');
		$stmt->execute($id);
	}
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . 'messages WHERE postdate<(?-60*(SELECT value FROM ' . PREFIX . "settings WHERE setting='messageexpire'))) AS t);");
	$stmt->execute([$time]);
	// delete expired ignored people
	$result=$db->query('SELECT id FROM ' . PREFIX . 'ignored WHERE ign NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions UNION SELECT nickname FROM ' . PREFIX . 'members UNION SELECT poster FROM ' . PREFIX . 'messages) OR ignby NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions UNION SELECT nickname FROM ' . PREFIX . 'members UNION SELECT poster FROM ' . PREFIX . 'messages);');
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE id=?;');
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute($tmp);
	}
	// delete files that do not belong to any message
	$result=$db->query('SELECT id FROM ' . PREFIX . 'files WHERE postid NOT IN (SELECT id FROM ' . PREFIX . 'messages UNION SELECT postid FROM ' . PREFIX . 'inbox);');
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'files WHERE id=?;');
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute($tmp);
	}
	// delete old notes
	$limit=get_setting('numnotes');
	// Modification for spare notes
	$db->exec('DELETE FROM ' . PREFIX . 'notes WHERE type!=2 AND id NOT IN (SELECT * FROM ( (SELECT id FROM ' . PREFIX . "notes WHERE type=0 ORDER BY id DESC LIMIT $limit) UNION (SELECT id FROM " . PREFIX . "notes WHERE type=3 ORDER BY id DESC LIMIT $limit)UNION (SELECT id FROM " . PREFIX . "notes WHERE type=1 ORDER BY id DESC LIMIT $limit) ) AS t);");
	$result=$db->query('SELECT editedby, COUNT(*) AS cnt FROM ' . PREFIX . "notes WHERE type=2 GROUP BY editedby HAVING cnt>$limit;");
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE type=2 AND editedby=? AND id NOT IN (SELECT * FROM (SELECT id FROM ' . PREFIX . "notes WHERE type=2 AND editedby=? ORDER BY id DESC LIMIT $limit) AS t);");
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute([$tmp[0], $tmp[0]]);
	}
	// delete old captchas
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'captcha WHERE time<(?-(SELECT value FROM ' . PREFIX . "settings WHERE setting='captchatime'));");
	$stmt->execute([$time]);

	// modification expire rooms
	$result=$db->query('SELECT DISTINCT roomid FROM ' . PREFIX . 'sessions where roomid is not null;');
	while($active=$result->fetch(PDO::FETCH_ASSOC)){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'rooms SET time=? WHERE id=?');
		$stmt->execute([$time, $active['roomid']]);
	}
	$expire = (int) get_setting('roomexpire')*60;
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'rooms WHERE time<=? AND permanent=0');
	$stmt->execute([$time-$expire]);
	if(!$rooms=$stmt->fetchAll(PDO::FETCH_ASSOC)){
		$rooms=[];
	}
	foreach($rooms as $room){
		remove_room(false, $room['id']);
	}

	// End modifications for rooms
}

function destroy_chat($C){
	global $I, $db, $memcached;
	setcookie(COOKIENAME, false);
	$_REQUEST['session']='';
	print_start('destory');
	$db->exec('DROP TABLE ' . PREFIX . 'captcha;');
	$db->exec('DROP TABLE ' . PREFIX . 'files;');
	$db->exec('DROP TABLE ' . PREFIX . 'filter;');
	$db->exec('DROP TABLE ' . PREFIX . 'ignored;');
	$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
	$db->exec('DROP TABLE ' . PREFIX . 'linkfilter;');
	$db->exec('DROP TABLE ' . PREFIX . 'members;');
	$db->exec('DROP TABLE ' . PREFIX . 'messages;');
	$db->exec('DROP TABLE ' . PREFIX . 'notes;');
	$db->exec('DROP TABLE ' . PREFIX . 'sessions;');
	$db->exec('DROP TABLE ' . PREFIX . 'settings;');
	if(MEMCACHED){
		$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
		$memcached->delete(DBANEM . '-' . PREFIX . 'linkfilter');
		foreach($C['settings'] as $setting){
			$memcached->delete(DBNAME . '-' . PREFIX . "settings-$setting");
		}
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-dbversion');
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-msgencrypted');
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-nextcron');
	}
	echo "<h2>$I[destroyed]</h2><br><br><br>";
	echo form('setup').submit($I['init']).'</form>'.credit();
	print_end();
}

function init_chat(){
	global $I, $db;
	$suwrite='';
	if(check_init()){
		$suwrite=$I['initdbexist'];
		$result=$db->query('SELECT null FROM ' . PREFIX . 'members WHERE status=8;');
		if($result->fetch(PDO::FETCH_NUM)){
			$suwrite=$I['initsuexist'];
		}
	}elseif(!preg_match('/^[a-z0-9]{1,20}$/i', $_REQUEST['sunick'])){
		$suwrite=sprintf($I['invalnick'], 20, '^[A-Za-z1-9]*$');
	}elseif(mb_strlen($_REQUEST['supass'])<5){
		$suwrite=sprintf($I['invalpass'], 5, '.*');
	}elseif($_REQUEST['supass']!==$_REQUEST['supassc']){
		$suwrite=$I['noconfirm'];
	}else{
		ignore_user_abort(true);
		set_time_limit(0);
		if(DBDRIVER===0){//MySQL
			$memengine=' ENGINE=MEMORY';
			$diskengine=' ENGINE=InnoDB';
			$charset=' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
			$primary='integer PRIMARY KEY AUTO_INCREMENT';
			$longtext='longtext';
		}elseif(DBDRIVER===1){//PostgreSQL
			$memengine='';
			$diskengine='';
			$charset='';
			$primary='serial PRIMARY KEY';
			$longtext='text';
		}else{//SQLite
			$memengine='';
			$diskengine='';
			$charset='';
			$primary='integer PRIMARY KEY';
			$longtext='text';
		}
		$db->exec('CREATE TABLE ' . PREFIX . "captcha (id $primary, time integer NOT NULL, code char(5) NOT NULL)$memengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "files (id $primary, postid integer NOT NULL UNIQUE, filename varchar(255) NOT NULL, hash char(40) NOT NULL, type varchar(255) NOT NULL, data $longtext NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'files_hash ON ' . PREFIX . 'files(hash);');
		$db->exec('CREATE TABLE ' . PREFIX . "filter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace text NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL, cs smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "ignored (id $primary, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'ign ON ' . PREFIX . 'ignored(ign);');
		$db->exec('CREATE INDEX ' . PREFIX . 'ignby ON ' . PREFIX . 'ignored(ignby);');
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL)$diskengine$charset;");
		
        //MODIFICATION clickable nicknames
        /*REMOVE LATER
		$db->exec('CREATE TABLE ' . PREFIX . "members (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash varchar(255) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL, clickablenicknames smallint NOT NULL DEFAULT 0)$diskengine$charset;");
		*/
		$db->exec('CREATE TABLE ' . PREFIX . "members (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash varchar(255) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$diskengine$charset;");
		
		
		$db->exec('ALTER TABLE ' . PREFIX . 'inbox ADD FOREIGN KEY (recipient) REFERENCES ' . PREFIX . 'members(nickname) ON DELETE CASCADE ON UPDATE CASCADE;');
		$db->exec('CREATE TABLE ' . PREFIX . "messages (id $primary, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL, delstatus smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'poster ON ' . PREFIX . 'messages (poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'recipient ON ' . PREFIX . 'messages(recipient);');
		$db->exec('CREATE INDEX ' . PREFIX . 'postdate ON ' . PREFIX . 'messages(postdate);');
		$db->exec('CREATE INDEX ' . PREFIX . 'poststatus ON ' . PREFIX . 'messages(poststatus);');
		$db->exec('CREATE TABLE ' . PREFIX . "notes (id $primary, type smallint NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_type ON ' . PREFIX . 'notes(type);');
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_editedby ON ' . PREFIX . 'notes(editedby);');
		
		//MODIFICATION clickable nicknames
		/* REMOVE LATER
		$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash varchar(255) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL, clickablenicknames smallint NOT NULL DEFAULT 0)$memengine$charset;");
		*/
		$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash varchar(255) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$memengine$charset;");
		
		$db->exec('CREATE INDEX ' . PREFIX . 'status ON ' . PREFIX . 'sessions(status);');
		$db->exec('CREATE INDEX ' . PREFIX . 'lastpost ON ' . PREFIX . 'sessions(lastpost);');
		$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
		$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) NOT NULL PRIMARY KEY, value text NOT NULL)$diskengine$charset;");

		// Modification for chat rooms
		$db->exec('CREATE TABLE ' . PREFIX . "rooms (id $primary, name varchar(50) NOT NULL UNIQUE, access smallint NOT NULL, time integer NOT NULL, permanent smallint NOT NULL DEFAULT(0))$diskengine$charset;");
		$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD COLUMN roomid integer;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages ADD COLUMN roomid integer;');
		$db->exec('CREATE INDEX ' . PREFIX . 'sroomid ON ' . PREFIX . 'sessions(roomid);');
		$db->exec('CREATE INDEX ' . PREFIX . 'mroomid ON ' . PREFIX . 'messages(roomid);');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages ADD COLUMN allrooms smallint NOT NULL DEFAULT(0);');


		$settings=[
			['guestaccess', '0'],
			['globalpass', ''],
			['englobalpass', '0'],
			['captcha', '0'],
			['dateformat', 'm-d H:i:s'],
			['rulestxt', ''],
			['msgencrypted', '0'],
			['dbversion', DBVERSION],
			['css', ''],
			['memberexpire', '60'],
			['guestexpire', '15'],
			['kickpenalty', '10'],
			['entrywait', '120'],
			['messageexpire', '14400'],
			['messagelimit', '150'],
			['maxmessage', 2000],
			['captchatime', '600'],
			['colbg', '000000'],
			['coltxt', 'FFFFFF'],
			['maxname', '20'],
			['minpass', '5'],
			['defaultrefresh', '20'],
			['dismemcaptcha', '0'],
			['suguests', '0'],
			['imgembed', '1'],
			['timestamps', '1'],
			['trackip', '0'],
			['captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'],
			['memkick', '1'],
			['forceredirect', '0'],
			['redirect', ''],
			['incognito', '1'],
			['chatname', 'My Chat'],
			['topic', ''],
			['msgsendall', $I['sendallmsg']],
			['msgsendmem', $I['sendmemmsg']],
			['msgsendmod', $I['sendmodmsg']],
			['msgsendadm', $I['sendadmmsg']],
			['msgsendprv', $I['sendprvmsg']],
			['msgenter', $I['entermsg']],
			['msgexit', $I['exitmsg']],
			['msgmemreg', $I['memregmsg']],
			['msgsureg', $I['suregmsg']],
			['msgkick', $I['kickmsg']],
			['msgmultikick', $I['multikickmsg']],
			['msgallkick', $I['allkickmsg']],
			['msgclean', $I['cleanmsg']],
			['numnotes', '3'],
			['mailsender', 'www-data <www-data@localhost>'],
			['mailreceiver', 'Webmaster <webmaster@localhost>'],
			['sendmail', '0'],
			['modfallback', '1'],
			['guestreg', '0'],
			['disablepm', '0'],
			['disabletext', "<h1>$I[disabledtext]</h1>"],
			['defaulttz', 'UTC'],
			['eninbox', '0'],
			['passregex', '.*'],
			['nickregex', '^[A-Za-z0-9]*$'],
			['externalcss', ''],
			['enablegreeting', '0'],
			['sortupdown', '0'],
			['hidechatters', '0'],
			['enfileupload', '0'],
			['msgattache', '%2$s [%1$s]'],
			['maxuploadsize', '1024'],
			['nextcron', '0'],
			['personalnotes', '1'],
			['filtermodkick', '0'],
			
			//MODIFICATION Text field for links in settings and option to enable or disable links page.
			['links',''],
			['linksenabled','0'],
			
			//MODIFICATION option to enable or disable DEL-Buttons for members, if no mod is present. (DEL Buttons can bes used to delete messages within the message frame)
			['memdel','0'],
			
			//MODIFICATION option to set galleryaccess for users depending on their rank(status).
			['galleryaccess', '10'],
			
			//MODIFICATION option to set forum button visibility for users depending on their rank(status).
			['forumbtnaccess', '10'],
			
			//MODIFICATION option to set link for the forum button 
			['forumbtnlink', 'forum/index.php'],
			
			//MODIFICATION frontpagetext (text for front page)
			['frontpagetext', ''],
			
			//MODIFICATION adminjoinleavemsg (admin join leave messages can be hidden)
			['adminjoinleavemsg', '1'],
			
			//MODIFICATION modsdeladminmsg (mods can delete admin messages)
			['modsdeladminmsg', '0'],
			
            //MODIFICATION clickablenicknamesglobal (nicknames at beginning of messages are clickable)
            ['clickablenicknamesglobal', '1'],

            //MODIFICATION spare notes.
            ['sparenotesname',''],
            ['sparenotesaccess', '10'],

            //MODIFICATION rooms
            ['roomcreateaccess', '7'],	
            ['roomexpire', '10'],
            ['channelvisinroom', '2']
		];
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES (?, ?);');
		foreach($settings as $pair){
			$stmt->execute($pair);
		}
		$reg=[
			'nickname'	=>$_REQUEST['sunick'],
			'passhash'	=>password_hash($_REQUEST['supass'], PASSWORD_DEFAULT),
			'status'	=>8,
			'refresh'	=>20,
			'bgcolour'	=>'000000',
			'timestamps'	=>1,
			'style'		=>'color:#FFFFFF;',
			'embed'		=>1,
			'incognito'	=>0,
			'nocache'	=>0,
			'nocache_old'	=>1,
			'tz'		=>'UTC',
			'eninbox'	=>0,
			'sortupdown'	=>0,
			'hidechatters'	=>0,
		];
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, timestamps, style, embed, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
		$suwrite=$I['susuccess'];
	}
	print_start('init');
	echo "<h2>$I[init]</h2><br><h3>$I[sulogin]</h3>$suwrite<br><br><br>";
	echo form('setup').submit($I['initgosetup']).'</form>'.credit();
	print_end();
}

function update_db(){
	global $I, $db, $memcached;
	$dbversion=(int) get_setting('dbversion');
	$msgencrypted=(bool) get_setting('msgencrypted');
	if($dbversion>=DBVERSION && $msgencrypted===MSGENCRYPTED){
		return;
	}
	ignore_user_abort(true);
	set_time_limit(0);
	if(DBDRIVER===0){//MySQL
		$memengine=' ENGINE=MEMORY';
		$diskengine=' ENGINE=InnoDB';
		$charset=' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
		$primary='integer PRIMARY KEY AUTO_INCREMENT';
		$longtext='longtext';
	}elseif(DBDRIVER===1){//PostgreSQL
		$memengine='';
		$diskengine='';
		$charset='';
		$primary='serial PRIMARY KEY';
		$longtext='text';
	}else{//SQLite
		$memengine='';
		$diskengine='';
		$charset='';
		$primary='integer PRIMARY KEY';
		$longtext='text';
	}
	$msg='';
	if($dbversion<2){
		$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . "ignored (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, ignored varchar(50) NOT NULL, `by` varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}
	if($dbversion<3){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('rulestxt', '');");
	}
	if($dbversion<4){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD incognito smallint NOT NULL;');
	}
	if($dbversion<5){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('globalpass', '');");
	}
	if($dbversion<6){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('dateformat', 'm-d H:i:s');");
	}
	if($dbversion<7){
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha ADD code char(5) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;');
	}
	if($dbversion<8){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('captcha', '0'), ('englobalpass', '0');");
		$ga=(int) get_setting('guestaccess');
		if($ga===-1){
			update_setting('guestaccess', 0);
			update_setting('englobalpass', 1);
		}elseif($ga===4){
			update_setting('guestaccess', 1);
			update_setting('englobalpass', 2);
		}
	}
	if($dbversion<9){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting,value) VALUES ('msgencrypted', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'settings MODIFY value varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages DROP postid;');
	}
	if($dbversion<10){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('css', ''), ('memberexpire', '60'), ('guestexpire', '15'), ('kickpenalty', '10'), ('entrywait', '120'), ('messageexpire', '14400'), ('messagelimit', '150'), ('maxmessage', 2000), ('captchatime', '600');");
	}
	if($dbversion<11){
		$db->exec('ALTER TABLE ' , PREFIX . 'captcha CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'filter CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'notes CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, `match` varchar(255) NOT NULL, `replace` varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD style varchar(255) NOT NULL;');
		$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET style=? WHERE id=?;');
		$F=load_fonts();
		while($temp=$result->fetch(PDO::FETCH_ASSOC)){
			$style="color:#$temp[colour];";
			if(isset($F[$temp['fontface']])){
				$style.=$F[$temp['fontface']];
			}
			if(strpos($temp['fonttags'], 'i')!==false){
				$style.='font-style:italic;';
			}
			if(strpos($temp['fonttags'], 'b')!==false){
				$style.='font-weight:bold;';
			}
			$stmt->execute([$style, $temp['id']]);
		}
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('colbg', '000000'), ('coltxt', 'FFFFFF'), ('maxname', '20'), ('minpass', '5'), ('defaultrefresh', '20'), ('dismemcaptcha', '0'), ('suguests', '0'), ('imgembed', '1'), ('timestamps', '1'), ('trackip', '0'), ('captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), ('memkick', '1'), ('forceredirect', '0'), ('redirect', ''), ('incognito', '1');");
	}
	if($dbversion<12){
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha MODIFY code char(5) NOT NULL, DROP INDEX id, ADD PRIMARY KEY (id) USING BTREE;');
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha ENGINE=MEMORY;');
		$db->exec('ALTER TABLE ' . PREFIX . 'filter MODIFY id integer unsigned NOT NULL AUTO_INCREMENT, MODIFY `match` varchar(255) NOT NULL, MODIFY replace varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored MODIFY ignored varchar(50) NOT NULL, MODIFY `by` varchar(50) NOT NULL, ADD INDEX(ignored), ADD INDEX(`by`);');
		$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter MODIFY match varchar(255) NOT NULL, MODIFY replace varchar(255) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages MODIFY poster varchar(50) NOT NULL, MODIFY recipient varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL, ADD INDEX(poster), ADD INDEX(recipient), ADD INDEX(postdate), ADD INDEX(poststatus);');
		$db->exec('ALTER TABLE ' . PREFIX . 'notes MODIFY type char(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY editedby varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings MODIFY id integer unsigned NOT NULL, MODIFY setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY value varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings DROP PRIMARY KEY, DROP id, ADD PRIMARY KEY(setting);');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('chatname', 'My Chat'), ('topic', ''), ('msgsendall', '$I[sendallmsg]'), ('msgsendmem', '$I[sendmemmsg]'), ('msgsendmod', '$I[sendmodmsg]'), ('msgsendadm', '$I[sendadmmsg]'), ('msgsendprv', '$I[sendprvmsg]'), ('numnotes', '3');");
	}
	if($dbversion<13){
		$db->exec('ALTER TABLE ' . PREFIX . 'filter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHANGE ignored ign varchar(50) NOT NULL, CHANGE `by` ignby varchar(50) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(255) NOT NULL;');
	}
	if($dbversion<14){
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'members');
			$memcached->delete(DBNAME . '-' . PREFIX . 'ignored');
		}
		if(DBDRIVER===0){//MySQL - previously had a wrong SQL syntax and the captcha table was not created.
			$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . 'captcha (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, time integer unsigned NOT NULL, code char(5) NOT NULL) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
		}
	}
	if($dbversion<15){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('mailsender', 'www-data <www-data@localhost>'), ('mailreceiver', 'Webmaster <webmaster@localhost>'), ('sendmail', '0'), ('modfallback', '1'), ('guestreg', '0');");
	}
	if($dbversion<17){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN nocache smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<18){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disablepm', '0');");
	}
	if($dbversion<19){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disabletext', '<h1>$I[disabledtext]</h1>');");
	}
	if($dbversion<20){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN tz smallint NOT NULL DEFAULT 0;');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('defaulttz', 'UTC');");
	}
	if($dbversion<21){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN eninbox smallint NOT NULL DEFAULT 0;');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('eninbox', '0');");
		if(DBDRIVER===0){
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, postid integer unsigned NOT NULL, postdate integer unsigned NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, INDEX(postid), INDEX(poster), INDEX(recipient)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
		}else{
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL);");
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_postid ON ' . PREFIX . 'inbox(postid);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		}
	}
	if($dbversion<23){
		$db->exec('DELETE FROM ' . PREFIX . "settings WHERE setting='enablejs';");
	}
	if($dbversion<25){
		$db->exec('DELETE FROM ' . PREFIX . "settings WHERE setting='keeplimit';");
	}
	if($dbversion<26){
		$db->exec('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES (\'passregex\', \'.*\'), (\'nickregex\', \'^[A-Za-z0-9]*$\');');
	}
	if($dbversion<27){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('externalcss', '');");
	}
	if($dbversion<28){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('enablegreeting', '0');");
	}
	if($dbversion<29){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('sortupdown', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN sortupdown smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<30){
		$db->exec('ALTER TABLE ' . PREFIX . 'filter ADD COLUMN cs smallint NOT NULL DEFAULT 0;');
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . "filter");
		}
	}
	if($dbversion<31){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('hidechatters', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN hidechatters smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<32 && DBDRIVER===0){
		//recreate db in utf8mb4
		try{
			$olddb=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
		}catch(PDOException $e){
			send_fatal_error($I['nodb']);
		}
		$db->exec('DROP TABLE ' . PREFIX . 'captcha;');
		$db->exec('CREATE TABLE ' . PREFIX . "captcha (id integer PRIMARY KEY AUTO_INCREMENT, time integer NOT NULL, code char(5) NOT NULL) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$result=$olddb->query('SELECT filtermatch, filterreplace, allowinpm, regex, kick, cs FROM ' . PREFIX . 'filter;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'filter;');
		$db->exec('CREATE TABLE ' . PREFIX . "filter (id integer PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace text NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL, cs smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES(?, ?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT ign, ignby FROM ' . PREFIX . 'ignored;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'ignored;');
		$db->exec('CREATE TABLE ' . PREFIX . "ignored (id integer PRIMARY KEY AUTO_INCREMENT, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'ignored (ign, ignby) VALUES(?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'ign ON ' . PREFIX . 'ignored(ign);');
		$db->exec('CREATE INDEX ' . PREFIX . 'ignby ON ' . PREFIX . 'ignored(ignby);');
		$result=$olddb->query('SELECT postdate, postid, poster, recipient, text FROM ' . PREFIX . 'inbox;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id integer PRIMARY KEY AUTO_INCREMENT, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$result=$olddb->query('SELECT filtermatch, filterreplace, regex FROM ' . PREFIX . 'linkfilter;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'linkfilter;');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES(?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters FROM ' . PREFIX . 'members;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'members;');
		$db->exec('CREATE TABLE ' . PREFIX . "members (id integer PRIMARY KEY AUTO_INCREMENT, nickname varchar(50) NOT NULL UNIQUE, passhash char(32) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz smallint NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT postdate, poststatus, poster, recipient, text, delstatus FROM ' . PREFIX . 'messages;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'messages;');
		$db->exec('CREATE TABLE ' . PREFIX . "messages (id integer PRIMARY KEY AUTO_INCREMENT, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL, delstatus smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'messages (postdate, poststatus, poster, recipient, text, delstatus) VALUES(?, ?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'poster ON ' . PREFIX . 'messages (poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'recipient ON ' . PREFIX . 'messages(recipient);');
		$db->exec('CREATE INDEX ' . PREFIX . 'postdate ON ' . PREFIX . 'messages(postdate);');
		$db->exec('CREATE INDEX ' . PREFIX . 'poststatus ON ' . PREFIX . 'messages(poststatus);');
		$result=$olddb->query('SELECT type, lastedited, editedby, text FROM ' . PREFIX . 'notes;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'notes;');
		$db->exec('CREATE TABLE ' . PREFIX . "notes (id integer PRIMARY KEY AUTO_INCREMENT, type char(5) NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES(?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT setting, value FROM ' . PREFIX . 'settings;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'settings;');
		$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) NOT NULL PRIMARY KEY, value text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES(?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
	}
	if($dbversion<33){
		$db->exec('CREATE TABLE ' . PREFIX . "files (id $primary, postid integer NOT NULL UNIQUE, filename varchar(255) NOT NULL, hash char(40) NOT NULL, type varchar(255) NOT NULL, data $longtext NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'files_hash ON ' . PREFIX . 'files(hash);');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('enfileupload', '0'), ('msgattache', '%2\$s [%1\$s]'), ('maxuploadsize', '1024');");
	}
	if($dbversion<34){
		$msg.="<br>$I[cssupdate]";
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN nocache_old smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<37){
		$db->exec('ALTER TABLE ' . PREFIX . 'members MODIFY tz varchar(255) NOT NULL;');
		$db->exec('UPDATE ' . PREFIX . "members SET tz='UTC';");
		$db->exec('UPDATE ' . PREFIX . "settings SET value='UTC' WHERE setting='defaulttz';");
	}
	if($dbversion<38){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('nextcron', '0');");
		$db->exec('DELETE FROM ' . PREFIX . 'inbox WHERE recipient NOT IN (SELECT nickname FROM ' . PREFIX . 'members);'); // delete inbox of members who deleted themselves
	}
	if($dbversion<39){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('personalnotes', '1');");
		$result=$db->query('SELECT type, id FROM ' . PREFIX . 'notes;');
		while($tmp=$result->fetch(PDO::FETCH_NUM)){
			if($tmp[0]==='admin'){
				$tmp[0]=0;
			}else{
				$tmp[0]=1;
			}
			$data[]=$tmp;
		}
		$db->exec('ALTER TABLE ' . PREFIX . 'notes MODIFY type smallint NOT NULL;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET type=? WHERE id=?;');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_type ON ' . PREFIX . 'notes(type);');
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_editedby ON ' . PREFIX . 'notes(editedby);');
	}
	if($dbversion<41){
		$db->exec('DROP TABLE ' . PREFIX . 'sessions;');
		$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash varchar(255) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$memengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'status ON ' . PREFIX . 'sessions(status);');
		$db->exec('CREATE INDEX ' . PREFIX . 'lastpost ON ' . PREFIX . 'sessions(lastpost);');
		$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
		$result=$db->query('SELECT nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, nocache_old, tz, eninbox, sortupdown, hidechatters FROM ' . PREFIX . 'members;');
		$members=$result->fetchAll(PDO::FETCH_NUM);
		$result=$db->query('SELECT postdate, postid, poster, recipient, text FROM ' . PREFIX . 'inbox;');
		$inbox=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
		$db->exec('DROP TABLE ' . PREFIX . 'members;');
		$db->exec('CREATE TABLE ' . PREFIX . "members (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash varchar(255) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, nocache_old smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL)$diskengine$charset");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, nocache_old, tz, eninbox, sortupdown, hidechatters) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($members as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?);');
		foreach($inbox as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$db->exec('ALTER TABLE ' . PREFIX . 'inbox ADD FOREIGN KEY (recipient) REFERENCES ' . PREFIX . 'members(nickname) ON DELETE CASCADE ON UPDATE CASCADE;');
	}
	if($dbversion<42){
		$db->exec('INSERT IGNORE INTO ' . PREFIX . "settings (setting, value) VALUES ('filtermodkick', '1');");
	}
	//MODIFICATION Text field for links in settings and option to enable or disable links page.
	if($dbversion<1142){
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('links', '');");
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('linksenabled', '0');");
	}
	//MODIFICATION option to enable or disable DEL-Buttons for members, if no mod is present. (DEL Buttons can bes used to delete messages within the message frame)
    if($dbversion<1242){
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('memdel', '0');");
    }
	
	//MODIFICATION clickable nicknames
	/* REMOVE LATER
	 if($dbversion<1243){
     $db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD COLUMN clickablenicknames smallint NOT NULL DEFAULT 0;');
     $db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN clickablenicknames smallint NOT NULL DEFAULT 0;');
    }
    */
	
	//MODIFICATION option to set galleryaccess and forum button visibility for users depending on their rank(status).
	if($dbversion<1342){
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('galleryaccess', '10');");
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('forumbtnaccess', '10');");
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('forumbtnlink', 'forum/index.php');");
    }
    //MODIFICATION fontpgagetext - Text field for text on front page of the chat.
	if($dbversion<1442){
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('frontpagetext', '');");
	}
	//MODIFICATION modsdeladminmsg - mods can delete admin messages. To be more precise: Staff members can delete messages of higher ranked staff members, bot only those messages that the lower ranked staff member can read (where status <= poststatus).
	if($dbversion<1542){
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('modsdeladminmsg', '0');");
	}
     //MODIFICATION adminjoinleavemsg to not create a system message if an admins arrives or leaves the chat
	if($dbversion<1642){
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('adminjoinleavemsg', '1');");
	}
    
    //MODIFICATION clickablenicknamesglobal (nicknames at beginning of messages are clickable)
	if($dbversion<1742){
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('clickablenicknamesglobal', '1');");
	}
	// Modification spare notes
	if($dbversion<2100){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('sparenotesaccess', '10');");
        $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('sparenotesname', '');");
	}
	// Modification for rooms
	if($dbversion<2101){
		$db->exec('INSERT IGNORE INTO ' . PREFIX . "settings (setting, value) VALUES ('roomcreateaccess', '7');");
		$db->exec('CREATE TABLE ' . PREFIX . "rooms (id $primary, name varchar(50) NOT NULL UNIQUE, access smallint NOT NULL, time integer NOT NULL)$diskengine$charset");
		$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD COLUMN roomid integer;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages ADD COLUMN roomid integer;');
		$db->exec('CREATE INDEX ' . PREFIX . 'sroomid ON ' . PREFIX . 'sessions(roomid);');
		$db->exec('CREATE INDEX ' . PREFIX . 'mroomid ON ' . PREFIX . 'messages(roomid);');
		$db->exec('INSERT IGNORE INTO ' . PREFIX . "settings (setting, value) VALUES ('roomexpire', '10');");
	}
	// Modification for rooms
	if($dbversion<2102){
		$db->exec('ALTER TABLE ' . PREFIX . 'rooms ADD COLUMN permanent smallint NOT NULL DEFAULT(0);');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages ADD COLUMN allrooms smallint NOT NULL DEFAULT(0);');
	}
	// Modification for rooms
	if($dbversion<2103){
		$db->exec('INSERT IGNORE INTO ' . PREFIX . "settings (setting, value) VALUES ('channelvisinroom', '2');");
	}
	update_setting('dbversion', DBVERSION);
	if($msgencrypted!==MSGENCRYPTED){
		if(!extension_loaded('sodium')){
			send_fatal_error($I['sodiumextrequired']);
		}
		$result=$db->query('SELECT id, text FROM ' . PREFIX . 'messages;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET text=? WHERE id=?;');
		while($message=$result->fetch(PDO::FETCH_ASSOC)){
			if(MSGENCRYPTED){
                $message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
			}else{
                $message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
			}
			$stmt->execute([$message['text'], $message['id']]);
		}
		$result=$db->query('SELECT id, text FROM ' . PREFIX . 'notes;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET text=? WHERE id=?;');
		while($message=$result->fetch(PDO::FETCH_ASSOC)){
			if(MSGENCRYPTED){
                $message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
			}else{
                $message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
			}
			$stmt->execute([$message['text'], $message['id']]);
		}
		update_setting('msgencrypted', (int) MSGENCRYPTED);
	}
	send_update($msg);
}

function get_setting($setting){
	global $db, $memcached;
	if(!MEMCACHED || !$value=$memcached->get(DBNAME . '-' . PREFIX . "settings-$setting")){
		$stmt=$db->prepare('SELECT value FROM ' . PREFIX . 'settings WHERE setting=?;');
		$stmt->execute([$setting]);
		$stmt->bindColumn(1, $value);
		$stmt->fetch(PDO::FETCH_BOUND);
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . "settings-$setting", $value);
		}
	}
	return $value;
}

function update_setting($setting, $value){
	global $db, $memcached;
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'settings SET value=? WHERE setting=?;');
	$stmt->execute([$value, $setting]);
	if(MEMCACHED){
		$memcached->set(DBNAME . '-' . PREFIX . "settings-$setting", $value);
	}
}

// configuration, defaults and internals

function check_db(){
	global $I, $db, $memcached;
	$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT];
	try{
		if(DBDRIVER===0){
			if(!extension_loaded('pdo_mysql')){
				send_fatal_error($I['pdo_mysqlextrequired']);
			}
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, $options);
		}elseif(DBDRIVER===1){
			if(!extension_loaded('pdo_pgsql')){
				send_fatal_error($I['pdo_pgsqlextrequired']);
			}
			$db=new PDO('pgsql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
		}else{
			if(!extension_loaded('pdo_sqlite')){
				send_fatal_error($I['pdo_sqliteextrequired']);
			}
			$db=new PDO('sqlite:' . SQLITEDBFILE, NULL, NULL, $options);
		}
	}catch(PDOException $e){
		try{
			//Attempt to create database
			if(DBDRIVER===0){
				$db=new PDO('mysql:host=' . DBHOST, DBUSER, DBPASS, $options);
				if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
					$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, $options);
				}else{
					send_fatal_error($I['nodbsetup']);
				}

			}elseif(DBDRIVER===1){
				$db=new PDO('pgsql:host=' . DBHOST, DBUSER, DBPASS, $options);
				if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
					$db=new PDO('pgsql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
				}else{
					send_fatal_error($I['nodbsetup']);
				}
			}else{
				if(isset($_REQUEST['action']) && $_REQUEST['action']==='setup'){
					send_fatal_error($I['nodbsetup']);
				}else{
					send_fatal_error($I['nodb']);
				}
			}
		}catch(PDOException $e){
			if(isset($_REQUEST['action']) && $_REQUEST['action']==='setup'){
				send_fatal_error($I['nodbsetup']);
			}else{
				send_fatal_error($I['nodb']);
			}
		}
	}
	if(MEMCACHED){
		if(!extension_loaded('memcached')){
			send_fatal_error($I['memcachedextrequired']);
		}
		$memcached=new Memcached();
		$memcached->addServer(MEMCACHEDHOST, MEMCACHEDPORT);
	}
	if(!isset($_REQUEST['action']) || $_REQUEST['action']==='setup'){
		if(!check_init()){
			send_init();
		}
		update_db();
	}elseif($_REQUEST['action']==='init'){
		init_chat();
	}
}

function load_fonts(){
	return [
		'Arial'			=>"font-family:'Arial','Helvetica','sans-serif';",
		'Book Antiqua'		=>"font-family:'Book Antiqua','MS Gothic';",
		'Comic'			=>"font-family:'Comic Sans MS','Papyrus';",
		'Courier'		=>"font-family:'Courier New','Courier','monospace';",
		'Cursive'		=>"font-family:'Cursive','Papyrus';",
		'Fantasy'		=>"font-family:'Fantasy','Futura','Papyrus';",
		'Garamond'		=>"font-family:'Garamond','Palatino','serif';",
		'Georgia'		=>"font-family:'Georgia','Times New Roman','Times','serif';",
		'Serif'			=>"font-family:'MS Serif','New York','serif';",
		'System'		=>"font-family:'System','Chicago','sans-serif';",
		'Times New Roman'	=>"font-family:'Times New Roman','Times','serif';",
		'Verdana'		=>"font-family:'Verdana','Geneva','Arial','Helvetica','sans-serif';",
	];
}

function load_lang(){
	global $I, $L, $language;
	$L=[
		'bg'	=>'Български',
		'cz'	=>'čeština',
		'de'	=>'Deutsch',
		'en'	=>'English',
		'es'	=>'Español',
		'fr'	=>'Français',
		'id'	=>'Bahasa Indonesia',
		'it'	=>'Italiano',
		'ru'	=>'Русский',
		'tr'	=>'Türkçe',
		'uk'	=>'Українська',
		'zh_CN'	=>'简体中文',
	];
	if(isset($_REQUEST['lang']) && isset($L[$_REQUEST['lang']])){
		$language=$_REQUEST['lang'];
		if(!isset($_COOKIE['language']) || $_COOKIE['language']!==$language){
			set_secure_cookie('language', $language);
		}
	}elseif(isset($_COOKIE['language']) && isset($L[$_COOKIE['language']])){
		$language=$_COOKIE['language'];
	}else{
		$language=LANG;
		set_secure_cookie('language', $language);
	}
	include('lang_en.php'); //always include English
	if($language!=='en'){
		$T=[];
		include("lang_$language.php"); //replace with translation if available
		foreach($T as $name=>$translation){
			$I[$name]=$translation;
		}
	}
}

function load_config(){
	mb_internal_encoding('UTF-8');
	define('VERSION', '2.2.2'); // Script version
	//See changelog
	
	define('DBVERSION', 10); // Database layout version
	//Paste other config below this line: 
 	define('MSGENCRYPTED', false); // Store messages encrypted in the database to prevent other database users from reading them - true/false - visit the setup page after editing!
	define('ENCRYPTKEY_PASS', 'MY_SECRET_KEY'); // Recommended length: 32. Encryption key for messages
	define('AES_IV_PASS', '012345678912'); // Recommended length: 12. AES Encryption IV
	define('DBHOST', 'dbhost'); // Database host
	define('DBUSER', 'dbuser'); // Database user
	define('DBPASS', 'dbpass'); // Database password
	define('DBNAME', 'dbname'); // Database
	define('PERSISTENT', true); // Use persistent database conection true/false
	define('PREFIX', ''); // Prefix - Set this to a unique value for every chat, if you have more than 1 chats on the same database or domain - use only alpha-numeric values (A-Z, a-z, 0-9, or _) other symbols might break the queries
	define('MEMCACHED', false); // Enable/disable memcached caching true/false - needs memcached extension and a memcached server.
	if(MEMCACHED){
		define('MEMCACHEDHOST', 'localhost'); // Memcached host
		define('MEMCACHEDPORT', '11211'); // Memcached port
	}
	define('DBDRIVER', 0); // Selects the database driver to use - 0=MySQL, 1=PostgreSQL, 2=sqlite
	if(DBDRIVER===2){
		define('SQLITEDBFILE', 'public_chat.sqlite'); // Filepath of the sqlite database, if sqlite is used - make sure it is writable for the webserver user
	}
	define('COOKIENAME', PREFIX . 'chat_session'); // Cookie name storing the session information
	define('LANG', 'en'); // Default language
    if (MSGENCRYPTED){
        if (version_compare(PHP_VERSION, '7.2.0') < 0) {
            die("You need at least PHP >= 7.2.x");
        }
        //Do not touch: Compute real keys needed by encryption functions
        if (strlen(ENCRYPTKEY_PASS) !== SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES){
            define('ENCRYPTKEY', substr(hash("sha512/256",ENCRYPTKEY_PASS),0, SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES));
        }else{
            define('ENCRYPTKEY', ENCRYPTKEY_PASS);
        }
        if (strlen(AES_IV_PASS) !== SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES){
            define('AES_IV', substr(hash("sha512/256",AES_IV_PASS), 0, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES));
        }else{
            define('AES_IV', AES_IV_PASS);
        }
    }
}
