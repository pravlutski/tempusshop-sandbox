<?php
if(!$_SERVER['DOCUMENT_ROOT']){
	$_SERVER['DOCUMENT_ROOT'] = "/var/www/bitrix/data/www/tempusshop.ru";
}
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(!CModule::IncludeModule("panel.manager")) return;
global $DB, $APPLICATION;

if(!$_REQUEST){
	foreach ((array)$_SERVER['argv'] as $v){
		list($k,$v) = explode("=",$v);
		if ($k && $v) $_REQUEST[$k] = $v;
	}
}

if(isset($_REQUEST["website"]) && in_array($_REQUEST["website"], array("ru","by","pl","ya","os","wb","wbtl","wbby","av", "sb", "kz", "ozkz", "ozti")))
	$psFilter["price_id"] = $_REQUEST["website"];

if(isset($_REQUEST["brand"]) && $_REQUEST["brand"] != "null"){
	$psFilter["brand_id"] = explode(",", $_REQUEST["brand"]);
}else{
	$tmp = (new CPanelBrand)->getList();
	$psFilter["brand_id"] = array_column($tmp, "id");
}

if(isset($_REQUEST["supplier"]) && $_REQUEST["supplier"] != "null"){
	$psFilter["supplier_id"] = explode(",", $_REQUEST["supplier"]);
}else{
	$tmp = (new CPanelSupplier)->getList();
	$psFilter["supplier_id"] = array_column($tmp, "id");
}

if(isset($_REQUEST["search_text"]) && strlen($_REQUEST["search_text"]) > 3)
	$psFilter["search_text"] = trim(addslashes($_REQUEST["search_text"]));

if(isset($_REQUEST["article"]) && $_REQUEST["article"] != "null")
	 $psFilter["article"] = explode(",", $_REQUEST["article"]);

?>
<?if(!in_array($psFilter["price_id"], array("ru","by","pl","ya","os","wb","wbtl","wbby","av", "sb", "kz", "ozkz", "ozti"))):?>
	<tr><td colspan="7">
		<p>Выберите цену</p>
	</tr></td>
	<?die;?>
<?endif?>
<?if(is_array($psFilter["brand_id"]) && count($psFilter["brand_id"]) <= 0):?>
	<tr><td colspan="7">
		<p>Выберите бренд</p>
	</tr></td>
	<?die;?>
<?endif?>
<?if(is_array($psFilter["supplier_id"]) && count($psFilter["supplier_id"]) <= 0):?>
	<tr><td colspan="7">
		<p>Выберите поставщика</p>
	</tr></td>
	<?die;?>
