<?php
//возвращает 1 если на основном (по умолчанию) и 2 если на tempus.by
function getTypePriceID(){
	if($_SERVER["HTTP_HOST"] == "dev.tempus.by")
		return 2;
	else
		return 1;
}

//возвращает код свойства привязки к сайтам для раздела UF_SITE_ID
function getPropID_SITE_ID(){
	if(SITE_ID == "s1") return 65; //else return 8;
	if(SITE_ID == "s2") return 66;
	if(SITE_ID == "s3") return 67;
}
function prent($mas, $file = false, $show = false, $prent = true) {
    global $USER;
    //if ($USER->isAdmin() || $show) {
	if ($USER->getID() == 587 || $show) {
        ob_start();
        print_r($mas);
        $content = ob_get_contents() . "\r\n";
        ob_end_clean();
        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/pre.log", $content);
		//file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/pre.log", $content, FILE_APPEND | LOCK_EX);
        if (!$file) {
            echo "<pre style=\"text-align:left; background-color:#FFF;color:#000;font-size:12px;\">";
			//$arBacktrace = Bitrix\Main\Diag\Helper::getBackTrace(6, ($bShowArgs? null : DEBUG_BACKTRACE_IGNORE_ARGS));
            if ($prent) {
                print_r($mas);
            } else
                var_dump($mas);
            echo "</pre><hr/>";
        }
    }
}
function json_encode_cyr($str) {
	$arr_replace_utf = array('\u0410', '\u0430','\u0411','\u0431','\u0412','\u0432',
		'\u0413','\u0433','\u0414','\u0434','\u0415','\u0435','\u0401','\u0451','\u0416',
		'\u0436','\u0417','\u0437','\u0418','\u0438','\u0419','\u0439','\u041a','\u043a',
		'\u041b','\u043b','\u041c','\u043c','\u041d','\u043d','\u041e','\u043e','\u041f',
		'\u043f','\u0420','\u0440','\u0421','\u0441','\u0422','\u0442','\u0423','\u0443',
		'\u0424','\u0444','\u0425','\u0445','\u0426','\u0446','\u0427','\u0447','\u0428',
		'\u0448','\u0429','\u0449','\u042a','\u044a','\u042b','\u044b','\u042c','\u044c',
		'\u042d','\u044d','\u042e','\u044e','\u042f','\u044f'
	);
		
	$arr_replace_cyr = array('А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е',
		'Ё', 'ё', 'Ж','ж','З','з','И','и','Й','й','К','к','Л','л','М','м','Н','н','О','о',
		'П','п','Р','р','С','с','Т','т','У','у','Ф','ф','Х','х','Ц','ц','Ч','ч','Ш','ш',
		'Щ','щ','Ъ','ъ','Ы','ы','Ь','ь','Э','э','Ю','ю','Я','я'
	);
		
	$str1 = json_encode($str);
	$str2 = str_replace($arr_replace_utf,$arr_replace_cyr,$str1);
		
	return $str2;
}
function op_strip($string){
	$string = str_replace('&nbsp;', ' ', $string);
	$string = strip_tags($string);
	$string = preg_replace('/([^\pL\pN\pP\pS\pZ])|([\xC2\xA0])/u', ' ', $string);
	$string = str_replace('  ', ' ', $string);
	$string = trim($string);
	while (strripos($string, '  ') !== false){
		$string = str_replace('  ', ' ', $string);
	}
	return $string;
}
function file_force_download($file) {
  if (file_exists($file)) {
    // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
    // если этого не сделать файл будет читаться в память полностью!
    if (ob_get_level()) {
      ob_end_clean();
    }
    // заставляем браузер показать окно сохранения файла
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($file));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    // читаем файл и отправляем его пользователю
    readfile($file);
    exit;
  }
}
//правильное окончание слов
function get_correct_str($num, $str1, $str2, $str3) {
	$int_num = (int)str_replace(" ", "", $num);
    $val = $int_num % 100;
    
    if ($val > 10 && $val < 20) return $num .' '. $str3;
    else {
        $val = $int_num % 10;
        if ($val == 1) return $num .' '. $str1;
        elseif ($val > 1 && $val < 5) return $num .' '. $str2;
        else return $num .' '. $str3;
    }
}

function debug_microtime_float(){
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

// проверка IP по маске. Временно ставили для сокрытия разделов не из РФ
function checkIP($user_ip) {
	return true;
	$arAvailIP = array();
	$handle = @fopen($_SERVER["DOCUMENT_ROOT"] . "/upload/ip_russia.txt", "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			$str = str_replace(array("\n"), array(""), $buffer);
			$tmp = explode("-", $str);
			$arAvailIP[] = array(
				"start" => trim($tmp[0]),
				"end" => trim($tmp[1])
			);
		}
		fclose($handle);
	}
	//prent($arAvailIP);
	$status = false;
	if(count($arAvailIP) > 0){
		foreach($arAvailIP as $arIP){
			$status = (ip2long($user_ip)>=ip2long($arIP["start"]) && ip2long($user_ip)<=ip2long($arIP["end"]));
			if($status === true) return true;
		}
	}
	return false;
}

/**
 * Получить реальный IP пользователя
 * @return string
 */
function GetRealIP(){
    $proxy_headers = array(
        'CLIENT_IP',
        'FORWARDED',
        'FORWARDED_FOR',
        'FORWARDED_FOR_IP',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED_FOR_IP',
        'HTTP_PC_REMOTE_ADDR',
        'HTTP_PROXY_CONNECTION',
        'HTTP_VIA',
        'HTTP_X_FORWARDED',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED_FOR_IP',
        'HTTP_X_IMFORWARDS',
        'HTTP_XROXY_CONNECTION',
        'VIA',
        'X_FORWARDED',
        'X_FORWARDED_FOR'
    );

    foreach($proxy_headers as $proxy_header)
    {
        if(isset($_SERVER[$proxy_header]) && preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $_SERVER[$proxy_header])) /* HEADER ist gesetzt und dies ist eine g?ltige IP */ {
            return $_SERVER[$proxy_header];
        } else if(stristr(',', $_SERVER[$proxy_header]) !== FALSE) /* Behandle mehrere IPs in einer Anfrage(z.B.: X-Forwarded-For: client1, proxy1, proxy2) */ {
            $proxy_header_temp = trim(array_shift(explode(',', $_SERVER[$proxy_header]))); /* Teile in einzelne IPs, gib die letzte zur?ck und entferne Leerzeichen */

            if(($pos_temp = stripos($proxy_header_temp, ':')) !== FALSE) $proxy_header_temp = substr($proxy_header_temp, 0, $pos_temp); /* Entferne den Port */

            if(preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $proxy_header_temp)) return $proxy_header_temp;
        }
    }

    return $_SERVER['REMOTE_ADDR'];
}

function getNameMonth($num){
	$arNameMonth = array(
		"1" => "января",
		"2" => "февраля",
		"3" => "марта",
		"4" => "апреля",
		"5" => "мая",
		"6" => "июня",
		"7" => "июля",
		"8" => "августа",
		"9" => "сентября",
		"10" => "октября",
		"11" => "ноября",
		"12" => "декабря",
	);
	return $arNameMonth[intval($num)];
}
//номер рейтинга по имени
function getRatingNumber($ratingTitle){
	if(is_numeric($ratingTitle)) return $ratingTitle;
	$arNameMonth = array(
		"Жуть!" => 1,
		"Ниже среднего" => 2,
		"Средне" => 3,
		"Хорошо" => 4,
		"Отлично!" => 5,
	);
	return $arNameMonth[$ratingTitle];
}

