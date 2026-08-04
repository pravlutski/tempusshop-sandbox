<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
//$start = debug_microtime_float();
$id = intval($_POST["id"]);
$price_id = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("ru", "by", "pl", "ya", "os", "wb", "sb")))
	$price_id = $_POST["website"];
?>
<?
if(CModule::IncludeModule("panel.manager") && $price_id && count($_POST["id"]) > 0){
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
		case "sb":
			$round = -1;
			$currency = "RUB";
			break;
		case "wb":
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
	$arCurrency = $objCurrency->getDetail($arSettings["currency"]);
	if($arCurrency){
		$arSettings["rate"] = $arCurrency["rate"];
	}
	
	$profile = $analysis->getListByFilter(array("price_id" => $price_id));
	
	foreach($profile as $key => $arItem){
		$arResult["PROFILE"][$arItem["brand_id"]] = $arItem;
	}
	//
	$ar = $price->getPriceByFilter(array("id" => $_POST["id"]));
	
	$promo_per = CProSet::getOption("CATALOG_PROMO_wb");
	$sale_per = CProSet::getOption("CATALOG_SALE_wb");
				
	$arResult["ITEMS"] = array();
	if(count($ar) > 0){

		foreach($ar as $key => $arItem){
			
			$itemPrice = 0; 
			$markup = 1;
			
			$itemPrice = $arItem["price"] / $arSettings["rate"];
			//$itemPrice = (float)round($itemPrice, $arSettings["round"]);

			if(isset($arResult["PROFILE"][$arItem["brand_id"]])){

				$profile = json_decode( $arResult["PROFILE"][$arItem["brand_id"]]["settings"], true );
				
				foreach($profile as $k => $arProfile){
					if($itemPrice >= $arProfile["price_from"] && $itemPrice <= $arProfile["price_to"] && $arProfile["markup"] > 0)
						$markup = (float)$arProfile["markup"];
				}
			
			}
			$itemPrice = $itemPrice * $markup;
			
			if($price_id == "wb"){

				//Цена до скидки = закупочная цена  x величина из попапа "настройки РРЦ"  x (100/(100-скидка)) x (100/(100-промокод) 
				
				$itemPrice = $itemPrice * (100 / (100 - $sale_per)) * (100 / (100 - $promo_per));
			}
	
			$itemPrice = round($itemPrice, $arSettings["round"]);

		//if($arItem["id"] == 8772885){
		//	prent($itemPrice,1,1);
		//}
			if($_POST["price"][$arItem["id"]] != $itemPrice){
				$arResult["ITEMS"][$arItem["id"]]["price"] = $itemPrice;
				$arResult["ITEMS"][$arItem["id"]]["old_price"] = $_POST["price"][$arItem["id"]];
			}

		}

	}
	//$itemPrice = $arSettings["rate"];
//	$itemPrice = $itemPrice / $arSettings["rate"];
	//prent();
	//prent($arResult["ITEMS"]);die;
	$res = array(
		'status' => (count($arResult["ITEMS"]) > 0 ? "ok" : "error"),
		'items' => $arResult["ITEMS"]
	);
	

}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить"
	);
}
//$end = debug_microtime_float();
//$txt = "Время выполнения - " . ($end - $start);
//AddMessage2Log($txt);

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();