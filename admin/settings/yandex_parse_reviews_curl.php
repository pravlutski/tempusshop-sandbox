<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule('panel.manager')) return;
function get_random_user_agent() {
     $uas = array(
       'Mozilla/4.0 (compatible; MSIE 6.0; Windows 98)',
       'Mozilla/4.0 (compatible; MSIE 5.5; Windows NT 5.0; .NET CLR 1.0.3705)',
       'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; Maxthon)',
       'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; bgft)',
       'Mozilla/4.5b1 [en] (X11; I; Linux 2.0.35 i586)',
       'Mozilla/5.0 (compatible; Konqueror/2.2.2; Linux 2.4.14-xfs; X11; i686)',
       'Mozilla/5.0 (Macintosh; U; PPC; en-US; rv:0.9.2) Gecko/20010726 Netscape6/6.1',
       'Mozilla/5.0 (Windows; U; Win98; en-US; rv:0.9.2) Gecko/20010726 Netscape6/6.1',
       'Mozilla/5.0 (X11; U; Linux 2.4.2-2 i586; en-US; m18) Gecko/20010131 Netscape6/6.01',
       'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:0.9.3) Gecko/20010801',
       'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.8.0.7) Gecko/20060909 Firefox/1.5.0.7',
       'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.6) Gecko/20040413 Epiphany/1.2.1',
       'Opera/9.0 (Windows NT 5.1; U; en)',
       'Opera/8.51 (Windows NT 5.1; U; en)',
       'Opera/7.21 (Windows NT 5.1; U)',
       'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT)',
       'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
       'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.8.0.6) Gecko/20060928 Firefox/1.5.0.6',
       'Opera/9.02 (Windows NT 5.1; U; en)',
       'Opera/8.54 (Windows NT 5.1; U; en)'
     );
 
     return $uas[rand(0, count($uas)-1)];
}

function proxy_find($proxy_load_good_max = 1) {
	global $DB;
	$pr_count = $proxy_load_good_max * 10;
	$load_page_time = 3;	// Время проверки прокси адреса на рабочесть [с]
	//set_time_limit(1000);

	// Список - урлы длч проверки прокси на работоспособность
	$url_list = array(
		'0' => array('url' => 'http://elsy.by/'),
		'1' => array('url' => 'https://www.kp.by/'),
		'2' => array('url' => 'https://ping-admin.ru/'),
		'3' => array('url' => 'https://sitestatus.ru/'),
	);

	$proxy_array = array();
	
	// Чистим таблицу от прокси адресов, последнее обращение к которым было более 24 часов назад
//	$DB->Query("DELETE FROM onparser_proxy WHERE (".time()." - UNIX_TIMESTAMP(first_call) > 86400 AND first_call != '0000-00-00 00:00:00') OR (first_call = '0000-00-00 00:00:00') OR (".time()." - UNIX_TIMESTAMP(first_call) > 86400 AND first_call != '0000-00-00 00:00:00')", false, $err_mess.__LINE__);
	// Загружаем прокси из базы

	$strSql = "SELECT address FROM onparser_proxy WHERE first=0 LIMIT $pr_count";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$proxy_list[] = $row["address"];
	}
	//prent($proxy_list);die;
	// Проверяем или есть хоть один прокси неиспользованный
	$strSql = "SELECT address FROM onparser_proxy WHERE first = 0 LIMIT 1";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$proxy_zeroes[] = $row["address"];
	}

	// Проверяем или не идет уже получение прокси
	$strSql = "SELECT * FROM onparser_proxy_get";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$proxy_get[] = $row;
	}

	// Если нет прокси для использования, то добавляем прокси из интернета
	if ((count($proxy_list) == 0 || count($proxy_zeroes) == 0) && count($proxy_get) == 0 && 1==2){
		$in = array(
			"date" => "NOW()",
		);
		$insert_id = $DB->Insert("onparser_proxy_get", $in, $err_mess.__LINE__);
		
		$proxy_array = $proxy = array();
		
		// Загружаем (запрещенные) прокси из базы
		$strSql = "SELECT address FROM onparser_proxy";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$forbidden_proxy[$row["address"]] = $row["address"];
		}
		// * 1 список прокси * //
		if (!isset($only_check) or $only_check == 1) {
			$content = get_content("http://proxy.vvs777.org.ua/exp.php?02b16c04");
			$arContent = explode("\r\n", $content);
			if(count($arContent) > 0){
				foreach($arContent as $key => $proxy){
					$proxy_array[] = $proxy;
				}
			}
		}
		// * 2 список прокси * //
