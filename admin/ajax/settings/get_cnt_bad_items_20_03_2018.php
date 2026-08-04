<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
//die;
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

	if(count($arArticle) <= 0) return;

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
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arResult["ITEMS_RU"] = getBadItems("s1");
	$arResult["ITEMS_BY"] = getBadItems("s2");
	$cnt = count($arResult["ITEMS_RU"]) + count($arResult["ITEMS_BY"]);
	?>
	<?if($cnt > 0):?>
	<span class="badge"><?=$cnt?></span>
	<?endif?>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');