function encrypt_decrypt($string, $decrypt = false){
    $output = false;

    $encrypt_method = "AES-256-CBC";
//    $secret_key = 'b2b tradeicsbel.by';
//    $secret_iv = 'secret_iv_tradeicsbel';
    $secret_key = 'OsPnr*42tOXp0cA|8KT7';
    $secret_iv = 'jtr*Idzx|4CmekX3ruI*';
    // hash
    $key = hash('sha256', $secret_key);
    
    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_iv), 0, 16);

    if( $decrypt === true ) {
		$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }else {
        $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
    }

    return $output;
}

function str_replace_once($search, $replace, $text) 
{ 
   $pos = strpos($text, $search); 
   return $pos!==false ? substr_replace($text, $replace, $pos, strlen($search)) : $text; 
}
function sort_nested_arrays($array, $args = array('COUNT_DAY' => 'asc'), $saveKey = false){
	//usort( $array, function( $a, $b ) use ( $args ){
	if($saveKey == false) $sort = "usort"; else $sort = "uasort";
	$sort( $array, function( $a, $b ) use ( $args ){
		$res = 0;

		$a = (object) $a;
		$b = (object) $b;

		foreach( $args as $k => $v ){
			if( $a->$k == $b->$k ) continue;

			$res = ( $a->$k < $b->$k ) ? -1 : 1;
			if( $v=='desc' ) $res= -$res;
			break;
		}

		return $res;
	} );

	return $array;
}


function getDeliveryDay($bitrix_id){
	global $DB;
	$strSql = "SELECT supplier_id,day_delivery,working_time FROM ci_model_delivery WHERE bitrix_id = '{$bitrix_id}' AND site_id = '" . SITE_ID . "'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	//if($bitrix_id == 120544){
	//	return getDeliveryText(2, $row["supplier_id"], $row["working_time"]);
	//}
	if($row = $results->Fetch()){
		if($row["day_delivery"] >= 0){
		//prent($row);
			return getDeliveryText($row["day_delivery"], $row["supplier_id"], $row["working_time"]);
		}
	}
	return false;
}

function getDeliveryDayByCode($bitrix_code){
	global $DB;
	$strSql = "SELECT supplier_id,day_delivery,working_time FROM ci_model_delivery WHERE bitrix_code = '{$bitrix_code}' AND site_id = '" . SITE_ID . "'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	if($row = $results->Fetch()){
		if($row["day_delivery"] >= 0){
		//prent($row);
			return getDeliveryText($row["day_delivery"], $row["supplier_id"], $row["working_time"]);
		}
	}
	return false;
}

