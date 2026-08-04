<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//$_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;

	
global $DB;

$obj = new CYandexParser();
$result = $obj->parse(); 

//prent($result);
$res = array(
	'status' => ($result["status"] == "Y" ? "ok" : "error"),
	'text' => ($result["status"] == "Y" ? "Выгрузка прошла успешно" : "Не удалось спарсить. " . $result["error"])
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();


/*
$path = "/var/www/bitrix/data/www/tempusshop.ru/upload/yandex.csv";
//$source = "https://tempusshop:123456@pricelabs.yandex.ru/export/tempusshop-market@yandex.ru/tempusshop.ru/prices.csv";
$source = "https://tempusshop:123456@pricelabs.yandex.ru/export/tempusshop-market@yandex.ru/tempusshop.ru/prices.csv";

$strSql = "SELECT el.ID as ID, pr.VALUE as ARTICLE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr 
		ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE 
		el.ACTIVE = 'Y' 
		AND el.IBLOCK_ID IN ('16','17') AND pr.IBLOCK_PROPERTY_ID IN ('121','123') AND pr.VALUE <> ''";
		
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arArticle = array();
while ($arFields = $results->Fetch()){
	$arArticle[$arFields["ID"]] = $arFields["ARTICLE"];
}
//		prent($arArticle);die;
$result["status"] = "N";
if(copy($source, $path)){
//if(true){
	//подключаем класс для работы с csv
	require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/csv.class.php');
	$csv = new CSV($path);
	
	$arResult["YMARKET_SHOP_HIDE"] = json_decode(CProSet::getOption("YMARKET_HIDE_SHOP"), true);
	if($csv->error != "no_found"){

		$arCsv = array();
		$get_csv = $csv->getCSV();
		foreach ($get_csv as $key => $value){ //Проходим по строкам
			$arCsv[] = $value;
		}
		
		$col_id = $col_price = $col_price_min = $col_price2 = $col_url = false;
		$arColShop = array();
		foreach ($arCsv[0] as $key => $row){
			if(preg_match('/^ID/',$row)) $col_id = $key;
			if($row == "PriceMin") $col_price_min = $key;
			if($row == "Price") $col_price = $key;
			if($row == "Price2") $col_price2 = $key;
			if($row == "CardUrl") $col_url = $key;
			
			if(preg_match('/^Competitor/',$row)) $arColShop[] = $key;
		}
		//prent($arColShop);
		//prent($col_id);prent($col_price_min);prent($col_price);prent($col_price2);die;
		$arResult["ITEMS"] = array();
		foreach($arCsv as $key => $arItem){
			if($key == 0) continue;
			$id = intval($arItem[$col_id]);
			$price = (float) $arItem[$col_price];
			$price_min = (float) $arItem[$col_price_min];
			$price2 = (float) $arItem[$col_price2];
			
			if($price == $price_min) $price_new = $price2; else $price_new = $price_min;
			//Price = PriceMin то Price2 иначе PriceMin
			if(strlen($arArticle[$id]) > 0){
				$article = $arArticle[$id];
			}else{
				//prent($arItem);//die;
				continue;
			}
			
			$min_price = false;
			foreach($arColShop as $shop){
				if($arItem[$shop]){
					$name = iconv("windows-1251", "UTF-8", $arItem[$shop]);
					$arResult["SHOPS"][$name] = $name;
					
					//ищем минимальную цену с учетом магазинов которые надо исключить
					if(!in_array($name, $arResult["YMARKET_SHOP_HIDE"])){
						if(!$min_price){
							$min_price = $arItem[$shop + 1];
						}elseif($min_price && $arItem[$shop + 1] < $min_price){
							$min_price = $arItem[$shop + 1];
						}
					}
				}
			}
			
			if($min_price){
				$arResult["ITEMS"][] = array(
					"ARTICLE" => $article,
					"BITRIX_ID" => $id,
					"MIN_PRICE" => $min_price,
				);
			}else{
				//prent($arItem);
			}

			
		}
		//prent($arResult["ITEMS"]);die;
		if(count($arResult["SHOPS"]) > 0){
			//сразу очищаем от старых
			$DB->Query("TRUNCATE TABLE ci_yandex_shop", false, $err_mess.__LINE__);
			//пишем в базу
			foreach($arResult["SHOPS"] as $shop){
				$in = array(
					"NAME" => "'".addslashes($shop)."'",
				);
				$DB->Insert("ci_yandex_shop", $in, $err_mess.__LINE__);
			}
		}
		
		//prent($arResult["ITEMS"]);die;
		if(count($arResult["ITEMS"]) > 0){
			//сразу очищаем от старых
			$DB->Query("TRUNCATE TABLE ci_yandex_price", false, $err_mess.__LINE__);
			//пишем в базу
			foreach($arResult["ITEMS"] as $key => &$arItem){
				$in = array(
					"name" => "'".addslashes($arItem["ARTICLE"])."'",
					"bitrix_id" => "'".addslashes($arItem["BITRIX_ID"])."'",
					"minPrice" => "'".$arItem["MIN_PRICE"]."'",
				);
				$DB->Insert("ci_yandex_price", $in, $err_mess.__LINE__);
			}
			
			$strSql = "SELECT COUNT(*) as cnt FROM ci_yandex_price";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$result["status"] = "Y";
				CProSet::setOption("PARSE_CATALOG_YANDEX", $row["cnt"]);
			}
		}

	}
}
*/
//skachivaem fail iz b2b
//авторизуемся в б2б
$filename_gz = $_SERVER["DOCUMENT_ROOT"] . "/upload/catalog_prices.csv.gz";
$file_orig = $_SERVER["DOCUMENT_ROOT"] . "/upload/catalog_prices.csv";
$file_ex = $_SERVER["DOCUMENT_ROOT"] . "/upload/catalog_onliner_ex.csv";

chmod($filename_gz, 0777);
chmod($file_orig, 0777);
chmod($file_ex, 0777);

$ch = curl_init();
$url = "https://b2b.onliner.by/login";
$login = "tempus.by";
$pass = "P6263f75";
curl_setopt($ch, CURLOPT_URL, $url);
// откуда пришли на эту страницу
curl_setopt($ch, CURLOPT_REFERER, $url);
// cURL будет выводить подробные сообщения о всех производимых действиях
curl_setopt($ch, CURLOPT_VERBOSE, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "email=".$login."&password=".$pass);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (Windows; U; Windows NT 5.0; En; rv:1.8.0.2) Gecko/20070306 Firefox/1.0.0.4");
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//сохранять полученные COOKIE в файл
curl_setopt($ch, CURLOPT_COOKIEJAR, $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/cookie.txt');
$result=curl_exec($ch);
curl_close($ch);
	
//переходим на страницу с Файлом
//$url = "https://b2b.onliner.by/catalog_prices";
$url = "https://b2b.onliner.by/shop/competitors_prices";
$ch = curl_init();
$fp = fopen($filename_gz, "w");
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_FILE, $fp);
// откуда пришли на эту страницу
curl_setopt($ch, CURLOPT_REFERER, $url);
//запрещаем делать запрос с помощью POST и соответственно разрешаем с помощью GET
curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//отсылаем серверу COOKIE полученные от него при авторизации
curl_setopt($ch, CURLOPT_COOKIEFILE, $_SERVER["DOCUMENT_ROOT"] . '/upload/tmp/cookie.txt');
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (Windows; U; Windows NT 5.0; En; rv:1.8.0.2) Gecko/20070306 Firefox/1.0.0.4");

$result = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
fclose($fp);

$hash = hash_file('md5', $filename_gz);
//prent($result);die;
//распаковываем unpack
exec("gunzip -f -v {$filename_gz}", $ret);
//delaem malenkii file iz pervih 5 kolonok
$fp = fopen($file_orig, "r");//открываем оригинальный файл
$fpNew = fopen($file_ex, "w");//создаем файл
$i = 0;
//массив разделов которые нам нужны
$arSection = array("Наручные часы");
while(false != ($row = fgetcsv($fp, 1024, ";"))){
	$section = iconv("windows-1251", "utf-8", $row[0]);
	if(in_array($section, $arSection)){

		$min = $row[8];
		if($row[8] == $row[5]){
			$tmp = array_slice($row, 11);
			//очищаем от watchshop.by
			/*
			$cl = array();
			foreach($tmp as $key => $v){
				if(($key == 0 || ($key % 4) == 0) && strlen($v) > 0 && $v != "watchshop.by"){
					$cl[] = $tmp[$key + 1];
				}
			}
			if(count($cl) > 0){
				$min = op_strip($cl[0]);
				$min = (float)str_replace(array(",", " "), array(".", ""), $min);
				unset($cl[0]);
				foreach($cl as $key => $v){
					$t_min = op_strip($v);
					$t_min = (float)str_replace(array(",", " "), array(".", ""), $v);
					if($t_min > 0 && $t_min < $min) $min = $t_min;
				}
			}
*/
			
			//потом оставить только это. когда надо будет отменить очистку от watchshop.by
			$min = op_strip($tmp[1]);
			$min = (float)str_replace(array(",", " "), array(".", ""), $min);

			foreach($tmp as $key => $v){
				if($key > 4 && ($key % 4) == 1){
					$t_min = op_strip($v);
					$t_min = (float)str_replace(array(",", " "), array(".", ""), $v);
					if($t_min > 0 && $t_min < $min) $min = $t_min;
				}
			}
			if($min == 0) $min = $row[8];
			
		}
		
		$arCsv = array(
			iconv("windows-1251", "utf-8", $row[0]),
			iconv("windows-1251", "utf-8", $row[1]),
			iconv("windows-1251", "utf-8", $row[2]),
			iconv("windows-1251", "utf-8", $row[3]),
			iconv("windows-1251", "utf-8", $row[4]),
			iconv("windows-1251", "utf-8", $min),
			iconv("windows-1251", "utf-8", $row[5]),
		);
		if(strlen($arCsv[0]) > 0 && strlen($arCsv[1]) > 0 && strlen($arCsv[2]) > 0 && strlen($arCsv[3]) > 0 && strlen($arCsv[4]) > 0){
			fputcsv($fpNew, $arCsv, ';');
		}
	}
}
fclose($fp);
fclose($fpNew);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/csv.class.php');
$html = "";
$csv = new CSV($file_ex);
if($csv->error != "no_found"){
	$arCsv = array();
	$get_csv = $csv->getCSV();
			
	foreach ($get_csv as $key => $value){ //Проходим по строкам
		if(count($value) == 7){
			$arCsv[] = array(
				"SECTION" => $value[0],
				"VENDOR" => $value[1],
				"MODEL" => $value[3],
				"LINK_ONLINER" => $value[4],
				"ONLINER_ID" => $value[2],
				"MIN_PRICE" => $value[5],
				"SHOP_PRICE" => $value[6],
				//"MIN_PRICE_2" => $value[7],
			);
		}
	}
	//prent($arCsv);die;
	if(count($arCsv) > 0){
		$DB->Query("TRUNCATE TABLE ci_catalog_onliner", false, $err_mess.__LINE__);
		$cnt_update = $cnt_add = 0;
		foreach ($arCsv as $key => $arItem){
			//if($arItem["MIN_PRICE"] == $arItem["SHOP_PRICE"]) continue;
			$xml_id = intval($arItem["ONLINER_ID"]);
			$min_price = op_strip($arItem["MIN_PRICE"]);
			$min_price = (float)str_replace(array(",", " "), array(".", ""), $min_price);
			
			$shop_price = op_strip($arItem["SHOP_PRICE"]);
			$shop_price = (float)str_replace(array(",", " "), array(".", ""), $shop_price);
			
			$in = array(
				"section" => "'".addslashes($arItem["SECTION"])."'",
				"brand" => "'".addslashes($arItem["VENDOR"])."'",
				"model" => "'".addslashes($arItem["MODEL"])."'",
				"url" => "'".addslashes($arItem["LINK_ONLINER"])."'",
				"min_price" => $min_price,
				"shop_price" => $shop_price
			);
			if($xml_id > 0 && strlen($arItem["SECTION"]) > 0 && strlen($arItem["VENDOR"]) > 0 && strlen($arItem["MODEL"]) > 0 && strlen($arItem["LINK_ONLINER"]) > 0){
				$strSql = "SELECT id FROM ci_catalog_onliner WHERE id = '".$xml_id."'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					/********* пока ничего **********/
					$cnt_update++; 
					$DB->Update("ci_catalog_onliner", $in, "WHERE id='".$xml_id."'", $err_mess.__LINE__);
				}else{
					$in["id"] = $xml_id;//"'".$xml_id."'";
					$DB->Insert("ci_catalog_onliner", $in, $err_mess.__LINE__);
					$cnt_add++; 
				}
			}else{
				$html .= "<p style='color: red;'>Не корректные данные. " . serialize($arItem) . "</p>";
			}
		}
		$res["status"] = "ok";
//			$res["text"] = "Обновлено {$cnt_update}. Добавлено {$cnt_add}. Всех товаров - " . count($arCsv);
		$html .= "<p>Обновлено {$cnt_update}. Добавлено {$cnt_add}. Всех товаров - " . count($arCsv) . "</p>";
	}else{
		$res["status"] = "error";
//			$res["text"] = "Нету данных для загрузки";
		$html .= "<p style='color: red'>Нету данных для загрузки</p>";
	}
}else{
	$res["status"] = "error";
//		$res["text"] = "Файл сломан";
	$html .= "<p style='color: red'>Файл сломан</p>";
}
CLog::add2log(array("event" => "C", "text" => $html));
	
if($res["status"] == "ok"){
	CProSet::setOption("PARSE_CATALOG_ONLINER", $hash);
	$res["text"] = "Парсер закончен";
}else{
	$res["text"] = "Ошибка";
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>