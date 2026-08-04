<?php 
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);

//if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
//	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");
CModule::IncludeModule('panel.manager');

$reportRes = [3235031];
//if(!$_REQUEST["yandex-report"])return;
$res["status"] = "error";
$res["text"] = "";
global $DB;
$objBrand = new CPanelBrand;
$logger = new TsLogger("/parseMarketparser/");


//список брендов. чтобы по удалять из названия бренды и оставить только артикулы
$arBrand = $objBrand->getList();
foreach($arBrand as $brand)
	$ar[] = $brand["name"];
$ar[] = "Emporio";

$api = new MParserAPI;
$arCompany = $api->getCompanyList();

$arActiveReports = $arResult = array();
foreach($arCompany["response"]["campaigns"] as $key => $company){
	$tmp = $api->getReportListCompany($company["id"]);
	foreach($tmp["response"]["reports"] as $report){
		if(in_array($report["id"], $reportRes) && $report["status"] == "OK" && $report["isSuccessfullyFinished"] == true)
			$arActiveReports[$company["id"]] = $report;
	}
}
//$report["id"] == 3187735 && 
file_put_contents("/home/bitrix/logs/last.yaparser.txt", print_r(["date" => date("Y-m-d H:i:s"), "arActiveReports" => $arActiveReports], true));
//получаем отчеты по парсингу 

$arIDs = [];

$currentDate = date('Y-m-d H:i:s');
$items = [];
$newPrices = [];
$updatePrices = [];
$currentPrices = [];

$arArticle = [];
// временно
$objRes = CIBlockElement::GetList([], array(
		'IBLOCK_ID' => CProSet::IB_CATALOG, 
	), 
	false, 
	false, 
	array('ID', 'PROPERTY_CML2_ARTICLE')
);
while ($rs = $objRes->GetNext()){
	if($rs["PROPERTY_CML2_ARTICLE_VALUE"]) $arArticle[$rs["PROPERTY_CML2_ARTICLE_VALUE"]] = $rs["ID"];
}
	
if(is_array($arActiveReports) && count($arActiveReports) > 0){
	
	$arResult["YMARKET_SHOP_HIDE"] = json_decode(CProSet::getOption("YMARKET_HIDE_SHOP"), true);
	file_put_contents("/home/bitrix/logs/last.yaparser.txt", print_r(["date" => date("Y-m-d H:i:s"), "arActiveReports" => $arActiveReports], true));
	
	foreach($arActiveReports as $c_id => $report){
		$cnt = ceil($report["countOkProducts"] / 100);
		for($i = 1; $i <= $cnt; $i++){
			//if ($i > 2) continue;
			$tmp = $api->getParseResult($c_id, $report["id"], $i);
			file_put_contents("/home/bitrix/logs/last.yaparser.txt", print_r([$tmp], true), 8);
			
			foreach($tmp["response"]["products"] as $products){
				//if (!$products["ourId"]) continue;
				
				$productId = intval($products["ourId"]);
				$productId = $arArticle[$products["name"]] ?? false;
				if (!$productId) continue;
				
				$name = trim(str_ireplace($ar, "", $products["name"]));

				foreach($products["offers"] as $k => $arOffer){
					if($arOffer["price"] <= 0) continue;
					
					if(!in_array($arOffer["shopName"], $arResult["YMARKET_SHOP_HIDE"])){
						$price = (float) $arOffer["price"];
						$shopName = trim($arOffer["shopName"]);
						$linkToOffer = trim($arOffer["linkToOffer"]);
						
						$key = $productId . '_' . $shopName;
						$currentPrices[$key] = true;
						
						$items[$key] = [
							"PRODUCT_ID" => $productId,
							"PRICE" => $price,
							"COMPETITOR_NAME" => $shopName,
							"PRODUCT_URL" => $linkToOffer,
							"ARTICLE" => $products["name"],
						];

					}
				}

			}

		}
	}
}

//$logger->log("LOG", "Всего обработано записей: " . count($items));
file_put_contents("/home/bitrix/logs/last.yaparser_items.txt", print_r($items, true));