function getDeliveryText($day_delivery, $supplier_id, $working_time){
	$days = array(
		'в воскресенье', 'в понедельник', 'во вторник', 'в среду',
		'в четверг', 'в пятницу', 'в субботу'
	);
	
	$curr_date = date("d-m-Y");
	$tomorrow_date = date("d-m-Y", strtotime(' +1 day'));
	$curr_day_number = date("N");
	$hour = date("H");
	
	
	//prent($day_delivery);
	//if(SITE_ID == "s1" && $day_delivery == 2 && date("H") < 10) $day_delivery = 1;
	
	if(SITE_ID == "s1"){
		/*
		если 0
		до 10 утра должно быть сегодня
		до 12 должно быть завтра
		после 12 тоже должно быть завтра
		*/
		$express = false;
		if($day_delivery == 0){
			if($hour < 10){
				//$express = true;
				$day_delivery_city = $day_delivery_country = 0;
			}elseif($hour < $working_time){
				$express = true;
				$day_delivery_city = 1;
				$day_delivery_country = 0;
			}else{
				$day_delivery_city = $day_delivery_country = 1;
			}
			
			$dd_city = getNextDay($curr_date, $day_delivery_city);
			
			$dn_city = date("N",  strtotime($dd_city));

			$dd_country = getNextBusinessDay($curr_date, $day_delivery_country);
			$dn_country = date("N",  strtotime($dd_country));

		}else{
			if($hour >= $working_time) $day_delivery += 1;
		
			$dd_city = getNextDay($curr_date, $day_delivery);
			$dn_city = date("N",  strtotime($dd_city));

			//$dd_country = getNextDay($curr_date, $day_delivery);
			$dd_country = getNextBusinessDay($curr_date, $day_delivery);
			$dn_country = date("N",  strtotime($dd_country));
			//$dd_country = $curr_date;
			//$dn_country = $curr_day_number;
		}

		
		return array(
			"CITY" => array(
				"express" => $express,
				"day_delivery" => $dd_city,
				"day_text" => ($dd_city == $curr_date ? "сегодня" : ($dd_city == $tomorrow_date ? "завтра" : $days[$dn_city])),
			),
			"COUNTRY" => array(
				"day_delivery" => $dd_country,
				"day_text" => ($dd_country == $curr_date ? "сегодня" : ($dd_country == $tomorrow_date ? "завтра" : $days[$dn_country])),
			),
		);
	}
	
	//if(SITE_ID == "s2" && $supplier_id == 44) $day_delivery = 0;// || (SITE_ID == "s1" && $supplier_id == 47)

	if(SITE_ID == "s2"){
	
		if($day_delivery == 0){
			if(date("H") < 18){
				$dd_city = $curr_date;
				$dn_city = $curr_day_number;
			}else{
				$dd_city = getNextDay($curr_date, 1);
				$dn_city = date("N",  strtotime($dd_city));
			}
			
			if(date("H") < 12){
				$dd_country = $curr_date;
				$dn_country = $curr_day_number;
			}else{
				//18122020 $dd_country = getNextBusinessDay($curr_date, 1);
				$dd_country = getNextDay($curr_date, 1);
				$dn_country = date("N",  strtotime($dd_country));
			}
		}else{
/*
До 15
Четверг - пятница
Пятница-суббота
Суббота -вторник
Все-вторник
Понедельник-вторник


После 15
Четверг - суббота
Пятница - вторник
Суббота - вторник
Вск - вторник
Понедельник - среда
*/
			if($hour < $working_time){
				if($curr_day_number >= 6){
					//18122020 $dd_city = getNextBusinessDay($curr_date, $day_delivery + 1);
					$dd_city = getNextDay($curr_date, $day_delivery);
				}else{
					$dd_city = getNextDay($curr_date, $day_delivery);
				}
			}else{
				//$day_delivery +=1;
				if($curr_day_number == 4){
					if($day_delivery == 1){
						$dd_city = getNextDay($curr_date, 2);
					}else{
						//18122020 $dd_city = getNextBusinessDay($curr_date, $day_delivery + 1);
						$dd_city = getNextDay($curr_date, $day_delivery + 1);
					}
				}elseif($curr_day_number >= 5){
					//18122020 $dd_city = getNextBusinessDay($curr_date, $day_delivery + 1);
					$dd_city = getNextDay($curr_date, $day_delivery + 1);
					//prent($dd_city);
				}else{
					$dd_city = getNextDay($curr_date, $day_delivery + 1);
					
				}
				
			}
			
			if(date("N", strtotime($dd_city)) == 7){
			//18122020	$dd_city = getNextDay($dd_city, 1);
			}
				
			$dn_city = date("N",  strtotime($dd_city));
				
			//$dd_country = getNextBusinessDay($curr_date, $day_delivery);
			//$dn_country = date("N",  strtotime($dd_country));
			
			$dd_country = $dd_city;
			$dn_country = date("N",  strtotime($dd_country));
		}

		return array(
			"CITY" => array(
				"day_delivery" => $dd_city,
				//"day_text" => ($dd_city != $curr_date ? ($tomorrow_date == $dd_city ? "завтра" : $days[$dn_city]) : "сегодня"),
				"day_text" => ($dd_city == $curr_date ? "сегодня" : ($dd_city == $tomorrow_date ? "завтра" : $days[$dn_city])),
			),
			"COUNTRY" => array(
				"day_delivery" => $dd_country,
				//"day_text" => ($dd_country != $curr_date ? $days[$dn_country] : "сегодня"),
				"day_text" => ($dd_country == $curr_date ? "сегодня" : ($dd_country == $tomorrow_date ? "завтра" : $days[$dn_country])),
			),
		);
	}
	if(SITE_ID == "s2"){
	
		//$day_city = (date("H") < 15 ? $curr_date : date('d-m-Y', strtotime("tomorrow")));
		//$day_country = (date("H") < 11 ? $curr_date : date('d-m-Y', strtotime("tomorrow")));
		
		if($day_delivery == 0){
			if(date("H") < 18){
				$dd_city = $curr_date;
				$dn_city = $curr_day_number;
			}else{
				$dd_city = getNextBusinessDay($curr_date, 1);
				$dn_city = date("N",  strtotime($dd_city));
			}
			
			if(date("H") < 12){
				$dd_country = $curr_date;
				$dn_country = $curr_day_number;
			}else{
				$dd_country = getNextBusinessDay($curr_date, 1);
				$dn_country = date("N",  strtotime($dd_country));
			}
		}else{
			if($supplier_id == 44 || ($day_delivery == 1 && $hour < $working_time))
				$dd_city = getNextDay($curr_date, $day_delivery);
			else{
				if($curr_day_number > 5 || ($curr_day_number == 5 && $hour > $working_time)){
					$day_delivery += 1;
				}
				$dd_city = getNextBusinessDay($curr_date, $day_delivery);
			}
				
				
			$dn_city = date("N",  strtotime($dd_city));
				
			$dd_country = getNextBusinessDay($curr_date, $day_delivery);
			$dn_country = date("N",  strtotime($dd_country));
		}
		/*
		if(date("H") < 17){
			$dd_city = $curr_date;
			$dn_city = $curr_day_number;
		}else{
			$dd_city = getNextBusinessDay($curr_date, 1);
			$dn_city = date("N",  strtotime($dd_city));
		}

		if($curr_day_number < 7){
			if(date("H") < 11 && $curr_day_number < 6){
				$dd_country = $curr_date;
				$dn_country = $curr_day_number;
			}else{
				$dd_country = getNextBusinessDay($curr_date, 1);
				$dn_country = date("N",  strtotime($dd_country));
			}
		}else{
			$dd_country = getNextBusinessDay($curr_date, 1);
			$dn_country = date("N",  strtotime($dd_country));
		}
		*/
		return array(
			"CITY" => array(
				"day_delivery" => $dd_city,
				//"day_text" => ($dd_city != $curr_date ? ($tomorrow_date == $dd_city ? "завтра" : $days[$dn_city]) : "сегодня"),
				"day_text" => ($dd_city == $curr_date ? "сегодня" : ($dd_city == $tomorrow_date ? "завтра" : $days[$dn_city])),
			),
			"COUNTRY" => array(
				"day_delivery" => $dd_country,
				//"day_text" => ($dd_country != $curr_date ? $days[$dn_country] : "сегодня"),
				"day_text" => ($dd_country == $curr_date ? "сегодня" : ($dd_country == $tomorrow_date ? "завтра" : $days[$dn_country])),
			),
		);
	}

	$cnt_day_city = $cnt_day_country = $day_delivery;
	
	$flgNextDay = true;
	if(SITE_ID == "s1"){
		$flgNextDay = false;
		if($day_delivery == 2){
			if(date("H") < 10) {
				$cnt_day_city = 1;
				$cnt_day_country = 0;
			}else{
				$cnt_day_country = 1;
			}
			
		}else{
			//if(date("H") < 15) $cnt_day_city = $day_delivery - 1;
			if(date("H") < 15) $cnt_day_country = $day_delivery - 1;
		}
		$dd_city = getNextDay($curr_date, $cnt_day_city);
		$dd_country = getNextBusinessDay($curr_date, $cnt_day_country);
	}else{
		if($day_delivery == 2){
			$flgNextDay = false;
			if(date("H") < 10) {
				$cnt_day_city = 1;
				$cnt_day_country = 1;
			}
			
		}else{
			if(date("H") < 15) {$cnt_day_city = $day_delivery - 1; /*$flgNextDay = false;*/}
			if(date("H") < 11) {$cnt_day_country = $day_delivery - 1; /*$flgNextDay = false;*/}
		}
		
		$dd_city = getNextBusinessDay($curr_date, $cnt_day_city);
		$dd_country = getNextBusinessDay($curr_date, $cnt_day_country);
		
	}

	

	
	//$dd_city = date('Y-m-d', strtotime($dd_city . ' +1 Day'));
	//$dd_country = date('Y-m-d', strtotime($dd_country . ' +1 Day'));
	if($flgNextDay == true){
		$dd_city = getNextDay($dd_city, 1);
		$dd_country = getNextDay($dd_country, 1);
	}

	$dn_city = date("N",  strtotime($dd_city));
	$dn_country = date("N",  strtotime($dd_country));
	//prent($dd_city);
	return array(
		"CITY" => array(
			"day_delivery" => $dd_city,
			//"day_text" => ($dd_city != $curr_date ? $days[$dn_city] : "сегодня"),
			"day_text" => ($dd_city == $curr_date ? "сегодня" : ($dd_city == $tomorrow_date ? "завтра" : $days[$dn_city])),
		),
		"COUNTRY" => array(
			"day_delivery" => $dd_country,
			//"day_text" => $days[$dn_country],
			"day_text" => ($dd_country == $curr_date ? "сегодня" : ($dd_country == $tomorrow_date ? "завтра" : $days[$dn_country])),
		),
	);
}
//следующий рабочий день
function getNextBusinessDay($date = false, $day_delivery, $format = "d-m-Y"){
	if($date == false) $date = date($format);
	
	//$holidays = ['01-05-2021', '02-05-2021', '03-05-2021', '04-05-2021', '05-05-2021', '06-05-2021', '07-05-2021', '08-05-2021', '09-05-2021'];
	//$holidays = ['03.05.2021', '04.05.2021', '05.05.2021', '06.05.2021', '07.05.2021'];
	$holidays = array('01-05-2021');
	$i = $day_delivery;
	$nextBusinessDay = date($format, strtotime($date . ' +' . $i . ' Weekday'));

	while (in_array($nextBusinessDay, $holidays)) {
		$i++;
		$nextBusinessDay = date($format, strtotime($date . ' +' . $i . ' Weekday'));
	}
	return $nextBusinessDay;
}
//следующий день без учета выходных. с учетом вдруг праздник
function getNextDay($date = false, $day_delivery){
	if($date == false) $date = date("d-m-Y");
	
	$holidays = ['06-11-2019', '31-12-2019'];
	$i = $day_delivery;
	$nextDay = date('d-m-Y', strtotime($date . ' +' . $i . ' Day'));

	while (in_array($nextDay, $holidays)) {
		$i++;
		$nextDay = date('d-m-Y', strtotime($date . ' +' . $i . ' Day'));
	}
	return $nextDay;
}

