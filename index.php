<?PHP
/*******************************************************************
 | 						Raha TinyCMS v3.6.0
 | 			------------------------------------------------
 |	@copyright: 		(C) 2006-2008 Raha Group, All Rights Reserved
 |	@license:		CC-BY-SA-4.0 <https://creativecommons.org/licenses/by-sa/4.0>
 |	@author: 		Mahdi NezaratiZadeh <HTTPS://Raha.Group>
 |	@since:			2006-03-01 00:00:00 GMT+0330 - 2008-06-06 18:57:33 GMT+0330
********************************************************************/
session_start();
!extension_loaded("zlib") or ob_start("ob_gzhandler");
error_reporting(E_ALL^E_NOTICE);
ini_set("display_errors", false);
ini_set("html_errors", false);
ini_set("error_reporting", E_ALL^E_NOTICE);
ini_set("max_execution_time", 180);
define("Raha", "3.6.0");
define("IP", IP());
define("Time", time());
define("MicroTime", microtime());
define("Now", gmdate("Y-m-d H:i:s"));
if (get_magic_quotes_gpc())
	$_POST = killmq($_POST);
$_POST = addmq($_POST);
if (file_exists("configuration.php"))
	require "configuration.php";
else {
	$parser = new XMLParser(file_get_contents("data.xml"));
	$tree = $parser->get_tree();
	eval($tree["data"]["script"]["value"]);
}
$DB = new DataBase($database_host, $database_username, $database_password, $database_name, $table_prefix);
if (config("site->cache"))
	header("Pragma: cache");