/*		if (!isset($only_check) or $only_check == 2) {
			unset($content, $proxy, $proxy1, $proxy2, $proxies, $data);
			$content = get_content('http://cool-proxy.ru/feed');
			$total = preg_match_all("#<content:encoded><!\[CDATA\[<p>(.*?)</p>#si", $content, $data);
			for ($i = 0; $i < $total; ++$i) {
				$proxies = explode("<br />", $data[1][$i]);
				if (count($proxies) > 1) {
					$proxy = proxy_extract($proxies);
					$proxy_array = array_merge($proxy_array, $proxy);
				}
			}
		}*/
		// * 3 список прокси * //
/*		if (!isset($only_check) or $only_check == 3) {
			unset($content, $proxy, $proxy1, $proxy2, $proxies, $data);
			$content = get_content("http://www.proxy-list.net/anonymous-proxy-lists.shtml");
			if ($content != '') {
				$total = preg_match_all("#<pre>(.*?)</pre>#s", $content, $data);
				$proxies = explode('Unknown', $data[1][0]);
				$proxy = proxy_extract($proxies);
				$proxy_array = array_merge($proxy_array, $proxy);
			}
			
		}*/
		
		$proxy_array = array_unique($proxy_array);
		shuffle($proxy_array);
		foreach ($proxy_array as $proxy) {
			if (!isset($forbidden_proxy[$proxy])) {
				$in = array(
					"address" => "'".$proxy."'",
					"added" => "NOW()",
					"first_call" => "",
					"first" => "'0'",
				);
				$insert_id = $DB->Insert("onparser_proxy", $in, $err_mess.__LINE__);
			}
		}
		unset($forbidden_proxy);
		
		// Загружаем прокси из базы
		$proxy_list = array();
		$strSql = "SELECT address FROM onparser_proxy WHERE first=0 ORDER BY id ASC LIMIT $pr_count";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$proxy_list[] = $row["address"];
		}
		$DB->Query("DELETE FROM onparser_proxy_get", false, $err_mess.__LINE__);
	}
	// Проверяем прокси на работоспособность, добавляем в базу
	$proxy_good_array = array();
	$url_nums_sub = count($url_list) - 1;
	$proxy_good_count = $proxy_check_count = 0;
	foreach ($proxy_list as $key=>$proxy) {
		$proxy_check_count++;
		unset($content);
		$random = rand(0, $url_nums_sub);
		//prent($url_list[$random]['url']);
		$content = get_content($url_list[$random]['url'], null, $proxy, $load_page_time);
		//print_var($content, '$content');
		if ($content != '' and strlen($content) > 1000) {
			$proxy_good_count++;
			$proxy_good_array[] = $proxy;
			$in = array(
				"first" => "1",
				"first_call" => "NOW()",
			);
			$DB->Update("onparser_proxy", $in, "WHERE address='".$proxy."'", $err_mess.__LINE__);
		} else {
			$DB->Query("DELETE FROM onparser_proxy WHERE address = '$proxy' AND first_call = '0000-00-00 00:00:00'", false, $err_mess.__LINE__);
			//00.00.0000 00:00:00
		}
		if ($proxy_good_count === $proxy_load_good_max) {
			break;
		}

	}
	prent("Хороших - " . $proxy_good_count);
	prent("Всех - " . $proxy_check_count);
	return $proxy_good_array;

}
function get_content($url, $referer = false, $proxy_addr = false, $time_out = 20, $post = false, $cookie_path = false, $headers = false, $switch = false, $cookie_iter = false) {
	$ua = get_random_user_agent();// получаем случайный браузер из списка
	
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
//    $page = curl_exec($ch);
	
	// ѕередаем header
	if ($headers !== false) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	
	// Используем прокси
	if ($proxy_addr !== false) {
		curl_setopt($ch, CURLOPT_PROXY, $proxy_addr);  
		if ($proxy = explode(":", trim($proxy_addr))) {
			//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
			//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
			//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
			//curl_setopt($ch, CURLOPT_PROXY, $proxy[0]);
			//if (isset($proxy[1])) {
			//	curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy[1]);
			//}
		}
	}
	
	// Передаем POST-данные
	if ($post !== false) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	
	// если с сервера пришли cookie, то запишем их в файл
	if ($cookie_path !== false) {
		if ($cookie_iter == false) $num = '';
		else $num = $cookie_iter;
		$cookie_file = realpath($cookie_path."cookie$num.txt");
		//print_var($cookie_file, '$cookie_file');
		$c_file = $cookie_path."cookie$num.txt";
		if (!file_exists($c_file)) {
			$cf = fopen($c_file, "w+");
			fclose($cf);
		}
		if ($switch === false) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);	// Сохраняем куки
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);	// Загружаем куки
		// Либо получаем, либо отправляем куки
		} else {
			if ($switch == 1) {
				curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);	// Сохраняем куки
			} elseif ($switch == 2) {
				curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);	// Загружаем куки
			}
		}
	}
	
	if ($referer !== false) {
		curl_setopt($ch, CURLOPT_REFERER, $referer);
	}
	curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);
	$page = curl_exec($ch);