function getCountryItem($ID){
	$country = false;
	global $DB;
	$arCountry = array(
		109	=> "Япония",
		110	=> "Швейцария",
		111	=> "США",
		112 => "Швеция",
		113	=> "Дания",
		114	=> "Италия",
		508	=> "Великобритания",
		516	=> "Беларусь",
		803	=> "Россия",
		806	=> "Австрия",
	);
	$arCountryModify = array(
		109	=> "Японские",
		110	=> "Швейцарские",
		111	=> "Американсикие",
		112 => "Швецкие",
		113	=> "Дацкие",
		114	=> "Итальянские",
		508	=> "Английские",
		516	=> "Белорусские",
		803	=> "Российские",
		806	=> "Австрийские",
	);
	$strSql = "SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID = '{$ID}' AND IBLOCK_PROPERTY_ID = '144'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	if($row = $results->Fetch()){
		$country = array("ID" => $row["VALUE"], "NAME" => $arCountry[$row["VALUE"]], "NAME_MODIFY" => $arCountryModify[$row["VALUE"]]);
	}
	
	return $country;
}
//изменение фильтра на странице Популярных товаров
function modifyFilterPopular($arFilter){
	//Механические часы
	if($arFilter["PROPERTY_POPULAR_TAG"][0] == 814){
		/*unset($arFilter["PROPERTY_POPULAR_TAG"]);
		$arFilterNew = array(
			"LOGIC" => "OR",
			array("PROPERTY_POPULAR_TAG" => 814),
			array("PROPERTY_MECHANISM" => array(464,465,481)),
		);
		$arFilter = array_merge($arFilter, $arFilterNew);*/
		$arFilter["PROPERTY_MECHANISM"] = array(464,465,481);
		//464	Механический с ручным подзаводом
		//465	Автоматический с ручным подзаводом
		//481	Механический с автоподзаводом
	}
	//Кварцевые часы
	if($arFilter["PROPERTY_POPULAR_TAG"][0] == 815){
		unset($arFilter["PROPERTY_POPULAR_TAG"]);
	}
	//Водонепроницаемые часы
	if($arFilter["PROPERTY_POPULAR_TAG"][0] == 817){
		unset($arFilter["PROPERTY_POPULAR_TAG"]);
	}
	return $arFilter;
}

function modifyTextItem($text, $section = "", $register = "", $type = 1){
	$country = false;
	global $DB;

	
	if($section[0]["CODE"] == "accessories"){
		$arText = array(
			"Япония" => "Японская",
			"Швейцария" => "Швейцарская",
			"США" => "Американсикая",
			"Швеция" => "Швецкая",
			"Дания" => "Дацкая",
			"Италия" => "Итальянская",
			"Великобритания" => "Английская",
			"Беларусь" => "Белорусская",
			"Россия" => "Российская",
			"Австрия" => "Австрийская",
			//"оригинальные" => "оригинальную",
		);
	} else{
		$arText = array(
			"Япония" => "Японские",
			"Швейцария" => "Швейцарские",
			"США" => "Американсикие",
			"Швеция" => "Швецкие",
			"Дания" => "Дацкие",
			"Италия" => "Итальянские",
			"Великобритания" => "Английские",
			"Беларусь" => "Белорусские",
			"Россия" => "Российские",
			"Австрия" => "Австрийские",
		);
	}

	if($arText[$text]) $text = $arText[$text];
//	if($type == 1) $text = $text . " наручные часы";
	if(strlen($section[0]["CODE"]) > 0){
		if($section[0]["CODE"] == "accessories") {
			if(count($section) > 2)
				$text = $text . " аксессуары";
		}elseif($type == 1){
			$text = $text . " наручные часы";
		}
		else {
			$text = $text . " часы";
		}
	}
	
	if($type == 1) {
	//	$text = $text . " наручные часы";
	}
	
	if($register == "upper") $text = mb_strtoupper($text);
	if($register == "lower") $text = mb_strtolower($text);
	

	
	
	return $text;
}

function retailCrmBeforeOrderSave($order){
    //Ваши изменения
	//AddMessage2Log($order);
	if($order["delivery"]["time"]["from"]){
		AddOrderProperty(10, $order["delivery"]["time"]["from"], $order["externalId"]);
	}
	if($order["delivery"]["time"]["to"]){
		AddOrderProperty(11, $order["delivery"]["time"]["to"], $order["externalId"]);
	}
	if($order["delivery"]["date"]){
		AddOrderProperty(56, date("d.m.Y", strtotime($order["delivery"]["date"])), $order["externalId"]);
	}
    return $order;
    //либо return false; и тогда изменения из системы по этому заказу будут проигнорированы
}

function retailCrmAfterOrderSave($order){
	
	//$track = $order["customFields"]["track"];
	CModule::IncludeModule("sale");
	global $DB;
	/*
	if($order["customFields"]["track"]){
		//пишем Идентификатор отправления в свойства заказа в битриксе
		
		$objOrder = \Bitrix\Sale\Order::load($order["externalId"]);
		$ShipmentCollection = $objOrder->getShipmentCollection();
		foreach ($ShipmentCollection as $ship) {
			if (!$ship->isSystem()){
				$ship->setFields(array(
					'TRACKING_NUMBER' => $order["customFields"]["track"]
				));
			}
		}
		$objOrder->save();
		//if (!$objOrder->isSuccess())
		//{ 
			//var_dump($objOrder->getErrorMessages());
		//	CLog::add2log(array("event" => "ER", "text" => "Ошибка записи TRACKING_NUMBER из crm", "detail" => $objOrder->getErrorMessages()));
			//AddMessage2Log($objOrder->getErrorMessages());
		//}
		
	}
	
	if($order["customFields"]["instock"] == true){
		$objOrder = \Bitrix\Sale\Order::load($order["externalId"]);
		$site_id = $objOrder->getField('LID');

		$propertyCollection = $objOrder->getPropertyCollection();
		$propertyValue = $propertyCollection->getPhone();
		$phone = $propertyValue->getField('VALUE');
		
		$ACCOUNT_NUMBER = $objOrder->getField('ACCOUNT_NUMBER');
		
		
		if($site_id == "s2" && $phone){
			
			$template = COption::GetOptionString("panel.manager", "SMS_ORDER_DELIVERY_s2");
			$message = str_replace(array("#ORDER_NUMBER#"), array($ACCOUNT_NUMBER), $template);
			
			//$message = "Ваш заказ №{$ACCOUNT_NUMBER} доставлен в магазин по адресу Минск, ул. Немига, 3, второй уровень, пав.10";
			sendSMS($phone, $message);
		}
		//prent($asd);
	}*/
	
	//пишим id заказа в crm себе в базу
	$orderCrmID = $order["id"];
	$orderID = $order["externalId"];
	
	
	if(CModule::IncludeModule("panel.manager")){
		//AddMessage2Log($order);
		OrderService::setOrderCrmID($orderID, $orderCrmID);
	}
	
	// если сайт s2 то обновляем внутренний счет покупателя.
	if($order["site"] == "tempus-by"){
		
		$arUser = array();
		
		if($order["customer"]["externalId"] > 0){
			$arUser[] = $order["customer"]["externalId"];
		}elseif($order["contact"]["externalId"]){
			$arUser[] = $order["contact"]["externalId"];
		}elseif($order["externalId"]){

			$strSql = "SELECT CREATED_BY,USER_ID,EMP_STATUS_ID,EMP_PAYED_ID FROM b_sale_order WHERE ID = '{$order["externalId"]}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			$arUser = array();
			
			if ($row = $results->Fetch()){
				if($row["CREATED_BY"]) $arUser[] = $row["CREATED_BY"];
				if($row["USER_ID"]) $arUser[] = $row["USER_ID"];
				if($row["EMP_STATUS_ID"]) $arUser[] = $row["EMP_STATUS_ID"];
				if($row["EMP_PAYED_ID"]) $arUser[] = $row["EMP_PAYED_ID"];
			}
			
			
		}
		
		$arUser = array_unique($arUser);

		if(count($arUser) > 0){
			
			foreach($arUser as $userID){
				$loyalty = getUserLoyaltyCRM($userID, "tempus-by");
				
				if($loyalty["active"]){
					$currency = "BYN";
					$notes = "Изменено из CRM";
					$res = updateInternalAccount($userID, $loyalty["amount"], $currency, $notes);
				}
			}

			
		}
	}
	
	return;
}