<?endif?>
<?
if(CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")):?>
	<?
	$page_size = 100;
//	$aa = debug_microtime_float();

	if(isset($_REQUEST["page_size"]) && intval($_REQUEST["page_size"]) > 0)
		$page_size = intval($_REQUEST["page_size"]);

	switch($psFilter["price_id"]){
		case "ru":
			//$round = -1;
			$round = -0;
			$currency = "RUB";
			$key_price = "price_ru";
			$key_price_discount = "price_discount_ru";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_ru";
			$siteR = "s1";
			break;
		case "by":
			//$round = -0;
			$round = 2;
			$currency = "BYN";
			$key_price = "price_by";
			$key_price_discount = "price_discount_by";
			$url = "https://tempus.by";

			$key_set_price= "set_price_by";
			$siteR = "s2";
			break;
		case "pl":
			$round = 0;
			$currency = "PLN";
			$key_price = "price_pl";
			$key_price_discount = "price_discount_pl";
			$url = "https://tempusshop.pl";

			$key_set_price= "set_price_pl";
			$siteR = "s3";
			break;
		case "ya":
			$round = -0;
			$currency = "RUB";
			$key_price = "price_ya";
			$key_price_discount = "price_discount_ya";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_ya";
			$siteR = "s1";
			break;
		case "os":
			$round = -0;
			$currency = "RUB";
			$key_price = "price_os";
			$key_price_discount = "price_os";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_os";
			$siteR = "s1";
			break;
		case "sb":
			$round = -0;
			$currency = "RUB";
			$key_price = "price_sb";
			$key_price_discount = "price_sb";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_sb";
			$siteR = "s1";
			break;
		case "kz":
			$round = -0;
			$currency = "KZT";
			$key_price = "price_kz";
			$key_price_discount = "price_kz";
			$url = "https://tempuswatch.kz";

			$key_set_price= "set_price_kz";
			$siteR = "s4";
			break;
		case "ozkz":
		  $round = -0;
		  $currency = "RUB";
		  $key_price = "price_ozkz";
		  $key_price_discount = "price_ozkz";
		  $url = "https://tempuswatch.kz";

		  $key_set_price= "set_price_ozkz";
		  $siteR = "s4";
		  break;
		case "ozti":
			$round = -0;
			$currency = "RUB";
			$key_price = "price_ozti";
			$key_price_discount = "price_ozti";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_ozti";
			$siteR = "s1";
			break;
		case "wb":
			//$round = -1;
			$round = -0;
			$currency = "RUB";
			$key_price = "price_wb";
			$key_price_discount = "price_wb";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_wb";
			$promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
			$sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");
			$siteR = "s1";
			break;
		case "wbtl":
			//$round = -1;
			$round = -0;
			$currency = "RUB";
			$key_price = "price_wbtl";
			$key_price_discount = "price_wbtl";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_wb";
			$promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
			$sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");
			$siteR = "s1";
			break;
		case "wbby":
			$round = 2;
			$currency = "BYN";
			$key_price = "price_wbby";
			$key_price_discount = "price_wbby";
			$url = "https://tempus.by";

			$key_set_price= "set_price_wbby";
			//$promo_per = (float)CProSet::getOption("CATALOG_PROMO_wb");
			//$sale_per = (float)CProSet::getOption("CATALOG_SALE_wb");
			$siteR = "s2";
			break;
		case "av":
			$round = -0;
			$currency = "RUB";
			$key_price = "price_av";
			$key_price_discount = "price_av";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_av";
			$siteR = "s1";
			break;
		default:
			$round = -1;
			$currency = "RUB";
			$key_price = "price_ru";
			$key_price_discount = "price_discount_ru";
			$url = "https://tempusshop.ru";

			$key_set_price= "set_price_ru";
			$siteR = "s1";
			break;
	}

	//доставем все резервы
	$strSql = "SELECT ARTICLE as ARTICLE, RESERVED_{$siteR} as RESERVED FROM ci_reserved WHERE RESERVED_{$siteR} > 0";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arReserve[$row["ARTICLE"]] = $row["RESERVED"];
	}
	//prent($arReserve);
	$arSettings = array(
		"round" => $round,
		"rate" => 1,
		"currency" => $currency
	);

	$objPricelist = new CPanelPricelist;
	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;
	$objUtils = new CPanelUtils;

	$tmp = $objSupplier->getList();
	$arCurrency = $objCurrency->getDetail($currency);

	//prent($arCurrency);
	if($arCurrency){
		$arSettings["rate"] = $arCurrency["rate"];
	}

	$arSupName = array();
	foreach($tmp as $arItem){
		$arSupName[$arItem["id"]] = $arItem["name"];
	}

	$price_from = $price_to = 0;
	if(isset($_REQUEST["search_price_from"])){
		$price_from = (float)$_REQUEST["search_price_from"];// * $arSettings["rate"];
		//if($price_from > 0)
		//	$psFilter["price_from"] = $price_from;
	}
	if(isset($_REQUEST["search_price_to"])){
		$price_to = (float)$_REQUEST["search_price_to"];// * $arSettings["rate"];
		//if($price_to > 0)
		//	$psFilter["price_to"] = $price_to;
	}

	if($_REQUEST["only_active"] == "N"){
		unset($psFilter["price_id"]);
	}
	$filter = $psFilter;
	//prent($filter);
	if($_REQUEST["remove_duplicates"] != "Y"){
		if($price_from > 0) $filter["price_from"] = $price_from * $arSettings["rate"];
		if($price_to > 0) $filter["price_to"] = $price_to * $arSettings["rate"];
	}else{
		unset($filter["supplier_id"]);
	}

	$tmpPricetype = json_decode(CProSet::getOption("SETTINGS_RRC"), true)[$psFilter["price_id"]];
	if (!isset($tmpPricetype['price_type'])) {
		$priceType = 'price';
	} else {
		$priceType = $tmpPricetype['price_type'];
	}
	$price = $objPricelist->getPriceByFilterNew($filter, false, false, "$priceType asc",$priceType);

	$arArticle = array();//массив со всеми артикулами
	$tmpPrice = $arPrice = array();

	//$arReserveOrder = array();