else {
	header("Pragma: no-cache");
	header("Cache-Control: no-cache");
}
header("Content-type: text/html; charset=".config("site->charset"));
$CFG = $pageTheme = array();
$mainScrip = $CPScript = "";
if (user("login")) {
	$id = $_SESSION["user id"] = cache("get", "CMS", "loged in");
	if (empty($_SESSION["last login"])) {
		$_SESSION["last login"] = user("last login");
		$_SESSION["last login ip"] = user("last login ip");
	}
	$DB->update($DB->user, array("last login ip" => IP, "last login" => Time), "id=$id");
	if (isset($_SESSION["last time"]))
		mysql_query("update $DB->user set `time online`=`time online`+'".(Time-$_SESSION["last time"])."' where id=$id");
	$_SESSION["last time"] = Time;
	$CP = new ControlPanel(user("level"));
	$CP->access = array("تنظيمات" => "configuration", "شکلک‌ها" => "emoticon", "اعلان" => "announcement", "کلمات ناپسند" => "bad word", "افراد اخراج شده" => "banned ip", "تگ‌ها" => "tag", "نظرسنجي" => "poll", "دفتر يادبود" => "guestbook", "پيوند" => "link", "وارد کردن پيوند" => "import link", "دسته بندي" => "category", "خبرنامه" => "newsletter", "پوسته" => "theme", "پلاگين" => "plugin", "کاربر" => "user", "مشخصات خود" => "profile", "تصاوير کاربران" => "user avatar", "سطح" => "level", "نويسنده" => "writer", "ويرايشگر" => "editor", "وارد کردن پست" => "import post", "نظرات" => "comment", "صندوق پيام" => "message", "پشتيبان پايگاه داده‌ها" => "backup", "شمارنده" => "counter", "وضعيت سايت" => "site state", "مردمي" => "demo");
}
define("logedIn", intval(isset($_SESSION["user id"]) ? $_SESSION["user id"] : 0));
$Jalali = new Jalali(config("site->date format"), config("site->time format"), config("site->time zone"));
$RQE = new Request();
$RQE->result();
$SC = new Cache;
function killmq($v) {
	return is_array($v) ? array_map("killmq", $v) : stripslashes($v);
}
function addmq($a) {
	foreach ($a as $k => $v)
		$a[$k] = is_array($v) ? addmq($v) : str_replace(array("ك", "ي"), array("ک", "ی"), addslashes($v));
	return $a;
}
function cache($action, $group, $key, $data = "") {
	global $cache;
	switch ($action) {
		case "set":
			$cache[$group][$key] = $data;
			break;
		case "get":
			return $cache[$group][$key];
	}
}
function url($q='') {
	return "http://$_SERVER[SERVER_NAME]$_SERVER[REQUEST_URI]".($q == '' ? '' : ($_SERVER['REQUEST_URI'] == '/' ? '?' : '&').$q);
}
function generation($precision=3) {
	$M = explode(" ", microtime());
	$m = explode(" ", MicroTime);
	return substr(number_format(($M[1]+$M[0])-($m[1]+$m[0]), $precision), 0, 5);
}
function mydate($t, $c=0) {
	return $c ? strtotime($t) : date("Y-m-d H:i:s", $t);
}
function my_date($m="now", $c=0) {
	global $Jalali;
	return $Jalali->date($Jalali->date_format, $c ? strtotime($m) : $m);
}
function my_time($m = "now", $c=0) {
	global $Jalali;
	return $Jalali->date($Jalali->time_format, $c ? strtotime($m) : $m);
}
function HTML($content, $action = "") {
	if ($action == "source")
		return htmlspecialchars(str_replace(array("<br />", "&nbsp;&nbsp;&nbsp;"), array("\n", "\t"), $content));
	elseif ($action == "convert")
		return str_replace(array("\r\n", "\r", "\n", "\t"), array("\n", "\n", "<br />", "&nbsp;&nbsp;&nbsp;"), htmlspecialchars($content));
	else {
		foreach ((array)config("tag") as $tag => $replace)
			$content = preg_replace($tag, $replace, $content);
		$content = strtr($content, (array)config("bad word"));
		empty($content) or $content = emoticons($content);
		$content = preg_replace(array("/&#([0-9]+);/e"), array('chr("\\1")'), $content);
		return preg_replace("/&#[Xx]([0-9A-Fa-f]+);/e", 'chr(hexdec("\\1"))', $content);
	}
}
function cookie($a, $e=null) {
	$c = config("cookie");
	$e = Time+(empty($e) ? $c["expires"] : $e);
	foreach ($a as $k => $v)
		setcookie($k, $v, $e, $c["path"], $c["domain"], $c["secure"]);
}
function compress($c, $n, $l=9) {
	if (strtolower(ini_get("zlib.output_compression")) == "on") {
		$r = "\x1f\x8b\x08\x00\x00\x00\x00\x00".substr(gzcompress($c, $l), 0, -4).pack("V", crc32($c)).pack("V", strlen($c));
		header('Content-Type: application/x-gzip; name="'.$n.'.gz"');
		header('Content-disposition: attachment; filename="'.$n.'.gz"');
	} else {
		$r = $c;
		header('Content-Type: text/x-delimtext; name="'.$n.'.txt"');
		header('Content-disposition: attachment; filename="'.$n.'.txt"');
	}
	header("Content-Length: ".strlen($r));
	return $r;
}
function IP() {
	$ip_address = empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["REMOTE_ADDR"] : $_SERVER["HTTP_X_FORWARDED_FOR"];
	strpos($ip_address, ",") === false or list($ip_address, ) = explode(",", $ip_address);
	return $ip_address;
}
function remote_fopen($uri) {
	if (ini_get("allow_url_fopen")) {
		$fp = fopen($uri, "r");
		if (!$fp)
			return false;
		$l = "";
		while ($r = fread($fp, 4194304))
			$l .= $r;
		fclose($fp);
		return $l;
	} elseif (function_exists("curl_init")) {
		$h = curl_init();
		curl_setopt($h, CURLOPT_URL, $uri);
		curl_setopt($h, CURLOPT_CONNECTTIMEOUT, 1);
		curl_setopt($h, CURLOPT_RETURNTRANSFER, 1);
		$b = curl_exec($h);
		curl_close($h);
		return $b;
	} else
		return false;
}
function bulk($w) {
	for ($s=array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"), $i=0; $w/1024>1; $i++)
		$w /= 1024;
	return round($w, 3)." ".$s[$i];
}
function xmlrpc($server) {
	$client = new IXR($server);
	$url = config("site->url");
	$title = config("site->title");
	return $client->query('weblogUpdates.extendedPing', $title, $url, $url.'/rss.xml', $url.'/atom.xml') ? "با موفقیت انجام شد (بهترین نوع)" : ($client->query('weblogUpdates.ping', $title, $url) ? "با موفقیت انجام شد" : "متاسفانه انجام نشد");
}
function emoticons($action = "print emoticons") {
	global $TPL;
	$data = config("emoticon->data");
	$path = config("emoticon->path");
	switch ($action) {
		case "print emoticons":
			foreach ($data["code"] as $k => $v)
				if ($data["state"][$k] == 1)
					$emoticons .= ' <a href="javascript:void(0)" onclick="javascript:insertCode(\''.str_replace('"', "&quot;", $v).'\');"><img src="'.$path.$data["url"][$k].'" border=0 title="'.$data["description"][$k].'" alt="'.$data["description"][$k].'"></a>';
				else
					$more = 1;
			if ($more)
				$emoticons .= $v=$TPL->get("emoticons") ? $v : '<br><a href="#" onclick="window.open(\'emoticon.html\', \'_blank\', \'width=300, height=300, status=yes, scrollbars=yes, resizable=yes\');">بيشتر</a>';
			return $emoticons;
		default:
			if (config("emoticon->convert") == 0)
				return $action;
			$a = array();
			foreach ($data["code"] as $k => $v)
				$a[$v] = '<img src="'.$path.$data["url"][$k].'" border=0 alt="'.$data["description"][$k].'" />';
			uksort($a, create_function('$a,$b', 'return strlen($b)-strlen($a);'));
			return strtr($action, $a);
	}
}
function configure($a) {
	if ($v=cache("get", "configuration", $a[0]))
		foreach ($a as $i => $m)
			$v = $i>0 ? $v[$m] : $v;
	else {
		global $DB;
		$q = mysql_query("select value from $DB->configuration where name='$a[0]'");
		if (mysql_num_rows($q)) {
			$v = mysql_result($q, 0);
			if (is_serialized($v)) {
				$v = unserialize($v);
				cache("set", "configuration", $a[0], $v);
				foreach ($a as $i => $m)
					$v = $i>0 ? $v[$m] : $v;
			}
		} else
			$v = 0;
	}
	return $v;
}
function set_cfg($n, $V) {
	global $DB;
	if (is_array($V))
		foreach ($V as $k => $v) {
			unset($V[$k]);
			$V[stripslashes($k)] = stripslashes($v);
		}
	$DB->update($DB->configuration, array("value" => $DB->escape(is_array($V) ? serialize($V) : $V)), "name='$n'");
}
function config($name, $value=null) {
	global $DB;
	$ID = explode("->", $name);
	if ($value == null) {
		if ($v=cache("get", "configuration", $name))
			return $v;
		$K = $ID[1];
		$v = user("login") && $ID[0] == "site" && ($K == "date format" || $K == "time format" || $K == "time zone" || $K == "theme") ? mysql_result(mysql_query("select `$K` from $DB->user where id=".logedIn), 0) : configure($ID);
		cache("set", "configuration", $name, $v);
		return $v;
	} elseif ($name == "delete") {
		$ID = explode("->", $value);
		if (count($ID) == 1) {
			mysql_query("delete from $DB->configuration where name='$value'");
			return "تنظيم $value حذف شد.";
		} else {
			foreach ($ID as $i => $k)
				$keys .= ($i != 0) ? "[\"$k\"]" : "";
			$var = str_replace(" ", "_", $ID[0]);
			$$var = config($ID[0]);
			eval('unset($'.$var.$keys.');');
			config($ID[0], $$var);
			return "آيتم $value حذف شد.";
		}
	} elseif ($name == "change")
		return preg_replace("/configuration\((.*?)\)/ise", 'config("$1");', $value);
	else {
		$value = (count($ID) == 1 && (is_array($value) || is_object($value) || is_serialized($value))) ? serialize($value) : $value;
		if (mysql_num_rows($q=mysql_query("select value from $DB->configuration where name='$ID[0]'"))) {
			if (count($ID)>1) {
				$values = unserialize(mysql_result($q, 0));
				foreach ($ID as $index => $key)
					$keys .= ($index > 0) ? "[\"$key\"]" : "";
				if (is_array($value) || is_object($value) || is_serialized($value)) {
					eval('$values'.$keys.' = array();');
					foreach ($value as $k => $v)
						$data .= '$values'.$keys."[\"$k\"] = '".$DB->escape($v)."';";
				} else {
					$data = '$values'.$keys.' = '."'".$DB->escape($value)."';";
				}
				eval($data);
				$value = serialize($values);
			} else
				$value = $DB->escape($value);
			mysql_query("update $DB->configuration set value='$value' where name='$ID[0]'");
			return "تنظيم $name به روزرساني شد";
		} else {
			if (count($ID)>1) {
				foreach ($ID as $i => $k)
					$K .= $i>0 ? "[\"$k\"]" : "";
				eval('$V'.$K.' = "'.addslashes($v).'";');
				$value = serialize($V);
			}
			mysql_query("insert into $DB->configuration values('$ID[0]', '".$DB->escape($value)."')");
			return "تنظيم $name اضافه شد";
		}
	}
}
function category($action, $id = "", $name = "") {
	global $DB;
	switch ($action) {
		case "output":
			$data = '<select class=select name="'.(empty($name) ? "category" : $name).'">';
			$q = mysql_query("select id,name,parent from $DB->category");
			while ($r=mysql_fetch_assoc($q))
				$cat[$r["parent"]][$r["id"]] = $r["name"];
			foreach ($cat[0] as $i => $n) {
				$data .= '<option value="'.$i.'"'.($id == $i ? "selected" : "").'>'.$n.'</option>';
				foreach ((array)$cat[$i] as $p => $n) {
					$data .= '<option value="'.$p.'"'.($id == $p ? "selected" : "").'>&nbsp;&nbsp;&nbsp;&nbsp;'.$n.'</option>';
					foreach ((array)$cat[$p] as $r => $n)
						$data .= '<option value="'.$r.'"'.($id == $r ? "selected" : "").'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$n.'</option>';
				}
			}
			$data .= "</select>";
			return $data;
		case "check moderator":
			$ID = mysql_result(mysql_query("select parent from $DB->category where id='$id'"), 0);
			if ($ID != 0) {
				$in .= ",$ID";
				$ID = mysql_result(mysql_query("select parent from $DB->category where id='$ID'"), 0);
				if ($ID != 0)
					$in .= ",$ID";
			}
			for ($q=mysql_query("select moderator from $DB->category where id in($id$in)"), $id=$name == "" && logedIn ? logedIn : "", $i=0; $i<mysql_num_rows($q); $i++)
				if (mysql_result($q, $i))
					foreach (explode("\r\n", mysql_result($q, $i)) as $user)
						if ($id == $user && $user)
							return 1;
			return 0;
		case "tree":
			for ($Q=mysql_query("select * from $DB->category order by `".config("category->order")."` ".config("category->sort")), $N=mysql_num_rows($Q), $i=0; $i<$N; $i++)
				$c[mysql_result($Q, $i)] = mysql_result($Q, $i, 3);
			for ($q=mysql_query("select category from $DB->post"), $n=mysql_num_rows($q), $i=0; $i<$n; $i++) {
				$C[mysql_result($q, $i)]++;
				$C[$c[mysql_result($q, $i)]]++;
				$C[$c[$c[mysql_result($q, $i)]]]++;
			}
			for ($i=0; $i<$N; $i++)
				$r .= 'd.add('.mysql_result($Q, $i).', '.mysql_result($Q, $i, 3).', "'.mysql_result($Q, $i, 1).'('.($C[mysql_result($Q, $i)] ? $C[mysql_result($Q, $i)] : 0).')", "category-'.mysql_result($Q, $i).'.html", '.js(mysql_result($Q, $i, 2)).', "", "", "", 0);';
			return "<script>d=new dTree('d');d.config.useSelection=false;d.config.useStatusText=true;d.config.inOrder=true;d.add(0, -1, 'دسته‌بندي', '', '', '', '', '', 0);".$r.'document.write(d);</script>';
		default:
			return mysql_result(mysql_query("select `$action` from $DB->category where id='$id'"), 0);
	}
}
function check($t, $c = "", $s = "") {
	global $DB;
	$r = 1;
	foreach (array_keys((array)config("bad word")) as $w)
		if (strstr($c, $w))
			$b .= "در اطلاعات وارد شده‌ي شما کلمه‌ي ناپسند <b>$w</b> وارد شده است<br>";
	if ($t == "author" && ($c == "" || $b))
		$r = $b."لطفا نام خود را وارد نماييد";
	elseif ($t == "content" && ($c == "" || $b))
		$r = $b."لطفا پيام خود را وارد نماييد";
	elseif ($t == "security code" && strtolower($c) != strtolower($_SESSION["security code"]))
		$r = !empty($c) ? "کد امنيتي نامعتبر است" : "لطفا کد امنيتي را وارد نماييد";
	elseif ($t == "subject" && ($c == "" || $b))
		$r = "لطفا عنوان را وارد نماييد";
	elseif ($t == "email" && !preg_match("/^[\.A-z0-9_\-]+[@][A-z0-9_\-]+([.][A-z0-9_\-]+)+[A-z]{1,4}$/", $c))
		$r = !empty($c) ? "آدرس ايميل نامعتبر است" : "لطفا ايميل خود را وارد نماييد";
	elseif ($t == "password" && $c == "")
		$r = "لطفا کلمه عبور را وارد نماييد";
	elseif ($t == "repassword" && $c == "")
		$r = "لطفا تکرار کلمه عبور را وارد نماييد";
	elseif ($t == "passwords" && (empty($c) || empty($s) || $c != $s))
		$r = empty($c) && empty($s) ? "لطفا قسمت کلمه‌هاي عبور را وارد نماييد" : "کلمه‌هاي عبور با هم مطابقت ندارند";
	elseif ($t == "username" || $b) {
		if ($c == "")
			$r = "لطفا نام کاربري را وارد نماييد";
		elseif (preg_match("/\s/", $c))
			$r = "لطفا فاصله‌هاي موجود در نام کاربري را حذف کنيد";
		elseif (strlen($c)>30)
			$r = "طول نام کاربري بيش از 30 کاراکتر نمي‌تواند باشد";
		elseif (!preg_match("/^[\.A-z0-9_\-]{3,30}$/", $c))
			$r = "نام کاربري نامعتبر است";
		elseif (mysql_result(mysql_query("select count(*) from $DB->user where username='$c'"), 0))
			$r = "نام کاربري $c قبلا ثبت شده است";
	} elseif ($t == "nickname" && mysql_result(mysql_query("select count(*) from $DB->user where ((nickname='$c' and nickname!='') or username='$c') and id!='$s'"), 0))
		$r = "نام مستعار انتخابي شما قبلا ثبت شده است";
	elseif ($t == "url" && ($b || $c == "" || $c == "http://"))
		$r = "لطفا آدرس سايت را وارد نماييد";
	elseif ($t == "title" && ($b || $c == ""))
		$r = "لطفا نام سايت را وارد نماييد";
	elseif ($t == "referer" && !strstr(str_replace("www.", "", $_SERVER["HTTP_REFERER"]), str_replace("www.", "", $_SERVER["HTTP_HOST"])))
		$r = 0;
	return $r;
}
function is_serialized($data) {
	return preg_match('/s:[0-9]+:.*;/', trim($data));
}
function form_value() {
	$a = $_COOKIE["saveCookie"] ? array(" checked", $_COOKIE["privateMessage"] ? " checked" : "", "", $_COOKIE["author"], $_COOKIE["email"], $_COOKIE["url"]) : (logedIn ? array("", "", " disabled", user("name"), user("email") , user("url")) : array("", "", "", "", "", "http://"));
	return array("saveCookie" => $a[0], "privateMessage" => $a[1], "disabled" => $a[2], "author" => $a[3], "email" => $a[4], "url" => $a[5]);
}
function comment($action, $id = "", $post_id = "") {
	global $DB, $TPL;
	switch ($action) {
		case "count":
			return $post_id == 1 || mysql_result(mysql_query("select `comment status` from $DB->post where id='$id'"), 0) == 1 ? mysql_result(mysql_query("select count(*) from $DB->comment where `post id`='$id' and status!=0"), 0) : -1;
		case "block":
			$postID = isset($_GET["comment"]) ? $_GET["comment"] : $_GET["post"];
			$q = mysql_query("select * from $DB->post where id='$postID'");
			$TPL->assign(array(
					"post title" => mysql_result($q, 0, 1),
					"post author" => user("name", mysql_result($q, 0, 3)),
					"post date" => my_date(mysql_result($q, 0, 5), 1),
					"post time" => my_time(mysql_result($q, 0, 5), 1),
					"count private" => mysql_result(mysql_query("select count(*) from $DB->comment where `post id`='$postID' and status=2"), 0)
				)
			);
			for ($q=mysql_query("select * from $DB->comment where status=1 and `post id`='$postID' order by `".config("comment->order")."` ".config("comment->sort")), $i=0; $i<mysql_num_rows($q); $i++)
				$TPL->block("comment", mysql_result($q, $i, 2) ? array("id" => mysql_result($q, $i), "author" => user("name", mysql_result($q, $i, 2)), "email" => user("email", mysql_result($q, $i, 2)), "url" => user("url", mysql_result($q, $i, 2)) ? array("url" => user("url", mysql_result($q, $i, 2))) : array(""), "content" => html(mysql_result($q, $i, 4)), "ip" => mysql_result($q, $i, 7), "date" => my_date(mysql_result($q, $i, 8)), "time" => my_time(mysql_result($q, $i, 8))) : array("id" => mysql_result($q, $i), "author" => mysql_result($q, $i, 3), "email" => mysql_result($q, $i, 5), "url" => mysql_result($q, $i, 6) ? array("url" => mysql_result($q, $i, 6)) : array(""), "content" => html(mysql_result($q, $i, 4)), "ip" => mysql_result($q, $i, 7), "date" => my_date(mysql_result($q, $i, 8)), "time" => my_time(mysql_result($q, $i, 8))));
			mysql_num_rows($q) or $TPL->block("comment");
			$v = form_value();
			$TPL->block("new comment", array(
					"author" => '<input class=input name=author value="'.$v["author"].'" lang=fa size=[value]'.$v["disabled"].'>',
					"email" => '<input class=input name=email dir=ltr value="'.$v["email"].'" size=[value]'.$v["disabled"].'>',
					"url" => '<input class=input name=url dir=ltr value="'.$v["url"].'" size=[value]'.$v["disabled"].'>',
					"content" => '<textarea class=textarea rows=10 cols=[value] name=content id=content '.(config("comment->html") ? 'editor=active' : 'lang=fa').'></textarea>',
					"submit" => '<input type=submit class=button value="[value]">',
					"reset" => '<input type=reset class=button value="[value]">',
					"private message" => '<input type=checkbox class=checkbox name=privateMessage id=privateMessage'.$v["privateMessage"].'><label for=privateMessage>[value]</label>',
					"result" => '<div id=commentResult></div>',
				), '<form method=post ajax=commentResult><input type=hidden name=do value=comment><input type=hidden name=postID value='.$postID.'>', '</form>'
			);
	}
}
function post($field, $id) {
	global $DB;
	return mysql_result(mysql_query("select `$field` from $DB->post where id='$id'"), 0);
}
function table_data($t, $f, $i) {
	global $DB;
	return mysql_result(mysql_query("select `$f` from $t where id='$i'"), 0);
}
function linkbox($name, $url, $description, $count, $view, $creator, $time, $type, $status) {
	global $DB;
	mysql_query("insert into $DB->link values('', '$name', '$url', '$description', '$creator', $time, '$count', '$view', $type, $status)");
	return ($type == 0 ? "پيوند" : ($type == 1 ? "پيوند روزانه" : "تبليغ"))." $name با موفقيت ارسال شد".($status == 1 ? "." : "، پس از تاييد مدير سايت به نمايش در مي‌آيد.");
}
function active_theme() {
	return isset($_GET["theme"]) ? $_GET["theme"] : (isset($_COOKIE["theme"]) ? $_COOKIE["theme"] : config("site->theme"));
}
function theme($action, $field = "", $value = "") {
	global $DB, $pageTheme;
	switch ($action) {
		case "add":
			!mysql_query("select `$field` from $DB->theme") or mysql_query("alter table $DB->theme add `$field` longtext not null");
			return "قالب $field اضافه شد.";
		case "delete":
			!mysql_query("select `$field` from $DB->theme") or mysql_query("alter table $DB->theme drop `$field`");
			return "قالب $field حذف شد.";
		case "page":
			$pageTheme[] = array($field, $value);
			break;
		case "print":
			if (isset($_GET["theme"])) {
				$id = $_GET["theme"];
				cookie(array("theme" => $id));
			} else
				$id = isset($_COOKIE["theme"]) ? $_COOKIE["theme"] : config("site->theme");
			if (mysql_num_rows($q = mysql_query("select `$field` from $DB->theme where id='$id'")))
				return mysql_result($q, 0);
			else {
				$r = mysql_fetch_assoc(mysql_query("select `$field` from $DB->theme"));
				return $r[$field];
			}
			break;
		case "data":
			$data = mysql_result(mysql_query("select style from $DB->theme where id='$field'"), 0);
			preg_match("|Name:(.*)|i", $data, $name);
			preg_match("|URL:(.*)|i", $data, $url);
			preg_match("|Description:(.*)|i", $data, $description);
			preg_match("|Date:(.*)|i", $data, $date);
			preg_match("|Version:(.*)|i", $data, $version);
			preg_match("|Author:(.*)|i", $data, $author);
			preg_match("|Author Email:(.*)|i", $data, $author_email);
			preg_match("|Author URL:(.*)|i", $data, $author_url);
			return array("name" => trim($name[1]), "url" => trim($url[1]), "description" => trim($description[1]), "date" => trim($date[1]), "version" => trim($version[1]), "author" => trim($author[1]), "author email" => trim($author_email[1]), "author url" => trim($author_url[1]));
	}
}
function counter($m) {
	global $TPL;
	$data = config("counter");
	$today = $data["today"];
	$yesterday = $data["yesterday"];
	$total = $data["total"];
	$update = $data["update"];
	$maxVisit = explode(" ", $data["max visit"]);
	$maxOnline = explode(" ", $data["max online"]);
	$onlines = array("explode" => $data["online"], "count" => count($data["online"]));
	$referrer = array("URL" => $data["referrer"], "explode" => !empty($data["referrer"]) ? explode("\n", $data["referrer"]) : array());
	$browser = $data["browser"];
	switch ($m) {
		case "block":
			$TPL->block("counter", array("today" => $today, "yesterday" => $yesterday, "total" => $total, "online" => $onlines["count"], "max visit" => array("count" => $maxVisit[0], "date" => my_date($maxVisit[1]), "time" => my_time($maxVisit[1])), "max online" => array("count" => $maxOnline[0], "date" => my_date($maxOnline[1]), "time" => my_time($maxOnline[1])), "referrer" => count($referrer["explode"])));
			break;
		case "detail":
			$r = '<div class="bc_center_title"><span style="color: #3d5262">» </span>آمار</div><div class="bc_center_tb"><table style="width:90%">';
			foreach (array("بازديد امروز" => $today, "بازديد ديروز" => $yesterday, "بازديد کل" => $total, "افراد آنلاين" => $onlines["count"], "بيشترين بازديد" => $maxVisit[0], "تاريخ بيشترين بازديد" => my_date($maxVisit[1])." در ساعت ".my_time($maxVisit[1]), "بيشترين افراد آنلاين" => $maxOnline[0], "تاريخ بيشترين افراد آنلاين" => my_date($maxOnline[1])." در ساعت ".my_time($maxOnline[1]), "تعداد معرف‌ها" => count($referrer["explode"])) as $n => $v)
				$r .= "<tr><td width=50%>$n</td><td width=50%>$v</td></tr>";
			$r .= '</table></div><br><div class="bc_center_title"><span style="color: #3d5262">» </span>مرورگر</div><div class="bc_center_tb"><table style="width:90%">';
			foreach (array("InternetExplorer" => $browser["internet explorer"], "FireFox" => $browser["fire fox"], "Opera" => $browser["opera"], "NetScape" => $browser["net scape"], "Gecko" => $browser["gecko"], "ديگر" => $browser["other"]) as $n => $v)
				$r .= "<tr><td width=50%>$n</td><td width=50%>$v</td></tr>";
			return $r.'</table></div><br><div class="bc_center_title" onclick="load(\'CP&page=manage&part=counter&action=referrer\', \'counter_referrer\')"><span style="color: #3d5262">» </span>معرف</div><div class=bc_center_tb dir=ltr align=left id=counter_referrer></div><br><div class="bc_center_title" onclick="load(\'CP&page=manage&part=counter&action=keyword\', \'counter_keyword\')"><span style="color: #3d5262">» </span>کلمه کليدي</div><div class=bc_center_tb align=right id=counter_keyword></div>';
		case "referrer":
			$referrerList = array();
			foreach ($referrer["explode"] as $site) {
				$site = preg_replace("|^([^:]+)://([^:/]+)(:[\d]+)*(.*)|", "\\1://\\2", $site);
				$name = str_replace(array("http://", "www."), "", $site);
				if (!array_key_exists($name, $referrerList)) {
					$referrerList[$name] = 1;
					$referrerListURL[$name] = $site;
				} else
					$referrerList[$name]++;
			}
			arsort($referrerList);
			foreach ($referrerList as $n => $c)
				$r .= '<a href="'.$referrerListURL[$n].'">'.$n.'</a> ('.$c.')<br />';
			return $r;
		case "keyword":
			$words = array();
			foreach ($referrer["explode"] as $url)
				if (preg_match("/([?&])(q|query)=(.*?)[&]/is", $url, $m))
					if (!array_key_exists($k=str_replace(array("<", ">"), array("&lt;", "&gt;"), $word = urldecode($m[3])), $words))
						$words[$k] = count(explode("=".urlencode($word), $referrer["URL"]));
			arsort($words);
			foreach ($words as $w => $c)
				$r .= "$w ($c)<br />";
			return $r;
		case "update":
			$timeOut = config("user->timeout");
			$agent = strtolower($_SERVER["HTTP_USER_AGENT"]);
			list($y, $m, $d) = explode("-", date("y-m-d", $update));
			list($Y, $M, $D) = explode("-", date("y-m-d"));
			if ($y == $Y && $m == $M && ($d == $D || $d+1 == $D))
				if ($d == $D)
					$today++;
				elseif ($d+1 == $D) {
					$yesterday = $today;
					$today = 1;
				}
			else {
				$today = 1;
				$yesterday = 0;
			}
			$total++;
			$maxVisit = ($maxVisit[0] <= $today) ? "$today ".Time : "$maxVisit[0] $maxVisit[1]";
			$onlines["explode"][IP] = Time;
			foreach ($onlines["explode"] as $ip => $date)
				if (Time-$date <= $timeOut)
					$onlines["data"][$ip] = $date;
			$maxOnline = $maxOnline[0] <= count($onlines["data"]) ? count($onlines["data"])." ".Time : "$maxOnline[0] $maxOnline[1]";
			if ($_SERVER["HTTP_REFERER"] && !preg_match("|http://".str_replace("www.", "", $_SERVER["HTTP_HOST"])."|is", str_replace("www.", "", $_SERVER["HTTP_REFERER"])))
				$referrer["URL"] .= ($referrer["URL"] ? "\n" : "").$_SERVER["HTTP_REFERER"];
			if (empty($_SESSION["counter"])) {
				$browser[strpos($agent, "msie") !== false ? "internet explorer" : (strpos($agent, "firefox") !== false ? "fire fox" : (strpos($agent, "opera") !== false ? "opera" : (strpos($agent, "netscape") !== false ? "net scape" : (strpos($agent, "gecko") !== false ? "gecko" : "other"))))]++;
				$_SESSION["counter"] = 1;
			}
			config("counter", array("today" => $today, "yesterday" => $yesterday, "total" => $total, "update" => Time, "max visit" => $maxVisit, "max online" => $maxOnline, "online" => $onlines["data"], "referrer" => $referrer["URL"], "browser" => $browser));
	}
}
function level($action, $id = "") {
	if ($action == "permission") {
		$permission = $id;
		foreach (config("level->access") as $id => $access)
			if (in_array($permission, $access) && !in_array("demo", $access)) {
				$level .= $comma.$id;
				$comma = ",";
			}
		return "level in($level)";
	} else {
		if ($action == "active" || $action == 1)
			return true;
		$id = $id == "" ? (logedIn ? user("level") : "") : $id;
		if ($v = cache("get", "level", "$id-$action"))
			return $v;
		$result = false;
		if (config("level->access->$id") != false)
			if (in_array($action, config("level->access->$id")))
				$result = true;
		cache("set", "level", "$id-$action", $result);
		return $result;
	}
}
function user($action, $id = "", $username = "", $password = "", $first_name = "", $last_name = "", $nickname = "", $birthday = "", $gender = "", $marriage = "", $eduction = "", $vocation = "", $amusement = "", $favorite = "", $about = "", $avatar = "", $signature = "", $location = "", $phone = "", $email = "", $url = "", $msn = "", $yahoo = "", $gmail = "", $aim = "", $icq = "", $visible = "", $date_format = "", $time_format = "", $time_zone = "", $start_of_week = "", $theme = 0, $level = "", $status = "", $send_email = 0) {
	global $DB;
	$id = ($id == "" && $action != "convert" && isset($_SESSION["user id"])) ? $_SESSION["user id"] : $id;
	if (($action == "add" || $action == "update") && !config("user->signature html"))
		$signature = html($signature, "convert");
	$url = $url == "http://" ? "" : $url;
	switch ($action) {
		case "add":
			($e = check("username", $username)) == 1 or $result .= "$e<br>";
			($e = check("passwords", $password[0], $password[1])) == 1 or $result .= "$e<br>";
			if (($error = check("email", $email)) != 1)
				$result .= "$error<br>";
			elseif (mysql_num_rows($q=mysql_query("select id from $DB->user where email='$email'")) == 1)
				$result .= "ايميل $email توسط کاربر ".user("name", mysql_result($q, 0))." ثبت شده است.<br>";
			($e = check("nickname", $nickname)) == 1 or $result .= "$e<br>";
			if (empty($result)) {
				mysql_query("insert into $DB->user values('', '".strtolower($username)."', '".md5($password[0])."', '$first_name', '$last_name', '$nickname', '$birthday', '$gender', '$marriage', '$eduction', '$vocation', '$amusement', '$favorite', '$about', '$avatar', '$signature', '$location', '$phone', '$email', '$url', '$aim', '$gmail', '$icq', '$msn', '$yahoo', '$visible', '$date_format', '$time_format', '$time_zone', '$start_of_week', '$theme', '".IP."', '".IP."', '".Time."', '".Time."', '', '$level', '$status')");
				$id = mysql_insert_id();
				if ($send_email == 1) {
					contact($email, str_replace("[site title]", config("site->title"), config("user->membership subject")), str_replace(array("[name]", "[security code url]", "[url]", "[title]", "[description]", "[id]"), array(user("name", $id), config("site->url")."?action=membership&id=$id&securityCode=".$status, config("site->url"), config("site->title"), config("site->description"), $id), config("user->membership message")), "", config("site->email"), config("site->url"));
					$result = "از عضويت شما ".user("name", $id)." عزيز متشکريم، يک نامه حاوي لينک فعال سازي به ايميل شما ارسال شد.";
				} else
					$result = "کاربر ".user("name", $id)." اضافه شد.";
				securityCode();
			}
			break;
		case "update":
			if (!empty($password[0])) {
				if (empty($password[2]))
					$result .= "شما براي تغيير رمز عبور بايد رمز عبور قبلي را وارد کنيد<br>";
				elseif (md5($password[2]) != user("password", $id))
					$result .= "رمز عبور قلبي وارد شده با رمز عبور فعلي متفاوت است<br>";
				($e = check("passwords", $password[0], $password[1])) == 1 or $result .= "$e<br>";
			}
			($e = check("nickname", $nickname, $id)) == 1 or $result .= "$e<br>";
			($e = check("email", $email)) == 1 or $result .= "$e<br>";
			if (empty($result)) {
				$password = !empty($password[2]) && md5($password[2]) == user("password", $id) && $password[0] == $password[1] ? "`password`='".md5($password[0])."'," : "";
				mysql_query("update $DB->user set $password `first name`='$first_name',`last name`='$last_name',nickname='$nickname',birthday='$birthday',gender='$gender',marriage='$marriage',eduction='$eduction',vocation='$vocation',amusement='$amusement',favorite='$favorite',about='$about',avatar='$avatar',signature='$signature',location='$location',phone='$phone',email='$email',url='$url',msn='$msn',yahoo='$yahoo',gmail='$gmail',aim='$aim',icq='$icq',visible='$visible',`date format`='$date_format',`time format`='$time_format',`time zone`='$time_zone',`start of week`='$start_of_week',theme='$theme',level='$level',status='$status' where id='$id'");
				$result = "کاربر ".user("name", $id)." بروز رساني شد";
			}
			break;
		case "delete":
			$name = user("name", $id);
			$DB->update($DB->user, array("status" => ""), "id='$id'");
			return "کاربر $name حذف شد.";
		case "name":
			$q = mysql_query("select username, nickname from $DB->user where id='$id'");
			return mysql_result($q, 0, mysql_result($q, 0, 1) ? 1 : 0);
		case "security code":
			$name = user("name", $id);
			return mysql_num_rows(mysql_query("select username from $DB->user where id='$id' and (status='$username' || status=1)"), 0) && mysql_query("update $DB->user set status=1 where id='$id'") ? "عضويت کاربر $name فعال شد." : "عضويت کاربر $name فعال نشد.";
		case "login":
			$v = cache("get", "CMS", "loged in");
			if (isset($v))
				return $v != false ? true : false;
			$r = mysql_num_rows($q = mysql_query("select id from $DB->user where ((username='$_SESSION[username]' and password='$_SESSION[password]') or (username='$_COOKIE[username]' and password='$_COOKIE[password]')) and status=1")) == 1 ? mysql_result($q, 0) : 0;
			cache("set", "CMS", "loged in", $r);
			return $r;
		case "convert":
			$field = $id;
			$converter = $username;
			$content = $password;
			$parser = $first_name;
			if ($content == "")
				return;
			if ($parser != "") {
				foreach (explode($parser, $content) as $index => $value) {
					$q = mysql_query("select `$converter` from $DB->user where `$field`='$value'");
					$newContent .= ($index != 0 ? $parser : "").(mysql_num_rows($q) != 0 ? mysql_result($q, 0) : $value);
				}
			} else {
				$q = mysql_query("select `$converter` from $DB->user where `$field`='$content'");
				$newContent = mysql_num_rows($q) ? mysql_result($q, 0) : $content;
			}
			return $newContent;
		default:
			$q = mysql_query("select `$action` from $DB->user where id='$id'");
			return mysql_num_rows($q) ? mysql_result($q, 0) : "";
	}
	return $result;
}
function status($table, $id, $change = 0, $print = 1) {
	global $DB;
	$r = mysql_result(mysql_query("select status from $table where id='$id'"), 0);
	if ($change) {
		mysql_query("update `$table` set `status`='".($r == 1 ? ($table == $DB->comment ? 2 : 0) : 1)."' where id='$id'");
		if ($print)
			return "آيتم مورد نظر ".($r ? ($table == $DB->comment ? "خصوصي" : "غير فعال") : "فعال")." شد.";
	}
	return $r == 1 ? "فعال" : ($r == 2 ? "خصوصي" : "غير فعال");
}
function gdversion() {
	ob_start();
	phpinfo(8);
	return preg_match("/\bgd\s+version\b[^\d\n\r]+?([\d\.]+)/i", ob_get_clean(), $m) ? $m[1] : 0;
}
function dirsize($directory) {
	if (!is_dir($directory))
		return -1;
	$size = 0;
	if ($DIR = opendir($directory)) {
		while (($dirfile = readdir($DIR)) !== false) {
			if (@is_link($directory.'/'.$dirfile) || $dirfile == '.' || $dirfile == '..')
				continue;
			if (@is_file($directory.'/'.$dirfile))
				$size += filesize($directory.'/'.$dirfile);
			else if (@is_dir($directory.'/'.$dirfile)) {
				$dirSize = dirsize($directory.'/'.$dirfile);
				if ($dirSize >= 0)
					$size += $dirSize;
				else
					return -1;
			}
		}
		closedir($DIR);
	}
	return $size;
}
function encrypt($c) {
	return trim(str_replace("/(\r\n|\n)+/", "\n", $c));
}
function security_code($width=50, $height=20) {
	$im = imagecreate($width, $height);
	$db = config("security code");
	$BGC = dehexize($db["background color"]);
	$BC = dehexize($db["border color"]);
	$FGC = dehexize($db["foreground color"]);
	$LC = dehexize($db["line color"]);
	imagecolorallocate($im, $BGC[0], $BGC[1], $BGC[2]);
	$FGC = imagecolorallocate($im, $FGC[0], $FGC[1], $FGC[2]);
	$LC = imagecolorallocate($im, $LC[0], $LC[1], $LC[2]);
	for ($i=0; $i<$db["line number"]; $i++)
		imageline($im, $i, rand(15, 50), ($i+1)*20, $i, $LC);
	imagestring($im, 5, 4, 1, $_SESSION["security code"], $FGC);
	imagepng($im);
	header("Content-type: image/png");
	imagedestroy($im);
}
function securityCode($s=1) {
	$c = strtoupper(substr(md5(rand(0, 99999)), 0, 5));
	!$s or $_SESSION["security code"] = $c;
	return $c;
}
function logo($width=80, $height=15) {
	global $SC;
	header("Content-type: image/png");
	if ($SC->time("logo"))
		$im = imagecreatefrompng("_/logo");
	else {
		$im = imagecreate($width, $height);
		$db = config("logo");
		$bgc = dehexize($db["background color"]);
		$lc = dehexize($db["line color"]);
		$bc = dehexize($db["border color"]);
		$fgc = dehexize($db["foreground color"]);
		$ic = dehexize($db["icon foreground color"]);
		$ibc = dehexize($db["icon background color"]);
		imagecolorallocate($im, $bgc[0], $bgc[1], $bgc[2]);
		$fgc = imagecolorallocate($im, $fgc[0], $fgc[1], $fgc[2]);
		$bc = imagecolorallocate($im, $bc[0], $bc[1], $bc[2]);
		$lc = imagecolorallocate($im, $lc[0], $lc[1], $lc[2]);
		$ic = imagecolorallocate($im, $ic[0], $ic[1], $ic[2]);
		$ibc = imagecolorallocate($im, $ibc[0], $ibc[1], $ibc[2]);
		imagefilledrectangle($im, 2, 2, 24, 12, $ibc);
		imageline($im, 25, 2, 25, 12, $lc);
		imagerectangle($im, 0, 0, $width-1, $height-1, $bc);
		imagerectangle($im, 1, 1, $width-2, $height-2, $lc);
		imagestring($im, 3, 7, 0, $db["icon content"], $ic);
		imagestring($im, 1, 27, 4, $db["site name"], $fgc);
		imagepng($im, "_/logo");
	}
	imagepng($im);
}
function compute($second, $num, $num2) {
	return floor(($second/$num)%$num2);
}
function time_parser($t) {
	return array("year" => ($v=compute($t, 31536000, 100))>0 ? array("year" => $v) : array(""), "month" => ($v=compute($t, 2592000, 12))>0 ? array("month" => $v) : array(""), "week" => ($v=compute($t, 604800, 5))>0 ? array("week" => $v) : array(""), "day" => ($v=compute($t, 86400, 7))>0 ? array("day" => $v) : array(""), "hour" => ($v=compute($t, 3600, 24))>0 ? array("hour" => $v) : array(""), "minute" => ($v=compute($t, 60, 60))>0 ? array("minute" => $v) : array(""), "second" => ($v=compute($t, 1, 60))>0 ? array("second" => $v) : array(""));
}
function element($t, $P = array(), $V = "") {
	foreach ((array)$P as $p => $v)
		$a .= " ".$p.'="'.str_replace('"', '&quot;', $v).'"';
	switch ($t) {
		case "text":
		case "password":
			return "<input type=$t class=input".(empty($V) ? "" : ' value="'.str_replace('"', '&quot;', $V).'"')."$a>";
		case "button":
		case "submit":
			return "<input type=$t class=button".(empty($V) ? "" : ' value="'.$V.'"')."$a>";
		case "checkbox":
		case "radio":
			return "<input type=$t class=$t id=$P[name]$a $V><lable for=$P[name]></label>";
		case "select":
			$r = "<$t class=$t$a>";
			foreach ($V as $v => $n)
				$r .= '<option value="'.$v.'"'.($v == $P["value"] ? " selected" : "").">$n</option>";
			return "$r</$t>";
		case "textarea":
			return "<$t class=$t$a>".htmlspecialchars($V)."</$t>";
		case "sort":
			return element("select", array("name" => $P, "value" => $V), array("asc" => "صعودي", "desc" => "نزولي"));
		case "status":
			return element("select", array("name" => $P, "value" => $V), array(1 => "فعال", 0 => "غيرفعال"));
		case "table":
			$r = "<$t $a>";
			foreach ($V as $f => $d)
				$r .= "<tr><td width=50%>$f</td><td width=50%>$d</td></tr>";
			return "$r</$t>";
		case "line":
			return "<hr class=$t$a>";
		case "part":
			return '<div class="bc_center_title"><span style="color: #3d5262">» </span>'.$P["title"].'</div><div class="bc_center_tb"'.(isset($P["align"]) ? "align=$P[align]" : "").'>'.$V.'</div><br>';
	}
}
function server_parse($socket, $response, $line = __LINE__) {
	while (substr($server_response, 3, 1) != ' ')
		$server_response = fgets($socket, 256) or die("Couldn't get mail server response codes".$line. __FILE__);
	substr($server_response, 0, 3) == $response or die("Ran into problems sending Mail. Response: $server_response".$line. __FILE__);
}
// Replacement or substitute for PHP's mail command
function smtpmail($mail_to, $SenderMail, $subject, $message, $headers="") {
	$config = config("smtp");
	// Fix any bare linefeeds in the message to make it RFC821 Compliant.
	$message = preg_replace("#(?<!\r)\n#si", " ", $message);
	if ($headers != "") {
		if (is_array($headers))
			$headers = sizeof($headers)>1 ? join("\n", $headers) : $headers[0];
		$headers = chop($headers);
		// Make sure there are no bare linefeeds in the headers
		$headers = preg_replace("#(?<!\r)\n#si", " ", $headers);
		// Ok this is rather confusing all things considered, 
		// but we have to grab bcc and cc headers and treat them differently 
		// Something we really didn't take into consideration originally 
		$header_array = explode(" ", $headers);
		@reset($header_array);
		$headers = ""; 
		while (list(, $header) = each($header_array)) {
			if (preg_match("#^cc:#si", $header))
				$cc = preg_replace("#^cc:(.*)#si", "\1", $header);
			else if (preg_match("#^bcc:#si", $header)) {
				$bcc = preg_replace("#^bcc:(.*)#si", "\1", $header);
				$header = "";
			}
			$headers .= ($header != "") ? $header." " : "";
		}
		$headers = chop($headers);
		$cc = "";//explode(", ", $cc);
		$bcc = "";//explode(", ", $bcc);
	}
	trim($subject) != "" or die("No email Subject specified". __LINE__ . __FILE__);
	trim($message) != "" or die("Email message was blank". __LINE__ . __FILE__);
	// Ok we have error checked as much as we can to this point let's get on
	// it already.
	$socket = @fsockopen($config["host"], 25, $errno, $errstr, 20) or die("Could not connect to smtp host : $errno : $errstr". __LINE__ . __FILE__);
	// Wait for reply 
	server_parse($socket, "220", __LINE__);
	// Do we want to use AUTH?, send RFC2554 EHLO, else send RFC821 HELO
	// This improved as provided by SirSir to accomodate
	if (!empty($config["username"]) && !empty($config["password"])) {
		fputs($socket, "EHLO $config[host] ");
		server_parse($socket, "250", __LINE__);
		fputs($socket, "AUTH LOGIN ");
		server_parse($socket, "334", __LINE__);
		fputs($socket, base64_encode($config["username"])." ");
		server_parse($socket, "334", __LINE__);
		fputs($socket, base64_encode($config["password"])." ");
		server_parse($socket, "235", __LINE__);
	} else {
		fputs($socket, "HELO $config[host] ");
		server_parse($socket, "250", __LINE__);
	}
	// From this point onward most server response codes should be 250
	// Specify who the mail is from....
	fputs($socket, "MAIL FROM: <".$SenderMail."> ");
	server_parse($socket, "250", __LINE__);
	// Specify each user to send to and build to header.
	$to_header = "";
	// Add an additional bit of error checking to the To field.
	$mail_to = trim($mail_to) == "" ? "Undisclosed-recipients:;" : trim($mail_to);
	if (preg_match("#[^ ]+\@[^ ]+#", $mail_to)) {
		fputs($socket, "RCPT TO: <$mail_to> ");
		server_parse($socket, "250", __LINE__);
	}
	// Ok now we tell the server we are ready to start sending data
	fputs($socket, "DATA ");
	// This is the last response code we look for until the end of the message.
	server_parse($socket, "354", __LINE__);
	// Send the Subject Line...
	fputs($socket, "Subject: $subject ");
	// Now the To Header.
	fputs($socket, "To: $mail_to ");
	// Now any custom headers....
	fputs($socket, "$headers ");
	// Ok now we are ready for the message...
	fputs($socket, "$message ");
	// Ok the all the ingredients are mixed in let's cook this puppy...
	fputs($socket, ". ");
	server_parse($socket, "250", __LINE__);
	// Now tell the server we are done and close the socket...
	fputs($socket, "QUIT ");
	fclose($socket);
	return true;
}
function contact($mail_to, $subject, $message, $headers = "", $SenderMail = "", $site = "") {
	$headers or $headers = "From: $SenderMail<$SenderMail>\nReply-To: $SenderMail\nWeb-Site: $site\nMIME-Version: 1.0\nContent-type: text/html; charset=".config("site->charset");
	return config("smtp->status") ? smtpmail($mail_to, $SenderMail, $subject, $message, $headers) : mail($mail_to, $subject, $message, $headers);
}
function excerptLength($c) {
	$l = config("site->excerpt length");
	$C = strip_tags($c);
	return strlen($C) <= $l ? $c : substr($C, 0, $l-1)."…";
}
function newsletter($type, $subject, $id, $title, $content, $date = 0) {
	if (empty($subject))
		$subject = str_replace("[site title]", config("site->title"), config("newsletter->subject"));
	$subject = str_replace("[post title]", $type == "post" ? $title : "", $subject);
	$n = user("name", logedIn);
	$t = config("newsletter->template");
	$C = config("site");
	$c = excerptLength($content);
	$emails = explode(",", config("newsletter->emails"));
	$search = array("[name]", "[email]", "[url]", "[title]", "[description]", "[post title]", "[post content]", "[post author]", "[post date]", "[post time]", "[post url]");
	foreach ($emails as $email) {
		$name = explode("@", $email);
		contact($email, $subject, $type == "post" ? str_replace($search, array($name[0], $email, $C["url"], $C["title"], $C["description"], $title, $c, $n, my_date($date, 1), my_time($date, 1), $C["url"]."/post-$id.html"), $t) : $id, "", $C["email"], $C["url"]);
	}
	return "پيام $subject با موفقيت براي اعضاي خبرنامه ارسال شد.";
}
function lock($t, $s) {
	$s = trim($s);
	switch ($t) {
		case "js":
			return CompressJavascript($s);
		case "css":
			return preg_replace(array('#/\*.*?\*/|\n|\r|\t|\f#is', '#\s*(\{|\}|\(|:|,|;)\s*#is'), array('', '$1'), $s);
		case "html":
			return "<!--\n|\tThis Program has written By MAHDI NEZARATI ZADEH\n|\tWEB : HTTPS://Raha.Group\n-->\n".str_replace("\r", "", $s)."\n".'<span style="color: #c0c0c0; font-size: 8pt">Programmed By <a href="//raha.group" style="color: #c0c0c0; font-size: 8pt" target="_blank">Raha.Group</a></span>'."\n<!-- Powered By WWW.Raha.Group -->";
	}
}
function CompressJavascript($S) {
	//remove windows cariage returns
	$S = str_replace("\r", "", $S);
	//array to store replaced literal strings
	$literal_strings = array();
	//explode the string into lines
	$lines = explode("\n", $S);
	//loop through all the lines, building a new string at the same time as removing literal strings
	$clean = "";
	$inComment = false;
	$literal = "";
	$inQuote = false;
	$escaped = false;
	$quoteChar = "";
	for ($i=0; $i<count($lines); $i++) {
		$line = $lines[$i];
		$inNormalComment = false;
		//loop through line's characters and take out any literal strings, replace them with ___i___ where i is the index of this string
		for ($j=0; $j<strlen($line); $j++) {
			$c = substr($line, $j, 1);
			$d = substr($line, $j, 2);
			//look for start of quote
			if (!$inQuote && !$inComment) {
				//is this character a quote or a comment
				if (($c == '"' || $c == "'") && !$inComment && !$inNormalComment) {
					$inQuote = true;
					$inComment = false;
					$escaped = false;
					$quoteChar = $c;
					$literal = $c;
				} elseif ($d == "/*" && !$inNormalComment) {
					$inQuote = false;
					$inComment = true;
					$escaped = false;
					$quoteChar = $d;
					$literal = $d;
					$j++;
				} elseif ($d == "//") { //ignore string markers that are found inside comments
					$inNormalComment = true;
					$clean .= $c;
				} else {
					$clean .= $c;
				}
			} else { //allready in a string so find end quote
				if ($c == $quoteChar && !$escaped && !$inComment) {
					$inQuote = false;
					$literal .= $c;
					//subsitute in a marker for the string
					$clean .= "___".count($literal_strings)."___";
					//push the string onto our array
					array_push($literal_strings, $literal);
				} elseif ($inComment && $d == "*/") {
					$inComment = false;
					$literal .= $d;
					//subsitute in a marker for the string
					$clean .= "___".count($literal_strings)."___";
					//push the string onto our array
					array_push($literal_strings, $literal);
					$j++;
				} elseif ($c == "\\" && !$escaped)
					$escaped = true;
				else
					$escaped = false;
				$literal .= $c;
			}
		}
		if ($inComment)
			$literal .= "\n";
		$clean .= "\n";
	}
	//explode the clean string into lines again
	$lines = explode("\n", $clean);
	//now process each line at a time
	for ($i=0; $i<count($lines); $i++) {
		$line = $lines[$i];
		//remove comments
		$line = preg_replace("/\/\/(.*)/", "", $line);
		//strip leading and trailing whitespace
		$line = trim($line);
		//remove all whitespace with a single space
		$line = preg_replace("/\s+/", " ", $line);
		//remove any whitespace that occurs after/before an operator
		$line = preg_replace("/\s*([!\}\{;,&=\|\-\+\*\/\)\(:])\s*/", "\\1", $line);
		$lines[$i] = $line;
	}
	//implode the lines
	$S = implode("\n", $lines);
	//make sure there is a max of 1 \n after each line
	$S = preg_replace("/[\n]+/", "\n", $S);
	//strip out line breaks that immediately follow a semi-colon
	$S = preg_replace("/;\n/", ";", $S);
	//curly brackets aren't on their own
	$S = preg_replace("/[\n]*\{[\n]*/", "{", $S);
	//finally loop through and replace all the literal strings:
	for ($i=0; $i<count($literal_strings); $i++)
		$S = str_replace("___".$i."___", $literal_strings[$i], $S);
	return $S;
}
function images($url) {
	$dh = opendir("./$url");
	while ($file = readdir($dh))
		preg_match( "/^..?$|^index|htm$|html$|Thumbs.db$|^\./i", $file) or $images[] = $file;
	closedir($dh);
	sort($images);
	reset($images);
	return $images;
}
function dehexize($c) {
	for ($c=strtolower($c), $h=array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, "a", "b", "c", "d", "e", "f"), $i=1; $i<7; $i++)
		for ($j=0; $j<16; $j++)
			if ($c[$i] == $h[$j])
				$i%2 ? $C[($i-1)/2] = $j*16 : $C[($i-1)/2] += $j;
	return $C;
}
function title($t, $s = "&raquo;") {
	$a = array("category" => '"بخش ".category("name", $_GET[$k])', "archive" => '"آرشيو"', "search" => '"نتايج جستجوي ".$_GET[$k]', "author" => '"نويسنده ".user("name", $_GET[$k])', "page" => '"صفحه ".$_GET[$k]', "post" => '"مطلب ".post("title", $_GET[$k])');
	foreach ($a as $k => $v)
		if (!empty($_GET[$k]))
			eval("\$t .= ' $s '.$v;");
	return $t;
}
function user_data($user, $author, $email, $url) {
	global $DB;
	return $detail = $user != 0 && mysql_fetch_array(mysql_query("select username,nickname,email,url from $DB->user where id='$user'"), MYSQL_NUM) ? array($d[1] ? $d[1] : $d[0], $d[2], $d[3]) : array($author, $email, $url);
}
function user_link($user, $author, $email, $url) {
	$detail = user_data($user, $author, $email, $url);
	return $detail[2] ? array($detail[0], $detail[2], $detail[1]) : array($detail[0], "mailto:".$detail[1]);
}
function js($C) {
	for ($a=array("\n" => '\n', "\r" => '\r', "\t" => '\t', '"' => '\"', '\\' => '\\\\'), $l=strlen($C), $r='', $i=0; $c=$C[$i], $i<$l; $i++)
		$r .= isset($a[$c]) ? $a[$c] : $c;
	return '"'.$r.'"';
}
function search($e, $f = "content") {
	for ($e=" ".trim($e), $i=0, $l=strlen($e); $i<$l; $i++) {
		$c = $e[$i];
		if (!$q && $c == " ") {
			$w .= ($s || $p ? "%'" : "").") or ($f like '%";
			$s = 1;
			$p = 0;
		} elseif (!$q && $c == "+") {
			$w .= ($s || $p ? "%'" : "")." and $f like '%";
			$s = 0;
			$p = 1;
		} elseif ($c == '"')
			if (!$q) {
				$q = 1;
				$e[$i-1] != '"' or $w .= ($s ? ") or (" : "and")." $f like '%";
			} else {
				$w .= $i != $l-1 ? "%' " : "";
				$s = $p = $q = 0;
			}
		else
			$w .= $e[$e == "\\" ? ++$i : $i];
	}
	return substr($w, 4, strlen($w))."%')";
}
function feed($feed, $content, $title = "", $description = "") {
	$title or $title = config("site->title");
	$description or $description = config("site->description");
	$name = config("site->name");
	$url = config("site->url");
	$email = config("site->email");
	$date = date("D, d M Y H:i:s", strtotime(config("site->create date")));
	$header = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<!-- generator=\"Raha\" -->\n";
	switch ($feed) {
		case "rss":
			$begin = <<<XML
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
<title>$title</title>
<link>$url</link>
<description>$description</description>
<pubDate>$date</pubDate>
<generator>https://raha.group</generator>
<language>fa</language>
XML;
			$end = "</channel>\n</rss>";
			break;
		case "opml":
			$begin = <<<XML
<opml xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<head>
<title>$title</title>
<dateModified>$date</dateModified> 
<ownerName>$name</ownerName> 
<ownerEmail>$email</ownerEmail>
</head>
<body>
XML;
			$end = "</body>\n</opml>";
	}
	die($header.$begin.$content.$end);
}
function script($type, $level = "", $script = "") {
	global $DB, $mainScript, $CPScript;
	switch ($type) {
		case "cp":
			$CPScript .= level($level) ? $script : "";
			break;
		case "main":
			$mainScript .= $level;
			break;
		case "print cp":
			return lock("js", config("change", $CPScript));
		case "print main":
			for ($i=0, $q=mysql_query("select source from $DB->plugin where type='js' and status=1"); $i<mysql_num_rows($q); $i++)
				$s .= mysql_result($q, $i);
			return lock("js", config("change", $mainScript.$s));
	}
}
# Class -> -> ->
class Jalali {
	var $cache = array(), $weekday = array("يکشنبه", "دوشنبه", "سه شنبه", "چهارشنبه", "پنجشنبه", "جمعه", "شنبه"), $numWeekday = array(1, 2, 3, 4, 5, 6, 0), $month = array("فروردين", "ارديبهشت", "خرداد", "تير", "مرداد", "شهريور", "مهر", "آبان", "آذر", "دي", "بهمن", "اسفند"), $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31), $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
	function __construct($date_format = "l j F Y", $time_format = "g:i A", $time_zone = +3.5) {
		$this->date_format = $date_format;
		$this->time_format = $time_format;
		$this->time_zone = $time_zone+date("I");
	}
	function div($a, $b){
		return (int)($a/$b);
	}
	function date($type, $maket = "now") {
		list($gy, $gm, $week, $gd, $H, $I, $S) = $this->cache[$maket] = isset($this->cache[$maket]) ? $this->cache[$maket] : explode(",", date("Y,m,w,d,H,i,s", ($maket != "now" ? $maket : Time)+$this->time_zone*3600));
		$gy -= 1600;
		$gm -= 1;
		$gd -= 1;
		$g_day_no = 365*$gy+$this->div($gy+3, 4)-$this->div($gy+99, 100)+$this->div($gy+399, 400);
		for ($i=0; $i<$gm && $i<12; ++$i)
			$g_day_no += $this->g_days_in_month[$i];
		if ($gm>1 && (($gy%4 == 0 && $gy%100 != 0) || ($gy%400 == 0))) /* leap and after Feb */
			$g_day_no++;
		$g_day_no += $gd;
		$j_day_no = $g_day_no-79;
		$j_np = $this->div($j_day_no, 12053); /* 12053 = 365*33 + 32/4 */
		$j_day_no = $j_day_no % 12053;
		$jy = 979+33*$j_np+4*$this->div($j_day_no, 1461); /* 1461 = 365*4 + 4/4 */
		$j_day_no %= 1461;
		if ($j_day_no>=366) {
			$jy += $this->div($j_day_no-1, 365);
			$j_day_no = ($j_day_no-1)%365;
		}
		for ($i=0; $i<11 && $j_day_no>=$this->j_days_in_month[$i]; ++$i)
			$j_day_no -= $this->j_days_in_month[$i];
		$jm = $i+1;
		$jd = $j_day_no+1;
		for ($j=0, $l=strlen($type); $j<$l; $j++)
			switch ($type[$j]) {
				case "A":
					$r .= $H<11 ? "قبل از ظهر" : "بعد از ظهر";
					break;
				case "a":
					$r .= $H<11 ? "ق.ظ" : "ب.ظ";
					break;
				case "d":
					$r .= $jd<10 ? "0".$jd : $jd;
					break;
				case "D":
					$r .= $this->weekday[$week][0];
					break;
				case "F":
					$r .= $this->month[$i];
					break;
				case "g":
					$r .= $H>11 ? $H-12 : ($H>9 ? $H : $H[1]);
					break;
				case "G":
					$r .= $H>9 ? $H : $H[1];
					break;
				case "h":
					$r .= $H>11 ? $H-12 : $H;
					break;
				case "H":
					$r .= $H;
					break;
				case "i":
					$r .= $I;
					break;
				case "s":
					$r .= $S;
					break;
				case "j":
					$r .= $jd;
					break;
				case "l":
					$r .= $this->weekday[$week];
					break;
				case "m":
					$r .= $jm<10 ? "0".$jm : $jm;
					break;
				case "M":
					$r .= substr($this->month[$i], 0, 3);
					break;
				case "n":
					$r .= $jm;
					break;
				case "S":
					$r .= "م";
					break;
				case "t":
					$r .= $i<11 || $jy%4 != 0 ? $this->j_days_in_month[$i] : 30;
					break;
				case "w":
					$r .= $this->numWeekday[$week];
					break;
				case "y":
					$r .= substr($jy, 2, 4);
					break;
				case "Y":
					$r .= $jy;
					break;
				case "U":
					$r .= mktime();
					break;
				case "Z":
					for ($s=0, $n=0; $n<=$i; $n++)
						$s += $n<11 || $jy%4 ? $this->j_days_in_month[$i] : 30;
					$r .= $s;
					break;
				case "\\":
					$r .= $type[++$j];
					break;							
				default:
					$r .= $type[$j];
			}
		return $r;
	}
	function convertToFarsi($matches) {
		$out = ''; 
		if (isset($matches[1])) {
			for ($i=0; $i<strlen($matches[1]); $i++)
				$out .= ereg("([0-9])", $matches[1][$i]) ? pack("C*", 0xDB, 0xB0 + $matches[1][$i]) : $matches[1][$i];
			return $out;
		}
		return $matches[0];
	}
	function convert($c, $fake=null, $fake2=null) {
		return str_replace(array("ك", "ی"), array("ک", "ي"), preg_replace_callback('/(?:&#\d{2,4};)|(\d+[\.\d]*)|<\s*[^>]+>/', array($this, 'convertToFarsi'), $c));
	}
	function gregorian($jy, $jm, $jd = 1) {
		$jy -= 979;
		$jm -= 1;
		$jd -= 1;
		$j_day_no = 365*$jy+$this->div($jy, 33)*8+$this->div($jy%33+3, 4);
		for ($i=0; $i<$jm; ++$i)
			$j_day_no += $this->j_days_in_month[$i];
		$j_day_no += $jd;
		$g_day_no = $j_day_no+79;
		$gy = 1600 + 400*$this->div($g_day_no, 146097); /* 146097 = 365*400 + 400/4 - 400/100 + 400/400 */
		$g_day_no %= 146097;
		$leap = true;
		if ($g_day_no >= 36525) /* 36525 = 365*100 + 100/4 */ {
			$g_day_no--;
			$gy += 100*$this->div($g_day_no, 36524); /* 36524 = 365*100 + 100/4 - 100/100 */
			$g_day_no %= 36524;
			if ($g_day_no >= 365)
				$g_day_no++;
			else
				$leap = false;
		}
		$gy += 4*$this->div($g_day_no, 1461); /* 1461 = 365*4 + 4/4 */
		$g_day_no %= 1461;
		if ($g_day_no >= 366) {
			$leap = false;
			$g_day_no--;
			$gy += $this->div($g_day_no, 365);
			$g_day_no %= 365;
		}
		for ($i=0; $g_day_no >= $this->g_days_in_month[$i]+($i == 1 && $leap); $i++)
			$g_day_no -= $this->g_days_in_month[$i]+($i == 1 && $leap);
		$gm = $i+1;
		$gd = $g_day_no+1;
		$gm = $gm<10 ? "0".$gm : $gm;
		return "$gy-$gm-$gd";
	}
	function archive($type = "monthly") {
		global $DB, $TPL;
		if (mysql_num_rows(mysql_query("select id from $DB->post where status=1 limit 1")) == 0) {
			$TPL->block("archive");
			return;
		}
		if ($type == "monthly") {
			$q = mysql_query("select distinct year(date) AS `year`, month(date) AS `month`, dayofmonth(date) as 'day', count(ID) as 'posts' from $DB->post where date < '".Now."' and status=1 group by year(date), month(date), dayofmonth(date) order by date desc");
			if (mysql_num_rows($q) == 0) {
				$TPL->block("archive");
				$TPL->block("archives");
				return;
			}
			list($jal_startyear, $jal_startmonth, $jal_startday) = explode("-", $this->date("Y-m-d", strtotime(mysql_result($q, mysql_num_rows($q)-1)."-".mysql_result($q, mysql_num_rows($q)-1, 1)."-".mysql_result($q, mysql_num_rows($q)-1, 2))));
			list($jal_endyear, $jal_endmonth, $jal_endday) = explode("-", $this->date("Y-m-d", strtotime(mysql_result($q, 0)."-".mysql_result($q, 0, 1)."-".mysql_result($q, 0, 2))));
			$jal_year = $jal_startyear;
			$jal_month = $jal_startmonth;
			while ($this->gregorian($jal_year, $jal_month, 1) <= $this->gregorian($jal_endyear, $jal_endmonth, 1)) {
				$jal_nextmonth = $jal_month+1;
				$jal_nextyear = $jal_year;
				if ($jal_nextmonth>12) {
					$jal_nextmonth = 1;
					$jal_nextyear++;
				}
				$gre_start = date("Y:m:d H:i:s", strtotime($this->gregorian($jal_year, $jal_month, 1)));
				$gre_end = date("Y:m:d H:i:s", strtotime($this->gregorian($jal_nextyear, $jal_nextmonth, 1)));
				$jal_post_count = mysql_result(mysql_query("select count(*) from $DB->post where date<'".Now."' and status=1 and date >= '$gre_start' and date<'$gre_end'"), 0);
				if ($jal_post_count>0) {
					$month = $jal_month<10 ? "0".$jal_month : $jal_month;
					$TPL->block("archive", $_GET["archive"] == $jal_year.$month ? array("main" => array(""), "select" => array("date" => $this->month[$jal_month]." ".$jal_year, "url" => "archive-$jal_year$month.html", "count" => $jal_post_count)) : array("select" => array(""), "main" => array("date" => $this->month[$jal_month]." ".$jal_year, "url" => "archive-$jal_year$month.html", "count" => $jal_post_count)));
					$TPL->block("archives", array("date" => $this->month[$jal_month]." ".$jal_year, "url" => "archive-$jal_year$month.html"));
				}
				$jal_month = $jal_nextmonth;
				$jal_year = $jal_nextyear;
			}
		} elseif ($type == "daily") {
			$q = mysql_query("select distinct year(date) AS `year`, month(date) AS `month`, dayofmonth(date) as 'day',count(ID) as 'posts' from $DB->post where date < '".Now."' and status=1 group by year(date), month(date), dayofmonth(date) order by date desc");
			for ($i=0; $i<mysql_num_rows($q); $i++) {
				$year = mysql_result($q, $i);
				$month = mysql_result($q, $i, 1) < 10 ? "0".mysql_result($q, $i, 1) : mysql_result($q, $i, 1);
				$day = mysql_result($q, $i, 2) < 10 ? "0".mysql_result($q, $i, 2)+1 : mysql_result($q, $i, 2)+1;
				$TPL->block("archive", $_GET["archive"] == str_replace("-", "", $this->gregorian($year, $month, $day)) ? array("main" => array(""), "select" => array("date" => $this->mydate("$year-$month-$day", 1), "url" => "archive-".$this->date("Ymd", strtotime("$year-$month-$day")).".html", "count" => mysql_result($q, $i, 3))) : array("select" => array(""), "main" => array("date" => $this->mydate("$year-$month-$day", 1), "url" => "archive-".$this->date("Ymd", strtotime("$year-$month-$day")).".html", "count" => mysql_result($q, $i, 3))));
			}
		} elseif ($type == "postbypost") {
			$q = mysql_query("select id, date, title from $DB->post where date < '".Now."' and status = 1 order by date desc");
			for ($i=0; $i<mysql_num_rows($q); $i++)
				$TPL->block("archive", array("date" => $this->mydate(mysql_result($q, $i, 1), 1), "url" => "post-".mysql_result($q, $i, 0).".html", "count" => mysql_result($q, $i, 2)));
		}
	}
	function calendar($cal_year="", $cal_month="", $block=1) {
		global $DB, $TPL;
		$cal_year != "" or $cal_year = $this->date("Y");
		$cal_month != "" or $cal_month = $this->date("m");
		$first_of_month	= strtotime($this->gregorian($cal_year, $cal_month, 2));
		$maxdays = $this->date("t", $first_of_month)+1;
		$cal_day = 1;
		$weekday = ($v=$this->date("w", $first_of_month)) != 0 ? $v-1 : 0;
		$events = array();
		$now = $this->date("Y-m-d");
		for ($i=0, $q=mysql_query("select distinct year(date) AS `year`, month(date) AS `month`, dayofmonth(date) as 'day',`title` as 'posts' from $DB->post where date < '".Now."' and status = 1 order by date desc"); $i<mysql_num_rows($q); $i++) {
			$date = mysql_result($q, $i)."-".mysql_result($q, $i, 1)."-".mysql_result($q, $i, 2);
			$events[$this->date("Y-m-d", strtotime($date))] .= ($last_post_date == $date ? "\n" : "").mysql_result($q, $i, 3);
			$last_post_date = $date;
		}
		if (!($cal_month-1)) {
			$prev_month = -11;
			$prev_year = 1;
		} else
			$prev_month++;
		if ($cal_month+1>12) {
			$next_month = -11;
			$next_year = 1;
		} else
			$next_month++;
		$prev_month = strtotime($this->gregorian($cal_year-$prev_year, $cal_month-$prev_month));
		$next_month = strtotime($this->gregorian($cal_year+$next_year, $cal_month+$next_month, 2));
		$prev = mysql_result(mysql_query("select count(*) from $DB->post where date<'".date("Y:m:d H:i:s", $prev_month)."' and status = 1 and date < '".Now."'"), 0);
		$next = mysql_result(mysql_query("select count(*) from $DB->post where date>'".date("Y:m:d H:i:s", $next_month)."' and status = 1 and date < '".Now."'"), 0);
		$url = config("site->url")."/archive-";
		$buffer = '<div align=center id=JalaliCalendar><table dir=rtl id=calendar><caption onClick="location=\''.$url.$this->date("Ym", $first_of_month).'.html\'" style="cursor: pointer">'.$this->date("F Y", $first_of_month).'</caption><thead><tr>';
		foreach (array("ش", "ي", "د", "س", "چ", "پ", "ج") as $d)
			$buffer .= "<th class=weekday>$d</th>";
		$buffer .= "</tr></thead><tr><tfoot><tr>".($prev != 0 ? '<td colspan=3 id=prev><a href="'.$url.$this->date("Ym", $prev_month).'.html" title="نمايش مطالب براي '.$this->date("F Y", $prev_month).'" onClick="return request(\'do=calendar&archive='.$this->date("Ym", $prev_month).'\', \'POST\', \'JalaliCalendar\')">« '.$this->date("F", $prev_month).'</a></td>' : "<td colspan=3 id=prev></td>").($prev != 0 || $next != 0 ? "<td class=pad>&nbsp;</td>" : "").($next != 0 ? '<td colspan=3 id=next><a href="'.$url.$this->date("Ym", $next_month).'.html" title="نمايش مطالب براي '.$this->date("F Y", $next_month).'" onClick="return request(\'do=calendar&archive='.$this->date("Ym", $next_month).'\', \'POST\', \'JalaliCalendar\')">'.$this->date("F", $next_month).' »</a></td>' : "<td colspan=3 id=next></td>")."</tr></tfoot><tbody>";
		if ($weekday>0)
			$buffer .= '<td colspan='.$weekday.'>&nbsp;';
		while ($maxdays>$cal_day) {
			if ($weekday == 7) {
				$buffer .= '<tr>';
				$weekday = 0;
			}
			$buffer .= ($events["$cal_year-$cal_month-$cal_day"] ? '<td class=event><a href="'.$url.$cal_year.$cal_month.$cal_day.'.html" title="'.$events["$cal_year-$cal_month-$cal_day"].'">'.$cal_day.'</a>' : '<td class='.("$cal_year-$cal_month-$cal_day" == $now ? 'today' : ($weekday == 6 ? 'endday' : 'day')).'>'.$cal_day).'</td>';
			$cal_day++;
			$weekday++;
		}
		$weekday == 7 or $buffer .= '<td colspan='.(7-$weekday).'>&nbsp;';
		$buffer .= '</tbody></table></div>';
		if ($block)
			$TPL->assign(array("site calendar" => $buffer));
		else
			return $buffer;
	}
}
class DataBase {
	var $host, $database, $prefix, $handle, $error = array();
	function __construct($h, $u, $P, $n, $p) {
		$this->handle = @mysql_pconnect($h, $u, $P) or die('<span style="color: #ff0000">Connection Failed!</span>');
		@mysql_select_db($n, $this->handle) or die('<span style="color: #ff0000">Can not select Data base!</span>');
		mysql_set_charset('utf8');
		$this->host = $h;
		$this->database = $n;
		$this->prefix = $p;
		$this->category = $p."categories";
		$this->comment = $p."comments";
		$this->configuration = $p."configuration";
		$this->link = $p."links";
		$this->message = $p."messages";
		$this->plugin = $p."plugins";
		$this->poll = $p."polls";
		$this->post = $p."posts";
		$this->session = $p."sessions";
		$this->theme = $p."themes";
		$this->user = $p."users";
	}
	function query($q) {
		return mysql_query($q, $this->handle) /*or $this->error[$q] = mysql_errno($this->handle)." => ".mysql_error($this->handle)*/;
	}
	function result($qu, $r=0, $c=0) {
		return mysql_result($q, $r, $c);
	}
	function num_rows($q) {
		return mysql_num_rows($q);
	}
	function fetch_array($q) {
		return mysql_fetch_assoc($q);
	}
	function field_name($q, $c) {
		return mysql_field_name($q, $c);
	}
	function num_fields($q) {
		return mysql_num_fields($q);
	}
	function insert_id() {
		return mysql_insert_id($this->handle);
	}
	function affected_rows() {
		return mysql_affected_rows($this->handle);
	}
	function select($f, $t, $w=array(), $o=array(), $g=array(), $l="") {
		$w = implode(" and ", $w);
		$o = implode(",", $o);
		$g = implode(",", $g);
		empty($w) or $q = " where $w";
		empty($o) or $q .= " order by $o";
		empty($g) or $q .= " group by $g";
		empty($l) or $q .= " limit $l";
		return $this->query("select ".implode(",", $f)." from ".implode(",", $t).$q);
	}
	function insert($t, $a) {
		foreach ($a as $f => $v) {
			$F .= "$c`$f`";
			$V .= "$c'".$this->escape($v)."'";
			$c = ", ";
		}
		return $this->query("insert into $t ".(array_keys($a) != range(0, sizeof($a)-1) ? "($F) " : "")."values ($V)");
	}
	function update($t, $a, $w="", $l="") {
		foreach ($a as $f => $v) {
			$q .= "$c`$f`='".$v."'";
			$c = ", ";
		}
		empty($w) or $q .= " where $w";
		empty($l) or $q .= " limit $l";
		$this->query("update $t set $q");
	}
	function delete($t, $w="", $l="") {
		if (empty($w) && empty($l))
			$q = "truncate $t";
		else {
			$q = "delete from $t";
			empty($w) or $q .= " where $w";
			empty($l) or $q .= " limit $l";
		}
		$this->query($q);
		return $this->affected_rows();
	}
	function create($q, $d=0) {
		$q = preg_replace("/#__(\S+?)([\s\.,]|$)/", "$this->prefix\\1\\2", $q);
		if ($d && preg_match("/create table (\S+) \(/i", $q, $m))
			$this->query("drop table if exists $m[1]");
		$this->query($q);
	}
	function escape($c) {
		return function_exists("mysql_real_escape_string") ? mysql_real_escape_string($c, $this->handle) : mysql_escape_string($c);
	}
	function backup() {
		$Q = mysql_query("show tables like '$this->prefix%'");
		while ($r=mysql_fetch_array($Q, MYSQL_NUM)) {
			for ($q=mysql_query("select * from `$r[0]`"), $R=mysql_num_rows($q), $i=0, $c=$d=""; $i<$R; $d .= "$c(\n\t$C\n)", $c=", ", $i++)
				for ($j=0, $C=""; $j<mysql_num_fields($q); $j++)
					$C .= ($j ? ", " : "").(is_numeric($v=mysql_result($q, $i, $j)) ? $v : "'".str_replace(array("\n", "\r", "\t"), array('\n', '\r', '\t'), $this->escape($v))."'");
			!$R or $d = "\n\n# --------------------------------------------------------\n# Data contents of table `$r[0]`\n#\nINSERT INTO `$r[0]` VALUES $d;\n\n#\n# End of data contents of table `$r[0]`\n# --------------------------------------------------------\n";
			$D .= "\n# --------------------------------------------------------\n# Table: `$r[0]`\n# --------------------------------------------------------\n\nDROP TABLE IF EXISTS `$r[0]`;\n".mysql_result(mysql_query("show create table `$r[0]`"), 0, 1).";$d";
		}
		return "# Raha MySQL database backup\n#\n# Generated: ".date("l j. F Y H:i T")."\n# Hostname: $this->host\n# Database: $this->database\n# --------------------------------------------------------\n$D";
	}
	function updateTime($t) {
		$r = $this->fetch_array($this->query("show table status like '$t'"));
		return strtotime($r["Update_time"]);
	}
	function info() {
		return mysql_get_server_info();
	}
	function error() {
		foreach ($this->error as $q => $e)
			$r .= "<p>SQL :<br><code>$q</code><br>$e</p>";
		return $r;
	}
}
class Template {
	var $set = array(array(array("comment" => null), "theme('print', 'comment')"), array(array("downloadPost" => null), "config('post->template')"), array(array("contact" => null), "theme('print', 'contact')"), array(array("guestbook" => null), "theme('print', 'guestbook')"), array(array("profile" => null), "theme('print', 'profile')"), array(array("CP" => null), "theme('print', 'control panel')"), array(array("membership" => null), "theme('print', 'membership')"), array(array("action" => "membership"), "theme('print', 'membership')"), array(array("login" => null), "theme('print', 'login')"), array(array("action" => "emoticon"), "theme('print', 'emoticons')"), array(array("post" => null), "theme('print', 'post')"), array(array("linkbox" => null), "config('link->theme')"), array(array("action" => "linkRegistration"), "theme('print', 'link registration')"));
	function __construct($tpl = null) {
		$this->tpl = $tpl;
	}
	function set($q, $t) {
		$this->set[] = array($q, $t);
	}
	function load() {
		if (isset($this->tpl))
			return $this->tpl;
		$b = config("banned ip");
		$k = array_search(IP, $b["ip"]);
		if (is_numeric(k) && $b["expires"][$k] >= Time)
			$B = 1;
		if (config("site->main page") == "main" && !$B) {
			foreach ($this->set as $v) {
				foreach ($v[0] as $K => $V)
					if (!(isset($_GET[$K]) && ($V == null || $_GET[$K] == $V))) {
						$v[1] = null;
						break;
					}
				if ($v[1]) {
					eval("\$tpl = $v[1];");
					break;
				}
			}
			if (!isset($tpl))
				$tpl = theme("print", "main");
		} else
			if ($B) {
				$TPL = new Template(config("template->banned ip"));
				$TPL->block("countdown", time_parser($b["expires"][$k]-Time));
				$TPL->assign(array("infraction" => $b["infraction"][$k]));
				$tpl = $TPL->compile();
			} else
				$tpl = isset($_GET["CP"]) && level("configuration") ? theme("print", "control panel") : (isset($_GET["login"]) ? theme("print", "login") : config("template->coming soon"));
		return ($this->tpl = $tpl);
	}
	function get($block, $input = "", $begin = "", $end = "") {
		$tag = $block;
		$beginBlock = explode("->", $block);
		preg_match_all("|<$beginBlock[0]>(.*?)</$beginBlock[0]>|is", $this->tpl, $tpls);
		foreach ($tpls[1] as $tpl) {
			foreach (explode("->", $block) as $index => $tag)
				if ($index != 0) {
					$tag = explode(".", $tag);
					$tag = $tag[0];
					preg_match("|<$tag>(.*?)</$tag>|is", $tpl, $tpl);
					$tpl = $tpl[1];
				}
			if ($tpl != "")
				$data = $tpl;
		}
		$tpl = $data;
		if ($input == "" && ereg(".", $block)) {
			$arrayTag = explode(".", $block);
			$arrayTag = empty($arrayTag[1]) ? $arrayTag[0] : $arrayTag[1];
			preg_match("|\[$arrayTag value=\((.*?)\)\]|is", $tpl, $value);
			return $value[1];
		}
		return $this->replace($input, $begin.$tpl.$end);
	}
	function replace($input, $tpl) {
		if (is_array($input) && $input != array("") && empty($input[0])) {
			if (!empty($input))
				foreach ($input as $tag => $value)
					$tpl = is_array($value) ? preg_replace("|<$tag>(.*)</$tag>|ies", $value == "" ? "" : '$this->replace($value, stripslashes("$1"))', $tpl) : (preg_match("|\[$tag value=\((.*?)\)\]|is", $tpl, $tag_value) ? str_replace("[$tag value=($tag_value[1])]", str_replace("[value]", $tag_value[1], $value), $tpl) : str_replace("[$tag]", $value, $tpl));
		} else
			$tpl = $input == array("") ? "" : $input[0];
		return $tpl;
	}
	function block($name, $input = "", $begin = "", $end = "") {
		$this->blocks["begin"][$name][] = $begin;
		$this->blocks["end"][$name][] = $end;
		$this->blocks[$name][] = $input;
	}
	function assign($input) {
		foreach ($input as $tag => $value)
			$this->tags[$tag] = $value;
	}
	function block_replace($arrays, $name, $template) {
		foreach ($arrays as $i => $v)
			$output .= $this->blocks["begin"][$name][$i].(is_array($v) ? $this->replace($v, $template) : $v).$this->blocks["end"][$name][$i];
		return $output;
	}
	function compile() {
		foreach ($this->blocks as $name => $arrays)
			$this->tpl = preg_replace("|<$name>(.*?)</$name>|ies", '$this->block_replace($arrays, $name, stripslashes("$1"))', $this->tpl);
		return lock("html", $this->replace($this->tags, $this->tpl));
	}
}
class ControlPanel {
	var $menu, $subMenu, $level, $permission = array(), $access = array(), $content = array();
	function __construct($level) {
		$this->level = $level;
		$this->permission = (array)config("level->access->$level");
	}
	function menu($section, $name, $URL, $title, $avatar = "") {
		$this->menu[$section] = array($name, $URL, $title, $avatar);
	}
	function subMenu($access, $parent, $name, $content, $avatar = "") {
		if ($this->permission($access) && !empty($content))
			if ($access == "active") {
				foreach ($content as $link)
					if (is_array($link))
						$contents[] = $link;
				if (is_array($contents))
					$this->subMenu[$parent][] = array($name, $contents, $avatar);
			} else
				$this->subMenu[$parent][] = array($name, $content, $avatar);
	}
	function page($access, $section, $category, $item, $content, $submit = "") {
		$this->content[$section][$category][$item] = array($access, $content, $submit);
		if (@$_GET["page"] == $section && @$_GET["part"] == $category && @$_GET["action"] == $item)
			die($this->permission($access) ? $content : "");
		else
			return false;
	}
	function access($array) {
		$this->access = array_merge($this->access, $array);
	}
	function permission($access) {
		if ($v=cache("get", "level", "$this->level-$access"))
			return $v;
		$result = in_array($access, $this->permission) || $access == "active" || $access == 1 ? true : false;
		cache("set", "level", "$this->level-$access", $result);
		return $result;
	}
	function link($access, $name, $URL, $title = "") {
		return $this->permission($access) ? array($name, $URL, $title) : false;
	}
	function URL($section, $category, $item = "") {
		return "CP&page=$section&part=$category&action=$item";
	}
	function publish() {
		if ($data = $this->content[$_GET["section"]][$_GET["category"]][$_GET["item"]] && $this->permission($data[0]))
			die(eval((isset($_POST["submit"])) ? $data[2] : $data[1]));
	}
	function run() {
		$countSection = count($this->subMenu)-1;
		$j = 0;
		foreach ((array)$this->subMenu as $index => $parent) {
			$data = $this->menu[$index];
			$content .= "[[\"$data[0]\", \"$data[1]\", \"$data[2]\", \"$data[3]\"], [";
			$countSubMenu = count($this->subMenu[$index])-1;
			foreach ($this->subMenu[$index] as $Index => $data) {
				$content .= "[\"$data[0]\", [";
				$countParent = count($data[1])-1;
				foreach ($data[1] as $i => $v)
					$content .= "[\"$v[0]\", \"$v[1]\", \"$v[2]\"]".(($i == $countParent) ? "" : ", ");
				$content .= "], \"$data[2]\"]".(($Index == $countSubMenu) ? "" : ", ");
			}
			$content .= "]]".(($j == $countSection) ? "" : ", ");
			$j++;
		}
		return '<span id=CPO style="display: none"></span><script>var Menu = ['.$content."];</script>";
	}
}
class XMLParser {
	var $data, $collapse_dups = 1, $index_numeric = 0;
	function __construct($data) {
		$this->data = str_replace("\r\n", "\n", $data);
	}
	function get_tree() {
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		if (!xml_parse_into_struct($parser, $this->data, $vals, $index))
			return false;
		$i = -1;
		return $this->get_children($vals, $i);
	}
	function build_tag($thisvals, $vals, &$i, $type) {
		$tag["tag"] = $thisvals["tag"];
		if (isset($thisvals["attributes"]))
			$tag["attributes"] = $thisvals["attributes"];
		if ($type == "complete")
			$tag["value"] = $thisvals["value"];
		else
			$tag = array_merge($tag, $this->get_children($vals, $i));
		return $tag;
	}
	function get_children($vals, &$i) {
		$children = array();
		if ($i>-1 && isset($vals[$i]["value"]))
			$children["value"] = $vals[$i]["value"];
		while (++$i<count($vals)) {
			$type = $vals[$i]["type"];
			if ($type == "cdata")
				$children["value"] .= $vals[$i]["value"];
			elseif ($type == "complete" || $type == "open") {
				$tag = $this->build_tag($vals[$i], $vals, $i, $type);
				if ($this->index_numeric) {
					$tag["tag"] = $vals[$i]["tag"];
					$children[] = $tag;
				} else
					$children[$tag["tag"]][] = $tag;
			} elseif ($type == "close")
				break;
		}
		if ($this->collapse_dups)
			foreach ($children as $key => $value)
				if (is_array($value) && count($value) == 1)
					$children[$key] = $value[0];
		return $children;
	}
}
class Request {
	function __construct() {
		global $DB, $CP;
		$this->DB = $DB;
		$this->CP = $CP;
		$permission = array("login" => 1, "comment" => 1, "membership" => 1, "contact" => 1, "newsletter" => 1, "registerLink" => 1, "poll" => 1, "postMoreContent" => 1, "ratePost" => 1, "calendar" => 1, "menu" => 1, "update_newsletter" => "newsletter", "send_newsletter" => "newsletter", "update_comment" => "comment", "delete_comment" => "comment", "update_guestbook" => "guestbook", "delete_guestbook" => "guestbook", "change_status" => 1, "add_plugin" => "plugin", "update_plugin" => "plugin", "delete_plugin" => "plugin", "announcement" => "announcement", "bad_word" => "bad word", "banned_ip" => "banned ip", "tag" => "tag", "links" => "link", "import_link" => "import link", "category" => "category", "add_poll" => "poll", "update_poll" => "poll", "select_poll" => "poll", "delete_poll" => "poll", "add_post" => "writer", "update_post" => 1, "delete_post" => 1, "import_post" => "import post", "add_level" => "level", "update_level" => "level", "delete_level" => "level", "add_theme" => "theme", "update_theme" => "theme", "select_theme" => "theme", "delete_theme" => "theme", "add_user" => "user", "update_user" => "user", "delete_user" => "user", "profile" => "profile", "avatar" => "user avatar", "emoticon" => "emoticon", "configuration" => "configuration", "PM" => "message");
		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["do"]) && method_exists($this, $_POST["do"]) && check("referer"))
			level($permission[$_POST["do"]]) && !level("demo") ? $this->$_POST["do"]() : $this->result = '<span style="color: #770000; text-align: center; font-family: tahoma; font-size: 8pt">شما داراي اختيارات کافي نمي‌باشيد.</span>';
	}
	function login() {
		global $DB;
		$username = strtolower($_POST["username"]);
		$password = md5($_POST["password"]);
		($e = check("security code", $_POST["securityCode"])) == 1 or $this->result = $e;
		if (empty($this->result)) {
			if (mysql_num_rows($q = mysql_query("select id, status from $DB->user where username='$username' and password='$password'")) && mysql_result($q, 0, 1)) {
				$_SESSION["logedIn"] = mysql_result($q, 0);
				$_SESSION["username"] = $username;
				$_SESSION["password"] = $password;
				cookie(array("username" => $username, "password" => $password, "user id" => $_SESSION["logedIn"]), $_POST["remember"] ? "" : 0);
				$url = empty($_POST["referrer"]) ? config("site->url") : $_POST["referrer"];
				$this->result = 'window.location = "'.$url.'";#JSاز ورود شما سپاسگذاريم';
			} else
				$this->result = mysql_num_rows($q) ? "وضعيت ورود شما غير فعال است" : "نام کاربري يا کلمه‌ي عبور شما نادرست است";
			securityCode();
		}
	}
	function comment() {
		global $DB;
		$author = logedIn ? user("name") : $_POST["author"];
		$email = logedIn ? user("email") : $_POST["email"];
		$url = logedIn ? user("url") : ($_POST["url"] == "http://" ? "" : $_POST["url"]);
		$postID = $_POST["postID"];
		$content = config(($postID != 0 ? "comment" : "guestbook")."->html") ? $_POST["content"] : html($_POST["content"], "convert");
		$status = $_POST["privateMessage"] ? 2 : config(($postID != 0 ? "comment" : "guestbook")."->status");
		$mode = $postID != 0 ? "نظر" : "يادبود";
		($e = check("content", $content)) == 1 or $result .= "<br>$e";
		($e = check("author", $author)) == 1 or $result .= "<br>$e";
		($e = check("email", $email)) == 1 or $result .= "<br>$e";
		!($postID != 0 && mysql_result(mysql_query("select `comment status` from $DB->post where id='$postID' "), 0) == 0) or $result .= "<br>نظرات بسته شده است";
		($e = check("security code", $_POST["securityCode"])) == 1 or $result .= "<br>$e";
		$this->result = $result;
		if (empty($result)) {
			mysql_query("insert into $DB->comment values('', '$postID', '".logedIn."', '$author', '$content', '$email', '$url', '".IP."', '".Time."', '$status')");
			cookie(array("saveCookie" => 1, "privateMessage" => $_COOKIE["privateMessage"] ? 1 : 0, "author" => $author, "email" => $email, "url" => $url, "to" => $to), $_POST["saveCookie"] ? "" : 0);
			$this->result = $status == 0 ? "$mode شما با موفقيت ارسال شد، پس از تاييد مدير سايت به نمايش در مي‌آيد." : "$mode شما با موفقيت ارسال شد";
			securityCode();
		}
	}
	function membership() {
		$username = $_POST["username"];
		$password = $_POST["password"];
		$repassword = $_POST["repassword"];
		$email = $_POST["email"];
		$name = $_POST["nickname"];
		config("user->membership status") != 2 or $result .= "<br>عضويت بسته شده است.";
		($e = check("security code", $_POST["securityCode"])) == 1 or $result .= "<br>$e";
		$this->result = $result;
		if (empty($result))
			$this->result = config("user->membership status") ? user("add", "", $username, array($password, $repassword), "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", $email, "", "", "", "", "", "", 1, "l j F Y", "g:i A", 3.5, 6, 0, config("user->membership level"), 1) : user("add", "", $username, array($password, $repassword), "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", $email, "", "", "", "", "", "", 1, "l j F Y", "g:i A", 3.5, 6, 0, config("user->membership level"), securityCode(0), 1);
	}
	function contact() {
		$to = $_POST["to"];
		$content = $_POST["content"];
		$author = logedIn ? user("name") : $_POST["author"];
		$email = logedIn ? user("email") : $_POST["email"];
		$url = logedIn ? user("url") : ($_POST["url"] == "http://" ? "" : $_POST["url"]);
		$subject = $_POST["subject"];
		($e = check("content", $content)) == 1 or $result .= "<br>$e";
		($e = check("author", $author)) == 1 or $result .= "<br>$e";
		($e = check("email", $email)) == 1 or $result .= "<br>$e";
		($e = check("security code", $_POST["securityCode"])) == 1 or $result .= "<br>$e";
		($e = check("subject", $subject)) == 1 or $result .= "<br>$e";
		$this->result = $result;
		if (empty($result)) {
			mysql_query("insert into {$this->DB->message} values('', 0, 1, ".logedIn.", '$to', ".Time.", '$subject', '".html($content, "convert")."', '$author', '$email', '$url', '".IP."')");
			cookie(array("saveCookie" => 1, "privateMessage" => $_COOKIE["privateMessage"], "author" => $author, "email" => $email, "url" => $url, "to" => $to), $_POST["saveCookie"] ? "" : 0);
			$this->result = "پيام شما با موفقيت براي ".user("name", $to)." ارسال شد.";
			securityCode();
		}
	}
	function newsletter() {
		$email = strtolower(trim($_POST["email"]));
		if (($e = check("email", $email)) == 1) {
			$status = strpos(config("newsletter->emails"), $email);
			if ($_POST["action"] == "subscription") {
				if ($status === false) {
					$emails = config("newsletter->emails");
					config("newsletter->emails", $emails.(empty($emails) ? "" : ",").$email);
					$this->result = "ايميل شما اضافه شد";
				} else
					$this->result = "اين ايميل قبلا ثبت شده است";
			} elseif ($_POST["action"] == "unsubscription") {
				if ($status !== false) {
					$d = config("newsletter");
					foreach (explode(",", $d["emails"]) as $mail)
						if ($mail != $email)
							$emails .= (empty($emails) ? "" : ",").$mail;
					$d["emails"] = $emails;
					config("newsletter", $d);
					$this->result = "ايميل شما حذف شد";
				} else
					$this->result = "چنين ايميلي موجود نيست";
			}
		} else
			$this->result = $e;
	}
	function registerLink() {
		$name = $_POST["name"];
		$url = $_POST["url"];
		$description = $_POST["description"];
		$email = $_POST["email"];
		$type = $_POST["type"];
		($e = check("title", $name)) == 1 or $result .= "<br>$e";
		($e = check("url", $url)) == 1 or $result .= "<br>$e";
		mysql_num_rows(mysql_query("select creator from {$this->DB->link} where url='$url' and type='$type'"), 0) == 0 or $result .= "<br>اين لينک قبلا ثبت شده است";
		($e = check("email", $email)) == 1 or $result .= "<br>$e";
		($e = check("security code", $_POST["securityCode"])) == 1 or $result .= "<br>$e";
		$this->result = $result;
		if (empty($result)) {
			$this->result = linkbox($name, $url, $description, 0, 0, $email, Time, $type, config("link->status"));
			securityCode();
		}
	}
	function poll() {
		$q = mysql_query("select * from {$this->DB->poll} where id='$_POST[id]'");
		$old_votes = explode("\n", mysql_result($q, 0, 3));
		foreach (explode("\n", mysql_result($q, 0, 2)) as $id => $answer) {
			$vote = empty($old_votes[$id]) ? 0 : $old_votes[$id];
			$votes .= $line.($_POST["answer"] == $id ? $vote+1 : $vote);
			$line = "\n";
		}
		$ips = mysql_result($q, 0, 4);
		$voted = in_array(IP, explode("\n", $ips));
		$this->result = "لطفا يک پاسخ را انتخاب کنيد";
		if (!$voted && !empty($_POST["id"]) && !empty($_POST["answer"])) {
			$ips .= (empty($ips) ? "" : "\n").IP;
			$this->result = "از ارزيابي شما متشکريم";
			$this->DB->update($this->DB->poll, array("votes" => $votes, "ips" => $ips), "id='$_POST[id]'");
		} elseif ($voted)
			$this->result = "راي شما قبلا ثبت شده بود";
	}
	function postMoreContent() {
		mysql_query("update {$this->DB->post} set view=view+1 where id='$_POST[id]'");
		$this->result = html(mysql_result(mysql_query("select content from {$this->DB->post} where id='$_POST[id]'"), 0));
	}
	function ratePost() {
		$n = config("post->max ratings");
		is_numeric($_POST["score"]) && $n >= $_POST["score"] && mysql_num_rows($q = mysql_query("select ratings from {$this->DB->post} where `rate status`=1 and id='$_POST[id]'")) or die;
		$ratings = explode(",", mysql_result($q, 0));
		for ($i=0; $i<$n; $i++)
			if (!is_numeric($ratings[$i]))
				$ratings[$i] = 0;
		$ratings[$_POST["score"]-1]++;
		$this->DB->update($this->DB->post, array("ratings" => join(",", $ratings)), "id='$_POST[id]'");
		$this->result = "از ارزيابي شما متشکريم";
	}
	function calendar() {
		global $Jalali;
		$this->result = $Jalali->calendar(substr($_POST["archive"], 0, 4), substr($_POST["archive"], 4, 2), 0);
	}
	function menu() {
		$this->result = $this->CP->run()."اطلاعات با موفقيت بارگذاري مجدد شد";
	}
	function send_newsletter() {
		die(newsletter("send", $_POST["title"], $_POST["content"]));
	}
	function update_newsletter() {
		set_cfg("newsletter", array("emails" => $_POST["emails"], "subject" => $_POST["subject"], "status" => $_POST["status"], "template" => $_POST["tpl"]));
		die("تنظيمات خبرنامه بروزرساني شد");
	}
	function update_comment() {
		$this->DB->update($this->DB->comment, array("post id" => $_POST["post_id"], "author" => $_POST["author"], "content" => $_POST["content"], "email" => $_POST["email"], "url" => $_POST["url"], "ip" => $_POST["ip"], "time" => mydate($_POST["date"], 1), "status" => $_POST["status"]), "id='$_POST[id]'");
		die("نظر مورد نظر ويرايش شد");
	}
	function delete_comment() {
		$this->DB->delete($this->DB->comment, "id='$_POST[id]'");
		die("نظر مورد نظر حذف شد");
	}
	function update_guestbook() {
		$this->DB->update($this->DB->comment, array("author" => $_POST["author"], "content" => $_POST["content"], "email" => $_POST["email"], "url" => $_POST["url"], "ip" => $_POST["ip"], "time" => mydate($_POST["date"], 1), "status" => $_POST["status"]), "id='$_POST[id]'");
		die("يادبود ".table_data($this->DB->comment, "author", $_POST["id"])." ويرايش شد");
	}
	function delete_guestbook() {
		$this->result = "يادبود ".table_data($this->DB->comment, "author", $_POST["id"])." حذف شد.";
		$this->DB->delete($this->DB->comment, "id='$_POST[id]'");
	}
	function add_plugin() {
		mysql_query("insert into {$this->DB->plugin} values('', '$_POST[source]', '$_POST[type]', '$_POST[status]')");
		die("اضافه شد");
	}
	function update_plugin() {
		$this->DB->update($this->DB->plugin, array("source" => $_POST["source"], "type" => $_POST["type"], "status" => $_POST["status"]), "id='$_POST[id]'");
		die("ويرايش شد");
	}
	function delete_plugin() {
		$this->DB->delete($this->DB->plugin, "id='$_POST[id]'");
		die("حذف شد");
	}
	function announcement() {
		set_cfg("announcement", array("title" => $_POST["title"], "content" => $_POST["content"], "view" => $_POST["view"], "count" => $_POST["count"], "expires" => mydate($_POST["expires"], 1)));
		die("اعلان‌ها بروزرساني شد");
	}
	function bad_word() {
		foreach ($_POST["word"] as $k => $v)
			if ($v)
				$badWord[$v] = $_POST["replace"][$k];
		set_cfg("bad word", $badWord);
		die("کلمه‌هاي ناپسند بروزرساني شد");
	}
	function banned_ip() {
		foreach ($_POST["IP"] as $k => $v)
			if ($v) {
				$bannedIP["ip"][] = $v;
				$bannedIP["expires"][] = mydate($_POST["expires"][$k], 1);
				$bannedIP["infraction"][] = $_POST["infraction"][$k];
			}
		set_cfg("banned ip", $bannedIP);
		die("افراد اخراج شده بروزرساني شد");
	}
	function tag() {
		foreach ($_POST["tag"] as $k => $v)
			if ($v)
				$tag[$v] = $_POST["replace"][$k];
		set_cfg("tag", $tag);
		die("تگ‌ها بروزرساني شدند");
	}
	function links() {
		for ($q=mysql_query("select id from {$this->DB->link}"), $i=0; $i<mysql_num_rows($q); $i++) {
			$id = mysql_result($q, $i);
			if ($_POST["status$id"] == 2)
				$this->DB->delete($this->DB->link, "id='$id'");
			elseif (!empty($_POST["url$id"]))
				$this->DB->update($this->DB->link, array("name" => $_POST["name".$id], "url" => $_POST["url".$id], "description" => $_POST["description".$id], "count" => $_POST["count".$id], "view" => $_POST["view".$id], "creator" => user("convert", "email", "id", $_POST["creator".$id]), "time" => mydate($_POST["date".$id], 1), "type" => $_POST["type".$id], "status" => $_POST["status".$id]), "id=$id");
		}
		for ($i=0; $i<30; $i++)
			if (!empty($_POST["new_name$i"]) && !empty($_POST["new_url$i"]))
				linkbox($_POST["new_name$i"], $_POST["new_url$i"], $_POST["new_description$i"], $_POST["new_count$i"], $_POST["new_view$i"], logedIn, Time, $_POST["new_type$i"], 1);
		die("لينک‌ها بروزرساني شدند");
	}
	function import_link() {
		$parser = new XMLParser(remote_fopen($_POST["url"]));
		$tree = $parser->get_tree();
		foreach ($tree["opml"]["body"]["outline"] as $link)
			linkbox($link["attributes"]["text"], $link["attributes"]["url"], $link["attributes"]["description"], 0, 0, logedIn, Time, $_POST["type"], $_POST["status"]);
		die("لينک‌ها وارد شدند");
	}
	function category() {
		for ($i=0; $i<30 && !empty($_POST["new_name_".$i]); $i++)
			mysql_query("insert into {$this->DB->category} values('', '".$_POST["new_name_".$i]."', '".$_POST["new_description_".$i]."', '".$_POST["new_parent_".$i]."', '".user("convert", "username", "id", $_POST["new_moderator_".$i], "\r\n")."', '".$_POST["new_avatar_".$i]."', '".$_POST["new_status_".$i]."')");
		for ($i=0, $Q=mysql_query("select id from ".$this->DB->category), $in=""; $i<mysql_num_rows($Q); $i++) {
			$id = mysql_result($Q, $i);
			if ($_POST["delete_".$id]) {
				for ($i=0, $q=mysql_query("select id from {$this->DB->category} where parent=$id"); $i<mysql_num_rows($q); $i++)
					$in .= ",".mysql_result($q, $i);
				mysql_query("delete from {$this->DB->category} where id='$id' or parent in('$id',$in);delete from {$this->DB->post} where category=$id");
			} elseif (!empty($_POST["name_".$id]))
				$this->DB->update($this->DB->category, array("name" => $_POST["name_".$id], "description" => $_POST["description_".$id], "parent" => $_POST["parent_".$id], "moderator" => user("convert", "username", "id", $_POST["moderator_".$id], "\r\n"), "icon" => $_POST["avatar_".$id], "status" => $_POST["status_".$id]), "id='$id'");
		}
		die("دسته‌بندي‌ها بروزرساني شد");
	}
	function add_poll() {
		$this->status_poll();
		mysql_query("insert into {$this->DB->poll} values('', '$_POST[question]', '$_POST[answers]', '$_POST[votes]', '$_POST[ips]', ".mydate($_POST["time"], 1).", $_POST[status]);");
		die("نظرسنجي $_POST[question] اضافه شد");
	}
	function update_poll() {
		$this->status_poll();
		$this->DB->update($this->DB->poll, array("question" => $_POST["question"], "answers" => $_POST["answers"], "votes" => $_POST["votes"], "ips" => $_POST["ips"], "time" => mydate($_POST["time"], 1), "status" => $_POST["status"]), "id='$_POST[id]'");
		die("نظرسنجي $_POST[question] ويرايش شد.");
	}
	function select_poll() {
		mysql_query("update {$this->DB->poll} set status = 0 where status = 1;update {$this->DB->poll} set status = 1 where id != '$_POST[id]'");
		die("نظرسنجي ".table_data($this->DB->poll, "question", $_POST["id"])." تغيير وضعيت داد");
	}
	function delete_poll() {
		$this->result = "نظرسنجي ".table_data($this->DB->poll, "question", $_POST["id"])." حذف شد";
		$this->DB->delete($this->DB->poll, "id='$_POST[id]'");
	}
	function status_poll() {
		if ($_POST["status"])
			mysql_query("update {$this->DB->poll} set status=0 where status=1");
	}
	function add_post() {
		mysql_query("insert into {$this->DB->post} values('', '$_POST[title]', '$_POST[content]', '".logedIn."', '$_POST[category]', '$_POST[date]', '', '".encrypt($_POST["ratings"])."', '$_POST[view]', '$_POST[rate_status]', '$_POST[comment_status]', '$_POST[status]');");
		$r = "مطلب $_POST[title] اضافه شد";
		if ($_POST["status"] && config("post->ping status"))
			foreach (explode("\n", config("post->ping service")) as $s)
				$r .= "<br>$s ".xmlrpc($s);
		if ($_POST["status"] && config("newsletter->status"))
			$r .= "<br>".newsletter("post", "", mysql_insert_id(), $_POST["title"], $_POST["content"], $_POST["date"]);
		die($r);
	}
	function update_post() {
		level("editor") || category("check moderator", mysql_result(mysql_query("select category from {$this->DB->post} where id='$_POST[id]'"), 0)) or die;
		$modify = post("modify", $_POST["id"]);
		$this->DB->update($this->DB->post, array("title" => $_POST["title"], "content" => $_POST["content"], "category" => $_POST["category"], "date" => $_POST["date"], "modify" => $_POST["date"] <= Now ? $modify.($modify ? "," : "").logedIn." ".Time : "", "ratings" => $_POST["ratings"], "view" => $_POST["view"], "rate status" => $_POST["rate_status"], "comment status" => $_POST["comment_status"], "status" => $_POST["status"]), "id='$_POST[id]'");
		die("مطلب $_POST[title] ويرايش شد");
	}
	function delete_post() {
		level("editor") || category("check moderator", mysql_result(mysql_query("select category from {$this->DB->post} where id='$_POST[id]'"), 0)) or die;
		$this->result = "مطلب ".mysql_result(mysql_query("select title from {$this->DB->post} where id='$_POST[id]'"), 0)." حذف شد.";
		$this->DB->delete($this->DB->post, "id='$_POST[id]'");
		$this->DB->delete($this->DB->comment, "`post id`='$_POST[id]'");
	}
	function import_post() {
		$parser = new XMLParser(remote_fopen($_POST["url"]));
		$tree = $parser->get_tree();
		foreach ($tree["rss"]["channel"]["item"] as $item) {
			if (!$content = $item["content:encoded"]["value"])
				$content = $item["description"]["value"];
			$category = $item["category"]["value"];
			if (isset($category))
				if (mysql_num_rows($q = mysql_query("select id from {$this->DB->category} where name='$category'")))
					$category = mysql_result($q, 0);
				else {
					mysql_query("insert into {$this->DB->category}(name) values('$category')");
					$category = mysql_insert_id();
				}
			else
				$category = config("category->default");
			mysql_query("insert into {$this->DB->post} values('', '$item[title][value]', '".str_replace(array("&lt;", "&gt;"), array("<", ">"), $content)."', ".logedIn.", '$category', '".gmdate("Y-m-d H:i:s", $item["pubDate"])."', '', '', '', '$_POST[rate]', '$_POST[comment]', '$_POST[status]')");
		}
		die("مطلب‌ها با موفقيت وارد شدند");
	}
	function level() {
		$_DATA = $_POST;
		unset($_DATA["do"], $_DATA["name"], $_DATA["id"]);
		foreach ($_DATA as $access => $check)
			if ($check)
				$accesses[] = str_replace(":_:", " ", $access);
		return $accesses;
	}
	function add_level() {
		$id = config("level->increment")+1;
		config("level->name->$id", $_POST["name"]);
		config("level->access->$id", $this->level());
		config("level->increment", $id);
		die("سطح $_POST[name] اضافه شد");
	}
	function update_level() {
		config("level->name->$_POST[id]", $_POST["name"]);
		config("level->access->$_POST[id]", $this->level());
		die("سطح $_POST[name] بروزرساني شد");
	}
	function delete_level() {
		$d = config("level");
		$this->result = "سطح ".$d["name"][$_POST["id"]]." حذف شد";
		unset($d["name"][$_POST[id]], $d["access"][$_POST[id]]);
		config("level", $d);
	}
	function add_theme() {
		for ($i=1, $q=mysql_query("show fields from ".$this->DB->theme); $i<mysql_num_rows($q); $i++)
			$f .= ", '".$_POST[str_replace(" ", "_", mysql_result($q, $i))]."'";
		mysql_query("insert into {$this->DB->theme} values(''$f)");
		die("پوسته‌ي مورد نظر اضافه شد");
	}
	function update_theme() {
		for ($i=1, $q=mysql_query("show fields from ".$this->DB->theme); $i<mysql_num_rows($q); $i++)
			$a[mysql_result($q, $i)] = $_POST[str_replace(" ", "_", mysql_result($q, $i))];
		$this->DB->update($this->DB->theme, $a, "id='$_POST[id]'");
		die("پوسته‌ي مورد نظر بروزرساني شد");
	}
	function select_theme() {
		config("site->theme", $_POST["id"]);
		die("پوسته‌ي مورد نظر شما انتخاب شد");
	}
	function delete_theme() {
		$data = theme("data", $_POST["id"]);
		$this->DB->delete($this->DB->theme, "id='$_POST[id]'");
		die("پوسته‌ي $data[name] حذف شد");
	}
	function user() {
		if ((($_POST["do"] == "add_user" || $_POST["do"] == "update_user") && level("user")) || ($_POST["do"] == "profile" && level("profile"))) {
			if ($_POST["do"] == "profile") {
				$id = logedIn;
				$level = user("level");
				$status = user("status");
			} else {
				$id = $_POST["id"];
				$level = $_POST["level"];
				$status = $_POST["status"];
			}
			die(user($_POST["do"] == "add_user" ? "add" : "update", $id, $_POST["username"], array($_POST["password"], $_POST["repassword"], $_POST["oldpassword"]), $_POST["first_name"], $_POST["last_name"], $_POST["nickname"], $_POST["birthday"], $_POST["gender"], $_POST["marriage"], $_POST["eduction"], $_POST["vocation"], $_POST["amusement"], $_POST["favorite"], $_POST["about"], $_POST["avatar"], $_POST["signature"], $_POST["location"], $_POST["phone"], $_POST["email"], $_POST["url"], $_POST["msn"], $_POST["yahoo"], $_POST["gmail"], $_POST["aim"], $_POST["icq"], $_POST["visible"], $_POST["date_format"], $_POST["time_format"], $_POST["time_zone"], $_POST["start_of_week"], $_POST["theme"], $level, $status));
		}
	}
	function add_user() {
		$this->user();
	}
	function update_user() {
		$this->user();
	}
	function profile() {
		$this->user();
	}
	function delete_user() {
		die(user("delete", $_POST["id"]));
	}
	function avatar() {
		foreach ($_POST["url"] as $k => $v)
			if ($v && $_POST["delete"][$k] == 0)
				$avatar[$_POST["name"][$k]] = $v;
		config("avatar", $avatar);
		die("تصاوير بروزرساني شد");
	}
	function emoticon() {
		$emoticon = config("emoticon");
		$emoticon["data"] = array();
		foreach ($_POST["code"] as $k => $v)
			if ($v && $_POST["state"][$k] != 2) {
				$emoticon["data"]["code"][] = $v;
				$emoticon["data"]["url"][] = $_POST["url"][$k];
				$emoticon["data"]["description"][] = $_POST["description"][$k];
				$emoticon["data"]["state"][] = $_POST["state"][$k];
			}
		config("emoticon", $emoticon);
		die("شکلک‌‌ها بروزرساني شدند.");
	}
	function configuration() {
		foreach (array("site", "post", "comment", "category", "user", "message", "guestbook", "link", "security code", "logo", "emoticon", "template", "cookie", "smtp") as $n) {
			foreach (($d=config($n)) as $k => $v)
				$d[$k] = isset($_POST[str_replace(" ", "_", $n)."_".str_replace(" ", "_", $k)]) ? encrypt(stripslashes($_POST[str_replace(" ", "_", $n)."_".str_replace(" ", "_", $k)])) : $v;
			config($n, $d);
		}
		die("تنظيمات بروزرساني شد");
	}
	private function PMP($i) {
		$q = mysql_query("select type, `delete`, `from`, `to` from {$this->DB->message} where id='$_POST[id]' and (`to`=".logedIn." or `from`=".logedIn.")");
		return (mysql_result($q, 0) == 4 && mysql_result($q, 3) == logedIn) || (mysql_result($q, 1) == 1 && mysql_result($q, 2) == logedIn) ? 0 : 1;
	}
	private function PMU($i) {
		static $UserId = array();
		$s = "select username from {$this->DB->user} where id=$i";
		$q = isset($UserId[$s]) ? $UserId[$s] : $UserId[$s] = mysql_query($s);
		return '"'.(mysql_num_rows($q) ? mysql_result($q, 0) : "").'"';
	}
	private function PMI($u) {
		return mysql_num_rows($q=mysql_query("select id from {$this->DB->user} where username='$u'")) ? mysql_result($q, 0) : 0;
	}
	private function PMW($w) {
		$q = mysql_query("select id, type, `delete`, `from`, `to`, time, subject, author, email, url from {$this->DB->message} where $w order by id desc");
		while ($r=mysql_fetch_assoc($q))
			$c .= "[$r[id], $r[type], $r[delete], [$r[from], ".$this->PMU($r["from"])."], [$r[to], ".$this->PMU($r["to"])."], '".my_date($r["time"])."', '".my_time($r["time"])."', ".js($r["subject"]).", '', ".js($r["author"]).", '$r[email]', '$r[url]'], ";
		die("new Array(".substr($c, 0, -2).")");
	}
	public function PM() {
		global $DB;
		switch ($_POST["action"]) {
			case "send":
				$_POST["type"] == 2 ? contact($_POST["to"], $_POST["subject"], html($_POST["content"], "convert"), "", user("email"), user("url")) : mysql_query("insert into $DB->message values('', ".($_POST["draft"] ? 5 : 0).", 0, ".logedIn.", '".($_POST["type"] == 1 ? $_POST["to"] : $this->PMI($_POST["to"]))."', ".Time.", '$_POST[subject]', '".html($_POST["content"], "convert")."', '".user("name")."', '".user("email")."', '".user("url")."', '".IP."')");
				die($_POST["draft"] ? ".پيام مورد نظر در پيش نويس ذخيره شد" : ".پيام شما با موفقيت ارسال شد");
			case "update":
				$this->PMP($_POST["id"]) or die();
				$DB->update($DB->message, array("type" => $_POST["draft"] ? 5 : 0, "to" => $this->PMI($_POST["to"]), "time" => Time, "subject" => $_POST["subject"], "content" => html($_POST["content"], "convert"), "ip" =>  IP), "id=$_POST[id]");
				die($_POST["draft"] ? ".پيام مورد نظر ويرايش شد" : ".پيام شما با موفقيت ارسال شد");
			case "remove":
				$this->PMP($_POST["id"]) or die();
				$DB->update($DB->message, array("type" => $_POST["type"]), "id=$_POST[id]");
				die($_POST["type"] == 0 ? ".پيام مورد نظر ارسال مجدد شد" : ($_POST["type"] == 2 ? ".پيام مورد نظر ذخيره شد" : ($_POST["type"] == 1 ? ".پيام مورد نظر بازگرداني شد" : ".پيام مورد نظر در سطل بازيافت قرار گرفت")));
			case "delete":
				$this->PMP($_POST["id"]) or die();
				$q = mysql_query("select type, `delete`, `to` from $DB->message where id=$_POST[id]");
				mysql_result($q, 0) == 4 || mysql_result($q, 1) == 1 ? $DB->delete($DB->message, "id=$_POST[id]") : $DB->update($DB->message, mysql_result($q, 2) == logedIn ? array("type" => 4) : array("delete" => 1), "id=$_POST[id]");
				die(".پيام مورد نظر حذف شد");
			case "show":
				$this->PMP($_POST["id"]) or die();
				$_POST["old"] or $DB->update($DB->message, array("type" => 1), "id=$_POST[id]");
				die(html(mysql_result(mysql_query("select content from $DB->message where id=$_POST[id]"), 0), $_POST["source"] ? "source" : ""));
			case "inbox":
				$this->PMW("`to`=".logedIn." and type<2");
			case "save":
				$this->PMW("`to`=".logedIn." and type=2");
			case "trash":
				$this->PMW("`to`=".logedIn." and type=3");
			case "sent":
				$this->PMW("`from`=".logedIn." and `delete`!=1");
			case "draft":
				$this->PMW("`from`=".logedIn." and type=5");
			case "outbox":
				$this->PMW("`from`=".logedIn." and type=0");
		}
	}
	function change_status() {
		if (($_POST["table"] == $this->DB->user && level("user")) || ($_POST["table"] == $this->DB->plugin && level("plugin")) || ($_POST["table"] == $this->DB->post && level("editor")) || ($_POST["table"] == $this->DB->comment && (level("comment") || level("guestbook"))))
			die(status($_POST["table"], $_POST["id"], 1, 1));
	}
	function result() {
		if (isset($this->result))
			die($this->result);
	}
}
class IXR {
	var $server, $port, $path, $useragent = 'Incutio XML-RPC -- Raha', $timeout = 3, $error = false, $xml;
	function __construct($server) {
		$bits = parse_url($server);
		$this->server = $bits['host'];
		$this->port = isset($bits['port']) ? $bits['port'] : 80;
		$this->path = !empty($bits['path']) ? $bits['path'] : '/';
	}
	function query() {
		$r = "\r\n";
		$args = func_get_args();
		$method = array_shift($args);
		foreach ($args as $arg)
			$this->xml .= "<param><value><string>$arg</string></value></param>";
		$xml = "<?xml version='1.0'?><methodCall><methodName>$method</methodName><params>$this->xml</params></methodCall>";
		$request = "POST {$this->path} HTTP/1.0{$r}Host: {$this->server}{$r}Content-Type: text/xml{$r}User-Agent: {$this->useragent}{$r}Content-length: ".strlen($xml)."$r$r$xml";
		$fp = $this->timeout ? @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout) : @fsockopen($this->server, $this->port, $errno, $errstr);
		if (!$fp) {
			$this->error = "خطا در ایجاد ارتباط: $errno $errstr";
			return false;
		}
		fputs($fp, $request);
		fclose($fp);
		return true;
	}
}
class Cache {
	protected $path, $expire;
	public function __construct($p="_", $e=3600) {
		if (!is_writable($p)) {
			mkdir($p);
			chmod($p, 0766);
			$h = fopen("./$p/.htaccess", "w");
			fwrite($h, "<Files *>\n\tOrder Allow, Deny\n\tDeny from All\n</Files>");
			fclose($h);
		}
		$this->path = "./$p/";
		$this->expire = $e;
	}
	public function set($f, $v) {
		if ($h=fopen($this->path.$f, "w")) {
			fwrite($h, serialize($v));
			fclose($h);
			chmod($this->path.$f, 0666);
			return $v;
		}
		return false;
	}
	public function time($f, $m=1, $e=null) {
		return file_exists($this->path.$f) ? ($m ? (Time < filemtime($this->path.$f)+(isset($e) ? $e : $this->expire)) : filemtime($this->path.$f)) : 0;
	}
	public function get($f) {
		return unserialize(file_get_contents($this->path.$f));
	}
	public function delete($f) {
		return unlink($this->path.$f);
	}
}
class TemplateControlPanel extends Jalali {
	function user($hidden, $button, $username, $first_name = "", $last_name = "", $nickname = "", $birthday = "", $gender = "male", $marriage = "single", $eduction = "", $vocation = "", $amusement = "", $favorite = "", $about = "", $avatar = "", $signature = "", $location = "", $phone = "", $email = "", $url = "", $msn = "", $yahoo = "", $gmail = "", $aim = "", $icq = "", $visible = 1, $date_format = "l j F Y", $time_format = "g:i A", $time_zone = +3.5, $start_of_week = 6, $theme = 0, $level = 1, $status = 1, $type = "") {
		global $DB;
		$selected = 1;
		foreach (config("avatar") as $n => $f) {
			$f = config("user->avatar path")."/$f";
			$f != $avatar or $selected = 0;
			$avatars .= '<option value="'.$f.'"'.($f == $avatar ? " selected" : "").'>'.$n.'</option>';
		}
		foreach (config("level->name") as $id => $name)
			$levels .= '<option value='.$id.($level == $id ? " selected" : "").'>'.$name.'</option>';
		for ($i=0, $q=mysql_query("select * from $DB->theme"); $i<mysql_num_rows($q); $i++) {
			$data = theme("data", mysql_result($q, $i));
			$themes .= '<option value='.mysql_result($q, $i).($theme == mysql_result($q, $i) ? " selected" : "").'>'.$data["name"].'</option>';
		}
		return '<form method=post ajax>'.$hidden.'<table border=0 width=300><tr><td width=50%>نام کاربري</td><td width=50%><input class=input name=username dir=ltr size=25 value='.$username.'></td></tr>'.($type != "" ? '<tr><td>کلمه‌ي عبور قبلي</td><td><input class=input name=oldpassword dir=ltr size=25 type=password></td></tr>' : "").'<tr><td>کلمه‌ي عبور جديد</td><td><input class=input name=password dir=ltr size=25 type=password></td></tr><tr><td>تکرار کلمه‌ي عبور</td><td><input class=input name=repassword dir=ltr size=25 type=password></td></tr><tr><td>نام</td><td><input class=input name="first name" value="'.$first_name.'" dir=rtl size=25 lang=fa></td></tr><tr><td>نام خانوادگي</td><td><input class=input name="last name" value="'.$last_name.'" dir=rtl size=25 lang=fa></td></tr><tr><td>لقب</td><td><input class=input name=nickname value="'.$nickname.'" dir=rtl size=25 lang=fa></td></tr><tr><td>تاريخ تولد</td><td><input class=input name=birthday value="'.$birthday.'" dir=ltr size=25></td></tr><tr><td>جنسيت</td><td><select class=select name=gender><option value=0></option><option value=1'.($gender == 1 ? " selected" : "").'>مرد</option><option value=2'.($gender == 2 ? " selected" : "").'>زن</option></select></td></tr><tr><td>تاهل</td><td><select class=select name=marriage><option value=0></option><option value=1'.($marriage == 1 ? " selected" : "").'>مجرد</option><option value=2'.($marriage == 2 ? " selected" : "").'>متاهل</option></select></td></tr><tr><td>تحصيلات</td><td><input dir=rtl class=input size=25 name=eduction value="'.$eduction.'" lang=fa></td></tr><tr><td>شغل</td><td><input dir=rtl class=input size=25 name=vocation value="'.$vocation.'" lang=fa></td></tr><tr><td>سرگرمي‌ها</td><td><textarea dir=rtl name=amusement rows=5 class=input cols=25 lang=fa>'.$amusement.'</textarea></td></tr><tr><td>علاقه مندي‌ها</td><td><textarea dir=rtl name=favorite rows=5 class=input cols=25 lang=fa>'.$favorite.'</textarea></td></tr><tr><td>درباره</td><td><textarea dir=rtl name=about rows=5 class=input cols=25 lang=fa>'.$about.'</textarea></td></tr><tr><td>تصوير</td><td><select class=select onchange="if (this.value != \'other\') { toggle(4, \'avatar\', this.value); document.getElementById(\'picture\').src=this.value;toggle(3, \'picture\', \'\'); } else { toggle(3, \'picture\', \'none\'); toggle(4, \'avatar\', \'\'); }">'.$avatars.'<option value=other'.($selected ? " selected" : "").'>ديگر...</option></select><br><input class=input name=avatar value="'.$avatar.'" dir=ltr size=25 onblur="if (this.value != \'\') { document.getElementById(\'picture\').src=this.value; toggle(3, \'picture\', \'\'); } else toggle(3, \'picture\', \'none\')" > <img id=picture src="'.$avatar.'" style="display:'.($avatar == "" ? "none" : "''").'"/></td></tr><tr><td>امضا</td><td><textarea dir=rtl name=signature rows=5 class=input cols=25 maxLength='.config("user->signature length").' '.(config("user->signature html") ? 'editor=active' : "lang=fa").'>'.$signature.'</textarea></td></tr><tr><td>آدرس</td><td><textarea dir=rtl name=location rows=5 class=input cols=25 lang=fa>'.$location.'</textarea></td></tr><tr><td>تلفن</td><td><input class=input name=phone value="'.$phone.'" size=25></td></tr><tr><td>ايميل</td><td><input class=input name=email value="'.$email.'" dir=ltr size=25></td></tr><tr><td>سايت</td><td><input class=input name=url value="'.$url.'" dir=ltr size=25></td></tr><tr><td>msn id</td><td><input class=input name=msn value="'.$msn.'" dir=ltr size=25></td></tr><tr><td>yahoo id</td><td><input class=input name=yahoo value="'.$yahoo.'" dir=ltr size=25></td></tr><tr><td>gmail id</td><td><input class=input name=gmail value="'.$gmail.'" dir=ltr size=25></td></tr><tr><td>aim id</td><td><input class=input name=aim value="'.$aim.'" dir=ltr size=25></td></tr><tr><td>icq id</td><td><input class=input name=icq value="'.$icq.'" dir=ltr size=25></td></tr><tr><td>نمايش آنلاين</td><td>'.element("status", "visible", $visible).'</td></tr><tr><td>قالب تاريخ</td><td><input class=input name="date format" value="'.$date_format.'" dir=ltr size=25></td></tr><tr><td>قالب ساعت</td><td><input class=input name="time format" value="'.$time_format.'" dir=ltr size=25></td></tr><tr><td>منطقه‌ي زماني</td><td><input class=input name="time zone" value="'.$time_zone.'" dir=ltr size=25></td></tr><tr><td>پوسته</td><td><select class=select name=theme><option value=0>پيش فرض</option>'.$themes.'</select></td></tr><tr><td>سطح</td><td><select class=select name=level'.($type == "profile" ? " disabled" : "").'>'.$levels.'</select></td></tr><tr><td>وضعيت</td><td><select class=input name=status'.($type == "profile" ? " disabled" : "").'><option value=1'.($status ? " selected" : "").'>فعال</option><option value=0'.($status ? "" : " selected").'>غير فعال</option></select></td></tr><tr><td colspan=1 align=center><input class=input type=submit value="'.$button.'" /></td></td></table></form>';
	}
	function user_page() {
		global $DB;
		switch($_GET["action"]) {
			case "add":
				return $this->user('<input type=hidden name=do value=add_user>', "افزودن", '""');
			case "archive":
				$q = mysql_query("select id,username,nickname,email,url,`last login` from $DB->user order by `last login` desc");
				while ($r=mysql_fetch_assoc($q)) {
					$id = $r["id"];
					$name = $r["nickname"] ? $r["nickname"] : $r["username"];
					if ($r["url"]) {
						$href = $r["url"];
						$title = $r["email"];
					} else {
						$href = "mailto:$r[email]";
						$title = "";
					}
					$content .= $this->item('آخرين ورود <a href="'.$href.'" title="'.$title.'">'.$name.'</a>، '.my_date($r["last login"]).' در '.my_time($r["last login"]).' بوده است - [<a href="#" onclick="return load(\'CP&page=manage&part=user&action=edit&id='.$id.'\', \''.$id.'\')">ويرايش</a>] [<a href="#" onclick="return request(\'do=change_status&table='.$DB->user.'&id='.$id.'\',\''.$id.'\')">'.status($DB->user, $id).'</a>] [<a href="#" onclick="return ADI(\'do=delete_user&id='.$id.'\',\''.$id.'\',\'آيا مطمئن از حذف حساب کاربري '.$name.' هستيد؟\')">حذف</a>]', $id);
				}
				return $content;
			case "edit":
			case "profile":
				$id = $_GET["action"] == "edit" ? $_GET["id"] : logedIn;
				$data = mysql_fetch_assoc(mysql_query("select * from $DB->user where id='$id'"));
				return $this->user($_GET["action"] == "edit" ? '<input type=hidden name=do value=update_user><input type=hidden name=id value="'.$id.'">' : '<input type=hidden name=do value=profile>', 'بروزرساني', '"'.$data["username"].'" disabled', $data["first name"], $data["last name"], $data["nickname"], $data["birthday"], $data["gender"], $data["marriage"], $data["eduction"], $data["vocation"], $data["amusement"], $data["favorite"], $data["about"], $data["avatar"], $data["signature"], $data["location"], $data["phone"], $data["email"], $data["url"], $data["msn"], $data["yahoo"], $data["gmail"], $data["aim"], $data["icq"], $data["visible"], $data["date format"], $data["time format"], $data["time zone"], $data["start of week"], $data["theme"], $data["level"], $data["status"], $_GET["action"]);
		}
	}
	function category() {
		global $DB;
		$content = 'var categoryList = [';
		for ($q=mysql_query("select * from $DB->category order by id"); $r=mysql_fetch_assoc($q); $c = ",")
			$content .= $c."[$r[id], '$r[name]', ".js($r["description"]).", $r[parent], ".js(user("convert", "id", "username", $r["moderator"], "\r\n")).", '$r[icon]', $r[status]]";
		$content .= '];var avatarList = [';
		foreach (images(config("category->icon path")) as $i => $n)
			$content .= ($i ? ', ' : '').'"'.$n.'"';
		$content .= <<<CODE
];
toggle(3, "CPS", "none");
category();
var categoryRow = 0, categoryElements = [["text", "", {"value" : "جديد", "size" : 1, "readonly" : 1}], ["text", "name", {"size" : 15, "lang" : "fa"}], ["textarea", "description", [20, "auto"], {"lang" : "fa"}], "category('outPut', categoryRow, 'new', 'object')", ["textarea", "moderator", [15, "auto"]], "category('avatar', 'new_avatar_'+categoryRow, 'object')", ["select", "status", [["فعال", 1], ["غير فعال", 0], ["قفل", 2]]], ["checkbox", "", {"disabled" : 1}]];
function category(action, id, parent, i) {
	var list = categoryList;
	switch (action) {
		case "outPut":
			var i, p, n, type = i, code = '<select name="'+((parent == "new") ? "new_" : "")+'parent_'+id+'" class=select><option value=0>بدون</option>', id = (parent == "new") ? "" : id;
			for (i=0; i<list.length; i++) {
				if (list[i][3] == 0 && list[i][0] != id) {
					code += '<option value="'+list[i][0]+'" '+((list[i][0] == parent) ? "selected" : "")+'>'+list[i][1]+'</option>';
					for (p=0; p<list.length; p++) {
						if (list[p][3] == list[i][0] && list[p][0] != id) {
							code += '<option value="'+list[p][0]+'" '+((list[p][0] == parent) ? "selected" : "")+'>&nbsp;&nbsp;&nbsp;'+list[p][1]+'</option>';
							for (n=0; n<list.length; n++)
								if (list[n][3] == list[p][0])
									code += (list[n][0] != id) ? '<option value="'+list[n][0]+'" '+((list[n][0] == parent) ? "selected" : "")+'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'+list[n][1]+'</option>' : "";
						}
					}
				}
			}
			code += "</select>";
			return type == "object" ? TOBJ(code) : code;
		case "list":
			str = id;
			if (Array.str) {
				n = 0;
				for (var i in str) {
					n++
					category("list", str[i], i);
				}
			} else {
				element = document.createElement('select');
				element.options[j] = new Option("", "");
				element.options[j].setAttribute("", "");
			}
			return element;
		case "space":
			spaces = "";
			for (i=0; i<id; i++)
				spaces += "&nbsp;";
			return spaces;
		case "insert":
			if (categoryRow>28)
				id.disabled = true;
			document.getElementById("newRow").innerHTML += '<table><tr><td><span id="new_picture_'+categoryRow+'">جديد</span></td><td><input class=input name="new_name_'+categoryRow+'" size="15" lang=fa dir=rtl></td><td><textarea class=textarea style="overflow: hidden" name="new_description_'+categoryRow+'" onfocus="rows=3" onblur="rows=1" cols="20" rows="1" lang=fa></textarea></td><td>'+category("outPut", categoryRow, "new")+'</td><td><textarea style="overflow: hidden" class=textarea name="new_moderator_'+categoryRow+'" onfocus="rows=3" onblur="rows=1" cols="15" rows="1" dir=ltr></textarea></td><td>'+category("avatar", "new_avatar_"+categoryRow)+'</td><td><select class=select name="new_status_'+categoryRow+'"><option value=1>فعال</option><option value=0>غير فعال</option><option value=2>قفل</option></select></td><td><input type=checkbox class=checkbox disabled></td></tr></table>';
			categoryRow++;
			break;
		case "avatar":
			type = i;
			var list = avatarList;
			var value = (typeof(i) != "undefined") ? i : "images/categories/main.gif";
			code = '<select name="'+id+'" onChange="document.getElementById(\'picture_'+parent+'\').src=value" class=select dir=ltr>';
			for (i=0; i<list.length; i++)
				code += '<option value="images/categories/'+list[i]+'" '+(('images/categories/'+list[i] == value) ? 'selected' : '')+'>'+list[i]+'</option>';
			code += '</select>';
			return type == "object" ? TOBJ(code) : code;
		default:
			data = '<form method=post ajax><input type=hidden name=do value="category"><table id="category"><tr><td>تصوير</td><td>نام</td><td>شرح</td><td>خانواده</td><td>مدير</td><td>تصوير</td><td>وضعيت</td><td>حذف</td></tr>';
			for (var i=0; i<list.length; i++)
				data += '<tr><td><img src="'+list[i][5]+'" alt="'+list[i][1]+'" id="picture_'+list[i][0]+'"></td><td><input class=input name="name_'+list[i][0]+'" value="'+list[i][1]+'" size="15" lang=fa dir=rtl></td><td><textarea style="overflow: hidden" class=textarea name="description_'+list[i][0]+'" onfocus="rows=3" onblur="rows=1" cols="20" rows="1" lang=fa>'+list[i][2]+'</textarea></td><td>'+category("outPut", list[i][0], list[i][3])+'</td><td><textarea style="overflow: hidden" class=textarea name="moderator_'+list[i][0]+'" onfocus="rows=3" onblur="rows=1" cols="15" rows="1" dir=ltr>'+list[i][4]+'</textarea></td><td>'+category("avatar", "avatar_"+list[i][0], list[i][0], list[i][5])+'</td><td><select class=select name="status_'+list[i][0]+'"><option value=1 '+((list[i][6] == 1) ? "selected" : "")+'>فعال</option><option value=0 '+((list[i][6] == 0) ? "selected" : "")+'>غير فعال</option><option value=2 '+((list[i][6] == 2) ? "selected" : "")+'>قفل</option></select></td><td><input type=checkbox class=checkbox name="delete_'+list[i][0]+'"></td></tr>';
			data += '</table><div id="newRow"></div><div align=center><input type=button class=button onclick="category(\'insert\', this)" value="درج رديف"> <input type=submit class=button value="بروزرساني"></div></form>';
			toggle(4, "CPD", data);
	}
	return false;
}
#JS
CODE;
		return $content;
	}
	function avatar() {
		$path = config("user->avatar path");
		$content = 'var list = {';
		foreach (config("avatar") as $name => $url) {
			$content .= ($i ? ", " : "")."\"$name\" : \"$url\"";
			$i++;
		}
		$content .= '};var avatarList = [';
		$imaes = images($path);
		$count = count($imaes)-1;
		foreach ($imaes as $i => $file)
			$content .= '"'.$file.'"'.($count != $i ? ', ' : '');
		$content .= <<<HTML
];
avatarElements = [["text", "", {"value" : "جديد", "size" : 1, "readonly" : 1}], ["text", "name", {"size" : 30, "lang" : "fa"}], "avatar()", ["checkbox", "", {"disabled" : 1}]];
function avatar(id, value) {
	code = '<select class=select name="url[]" dir=ltr onchange="document.getElementById(\'picture_'+id+'\').src=\'$path/\'+value">';
	for (var i=0; i<avatarList.length; i++)
		code += '<option value="'+avatarList[i]+'" '+((avatarList[i] == value) ? 'selected' : '')+'>'+avatarList[i]+'</option>';
	code += '</select>';
	return code;
}
data = '<form method=post ajax><input type=hidden name=do value=avatar><table border=0 id=avatar><tr><td>تصوير</td><td>نام</td><td>آدرس</td><td>حذف</td></tr>';
j = 0;
for (var i in list) {
	data += '<tr><td><img src="$path/'+list[i]+'" id="picture_'+j+'"/></td><td><input class=input name="name[]" value="'+i+'" size=30 lang=fa></td><td>'+avatar(j, list[i])+'</td><td><input type=checkbox class=checkbox name="delete[]" value=1></td></tr>';
	j++;
}
data += '<tr><td>جديد</td><td><input class=input name="name[]" size=30 lang=fa></td><td>'+avatar()+'</td><td><input type=checkbox class=checkbox disabled></td></tr><tr><td colspan=5 align=center><input type=button class=button onclick="insertRow(\'avatar\', avatarElements, \'[name][]\'+avatarRow, -1)" value="درج رديف"> <input type=submit class=button value="بروزرساني"></td></tr></table></form>';
toggle(4, "CPD", data);#JS
HTML;
		return $content;
	}
	function item($c = "", $i = "") {
		return '<div class=us_wlist id="'.$i.'"><div class=us_wlist_in>'.$c.'</div></div>';
	}
	function level($hidden, $button, $name = "", $access = "", $id = 0) {
		global $CP;
		foreach ($CP->access as $k => $v)
			$accesses .= '<br><input type=checkbox name="'.str_replace(" ", ":_:", $v).'" id="'.$v.'" class=checkbox '.(level($v, $id) ? "checked" : "").'><label for="'.$v.'">'.$k.'</label>';
		return '<form method=post action="?CP&page=manage&part=level&action=archive" ajax'.($id != 0 ? '="'.$id.'"' : "").'>'.$hidden.'<table border=0 width="60%"><tr><td>نام</td><td><input class=input name=name size=50 value="'.$name.'" lang=fa></td></tr><tr><td>دسترسي‌ها</td><td>'.$accesses.'</td></tr></table><input type=submit class=button value="'.$button.'"></form>';
	}
	function level_page() {
		global $CP;
		switch ($_GET["action"]) {
			case "archive":
				foreach (config("level->name") as $id => $name) {
					$accesses = "";
					foreach ($CP->access as $k => $v)
						if (level($v, $id))
							$accesses .= $k."<br>";
					$c.= $this->item("نام : ".$name.' - [<a href="#" onClick="return load(\'CP&page=manage&part=level&action=edit&id='.$id.'\', \''.$id.'\')">ويرايش</a>][<a href="#" onclick="return ADI(\'do=delete_level&id='.$id.'\','.$id.',\'آيا مطمئن از حذف سطح '.$name.' هستيد ؟\')">حذف</a>]<br>دسترسي‌ها : <ul>'.$accesses.'</ul>', $id);
				}
				return $c;
			case "add":
				return $this->level('<input type=hidden name=do value="add_level">', "افزودن");
			case "edit":
				return $this->level('<input type=hidden name=do value="update_level"><input type=hidden name=id value="'.$_GET["id"].'">', "بروزرساني", config("level->name->$_GET[id]"), config("level->access->$_GET[id]"), $_GET["id"]);
		}
	}
	function plugin($hidden, $button, $source="", $type="", $status=1, $id=0) {
		return '<form method=post action="?CP&page=plugin&part=manage&action=archive" ajax'.($id ? '="'.$id.'"' : "").'>'.$hidden.'<table border=0 width=300><tr><td>محتويات</td><td><textarea class=textarea name=source rows=30 cols=90 dir=ltr>'.$source.'</textarea></td></tr><tr><td>نوع</td><td><select class=input name=type><option value=php '.($type == "php" ? "selected" : "").'>PHP</option><option value=js '.($type == "js" ? "selected" : "").'>JavaScript</option><option value="lang">زبان</option></select></td></tr><tr><td>وضعيت</td><td><select class=input name=status><option value=1'.($status == 1 ? " selected" : "").'>فعال</option><option value=0'.($status == 0 ? " selected" : "").'>غير فعال</option></select></td></tr></table><input class=input type=submit value="'.$button.'"></form>';
	}
	function plugin_page() {
		global $DB;
		switch($_GET["action"]) {
			case "add":
				return $this->plugin('<input type=hidden name=do value="add_plugin">', "درج");
			case "archive":
				for ($i=0, $q=mysql_query("select * from $DB->plugin order by id"); $i<mysql_num_rows($q); $i++) {
					$id = mysql_result($q, $i);
					if ($id == 1)
						continue;
					$data = mysql_result($q, $i, 1);
					preg_match("|Name:(.*)|i", $data, $name);
					preg_match("|URL:(.*)|i", $data, $url);
					preg_match("|Description:(.*)|i", $data, $description);
					preg_match("|Date:(.*)|i", $data, $date);
					preg_match("|Version:(.*)|i", $data, $version);
					preg_match("|Author:(.*)|i", $data, $author);
					preg_match("|Author Email:(.*)|i", $data, $author_email);
					preg_match("|Author URL:(.*)|i", $data, $author_url);
					$data = array("name" => trim($name[1]), "url" => trim($url[1]), "description" => trim($description[1]), "date" => trim($date[1]), "version" => trim($version[1]), "author" => trim($author[1]), "author email" => trim($author_email[1]), "author url" => trim($author_url[1]));
					$c .= $this->item('نام: <a href="'.$data["url"].'" target="_blank" title="ورژن '.$data["version"].'">'.$data["name"].'</a> - مؤلف: <a href="'.$data["author url"].'" title="'.$data["author email"].'" target="_blank">'.$data["author"].'</a> - [<a href="#" onclick="return load(\'CP&page=plugin&part=manage&action=edit&id='.$id.'\', \''.$id.'\')">ويرايش</a>] [<a href="#" onclick="return request(\'do=change_status&table='.$DB->plugin.'&id='.$id.'\',\''.$id.'\')">'.status($DB->plugin, $id).'</a>] [<a href="#" onclick="return ADI(\'do=delete_plugin&id='.$id.'\',\''.$id.'\',\'آيا مطمئن از حذف پلاگين '.$data["name"].' هستيد ؟\');">حذف</a>]<br>شرح : '.$data["description"], $id);
				}
				return $c;
			case "edit":
				$r = mysql_fetch_assoc(mysql_query("select * from $DB->plugin where id='$_GET[id]'"));
				return $this->plugin("<input type=hidden name=do value=update_plugin><input type=hidden name=id value=$r[id]>", "بروز رساني", htmlspecialchars($r["source"]), $r["type"], $r["status"], $r["id"]);
		}
	}
	function theme_page() {
		global $DB;
		switch($_GET["action"]) {
			case "add":
				for ($i=1, $q=mysql_query("show fields from $DB->theme"); $i<mysql_num_rows($q); $i++)
					$c .= '<tr><td>'.mysql_result($q, $i).'</td><td><textarea class=textarea cols=100 rows=30 name="'.mysql_result($q, $i).'" dir=ltr></textarea></td></tr>';
				return '<form method=post action="?CP&page=manage&part=theme&action=archive" ajax><input type=hidden name=do value=add_theme><table>'.$c.'</table><input type=submit class=button value="درج"></form>';
			case "archive":
				$s = config("site");
				$s = $s["theme"];
				for ($i=0, $q=mysql_query("select * from $DB->theme"); $i<mysql_num_rows($q); $i++) {
					$id = mysql_result($q, $i);
					$data = theme("data", $id);
					$c .= $this->item('نام پوسته : <a href="'.$data["url"].'" title="'.trim($version[1]).'" target="_blank">'.$data["name"].'</a> - نويسنده : <a href="'.$data["author url"].'" target="_blank">'.$data["author"].'</a> - [<a href="#" onclick="return load(\'CP&page=manage&part=theme&action=edit&id='.$id.'\', \''.$id.'\')">ويرايش</a>] [<a href="#" onclick="return request(\'do=select_theme&id='.$id.'\', \''.$id.'\');">'.(mysql_result($q, $i) == $s ? "فعال" : "غير فعال").'</a>] [<a href="#" onclick="return ADI(\'do=delete_theme&id='.$id.'\',\''.$id.'\',\'آيا مطمئن از حذف پوسته‌ي '.$data["name"].' هستيد؟\')">حذف</a>]<br>شرح : '.$data["description"], $id);
				}
				return $c;
			case "edit":
				$id = $_GET["id"];
				for ($i=1, $q=mysql_query("select * from $DB->theme where id='$id'"); $i<mysql_num_fields($q); $i++)
					$c .= '<tr><td>'.mysql_field_name($q, $i).'</td><td width=85%><textarea class=textarea cols=100 rows=30 name="'.mysql_field_name($q, $i).'" dir=ltr>'.htmlspecialchars(mysql_result($q, 0, $i)).'</textarea></td></tr>';
				return '<form method=post action="?CP&page=manage&part=theme&action=archive" ajax="'.$id.'"><input type=hidden name=do value="update_theme"><input type=hidden name=id value="'.$id.'"><table>'.$c.'</table><input type=submit class=button value="بروزرساني"></form>';
		}
	}
	function emoticon() {
		$data = config("emoticon");
		$content = "var list = [";
		foreach ($data["data"]["code"] as $k => $v)
			$content .= ($k ? ", " : "")."[".js($v).", \"{$data[data][url][$k]}\", ".js($data["data"]["description"][$k]).", {$data[data][state][$k]}]";
		$content .= <<<HTML
];
emoticonElements = [["text", "", {"value" : "جديد", "size" : 1, "readonly" : 1}], ["text", "code", {"size" : 20, "dir" : "ltr"}], ["textarea", "description", [20, "auto"], {"lang" : "fa"}], ["text", "url", {"size" : 30, "dir" : "ltr"}], ["select", "state", [["اول", 1], ["دوم", 0]]]];
data = '<form method=post ajax><input type=hidden name=do value="emoticon"><table cellspacing=0 border=0 id="emoticon"><tr><td align=center>تصوير</td><td>کد</td><td>شرح</td><td>آدرس</td><td>موقعيت</td></tr>';
for (i=0; i<list.length; i++) {
	code = list[i][0];
	code = code.replace('"', "&quot;");
	data += '<tr><td align=center><img border=0 src="$data[path]'+list[i][1]+'" alt="'+list[i][2]+'" id='+i+'></td><td><input class=input name="code[]" value="'+code+'" size="20" dir=ltr></td><td><textarea class=textarea style="overflow: hidden" onfocus="this.rows=3;this.style.position=\'absolute\';this.style.overflow=\'auto\'" onblur="this.rows=1;this.style.position=\'static\';this.style.overflow=\'hidden\'" cols="20" rows="1" name="description[]" lang=fa>'+list[i][2]+'</textarea></td><td><input class=input name="url[]" value="'+list[i][1]+'" size=30 dir=ltr onblur="document.getElementById(\''+i+'\').src=\'$data[path]\'+this.value"></td><td><select class=input name="state[]"><option value="1" '+((list[i][3] == 1) ? "selected" : "")+'>اول</option><option value="0" '+((list[i][3] == 0) ? "selected" : "")+'>دوم</option><option value="delete">حذف</option></select></td></tr>';
}
data += '</table><input type=button class=button onclick="insertRow(\'emoticon\', emoticonElements, \'[name][]\')" value="درج رديف"> <input type=submit class=button value="بروزرساني"></form>';
toggle(4, "CPD", data);#JS
HTML;
		return $content;
	}
	function CQ($postID, $status) {
		global $DB;
		return mysql_query("select * from $DB->comment where `post id`$postID and status=$status order by time desc");
	}
	function guestbook() {
		global $DB;
		switch ($_GET["action"]) {
			case "edit":
				$id = $_GET["id"];
				$q = mysql_query("select * from $DB->comment where id='$id'");
				mysql_result($q, 0, 9) == 1 ? $active = "selected" : (mysql_result($q, 0, 9)>1 ? $private = "selected" : $inactive = "selected");
				return '<form method=post action="?CP&page=manage&part=guestbook&action=archive" ajax="'.$id.'"><input type=hidden name=do value=update_guestbook><input type=hidden name=id value='.$id.'><table><tr><td>نام</td><td><input class=input name=author value="'.mysql_result($q, 0, 3).'" size=30 lang=fa></td></tr><tr><td>پيام</td><td><textarea class=input cols=75 rows=5 name=content id=content lang=fa editor=active>'.mysql_result($q, 0, 4).'</textarea></td></tr><tr><td>ايميل</td><td><input class=input name=email value="'.mysql_result($q, 0, 5).'" size=30 dir=ltr></td></tr><tr><td>سايت</td><td><input class=input name=url value="'.mysql_result($q, 0, 6).'" size=30 dir=ltr></td></tr><tr><td>آدرس</td><td><input class=input name=ip value="'.mysql_result($q, 0, 7).'" size=30 dir=ltr></td></tr><tr><td>تاريخ</td><td><input class=input name=date value="'.mydate(mysql_result($q, 0, 8)).'" size=30 dir=ltr></td></tr><tr><td>وضعيت</td><td><select class=input name=status><option value=1 '.$active.'>فعال</option><option value=0 '.$inactive.'>غير فعال</option><option value=2 '.$private.'>خصوصي</option></select></td></tr></table><div align=center><input type=submit class=button value="بروزرساني"></div></form>';
			default:
				for ($a=$_GET["action"], $q=$this->CQ("=0", $a == "inactive" ? 0 : ($a == "archive" ? 1 : ($a == "private" ? 2 : 3))), $i=0; $i<mysql_num_rows($q); $i++) {
					$id = mysql_result($q, $i);
					if (mysql_result($q, 0, 9) == 2)
						$DB->update($DB->comment, array("status" => 3), "id='$id'");
					$link = user_link(mysql_result($q, $i, 2), mysql_result($q, $i, 3), mysql_result($q, $i, 5), mysql_result($q, $i, 6));
					$c .= $this->item('نام : <a href="'.$link[1].'" title="'.$link[2].'">'.$link[0].'</a> - '.my_date(mysql_result($q, $i, 8)).'در ساعت : '.my_time(mysql_result($q, $i, 8)).' - [<a href="#" onclick="return load(\'CP&page=manage&part=guestbook&action=edit&id='.$id.'\', \''.$id.'\')">ويرايش</a>] [<a href="#" onclick="return request(\'do=change_status&table='.$DB->comment.'&id='.$id.'\',\''.$id.'\');">'.status($DB->comment, $id).'</a>] [<a href="#" onclick="return ADI(\'do=delete_guestbook&id='.$id.'\',\''.$id.'\',\'آيا مطمئن از حذف يادبود '.$author.' هستيد ؟\');">حذف</a>]<br>'.html(mysql_result($q, $i, 4)), $id);
				}
				return $c;
		}
		
	}
	function poll($hidden, $button, $question = "", $answers = "", $votes = "", $ips = "", $date = Now, $status = 1, $id = 0) {
		return '<form method=post action="?CP&page=manage&part=poll&action=archive" ajax'.($id ? '="'.$id.'"' : "").'>'.$hidden.'<table><tr><td>سوال</td><td><input class=input name="question" value="'.$question.'" size=75 lang=fa></td></tr><tr><td>گزينه‌ها</td><td><textarea class=textarea cols=75 rows=5 name="answers" lang=fa>'.$answers.'</textarea></td></tr><tr><td>راي‌ها</td><td><textarea class=input cols=75 rows=5 name="votes">'.$votes.'</textarea></td></tr><tr><td>راي دهنده‌ها</td><td><textarea class=input cols=75 rows=5 name="ips" dir=ltr>'.$ips.'</textarea></td></tr><tr><td>تاريخ</td><td><input class=input name=date value="'.$date.'" size="20" dir=ltr><tr><td>وضعيت</td><td><select class=input name=status><option value=1'.($status == 1 ? " selected" : "").'>فعال</option><option value=0'.($status == 0 ? " selected" : "").'>غيرفعال</option></select></td></tr></td></tr></table><div align=center><input type=submit class=button value="'.$button.'"></div></form>';
	}
	function poll_page() {
		global $DB;
		switch($_GET["action"]) {
			case "add":
				return $this->poll('<input type=hidden name=do value="add_poll">', "درج");
			case "edit":
				$id = $_GET["id"];
				$r = mysql_fetch_assoc(mysql_query("select * from $DB->poll where id='$id'"));
				return $this->poll('<input type=hidden name=do value=update_poll><input type=hidden name=id value="'.$id.'">', "بروزرساني", $r["question"], $r["answers"], $r["votes"], $r["ips"], mydate($r["date"]), $r["status"], $id);
			case "archive":
				for ($i=0, $q=mysql_query("select * from $DB->poll"); $i<mysql_num_rows($q); $i++)
					$c .= $this->item(mysql_result($q, $i, 1)." - ".my_date(mysql_result($q, $i, 5))." و ساعت ".my_time(mysql_result($q, $i, 5)).' - [<a href="#" onclick="return request(\'do=select_poll&id='.mysql_result($q, $i).'\',\''.mysql_result($q, $i).'\');">'.status($DB->poll, mysql_result($q, $i)).'</a>] [<a href="#" onclick="return load(\'CP&page=manage&part=poll&action=edit&id='.mysql_result($q, $i).'\', \''.mysql_result($q, $i).'\')">ويرايش</a>] [<a href="#" onclick="return ADI(\'do=delete_poll&id='.mysql_result($q, $i).'\','.mysql_result($q, $i).',\'آيا مطمئن از حذف نظرسنجي '.mysql_result($q, $i, 1).' هستيد؟\')">حذف</a>]', mysql_result($q, $i));
				return $c;
		}
	}
	function linkbox() {
		global $DB;
		switch ($_GET["action"]) {
			case "manage":
				$q = mysql_query("select * from $DB->link order by type asc, status asc");
				while ($r=mysql_fetch_assoc($q))
					$c .= ",[$r[id], ".js($r["name"]).", '$r[url]', ".js($r["description"]).", $r[count], $r[view], '".user("convert", "id", "email", $r["creator"])."', '".mydate($r["time"])."', $r[type], $r[status]]";
				return 'var list = ['.$c.'];toggle(3, "CPS", "none");link(list);#JS';
			case "import":
				return '<form method=post action="?CP&page=manage&part=link&action=manage" ajax><input type=hidden name=do value=import_link><table border=0 width=75% cellspacing=0><tr><td>آدرس OPML </td><td><input class=file name=url size=50 dir=ltr></td></tr><tr><td colspan=2>تنظيمات واردات</td></tr><tr><td>نوع</td><td><select class=select name="type"><option value=0>پيوند</option><option value=1>پيوند روزانه</option><option value=2>تبليغ</option></select></td></tr><tr><td colspan=2><input type=checkbox name=status checked>وضعيت لينک‌ها فعال باشد</td></tr></table><input type=submit class=button value="ورود"></form>';
		}
	}
	function config() {
		global $DB;
		$_ = array(
			"site" => array("title" => "عنوان", "name" => "نام", "url" => "آدرس", "server path" => "مسير فايل", "description" => "شرح", "keywords" => "کلمات کليدي", "author" => "نويسنده", "email" => "پست الکترونيک", "create date" => "تاريخ ايجاد", "date format" => "قالب تاريخ", "time format" => "قالب زمان", "time zone" => "منطقه‌ي زماني", "main page" => "قالب صفحه اصلي", "cache" => "ذخيره گاه", "charset" => "نوع کد گذاري", "excerpt length" => "گلچين کردن کلمه"),
			"image" => array("background color" => "رنگ پس زمينه", "border color" => "رنگ حاشيه", "foreground color" => "رنگ پيش زمينه", "icon content" => "محتوي آيکن", "icon background color" => "رنگ پس زمينه آيکن", "icon foreground color" => "رنگ پيش زمينه آيکن", "line color" => "رنگ خط کشيدن", "length" => "طول کد", "line color" => "رنگ خط کشيدن", "line number" => "تعداد خط کشيدن", "site name" => "نام سايت", "template" => "قالب"),
			"cookie" => array("expires" => "مدت زمان انقضا", "path" => "مسير ذخيره سازي", "domain" => "دامنه", "secure" => "ارسال از طريق اتصالات امن HTTP"),
			"smtp" => array("status" => "استفاده از SMTP براي ارسال ايميل", "host" => "نام هاست", "username" => "نام کاربري", "password" => "کلمه عبور"),
			"emoticon" => array("convert" => "جايگزين شدن", "path" => "مسير"),
			"comment" => array("html" => "وضعيت HTML", "status" => "وضعيت ثبت", "per feed" => "متوسط تعداد نمايش در خروجي"),
			"category" => array("default" => "دسته بندي پيش فرض", "default icon" => "آيکن پيش فرض", "icon path" => "مسير آيکن"),
			"template" => array("message" => "پيام", "coming soon" => "در حال بازسازي", "banned ip" => "افراد اخراج شده"),
			"message" => array("size" => "حجم", "html" => "وضعيت HTML"),
			"post" => array("template" => "قالب دانلود", "archive" => "نوع آرشيو", "ping service" => "سرويس‌هاي پينگ شونده", "ping status" => "وضعيت پينگ", "max ratings" => "تعداد درجه‌ها", "html" => "وضعيت HTML"),
			"link" => array("per page" => "متوسط تعداد نمايش در صفحه", "per feed" => "متوسط تعداد نمايش در خروجي", "status" => "وضعيت ثبت", "theme" => "قالب", "template" => "قالب جاوااسکريپت"),
			"user" => array("membership status" => "وضعيت عضويت", "membership subject" => "عنوان پيام عضويت", "membership message" => "پيام عضويت", "membership level" => "سطح عضويت", "avatar dimensions" => "ابعاد تصوير", "avatar size" => "حجم تصوير", "avatar path" => "مسير تصاوير", "signature length" => "طول امضا", "signature html" => "وضعيت HTML", "timeout" => "زمان خارج شدن"),
		);
		$s = array("link" => array("time" => "تاريخ"), "comment" => array("time" => "تاريخ"), "guestbook" => array("time" => "تاريخ"), "category" => array("id" => "شناسه"), "post" => array("id" => "شناسه", "date" => "تاريخ"));
		$l = array("link" => "پيوند", "smtp" => "SMTP", "cookie" => "COOKIE", "category" => "بخش", "site" => "سايت", "comment" => "نظرات", "guestbook" => "دفتريادبود", "emoticon" => "صورتک", "template" => "قالب", "security code" => "کد امنيتي", "logo" => "لوگو", "post" => "پست", "user" => "کاربران", "message" => "صندوق پيام");
		$q = mysql_query("select * from $DB->configuration");
		while ($r = mysql_fetch_assoc($q)) {
			$n = $r["name"];
			if (!array_key_exists($n, $l))
				continue;
			$c = array();
			foreach (unserialize($r["value"]) as $k => $v) {
				if (is_array($v) || ($n != "link" && $k == "theme"))
					continue;
				$N = str_replace(" ", "_", $n)."_".str_replace(" ", "_", $k);
				$t = $n == "security code" || $n == "logo" ? $_["image"][$k] : ($n == "guestbook" ? $_["comment"][$k] : $_[$n][$k]);
				if ($k == "order")
					$c["ترتيب نمايش بر اساس"] = element("select", array("name" => $N, "value" => $v), $s[$n]);
				elseif ($k == "author order")
					$c["ترتيب نمايش مؤلف‏ها بر اساس"] = element("select", array("name" => $N, "value" => $v), array("id" => "شناسه"));
				elseif ($k == "sort" || $k == "author sort")
					$c[$k == "sort" ? "مزتب سازي بر اساس" : "مرتب سازي مؤلف‏ها بر اساس"] = element("sort", $N, $v);
				elseif ($k == "cache" || $k == "status" || $k == "convert" || $k == "secure" || $k == "html" || $k == "signature html" || $k == "ping status")
					$c[$t] = element("status", $N, $v);
				elseif ($n == "template" || $k == "theme" || $k == "template" || $k == "membership message" || $k == "ping service")
					$c[$t] = element("textarea", array("name" => $N, "dir" => "ltr"), $v);
				elseif ($k == "membership status")
					$c[$t] = element("select", array("name" => $N, "value" => $v), array(1 => "فعال", 0 => "غيرفعال", 2 => "قفل"));
				elseif ($k == "membership level")
					$c[$t] = element("select", array("name" => $N, "value" => $v), config("level->name"));
				elseif ($k == "default" && $n == "category")
					$c[$t] = category("output", $v, $N);
				elseif ($k == "per page" || $k == "per feed")
					$c["متوسط تعداد نمايش در ".($k == "per page" ? "صفحه" : "خروجي")] = element("text", array("name" => $N, "dir" => "ltr"), $v);
				elseif ($k == "show most last posts")
					$c["بيشترين تعداد نمايش اخرين پست‌ها"] = element("text", array("name" => $N, "dir" => "ltr"), $v);
				elseif ($k == "archive")
					$c[$t] = element("select", array("name" => $N, "value" => $v), array("monthly" => "ماهانه", "daily" => "روزانه", "postbypost" => "پست به پست"));
				elseif ($k == "main page")
					$c[$t] = element("select", array("name" => $N, "value" => $v), array("main" => "صفحه اصلي", "coming soon" => "در حال بازسازي"));
				elseif ($k == "title" || $k == "description" || $k == "keywords" || $k == "author")
					$c[$t] = element($k != "password" ? "text" : "password", array("name" => $N, "lang" => "fa"), $v);
				else
					$c[$t] = element($k != "password" ? "text" : "password", array("name" => $N, "dir" => "ltr"), $v);
			}
			$R .= element("part", array("title" => $l[$r["name"]]), element("table", array("width" => "95%"), $c));
		}
		return "<form method=post ajax><input type=hidden name=do value=configuration>$R<br><input type=submit class=button value=بروزرساني></form>";
	}
	function post($hidden, $button, $title = "", $content = "", $category = "", $date = Now, $ratings = "", $view = 0, $rate_status = 1, $comment_status = 1, $status = 1, $id = 0) {
		return '<form method=post ajax'.($id ? '="'.$id.'"' : "").'>'.$hidden.'<table><tr><td>عنوان </td><td><input class=input name="title" value="'.$title.'" size=75 lang=fa></td></tr><tr><td>مطلب</td><td><textarea class=input cols=100 rows=15 name=content '.(config("post->html") ? 'editor=active' : 'lang=fa').'>'.$content.'</textarea></td></tr><tr><td>موضوع</td><td>'.category("output", $category).'</td></tr><tr><td>تاريخ</td><td><input class=input name=date value="'.$date.'" dir=ltr></td></tr><tr><td>درجه‌ها</td><td><textarea class=textarea name="ratings" dir=ltr cols=23 rows=3>'.$ratings.'</textarea></td></tr><tr><td>مشاهده</td><td><input class=input name="view" value="'.$view.'" dir=ltr></td></tr><tr><td>وضعيت ارزيابي کردن</td><td><select class=input name="rate_status"><option value=1'.($rate_status ? " selected" : "").'>فعال</option><option value=0'.($rate_status ? "" : " selected").'>غير فعال</option></select></td></tr><tr><td>وضعيت نظرات</td><td><select class=input name="comment_status"><option value=1'.($comment_status ? " selected" : "").'>فعال</option><option value=0'.($comment_status ? "" : " selected").'>غير فعال</option></select></td></tr><tr><td>وضعيت ارسال</td><td><select class=input name=status><option value=1'.($status ? " selected" : "").'>فعال</option><option value=0'.($status ? "" : " selected").'>غير فعال</option></select></td></tr></table><input type=submit class=button value="'.$button.'"></form>';
	}
	function post_page() {
		global $DB;
		switch($_GET["part"]) {
			case "post":
				switch ($_GET["action"]) {
					case "add":
						return $this->post('<input type=hidden name=do value=add_post>', "افزودن");
					case "archive":
						for ($i=0, $q=mysql_query("select * from $DB->post where author=".logedIn." order by date desc"); $i<mysql_num_rows($q); $i++)
							$c .= $this->item(mysql_result($q, $i, 1).' - '.my_date(mysql_result($q, $i, 5), 1).' در ساعت : '.my_time(mysql_result($q, $i, 5), 1).' - [<a href="#" onclick="return load(\'CP&page=post&part=post&action=edit&id='.mysql_result($q, $i).'\', \''.mysql_result($q, $i).'\')">ويرايش</a>] [<a href="#" onclick="return ADI(\'do=delete_post&id='.mysql_result($q, $i).'\','.mysql_result($q, $i).',\'آيا مطمئن از حذف مطلب '.mysql_result($q, $i, 1).' هستيد؟\')">حذف</a>]', mysql_result($q, $i));
						return $c;
					case "edit":
						$r = mysql_fetch_array(mysql_query("select * from $DB->post where id='$_GET[id]'"), MYSQL_NUM);
						return $this->post("<input type=hidden name=do value=update_post><input type=hidden name=id value=$_GET[id]>", "بروزرساني", $r[1], htmlspecialchars($r[2]), $r[4], $r[5], $r[7], $r[8], $r[9], $r[10], $r[11], $r[0]);
				}
			case "comment":
				switch ($_GET["action"]) {
					case "edit":
						$id = $_GET["id"];
						$q = mysql_query("select * from $DB->comment where id='$id'");
						mysql_result($q, 0, 9) == 1 ? $active = " selected" : (mysql_result($q, 0, 9)>1 ? $private = " selected" : $inactive = " selected");
						return '<form method=post ajax="'.$id.'"><input type=hidden name=do value=update_comment><input type=hidden name=id value="'.$id.'"><table><tr><td>مطلب</td><td><select name=post_id class=select><option value='.mysql_result($q, 0, 1).'>'.mysql_result(mysql_query("select title from $DB->post where id=".mysql_result($q, 0, 1)), 0).'</option></select></td></tr><tr><td>نام</td><td><input class=input name=author value="'.mysql_result($q, 0, 3).'" size=30 lang=fa></td></tr><tr><td>پيام</td><td><textarea class=input cols=75 rows=5 name=content id=content lang=fa editor=active>'.mysql_result($q, 0, 4).'</textarea></td></tr><tr><td>ايميل</td><td><input class=input name=email value="'.mysql_result($q, 0, 5).'" size=30 dir=ltr></td></tr><tr><td>سايت</td><td><input class=input name=url value="'.mysql_result($q, 0, 6).'" size=30 dir=ltr></td></tr><tr><td>آدرس</td><td><input class=input name=ip value="'.mysql_result($q, 0, 7).'" size=30 dir=ltr></td></tr><tr><td>تاريخ</td><td><input class=input name=date value="'.mydate(mysql_result($q, 0, 8)).'" size=30 dir=ltr></td></tr><tr><td>وضعيت</td><td><select class=select name=status><option value=1'.$active.'>فعال</option><option value=0'.$inactive.'>غير فعال</option><option value=2'.$private.'>خصوصي</option></select></td></tr></table><div align=center><input type=submit class=button value="بروز رساني"></div></form>';
					default:
						for ($a=$_GET["action"], $q=$this->CQ("!=0", $a == "inactive" ? 0 : ($a == "archive" ? 1 : ($a == "private" ? 2 : 3))), $i=0; $i<mysql_num_rows($q); $i++) {
							$id = mysql_result($q, $i);
							$link = user_link(mysql_result($q, $i, 2), mysql_result($q, $i, 3), mysql_result($q, $i, 5), mysql_result($q, $i, 6));
							if (mysql_result($q, 0, 9) == 2)
								$DB->update($DB->comment, array("status" => 3), "id='$id'");
							$c .= $this->item('نام : <a href="'.$link[1].'" title="'.$link[2].'">'.$link[0].'</a> - '.my_date(mysql_result($q, $i, 8)).' در ساعت '.my_time(mysql_result($q, $i, 8)).' - [<a href="#" onclick="return load(\'CP&page=post&part=comment&action=edit&id='.$id.'\', \''.$id.'\')">ويرايش</a>] [<a href="#" onclick="return request(\'do=change_status&table='.$DB->comment.'&id='.$id.'\',\''.$id.'\');">'.status($DB->comment, $id).'</a>] [<a href="#" onclick="return ADI(\'do=delete_comment&id='.$id.'\',\''.$id.'\',\'آيا مطمئن از حذف نظر '.$author.' هستيد ؟\');">حذف</a>]<br>'.html(mysql_result($q, $i, 4)), $id);
						}
						return $c;
				}
			case "import":
				return '<form method=post action="?CP&page=post&part=post&action=archive" ajax><input type=hidden name=do value=import_post><table border=0 width=75% cellspacing=0><tr><td>آدرس RSS </td><td><input class=file name=url size=50 dir=ltr></td></tr><tr><td colspan=2>تنظيمات واردات</td></tr><tr><td colspan=2><input type=checkbox name="rate" checked>وضعيت ارزيابي‌ها فعال باشد <input type=checkbox name="comment" checked>وضعيت نظرها فعال باشد <input type=checkbox name=status checked>وضعيت پست‌ها فعال باشد</td></tr></table><input type=submit class=button value="ورود"></form>';
		}
	}
	function newsletter() {
		switch($_GET["action"]) {
			case "configuration":
				return '<form method=post ajax><input type=hidden name=do value=update_newsletter><table border=0><tr><td>افراد عضو</td></tr><tr><td><textarea class=textarea cols=100 rows=15 id="emails" dir=ltr>'.config("newsletter->emails").'</textarea></td></tr><tr><td>عنوان ارسال</td></tr><tr><td><input id="subject" class=input value="'.config("newsletter->subject").'" /></td></tr><tr><td><input type=checkbox class=checkbox name=status id="status"'.(config("newsletter->status") ? " checked" : "").'><label for="status">هنگامي که نوشته ي جديدي ثبت مي شود، به اعضاي خبرنامه ارسال شود.</label></td></tr><tr><td>قالب نامه‌هاي ارسالي</td></tr><tr><td><textarea class=input cols=100 rows=15 id=tpl editor=active>'.config("newsletter->template").'</textarea></td></tr><tr align=center><td><input type=submit class=button value="بروزرساني"></td></tr></table></form>';
			case "send":
				return '<form method=post ajax><input type=hidden name=do value=send_newsletter><table border=0><tr><td>عنوان :</td><td><input class=input id="title" size=50 lang=fa></td><tr><td>پيام : </td><td><textarea class=input cols=100 rows=15 id="content" lang=fa editor=active></textarea></td></tr><tr align=center><td colspan=2><input type=submit class=button value="ارسال"></td></tr></table></form>';
		}
	}
	function state() {
		global $DB;
		for ($i=0, $q=mysql_query("show table status like '$DB->prefix%'"); $i<mysql_num_fields($q); $i++)
			if (mysql_field_name($q, $i) == "Data_length")
				$Data_length = $i;
			elseif (mysql_field_name($q, $i) == "Index_length")
				$Index_length = $i;
		for ($i=$db_size=0; $i<mysql_num_rows($q); $i++) {
			$b = mysql_result($q, $i, $Data_length)+mysql_result($q, $i, $Index_length);
			$db_size += $b;
			$l .= '<tr bgcolor='.($i%2 ? "#FFFFFF" : "#F8F8F8").'><td width=50%>'.mysql_result($q, $i)."</td><td width=50%>".bulk($b)."</td></tr>";
		}
		$status = array(
			"نسخه‌ي PHP" => phpversion(),
			"نسخه‌ي MySQL" => $DB->info(),
			"نسخه‌ي GD" => gdversion(),
			"Module mod_rewrite" => function_exists("apache_get_modules") ? (array_search("mod_rewrite", apache_get_modules()) ? "روشن" : "خاموش") : "ناشناخته",
			"حجم مصرف شده‌ي پايگاه داده‌ها" => bulk($db_size),
			"حجم مصرف شده‌ي ميزبان" => bulk(dirsize("./"))
		);
		foreach ($status as $name => $value) {
			$c .= '<tr bgcolor='.($r%2 ? "#FFFFFF" : "#F8F8F8").'><td>'.$name.'</td><td>'.$value.'</td></tr>';
			$r++;
		}
		return "<table class=table><tr><td width=50%>نام</td><td width=50%>مقدار</td></tr>$c</table><br><table class=table><tr><td>جدول</td><td>حجم</td></tr>$l</table>";
	}
	function announcement() {
		$data = config("announcement");
		$data["content"] = htmlspecialchars($data["content"]);
		$data["expires"] = mydate($data["expires"]);
		$content = <<<HTML
<form ajax method=post>
	<table>
		<tr>
			<td>عنوان</td>
			<td>محتوي</td>
			<td>نمايش</td>
			<td>کليک</td>
			<td>انقضا</td>
		</tr>
		<tr>
			<td><input class=input name=title value="$data[title]" size=30 lang=fa /></td>
			<td><textarea class=textarea name=content rows=1 cols=30 onfocus=rows=10 onblur=rows=1 editor=active>$data[content]</textarea></td>
			<td><input class=input name=view value="$data[view]" size=10 /></td>
			<td><input class=input name=count value="$data[count]" size=10 /></td>
			<td><input class=input name=expires value="$data[expires]" size=15 dir=ltr /></td>
		</tr>
		<tr>
			<td colspan=5 align=center><input type=hidden name=do value=announcement /><input type=submit class=button value='بروزرساني' /></td>
		</tr>
	</table>
</form>
HTML;
		return $content;
	}
	function badWord() {
		$data = config("bad word");
		$content = "badWordList = {";
		foreach ($data as $word => $replace) {
			$content .= $comma.'"'.$word.'" : "'.$replace.'"';
			$comma = ", ";
		}
		$content .= <<<HTML
};
badWordElements = [["text", "word", {"size" : 30, "lang" : "fa"}], ["text", "replace", {"size" : 30, "lang" : "fa"}]];
var content = '<form ajax method=post><table id="bad word"><tr><td>کلمه</td><td>جايگزين</td></tr>';
for (var word in badWordList)
	content += '<tr><td><input class=input name=word[] value="'+word+'" size=30 lang=fa /></td><td><input class=input name=replace[] value="'+badWordList[word]+'" size=30 lang=fa /></td></tr>';