function getUserLoyaltyCRM($userID, $site = "tempus-by"){
	
	if(!CModule::IncludeModule("crm_courier")){
		$APPLICATION->ThrowException("module crm_courier not installed");
		return False;
	}
	
	$userID = (int)$userID;
	if ($userID <= 0){
		$APPLICATION->ThrowException("Invalid userID");
		return False;
	}
	
	if (!in_array($site, array("tempus-by", "tempusshop-ru123"))){
		$APPLICATION->ThrowException("Invalid site");
		return False;
	}
	
	$obj = new CCourier();
	$api = new CCourierRetail(RetailcrmConfigProvider::getApiUrl(), RetailcrmConfigProvider::getApiKey());

	$ar = array(
		"limit" => 20,
		"filter" => array(
			"status" => "activated",
			"customerExternalId" => $userID,
			"sites" => array($site),
		)
	);
	
	$response = $api->loyaltyList($ar);
	$response = objectToArray($response);
	
	if($response["response"]["success"] && $response["response"]["pagination"]["totalCount"] == 1){
		$loyalty = $response["response"]["loyaltyAccounts"][0];
		return $loyalty;
	}
	return false;
}

function updateInternalAccount($userID, $sum, $currency, $notes = ""){
	global $DB, $APPLICATION, $USER;
	//AddMessage2Log($userID);AddMessage2Log($sum);AddMessage2Log($currency);
	if(!CModule::IncludeModule("sale")){
		$APPLICATION->ThrowException("module sale not installed");
		return False;
	}
	
	$userID = (int)$userID;
	if ($userID <= 0){
		$APPLICATION->ThrowException(GetMessage("SKGU_EMPTYID"), "EMPTY_USER_ID");
		return False;
	}
	$dbUser = CUser::GetByID($userID);
	if (!$dbUser->Fetch()){
		$APPLICATION->ThrowException(str_replace("#ID#", $userID, GetMessage("SKGU_NO_USER")), "ERROR_NO_USER_ID");
		return False;
	}

	$sum = (float)str_replace(",", ".", $sum);

	$currency = trim($currency);
	if ($currency === ''){
		$APPLICATION->ThrowException(GetMessage("SKGU_EMPTY_CUR"), "EMPTY_CURRENCY");
		return False;
	}

	if (!CSaleUserAccount::Lock($userID, $currency)){
		$APPLICATION->ThrowException(GetMessage("SKGU_ACCOUNT_NOT_WORK"), "ACCOUNT_NOT_LOCKED");
		return False;
	}

	$result = false;

	$dbUserAccount = CSaleUserAccount::GetList(
			array(),
			array("USER_ID" => $userID, "CURRENCY" => $currency)
	);
	
	if ($arUserAccount = $dbUserAccount->Fetch()){
		
		$arFields = array(
			"=TIMESTAMP_X" => $DB->GetNowFunction(),
			"CURRENT_BUDGET" => $sum
		);

		if (!empty($notes)){
			$arFields['NOTES'] = $notes;
		}

		$result = CSaleUserAccount::Update($arUserAccount["ID"], $arFields);
		
	}else{
		
		$arFields = array(
			"USER_ID" => $userID,
			"CURRENT_BUDGET" => $sum,
			"CURRENCY" => $currency,
			"LOCKED" => "Y",
			"=TIMESTAMP_X" => $DB->GetNowFunction(),
			"=DATE_LOCKED" => $DB->GetNowFunction()
		);

		if (!empty($notes)){
			$arFields['NOTES'] = $notes;
		}
		$result = CSaleUserAccount::Add($arFields);
		
	}

	if ($result){
		if (isset($GLOBALS["SALE_USER_ACCOUNT"]["SALE_USER_ACCOUNT_CACHE_".$userID."_".$currency]))
			unset($GLOBALS["SALE_USER_ACCOUNT"]["SALE_USER_ACCOUNT_CACHE_".$userID."_".$currency]);

	}

	CSaleUserAccount::UnLock($userID, $currency);
	return $result;
	
}

function retailCrmBeforeOrderSend($order, $arFields)
{
    //Ваши изменения
	//vatRate
	//AddMessage2Log($order);
	//AddMessage2Log($arFields);
	//ставим ставку НДС - БЕЗ НДС
	foreach($order["items"] as $key => $arItem){
		$order["items"][$key]["vatRate"] = "none";//
		//$order["items"][$key]["offer"]["vatRate"] = "none";
	}
	//AddMessage2Log($order);
	//if($order["externalId"] == 46247){
		/*		
		$roistat = "";
		foreach($arFields["PROPS"]["properties"] as $key => $arItem){
			if($arItem["CODE"] == "ROISTAT_VISIT"){
				$roistat = $arItem["VALUE"][0];
				break;
			}
		}
		
		$order["customFields"]["roistat"] = $roistat;
		*/
		
		$order["customFields"]["roistat"] = array_key_exists('roistat_visit', $_COOKIE) ? $_COOKIE['roistat_visit'] : 'nocookie';
		
		$arCoupon = array();
		if($arFields["DISCOUNTS"]["COUPON_LIST"]){
			foreach($arFields["DISCOUNTS"]["COUPON_LIST"] as $key => $arItem){
				$arCoupon[] = $arItem["COUPON"];
			}
		}
		$order["customFields"]["coupon"] = implode(",", $arCoupon);
		//AddMessage2Log($order);
	//}
	//AddMessage2Log($order);
	//AddMessage2Log($_COOKIE);
	
	
	$order["customFields"]["utmstat"] = isset($_COOKIE['utmstat_client_id']) ? $_COOKIE['utmstat_client_id'] : null;
	//свойства дата доставки, время
	
	foreach($arFields["PROPS"]["properties"] as $key => $arItem){
		if($arItem["ID"] == 56){
			$date = $arItem["VALUE"][0];
		}
		//Желаемое время доставки от
		if($arItem["ID"] == 10){
			$dateFrom = $arItem["VALUE"][0];
		}
		//Желаемое время доставки до
		if($arItem["ID"] == 11){
			$dateTo = $arItem["VALUE"][0];
		}
	}
	
	if($date) $order["delivery"]["date"] = date("Y-m-d", strtotime($date));//$date;
	if($dateFrom && $dateTo){
		$order["delivery"]["time"]["from"] = $dateFrom;
		$order["delivery"]["time"]["to"] = $dateTo;
	}
	//if($dateTo) $order["deliveryTime"]["to"] = $dateTo;
	//AddMessage2Log($order);
	
	foreach($order["payments"] as $key => $arItem){
		
		if($arItem["type"] == "internal-account" && $arItem["amount"]){
			$order["managerComment"] .= "Оплата бонусами {$arItem["amount"]} рублей";
		}
		
	}
	
    return $order;
    //либо return false; и тогда данные отправлены в систему не будут
}



