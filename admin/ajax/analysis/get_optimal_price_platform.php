<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$id = intval($_POST["id"]);
$percent = (float) $_POST["percent"];
$percent = $percent / 100;
$price_id = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("ya", "by", 'ru', 'wb')))
	$price_id = $_POST["website"];
?>
<?
if(CModule::IncludeModule("main") && CModule::IncludeModule("catalog") && 
CModule::IncludeModule("iblock") && CModule::IncludeModule("panel.manager") && $price_id && $id > 0){
	
	$objCurrency = new CPanelCurrency;
	$arSettings = array(
		"round" => (in_array($price_id, ['ya', 'ru', 'wb']) ? -1 : 0),
		"rate" => 1,
		"currency" => (in_array($price_id, ['ya', 'ru', 'wb']) ? "RUB" : "BYN")
	);
	$arCurrency = $objCurrency->getDetail($arSettings["currency"]);
	if($arCurrency){
		$arSettings["rate"] = $arCurrency["rate"];
	}
	
	$promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
	$sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");
			
	$objPricelist = new CPanelPricelist;
	$arPricePlatform = array();
	$discount_per = $new_price = 0;

	$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $id, "!PROPERTY_CML2_ARTICLE" => false), false, false, array("ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_ARTICLE_ONLINER"));
	if ($ob = $objRes->GetNext()){
		$price_supp = 0;
		if($_POST["id_price"] > 0){
			$ar = $objPricelist->getPriceByFilter(array("id" => $_POST["id_price"]));
			$price_supp = $ar[0]["price"] / $arSettings["rate"];//закупочная цена поставщика
		}
		
		if ($price_id == "ya") {
			$tmp = $objPricelist->getYandexPriceByFilter(array("name" => $ob["PROPERTY_CML2_ARTICLE_VALUE"]));
			if(is_array($tmp) && count($tmp) == 1){
				$minPricePlatform = $tmp[0]["minPrice"];
			}
			$ar = AHCatalog::OnGetOptimalPrice($ob["ID"], 1, array(), "N", array(), $price_id);
			if(isset($ar["DISCOUNT"]["VALUE_TYPE"]) && $ar["DISCOUNT"]["VALUE_TYPE"] == "P"){
				$discount_per = $ar["DISCOUNT"]["VALUE"] / 100;
			}
			if($minPricePlatform > 0){
				//$new_price = $minPricePlatform * 0.95 / ($discount_per - 1);
				$new_price = $minPricePlatform * (1 - $percent) / (1 - $discount_per);
				$new_price = abs($new_price);
				$new_price = round($new_price, $arSettings["round"]);
				
				//цена без скидки
				$price_no_dis = $new_price - $new_price * $discount_per;
				$price_no_dis = round($price_no_dis, $arSettings["round"]);
			}
			//$new_price = $minPricePlatform * (1 - $percent);
		} elseif($price_id == "by") {
			//if(["PROPERTY_ARTICLE_ONLINER_VALUE"])
			$tmp = $objPricelist->getOnlinerPriceByFilter(array("model" => ($ob["PROPERTY_ARTICLE_ONLINER_VALUE"] ? $ob["PROPERTY_ARTICLE_ONLINER_VALUE"] : $ob["PROPERTY_CML2_ARTICLE_VALUE"])));//prent($tmp);
			//$tmp = $objPricelist->getOnlinerPriceByFilter(array("model" => $ob["PROPERTY_CML2_ARTICLE_VALUE"]));
//			if(count($tmp) == 1){
//				$new_price = $price_no_dis = floor($tmp[0]["minPrice"]);
//			}
			
			
			if(is_array($tmp) && count($tmp) == 1){
				$minPricePlatform = $tmp[0]["minPrice"];
			}
			$ar = AHCatalog::OnGetOptimalPrice($ob["ID"], 1, array(), "N", array(), $price_id);
			if(isset($ar["DISCOUNT"]["VALUE_TYPE"]) && $ar["DISCOUNT"]["VALUE_TYPE"] == "P"){
				$discount_per = $ar["DISCOUNT"]["VALUE"] / 100;
			}
			
			if($minPricePlatform > 0){
				//$new_price = $minPricePlatform * 0.95 / ($discount_per - 1);
				$new_price = $minPricePlatform * (1 - $percent) / (1 - $discount_per);
				$new_price = abs($new_price);
				$new_price = round($new_price, $arSettings["round"]);
				//$new_price = floor($new_price);
				
				//цена без скидки
				$price_no_dis = $new_price - $new_price * $discount_per;
				//$price_no_dis = round($price_no_dis, $arSettings["round"]);
				$price_no_dis = floor($price_no_dis);
			}
			//prent($price_no_dis);
			
		} elseif($price_id == "s3") {
			$tmp = $objPricelist->getCeneoPriceByFilter(array("bitrix_id" => $ob["ID"]));
			if(is_array($tmp) && count($tmp) == 1){
				$minPricePlatform = $tmp[0]["minPrice"];
			}
			$ar = AHCatalog::OnGetOptimalPrice($ob["ID"], 1, array(), "N", array(), $price_id);
			if(isset($ar["DISCOUNT"]["VALUE_TYPE"]) && $ar["DISCOUNT"]["VALUE_TYPE"] == "P"){
				$discount_per = $ar["DISCOUNT"]["VALUE"] / 100;
			}
			
			if($minPricePlatform > 0){
				//$new_price = $minPricePlatform * 0.95 / ($discount_per - 1);
				$new_price = $minPricePlatform * (1 - $percent) / (1 - $discount_per);
				$new_price = abs($new_price);
				$new_price = round($new_price, $arSettings["round"]);
				//$new_price = floor($new_price);
				
				//цена без скидки
				$price_no_dis = $new_price - $new_price * $discount_per;
				//$price_no_dis = round($price_no_dis, $arSettings["round"]);
				$price_no_dis = floor($price_no_dis);
			}
			//prent($price_no_dis);
			
		} elseif($price_id == "ru") {
			$article = $ob["PROPERTY_CML2_ARTICLE_VALUE"];
			
			$price = $objPricelist->getCompetitorPriceByFilter($price_id, ["article" => $article]);
			$priceMin = $objPricelist->prepareMinPrice($price);
			
			if(is_array($priceMin) && count($priceMin) == 1){
				$minPricePlatform = $priceMin[$article]["minPrice"];
			}
			$ar = AHCatalog::OnGetOptimalPrice($ob["ID"], 1, array(), "N", array(), $price_id);
			if(isset($ar["DISCOUNT"]["VALUE_TYPE"]) && $ar["DISCOUNT"]["VALUE_TYPE"] == "P"){
				$discount_per = $ar["DISCOUNT"]["VALUE"] / 100;
			}
			
			if($minPricePlatform > 0){
				//$new_price = $minPricePlatform * 0.95 / ($discount_per - 1);
				$new_price = $minPricePlatform * (1 - $percent) / (1 - $discount_per);
				$new_price = abs($new_price);
				$new_price = round($new_price, $arSettings["round"]);
				
				//цена без скидки
				$price_no_dis = $new_price - $new_price * $discount_per;
				$price_no_dis = floor($price_no_dis);
			}
			//prent($price_no_dis);
			
		} elseif ($price_id == "wb") {
			$article = $ob["PROPERTY_CML2_ARTICLE_VALUE"];
			$price = $objPricelist->getCompetitorPriceByFilter('wb', ["article" => [$article]]);
			$priceMin = $objPricelist->prepareMinPrice($price);
			
			$mpCommision = (int)COption::GetOptionString("panel.manager", "PRICEUPDATE_MP_COMMISSION_WB");
			$revMin = (int)COption::GetOptionString("panel.manager", "PRICEUPDATE_MIN_PER_WB");
			
			if(is_array($priceMin) && count($priceMin) == 1){
				$minPricePlatform = $priceMin[$article]["minPrice"];
			}
			
			if($minPricePlatform > 0){
				$finishPrice = $minPricePlatform * (1 - $percent);
				$finishPrice = abs($finishPrice);
				
				$new_price = $finishPrice * (100 / (100 - $sale_per)) * (100 / (100 - $promo_per));
				if($new_price > 600000) $new_price = 0;
				
				if ($mpCommision > 0) {
					$marginality = (($finishPrice - ($finishPrice * $mpCommision / 100) - $price_supp) / $price_supp) * 100; 
				
					//if ($marginality < $revMin) {
					//	$new_price = 0;
					//}
				}
				
				$new_price = round($new_price, $arSettings["round"]);
				
				$price_no_dis = $new_price;
				//цена без скидки
				//$price_no_dis = $new_price - $new_price * $discount_per;
				//$price_no_dis = floor($price_no_dis);
			}
		}

		if($new_price > 0 && $price_supp < $price_no_dis){
			$res = array(
				'status' => "ok",
				'price' => $new_price
			);
		}else{
			$res = array(
				'status' => 'error',
				'text' => "Некорректная цена"
			);
		}
		$res["asd"] = array($minPricePlatform, $discount_per);
		//prent($res);
	}
	
}else{
	$res = array(
		'status' => 'error',
		'text' => "Тип цены неопределен"
	);
}
//prent($res);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();