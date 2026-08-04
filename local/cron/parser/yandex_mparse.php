#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

global $DB;
$api = new MParserAPI;
$objBrand = new CPanelBrand;

$companyID = 14955;
$reportID = false;
$arResult = [];
$logger = new TsLogger("/yaMarketParser/");
$logger->log("LOG", "START");

// собираем массив для отправки
$arIDs = [];
$strSql = "SELECT bitrix_id, source, article as model FROM ci_wb_top";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arIDs[] = $row['bitrix_id'];
}

//массив с брендами
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_BRANDS,
	"ACTIVE" => "Y",
);

$resEl = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
$arBitrixBrand = array();
while($obEl = $resEl->GetNextElement()){
	$arEl = $obEl->GetFields();
	$arBitrixBrand[$arEl["ID"]] = $arEl["NAME"];
}

//список брендов. чтобы по удалять из названия бренды и оставить только артикулы
foreach($objBrand->getList() as $brand)
	$arBrand[] = $brand["name"];
$arBrand[] = "Emporio";


$arItems = array();
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"ID" => $arIDs,
	"INCLUDE_SUBSECTIONS" => "Y",
	"!PROPERTY_CML2_ARTICLE" => false,
	"PROPERTY_AVAILABILITY_RU" => 512,
);

$resEl = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME", "IBLOCK_SECTION_ID", "CODE", "DETAIL_PAGE_URL", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND", "PROPERTY_MINIMUM_PRICE"));

while($obEl = $resEl->GetNextElement()){
	$arEl = $obEl->GetFields();

	if($arBitrixBrand[$arEl["PROPERTY_BRAND_VALUE"]]){
		$name = $arBitrixBrand[$arEl["PROPERTY_BRAND_VALUE"]] . " " . $arEl["PROPERTY_CML2_ARTICLE_VALUE"];
	}else{
		$name = $arEl["NAME"];
	}

	$arItems[] = array(
		"name" => $name,
		"cost" => $arEl["PROPERTY_MINIMUM_PRICE_VALUE"],
		"id" => $arEl["ID"],
		"custom" => array(
			"custom-field-1" => "http://tempusshop.ru" . $arEl["DETAIL_PAGE_URL"],
			"custom-field-2" => $arEl["PROPERTY_CML2_ARTICLE_VALUE"],
		),
	);

}

//$arItems = array_slice($arItems, 0, 10);

$logger->log("LOG", "Собрали товары " . count($arItems));
//prent($arItems);
// отправляем в маркетпарсер
CProSet::setOption("YANDEX_PARSE_ALL", "STEP_2");
$res = $api->setPriceCompany($companyID, ['products' => $arItems]);

file_put_contents("/home/bitrix/logs/yaMarketParser/tmp_setPriceCompany.txt", print_r([date("Y-m-d H:i:s"), $arItems, $res], true), 8);

