<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
if(isset($_POST["brand"]) && $_POST["brand"] != "null")
	$psFilter["brand_id"] = $_POST["brand"];
//prent($psFilter);die;

?>
<?
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$page_size = 100;

	$arSettings = array(
		"round" => ($psFilter["website"] == "s1" ? -1 : 0),
		"rate" => 1,
		"currency" => ($psFilter["website"] == "s1" ? "RUB" : "BYN")
	);
	
	$objPricelist = new CPanelPricelist;
	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;
	
	$tmp = $objSupplier->getList();
	
	$arSupName = array();
	foreach($tmp as $arItem){
		$arSupName[$arItem["id"]] = $arItem["name"];
	}
	//"supplier_id", "store_id", "MIN(price) as price"
	$arSelect = array("id", "model", "brand_id", "supplier_id", "price");
	$price = $objPricelist->getPriceByFilter($psFilter, true, $arSelect);
prent($psFilter);prent($price);die;
prent($price);die;

	$arArticle = array();//массив со всеми артикулами
	$tmpPrice = $arPrice = array();
	foreach($price as $key => &$arItem){
		if($arItem["model"]){
			$arItem["price"] = $arItem["price"] / $arSettings["rate"];
			$arItem["price"] = (float)round($arItem["price"], $arSettings["round"]);
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

	//если стоит галку убрать хорошие РРЦ
	if($_POST["hide_rrc"] == "Y"){
		$objAnalysis = new CPanelAnalysis;	
		foreach($objAnalysis->getList($psFilter["website"]) as $key => $arItem){
			$arSettingsRRC[$arItem["brand_id"]] = $arItem;
		}
		foreach($tmpPrice as $key => &$arItem){
			if(isset($arSettingsRRC[$arItem["brand_id"]])){
				$tmp = json_decode($arSettingsRRC[$arItem["brand_id"]]["settings"], true);
				foreach($tmp as $k => $v){
					if($arItem["price"] >= $v["price_from"] && $arItem["price"] <= $v["price_to"]){
						$arItem["price_with_rrc"] = $arItem["price"] * $v["markup"];
						break;
					}
				}//prent($arItem);
			}
		}
	}
	unset($arItem);
	

	$key_price = ($psFilter["website"] == "s1" ? "price_ru" : "price_by");
	$key_price_discount = ($psFilter["website"] == "s1" ? "price_discount_ru" : "price_discount_by");
	//prent($key_price);prent($key_price_discount);
	foreach($arCatalogPrice as  $key => $arItem){
		$tmp = $tmpPrice[$arItem["model"]];
		
		$arPrice[] = array(
			"id" => $tmp["id"],
			"article" => $arItem["model"],
			"price" => $tmp["price"],
			"supplier_id" => $tmp["supplier_id"],
			"brand_id" => $tmp["brand_id"],
			"supplier_name" => $arSupName[$tmp["supplier_id"]],
			"b_id" => $arItem["product_id"],//ID битрикс
			"b_sku_id" => $arItem["product_sku"],//SKU ID битрикс
			"b_link" => $arItem["detail_page_url"],
			"b_price" => $b_price,//цена битрикс
			"b_price_full" => $b_full_price,
			"price_platform" => $price_platform,
			"price_platform_av" => $price_platform_av,
			"revenue" => $revenue,
			"revenue_p" => $revenue_p,
			"price_with_rrc" => $tmp["price_with_rrc"],
		);
	}
	//prent($arPrice);
	//убираем из массива позиции с корректными РРЦ
	if($_POST["hide_rrc"] == "Y"){
		foreach($arPrice as $key => $arItem){
			if ($arItem["price_with_rrc"] > 0){
				$min = $arItem["b_price_full"] - $arItem["b_price_full"] * 5 / 100;
				$max = $arItem["b_price_full"] + $arItem["b_price_full"] * 5 / 100;
				if($arItem["price_with_rrc"] >= $min && $arItem["price_with_rrc"] <= $max){
					unset($arPrice[$key]);
					//prent("asd");
				}
			}else{
				unset($arPrice[$key]);
			}
		}
	}

	$cntAll = count($arPrice);
	//prent($cntAll);
	$arPrice = array_slice($arPrice, 0, $page_size);
	?>
	<?if($cntAll != count($arPrice)):?>
		<tr><td colspan="9">Показаны <?=count($arPrice)?> из <?=$cntAll?></td></tr>
	<?endif?>
	<?

//	prent($all);
/* 
	foreach($price as $key => &$arItem){
		if($arItem["model"]){
			$res = CIBlockElement::getList(array(), array('ACTIVE' => 'Y', 'IBLOCK_ID' => CProSet::IB_CATALOG, "PROPERTY_CML2_ARTICLE" => $arItem["model"]),false, false,array("ID", "CATALOG_GROUP_{$type_price}"));
			if ($row = $res->getNext()) {
				$arItem["b_price"] = $row["CATALOG_PRICE_{$type_price}"];
				$arItem["b_price_cur"] = $row["CATALOG_CURRENCY_{$type_price}"];
			}else
				unset($price[$key]);
		}else
			unset($price[$key]);
	}
*/
	//prent($arPrice); 
	//[supplier_id] => 45
	foreach($arPrice as $key => $arItem):?>
		<?
		$class = "";
		if($arItem["b_price"] > 0 && $arItem["price_platform"] > 0 && $arItem["b_price"] < $arItem["price_platform"]){
			$p = abs($arItem["b_price"] - $arItem["price_platform"]) * 100 / $arItem["b_price"];
			if($p > 10) $class = "warning";
		}elseif($arItem["price_platform"] > 0 && $arItem["price_platform"] < $arItem["b_price"]){
			$class = "danger";
		}
		?>
		<tr class="<?if(!$arItem["b_price"] || $arItem["b_price"] == 0):?>no-price<?endif?> <?=$class?>" data-productid="<?=$arItem["b_id"]?>" data-skuid="<?=$arItem["b_sku_id"]?>" data-bprice="<?=$arItem["b_price"]?>">
			<td <?if($sort == "article"):?>class="active"<?endif?>>
				<a href="<?=($psFilter["website"] == "s1" ? "http://tempusshop.ru" : "http://tempus.by")?><?=$arItem["b_link"]?>" target="_blank"><?=$arItem["article"]?></a>
			</td>
			<td class="td-price<?if($sort == "b_price"):?> active<?endif?>">
				<input type="text" class="form-control" value="<?=$arItem["b_price"]?>" style="width: 150px;float: left;"></td>
			<td <?if($sort == "price"):?>class="active"<?endif?>><?=$arItem["price"]?></td>
			<td class="c-competitors <?if($sort == "price_platform"):?>active<?endif?>"><?=$arItem["price_platform"]?></td>
			<td class="c-competitors <?if($sort == "price_platform_av"):?>active<?endif?>"><?=$arItem["price_platform_av"]?></td>
			<td <?if($sort == "revenue"):?>class="active"<?endif?>><?=$arItem["revenue"]?></td>
			<td <?if($sort == "revenue_p"):?>class="active"<?endif?>><?=$arItem["revenue_p"]?></td>
			<td <?if($sort == "supplier_name"):?>class="active"<?endif?>><?=$arSupName[$arItem["supplier_id"]]?></td>
			<td>
				<?if($arItem["price"] > 0):?>
				<button type="button" class="btn btn-primary btn-get-price" data-id="<?=$arItem["id"]?>">РРЦ</button>
				<?endif?>
				<?if($arItem["price_platform"] > 0):?>
				<button type="button" class="btn btn-primary btn-get-price-platform c-competitors" data-id="<?=$arItem["b_id"]?>" data-priceid="<?=$arItem["id"]?>">КЦ</button>
				<?endif?>
			</td>
		</tr>
	<?endforeach?>
	
	<?
/*	if($allEl > $page_size):?>
		<tr><td colspan="7">
		<select class="form-control select_w" id="s-page">
		<?
		$page = round($allEl / $page_size);
		for($i = 1; $i < $page; $i++):?> 
		<option value="<?=$i?>"><?=$i?></option>
		<?endfor?>
		<select>
		<?//=$page?>
		</tr></td>
	<?endif*/?>
	<?
	//prent($price);

}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');