content += '<tr><td colspan=2 align=center><input type=hidden name=do value="bad_word" /><input type=button class=button value="درج رديف" onclick="insertRow(\'bad word\', badWordElements, \'[name][]\', -1)" /> <input type=submit class=button value=بروزرساني /></td></tr></table></form>';
toggle(4, "CPD", content);
#JS
HTML;
		return $content;
	}
	function bannedIP() {
		$data = config("banned ip");
		$content = "bannedIPList = [";
		foreach ($data["ip"] as $i => $IP) {
			$content .= $comma.'["'.$IP.'", "'.mydate($data["expires"][$i]).'", '.js($data["infraction"][$i]).']';
			$comma = ", ";
		}
		$content .= <<<HTML
];
bannedIPElements = [["text", "IP", {"size" : 20, "dir" : "ltr"}], ["text", "expires", {"size" : 20, "dir" : "ltr"}], ["text", "infraction", {"size" : 50, "lang" : "fa"}]];
var content = '<form ajax method=post><table id="banned ip"><tr><td>آدرس</td><td>انقضا</td><td>تخلف</td></tr>';
for (var i=0; i<bannedIPList.length; i++)
	content += '<tr><td><input class=input name=IP[] value="'+bannedIPList[i][0]+'" size=20 dir=ltr /></td><td><input class=input name=expires[] value="'+bannedIPList[i][1]+'" size=20 dir=ltr /></td><td><input class=input name=infraction[] value="'+bannedIPList[i][2]+'" size=50 lang=fa /></td></tr>';