//	prent($url);
//	prent($page);
	curl_close($ch);
	return $page;
}

function proxy_extract($proxies = array()) {
	$proxy_array = array();
	foreach ($proxies as $v) {
		$proxy = "";
		preg_match_all("/(?:([1-9]\d{0,2}\.[1-9]\d{0,2}\.[1-9]\d{0,2}\.[1-9]\d{0,2})(?:[\D]*)([1-9]\d{0,5}))/", $v, $data2);
		if (isset($data2[1][0]) and $data2[1][0] != "") {
			$proxy = $data2[1][0];
			if (isset($data2[2][0]) and $data2[2][0] != "") {
				$proxy .= ":".$data2[2][0];
			}
		}
		if ($proxy != "") {
			$proxy_array[] = $proxy;
		}
	}
	return $proxy_array;
}


function save_page_yandex($model_id, $referer = false, $proxy_addr = false, $time_out = 20) {
	sleep(1);
	global $DB;
	$fp = fopen("/tmp/yandex_tmp/{$model_id}.txt", "w");
	$ua = get_random_user_agent();// получаем случайный браузер из списка
	$url = "https://market.yandex.ru/product/{$model_id}/reviews?track=tabs";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	// Используем прокси
	if ($proxy_addr !== false) {
		curl_setopt($ch, CURLOPT_PROXY, $proxy_addr);  
		if ($proxy = explode(":", trim($proxy_addr))) {
			//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
			//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
			//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
			//curl_setopt($ch, CURLOPT_PROXY, $proxy[0]);
			//if (isset($proxy[1])) {
			//	curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy[1]);
			//}
		}
	}
	
	if ($referer !== false) {
		curl_setopt($ch, CURLOPT_REFERER, $referer);
	}
	curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	
	curl_close($ch);
	fclose($fp);prent($info);
	if($info["http_code"] == 404 || $info["header_size"] < 1000){
		$DB->Query("DELETE FROM onparser_proxy WHERE address = '".$proxy_addr."'", false, $err_mess.__LINE__);
	}
	if($info["header_size"] > 1000){
		$strSql = "SELECT id, first FROM onparser_proxy WHERE address='".$proxy_addr."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$cnt = $row["first"];
			$cnt++;
			$in = array(
				"first" => "'".$cnt."'",
				"first_call" => "NOW()",
			);
			$DB->Update("onparser_proxy", $in, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);
		}

		return true;
	} 
	return false;
}
function getGoodProxy(){
	global $DB;
	// Проверяем или не идет ужее получение прокси
	$strSql = "SELECT * FROM onparser_proxy WHERE first>0 AND first<4 ORDER BY RAND() LIMIT 0,1";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		return $row;
	}
	return false;
}
/*
$find_proxy = proxy_find(10);
prent($find_proxy);
die;
*/
function proxy_find_file() {
	global $DB;
//	$DB->Query("DELETE FROM onparser_proxy WHERE (".time()." - UNIX_TIMESTAMP(first_call) > 86400 AND first_call != '0000-00-00 00:00:00') OR (first_call = '0000-00-00 00:00:00') OR (".time()." - UNIX_TIMESTAMP(first_call) > 86400 AND first_call != '0000-00-00 00:00:00')", false, $err_mess.__LINE__);
	$arProxy = array();
	$handle = @fopen("/var/www/bitrix/data/www/tempusshop.ru/admin/proxylist.txt", "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			$arProxy[] = str_replace(array("\r", "\n"), array("", ""), $buffer);
		}
	}
	$strSql = "SELECT address FROM onparser_proxy";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$forbidden_proxy[$row["address"]] = $row["address"];
	}//prent($forbidden_proxy);
	foreach($arProxy as $key => $proxy){
		if (!isset($forbidden_proxy[$proxy]) && strlen($proxy) > 0) {
			$in = array(
				"address" => "'".$proxy."'",
				"added" => "NOW()",
				"first_call" => "",
				"first" => "'0'",
			);prent($in);
			$insert_id = $DB->Insert("onparser_proxy", $in, $err_mess.__LINE__);
		}
	}
	
}