if($res["response"]["success"]){
	$logger->log("LOG", "Отправили в мпарсер");
	//sleep(5);
	// создаем отчет и ждем пока сформируется
	CProSet::setOption("YANDEX_PARSE_ALL", "STEP_3");

	sleep(10);
	$tmp = $api->setReportCompany($companyID);
	file_put_contents("/home/bitrix/logs/yaMarketParser/tmp_setReportCompany.txt", print_r([date("Y-m-d H:i:s"), $companyID, $tmp], true), 8);
	if(isset($tmp["response"]["id"]) && $tmp["response"]["id"] > 0){
		$reportID = $tmp["response"]["id"];
	}
	/*
	while(true){
		sleep(10);
		$tmp = $api->setReportCompany($companyID);
		file_put_contents("/home/bitrix/logs/yaMarketParser/tmp_setReportCompany.txt", print_r([date("Y-m-d H:i:s"), $companyID, $tmp], true), 8);
		if(isset($tmp["response"]["id"]) && $tmp["response"]["id"] > 0){
			$reportID = $tmp["response"]["id"];
			break;
		}else{
			$logger->log("ERROR", "Ждем создание отчета. ошибка.", $tmp);
		}

		$i++;
		if($i > 30) break;
	}*/

	$activeReport = false;
	if($reportID > 0){
		$logger->log("LOG", "Ждем пока сформируется отчет {$reportID}");
		//$send = true;
		$i = 0;
		while(true){
			sleep(60);
			$tmp = $api->getReportListCompany($companyID);
			foreach($tmp["response"]["reports"] as $report){
				if($report["id"] != $reportID) continue;
				if($report["status"] == "OK" && $report["isSuccessfullyFinished"] == true){
					$activeReport = $report;
					//$send = false;
					break;
				}
			}

			$i++;
			if($i > 120) break;
				//$send = false;
		}


		if($activeReport){
			CProSet::setOption("YANDEX_PARSE_ALL", "STEP_4");
			file_put_contents("/home/bitrix/logs/last.yaparser.txt", print_r(["date" => date("Y-m-d H:i:s"), "activeReport" => $activeReport], true));

			$logger->log("LOG", "Получил ответ об отчете", $activeReport);
			$arResult["YMARKET_SHOP_HIDE"] = json_decode(CProSet::getOption("YMARKET_HIDE_SHOP"), true);
			$cnt = ceil($activeReport["countOkProducts"] / 100);

			$arIDs = [];
			for($i = 1; $i <= $cnt; $i++){
				$tmp = $api->getParseResult($companyID, $activeReport["id"], $i);
				file_put_contents("/home/bitrix/logs/last.yaparser.txt", print_r([$tmp], true), 8);

				foreach($tmp["response"]["products"] as $products){
					$arIDs[] = $products["ourId"];
					$name = trim(str_ireplace($ar, "", $products["name"]));
					//wdhs вырезаем бренд
					$name = end(explode(' ',$name));
					$arPrice = array();

					foreach($products["offers"] as $k => $arOffer){
						if($arOffer["price"] <= 0) continue;

						if(!in_array($arOffer["shopName"], $arResult["YMARKET_SHOP_HIDE"])){
							if(preg_match('/^Generalwatches/', $arOffer["shopName"])){
								$arInfo[$products["ourId"]] = "Generalwatches";
							}

							$arPrice[$arOffer["price"]] = $arOffer["price"];
						}
					}

					$arPrice = array_diff($arPrice, array(''));

					if(is_array($arPrice) && count($arPrice) > 0){

						asort($arPrice);
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

			$objRes = CIBlockElement::GetList(
				array(),
				array(
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

			file_put_contents("/home/bitrix/logs/yaparser.txt", print_r(["date" => date("Y-m-d H:i:s"), "arResult" => $arResult], true), 8);

			if(is_array($arResult["ITEMS"]) && count($arResult["ITEMS"]) > 0){
				$DB->Query("DELETE FROM ci_yandex_price WHERE type_price = 'PARSER'", false, $err_mess.__LINE__);
				//$DB->Query("DELETE FROM ci_yandex_price WHERE bitrix_id IN ('" . implode("','", $arIDs)."')", false, $err_mess.__LINE__);
				//пишем в базу
				foreach($arResult["ITEMS"] as $key => &$arItem){
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
				}

				$strSql = "SELECT COUNT(*) as cnt FROM ci_yandex_price";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if ($row = $results->Fetch()){
					CProSet::setOption("PARSE_CATALOG_YANDEX", $row["cnt"]);
					CProSet::setOption("YANDEX_PARSE_ALL", "STEP_6");
					//CProSet::setOption("YANDEX_PARSE_ALL", "Загружен отчет");
				}
			}else{
				CProSet::setOption("YANDEX_PARSE_ALL", "<p style='color:red;'>Нет данных в отчете</p>");
			}
		}else{
			$logger->log("ERROR", "Не дождались отчета");
			CProSet::setOption("YANDEX_PARSE_ALL", "<p style='color:red;'>Не дождались отчета</p>");
		}
		//prent($arResult["ITEMS"]);
	}else{
		$logger->log("ERROR", "Товары не отправили в mparser");
		CProSet::setOption("YANDEX_PARSE_ALL", "<p style='color:red;'>Товары не отправили в mparser</p>");
	}
}else{
	$logger->log("ERROR", "Ошибка при отправке в мпарсер", $res);
	CProSet::setOption("YANDEX_PARSE_ALL", "<p style='color:red;'>Ошибка при отправке в мпарсер</p>" . serialize($res));
}
$logger->log("LOG", "END");
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