content += '<tr><td colspan=3 align=center><input type=hidden name=do value="banned_ip" /><input type=button class=button value="درج رديف" onclick="insertRow(\'banned ip\', bannedIPElements, \'[name][]\', -1)" /> <input type=submit class=button value=بروزرساني /></td></tr></table></form>';
toggle(4, "CPD", content);
#JS
HTML;
		return $content;
	}
	function tag() {
		$data = config("tag");
		$content = "tagList = {";
		foreach ($data as $tag => $replace) {
			$content .= $comma.js(str_replace('"', "&quot;", $tag))." : ".js(str_replace('"', "&quot;", $replace));
			$comma = ", ";
		}
		$content .= <<<HTML
};
tagElements = [["text", "tag", {"size" : 30, "dir" : "ltr"}], ["text", "replace", {"size" : 30, "dir" : "ltr"}]];
var content = '<form ajax method=post><table id="tag"><tr><td>تگ</td><td>جايگزين</td></tr>';
for (var tag in tagList)
	content += '<tr><td><input class=input name=tag[] value="'+tag+'" size=30 dir=ltr /></td><td><input class=input name=replace[] value="'+tagList[tag]+'" size=30 dir=ltr /></td></tr>';
content += '<tr><td colspan=2 align=center><input type=hidden name=do value="tag" /><input type=button class=button value="درج رديف" onclick="insertRow(\'tag\', tagElements, \'[name][]\', -1)" /> <input type=submit class=button value=بروزرساني /></td></tr></table></form>';
toggle(4, "CPD", content);
#JS
HTML;
		return $content;
	}
}
if (!empty($_GET["feed"])) {
	header("Content-type: text/xml");
	$header = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<!-- generator=\"Raha\" -->\n";
	if (is_numeric($_GET["id"])) {
		$comment = mysql_query("select * from $DB->comment where id='$_GET[id]'");
		$post = mysql_query("select * from $DB->post where id='$_GET[id]'");
		$link = mysql_query("select * from $DB->link where id='$_GET[id]'");
	} else {
		$comment = mysql_query("select * from $DB->comment where status=1 order by `".config("comment->order")."` ".config("comment->sort").($v = config("comment->per feed") ? " limit $v" : ""));
		$post = mysql_query("select * from $DB->post where status=1 order by `".config("post->order")."` ".config("post->sort").(config("post->per feed") != "" ? " limit ".config("post->per feed") : ""));
		$link = mysql_query("select * from $DB->link where status=1 order by `".config("link->order")."` ".config("link->sort").(config("link->per feed") != "" ? " limit ".config("post->per feed") : ""));
	}
	$content = $item = "";
	switch ($_GET["feed"]) {
		case "comment":
			for ($i=0; $i<mysql_num_rows($comment); $i++)
				$item .= "<item><title>Comment on ".mysql_result(mysql_query("select title from $DB->post where id='".mysql_result($comment, $i, 1)."'"), 0)." by ".mysql_result($comment, $i, 3)."</title><link>".config("site->url")."/comment-".mysql_result($comment, $i, 1).".html#comment-".mysql_result($comment, $i)."</link><pubDate>".date("D, d M Y", mysql_result($comment, $i, 8))."</pubDate><dc:creator>".mysql_result($comment, $i, 3)."</dc:creator><description><![CDATA[".strip_tags(mysql_result($comment, $i, 4))."]]></description><content:encoded><![CDATA[".mysql_result($comment, $i, 4)."]]></content:encoded></item>";
			feed("rss", $item, 'Comments for '.config("site->title"));
		case "rdf":
			$site = config("site");
			$site["create date"] = date("Y-m-d\TH:i:s\Z", strtotime($site["create date"]));
			$linkResource = $item ="";
			for ($i=0; $i<mysql_num_rows($post); $i++) {
				$url = config("site->url")."/post-".mysql_result($post, $i).".html";
				$linkResource .= "\n\t\t\t<rdf:li rdf:resource=\"$url\"/>";
				$item .= "\n<item rdf:about=\"$url\">\n\t<title>".mysql_result($post, $i, 1)."</title>\n\t<link>$url</link>\n\t<dc:date>".date("Y-m-d\TH:i:s\Z", strtotime(mysql_result($post, $i, 5)))."</dc:date>\n\t<dc:creator>".user("name", mysql_result($post, $i, 3))."</dc:creator>\n\t<dc:subject>".category("name", mysql_result($post, $i, 4))."</dc:subject>\n\t<description>".strip_tags(mysql_result($post, $i, 2))."</description>\n\t<content:encoded><![CDATA[".html(mysql_result($post, $i, 2))."]]></content:encoded>\n\t</item>";
			}
			$c = $header.<<<RDF
<rdf:RDF
	xmlns="http://purl.org/rss/1.0/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:admin="http://webns.net/mvcb/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
>
<channel rdf:about="$site[url]">
	<title>$site[title]</title>
	<link>$site[url]</link>
	<description>$site[description]</description>
	<dc:date>{$site['create date']}</dc:date>
	<admin:generatorAgent rdf:resource="https://raha.group"/>
	<sy:updatePeriod>hourly</sy:updatePeriod>
	<sy:updateFrequency>1</sy:updateFrequency>
	<sy:updateBase>2000-01-01T12:00+00:00</sy:updateBase>
	<items>
		<rdf:Seq>$linkResource
		</rdf:Seq>
	</items>
</channel>$item
</rdf:RDF>
RDF;
			die($c);
		case "rss":
			for ($i=0; $i<mysql_num_rows($post); $i++)
				$item .= "<item><title>".mysql_result($post, $i, 1)."</title><link>".config("site->url")."/post-".mysql_result($post, $i).".html</link><comments>".config("site->url")."/comment-".mysql_result($post, $i).".html</comments><pubDate>".date("D, d M Y H:i:s", strtotime(mysql_result($post, $i, 5)))."</pubDate><dc:creator>".user("name", mysql_result($post, $i, 3))."</dc:creator><category>".category("name", mysql_result($post, $i, 4))."</category><description><![CDATA[".strip_tags(mysql_result($post, $i, 2))."]]></description><content:encoded><![CDATA[".html(mysql_result($post, $i, 2))."]]></content:encoded></item>";
			feed("rss", $item);
		case "atom":
			$c = $header.'<feed version="0.3" xmlns="http://purl.org/atom/ns#">
<title>'.config("site->title").'</title> 
<tagline>'.config("site->description").'</tagline> 
<link rel="alternate" type="text/html" href="'.config("site->url").'" /> 
<id>'.config("site->url").'</id> 
<modified>'.date("D, d M Y H:i:s", strtotime(config("site->create date"))).'</modified> 
<generator>Raha</generator>';
			for ($i=0; $i<mysql_num_rows($post); $i++) {
				if (mysql_result($post, $i, 6)) {
					$last_edit = explode(",", mysql_result($post, $i, 6));
					$last_edit = explode(" ", $last_edit[count($last_edit)-1]);
					$modified = date("Y-m-d H:i:s", $last_edit[1]);
				} else
					$modified = mysql_result($post, $i, 5);
				$c .= '<entry>
<title>'.mysql_result($post, $i, 1).'</title> 
<link rel="alternate" type="text/html" href="'.config("site->url")."/post-".mysql_result($post, $i).'.html" /> 
<created>'.date("D, d M Y H:i:s", strtotime(mysql_result($post, $i, 5))).'</created> 
<issued>'.date("D, d M Y H:i:s", strtotime(mysql_result($post, $i, 5))).'</issued> 
<modified>'.date("D, d M Y H:i:s", strtotime($modified)).'</modified> 
<id>'.config("site->url")."/post-".mysql_result($post, $i).'.html</id> 
<summary><![CDATA['.html(mysql_result($post, $i, 2)).']]></summary> 
</entry>';
			}
			die($c."</feed>");
		case "opml":
			for ($i=0; $i<mysql_num_rows($post); $i++)
				$item .= '<outline type="rss" title="'.mysql_result($post, $i, 1).'" text="'.str_replace('"', "″", htmlspecialchars(strip_tags(mysql_result($post, $i, 2))), $source).'" url="'.config("site->url")."/post-".mysql_result($post, $i).'.html"/>';
			feed("opml", $item);
		case "linkRSS":
			for ($i=0; $i<mysql_num_rows($link); $i++)
				$item .= "<item><title>".mysql_result($link, $i, 1)."</title><link>".mysql_result($link, $i, 2)."</link><description>".mysql_result($link, $i, 3)."</description></item>";
			feed("rss", $item, "", "RSS Feed ver 2.0 for ".config("site->name")."'s Blogroll");
		case "linkOPML":
			$t = array("link", "dump link", "advertisment");
			for ($i=0; $i<mysql_num_rows($link); $i++)
				$item .= '<outline type="'.$t[mysql_result($link, $i, 8)].'" text="'.mysql_result($link, $i, 1).'" description="'.mysql_result($link, $i, 3).'" url="'.mysql_result($link, $i, 2).'" />';
			feed("opml", $item, config("site->title")."'s Blogroll");
	}
}
$tcp = new TemplateControlPanel();
$TPL = new Template();
$TPL->load();
if (empty($_SESSION["security code"]))
	securityCode();
