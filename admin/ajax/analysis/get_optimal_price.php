<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
//$start = debug_microtime_float();
$id = intval($_POST["id"]);
$price_id = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("ru", "by", "pl", "ya", "os", "wb","wbtl","wbby","av", "sb", "kz", "ozkz", "ozti")))
	$price_id = $_POST["website"];
?>
<?
if(CModule::IncludeModule("panel.manager") && $price_id && $id > 0){
	$price = new CPanelPricelist;
	$analysis = new CPanelAnalysis;
	$objCurrency = new CPanelCurrency;

	switch($price_id){
		case "ru":
			$round = -1;
			$currency = "RUB";
			break;
		case "by":
			$round = 2;
			$currency = "BYN";
			break;
		case "pl":
			$round = 0;
			$currency = "PLN";
			break;
		case "ya":
			$round = -1;
			$currency = "RUB";
			break;
			break;
		case "os":
			$round = -1;
			$currency = "RUB";
			break;
		case "wb":
			$round = -1;
			$currency = "RUB";
			break;
			break;
		case "wbtl":
			$round = -1;
			$currency = "RUB";
			break;
		case "wbby":
			$round = 2;
			$currency = "BYN";
			break;
		case "av":
			$round = -1;
			$currency = "RUB";
			break;
		case "sb":
			$round = -1;
			$currency = "RUB";
			break;
		case "kz":
			$round = -1;
			$currency = "KZT";
		case "ozkz":
			$round = -1;
			$currency = "RUB";
			break;
		case "ozti":
			$round = -1;
			$currency = "RUB";
			break;
		default:
			$round = -1;
			$currency = "RUB";
			break;
	}
	$arSettings = array(
		"round" => $round,
		"rate" => 1,
		"currency" => $currency
	);

	$arDefaultRRC = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$price_id];

	$arCurrency = $objCurrency->getDetail($arSettings["currency"]);
	if($arCurrency){
		$arSettings["rate"] = $arCurrency["rate"];
	}
	$productID = 0;
	$itemPrice = 0;
	$markup = 1;

	global $DB;
	$strSql = "SELECT * FROM individual_markups WHERE source = '{$price_id}'";
	$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
	$indivMarkups = [];
	while ( $row = $resultDB->Fetch() ){
		$indivMarkups[$row['bitrix_id']] = $row['markup'];
	}

	$tmpPricetype = $arDefaultRRC;
	if (!isset($tmpPricetype['price_type'])) {
		$priceType = 'price';
	} else {
		$priceType = $tmpPricetype['price_type'];
	}

	$ar = $price->getPriceByFilterNew(array("id" => $id), false, false, false ,$priceType);

	if(is_array($ar) && count($ar) == 1){
		$ar = $ar[0];
		if ($priceType == 'price_n') {
			$ar["price"] = $ar["price_n"];
		}
		$itemPrice = $ar["price"] / $arSettings["rate"];
		//$itemPrice = (float)round($itemPrice, $arSettings["round"]);
		$profile = $analysis->getListByFilter(array("brand_id" => $ar["brand_id"], "price_id" => $price_id));
		//echo json_encode($profile, JSON_UNESCAPED_UNICODE);
		if(is_array($profile) && count($profile) == 1){
			$profile = $profile[0];
			$profile["settings"] = json_decode( $profile["settings"], true );

			foreach($profile["settings"] as $key => $arItem){
				if($itemPrice >= $arItem["price_from"] && $itemPrice <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}

		}elseif(is_array($arDefaultRRC["rules"])){
			foreach($arDefaultRRC["rules"] as $key => $arItem){
				if($itemPrice >= $arItem["price_from"] && $itemPrice <= $arItem["price_to"] && $arItem["markup"] > 0)
					$markup = (float)$arItem["markup"];
			}
			//prent($markup);
		}

		if($ar["bitrix_id"]){
			$productID = $ar["bitrix_id"];
		}
	}else{
		//$itemPrice = 15;
	}
	//$itemPrice = $arSettings["rate"];
//	$itemPrice = $itemPrice / $arSettings["rate"];
	if ( $price_id == 'wb' && !empty($indivMarkups[$productID]) ){
		$markup = $indivMarkups[$productID];
	}


	$itemPrice = $itemPrice * $markup;// / $arSettings["rate"];//
	if($price_id == "WB"){

		$promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
		$sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");

		//Цена до скидки = закупочная цена  x величина из попапа "настройки РРЦ"  x (100/(100-скидка)) x (100/(100-промокод)

		$itemPrice = $itemPrice * (100 / (100 - $sale_per)) * (100 / (100 - $promo_per));
	}
	if($price_id == "WBTL"){

		$promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
		$sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");

		//Цена до скидки = закупочная цена  x величина из попапа "настройки РРЦ"  x (100/(100-скидка)) x (100/(100-промокод)

		$itemPrice = $itemPrice * (100 / (100 - $sale_per)) * (100 / (100 - $promo_per));
	}

	if(isset($arDefaultRRC["supersale"]) && $arDefaultRRC["supersale"] > 0 && $productID > 0){
		$arFilter = array("IBLOCK_ID" => CProSet::IB_CATALOG, "ID" => $productID, "SECTION_ID" => 370);
		$res = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID"));
		if($ob = $res->GetNextElement()){
			$itemPrice = $itemPrice - ($itemPrice * $arDefaultRRC["supersale"] / 100);
		}
	}

	$itemPrice2 = $itemPrice;

	$itemPrice = round($itemPrice, $arSettings["round"]);
	//$itemPrice = 15;
	$res = array(
		'status' => "ok",
		'price' => $itemPrice,
		'price2' => $itemPrice2,
		'ar' => $markup,
	);


}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось получить"
	);
}
//$end = debug_microtime_float();
//$txt = "Время выполнения - " . ($end - $start);
//AddMessage2Log($txt);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