function custom_mail($to, $subject, $message, $additional_headers='', $additional_parameters='')
{
	if(strripos($additional_headers, "@tempusshop.pl") !== false){
		$additional_parameters = "-finfo@tempusshop.pl";
	}elseif(strripos($additional_headers, "@tempus.by") !== false){
		$additional_parameters = "-finfo@tempus.by";
	}
	/*
	AddMessage2Log(
		'To: '.$to.PHP_EOL.
		'Subject: '.$subject.PHP_EOL.
		'Message: '.$message.PHP_EOL.
		'Headers: '.$additional_headers.PHP_EOL.
		'Params: '.$additional_parameters.PHP_EOL
	);*/
	if ($additional_parameters!='') {

		return @mail($to, $subject, $message, $additional_headers, $additional_parameters);
	} else {
		return @mail($to, $subject, $message, $additional_headers);
	}
}

function getProductTimestampByID($ID = 0){
	if($ID <= 0) return;
	global $DB;

	$strSql = "SELECT timestamp FROM ci_price_catalog WHERE product_id = '{$ID}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if($row = $results->Fetch()){
		return $row["timestamp"];
	}
	return false;
}
function getProductTimestamp($code = ""){
	if(strlen($code) <= 0) return date("m-d-Y H");
	global $DB;

	//$strSql = "SELECT timestamp FROM ci_price_catalog WHERE product_code = '{$code}'";
	switch(SITE_ID){
		case "s1":
			$col = "active";
			break;
		case "s1":
			$col = "active_by";
			break;
		case "s1":
			$col = "active_pl";
			break;
		default:
			$col = "active";
			break;
	}
	$strSql = "SELECT catalog.timestamp as timestamp, price.{$col} as active 
	FROM 
		ci_price_catalog catalog 
	LEFT OUTER JOIN ci_price price
		ON catalog.model = price.model
	WHERE 
		catalog.product_code = '{$code}'
	ORDER BY price.{$col} desc";
		
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	if($row = $results->Fetch()){
		return $row["timestamp"] . $row["active"];
		//return md5($row["timestamp"]);
		//echo "<input type='hidden' name='product_timestamp' value='".md5($row["timestamp"])."'>";
	}else{
		return date("m-d-Y H");
		//return md5(date("m-d-Y H"));
	}
}

function getProductTimestampByUrl($url = ""){
	if(strlen($url) <= 0) return false;
	global $DB;

	$strSql = "SELECT timestamp FROM ci_price_catalog WHERE detail_page_url = '{$url}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if($row = $results->Fetch()){
		return $row["timestamp"];
	}
	
	return false;
}

function getCountSection($elementFilter = array()){
	if($elementFilter["SECTION_ID"] <= 0) return false;
	global $DB;

	$strSql = "SELECT COUNT(*) as CNT
		FROM b_iblock_16_index FC 
		left join b_catalog_product as PRD on (PRD.ID = FC.ELEMENT_ID)
	WHERE ( (FC.SECTION_ID = '{$elementFilter["SECTION_ID"]}' AND FC.FACET_ID = 1 AND FC.VALUE_NUM = 0 AND FC.VALUE in (0)) AND PRD.AVAILABLE='Y')";
	 
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if($row = $results->Fetch()){
		return $row["CNT"];
	}
	
	return false;
}

function mb_upper_first($string, $encoding='UTF-8')
{
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then = mb_substr($string, 1, mb_strlen($string, $encoding)-1, $encoding);
    return mb_strtoupper($firstChar, $encoding) . $then;
}
function mb_lower_first($string, $encoding='UTF-8')
{
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then = mb_substr($string, 1, mb_strlen($string, $encoding)-1, $encoding);
    return mb_strtolower($firstChar, $encoding) . $then;
}

function clearDirCompositeCache($url = ""){
	if(strlen($url) <= 0) return false;
	$arSite = array("tempusshop.ru", "tempus.by", "tempusshop.pl");
	foreach($arSite as $site){
		$path = $_SERVER['DOCUMENT_ROOT'] . "/bitrix/html_pages/{$site}" . $url;
		array_map('unlink', glob("$path/*.*"));
		rmdir($path);
	}

}


// удяляем скрипты ядра при отдаче сайта пользователям
function deleteKernelJs(&$content) {
    global $USER, $APPLICATION;
    if((is_object($USER) && $USER->IsAuthorized()) || strpos($APPLICATION->GetCurDir(), "/bitrix/")!==false) return;
	if($_REQUEST["test_webp"] !== "Y") return;
    if($APPLICATION->GetProperty("save_kernel") == "Y") return;

    $arPatternsToRemove = Array(
        '/<script.+?src=".+?kernel_main\/kernel_main\.js\?\d+"><\/script\>/',
		'/<script.+?src=".+?kernel_main\/kernel_main_v1\.js\?\d+"><\/script\>/',
        '/<script.+?src=".+?bitrix\/js\/main\/core\/core[^"]+"><\/script\>/',
        '/<script.+?>BX\.(setCSSList|setJSList)\(\[.+?\]\).*?<\/script>/',
        '/<script.+?>if\(\!window\.BX\)window\.BX.+?<\/script>/',
        '/<script[^>]+?>\(window\.BX\|\|top\.BX\)\.message[^<]+<\/script>/',
		'/<script.+?src="https://mc.yandex.ru/metrika/tag.js" defer><\/script\>/',
    );

    $content = preg_replace($arPatternsToRemove, "", $content);
    $content = preg_replace("/\n{2,}/", "\n\n", $content);
}

// удяляем css ядра при отдаче сайта пользователям
function deleteKernelCss(&$content) {
    global $USER, $APPLICATION;
    if((is_object($USER) && $USER->IsAuthorized()) || strpos($APPLICATION->GetCurDir(), "/bitrix/")!==false) return;
    if($APPLICATION->GetProperty("save_kernel") == "Y") return;

    $arPatternsToRemove = Array(
        '/<link.+?href=".+?kernel_main\/kernel_main\.css\?\d+"[^>]+>/',
        '/<link.+?href=".+?bitrix\/js\/main\/core\/css\/core[^"]+"[^>]+>/',
 //       '/<link.+?href=".+?bitrix\/templates\/[\w\d_-]+\/styles.css[^"]+"[^>]+>/',
 //       '/<link.+?href=".+?bitrix\/templates\/[\w\d_-]+\/template_styles.css[^"]+"[^>]+>/',
    );

    $content = preg_replace($arPatternsToRemove, "", $content);
    $content = preg_replace("/\n{2,}/", "\n\n", $content);
}

function objectToArray($object){
    $output = [];
    foreach ((array) $object as $key => $value) {
        $output[preg_replace('/\000(.*)\000/', '', $key)] = $value;
    }

    return $output;
}


function sendSMS($phone = "", $message = ""){
	if(!$phone || !$message) return false;
	
	if(\Bitrix\Main\Loader::includeModule('mlife.smsservices')){

		$transport = new \Mlife\Smsservices\Sender();
		$phoneCheck = $transport->checkPhoneNumber($phone);
		$phone = $phoneCheck['phone'];
		if($phoneCheck['check']) {
			$arSend = (array)$transport->sendSms($phone, $message);
			if($arSend["id"] > 0)
				return true;
			//prent($arSend);
		}else{
			return "Не валидный телефон";
		}

	}
	return false;
}