if (logedIn) {
	$inavtiveLink = $CP->permission("link") ? mysql_result(mysql_query("select count(*) from $DB->link where status=0"), 0) : 0;
	$newMessage = $CP->permission("message") ? mysql_result(mysql_query("select count(*) from $DB->message where `to`=".logedIn." and type=0"), 0) : 0;
	$comment = $CP->permission("comment") ? array(0 => mysql_result(mysql_query("select count(*) from $DB->comment where status=0 and `post id`!=0"), 0), 2 => mysql_result(mysql_query("select count(*) from $DB->comment where status=2 and `post id`!=0"), 0)) : 0;
	$memory = $CP->permission("guestbook") ? array(0 => mysql_result(mysql_query("select count(*) from $DB->comment where status=0 and `post id`=0"), 0), 2 => mysql_result(mysql_query("select count(*) from $DB->comment where status=2 and `post id`=0"), 0)) : 0;
	$TPL->block("state", $newMessage || (is_array($comment) && ($comment[0] || $comment[2])) || (is_array($memory) && ($memory[0] || $memory[2])) || $inavtiveLink ? array("message" => $newMessage ? array("new" => $newMessage) : array(""), "comment" => is_array($comment) && ($comment[0] || $comment[2]) ? array("inactive" => $comment[0], "private" => $comment[2]) : array(""), "guestbook" => is_array($memory) && ($memory[0] || $memory[2]) ? array("inactive" => $memory[0], "private" => $memory[2]) : array(""), "link" => $inavtiveLink ? array("inactive" => $inavtiveLink) : array("")) : array(""));
	$TPL->block("loged in", array("last login" => array("date" => my_date($_SESSION["last login"]), "time" => my_time($_SESSION["last login"])), "avatar" => $v=user("avatar") ? array("url" => $v) : array(""), "username" => user("name"), "log out" => "logOut.html", "control panel" => "CP.html"));
	$CP->menu("manage", "مديريت", $CP->URL("manage", "counter"), "", "manage");
	$CP->menu("post", "نوشتن", $CP->URL("post", "post", "add"), "", "post");
	$CP->menu("plugin", "افزونه‌ها", $CP->URL("plugin", "manage", "archive"), "", "plugin");
	$CP->menu("account", "حساب کاربري ".($newMessage ? "(".$newMessage.")" : ""), $CP->URL("account", "profile", "profile"), "", "account");
	$CP->subMenu("active", "manage", "منوي اصلي", array($CP->link("configuration", "تنظيمات", $CP->URL("manage", "config")), $CP->link("category", "دسته بندي‌ها", $CP->URL("manage", "category")), $CP->link("emoticon", "شکلک‌ها", $CP->URL("manage", "emoticon")), $CP->link("announcement", "اعلان‌ها", $CP->URL("manage", "announcement")), $CP->link("bad word", "کلمات ناپسند", $CP->URL("manage", "badWord")), $CP->link("banned ip", "افراد اخراج شده", $CP->URL("manage", "bannedIP")), $CP->link("tag", "تگ‌ها", $CP->URL("manage", "tag")), $CP->link("counter", "شمارنده", $CP->URL("manage", "counter")), $CP->link("site state", "وضعيت سايت", $CP->URL("manage", "status")), $CP->link("backup", "پشتيبان پايگاه داده‌ها", "javascript:document.location='backup.html'")), "manage");
	$CP->subMenu("active", "manage", "کاربر", array($CP->link("user", "افزودن", $CP->URL("manage", "user", "add")), $CP->link("user", "آرشيو", $CP->URL("manage", "user", "archive")), $CP->link("user avatar", "تصاوير", $CP->URL("manage", "user", "avatar"))), "user");
	$CP->subMenu("level", "manage", "سطح‌هاي دسترسي", array(array("افزودن", $CP->URL("manage", "level", "add")), array("مديريت", $CP->URL("manage", "level", "archive"))), "level");
	$CP->subMenu("guestbook", "manage", "دفتر يادبود", array(array("تاييد نشده [$memory[0]]", $CP->URL("manage", "guestbook", "inactive")), array("خصوصي جديد [$memory[2]]", $CP->URL("manage", "guestbook", "private")), array("آخرين (عمومي)", $CP->URL("manage", "guestbook", "archive")), array("آخرين (خصوصي)", $CP->URL("manage", "guestbook", "privateArchive"))), "guestbook"); 
	$CP->subMenu("poll", "manage", "نظرسنجي", array(array("افزودن", $CP->URL("manage", "poll", "add")), array("مديريت", $CP->URL("manage", "poll", "archive"))), "poll"); 
	$CP->subMenu("active", "manage", "پيوند", array($CP->link("link", "مديريت [$inavtiveLink]", $CP->URL("manage", "link", "manage")), $CP->link("import link", "واردات", $CP->URL("manage", "link", "import"))), "link"); 
	$CP->subMenu("theme", "manage", "پوسته‌", array(array("افزودن", $CP->URL("manage", "theme", "add")), array("مديريت", $CP->URL("manage", "theme", "archive"))), "theme");
	$CP->subMenu("newsletter", "manage", "خبرنامه", array(array("ارسال خبر", $CP->URL("manage", "newsletter", "send")), array("تنظيمات", $CP->URL("manage", "newsletter", "configuration"))), "newsletter");
	$CP->subMenu("active", "post", "مديريت", array($CP->link("writer", "نوشتن", $CP->URL("post", "post", "add")), $CP->link("writer", "آرشيو", $CP->URL("post", "post", "archive")), $CP->link("import post", "واردات", $CP->URL("post", "import"))), "post");
	$CP->subMenu("comment", "post", "نظرات", array(array("تاييد نشده [$comment[0]]", $CP->URL("post", "comment", "inactive")), array("خصوصي جديد [$comment[2]]", $CP->URL("post", "comment", "private")), array("آخرين (عمومي)", $CP->URL("post", "comment", "archive")), array("آخرين (خصوصي)", $CP->URL("post", "comment", "privateArchive"))), "comment");
	$CP->subMenu("plugin", "plugin", "مديريت", array(array("افزودن", $CP->URL("plugin", "manage", "add")), array("آرشيو", $CP->URL("plugin", "manage", "archive"))), "plugin");
	$CP->subMenu("message", "account", "صندوق پيام‌ها", array(array("ارسال", "javascript:PM.compose(0, '', '', '', '', '')"), array("دريافت شده ".((level("message")) ? "[$newMessage]" : ""), "javascript:PM.get('inbox')"), array("ذخيره شده", "javascript:PM.get('save')"), array("خذف شده", "javascript:PM.get('trash')"), array("ارسال شده", "javascript:PM.get('sent')"), array("پيش نويس", "javascript:PM.get('draft')"), array("آماده ارسال", "javascript:PM.get('outbox')")), "message");
	$CP->subMenu("profile", "account", "مشخصات کاربري", array(array("ويرايش مشخصات", $CP->URL("account", "profile", "profile")), array("حذف حساب کاربري", "javascript:Confirm('آيا از حذف حساب کاربري خود مطمئن هستيد؟', 'location', 'deleteAccount.html')"), array("خروج", "javascript:Confirm('آيا قصد خروج از سايت داريد؟', 'location', 'logOut.html')")), "account");
	$TPL->block("log in");
}
if (isset($_GET["login"]) || !logedIn) {
	$TPL->block("log in", array(
			"username" => '<input name=username class=input size=[value] dir=ltr />',
			"password" => '<input type=password name=password class=input size=[value] dir=ltr />',
			"remember" => '<input type=checkbox class=checkbox name=remember id=remember><label for=remember>[value]</label>',
			"submit" => '<input type=submit class=button value="[value]" />',
			"result" => '<div class=result id=loginResult />'
		),
		'<form method=post ajax=loginResult><input type=hidden name=do value=login><input type=hidden name=referrer value="'.(isset($_GET["login"]) ? $_SERVER["HTTP_REFERER"] : url()).'">',
		"</form>"
	);
	$TPL->block("loged in");
}
if (isset($_GET["CP"]) && logedIn) {
	$TPL->assign(array("username" => user("name"), "log out" => "logOut.html", "refresh" => "return request('do=menu', CP.cfg.content)", "menu" => "CPM", "sub menu" => "CPSM", "result" => "result", "content" => "CPD", "sidebar" => "CPS"));
	$TPL->block("last login", array("date" => my_date($_SESSION["last login"]), "time" => my_time($_SESSION["last login"])));
} elseif (isset($_GET["CP"]))
	die(header("Location:logIn.html"));