if (count($items) > 0) {
	$processedItems = 0;
	
	$allProductIds = array_column($items, 'PRODUCT_ID');
	$allProductIds = array_unique($allProductIds);
	
	$existingPrices = [];

	$strSql = "SELECT ID, PRODUCT_ID, COMPETITOR_NAME, PRICE, PRODUCT_URL 
			   FROM ci_price_competitor 
			   WHERE PRODUCT_ID IN (".implode(',', $allProductIds).") 
			   AND PRICE_TYPE = 'os'";
	$result = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	while($row = $result->Fetch()) {
		$key = $row['PRODUCT_ID'] . '_' . $row['COMPETITOR_NAME'];
		$existingPrices[$key] = array(
			'ID' => $row['ID'],
			'PRICE' => $row['PRICE'],
			'PRODUCT_URL' => $row['PRODUCT_URL'],
		);
	}
	
	foreach($items as $key => $arItem) {
		$priceValue = $arItem['PRICE'];
		
		if(isset($existingPrices[$key])) {
			$existingPrice = $existingPrices[$key]['PRICE'];
			$existingProductUrl = $existingPrices[$key]['PRODUCT_URL'];
			if(abs($existingPrice - $priceValue) > 0.01 || $existingProductUrl != $arItem['PRODUCT_URL']) {
				$updatePrices[] = array(
					'ID' => $existingPrices[$key]['ID'],
					'PRICE' => $priceValue,
					'PREVIOUS_PRICE' => $existingPrice,
					'PRODUCT_URL' => $arItem['PRODUCT_URL'], 
				);
			}
		} else {
			$newPrices[] = array(
				'PRODUCT_ID' => $arItem['PRODUCT_ID'],
				'ARTICLE' => $arItem['ARTICLE'],
				'COMPETITOR_NAME' => $arItem['COMPETITOR_NAME'],
				'PRODUCT_URL' => $arItem['PRODUCT_URL'],
				'PRICE' => $priceValue
			);
		}
			
		$currentPrices[$key] = true;
	}

	if(!empty($updatePrices)) {
		file_put_contents("/home/bitrix/logs/last.yaparser_updatePrices.txt", print_r($updatePrices, true));
		foreach($updatePrices as $update) {
			$strSql = "UPDATE ci_price_competitor SET 
					  PRICE = ".$update['PRICE'].",
					  PREVIOUS_PRICE = ".$update['PREVIOUS_PRICE'].",
					  PRODUCT_URL = '".$DB->ForSql($update['PRODUCT_URL'])."',
					  DATE_UPDATE = '".$currentDate."'
					  WHERE ID = ".$update['ID'];
			
			file_put_contents("/home/bitrix/logs/last.yaparser_updatePrices2.txt", print_r($strSql, true), 8);
			$DB->Query($strSql, false, $err_mess.__LINE__);
			file_put_contents("/home/bitrix/logs/last.yaparser_updatePrices22.txt", print_r($err_mess, true), 8);
		}
		
		$processedItems += count($updatePrices);
		$logger->log("LOG", "Обновлено записей: ".count($updatePrices));
	}

	if(!empty($newPrices)) {
		file_put_contents("/home/bitrix/logs/last.yaparser_newPrices.txt", print_r($newPrices, true));
		$values = array();
		foreach($newPrices as $newPrice) {
			$values[] = "(
				".$newPrice['PRODUCT_ID'].",
				'".$DB->ForSql($newPrice['ARTICLE'])."',
				'os',
				'".$DB->ForSql($newPrice['COMPETITOR_NAME'])."',
				".$newPrice['PRICE'].",
				'".$DB->ForSql($newPrice['PRODUCT_URL'])."',
				NULL,
				'".$currentDate."',
				'".$currentDate."'
			)";
		}
		
		$strSql = "INSERT INTO ci_price_competitor 
				  (PRODUCT_ID, ARTICLE, PRICE_TYPE, COMPETITOR_NAME, PRICE, PRODUCT_URL, PREVIOUS_PRICE, DATE_CREATE, DATE_UPDATE)
				  VALUES ".implode(',', $values);
		$DB->Query($strSql, false, $err_mess.__LINE__);
		
		$processedItems += count($newPrices);
		$logger->log("LOG", "Добавлено новых записей: ".count($newPrices));
	}

	if(!empty($allProductIds)) {
		$notInConditions = array();
		foreach($currentPrices as $key => $value) {
			list($productId, $competitorName) = explode('_', $key, 2);
			$notInConditions[] = "(PRODUCT_ID = ".$productId." AND COMPETITOR_NAME = '".$DB->ForSql($competitorName)."')";
		}
		
		if(!empty($notInConditions)) {
			$strSql = "DELETE FROM ci_price_competitor 
					  WHERE PRODUCT_ID IN (".implode(',', $allProductIds).")
					  AND PRICE_TYPE = 'os'";
			
			if(!empty($notInConditions)) {
				$strSql .= " AND NOT (".implode(' OR ', $notInConditions).")";
			}
			
			$deleteResult = $DB->Query($strSql, false, $err_mess.__LINE__);
		} else {
			$strSql = "DELETE FROM ci_price_competitor 
					  WHERE PRODUCT_ID IN (".implode(',', $allProductIds).")
					  AND PRICE_TYPE = 'os'";
			$deleteResult = $DB->Query($strSql, false, $err_mess.__LINE__);
		}
	}
	

	$logger->log("LOG", "Всего обработано записей: ".$processedItems);

	$strSql = "SELECT COUNT(DISTINCT PRODUCT_ID) as product_count, 
					  COUNT(*) as price_count 
			   FROM ci_price_competitor 
			   WHERE PRICE_TYPE = 'os'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	if($row = $results->Fetch()) {
		//CProSet::setOption("PARSE_CATALOG_ONLINER", $row["price_count"]);

		$html = "<p>Обновлено/добавлено: ".$processedItems." цен</p>";
		$html .= "<p>Товаров в базе: ".$row['product_count']."</p>";
		$html .= "<p>Всего цен в базе: ".$row['price_count']."</p>";
		$html .= "<p>Всех товаров в ответе: " . count($items) . "</p>";
		
		$logger->log("LOG", "Сводка: ", $html);
	}
					
}
				
