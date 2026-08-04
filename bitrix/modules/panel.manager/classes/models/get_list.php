<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2")))
	$psFilter["website"] = $_POST["website"];
if(isset($_POST["brand"]) && $_POST["brand"] != "null")
	$psFilter["brand_id"] = explode(",", $_POST["brand"]);
if(isset($_POST["supplier"]) && $_POST["supplier"] != "null")
	$psFilter["supplier_id"] = explode(",", $_POST["supplier"]);
if(isset($_POST["search_text"]) && strlen($_POST["search_text"]) > 3)
	$psFilter["search_text"] = trim(addslashes($_POST["search_text"]));
//prent($psFilter);die;
//prent($_POST);die;
?>
<?if(!in_array($psFilter["website"], array("s1", "s2"))):?>
	<tr><td colspan="7">
		<p>Выберите сайт</p>
	</tr></td>
	<?die;?>
<?endif?>
<?if(count($psFilter["brand_id"]) <= 0):?>
	<tr><td colspan="7">
		<p>Выберите бренд</p>
	</tr></td>
	<?die;?>
<?endif?>
<?if(count($psFilter["supplier_id"]) <= 0):?>
	<tr><td colspan="7">
		<p>Выберите поставщика</p>
	</tr></td>
	<?die;?>
<?endif?>
<?
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$page_size = 100;
//	$aa = debug_microtime_float();
	
	if(isset($_POST["page_size"]) && intval($_POST["page_size"]) > 0)
		$page_size = intval($_POST["page_size"]); 
	$arSettings = array(
		"round" => ($psFilter["website"] == "s1" ? -1 : 0),
		"rate" => 1,
		"currency" => ($psFilter["website"] == "s1" ? "RUB" : "BYN")
	);
	$objPricelist = new CPanelPricelist;
	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;
	
	$tmp = $objSupplier->getList();
	$arCurrency = $objCurrency->getDetail(($psFilter["website"] == "s1" ? "RUR" : "BYN"));
	
	if($arCurrency){
		$arSettings["rate"] = $arCurrency["rate"];
	}
	
	$arSupName = array();
	foreach($tmp as $arItem){
		$arSupName[$arItem["id"]] = $arItem["name"];
	}

	$price_from = $price_to = 0;
	if(isset($_POST["search_price_from"])){
		$price_from = (float)$_POST["search_price_from"];// * $arSettings["rate"];
		//if($price_from > 0)
		//	$psFilter["price_from"] = $price_from;
	}
	if(isset($_POST["search_price_to"])){
		$price_to = (float)$_POST["search_price_to"];// * $arSettings["rate"];
		//if($price_to > 0)
		//	$psFilter["price_to"] = $price_to;
	}

	$filter = $psFilter;
//	prent($price_from);
	if($_POST["remove_duplicates"] != "Y"){
		if($price_from > 0) $filter["price_from"] = $price_from * $arSettings["rate"];
		if($price_to > 0) $filter["price_to"] = $price_to * $arSettings["rate"];
	}else{
		unset($filter["supplier_id"]);
	}

	$price = $objPricelist->getPriceByFilter($filter);
