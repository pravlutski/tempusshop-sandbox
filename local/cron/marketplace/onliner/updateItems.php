#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CProSet::setOption("ONLINER_LAST_EXCH", "");
global $DB;

//onlinerUpdateItems
$triggers = new TsTriggers();
$logger = new TsLogger("/onliner/updateItems/");
$workers = new WorkersChecker("onlinerUpdateItems");

if (!$workers->checkStatus()) {
	$logger->log("LOG", "Обработчик занят");
	exit();
}
$logger->log("LOG", "Запуск обработчика");

$workers->updateStatus("Y");

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/classes/api_onliner.php");
$obj = new Onliner_API;
$arOnliner = array();
/* дополнительные параметры для api onliner */
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_INFO_ONLINER,
);
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "PROPERTY_BRAND", "PROPERTY_MANUFACTURER", "PROPERTY_IMPORTER", "PROPERTY_SERVICE_CENTER", "PROPERTY_WARRANTY", "PROPERTY_INCREASED_INSTALLMENT"));
while($arFields = $res->GetNext()){
	$arOnliner[$arFields["PROPERTY_BRAND_VALUE"]] = array(
		"MANUFACTURER" => $arFields["PROPERTY_MANUFACTURER_VALUE"],
		"IMPORTER" => $arFields["PROPERTY_IMPORTER_VALUE"],
		"SERVICE_CENTER" => $arFields["~PROPERTY_SERVICE_CENTER_VALUE"]["TEXT"],
		"WARRANTY" => $arFields["PROPERTY_WARRANTY_VALUE"],
		"INSTALLMENT" => $arFields["PROPERTY_INCREASED_INSTALLMENT_VALUE"]
	);
}

$arBrand = array();
/* массив брендов */
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_BRANDS,
);
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
while($arFields = $res->GetNext()){
	$arBrand[$arFields["ID"]] = $arFields["NAME"];
}

$arResultPrices = CIBlockPriceTools::GetCatalogPrices(CProSet::IB_CATALOG, array("BASE_BEL"));

// позиции в карантине
$arQuarantine = [];
$strSql = "SELECT PRODUCT_ID FROM ci_price_quarantine WHERE PRICE_ID = 'BY'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arQuarantine[$row["PRODUCT_ID"]] = true;
}