for ($q=mysql_query("select source from $DB->plugin where type='php' and status=1"), $i=0; $i<mysql_num_rows($q); $i++)
	@eval(mysql_result($q, $i));
@eval(theme("print", "php script"));
if (logedIn && isset($_GET["CP"])) {
switch ($_GET["page"]) {
	case "account":
		if ($_GET["part"] == "profile" && $_GET["action"] == "profile")
			$CP->page("profile", "account", "profile", "profile", $tcp->user_page());
	case "manage":
		switch ($_GET["part"]) {
			case "config":
				$CP->page("configuration", "manage", "config", "", $tcp->config());
			case "category":
				$CP->page("category", "manage", "category", "", $tcp->category());
			case "emoticon":
				$CP->page("emoticon", "manage", "emoticon", "", $tcp->emoticon());
			case "announcement":
				$CP->page("announcement", "manage", "announcement", "", $tcp->announcement());
			case "badWord":
				$CP->page("bad word", "manage", "badWord", "", $tcp->badWord());
			case "bannedIP":
				$CP->page("banned ip", "manage", "bannedIP", "", $tcp->bannedIP());
			case "tag":
				$CP->page("tag", "manage", "tag", "", $tcp->tag());
			case "status":
				$CP->page("site state", "manage", "status", "", $tcp->state());
			case "counter":
				switch ($_GET["action"]) {
					case "referrer":
						$CP->page("counter", "manage", "counter", "referrer", counter("referrer"));
					case "keyword":
						$CP->page("counter", "manage", "counter", "keyword", counter("keyword"));
					default:
						$CP->page("counter", "manage", "counter", "", counter("detail"));
				}
			case "user":
				switch ($_GET["action"]) {
					case "add":
						$CP->page("user", "manage", "user", "add", $tcp->user_page());
					case "archive":
						$CP->page("user", "manage", "user", "archive", $tcp->user_page());
					case "edit":
						$CP->page("user", "manage", "user", "edit", $tcp->user_page());
					case "avatar":
						$CP->page("user avatar", "manage", "user", "avatar", $tcp->avatar());
				}
			case "level":
				switch ($_GET["action"]) {
					case "add":
						$CP->page("level", "manage", "level", "add", $tcp->level_page());
					case "archive":
						$CP->page("level", "manage", "level", "archive", $tcp->level_page());
					case "edit":
						$CP->page("level", "manage", "level", "edit", $tcp->level_page());
				}
			case "guestbook":
				switch ($_GET["action"]) {
					case "inactive":
						$CP->page("guestbook", "manage", "guestbook", "inactive", $tcp->guestbook());
					case "private":
						$CP->page("guestbook", "manage", "guestbook", "private", $tcp->guestbook());
					case "archive":
						$CP->page("guestbook", "manage", "guestbook", "archive", $tcp->guestbook());
					case "privateArchive":
						$CP->page("guestbook", "manage", "guestbook", "privateArchive", $tcp->guestbook());
					case "edit":
						$CP->page("guestbook", "manage", "guestbook", "edit", $tcp->guestbook());
				}
			case "poll":
				switch ($_GET["action"]) {
					case "add":
						$CP->page("poll", "manage", "poll", "add", $tcp->poll_page());
					case "archive":
						$CP->page("poll", "manage", "poll", "archive", $tcp->poll_page());
					case "edit":
						$CP->page("poll", "manage", "poll", "edit", $tcp->poll_page());
				}
			case "link":
				switch ($_GET["action"]) {
					case "manage":
						$CP->page("link", "manage", "link", "manage", $tcp->linkbox());
					case "import":
						$CP->page("link", "manage", "link", "import", $tcp->linkbox());
				}
			case "theme":
				switch ($_GET["action"]) {
					case "add":
						$CP->page("theme", "manage", "theme", "add", $tcp->theme_page());
					case "archive":
						$CP->page("theme", "manage", "theme", "archive", $tcp->theme_page());
					case "edit":
						$CP->page("theme", "manage", "theme", "edit", $tcp->theme_page());
				}
			case "newsletter":
				switch ($_GET["action"]) {
					case "send":
						$CP->page("newsletter", "manage", "newsletter", "send", $tcp->newsletter());
					case "configuration":
						$CP->page("newsletter", "manage", "newsletter", "configuration", $tcp->newsletter());
				}
		}
	case "post":
		switch ($_GET["part"]) {
			case "post":
				switch ($_GET["action"]) {
					case "add":
						$CP->page("writer", "post", "post", "add", $tcp->post_page());
					case "archive":
						$CP->page("writer", "post", "post", "archive", $tcp->post_page());
					case "edit":
						$q = mysql_query("select author,category from $DB->post where id='$_GET[id]'");
						$CP->page(mysql_result($q, 0) == logedIn || category("check moderator", mysql_result($q, 0, 1)) ? "active" : "editor", "post", "post", "edit", $tcp->post_page());
				}
			case "comment":
				switch ($_GET["action"]) {
					case "inactive":
						$CP->page("comment", "post", "comment", "inactive", $tcp->post_page());
					case "private":
						$CP->page("comment", "post", "comment", "private", $tcp->post_page());
					case "archive":
						$CP->page("comment", "post", "comment", "archive", $tcp->post_page());
					case "privateArchive":
						$CP->page("comment", "post", "comment", "privateArchive", $tcp->post_page());
					case "edit":
						$CP->page("comment", "post", "comment", "edit", $tcp->post_page());
				}
			case "import":
				$CP->page("import post", "post", "import", "", $tcp->post_page());
		}
	case "plugin":
		if ($_GET["part"] == "manage")
			switch ($_GET["action"]) {
				case "add":
					$CP->page("plugin", "plugin", "manage", "add", $tcp->plugin_page());
				case "archive":
					$CP->page("plugin", "plugin", "manage", "archive", $tcp->plugin_page());
				case "edit":
					$CP->page("plugin", "plugin", "manage", "edit", $tcp->plugin_page());
			}
}
	echo $CP->run();
}
if (isset($_GET["membership"])) {
	$TPL->block("membership", array(
			"status" => array(""),
			"main" => array(
				"username" => '<input name=username class=input size=[value] />',
				"password" => '<input type=password name=password class=input size=[value] />',
				"repassword" => '<input type=password name=repassword class=input size=[value] />',
				"email" => '<input name=email class=input size=[value] />',
				"submit" => '<input type=submit class=button value="[value]" />',
				"reset" => '<input type=reset class=button value="[value]" />',
				"result" => "<div id=result />"
			)
		), "<form method=post ajax=result><input type=hidden name=do value=membership />", "</form>"
	);
}
switch ($_GET["action"]) {
	case "mainScript":
		check("referer") or die;
		header("Content-Type:text/javascript");
		!$SC->time("MPSS") or die($SC->get("MPSS"));
		script("main", JavaScript("public"));
		script("main", JavaScript("AJAX"));
		script("main", JavaScript("insert code"));
		die($SC->set("MPSS", script("print main")));
	case "CPScript":
		check("referer") or die;
		header("Content-Type:text/javascript");
		!$SC->time("CPS") or die($SC->get("CPS"));
		script("cp", 1, CPJS());
		script("cp", 1, JavaScript("CP"));
		script("cp", "message", JavaScript("message"));
		script("cp", "link", JavaScript("link"));
		die($SC->set("CPS", script("print cp")));
	case "changeSecurityCode":
		check("referer") or die;
		securityCode();
	case "securityCode":
		check("referer") or die;
		die(security_code());
	case "membership":
		$TPL->block("membership", array("main" => array(""), "status" => array("status" => user("security code", $_GET["id"], $_GET["securityCode"]))));
		break;
	case "style":
		check("referer") or die;
		$i = active_theme();
		die($SC->time("MPST$i") ? $SC->get("MPST$i") : $SC->set("MPST$i", lock("css", config("change", theme("print", "style")))));
	case "script":
		check("referer") or die;
		$i = active_theme();
		die($SC->time("MPSC$i") ? $SC->get("MPSC$i") : $SC->set("MPSC$i", lock("js", config("change", theme("print", "script")))));
	case "logo_image":
		die(logo());
	case "logo":
		die(str_replace(array("[site url]", "[site description]", "[logo url]"), array(config("site->url"), config("site->description"), config("site->url").'/logo.png'), config("logo->template")));
	case "logOut":
		session_destroy();
		cookie(array("username" => "", "password" => ""), 0);
		die(header("location: $_SERVER[HTTP_REFERER]"));
	case "deleteAccount":
		logedIn && !level("demo") or die;
		user("delete");
		die(header("location: ".config("site->url")));
	case "redirect":
		mysql_query("update $DB->link set count=count+1 where id='$_GET[id]'");
		die(header("location:".mysql_result(mysql_query("select url from $DB->link where id='$_GET[id]'"), 0)));
	case "linkbox":
		die('<script language="javascript" type="text/javascript">document.write('.js(str_replace("[linkbox url]", config("site->url")."/linkbox.html", config("link->template"))).');</script>');
	case "linkRegistration":
		$TPL->block("new link", array(
				"link" => '<input class=radio type=radio id="link" name="type" value=0 checked /><label for="link">[value]</label>',
				"dump" => '<input class=radio type=radio id="dump" name="type" value=1 /><label for="dump">[value]</label>',
				"advertisment" => '<input class=radio type=radio id="advertisment" name="type" value=2 /><label for="advertisment">[value]</label>',
				"name" => '<input class=input name=name size=[value] lang=fa />',
				"url" => '<input class=input name=url size=[value] dir=ltr value="http://" />',
				"description" => '<textarea class=textarea rows="3" cols=[value] name=description lang=fa></textarea>',
				"email" => '<input class=input name=email size=[value] dir=ltr />',
				"submit" => '<input class=button type=submit value="[value]" />',
				"result" => "<div id=result />"
			),
				'<form method=post ajax=result><input type=hidden name=do value=registerLink />',
				"</form>"
		);
		break;
	case "emoticon":
		$data = config("emoticon->data");
		$path = config("emoticon->path");
		foreach ($data["code"] as $i => $v)
			$TPL->block("emoticons", array("background color" => $i%2 ? "" : "[value]", "code" => str_replace('"', "&quot;", $v), "description" => $data["description"][$i], "url" => $path.$data["url"][$i]));
		break;
	case "backup":
		if (level("backup") && !level("demo"))
			die(compress($DB->backup(), "backup($DB->database).sql"));
}
if (isset($_GET["guestbook"])) {
	for ($q=mysql_query("select * from $DB->comment where status=1 and `post id`=0 order by `".config("guestbook->order")."` ".config("guestbook->sort")), $i=0; $i<mysql_num_rows($q); $i++)
		$TPL->block("memory", mysql_result($q, $i, 2) ? array("id" => mysql_result($q, $i), "author" => user("name", mysql_result($q, $i, 2)), "email" => user("email", mysql_result($q, $i, 2)), "url" => user("url", mysql_result($q, $i, 2)) ? array("url" => user("url", mysql_result($q, $i, 2))) : array(""), "content" => html(mysql_result($q, $i, 4)), "ip" => mysql_result($q, $i, 7), "date" => my_date(mysql_result($q, $i, 8)), "time" => my_time(mysql_result($q, $i, 8))) : array("id" => mysql_result($q, $i), "author" => mysql_result($q, $i, 3), "email" => mysql_result($q, $i, 5), "url" => mysql_result($q, $i, 6) ? array("url" => mysql_result($q, $i, 6)) : array(""), "content" => html(mysql_result($q, $i, 4)), "ip" => mysql_result($q, $i, 7), "date" => my_date(mysql_result($q, $i, 8)), "time" => my_time(mysql_result($q, $i, 8))));
	mysql_num_rows($q) or $TPL->block("memory");
	$v = form_value();
	$TPL->block("new memory", array(
			"author" => '<input class=input name=author size=[value] value="'.$v["author"].'" lang=fa'.$v["disabled"].'>',
			"email" => '<input class=input name=email size=[value] dir=ltr value="'.$v["email"].'"'.$v["disabled"].'>',
			"url" => '<input class=input name=url size=[value] dir=ltr value="'.$v["url"].'"'.$v["disabled"].'>',
			"content" => '<textarea class=textarea rows=10 cols=[value] name=content id=content '.(config("guestbook->html") ? 'editor=active' : 'lang=fa').'></textarea>',
			"submit" => '<input class=button type=submit class=input value="[value]">',
			"reset" => '<input class=button type=reset class=input value="[value]">',
			"private message" => '<input type=checkbox class=checkbox name=privateMessage id=privateMessage'.$v["privateMessage"].'><label for=privateMessage>[value]</label>',
			"result" => "<div id=result />"
		), '<form method=post ajax=result><input type=hidden name=do value=comment><input type=hidden name=postID value=0>', '</form>'
	);
} elseif (isset($_GET["comment"]))
	comment("block");