$find_proxy = proxy_find_file();
prent($find_proxy);
die;

//$content = get_content("https://hidemy.name/ru/proxy-list/?type=h&anon=4#list");

$last = CProSet::getOption("LAST_PARSE_YANDEX_ID");
$arFilter = Array(
	"IBLOCK_ID"		=> CProSet::IB_CATALOG,
	"!PROPERTY_YANDEX_MODEL_ID" => false,
	">CATALOG_QUANTITY" => 0,
	">ID"			=> $last,
//	"PROPERTY_YANDEX_MODEL_ID" => "3965632",
);

$res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nPageSize" => 100), array("ID", "NAME", "XML_ID", "PROPERTY_YANDEX_MODEL_ID"));
while($ar_fields = $res->GetNext()){
	$arResult["ITEMS"][] = $ar_fields;
}
// prent($arResult);
$html = "";
foreach($arResult["ITEMS"] as $key => $arItem){
	$filename = "/tmp/yandex_tmp/{$arItem["PROPERTY_YANDEX_MODEL_ID_VALUE"]}.txt";
	if (!file_exists($filename) || filesize($filename) < 50000) {
		$proxy = getGoodProxy();//prent($proxy);
//		$proxy["address"] = "109.207.92.242:53281";
		if($proxy["address"]){
			$content = save_page_yandex($arItem["PROPERTY_YANDEX_MODEL_ID_VALUE"], null, $proxy["address"], 12);
			if($content === true)
				CProSet::setOption("LAST_PARSE_YANDEX_ID", $arItem["ID"]);
		}

	}
}
die;
$proxy = getGoodProxy();
prent($proxy["address"]);
$content = save_page_yandex("7705089", null, $proxy["address"], 12);
prent($content);
die;



//

die;
//die;


function createfile2Parse($urlParse, $model_id){
	//usleep(1000000);
	//$useragent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13";
	sleep(20);
	$ch = curl_init($urlParse);
	$fp = fopen("/tmp/yandex_tmp/{$model_id}.txt", "w");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, get_random_user_agent());
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_POST, false);
	$arC = array(
		"utm_source=email",
		"utm_medium=transaction",
		"utm_campaign=processing",
		"settings-notifications-popup=%7B%22showCount%22%3A2%2C%22showDate%22%3A17438%7D",
		"pof=%7B%22clid%22%3A%5B%22155842%22%5D%2C%22mclid%22%3Anull%2C%22distr_type%22%3Anull%7D",
		"parent_reqid_seq=830febde0b381d4c396abee61c595d78%2C9fd189508b82de811897aa98a2a4c4de%2C743d4bfdd597f119cb9ba05d19cfb85b%2C0eeb9a44557adcfffe0eb70ad59dc0ae%2C1a2503f59e9abe988e794af4a59d8639",
		"cpa-pof=%7B%22clid%22%3A%5B%22155842%22%5D%2C%22mclid%22%3Anull%2C%22distr_type%22%3Anull%7D",
		"HISTORY_AUTH_SESSION=ac70e43d",
		"yandexmarket=10,RUR,1,,,,2,0,0,213,0,0",
		"currentRegionId=213",
		"currentRegionName=%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D1%83",
		"fonts-loaded=1",
		"head-banner=%7B%22closingCounter%22%3A0%2C%22showingCounter%22%3A1%2C%22shownAfterClicked%22%3Afalse%2C%22isClicked%22%3Afalse%7D",
		//"parent_reqid_seq=9fd189508b82de811897aa98a2a4c4de%2C743d4bfdd597f119cb9ba05d19cfb85b%2C0eeb9a44557adcfffe0eb70ad59dc0ae%2C1a2503f59e9abe988e794af4a59d8639%2C82a5b658f37d746a2f567bedd689fb99",
		"parent_reqid_seq=743d4bfdd597f119cb9ba05d19cfb85b%2C0eeb9a44557adcfffe0eb70ad59dc0ae%2C1a2503f59e9abe988e794af4a59d8639%2C82a5b658f37d746a2f567bedd689fb99%2C70f3a74c320231aaeb343329477a886a",
		"uid=DbyhtVnUw/EgugBIBSAoAg==",
	);
	$str = implode(";", $arC);
	curl_setopt($ch, CURLOPT_COOKIE, $str);
	prent($model_id);prent($useragent);