/*********************************************/
$arFilter = Array(
	"ACTIVE" => "Y",
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	">CATALOG_QUANTITY" => 0,
//	"!PROPERTY_MODEL_ONLINER" => false,
//	"!PROPERTY_BRAND_ONLINER" => false,
	"PROPERTY_AVAILABILITY_BY" => array(492, 493),
	// "!PROPERTY_BRAND" => 43508
);
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME", "DETAIL_PAGE_URL", "CATALOG_GROUP_2", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND", "PROPERTY_TYPE", "PROPERTY_MODEL_ONLINER", "PROPERTY_BRAND_ONLINER", "PROPERTY_ARTICLE_ONLINER", "PROPERTY_AVAILABILITY_BY"));
while($arFields = $res->GetNext()){
	if(isset($arQuarantine[$arFields["ID"]])) continue;
	//prent($arFields);die;
	$model = $brand = false;
	if(strlen($arFields["PROPERTY_MODEL_ONLINER_VALUE"]) > 0)
		$model = $arFields["PROPERTY_MODEL_ONLINER_VALUE"];
	elseif(strlen($arFields["PROPERTY_CML2_ARTICLE_VALUE"]) > 0)
		$model = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
	else
		$model = "NOARTICLE";
	if(strlen($arFields["PROPERTY_BRAND_ONLINER_VALUE"]) > 0)
		$brand = $arFields["PROPERTY_BRAND_ONLINER_VALUE"];
	elseif(strlen($arFields["PROPERTY_BRAND_VALUE"]) > 0){
		if(isset($arBrand[$arFields["PROPERTY_BRAND_VALUE"]]))
			$brand = $arBrand[$arFields["PROPERTY_BRAND_VALUE"]];
	}else
		$brand = "NOBRAND";

	if($brand == "Daniel Klein") $model = "DK" . $model;

	switch($arFields["PROPERTY_AVAILABILITY_BY_ENUM_ID"]){
		case 492:
			$stockStatus = "in_stock";
			break;
		case 493:
			$stockStatus = "";
			break;
		case 494:
			$stockStatus = "";
			break;
		default:
			$stockStatus = "";
			break;
	}
//	$price = (float) $arFields["CATALOG_PRICE_2"];
//	$price = CCurrencyRates::ConvertCurrency($price, $arFields["CATALOG_CURRENCY_2"], "BYN");
	$arPrice = CIBlockPriceTools::GetItemPrices(CProSet::IB_CATALOG, $arResultPrices, $arFields, "N", array(), "", "s2");
	$price = $arPrice["BASE_BEL"]["DISCOUNT_VALUE"];

	if(isset($arOnliner[mb_strtolower($brand)]))
		$info = $arOnliner[mb_strtolower($brand)];
	else
		$info = $arOnliner["nobrand"];
	$comment = $obj->default_comment;//"Магазин в центре Минска. Более 10 000 товаров в каталоге. 5 лет работаем для Вас!";

	if($price > 0){

		if($arFields["PROPERTY_TYPE_VALUE"][40] || $arFields["PROPERTY_TYPE_VALUE"][41]){
		//	$price = $price * 0.9;
		}
		$aRpromo = array(29451,4760,84720,5680,135625,12226,171749,174183,80924,4773,172794,170424,178618,87632,87631,76972,4296,171751,74145,156651,134048,146680,1008,5349,172874,72769,3077,176887,174871,119408,118986,173632,175590,162384,166457,122234,6483,77038,177773,177757,39625,179018,176244,164644,80443,41729,74730,176164,164721,162323,172197,14516,29389,36923,119495,35050,1224,129454,121810,81195,44124,12520,81079,1199,37393,3888,1789,161163,177601,178470,156112,178481,4958,128281,156621,178724,173190,178570,137846,132594,84704,176043,164900,88987,88988,171350,74546,74543,74538,74537,85700,118363,13631,157123,157133,157147,5046,178705,178648,175128,5388,5392,4117,5397,5094,4769,4768,4704,14233,2264,166026,166027,85699,85712,7855,5173,179744,4558,7822,137318,138453,1901,1002,82475,173257,156655,173536,161968,87266,178571,170425,170426,170427,171361,165988,82489,138451,29449,124664,76974,1061,1018,4109,7771,156624,159964,138445,173190,73752,72706,171849,138447,134050,87272,129759,29541,20479,159966,146681,172849,1025,5353,171341,171343,172873,172875,118351,118339,85769,87836,121728,87890,166007,166013,73614,12164,13264,29538,123623);

		if($arFields["PROPERTY_ARTICLE_ONLINER_VALUE"] && strlen($arFields["PROPERTY_ARTICLE_ONLINER_VALUE"]) > 2){
			$article = $arFields["PROPERTY_ARTICLE_ONLINER_VALUE"];
		}else{
			$article = $arFields["PROPERTY_CML2_ARTICLE_VALUE"];
		}
		if (in_array($arFields["ID"], $aRpromo)) {
			// $promoPrice = $price - ($price * 0.1);
			$positions[] = array(
				'id' => $arFields["ID"],
				'category' => 'Наручные часы',
				'vendor' => $brand,//$arFields["PROPERTY_BRAND_ONLINER_VALUE"],
				'model' => $model,//$arFields["PROPERTY_MODEL_ONLINER_VALUE"],
				'article' => $article,
				'price' => $price,
				'currency' => 'BYN',
				'comment' => $comment,
				'producer' => $info["MANUFACTURER"],
				'importer' => $info["IMPORTER"],
				'serviceCenters' => $info["SERVICE_CENTER"],
				'warranty' => $info["WARRANTY"],
				'productLifeTime'   => 240,
				'deliveryTownTime' => 1,
				'deliveryTownPrice' => ($price >= 50 ? 0 : 5),
				'deliveryCountryTime' => 2,
				'deliveryCountryPrice'=> ($price >= 50 ? 0 : 5),
				'isCashless' => 'нет',
				'isCredit' => 'да',
				'stockStatus' => $stockStatus,
				'termHalva' => 4,
				// 'pricePromo' => $promoPrice,
				'increasedMinipayInstallment' => $arOnliner[ mb_strtolower($brand) ]["INSTALLMENT"]
			);
		} else {
			$positions[] = array(
				'id' => $arFields["ID"],
				'category' => 'Наручные часы',
				'vendor' => $brand,//$arFields["PROPERTY_BRAND_ONLINER_VALUE"],
				'model' => $model,//$arFields["PROPERTY_MODEL_ONLINER_VALUE"],
				'article' => $article,
				'price' => $price,
				'currency' => 'BYN',
				'comment' => $comment,
				'producer' => $info["MANUFACTURER"],
				'importer' => $info["IMPORTER"],
				'serviceCenters' => $info["SERVICE_CENTER"],
				'warranty' => $info["WARRANTY"],
				'productLifeTime'   => 240,
				'deliveryTownTime' => 1,
				'deliveryTownPrice' => ($price >= 50 ? 0 : 5),
				'deliveryCountryTime' => 2,
				'deliveryCountryPrice'=> ($price >= 50 ? 0 : 5),
				'isCashless' => 'нет',
				'isCredit' => 'да',
				'stockStatus' => $stockStatus,
				'termHalva' => 4,
				'increasedMinipayInstallment' => $arOnliner[ mb_strtolower($brand) ]["INSTALLMENT"]
			);
		}
	}
//echo serialize($positions);die;
}
//prent($arOnliner,0,1);
file_put_contents("/home/bitrix/logs/onliner/last_update.txt", print_r($positions, true));

//prent($positions,0,1);die;
$logger->log("LOG", "Добавлено - " . count($positions));
//$logger->log("LOG", "Позиции - " . print_r($positions, true));
// print_r($positions);
// die();
$params = json_encode_cyr($positions);
$result = $obj->edit_position_pack($params);
CLog::add2log(array("event" => "O", "text" => "Добавлено - " . count($positions) . " моделей."));

$result = json_decode($result, true);
if($result["id"] && count($positions) > 0){
	CProSet::setOption("UPDATE_ONLINER", $result["id"]);
}else{
	$logger->log("LOG", "Ошибка при обновлении позиций на онлайнере. " . print_r($result, true));
	$triggers->SetError(["Ошибка при обновлении позиций на онлайнере. \r\n"]);
	$triggers->SendTriggerErrors();
}

$workers->updateStatus("N");

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
