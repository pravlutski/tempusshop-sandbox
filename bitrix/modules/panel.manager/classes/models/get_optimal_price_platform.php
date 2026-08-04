<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$id = intval($_POST["id"]);
$percent = (float) $_POST["percent"];
$percent = $percent / 100;
$website = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2")))
	$website = $_POST["website"];
?>
<?
if(1==2 && CModule::IncludeModule("main") && CModule::IncludeModule("catalog") && 
CModule::IncludeModule("iblock") && CModule::IncludeModule("panel.manager") && $website && $id > 0){
	
	$objCurrency = new CPanelCurrency;
	$arSettings = array(
		"round" => ($website == "s1" ? -1 : 0),
		"rate" => 1,
		"currency" => ($website == "s1" ? "RUB" : "BYN")
	);
	$arCurrency = $objCurrency->getDetail($arSettings["currency"]);
	if($arCurrency){
		$arSettings["rate"] = $arCurrency["rate"];
	}
	
	$objPricelist = new CPanelPricelist;
	$arPricePlatform = array();
	$discount_per = $new_price = 0;
	$artnumber = trim($artnumber);
	$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $id, "!PROPERTY_CML2_ARTICLE" => false), false, false, array("ID", "PROPERTY_CML2_ARTICLE"));
	if ($ob = $objRes->GetNext()){
		if($website == "s1"){
			$tmp = $objPricelist->getYandexPriceByFilter(array("name" => $ob["PROPERTY_CML2_ARTICLE_VALUE"]));
			if(count($tmp) == 1){
				$minPricePlatform = $tmp[0]["minPrice"];
			}
			$ar = AHCatalog::OnGetOptimalPrice($ob["ID"], 1, array(), "N", array(), $website);
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
		}else{
			$tmp = $objPricelist->getOnlinerPriceByFilter(array("model" => $ob["PROPERTY_CML2_ARTICLE_VALUE"]));//prent($tmp);
//			if(count($tmp) == 1){
//				$new_price = $price_no_dis = floor($tmp[0]["minPrice"]);
//			}
			
			
			if(count($tmp) == 1){
				$minPricePlatform = $tmp[0]["minPrice"];
			}
			$ar = AHCatalog::OnGetOptimalPrice($ob["ID"], 1, array(), "N", array(), $website);
			if(isset($ar["DISCOUNT"]["VALUE_TYPE"]) && $ar["DISCOUNT"]["VALUE_TYPE"] == "P"){
				$discount_per = $ar["DISCOUNT"]["VALUE"] / 100;
			}
			
			if($minPricePlatform > 0){
				//$new_price = $minPricePlatform * 0.95 / ($discount_per - 1);
				$new_price = $minPricePlatform * (1 - $percent) / (1 - $discount_per);
				$new_price = abs($new_price);
				//$new_price = round($new_price, $arSettings["round"]);
				$new_price = floor($new_price);
				
				//цена без скидки
				$price_no_dis = $new_price - $new_price * $discount_per;
				//$price_no_dis = round($price_no_dis, $arSettings["round"]);
				$price_no_dis = floor($price_no_dis);
			}
			//prent($price_no_dis);
			
		}
		$price_supp = 0;
		if($_POST["id_price"] > 0){
			$ar = $objPricelist->getPriceByFilter(array("id" => $_POST["id_price"]));
			$price_supp = $ar[0]["price"] / $arSettings["rate"];//закупочная цена поставщика
		}
		if($new_price > 0 && $price_supp < $price_no_dis){
			$res = array(
				'status' => "ok",
				'price' => $new_price
			);
		}else{
			$res = array(
				'status' => 'error',
				'text' => "Не корректная цена"
			);
		}
		$res["asd"] = array($new_price,$price_supp,$price_no_dis);
		//prent($res);
	}else{
			$res = array(
				'status' => 'error',
				'text' => "Не найден товар {$id}"
			);
	}
	
}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить"
	);
}
//prent($res);
			$res = array(
				'status' => "ok",
				'price' => 12312
			); 
			
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();