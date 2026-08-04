<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;

//ищем процессы и убиваем если они есть
exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/onliner/updateItems.php",$output,$code); 
if(is_array($output) && count($output) > 0){
	foreach($output as $pid)
		exec("kill -9 {$pid}");
}
	
//system("/usr/bin/php81 -f /userscripts/update_onliner.php >>/userscripts/logs/update_onliner.txt >/dev/null 2>&1 &");
system("/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/onliner/updateItems.php >/dev/null 2>&1 &");
// 
$res = array(
	'status' => "ok",
	'text' => "Выгрузка запущена. Подождите окончания"
);
CProSet::setOption("UPDATE_ONLINER", "START");
echo json_encode($res, JSON_UNESCAPED_UNICODE);

die;


require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/classes/api_onliner.php");
$obj = new Onliner_API;
$arOnliner = array();
/* дополнительные параметры для api onliner */
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_INFO_ONLINER,
); 
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "PROPERTY_BRAND", "PROPERTY_MANUFACTURER", "PROPERTY_IMPORTER", "PROPERTY_SERVICE_CENTER", "PROPERTY_WARRANTY"));
while($arFields = $res->GetNext()){
	$arOnliner[$arFields["PROPERTY_BRAND_VALUE"]] = array(
		"MANUFACTURER" => $arFields["PROPERTY_MANUFACTURER_VALUE"],
		"IMPORTER" => $arFields["PROPERTY_IMPORTER_VALUE"],
		"SERVICE_CENTER" => $arFields["~PROPERTY_SERVICE_CENTER_VALUE"]["TEXT"],
		"WARRANTY" => $arFields["PROPERTY_WARRANTY_VALUE"],
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

/*********************************************/
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	">CATALOG_QUANTITY" => 0,
//	"!PROPERTY_MODEL_ONLINER" => false,
//	"!PROPERTY_BRAND_ONLINER" => false,
);
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME", "DETAIL_PAGE_URL", "CATALOG_GROUP_2", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND", "PROPERTY_MODEL_ONLINER", "PROPERTY_BRAND_ONLINER"));
while($arFields = $res->GetNext()){
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
		

//	$price = (float) $arFields["CATALOG_PRICE_2"];
//	$price = CCurrencyRates::ConvertCurrency($price, $arFields["CATALOG_CURRENCY_2"], "BYN");
	$arPrice = CIBlockPriceTools::GetItemPrices(CProSet::IB_CATALOG, $arResultPrices, $arFields, "N", array(), "", "s2");
	$price = $arPrice["BASE_BEL"]["DISCOUNT_VALUE"];
//	if($model == "2629BKSV") {
//		prent($arPrice);die;
//	}
	if(isset($arOnliner[mb_strtolower($brand)]))
		$info = $arOnliner[mb_strtolower($brand)];
	else
		$info = $arOnliner["nobrand"];
	$comment = $obj->default_comment;//"Магазин в центре Минска. Более 10 000 товаров в каталоге. 5 лет работаем для Вас!";
		
	if($price > 0){
		$positions[] = array(
			'category' => 'Наручные часы',
			'vendor' => $brand,//$arFields["PROPERTY_BRAND_ONLINER_VALUE"],
			'model' => $model,//$arFields["PROPERTY_MODEL_ONLINER_VALUE"],
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
			'isCredit' => 'да'
		);
	}

}
//prent($positions);die;
$params = json_encode_cyr($positions);

$result = $obj->edit_position_pack($params);

CLog::add2log(array("event" => "O", "text" => "Добавлено - " . count($positions) . " моделей."));
$result = json_decode($result, true);
if($result["id"] && count($positions) > 0)
	CProSet::setOption("UPDATE_ONLINER", $result["id"]);
	
$res = array(
	'status' => ($result["id"] ? "ok" : "error"),
	'text' => ($result["id"] ? "Выгрузка прошла успешно" : "Не удалось выгрузить")
);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