function clearElementCache($IBLOCK_ID = 16, $ELEMENT_ID = 0){
	//AddMessage2Log($ELEMENT_ID); 
	global $CACHE_MANAGER;
	if($ELEMENT_ID <= 0) return false;
	\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($IBLOCK_ID, $ELEMENT_ID);
	
	$CACHE_MANAGER->ClearByTag("product_" . $ELEMENT_ID);
}
function clearSectionCache($SECTION_ID = 0){
	//AddMessage2Log($SECTION_ID); 
	global $CACHE_MANAGER;
	if($SECTION_ID <= 0) return false;

	$CACHE_MANAGER->ClearByTag("iblock_section_id_" . $SECTION_ID);
}


function clearBadTag($cachePath = ""){
	if(strlen($cachePath) <= 0) return false;
	global $DB;
	if (!$DB->Query("DELETE FROM b_cache_tag WHERE RELATIVE_PATH = '" . addslashes($cachePath) . "' AND (TAG = 'iblock_id_16' OR TAG = 'iblock_id_17')", false))
		return false;
}
function AddOrderProperty($prop_id, $value, $order) {
	if (!strlen($prop_id)) {
		return false;
	}
	if (CModule::IncludeModule('sale')) {
		if ($arOrderProps = CSaleOrderProps::GetByID($prop_id)) {
			$db_vals = CSaleOrderPropsValue::GetList(array(), array('ORDER_ID' => $order, 'ORDER_PROPS_ID' => $arOrderProps['ID']));
			if ($arVals = $db_vals->Fetch()) {
				return CSaleOrderPropsValue::Update($arVals['ID'], array(
					'NAME' => $arVals['NAME'],
					'CODE' => $arVals['CODE'],
					'ORDER_PROPS_ID' => $arVals['ORDER_PROPS_ID'],
					'ORDER_ID' => $arVals['ORDER_ID'],
					'VALUE' => $value,
				));
			} else {
				return CSaleOrderPropsValue::Add(array(
					'NAME' => $arOrderProps['NAME'],
					'CODE' => $arOrderProps['CODE'],
					'ORDER_PROPS_ID' => $arOrderProps['ID'],
					'ORDER_ID' => $order,
					'VALUE' => $value,
				));
			}
		}
	}
}
function AddOrderPropertyD7($orderPropertyId, $value, $orderId) {
	if (!strlen($orderPropertyId)) {
		return false;
	}
	if (CModule::IncludeModule('sale')) {
		$order = Bitrix\Sale\Order::load($orderId);
		
		$propertyCollection = $order->getPropertyCollection();
		
		$somePropValue = $propertyCollection->getItemByOrderPropertyId($orderPropertyId);
		$somePropValue->setValue($value);
		//prent($order); 
		$order->save();
	}
}
// Удаление брошенных корзин
function deleteOldBaskets(){
	if ( CModule::IncludeModule("sale") && CModule::IncludeModule("catalog") ){
		global $DB;
		$nDays = 10; // сроком старше 10 дней
		$nDays = IntVal($nDays);
		$strSql =
			"SELECT f.ID ".
			"FROM b_sale_fuser f ".
			"LEFT JOIN b_sale_order o ON (o.USER_ID = f.USER_ID) ".
			"WHERE ".
			"   TO_DAYS(f.DATE_UPDATE)<(TO_DAYS(NOW())-".$nDays.") ".
			"   AND o.ID is null ".
			"   AND f.USER_ID is null ".
			"LIMIT 3000";
		$db_res = $DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
		while ($ar_res = $db_res->Fetch()){
		//	prent($ar_res);
			CSaleBasket::DeleteAll($ar_res["ID"], false);
			CSaleUser::Delete($ar_res["ID"]);
		}
	}
	return "deleteOldBaskets();";
}
	function getDeliveryObjectById($deliveryId){
		
		$arResult = array();
		$obCache = new \CPHPCache();
		
		$arParams["CACHE_TIME"] = 86400;
		$arParams["CACHE_PATH"] = '/op/ymarket/' . $deliveryId . '/';
		$arParams["CACHE_ID"] = md5("asd");
				
		if($obCache->InitCache($arParams["CACHE_TIME"], $arParams["CACHE_ID"], $arParams["CACHE_PATH"])){
			$vars = $obCache->GetVars();// Извлечение переменных из кэша
			$arResult = $vars["arResult"];
		}elseif($obCache->StartDataCache()){
			$arResult = Bitrix\Sale\Delivery\Services\Manager::getObjectById($deliveryId);
			if ($arResult){
				$obCache->EndDataCache(
					array(
						"arResult" => $arResult
					)
				);
			}
		}
		return $arResult;
	}
	
	
function getNextBarcode($type = ""){
	$filename = $_SERVER["DOCUMENT_ROOT"] . "/upload/barcode{$type}.txt";
	$lines = file($filename);
	foreach($lines as $key => $barcode){
		$_barcode = str_replace(array("\r\n", "\r", "\n", " "), "", $barcode);
		//prent($barcode);
		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"PROPERTY_AEN{$type}" => $_barcode,
		);
		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID"));

		if($ob = $rs->GetNextElement()){
			unset($lines[$key]);
			//prent($lines);
			file_put_contents($filename, implode('', $lines));
			continue;
		}
		return $_barcode;
	}
	return false;
}

function getCntBarcode($type = ""){
	$filename = $_SERVER["DOCUMENT_ROOT"] . "/upload/barcode{$type}.txt";
	$lines = file($filename);
	
	return count($lines);
}

function getStickerWB($arOrder, $cabinet = "DEFAULT"){
	
	if(!is_array($arOrder) || count($arOrder) <= 0) return false;
	if(!CModule::IncludeModule("maxyss.wb")) return false;
//$arOrder = array(185576637);
	$data_string = array(
		"orderIds" => $arOrder,
		"type" => "qr",
	);
	
	$arSettings = CMaxyssWb::settings_wb($cabinet);
	
	$data_string = \Bitrix\Main\Web\Json::encode($data_string);
			
	$result = CRestQueryWB::rest_query_na("https://suppliers-api.wildberries.ru", $data_string, "/api/v2/orders/stickers", $arSettings["AUTHORIZATION"]);
	
	$res = \Bitrix\Main\Web\Json::decode($result);
	$arResult["ITEMS"] = array();
	//prent($res,0,1); 
	foreach($res["data"] as $key => $arItem){
		$arResult["ITEMS"][$arItem["orderId"]] = array(
			"ORDER_ID_WB" => $arItem["orderId"],
			"STICKER_PART_A" => $arItem["sticker"]["wbStickerIdParts"]["A"],
			"STICKER_PART_B" => $arItem["sticker"]["wbStickerIdParts"]["B"],
			"STICKER_ENCODING" => $arItem["sticker"]["wbStickerEncoded"],
		);
		//prent(filesize($_SERVER["DOCUMENT_ROOT"] . "/upload/wb/{$arItem["orderId"]}.svg"));
		//проверяем существует ли стикер. ечли нет, создаем
		//!file_exists($_SERVER["DOCUMENT_ROOT"] . "/upload/wb/{$arItem["orderId"]}.svg" || 
		if(filesize($_SERVER["DOCUMENT_ROOT"] . "/upload/wb/{$arItem["orderId"]}.svg") < 10){
			$image = base64_decode($arItem["sticker"]["wbStickerSvgBase64"]);
			//prent($arItem);
			$FPName = $arItem["orderId"] . '.svg';
			$FPPath = $_SERVER["DOCUMENT_ROOT"] . '/upload/wb/' . $FPName;
			file_put_contents($FPPath, $image, LOCK_EX);
			
			//prent($arItem["wbStickerSvgBase64"]);
		}
		
	}
	
	return $arResult["ITEMS"];
}