//	$bb = debug_microtime_float();
//	prent($bb - $aa);die;
	$type_price = ($psFilter["website"] == "s1" ? 1 : 2);
	$arArticle = array();//массив со всеми артикулами
	$tmpPrice = $arPrice = array();//
	foreach($price as $key => &$arItem){
	//$psFilter["supplier_id"]
	//prent($arItem);
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
	foreach($tmpPrice as $key => $arItem){
		if(($price_from > 0 && $arItem["price"] < $price_from) || ($price_to > 0 && $arItem["price"] > $price_to) ||
		 !in_array($arItem["supplier_id"], $psFilter["supplier_id"])){
			unset($tmpPrice[$key]);
			unset($arArticle[$arItem["model"]]);
			//prent($arItem);
		}
	}
	
	if(count($arArticle) <= 0) return;
	/* берем минимальную цену яндекса или онлайнера */
	
	if($_POST["price_competitors"] == "Y"){
		if($psFilter["website"] == "s1"){
			/* ищем цену яндекса */
			$tmp = $objPricelist->getYandexPriceByFilter(array("name" => $arArticle));
		}elseif($psFilter["website"] == "s2"){
			$tmp = $objPricelist->getOnlinerPriceByFilter(array("model" => $arArticle));
		}
		foreach($tmp as $arItem){
			$arPricePlatform[$arItem["name"]] = $arItem;
		}
	}//prent($arPricePlatform);

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
	
	$arCatalogPrice = $objPricelist->getCatalogPriceByFilter(array("model" => $arArticle));
	$key_price = ($psFilter["website"] == "s1" ? "price_ru" : "price_by");
	$key_price_discount = ($psFilter["website"] == "s1" ? "price_discount_ru" : "price_discount_by");
	//prent($key_price);prent($key_price_discount);
	foreach($arCatalogPrice as  $key => $arItem){
		$tmp = $tmpPrice[$arItem["model"]];
		
//		if(!$tmp) continue;
		if($_POST["price"] == "discount"){
			$b_price = $arItem[$key_price_discount];
		}else{
			$b_price = $arItem[$key_price];
		}
		$b_full_price = $arItem[$key_price];
	//	if($tmp["price"] == 0) $tmp["price"] = $b_price;
		//if($arItem["model"] == "NY2219")
		//prent($arItem);
		$revenue_p = 0;
		$revenue = $b_price - $tmp["price"];
		if($tmp["price"] > 0){
			$revenue_p = ($revenue / $tmp["price"]) * 100;
			$revenue_p = round($revenue_p, 2);
		}
		if($tmp["price_with_rrc"] > 0){
			$tmp["price_with_rrc"] = round($tmp["price_with_rrc"], 2);
		}else{
			$tmp["price_with_rrc"] = 0;
		}
		$price_platform = ($arPricePlatform[$arItem["model"]]["minPrice"] ? $arPricePlatform[$arItem["model"]]["minPrice"] : 0);
		if($_POST["price_competitors_act"] == "Y" && $price_platform === 0) continue;
		$price_platform_av = $b_price - $price_platform;
		$price_platform_av = round($price_platform_av, 2);
		$price_platform_sb = $b_price - $price_platform;
		$price_platform_sb = round($price_platform_sb, 2);
		
		$arPrice[] = array(
			"id" => $tmp["id"],
			"article" => $arItem["model"],
			"price" => $tmp["price"],
			"supplier_id" => $tmp["supplier_id"],
			"brand_id" => $tmp["brand_id"],
			"supplier_name" => $arSupName[$tmp["supplier_id"]],
			"b_id" => $arItem["product_id"],//ID битрикс
			"b_link" => $arItem["detail_page_url"],
			"b_price" => $b_price,//цена битрикс
			"b_price_full" => $b_full_price,
			"price_platform" => $price_platform,
			"price_platform_av" => $price_platform_av,
			"price_platform_sb" => $price_platform_sb,
			"revenue" => $revenue,
			"revenue_p" => $revenue_p,
			"price_with_rrc" => $tmp["price_with_rrc"],
		);
	}
	
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
	/******************** сортировка **********************/
	$arAvailSort = array(
		"article" => SORT_STRING,
		"b_price" => SORT_NUMERIC,
		"price" => SORT_NUMERIC,
		"price_platform" => SORT_NUMERIC,
		"price_platform_av" => SORT_NUMERIC,
		"price_platform_sb" => SORT_NUMERIC,
		"revenue" => SORT_NUMERIC,
		"revenue_p" => SORT_NUMERIC,
		"supplier_name" => SORT_STRING,
	);
	$order = $sort = "";
	if($_POST["order"] != "undefined" && $_POST["sort"] != "undefined"){
		if(in_array($_POST["order"], array("asc", "desc")) && isset($arAvailSort[$_POST["sort"]])){
			$order = ($_POST["order"] == "asc" ? SORT_ASC : SORT_DESC);
			$sort = $_POST["sort"];
//$sort = "article";
			$data = array();
			foreach($arPrice as $key => &$arr){
//				if($arr["price_platform"] === 0 && $_POST["sort"] == "price_platform_av" && $_POST["order"] == "desc"){
//					$arr["price_platform_av"] = -999999;
//				}
				$data[$key] = $arr[$sort];
			}
			unset($arr);
			array_multisort($data, $arAvailSort[$sort], $order, $arPrice);
//			prent($arPrice);
			
//	
//	prent($sort);
		}
	}
	$arPrice = array_slice($arPrice, 0, $page_size);
	//prent($arPrice);
/*	if(count($arPrice) > 0){
		$res->NavStart();
		$allEl = $res->NavRecordCount;
	}*/
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
		<tr class="<?if(!$arItem["b_price"] || $arItem["b_price"] == 0):?>no-price<?endif?> <?=$class?>" data-productid="<?=$arItem["b_id"]?>" data-bprice="<?=$arItem["b_price"]?>">
			<td <?if($sort == "article"):?>class="active"<?endif?>>
				<a href="<?=($psFilter["website"] == "s1" ? "http://tempusshop.ru" : "http://tempus.by")?><?=$arItem["b_link"]?>" target="_blank"><?=$arItem["article"]?></a>
			</td>
			<td class="td-price<?if($sort == "b_price"):?> active<?endif?>">
				<input type="text" class="form-control" value="<?=$arItem["b_price"]?>" style="width: 150px;float: left;"></td>
			<td <?if($sort == "price"):?>class="active"<?endif?>><?=$arItem["price"]?></td>
			<td class="c-competitors <?if($sort == "price_platform"):?>active<?endif?>"><?=$arItem["price_platform"]?></td>
			<td class="c-competitors <?if($sort == "price_platform_av"):?>active<?endif?>"><?=$arItem["price_platform_av"]?></td>
			<td class="c-competitors <?if($sort == "price_platform_sb"):?>active<?endif?>"><?=$arItem["price_platform_sb"]?></td>
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