elseif (isset($_GET["contact"])) {
		$q = mysql_query("select id, username, nickname from $DB->user where ".level("permission", "message")." and status=1 ".(!empty($_GET["contact"]) ? "and id='$_GET[contact]'" : ""));
		while ($r = mysql_fetch_array($q, MYSQL_NUM))
			$TPL->block("user", array("id" => $r[0], "selected" => $_COOKIE["saveCookie"] && $_COOKIE["to"] == $r[0] ? "selected" : "", "username" => $r[1], "name" => $r[2] ? $r[2] : $r[1]));
		$v = form_value();
		$TPL->block("contact", array(
				"author" => '<input class=input name=author value="'.$v["author"].'" size=[value] lang=fa'.$v["disabled"].'>',
				"email" => '<input class=input name=email dir=ltr value="'.$v["email"].'" size=[value]'.$v["disabled"].'>',
				"url" => '<input class=input name=url dir=ltr value="'.$v["url"].'" size=[value]'.$v["disabled"].'>',
				"subject" => '<input class=input name=subject size=[value] lang=fa>',
				"content" => '<textarea class=textarea rows=10 cols=[value] name=content id=content '.(config("message->html") ? 'editor=active' : "lang=fa").'></textarea>',
				"submit" => '<input type=submit class=button value="[value]">',
				"reset" => '<input type=reset class=button value="[value]">',
				"result" => '<div id=result></div>'
			), '<form method=post ajax=result><input type=hidden name=do value=contact>', "</form>"
		);
} elseif (isset($_GET["profile"])) {
	$id = $_GET["profile"];
	if ($id == 0) {
		for ($q=mysql_query("select * from $DB->user order by registered"), $i=0; $r=mysql_fetch_object($q); $i++)
			$TPL->block("member", array(
					"username" => $r->username,
					"nickname" => user("name", $r->id),
					"level" => config("level->name->".$r->level),
					"registered" => array("date" => my_date($r->registered), "time" => my_time($r->registered)),
					"contact" => ($p=level("message", $r->level)) || $r->url || $r->email ? array("email" => array("email" => $r->email), "message" => $p ? array("pm" => "contact-$r->id.html") : array(""), "url" => $r->url ? array("url" => $r->url) : array("")) : array(""),
					"avatar" => $r->avatar ? array("url" => $r->avatar) : array(""),
					"profile" => "profile-$r->id.html",
					"style" => $i%2 ? "[value]" : ""
				)
			);
		$TPL->block("profile", array(""));
	} else {
		$data = mysql_fetch_assoc(mysql_query("select * from $DB->user where id='$id'"));
		$TPL->block("profile", array(
				"username" => $data["username"],
				"name" => user("name", $id),
				"first name" => $data["first name"],
				"last name" => $data["last name"],
				"nickname" => user("name", $id),
				"birthday" => $data["birthday"] == "0000-00-00 00:00:00" ? array("") : array("date" => my_date($data["birthday"], 1), "time" => my_time($data["birthday"], 1)), 
				"age" => ($age = Now-$data["birthday"]) > Now-1 ? array("") : array("age" => $age),
				"gender" => $data["gender"] ? array("") : array("gender" => $data["gender"] == 1 ? "مرد" : "زن"),
				"marriage" => $data["marriage"] ? array("") : array("marriage" => $data["marriage"] == 1 ? "مجرد" : "متاهل"),
				"eduction" => $data["eduction"],
				"vocation" => $data["vocation"],
				"amusement" => html($data["amusement"], "convert"),
				"favorite" => html($data["favorite"], "convert"),
				"about" => html($data["about"], "convert"),
				"location" => $data["location"],
				"phone" => $data["phone"],
				"level" => config("level->name->".$data["level"]),
				"registered" => array("date" => my_date($data["registered"]), "time" => my_time($data["registered"])),
				"last login" => $data["registered"] != $data["last login"] ? array("date" => my_date($data["last login"]), "time" => my_time($data["last login"])) : array(""),
				"post" => level("writer", $data["level"]) && !level("demo", $data["level"]) ? array("count" => mysql_result(mysql_query("select count(*) from $DB->post where author='$id'"), 0)) : array(""),
				"contact" => ($p = level("message", $data["level"])) || $data["url"] ? array("email" => array("email" => $data["email"]),"message" => $p ? array("pm" => "contact-$id.html") : array(""),"url" => $data["url"] ? array("url" => $data["url"]) : array("")) : array(""),
				"avatar" => $data["avatar"] ? array("avatar" => $data["avatar"]) : array(""),
				"signature" => $v = html($data["signature"]) ? array("signature" => $v) : array(""),
				"im" =>
					$data["msn"] || $data["yahoo"] || $data["gmail"] || $data["aim"] || $data["icq"] ?
						array(
							"msn" => $data["msn"] ? array("msn" => $data["msn"]) : array(""),
							"yahoo" => $data["yahoo"] ? array("yahoo" => $data["yahoo"]) : array(""),
							"gmail" => $data["gmail"] ? array("gmail" => $data["gmail"]) : array(""),
							"aim" => $data["aim"] ? array("aim" => $data["aim"]) : array(""),
							"icq" => $data["icq"] ? array("icq" => $data["icq"]) : array("")
						)
					:
						array(""),
				"time online" => $data["time online"] ? time_parser($data["time online"]) : array("")
			)
		);
		$TPL->block("member list", array(""));
	}
} elseif ($TPL->load() == theme("print", "main") || $TPL->load() == theme("print", "post") || $TPL->load() == config("post->template") || $TPL->load() == config("link->theme")) {
	$archive_sql = mysql_query("select distinct year(date) AS `year`, month(date) AS `month`, count(ID) as posts from $DB->post where date<='".Now."' and status=1 group by year(date), month(date) order by `".config("post->order")."` ".config("post->sort"));
	$last_post_sql = mysql_query("select id, title, date, view, `comment status` from $DB->post where status=1 and date<='".Now."' order by `".config("post->order")."` ".config("post->sort")." limit ".config("post->per page").",".config("post->show most last posts"));
	$page_sql = mysql_query("select count(*) from $DB->post where status=1 and date<='".Now."'");
	$author_sql = mysql_query("select u.id, username, nickname, email, url, registered, visible, `last login`, count(p.id) count from $DB->user u left join $DB->post p on(p.author=u.id) where ".level("permission", "writer")." group by u.id order by ".config("post->author order")." ".config("post->author sort"));
	$PSO = " order by `".config("post->order")."` ".config("post->sort");
	if (isset($_GET["downloadPost"])) {
		$fileName = iconv("utf-8", "windows-1256", $Jalali->convert(post("title", $_GET["downloadPost"]))).".html";
		header('Content-Type: text/x-delimtext; name="'.$fileName.'"');
		header('Content-disposition: attachment; filename="'.$fileName.'"');
		$post_sql = "p.id='$_GET[downloadPost]'";
	} elseif (isset($_GET["post"]))
		$post_sql = "p.id='$_GET[post]'";
	elseif (isset($_GET["archive"])) {
		$archive = $_GET["archive"];
		$post_sql = strlen($archive) == 8 ? "date LIKE '%".date("Y-m-d", strtotime($Jalali->gregorian(substr($archive, 0, 4), substr($archive, 4, 2), substr($archive, 6, 2))))."%'$PSO" : "date >= '".date("Y-m-d", strtotime($Jalali->gregorian(substr($archive, 0, 4), substr($archive, 4, 2))))."' && `date` < '".date("Y-m-d", strtotime($Jalali->gregorian(substr($archive, 0, 4), substr($archive, 4, 2)+1)))."'$PSO";
	} elseif (isset($_GET["category"])) {
		for ($id=$_GET["category"], $q=mysql_query("select id from $DB->category where parent='$id'"), $in="", $i=0; $i<mysql_num_rows($q); $i++)
			for ($in.=",".mysql_result($q, $i), $Q=mysql_query("select id from $DB->category where parent=".mysql_result($q, $i)), $j=0; $j<mysql_num_rows($Q); $j++)
				$in .= ",".mysql_result($Q, $j);
		$post_sql = "category in ($id$in) and date<='".Now."'$PSO";
	} elseif (isset($_GET["search"]))
		$post_sql = "(".search(stripslashes($_GET["search"])).") and date <= '".Now."'$PSO";
	elseif (isset($_GET["author"]))
		$post_sql = "author='".$_GET["author"]."' and date<='".Now."'$PSO";
	elseif (isset($_GET["page"]))
		$post_sql = "date <= '".Now."'$PSO limit ".(config("post->per page")*intval($_GET["page"])-config("post->per page")).", ".config("post->per page");
	else
		$post_sql = "date<='".Now."'$PSO limit 0,".config("post->per page");
	$post_sql = mysql_query("select p.*,u.username,u.nickname,u.email,u.url,c.name,c.description from $DB->post p, $DB->user u, $DB->category c where p.status=1 and p.author=u.id and p.category=c.id and $post_sql");
	if (isset($_GET["post"]))
		comment("block");
	if (mysql_num_rows($post_sql) == 0) {
		$TPL->block("post");
		$TPL->block("page");
	}
	for ($i=0; $i<mysql_num_rows($post_sql); $i++) {
		$ID = mysql_result($post_sql, $i);
		$content = isset($_GET["category"]) || isset($_GET["search"]) || isset($_GET["author"]) || isset($_GET["archive"]) ? excerptLength(str_replace("<!-- More -->", "", mysql_result($post_sql, $i, 2))) : (ereg(".*<!-- More -->", mysql_result($post_sql, $i, 2), $content) && !isset($_GET["post"]) && !isset($_GET["downloadPost"]) ? $content[0] : str_replace("<!-- More -->", "", mysql_result($post_sql, $i, 2)));
		if (mysql_result($post_sql, $i, 6)) {
			$LEA = explode(",", mysql_result($post_sql, $i, 6));
			list($lea, $led) = explode(" ", $LEA[count($LEA)-1]);
		}
		$content != mysql_result($post_sql, $i, 2) or mysql_query("update $DB->post set view=view+1 where id=$ID");
		$TPL->block("post", array(
				"id" => $ID,
				"title" => mysql_result($post_sql, $i, 1),
				"content" => html($content),
				"more" => $content == mysql_result($post_sql, $i, 2) ? array("") : array("url" => "[url]"),
				"rate" => mysql_result($post_sql, $i, 9) ? array("votes" => "[".mysql_result($post_sql, $i, 7)."]") : array(""),
				"last edit" => mysql_result($post_sql, $i, 6) ? array("author" => user("name", $lea), "date" => my_date($led), "time" => my_time($led), "count" => count($LEA)-1) : array(""),
				"comment" => mysql_result($post_sql, $i, 10) ? array("count" => comment("count", $ID, 1), "url" => "comment-$ID.html") : array(""),
				"modify" => level("editor") || category("check moderator", mysql_result($post_sql, $i, 4)) ? array("url" => "CP&page=post&part=post&action=edit&id=".$ID) : array(""),
				"author" => array("name" => ($v=mysql_result($post_sql, $i, 13)) != "" ? $v : mysql_result($post_sql, $i, 12), "email" => mysql_result($post_sql, $i, 14), "url" => mysql_result($post_sql, $i, 15)),
				"category" => array("name" => mysql_result($post_sql, $i, 16), "url" => "category-".mysql_result($post_sql, $i, 4).".html", "description" => mysql_result($post_sql, $i, 17)),
				"url" => config("site->url")."/post-$ID.html",
				"view" => mysql_result($post_sql, $i, 8),
				"date" => my_date(mysql_result($post_sql, $i, 5), 1),
				"time" => my_time(mysql_result($post_sql, $i, 5), 1),
				"download" => "download-post-$ID.html",
				"rss" => "rss-post-$ID.xml",
				"rdf" => "rdf-post-$ID.xml",
				"atom" => "atom-post-$ID.xml",
				"opml" => "opml-post-$ID.xml"
			)
		);
	}
	while ($r=mysql_fetch_assoc($author_sql))
		$TPL->block("author", array(
				"id" => $r["id"],
				"username" => $r["username"],
				"name" => $r["nickname"] ? $r["nickname"] : $r["username"],
				"email" => $r["email"],
				"url" => $r["url"],
				"post url" => "author-$r[id].html",
				"count post" => $r["count"],
				"profile" => "profile-$r[id].html",
				"registered" => array("date" => my_date($r["registered"]), "time" => my_time($r["registered"])),
				"status" => $r["visible"] && Time-$r["last login"] <= config("user->timeout") ? array("online" => array(), "offline" => array("")) : array("offline" => array(), "online" => array(""))
			)
		);
	$t = array("link", "link dump", "advertisment");
	$b = array();
	for ($q=mysql_query("select * from $DB->link where status=1 and (type!=1 or (type=1 and time between ".mktime(0, 0, 0)." and ".(Time+86400).")) order by `".config("link->order")."` ".config("link->sort").(($v=config("link->per page")) ? " limit $v" : "")), $i=0; $i<mysql_num_rows($q); $i++) {
		$TPL->block($t[mysql_result($q, $i, 8)], array("name" => mysql_result($q, $i, 1), "url" => "link-".mysql_result($q, $i).".html", "description" => mysql_result($q, $i, 3), "count" => mysql_result($q, $i, 6), "view" => mysql_result($q, $i, 7), "creator" => mysql_result($q, $i, 4), "date" => my_date(mysql_result($q, $i, 5), 1), "time" => my_time(mysql_result($q, $i, 5), 1)));
		$b[mysql_result($q, $i, 8)]++;
	}
	$TPL->block("$t[0] block", ($b[0] ? array() : array("")));
	$TPL->block("$t[1] block", ($b[1] ? array() : array("")));
	$TPL->block("$t[2] block", ($b[2] ? array() : array("")));
	mysql_query("update $DB->link set view=view+1 where status=1");
	for ($i=0; $i<mysql_num_rows($last_post_sql); $i++)
		$TPL->block("last post", array("title" => mysql_result($last_post_sql, $i, 1), "view" => mysql_result($last_post_sql, $i, 3), "comment" => comment("count", mysql_result($last_post_sql, $i), mysql_result($last_post_sql, 4)), "url" => "post-".mysql_result($last_post_sql, $i).".html", "date" => my_date(mysql_result($last_post_sql, $i, 2), 1), "time" => my_time(mysql_result($last_post_sql, $i, 2), 1)));
	$TPL->block("last post block", (mysql_num_rows($last_post_sql) ? array() : array("")));
	if ($TP = mysql_result($page_sql, 0))
		for ($v=ceil($TP/config("post->per page")), $i=1; $i<=$v; $i++)
			$TPL->block("page", $_GET["page"] == $i ? array("main" => array(""), "select" => array("page" => $i, "url" => "page-$i.html")) : array("select" => array(""), "main" => array("page" => $i, "url" => "page-$i.html")));
	for ($q=mysql_query("select id from $DB->theme"), $i=0; $i<mysql_num_rows($q); $i++) {
		$id = mysql_result($q, $i);
		$data = theme("data", $id);
		$TPL->block("theme", $_COOKIE["theme"] == $id || $_GET["theme"] == $id ? array("select" => array(""), "main" => array("name" => $data["name"], "url" => "theme-$id.html", "theme url" => $data["url"], "description" => $data["description"], "version" => $data["version"], "author" => $data["author"], "author url" => $data["author url"], "author email" => $data["author email"])) : array("main" => array(""), "select" => array("name" => $data["name"], "url" => "theme-$id.html", "theme url" => $data["url"], "description" => $data["description"], "version" => $data["version"], "author" => $data["author"], "author url" => $data["author url"], "author email" => $data["author email"])));
	}
	strlen($_GET["archive"]) == 6 ? $Jalali->calendar(substr($_GET["archive"], 0, 4), substr($_GET["archive"], 4, 2)) : $Jalali->calendar();
	$Jalali->archive(config("post->archive"));
	$TPL->block("search", array("search" => '<input class=input name=search size=[value] lang=fa value="'.htmlspecialchars(stripslashes($_GET["search"])).'" title=\'علامت "عبارت" براي عين عبارت، + براي و، فاصله براي يا\'/>', "submit" => '<input type=submit class=button value="[value]"/>'), '<form method=get onsubmit="return this.search.value != \'\' ? true : false" action="index.php">', "</form>");
	$q = mysql_query("select * from $DB->poll where status=1");
	if (mysql_num_rows($q)) {
		$votes = explode("\n", mysql_result($q, 0, 3));
		$total_votes = 0;
		foreach ($votes as $vote)
			$total_votes += $vote;
		$count = $total_votes == 0 ? 1 : $total_votes;
		$mode = $_GET["action"] == "poll" || in_array(IP, explode("\n", mysql_result($q, 0, 4)));
		$section = $mode ? "result->reply" : "main->answer";
		$data = "";
		foreach (explode("\n", mysql_result($q, 0, 2)) as $i => $answer)
			$data .= $TPL->get("poll->$section", array("id" => $i, "answer" => $answer, "vote" => $votes[$i], "score" => (int)($votes[$i]*100/$count)));
		if ($mode)
			$TPL->block("poll", array("main" => array(""), "result" => array("reply" => array($data), "question" => mysql_result($q, 0, 1), "date" => my_date(mysql_result($q, 0, 4)), "total votes" => $total_votes)));
		else
			$TPL->block("poll", array("result" => array(""), "main" => array("question" => mysql_result($q, 0, 1), "url" => "poll-result.html", "submit" => '<input type=submit class=button value="[value]">', "date" => my_date(mysql_result($q, 0, 4)), "total votes" => $total_votes, "result" => "<div id=pollResult />", "answer" => array($data))), '<form method=post ajax=pollResult><input type=hidden name=do value="poll"><input type=hidden name=id value="'.mysql_result($q, 0).'">', "</form>");
	} else
		$TPL->block("poll");
	$TPL->block("newsletter", array(
			"email" => '<input class=input maxLength=255 id=email dir=ltr size=[value] />',
			"subscription" => '<input type=radio class=radio id=subscription value=subscription name=action checked><label for=subscription>[value]</label>',
			"unsubscription" => '<input type=radio class=radio id=unsubscription value=unsubscription name=action><label for=unsubscription>[value]</label>',
			"submit" => '<input type=submit class=button value="[value]">',
			"result" => "<div id=newsletterResult />"
		), "<form method=post ajax=newsletterResult><input type=hidden name=do value=newsletter />", "</form>"
	);
}
$total = $SC->time("total") ? $SC->get("total") : $SC->set("total", mysql_fetch_assoc(mysql_query("select count(distinct c.id) comment, count(distinct g.id) memory, count(distinct l.id) link, count(distinct d.id) dump, count(distinct a.id) advertisment, count(distinct w.id) poll, count(distinct p.id) post, count(distinct u.id) user from $DB->poll w, $DB->post p, $DB->user u left join $DB->comment c on(c.`post id`>0) left join $DB->comment g on(g.`post id`=0) left join $DB->link l on(l.type=0) left join $DB->link d on(d.type=1) left join $DB->link a on(a.type=2)")));
$TPL->block("total", array("post" => $total["post"], "poll" => $total["poll"], "comment" => $total["comment"], "memory" => $total["memory"], "link" => $total["link"], "advertisment" => $total["advertisment"], "user" => $total["user"]));
$TPL->assign(array(
		"linkbox" => config("site->url")."/linkbox.js",
		"link registration" => "linkRegistration.html",
		"logo" => config("site->url")."/logo.png",
		"logo url" => config("site->url")."/logo.js",
		"main script" => "mainScript.js",
		"control panel script" => "CPScript.js",
		"rss" => config("site->url")."/rss.xml",
		"rdf" => config("site->url")."/rdf.xml",
		"atom" => config("site->url")."/atom.xml",
		"opml" => config("site->url")."/opml.xml",
		"link rss" => config("site->url")."/rss-link.xml",
		"link opml" => config("site->url")."/opml-link.xml",
		"xhtml" => "http://validator.w3.org/check?uri=".config("site->url"),
		"css" => "http://jigsaw.w3.org/css-validator/validator?uri=".config("site->url"),
		"comments rss" => config("site->url")."/rss-comment.xml",
		"guestbook" => "guestbook.html",
		"new url" => url(),
		"style" => "style.css",
		"script" => "script.js",
		"contact" => "contact.html",
		"membership" => "membership.html",
		"member list" => "memberlist.html",
		"save cookie" => '<input type=checkbox class=checkbox name=saveCookie id=saveCookie'.($_POST["saveCookie"] || $_COOKIE["saveCookie"] ? " checked" : "").'><label for=saveCookie>[value]</label>',
		"security code" => str_replace(array("[url]", "[change url]"), array("securityCode.png", "changeSecurityCode.png"), config("security code->template")),
		"security code field" => '<input class=input size="5" name="securityCode" id="securityCode" dir=ltr autocomplete=off />',
		"emoticons" => emoticons("print emoticons"),
		"site url" => config("site->url"),
		"site title" => config("site->title"),
		"site title separator" => title(config("site->title")),
		"site name" => config("site->name"),
		"site description" => config("site->description"),
		"site keywords" => config("site->keywords"),
		"site author" => config("site->author"),
		"site email" => config("site->email"),
		"site date" => my_date(),
		"site time" => my_time(),
		"site charset" => config("site->charset"),
		"site category" => category("tree"),
		"site generator" => "Raha CMS",
		"site generator version" => Raha,
		"create site date" => my_date(config("site->create date"), 1),
		"create site time" => my_time(config("site->create date"), 1),
		"page generation" => generation()
	)
);
$announcement = config("announcement");
if ($announcement["expires"] >= Time) {
	$TPL->block("announcement", array("title" => $announcement["title"], "content" => $announcement["content"]));
	config("announcement->view", $announcement["view"]+1);
} else
	$TPL->block("announcement");
if (!isset($_GET["CP"]) && !isset($_GET["linkbox"]) && $_GET["action"] != "linkbox")
	counter("update");
counter("block");
die($TPL->compile());
?>