//prent($arReserve["A-158WA-1"]);
//$arReserve["A-158WA-1"] += 1;
	foreach($price as $key => &$arItem){
		if(isset($arReserve[$arItem["model"]])){
			if($arReserve[$arItem["model"]] >= $arItem["count"]){
				$arItem["can_buy"] = false;
				$arReserve[$arItem["model"]] -= $arItem["count"];
			}else{
				$arItem["can_buy"] = true;
			}
		}else{
			$arItem["can_buy"] = true;
		}
		//$arItem["can_buy"] = true;
	}
	unset($arItem);


	foreach($price as $key => &$arItem){
	//$psFilter["supplier_id"]
	//prent($arItem);
		if ($priceType == 'price_n') {
			$arItem["price"] = $arItem["price_n"];
		}
		if($arItem["model"] && $arItem["can_buy"] === true){
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/analysis/kzprice.txt", print_r($arItem["model"], true).PHP_EOL, FILE_APPEND);
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/analysis/kzprice.txt", print_r($arItem["price"], true).PHP_EOL, FILE_APPEND);
			// if ( $arItem['currency'] == 'BYN'){
			// 	$arItem["price"] = $arItem["price"];
			// }else{
			// }
			$arItem["price_raw"] = $arItem["price"];
			$arItem["price"] = $arItem["price"] / $arSettings["rate"];
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/analysis/kzprice.txt", print_r($arItem["price"], true).PHP_EOL, FILE_APPEND);
			$arItem["price"] = (float)round($arItem["price"], $arSettings["round"]);
			$arArticle[$arItem["model"]] = $arItem["model"];
			// if ($siteR == "s2") {
			//
			// 	// if ($arItem['supplier_id'] == 44) {
			// 	// 	$tmpPrice[$arItem["model"]] = $arItem;
			// 	// }
			//
			// 	if(isset($tmpPrice[$arItem["model"]]) && $tmpPrice[$arItem["model"]]['supplier_id'] != 44){
			// 		if($arItem["price"] < $tmpPrice[$arItem["model"]]["price"])
			// 			$tmpPrice[$arItem["model"]] = $arItem;
			// 	} else {
			// 		if ($tmpPrice[$arItem["model"]]['supplier_id'] != 44)
			// 			$tmpPrice[$arItem["model"]] = $arItem;
			// 	}
			// } else {
				if(isset($tmpPrice[$arItem["model"]])){
					if($arItem["price"] < $tmpPrice[$arItem["model"]]["price"])
						$tmpPrice[$arItem["model"]] = $arItem;
				}else
					$tmpPrice[$arItem["model"]] = $arItem;
			// }
		}
	}
	unset($arItem);
	//prent($price);
	//prent($tmpPrice);

	foreach($tmpPrice as $key => $arItem){
		if(($price_from > 0 && $arItem["price"] < $price_from) || ($price_to > 0 && $arItem["price"] > $price_to) ||
		 !in_array($arItem["supplier_id"], $psFilter["supplier_id"])){
			unset($tmpPrice[$key]);
			unset($arArticle[$arItem["model"]]);
			//prent($arItem);
		}
	}

	if(is_array($arArticle) && count($arArticle) <= 0) return;
	/* берем минимальную цену яндекса или онлайнера */
	//

	if($_REQUEST["price_competitors"] == "Y"){
		$price_id = $psFilter["price_id"];
		if($psFilter["price_id"] == "ru"){
			/* ищем цены конкурентов */
			$price = $objPricelist->getCompetitorPriceByFilter($price_id, ["article" => $arArticle]);
			$tmp = $objPricelist->prepareMinPrice($price);
			//prent($tmp22);
		}elseif($psFilter["price_id"] == "ya"){
			/* ищем цену яндекса */
			$tmp = $objPricelist->getYandexPriceByFilter(array("name" => $arArticle));
			//prent($tmp);
		}elseif($psFilter["price_id"] == "by"){
			//prent($arArticle);
			$arTmp = $arArticleAlt = array();
			//сразу выбираем артикулы из свойства товара "Артикул Онлайнера" для подмены

			$tmp = array_values($arArticle);
			sort($tmp);

			if(is_array($tmp) && count($tmp) > 0){
				$strSql = "SELECT pr.PROPERTY_123 as CML2_ARTICLE, pr.PROPERTY_373 as ARTICLE_ONLINER
				FROM
					b_iblock_element el
				LEFT JOIN
					b_iblock_element_prop_s16 pr
				ON el.ID=pr.IBLOCK_ELEMENT_ID
				WHERE
					el.IBLOCK_ID = '".CProSet::IB_CATALOG."' AND pr.PROPERTY_123 IN ('" . implode("','", $tmp) . "') AND pr.PROPERTY_373 <> ''";

				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				while ($row = $results->Fetch()){
					$arTmp[$row["CML2_ARTICLE"]] = $row["ARTICLE_ONLINER"];
				}
			}

			/*//prent($tmp);die;
			$res = CIBlockElement::getList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, "PROPERTY_CML2_ARTICLE" => $tmp, "!PROPERTY_ARTICLE_ONLINER" => false), array("PROPERTY_CML2_ARTICLE","PROPERTY_ARTICLE_ONLINER"),false,array("ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_ARTICLE_ONLINER"));
			if ($row = $res->getNext()) {
				prent($row);die;
				$arTmp[$row["PROPERTY_CML2_ARTICLE_VALUE"]] = $row["PROPERTY_ARTICLE_ONLINER_VALUE"];
			}
			//die;*/

			$allAltArticle = $objUtils->getAltAnList();
			foreach($arArticle as $article){
				if($arTmp[$article]) $arArticleAlt[] = $arTmp[$article]; else $arArticleAlt[] = $article;
				if($allAltArticle[$article]) $arArticleAlt = array_merge($arArticleAlt, $allAltArticle[$article]);
			}
			//prent($arArticleAlt);



			//
			$tmp2 = $objPricelist->getOnlinerPriceByFilter(array("model" => $arArticleAlt));
			prent($tmp2);
			$tmp = array();
			foreach($tmp2 as $key => $arItem){
				if($name = array_search($arItem["name"], $arTmp)){
					$tmp[$key] = array(
						"name" => $name,
						"minPrice" => $arItem["minPrice"],
						"minPrice2" => $arItem["minPrice2"],
						"minPrice3" => $arItem["minPrice3"],
					);
				}else{
					$tmp[$key] = array(
						"name" => $arItem["name"],
						"minPrice" => $arItem["minPrice"],
						"minPrice2" => $arItem["minPrice2"],
						"minPrice3" => $arItem["minPrice3"],
					);
				}
			}

			//
		}elseif($psFilter["price_id"] == "pl"){
			/* ищем цену яндекса */
			$tmp = $objPricelist->getCeneoPriceByFilter(array("name" => $arArticle));
			//prent($tmp);
		} elseif($psFilter["price_id"] == "wb"){
			/* ищем цену wb */
			//$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
			$price = $objPricelist->getCompetitorPriceByFilter($price_id, ["article" => $arArticle]);
			$tmp = $objPricelist->prepareMinPrice($price);
			
			//prent($price);
			//prent($tmp);
		}elseif($psFilter["price_id"] == "wbtl"){
			/* ищем цену wb */
			$tmp = $objPricelist->getWbPriceByFilter(array("name" => $arArticle));
			print_r($tmp);
			//prent($tmp);
		}elseif($psFilter["price_id"] == "wbby"){
			$tmp = [];
		}

		foreach($tmp as $arItem){
			$arPricePlatform[$arItem["name"]] = $arItem;
		}
	}
	//prent($arPricePlatform);

	//если стоит галку убрать хорошие РРЦ
	if($_REQUEST["hide_rrc"] == "Y"){
		$objAnalysis = new CPanelAnalysis;
		foreach($objAnalysis->getList($psFilter["price_id"]) as $key => $arItem){
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

//$arArticle = ["GA-100-1A2"];
	//prent($tmpPrice);

	$arCatalogPrice = $objPricelist->getCatalogPriceByFilter(array("model" => $arArticle));

	if($psFilter["price_id"] == "wb" && CModule::IncludeModule("maxyss.wb")){

		$arSettings = CMaxyssWb::settings_wb("WR");
		//prent($arSettings);
		foreach(CMaxyssWb::getAllItemsAvail($arSettings) as $key => $arItem){
			$itemsAvail[$arItem["PROPERTY_CML2_ARTICLE_VALUE"]] = $arItem;
			//prent($arItem);
		}

		foreach($arCatalogPrice as $key => $arItem){
			$b_price = $arItem[$key_price] / 100 * (100 - $sale_per) / (100 / (100 - $promo_per));
			//prent($b_price);
			if(!$itemsAvail[$arItem["model"]] || ($b_price < 50 || $b_price > 50000)){
				unset($arCatalogPrice[$key]);
			}
		}

	}

	if($psFilter["price_id"] == "wbtl" && CModule::IncludeModule("maxyss.wb")){

		$arSettings = CMaxyssWb::settings_wb("WR");
		//prent($arSettings);
		foreach(CMaxyssWb::getAllItemsAvail($arSettings) as $key => $arItem){
			$itemsAvail[$arItem["PROPERTY_CML2_ARTICLE_VALUE"]] = $arItem;
			//prent($arItem);
		}

		foreach($arCatalogPrice as $key => $arItem){
			$b_price = $arItem[$key_price] / 100 * (100 - $sale_per) / (100 / (100 - $promo_per));
			//prent($b_price);
			if(!$itemsAvail[$arItem["model"]] || ($b_price < 50 || $b_price > 50000)){
				unset($arCatalogPrice[$key]);
			}
		}

	}

	foreach($arCatalogPrice as  $key => $arItem){
		$tmp = $tmpPrice[$arItem["model"]];

//		if(!$tmp) continue;
		if($_REQUEST["price"] == "discount"){
			if($psFilter["price_id"] == "wb" || $psFilter["price_id"] == "wbtl"){
				//$b_price = ($arItem[$key_price] * ( 100 / (100 - $sale_per))) * (100 / (100 - $promo_per));
				//11440 / 100 * (100 - 30) / (100 / (100 - 25))
				//$asd = "{$arItem[$key_price]} / ( 100 * (100 - {$sale_per})) / (100 / (100 - {$promo_per}))";
				//prent($asd);

				if($arItem[$key_price] > 0){
					// $b_price = $tmp["price_with_rrc"];

					$b_price = $arItem[$key_price] / 100 * (100 - $sale_per) / (100 / (100 - $promo_per));

				}else{
					$b_price = 0;
				}

				//Цена с учетом скидки = цена ВБ / 100 * (100-промокод) / 100 / (100-скидка)
				$b_price = round($b_price, $arSettings["round"]);
				//$b_price = $arItem[$key_price_discount];
			}else{
				$b_price = $arItem[$key_price_discount];
			}


		}else{
			$b_price = $arItem[$key_price];
		}//prent($arItem);
		// print_r($arItem[$key_price]);
		// print_r('#');
		// print_r($b_price);
		// print_r('//');
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
		$price_platform2 = ($arPricePlatform[$arItem["model"]]["minPrice2"] ? $arPricePlatform[$arItem["model"]]["minPrice2"] : 0);
		$price_platform3 = ($arPricePlatform[$arItem["model"]]["minPrice3"] ? $arPricePlatform[$arItem["model"]]["minPrice3"] : 0);
		//prent($arPricePlatform);prent($arItem["model"]);
		if($_REQUEST["price_competitors_act"] == "Y" && $price_platform === 0) continue;
		$price_platform_av = $b_price - $price_platform;
		$price_platform_av = round($price_platform_av, 2);
		$price_platform_sb = $b_price - $price_platform;
		$price_platform_sb = round($price_platform_sb, 2);

		$price_platform_kz = $b_price - $price_platform;
		$price_platform_kz = round($price_platform_kz, 2);

    $price_platform_ozkz = $b_price - $price_platform;
    $price_platform_ozkz = round($price_platform_ozkz, 2);

		$price_platform_ozti = $b_price - $price_platform;
    $price_platform_ozti = round($price_platform_ozti, 2);

		$set_price = $arItem[$key_set_price];

		$arPrice[] = array(
			"id" => $tmp["id"],
			"article" => $arItem["model"],
			"price" => $tmp["price"],
			"price_raw" => $tmp['price_raw'], // Себестоимость без конвертации валют
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
			"price_platform_sb" => $price_platform_sb,
			"price_platform_kz" => $price_platform_kz,
			"price_platform_ozkz" => $price_platform_ozkz,
			"price_platform_ozti" => $price_platform_ozti,
			"revenue" => $revenue,
			"revenue_p" => $revenue_p,
			"price_with_rrc" => $tmp["price_with_rrc"],
			"price_platform2" => $price_platform2,
			"price_platform3" => $price_platform3,
			"set_price" => $set_price,
		);
	}

	//убираем из массива позиции с корректными РРЦ
	if($_REQUEST["hide_rrc"] == "Y"){

		foreach($arPrice as $key => $arItem){
			if ($arItem["price_with_rrc"] > 0){

				if($psFilter["price_id"] == "wb"){
					$min = $arItem["b_price"] - $arItem["b_price"] * 1 / 100;
					$max = $arItem["b_price"] + $arItem["b_price"] * 1 / 100;
				}else{
					$min = $arItem["b_price_full"] - $arItem["b_price_full"] * 1 / 100;
					$max = $arItem["b_price_full"] + $arItem["b_price_full"] * 1 / 100;
				}
				if($psFilter["price_id"] == "wbtl"){
					$min = $arItem["b_price"] - $arItem["b_price"] * 1 / 100;
					$max = $arItem["b_price"] + $arItem["b_price"] * 1 / 100;
				}else{
					$min = $arItem["b_price_full"] - $arItem["b_price_full"] * 1 / 100;
					$max = $arItem["b_price_full"] + $arItem["b_price_full"] * 1 / 100;
				}
				//	$arItem["b_price_full"] = $arItem["b_price_full"] * (100 / (100 - $sale_per)) * (100 / (100 - $promo_per));
//prent($min);prent($max);
				if($arItem["price_with_rrc"] >= $min && $arItem["price_with_rrc"] <= $max){
					unset($arPrice[$key]);
					//prent("asd");
				}
			}else{
				unset($arPrice[$key]);
			}
		}
	}
	//prent($arPrice);
	if($_REQUEST["without_competitors"] == "Y"){
		foreach($arPrice as $key => $arItem){
			if ($arItem["price_platform"] > 0){
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
		"price_platform_kz" => SORT_NUMERIC,
		"price_platform_ozkz" => SORT_NUMERIC,
		"price_platform_ozti" => SORT_NUMERIC,
		"revenue" => SORT_NUMERIC,
		"revenue_p" => SORT_NUMERIC,
		"supplier_name" => SORT_STRING,
	);
	//$order = $sort = "";
	if($_REQUEST["order"] != "undefined" && $_REQUEST["sort"] != "undefined"){
		$order = $_REQUEST["order"];
		$sort = $_REQUEST["sort"];
	}else{
		$order = "asc";
		$sort = "revenue_p";
	}
	//prent($order);prent($sort);
	//if($_REQUEST["order"] != "undefined" && $_REQUEST["sort"] != "undefined"){
		if(in_array($order, array("asc", "desc")) && isset($arAvailSort[$sort])){
			$_order = ($order == "asc" ? SORT_ASC : SORT_DESC);
			$_sort = $sort;

			$data = array();
			foreach($arPrice as $key => &$arr){
				$data[$key] = $arr[$_sort];
			}
			unset($arr);
			array_multisort($data, $arAvailSort[$_sort], $_order, $arPrice);

		}
	//}



	$arResult["cnt_1"] = $arResult["cnt_2"] = $arResult["cnt_3"] = $arResult["cnt_4"] = 0;

	$filter_price = false;
	if($_REQUEST["filter_price"] && in_array($_REQUEST["filter_price"], array("cnt_1", "cnt_2", "cnt_3", "cnt_4"))){
		$filter_price = $_REQUEST["filter_price"];
	}

	foreach($arPrice as $key => $arItem){
		if ($arItem["price_platform"] <= 0){
			$arResult["cnt_4"]++;
			$price = "cnt_4";
		}else{
			$min = $max = $arItem["price_platform"];
			if ($arItem["price_platform2"] > 0) $max = $arItem["price_platform2"];
			if ($arItem["price_platform3"] > 0) $max = $arItem["price_platform3"];

			if ($arItem["b_price"] > $max){
				$arResult["cnt_3"]++;
				$price = "cnt_3";
			}else{
				if ($arItem["b_price"] >= $min && $arItem["b_price"] <= $max){
					$arResult["cnt_2"]++;
					$price = "cnt_2";
				}else{
					$arResult["cnt_1"]++;
					$price = "cnt_1";
				}
			}
		}
		if($filter_price && $filter_price != $price){
			unset($arPrice[$key]);
		}
	}

	//prent($cntAll);

	//prent($arPrice);die; || $USER->getID() == 587
	//скачивание xls
	unlink("/var/www/bitrix/data/www/tempusshop.ru/upload/price_analys.xlsx");
	if($_REQUEST["download_xls"] == "Y"){

		require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';

		// $arCol = array(0 => "A", 1 => "B", 2 => "C", 3 => "D", 4 => "E", 5 => "F", 6 => "G", 7 => "H", 8 => "I", 9 => "J", 10 => "K");
		$arCol = range('A', 'Z');
		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);
		$sheet = $objPHPExcel->getActiveSheet();

		$sheet->setTitle("tempus");
		$sheet->getStyle("A:D")->getFont()->setName("Arial");
		$sheet->getStyle("A:D")->getFont()->setSize(10);

		$col_num = 0;
		$sheet->setCellValue("{$arCol[$col_num]}1", "Артикул");$col_num++;
		$sheet->setCellValue("{$arCol[$col_num]}1", "Цена сайта");$col_num++;
		$sheet->setCellValue("{$arCol[$col_num]}1", "По прайсу");$col_num++;

		if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}1", "Мин.");$col_num++;}
		if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}1", "Дельта");$col_num++;}

		$sheet->setCellValue("{$arCol[$col_num]}1", "ВП");$col_num++;
		$sheet->setCellValue("{$arCol[$col_num]}1", "ВП%");$col_num++;
		$sheet->setCellValue("{$arCol[$col_num]}1", "Поставщик");


		$i = 2;

		foreach($arPrice as $key => $arItem){
			$col_num = 0;

			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["article"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["b_price"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price"]);$col_num++;

			if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform"]);$col_num++;}
			if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform_av"]);$col_num++;}
			// if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform_sb"]);$col_num++;}
			// if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform_kz"]);$col_num++;}
			// if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform_ozkz"]);$col_num++;}
			// if($_REQUEST["price_competitors"] == "Y") {$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["price_platform_ozti"]);$col_num++;}

			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["revenue"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arItem["revenue_p"]);$col_num++;
			$sheet->setCellValue("{$arCol[$col_num]}{$i}", $arSupName[$arItem["supplier_id"]]);$col_num++;

			$i++;
		}

		$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$writer->save("/var/www/bitrix/data/www/tempusshop.ru/upload/price_analys.xlsx", __FILE__);

	}

	if($_REQUEST["ajax"] == "Y"){
		$APPLICATION->RestartBuffer();
		//prent($arPrice);
		echo json_encode($arPrice, JSON_UNESCAPED_UNICODE);
		die;
	}

	$cntAll = (is_array($arPrice) ? count($arPrice) : 0);
	$arPrice = array_slice($arPrice, 0, $page_size);


//prent($_REQUEST);
	?>
	<?

	$sum = $arResult["cnt_1"] + $arResult["cnt_2"] + $arResult["cnt_3"] + $arResult["cnt_4"];
	if($sum > 0){
		$arResult["per_1"] = round($arResult["cnt_1"] / $sum * 100, 2);
		$arResult["per_2"] = round($arResult["cnt_2"] / $sum * 100, 2);
		$arResult["per_3"] = round($arResult["cnt_3"] / $sum * 100, 2);
		$arResult["per_4"] = round($arResult["cnt_4"] / $sum * 100, 2);
	}

	//prent($arResult["per_1"]);
	?>
	<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_1"):?>active<?endif?>" data-type="cnt_1">Предложений с минимальной ценой - <span style="color: rgb(42, 173, 46);" ><?=$arResult["cnt_1"]?> - <?=$arResult["per_1"]?>%</span></p>
	<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_2"):?>active<?endif?>" data-type="cnt_2">Предложений с ценой TOP3 - <span ><?=$arResult["cnt_2"]?> - <?=$arResult["per_2"]?>%</span></p>
	<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_3"):?>active<?endif?>" data-type="cnt_3">Предложений с ценой выше TOP3 - <span style="color: rgb(255, 0, 0)" ><?=$arResult["cnt_3"]?> - <?=$arResult["per_3"]?>%</span></p>
	<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_4"):?>active<?endif?>" data-type="cnt_4">Предложений без конкурентов - <span ><?=$arResult["cnt_4"]?> - <?=$arResult["per_4"]?>%</span></p>

	<?if(is_array($arPrice) && count($arPrice) > 0):?>
		<p><a href="#" id="download_xls">Скачать xls</a></p>
	<?endif?>
	<?//if($cntAll != count($arPrice)):?>

		<p>Показаны <?=(is_array($arPrice) ? count($arPrice) : 0)?> из <?=$cntAll?></p>
	<?//endif?>
	<?

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

	switch($psFilter["price_id"]){
		case "ru":
			$colName = "Мин. конкурента";
			break;
		case "by":
			$colName = "Мин. онлайнера";
			break;
		case "kz":
			$colName = "Мин. kz";
			break;
	  case "ozkz":
	    $colName = "Мин. ozkz";
	    break;
		case "ozti":
	    $colName = "Мин. ozti";
	    break;
		case "pl":
			$colName = "Мин. ceneo";
			break;
		case "ya":
			$colName = "Мин. яндекса";
			break;
		case "os":
			$colName = "Мин. os";
			break;
		case "wb":
			$colName = "Мин. WB";
			break;
		case "wbtl":
			$colName = "Мин. WBTL";
			break;
		case "wbby":
			$colName = "Мин. WBBY";
			break;
		case "sb":
			$colName = "Мин. SB";
			break;
		default:
			$colName = "Мин.";
			break;
	}
	?>
	<table class="table">
		<thead>
			<tr>
				<th class="search <?if($sort == "article"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="article" data-order="">Артикул <div class="arrow"></div></th>
				<th class="search <?if($sort == "b_price"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="b_price" data-order="">Цена продажи <div class="arrow"></div></th>
				<th class="search <?if($sort == "price"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="price" data-order="">По прайсу <div class="arrow"></div></th>
				<th class="search c-competitors <?if($sort == "price_platform"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="price_platform" data-order="" style="display:none;"><span><?=$colName?></span> <div class="arrow"></div></th>
				<th class="search c-competitors <?if($sort == "price_platform_av"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="price_platform_av" data-order=""><span>Дельта</span> <div class="arrow"></div></th>
				<th class="search <?if($sort == "revenue"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="revenue" data-order="">Наценка <div class="arrow"></div></th>
				<th class="search <?if($sort == "revenue_p"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="revenue_p" data-order="asc">Наценка, % <div class="arrow"></div></th>
				<th class="search <?if($sort == "supplier_name"):?>active <?if($order == "asc"):?>arrow-top<?else:?>arrow-bottom<?endif?><?endif?>" data-column="supplier_name" data-order="">Поставщик <div class="arrow"></div></th>
				<th>
				<button type="button" class="btn btn-primary" id="all-get-price" style="display: none;">РРЦ</button>
				<button type="button" class="btn btn-primary" id="all-get-price2">РРЦ</button>
				<button type="button" class="btn btn-primary" id="all-get-price-platform" style="display: none;">КЦ</button>
				</th>
			</tr>
		</thead>
		<tbody>
	<?foreach($arPrice as $key => $arItem):?>
		<??>

		<?
		$class = "";
		if($arItem["b_price"] > 0 && $arItem["price_platform"] > 0 && $arItem["b_price"] < $arItem["price_platform"]){
			$p = abs($arItem["b_price"] - $arItem["price_platform"]) * 100 / $arItem["b_price"];
			if($p > 10) $class = "warning";
		}elseif($arItem["price_platform"] > 0 && $arItem["price_platform"] < $arItem["b_price"]){
			$class = "danger";
		}
		?>
		<tr class="tr-priceid-<?=$arItem["id"]?> <?if(!$arItem["b_price"] || $arItem["b_price"] == 0):?>no-price<?endif?> <?=$class?>" data-productid="<?=$arItem["b_id"]?>" data-skuid="<?=$arItem["b_sku_id"]?>" data-bprice="<?=$arItem["b_price"]?>" id="sdftr-priceid-<?=$arItem["id"]?>">
			<td <?if($sort == "article"):?>class="active"<?endif?>>
				<a href="<?=$url?><?=$arItem["b_link"]?>" target="_blank"><?=$arItem["article"]?></a>
			</td>
			<td class="td-price<?if($sort == "b_price"):?> active<?endif?>">
				<input type="text" class="form-control" value="<?=$arItem["b_price"]?>" style="width: 150px;float: left;" name="price[<?=$arItem["id"]?>]"></td>
			<td <?if($sort == "price"):?>class="active"<?endif?>>
				<span style="display:block; margin-top: 10px"><?=$arItem["price"]?></span>
				<p style="font-size: 12px; color: grey; opacity: 0.7"><?=$arItem["price_raw"]?></p>
			</td>
			<td class="c-competitors <?if($sort == "price_platform"):?>active<?endif?>">
				<div style="display: inline-block;">
				<span class="price <?if($arItem["set_price"] == 1):?>active-price<?endif?>"><?=$arItem["price_platform"]?></span>

				<?if($arItem["price_platform2"]):?><span class="price <?if($arItem["set_price"] == 2):?>active-price<?endif?>"><?=$arItem["price_platform2"]?></span><?endif?>
				<?if($arItem["price_platform3"]):?><span class="price <?if($arItem["set_price"] == 3):?>active-price<?endif?>"><?=$arItem["price_platform3"]?></span><?endif?>
				</div>

				<?if($arItem["set_price"] == -1):?><div style="display: inline-block;border-left: 1px solid #000;padding-left: 3px;"><span>Р</span></div><?endif?>

			</td>
			<td class="c-competitors <?if($sort == "price_platform_av"):?>active<?endif?>"><?=$arItem["price_platform_av"]?></td>
			<td <?if($sort == "revenue"):?>class="active"<?endif?>><?=$arItem["revenue"]?></td>
			<td <?if($sort == "revenue_p"):?>class="active"<?endif?>><?=$arItem["revenue_p"]?></td>
			<td <?if($sort == "supplier_name"):?>class="active"<?endif?>><?=$arSupName[$arItem["supplier_id"]]?></td>
			<td>
				<?if($arItem["price"] > 0):?>
				<button type="button" class="btn btn-primary btn-get-price" data-id="<?=$arItem["id"]?>">РРЦ</button>
				<input type="hidden" name="id[<?=$arItem["id"]?>]" value="<?=$arItem["id"]?>">
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
		</tbody>
	</table>
<?else:?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
<?endif?>

<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