function getOrderMS($site_id = "s1"){
	global $DB;

	$strSql = "SELECT MS_ID FROM ci_ms_order WHERE SITE_ID = '{$site_id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arOrderMS[] = $row["MS_ID"];
	}
	
	if (!class_exists('MoyskladAPI')){
		require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/api_moysklad.php');
	}

	$obj = new MoyskladAPI($site_id);

	$arDB = array();

	$res = $obj->getListOrder(0);
	//prent($res);  
	foreach($res["rows"] as $key => $arItem){
		$arDB[$arItem["id"]] = array(
			"ORDER_NUMBER" => $arItem["name"],
			"MS_ID" => $arItem["id"],
			"META" => $arItem["meta"],
			"DATA" => $arItem,
		);
	}

	foreach($arDB as $key => $arItem){
		if(!in_array($arItem["MS_ID"], $arOrderMS)){
			$in = array(
				"ORDER_NUMBER" => "'".addslashes($arItem["ORDER_NUMBER"])."'",
				"SITE_ID" => "'".addslashes($site_id)."'",
				"MS_ID" => "'".addslashes($arItem["MS_ID"])."'",
				"META" => "'" . json_encode($arItem["META"]) . "'",
				"DATA" => "'" . json_encode($arItem["DATA"]) . "'"
			);
			
			//пишем всё во временную таблицу сразу
			$DB->Insert("ci_ms_order", $in, $err_mess.__LINE__);
		}
	}
	return "getOrderMS($site_id);";
}

function getFullPathSection(&$arSection = array(), $find){
	if(!$find) return false;
	global $DB;
	
	$ID = intval($ID);
	
	$strSql = "SELECT ID,NAME,IBLOCK_SECTION_ID,DEPTH_LEVEL FROM b_iblock_section WHERE ID = '{$find}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	if($row = $results->Fetch()){
		
		$arSection[] = array(
			"ID" => $row["ID"],
			"NAME" => $row["NAME"],
			"IBLOCK_SECTION_ID" => $row["IBLOCK_SECTION_ID"],
			"DEPTH_LEVEL" => $row["DEPTH_LEVEL"],
		);
		
		if($row["IBLOCK_SECTION_ID"] > 0){
			getFullPathSection($arSection, $row["IBLOCK_SECTION_ID"]);
		}
	}

	
}

function getSectionsElement($ID = 0){
	if(!$ID) return false;
	global $DB;
	
	$ID = intval($ID);
	
	$strSql = "SELECT IBLOCK_SECTION_ID FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID = '{$ID}' AND IBLOCK_SECTION_ID <> '370'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	while($row = $results->Fetch()){
		$ar[] = $row["IBLOCK_SECTION_ID"];
	}

	$arSection = array();
	foreach($ar as $section_id){
		getFullPathSection($arSection, $section_id);
	}
	
	$arSection = sort_nested_arrays($arSection, array('DEPTH_LEVEL' => 'asc'));
	
	return $arSection;

}
// /var/www/bitrix/data/www/tempusshop.ru/bitrix/components/bitrix/sale.order.ajax/class.php
// formatLocation
/*
страна
24 - Россия
1 - Беларусь
*/
function modifyPropLocation(&$arProperty){
	//проверяем. если город из России от подменяем на Минск для РБ
	$location = current($arProperty['VALUE']);
	if(SITE_ID == "s2"){
		if($location){
			$item = \Bitrix\Sale\Location\LocationTable::getById($location)->fetch();
			if($item["COUNTRY_ID"] != 1){
				$arProperty["DEFAULT_VALUE"] = "000000014333";
				$arProperty["VALUE"] = "11395";
			}
			//prent($item);
		}else{
			$arProperty["DEFAULT_VALUE"] = "000000014333";
			$arProperty["VALUE"] = "11395";
		}
	}
}

function translitArticle($str){
    $tr = array(
        "А" => "A", "В" => "В", 
        "Д"=>"D","Е"=>"E", "И"=>"I",
        "К"=>"K","Л"=>"L","М"=>"M","Н"=>"H",
        "О"=>"O","Р"=>"P","С"=>"C","Т"=>"T",
        "Х"=>"X",
		"а" => "a", "в" => "b", 
		"д" => "d", "е" => "e", "и"=>"i",
		"к"=>"k","л"=>"l", "м"=>"m","н"=>"n",
		"о"=>"o","р"=>"p", "с"=>"c","т"=>"t",
		"х"=>"h",

    );
    return strtr($str,$tr);
}

function getTradingOrderID($orderID = 0){
	if($orderID <= 0) return false;
	//b_sale_tp
	$res = \Bitrix\Sale\Internals\OrderTable::getList(array(
		'filter' => array(
			'=ID' => $orderID
		),
		'select' => array("SOURCE.TRADING_PLATFORM_ID"),
		'runtime' => array(
			'SOURCE' => array(
				'data_type' => '\Bitrix\Sale\TradingPlatform\OrderTable',
					'reference' => array(
						'ref.ORDER_ID' => 'this.ID',
					),
				'join_type' => 'left'
			)
		)
	));

	if($arOrder = $res->fetch()){
		//prent($arOrder);
		return $arOrder["SALE_INTERNALS_ORDER_SOURCE_TRADING_PLATFORM_ID"];
	}
	return false;
}


function getOnlinerKeyOrderID($orderID = 0){
	if($orderID <= 0) return false;
	
	$res = \Bitrix\Sale\Internals\OrderTable::getList(array(
		'filter' => array(
			'=ID' => $orderID
		),
		'select' => array('PROP__ONLINER_ORDER_KEY' => 'ONLINER_ORDER_KEY.VALUE'),
		'runtime' => array(
			new \Bitrix\Main\Entity\ReferenceField(
				'ONLINER_ORDER_KEY',
				'\Bitrix\Sale\Internals\OrderPropsValueTable',
				array(
					'=this.ID' => 'ref.ORDER_ID',
					'=ref.CODE' => new \Bitrix\Main\DB\SqlExpression('?s', 'ONLINER_ORDER_KEY')
				)
			),
		)
	));

	if($arOrder = $res->fetch()){
		if(strlen($arOrder["PROP__ONLINER_ORDER_KEY"]) > 0)
			return $arOrder["PROP__ONLINER_ORDER_KEY"];
	}
	return false;
}

function generateCoupon(){
	if(!CModule::IncludeModule("sale")) return false;


	$allchars = 'ABCDEFGHIJKLNMOPQRSTUVWXYZ0123456789';
	$charsLen = mb_strlen($allchars) - 1;

	do{
		$resultCorrect = true;
		$result = '';
		for ($i = 0; $i < 5; $i++)
			$result .= mb_substr($allchars, rand(0, $charsLen), 1);

		$existCoupon = \Bitrix\Sale\DiscountCouponsManager::isExist($result);
		$resultCorrect = empty($existCoupon);

	} while (!$resultCorrect);
	return $result;
}
function isJSON($string){
	return is_string($string) && is_array(json_decode($string, true)) ? true : false;
}