/*
$arIDs = [];
if(is_array($arActiveReports) && count($arActiveReports) > 0){
	
	$arResult["YMARKET_SHOP_HIDE"] = json_decode(CProSet::getOption("YMARKET_HIDE_SHOP"), true);
	file_put_contents("/home/bitrix/logs/last.yaparser.txt", print_r(["date" => date("Y-m-d H:i:s"), "arActiveReports" => $arActiveReports], true));
	
	foreach($arActiveReports as $c_id => $report){
		$cnt = ceil($report["countOkProducts"] / 100);
		for($i = 1; $i <= $cnt; $i++){
			$tmp = $api->getParseResult($c_id, $report["id"], $i);
			file_put_contents("/home/bitrix/logs/last.yaparser.txt", print_r([$tmp], true), 8);
			foreach($tmp["response"]["products"] as $products){
				$arIDs[] = $products["ourId"];
				$name = trim(str_ireplace($ar, "", $products["name"]));

				$arPrice = array();
				
				foreach($products["offers"] as $k => $arOffer){
					if($arOffer["price"] <= 0) continue;
					
					if(!in_array($arOffer["shopName"], $arResult["YMARKET_SHOP_HIDE"])){
						//if(preg_match('/^НАРУЧКА - Часы и Аксессуары/', $arOffer["shopName"])){
						//	$arPrice[$arOffer["price"]] = $arOffer["price"] - ($arOffer["price"] * 5 / 100);
						//}else{
						//	$arPrice[$arOffer["price"]] = $arOffer["price"];
						//}
						
						if(preg_match('/^Generalwatches/', $arOffer["shopName"])){
							$arInfo[$products["ourId"]] = "Generalwatches";
						}
						
						$arPrice[$arOffer["price"]] = $arOffer["price"];
					}
				}
				
				$arPrice = array_diff($arPrice, array(''));
				

				//if($name == "GA-100-1A1"){
				//	AddMessage2Log($products);AddMessage2Log($arPrice); 
				//}
				if(is_array($arPrice) && count($arPrice) > 0){
					
					asort($arPrice);
					//array_shift($arPrice);
					$arPrice = array_values($arPrice);

					$arResult["ITEMS"][$products["ourId"]] = array(
						"name" => $name,
						"minPrice" => ($arPrice[0] ? $arPrice[0] : ""),
						"minPrice2" => ($arPrice[1] ? $arPrice[1] : ""),
						"minPrice3" => ($arPrice[2] ? $arPrice[2] : ""),
						"yandex_id" => $products["yandexModelId"],
						"bitrix_id" => $products["ourId"],
						"info" => $arInfo[$products["ourId"]],
					);
				}
				

			}

		}
	}
}
//AddMessage2Log($arResult["ITEMS"]);die;
//file_put_contents("/home/bitrix/logs/yaparser.txt", print_r([$arResult], true), 8);
//AddMessage2Log($arYandexID);
//die;

file_put_contents("/home/bitrix/logs/yaparser.txt", print_r(["date" => date("Y-m-d H:i:s"), "arResult" => $arResult, "arIDs" => $arIDs], true), 8);

if(count($arIDs) > 0){
	$objRes = CIBlockElement::GetList(array(), array(
			'IBLOCK_ID' => CProSet::IB_CATALOG, 
			'ID' => $arIDs,
		), 
		false, 
		false, 
		array('ID', 'PROPERTY_CML2_ARTICLE')
	);
	while ($rs = $objRes->GetNext()){
		if($arResult["ITEMS"][$rs["ID"]]) $arResult["ITEMS"][$rs["ID"]]["name"] = $rs["PROPERTY_CML2_ARTICLE_VALUE"];
	}
	//очищаем только те которые есть в парсере
	$DB->Query("DELETE FROM ci_yandex_price WHERE bitrix_id IN ('" . implode("','", $arIDs)."')", false, $err_mess.__LINE__);
}

if(is_array($arResult["ITEMS"]) && count($arResult["ITEMS"]) > 0){
	//сразу очищаем от старых
	//$DB->Query("TRUNCATE TABLE ci_yandex_price", false, $err_mess.__LINE__);

	//$DB->Query("DELETE FROM ci_yandex_price WHERE type_price = 'PARSER'", false, $err_mess.__LINE__);
	//пишем в базу
	foreach($arResult["ITEMS"] as $key => &$arItem){
//		$arItem["name"] = trim(str_ireplace("Armani", "", $arItem["name"]));
		$in = array(
			"name" => "'".addslashes($arItem["name"])."'",
			"bitrix_id" => "'".addslashes($arItem["bitrix_id"])."'",
			"yandex_id" => "'".addslashes($arItem["yandex_id"])."'",
			"minPrice" => "'".$arItem["minPrice"]."'",
			"minPrice2" => "'".$arItem["minPrice2"]."'",
			"minPrice3" => "'".$arItem["minPrice3"]."'",
			"type_price" => "'".addslashes("PARSER")."'",
			"info" => "'".$arItem["info"]."'",
		);
		$DB->Insert("ci_yandex_price", $in, $err_mess.__LINE__);
//		if($arItem["yandex_id"] > 0 && strlen($arItem["name"]) > 0){
//			$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'PROPERTY_CML2_ARTICLE' => $arItem["name"], "!PROPERTY_YANDEX_MODEL_ID" => $arItem["yandex_id"]), false, false, array('ID'));
//			if ($rs = $objRes->GetNext()){
//				CIBlockElement::SetPropertyValuesEx($rs["ID"], false, array("YANDEX_MODEL_ID" => $arItem["yandex_id"]));
//			}
//			$PRODUCT_ID = CPanelProduct::findArticle($arItem["name"]);
//			if($PRODUCT_ID > 0){
//				CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, false, array("YANDEX_MODEL_ID" => $arItem["yandex_id"]));
//			}
//		}

	}
	
	$strSql = "SELECT COUNT(*) as cnt FROM ci_yandex_price";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$res["status"] == "ok";
		CProSet::setOption("PARSE_CATALOG_YANDEX", $row["cnt"]);
		CProSet::setOption("YANDEX_PARSE_ALL", "STEP_6");
	}
}*/
$res["status"] = "ok";
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();

?>