<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?

?>

<?
function getBadItems($site_id = "s1"){
	$psFilter["website"] = $site_id;
	$arSettings = array(
		"round" => ($psFilter["website"] == "s1" ? -1 : 0),
		"rate" => 1,
		"currency" => ($psFilter["website"] == "s1" ? "RUB" : "BYN")
	);
	$objPricelist = new CPanelPricelist;
	$objCurrency = new CPanelCurrency;
	
	$arCurrency = $objCurrency->getDetail(($psFilter["website"] == "s1" ? "RUR" : "BYN"));
	if($arCurrency){
		$arSettings["rate"] = $arCurrency["rate"];
	}

	$type_price = ($psFilter["website"] == "s1" ? 1 : 2);
	$price = $objPricelist->getPriceByFilter($psFilter);

	$arArticle = array();//массив со всеми артикулами
	$tmpPrice = $arPrice = array();//
	foreach($price as $key => &$arItem){
        if($arItem["model"]){
			$arItem["price"] = $arItem["price"] / $arSettings["rate"];
			$arItem["price"] = round($arItem["price"], $arSettings["round"]);
			$arArticle[$arItem["model"]] = $arItem["model"];
			if(isset($tmpPrice[$arItem["model"]])){
				if($arItem["price"] < $tmpPrice[$arItem["model"]]["price"])
					$tmpPrice[$arItem["model"]] = $arItem;
			}else
				$tmpPrice[$arItem["model"]] = $arItem;
		}
	}
	unset($arItem);

	if(!$arArticle) return;

	$arCatalogPrice = $objPricelist->getCatalogPriceByFilter(array("model" => $arArticle));
	$key_price = ($psFilter["website"] == "s1" ? "price_ru" : "price_by");
	$key_price_discount = ($psFilter["website"] == "s1" ? "price_discount_ru" : "price_discount_by");
	//prent($key_price);prent($key_price_discount);
	foreach($arCatalogPrice as  $key => $arItem){
		$tmp = $tmpPrice[$arItem["model"]];
		//
		$b_price = $arItem[$key_price_discount];
		//prent($b_price);
		$revenue = $b_price - $tmp["price"];
		if($revenue < 0){
			$arPrice[] = array(
				"id" => $tmp["id"],
				"article" => $arItem["model"],
				"price" => $tmp["price"],
				"b_price" => $b_price,//цена битрикс
				"revenue" => $revenue,
			);
		}
	}
	return $arPrice;
}

function getCntPurchaseTop($website = "s1"){
	global $DB;
	$arResult = $arTmp = $arArticle = array();
	$objPricelist = new CPanelPricelist;
	/* массив закупок которые уже добавлены в правую колонку */
	$strSql = "SELECT * FROM ci_purchase WHERE status = 'T' AND active = 'Y' AND site_id = '".$website."'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arPurchase[$row["model"]] = $row;
	}

	$strSql = "SELECT * FROM ci_top_models WHERE site_id = '".$website."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		if(!$arPurchase[$row["model"]]){
			$arResult["ITEMS"][] = array(
				"ID" => $row["id"],
				"ARTICLE" => $row["model"],
				"BITRIX_ID" => $row["bitrix_id"],
			);
			$arArticle[$row["model"]] = $row["model"];
		}
	}
	
	// AND model='EFR-526L-1A'
	$arFilter = array("article" => $arArticle);//
	$tmp = $objPricelist->getPriceByFilter($arFilter, false, array("model", "price", "id", "supplier_id"));
	
	foreach($tmp as $k => $v){
		$supp_id = ($website == "s1" ? 47 : 44);
		if($v["supplier_id"] == $supp_id){
			$price[$v["model"]]["STOCK"][$v["id"]] = $v;
		}else{
			$price[$v["model"]]["PRICELIST"][$v["id"]] = $v;
		}
		
	}
	
	foreach($arResult["ITEMS"] as $key => &$arItem){
		if($price[$arItem["ARTICLE"]]){
			if(!$price[$arItem["ARTICLE"]]["STOCK"]){
				$arItem["PRICELIST"] = $price[$arItem["ARTICLE"]]["PRICELIST"];
			}			
		}
	}
	unset($arItem);
	
	//prent(count($price));return;
	/*
	foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arItem["ARTICLE"]){
			$arr = array();
			$supp_id = ($website == "s1" ? 47 : 44);
			$strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["ARTICLE"]}'";// AND supplier_id <> '{$supp_id}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			
			$avail = false;
			while ($row = $results->Fetch()){
				$arr[$row["id"]] = $row;
				//если есть на складе, то не выводим совсем
				if($row["supplier_id"] == $supp_id)
					$avail = true;
			}
			if($avail === false)
				$arItem["PRICELIST"] = $arr;
			else
				$arItem["IN_STOCK"] = "Y";
		}
	}
	
	unset($arItem);
	*/
	//prent($arResult["ITEMS"]);return;
	$arResult["PRICE"] = array();
	$cnt = 0;
	foreach($arResult["ITEMS"] as $key => &$arItem){
		$ar = array();
		foreach($arItem["PRICELIST"] as $arr){
			$ar[$arr["id"]] = $arr["price"];
		}
		$min = array_keys($ar, min($ar))[0];
		
		if($arItem["PRICELIST"][$min]) {
			$cnt++;
		}
	}
	unset($arItem);
	return $cnt;
}
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
//	$arResult["ITEMS_RU"] = getCntPurchaseTop("s1");
//	$arResult["ITEMS_BY"] = getCntPurchaseTop("s2");
	$cnt = $arResult["ITEMS_RU"] + $arResult["ITEMS_BY"];
	?>
	<?/*if($cnt > 0):?>
	<span class="badge"><?=$cnt?></span>
	<?endif*/?>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');