//	curl_setopt($ch, CURLOPT_PROXY, '158.69.223.85:80');
//	curl_setopt($ch, CURLOPT_HEADER, true);
		 
	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
//	if($info["header_size"] < 1000) unlink("/tmp/yandex_tmp/{$model_id}.txt");
	
//prent($info);
	curl_close($ch);
	fclose($fp);
	if($info["header_size"] > 1000) return true;
	return false;
}

/*
$handle = @fopen("/var/www/bitrix/data/www/tempusshop.ru/admin/useragent.txt", "r");
if ($handle) {
    while (($buffer = fgets($handle, 4096)) !== false) {
		$arUserAgent[] = $buffer;
    }
}
*/
$last = CProSet::getOption("LAST_PARSE_YANDEX_ID");
$arFilter = Array(
	"IBLOCK_ID"		=> CProSet::IB_CATALOG,
	"!PROPERTY_YANDEX_MODEL_ID" => false,
	">CATALOG_QUANTITY" => 0,
//	"ID" => 1001, 
	">ID"			=> $last,
//	"PROPERTY_YANDEX_MODEL_ID" => "3965632",
);

$res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nPageSize" => 2), array("ID", "NAME", "XML_ID", "PROPERTY_YANDEX_MODEL_ID"));
while($ar_fields = $res->GetNext()){
	$arResult["ITEMS"][] = $ar_fields;
}
// prent($arResult);
$html = "";
foreach($arResult["ITEMS"] as $key => $arItem){
//	if($key % 10 == 0) sleep(15);
	$urlParse = "https://market.yandex.ru/product/{$arItem["PROPERTY_YANDEX_MODEL_ID_VALUE"]}/reviews?track=tabs";
	$filename = "/tmp/yandex_tmp/{$arItem["PROPERTY_YANDEX_MODEL_ID_VALUE"]}.txt";
	if (!file_exists($filename) || filesize($filename) < 50000) {
//		$rand_keys = array_rand($arUserAgent, 1);//prent($arItem);
		//prent($arUserAgent[$rand_keys]);
		$page = createfile2Parse($urlParse, $arItem["PROPERTY_YANDEX_MODEL_ID_VALUE"]);
		if($page === true)
			CProSet::setOption("LAST_PARSE_YANDEX_ID", $arItem["ID"]);
	}
}
 die;
$model_id = 2545301;
$page = createfile2Parse($urlParse, $model_id);
//$page = get_page($url);
//$page = file_get_contents($urlParse, true);
prent($page);



function set_utf8_meta($page){
	return preg_replace('/<head[^>]*>/',
			'<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">',
			$page);
}

$query = 'парсер яндекса';
$url = 'http://yandex.ru/yandsearch?text='. urlencode($query) .'&lr=213&numdoc=50'; // 213 - Это регион (Москва), 50 - кол-во позиций первой сттраницы выдачи

$url = "https://market.yandex.ru/product/2545301/reviews?track=tabs";

$page = get_page($url);
$page = set_utf8_meta($page);

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->resolveExternals = false; 
$dom->validateOnParse = false;
$dom->loadHTML($page);
$xpath = new DOMXpath($dom);

//$serp_items = $xpath->query('//div[contains(@class, "serp-block") and not(contains(@class, "-adv"))]//*[contains(@class, "serp-item__wrap")]');
$serp_items = $xpath->query('//div[contains(@class, "layout layout_type_maya")]');
//echo $serp_items->length; // кол-во результатов на странице
prent($serp_items);
$links = array();

foreach ($serp_items as $k=>$item)
{
	$_tmp = array();
	$header_obj = $xpath->query('./h2', $item)->item(0);
	$rev = $xpath->query('//div[contains(@class, "n-product-review-item")]', $item)->item(0);
	prent($rev);
/*
	$_tmp['position'] = $k+1;
	
	$link_obj = $xpath->query('./a', $header_obj)->item(0);
	$_tmp['url'] = $link_obj->getAttribute('href');
	$_tmp['url_text'] = trim(preg_replace('/\s+/i', ' ', $link_obj->nodeValue));
	
	$links[] = $_tmp;*/
}

var_dump($links);