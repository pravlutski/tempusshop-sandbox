<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s3")))
	$website = array($_POST["website"]);
else
	$website = array("s1", "s2", "s3");

global $USER;
$arGroups = $USER->GetUserGroupArray();
if($USER->isAdmin() || in_array(6, $arGroups) || in_array(19, $arGroups)) $arResult["ACCESS"] = true;

?>
<div class="row">
<?
if(!$arResult["ACCESS"]) return;

$start = debug_microtime_float();

// проверяем загружены ли склады. Если склад не получали более 600 секунд, то не показываем.
$arStockCode = ["UPDATE_STOCK_RU","UPDATE_STOCK_BY","UPDATE_STOCK_PL","UPDATE_STOCK_MSK"];
$strSql = "SELECT * FROM ci_options WHERE code IN ('".implode("','",$arStockCode)."')";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$checkSec = 600;
$arError = [];
while ($row = $results->Fetch()){
	$timeStock = strtotime($row["timestamp"]);
	if(time() > ($timeStock + $checkSec)){
		$arError[] = "Склад {$row["code"]} загружен более {$checkSec} секунд назад.";
	}

}
if(count($arError) > 0){
	foreach($arError as $error){
		echo "<p>{$error}</p>";
	}
	die;
}

if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arResult = $arTmp = array();
	$objService = new OrderService;
	$objService->getPropOrderFlg = true;

	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;

	$skipSup = [];
	// список поставщиков у которых отмечена галка Оптовый. их мы пропускаем.
	$tmp = $objSupplier->getList(array("opt_supplier" => "Y"));
	foreach($tmp as $arItem){
		$skipSup[] = $arItem["id"];
	}
	if(is_array($skipSup) && count($skipSup) > 0){
		$add_filter = " AND supplier_id NOT IN ('".implode("','", $skipSup)."')";
	}

	/* массив закупок которые уже добавлены в правую колонку */
	$strSql = "SELECT * FROM ci_purchase WHERE status = 'N' AND active = 'Y'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arPurchase[$row["order_basket_id"]] = $row;
		$arPurchaseSP[$row["supp_id"]][$row["model"]] += 1;
	}

	// список всех поставщиков
	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup){
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
		$arResult["SUPPLIER_SORT"][$arSup["id"]] = $arSup["sort"];
		$settings = json_decode($arSup["settings"], true);
		foreach($settings["brand"] as $k => $v)
			$arResult["SUPP_BRAND_PRIORITY"][$arSup["id"]][$v["id"]] = $v["priority"];
	}
	// максимальное отклонение от цены
	$arResult["PRICE_DEVIATION"] = CProSet::getOption("PRICE_DEVIATION_ORDER");

	$arFilter = array(
		"LID" => $website,
		"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CL"),
		"!CANCELED" => "Y",
	);
	$ar = array();
	$arOrder = $objService->getOrderCache(array("DATE_INSERT" => "ASC"), $arFilter); // список заказов по которым надо проверить закупку

	foreach($arOrder as $key => $arItem){
		foreach($arItem["BASKET"] as $arBasket){
			for($i = 1; $i <= $arBasket["QUANTITY"]; $i++){
				if(!$arPurchase[$arBasket["ID"].".".$i])
					$arResult["ITEMS"][] = array(
						"ID" => $arBasket["ID"],
						"ID_UNIQUE" => $arBasket["ID"].".".$i,
						"PRODUCT_ID" => $arBasket["PRODUCT_ID"],
						"NAME" => $arBasket["NAME"],
						"PRICE" => $arBasket["PRICE"],
						"CURRENCY" => $arBasket["CURRENCY"],
						"SITE_ID" => $arItem["LID"],
						"ORDER_ID" => $arItem["ID"],
						"ORDER_XML_ID" => $arItem["XML_ID"],
						"ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
						"STATUS_ID" => $arItem["STATUS_ID"],
						"DELIVERY_ID" => $arItem["DELIVERY_ID"],
						"DELIVERY_DATE" => $arItem["DELIVERY_DATE"],
						"FROM" => $arItem['FIO']
					);
			}
		}
		$ar[] = $arItem["ID"];
	}

	$arCrmID = $objService->getOrderCrmID(array("ORDER_ID" => $ar));

	// выбираем топ
	$strSql = "SELECT * FROM ci_top_models";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["TOP_LIST"][$row["site_id"]][$row["model"]] = $row["model"];
	}

	unset($arItem);
	foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arItem["SITE_ID"] == "s2" && $arItem["CURRENCY"] == "RUB") $arItem["CURRENCY"] = "BYN";
		if(isset($arItem["CURRENCY"]) && $arItem["CURRENCY"] != "RUB"){
			$currency = $objCurrency->getDetail( $arItem["CURRENCY"] );//курс валюты
			$rate = $currency["rate"];

			$arItem["PRICE"] = round($arItem["PRICE"] * $rate, 2);
		}else{
			$amount = $rate = 1;
		}

		$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $arItem["PRODUCT_ID"]), false, false, array('PROPERTY_CML2_ARTICLE'));
        if ($res = $objRes->GetNext()){
			$arItem["ARTICLE"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];
		}
	}
	unset($arItem);

	$arMinPrice = array();
	$arMinPrice_n = array();

	$strSql = "SELECT * FROM ci_price WHERE supplier_id IN ('47', '44', '71', '82', '103', '116')";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){

		if(isset($arPurchaseSP[$row["supplier_id"]][$row["model"]])){
			$row["count"] = $row["count"] - $arPurchaseSP[$row["supplier_id"]][$row["model"]];
		}

		if($row["count"] > 0){
			//разбиваем т.к. изначально было сделано 1 строка = 1 штука
			if($row["count"] > 1){
				for($i = 1; $i <= $row["count"]; $i++){
					$tmp_row = $row;
					$tmp_row["count"] = 1;

					$arStock[$row["supplier_id"]][$row["model"]][] = $tmp_row;
				}
			}else{
				$arStock[$row["supplier_id"]][$row["model"]][] = $row;
			}

			if(!isset($arMinPrice[$row["model"]]) || $arMinPrice[$row["model"]] > $row["price"]){
				$arMinPrice[$row["model"]] = $row["price"];
			}
			if(!isset($arMinPrice_n[$row["model"]]) || $arMinPrice_n[$row["model"]] > $row["price_n"]){
				$arMinPrice_n[$row["model"]] = $row["price_n"];
			}
		}else{

		}

	}

	//удаляем из склада позиции, которые уже в правом столбце закупок

	/* очищаем от склада */
	foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arItem["ARTICLE"]){

			switch($arItem["SITE_ID"]){
				case "s1":
					$arSup = array(47);
					break;
				case "s2":
					$arSup = array(44);
					break;
				case "s3":
					$arSup = array(71, 82);
					break;
				default:
					$arSup = array(-1);
					break;
			}
			foreach($arSup as $k => $supp_id){
				if(isset($arStock[$supp_id][$arItem["ARTICLE"]]) && is_array($arStock[$supp_id][$arItem["ARTICLE"]]) && count($arStock[$supp_id][$arItem["ARTICLE"]]) > 0){
					unset($arResult["ITEMS"][$key]);

					$key_stock = array_keys($arStock[$supp_id][$arItem["ARTICLE"]]);
					unset($arStock[$supp_id][$arItem["ARTICLE"]][$key_stock[0]]);
					if(is_array($arStock[$supp_id][$arItem["ARTICLE"]]) && count($arStock[$supp_id][$arItem["ARTICLE"]]) == 0){
						unset($arStock[$supp_id][$arItem["ARTICLE"]]);
					}
				}

			}

		}else{
			unset($arResult["ITEMS"][$key]);
		}
	}
	unset($arItem);

	$default_supp = array(47,44,71,82,103,116);

	$arSuppSite = array(47 => "s1",44 => "s2",71 => "s3", 103 => "s1", 82 => "s3", 116 => "s1");

	$add_where = array();

	$arAllArticle = $arAllIDs = array();

	$strSql = "SELECT * FROM ci_price WHERE 1=1 {$add_filter}";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ALL_PRICE"][$row["model"]][] = $row;
	}
	//prent($arResult["ITEMS"]);
	foreach($arResult["ITEMS"] as $key => &$arItem){

		$arr = array();

		foreach($arResult["ALL_PRICE"][$arItem["ARTICLE"]] as $row){

			if($arItem["SITE_ID"] == "s1"){
				if($row["active_ru"] == "N" || ($row["supplier_id"] == 47 && $arStock[47][$arItem["ARTICLE"]])){
					continue;
				}
			}elseif($arItem["SITE_ID"] == "s2"){
				if($row["active_by"] == "N" || ($row["supplier_id"] == 44 && $arStock[44][$arItem["ARTICLE"]])){
					continue;
				}
			}elseif($arItem["SITE_ID"] == "s3"){
				if($row["active_pl"] == "N" || ($row["supplier_id"] == 82 && $arStock[82][$arItem["ARTICLE"]])){
					continue;
				}
			}

			if(in_array($row["supplier_id"], $default_supp)){

				if(!$arStock[$row["supplier_id"]][$row["model"]] || (is_array($arStock[$row["supplier_id"]][$row["model"]]) && count($arStock[$row["supplier_id"]][$row["model"]]) <= 0)){
					continue;
				}else{
					$key_stock = array_keys($arStock[$row["supplier_id"]][$row["model"]]);
					unset($arStock[$row["supplier_id"]][$row["model"]][$key_stock[0]]);
					if(is_array($arStock[$row["supplier_id"]][$row["model"]]) && count($arStock[$row["supplier_id"]][$row["model"]]) == 0){
						unset($arStock[$row["supplier_id"]][$row["model"]]);
					}
				}
			}

			$arr[$row["id"]] = $row;
			if(isset($arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]]))
				$arr[$row["id"]]["priority"] = $arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]];
			elseif(in_array($row["supplier_id"], $default_supp)){
				$arr[$row["id"]]["priority"] = 1;

				if($arItem["SITE_ID"] == "s1"){
					if($row["supplier_id"] == 116){
						$arr[$row["id"]]["priority"] = 0;
					}elseif($row["supplier_id"] == 103){
						$arr[$row["id"]]["priority"] = 1;
					}

				}elseif($arItem["SITE_ID"] == "s2"){
					if($row["supplier_id"] == 116){
						$arr[$row["id"]]["priority"] = 0;
					}elseif($row["supplier_id"] == 103){
						$arr[$row["id"]]["priority"] = 1;
					}elseif($row["supplier_id"] == 47){
						$arr[$row["id"]]["priority"] = 1;
					}
				}elseif($arItem["SITE_ID"] == "s3"){
					if($row["supplier_id"] == 103){
						$arr[$row["id"]]["priority"] = 1;
					}elseif($row["supplier_id"] == 47){
						//$arr[$row["id"]]["priority"] = 0;
					}elseif($row["supplier_id"] == 44){
						$arr[$row["id"]]["priority"] = 0;
					}
				}

			}else
				$arr[$row["id"]]["priority"] = 10;

			if(!isset($arMinPrice[$row["model"]]) || $arMinPrice[$row["model"]] > $row["price"]){
				$arMinPrice[$row["model"]] = $row["price"];
			}

			if(!isset($arMinPrice_n[$row["model"]]) || $arMinPrice_n[$row["model"]] > $row["price_n"]){
				$arMinPrice_n[$row["model"]] = $row["price_n"];
			}

			if(in_array($row["supplier_id"], $default_supp)){
				$arItem["IN_STOCK"] = true;
			}
		}
		 print_r($arr);
		if(is_array($arr) && count($arr) > 0){
			foreach($arr as $k => $v){

				if($arMinPrice[$v["model"]] && $arResult["PRICE_DEVIATION"]){
					$diff = $v["price"] / $arMinPrice[$v["model"]] * 100 - 100;

					//if(($diff <= $arResult["PRICE_DEVIATION"] || in_array($v["supplier_id"], array(44,47,71,103))) && (!$arResult["TOP_LIST"][$arSuppSite[$v["supplier_id"]]][$v["model"]] || $arItem["SITE_ID"] == "s3")){

					if(($diff <= $arResult["PRICE_DEVIATION"] && (!$arResult["TOP_LIST"][$arSuppSite[$v["supplier_id"]]][$v["model"]] || $arItem["SITE_ID"] == "s3")) || in_array($v["supplier_id"], array(44,47,71,103,116))){
						$arItem["DEVIATION"][] = $v;// все подходящие элементы из прайслистов

						$last = $k;
					}
				}

			}

			if(is_array($arItem["DEVIATION"]) && count($arItem["DEVIATION"]) > 0){
				$arItem["DEVIATION"] = sort_nested_arrays($arItem["DEVIATION"], array("priority" => "asc", "price" => "asc"));

				$arItem["OPTIMAL_DEVIATION"] = $arItem["DEVIATION"][0]["id"];// оптимальным считаем самого высокого по приоритету и низкой цене. его выводим как основного для закупки.
			}
			$arItem["PRICELIST"] = $arr;

			$arAllArticle[$arItem["ARTICLE"]] = $arItem["ARTICLE"];

		}else{

			$arr = array();

			$strSql = "SELECT * FROM ci_price WHERE model = '{$arItem["ARTICLE"]}' {$add_filter} ORDER BY store_id asc";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arr[$row["id"]] = $row;
			}

			if(is_array($arr) && count($arr) > 0){
				$arItem["PRICELIST"] = $arr;
			}

			$arItem["NOT_STOCK"] = "Y";
		}

	}

	unset($arItem);

	if(is_array($arAllArticle) && count($arAllArticle) > 0){

		$strSql = "SELECT * FROM ci_price WHERE model IN ('" . implode("','", $arAllArticle) . "') {$add_filter}";// AND id NOT IN ('" . implode("','", $arAllIDs) . "')";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){

			$rsALL[$row["model"]][$row["id"]] = $row;

		}

	}


	$arResult["ITEMS"] = array_reverse($arResult["ITEMS"]);

	$arResult["PRICE"] = array();

	foreach($arResult["ITEMS"] as $key => &$arItem){
		$ar = array();

		$_ar_min = array();
		foreach($arItem["PRICELIST"] as &$arr){

			if(!$_ar_min || $arr["price"] < $_ar_min["price"]){
				$_ar_min = $arr;
			}

			unset($rsALL[$arr["model"]][$arr["id"]]);

		}
		unset($arr);

		if($arItem["OPTIMAL_DEVIATION"] && $arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]] && $_ar_min["id"] != $arItem["OPTIMAL_DEVIATION"]){
			$_ar_min = $arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]];
		}

		$min = $_ar_min["id"];

		if($arItem["PRICELIST"][$min]) {
			$arItem["PRICE_ACTUAL"] = $arItem["PRICELIST"][$min];
			$arItem["PRICE_ACTUAL"]["site_id"] = $arItem["SITE_ID"];
			$arItem["PRICE_ACTUAL"]["status_id"] = $arItem["STATUS_ID"];
			$arItem["PRICE_ACTUAL"]["order_id"] = $arItem["ORDER_ID"];
			$arItem["PRICE_ACTUAL"]["order_xml_id"] = $arItem["ORDER_XML_ID"];
			$arItem["PRICE_ACTUAL"]["order_number_id"] = $arItem["ORDER_NUMBER_ID"];
			$arItem["PRICE_ACTUAL"]["order_basket_id"] = $arItem["ID_UNIQUE"];
			$arItem["PRICE_ACTUAL"]["product_id"] = $arItem["PRODUCT_ID"];

			$arItem["PRICE_ACTUAL"]["delivery_id"] = $arItem["DELIVERY_ID"];
			$arItem["PRICE_ACTUAL"]["delivery_date"] = $arItem["DELIVERY_DATE"];
			$arItem["PRICE_ACTUAL"]["from"] = $arItem["FROM"];


			$arItem["PRICE_ACTUAL"]["price_order"] = round($arItem["PRICE"], 2);
			unset($arItem["PRICELIST"][$min]);
		}
	}
	unset($arItem);

	$arSort = array();

	foreach($arResult["ITEMS"] as $key => $arItem){
		if($arItem["NOT_STOCK"] !== "Y"){

			if($arItem["PRICE_ACTUAL"]){
				$tmp = $arItem["PRICE_ACTUAL"];
				//$arSort[$tmp["supplier_id"]] = $arResult["SUPPLIER_NAME"][$tmp["supplier_id"]];
				$arSort[$tmp["supplier_id"]] = $arResult["SUPPLIER_SORT"][$tmp["supplier_id"]];

				$arResult["PRICE_GROUP"][$tmp["supplier_id"]][] = $arItem["PRICE_ACTUAL"];
			}

			foreach($arItem["PRICELIST"] as $alt){
				$arResult["PRICE_ALTERNATIVE"][$alt["model"]][$alt["id"]] = $alt;
			}

		}else{
			$arResult["PRICE_NO_SUPP"][] = $arItem;
		}

	}

	unset($arItem);

	asort($arSort);
	$tmp = array();
	foreach($arSort as $key => $val){
		$tmp[$key] = $arResult["PRICE_GROUP"][$key];
	}
	$arResult["PRICE_GROUP"] = $tmp;

	//сортируем альтернативные
	$tmp = array();
	foreach($arResult["PRICE_ALTERNATIVE"] as $model => $ar){
		if(is_array($ar) && count($ar) > 1){
			$arSortAlt = array();
			foreach($ar as $code => $arItem){
				$arSortAlt[$code] = $arItem["price"];
			}
			asort($arSortAlt);
			foreach($arSortAlt as $key => $val){
				$tmp[$model][$key] = $arResult["PRICE_ALTERNATIVE"][$model][$key];
			}

		}else{
			$tmp[$model] = $ar;
		}

	}
	$arResult["PRICE_ALTERNATIVE"] = $tmp;


	foreach($arResult["PRICE_GROUP"] as $key => $arItem):?>

		<?$txt = "";?>
		<div class="col-sm-12">
			<table class="table">
				<thead>
					<tr>
						<th style="width: 45%"><span class="btn-clipboard-list" style="cursor:pointer; color: #337ab7;" data-id="textarea-purchase-<?=$key?>" data-clipboard-target="#textarea-purchase-<?=$key?>"><?=$arResult["SUPPLIER_NAME"][$key]?></span></th>
						<th style="width: 25%">Цена</th>
						<th style="width: 5%"></th>
						<th style="width: 5%"></th>
						<th style=""><button type="button" class="btn btn-sm btn-default add-all-purchase" style="width: 90px;padding: 1px;">OK</button></th>
					</tr>
				</thead>
				<tbody>
				<?foreach($arItem as $article => $arPrice):?>

					<?$txt .= $arPrice["model"] . "\r\n";?>
						<tr class="<?if($arPrice["price_order"] && $arPrice["price_order"] < $arPrice["price"]):?>black<?elseif($arPrice["status_id"] == "WT"):?>warning<?endif?> <?if($arResult["TOP_LIST"][$arSuppSite[$arPrice["supplier_id"]]][$arPrice["model"]]):?>danger<?endif?>" data-orderid="<?=$arPrice["order_id"]?>" data-orderbasketid="<?=$arPrice["order_basket_id"]?>" data-productid="<?=$arPrice["product_id"]?>">
							<td><?/*<a href="/bitrix/admin/sale_order_view.php?ID=<?=$arPrice["order_id"]?>&lang=ru&filter=Y&set_filter=Y" target="_blank"><?=$arPrice["model"]?></a>*/?><?=$arPrice["model"]?></td>
							<td><?=$arPrice["price"]?></td>
							<td>
								<? $t = "";?>

								<?if(strripos($arPrice["delivery_id"], "sdek") !== false):?>
									<? $t .= '<span class="badge" style="font-size: 8px;position: absolute;padding: 3px 3px 2px 2px;top: -11px;right: -15px;">СДЭК</span>';?>
								<?endif?>
								<?if(strripos($arPrice["from"], "ozon") !== false):?>
									<? $t .= '<span class="badge" style="font-size: 8px;position: absolute;padding: 3px 3px 2px 2px;top: -11px;right: -15px;">OZON</span>';?>
								<?endif?>
								<?if(strripos($arPrice["order_xml_id"], "YAMARKET") !== false):?>
									<?
									$tmp = explode("_", $arPrice["order_xml_id"]);
									if($tmp[1] == 4){



										if(date("d.m.Y") == $arPrice["delivery_date"]){
											$fbs = "FBS0";
										}elseif(date('d.m.Y', strtotime("+1 days")) == $arPrice["delivery_date"]){
											$fbs = "FBS1";
										}else{
											$cnt_day = (time() - strtotime($arPrice["delivery_date"])) / (60 * 60 * 24);
											$cnt_day = ceil(abs($cnt_day));
											$fbs = "FBS{$cnt_day}";
										}

										$t .= "<span class='badge' style='font-size: 8px;position: absolute;padding: 3px 3px 2px 2px;top: -11px;right: -15px;'>{$fbs}</span>";
									}
									?>
								<?endif?>

								<?if(in_array($arPrice["delivery_id"], array(1, 25)) && $arPrice["delivery_date"]):?>
									<?if(date("d.m.Y") == $arPrice["delivery_date"]):?><? $t .= '<span class="badge" style="font-size: 8px;position: absolute;padding: 3px 3px 2px 2px;top: -6px;right: -15px;">0Д</span>';?><?endif?>
									<?if(date('d.m.Y', strtotime("+1 days")) == $arPrice["delivery_date"]):?><? $t .= '<span class="badge" style="font-size: 8px;position: absolute;padding: 3px 3px 2px 2px;top: -6px;right: -15px;">1Д</span>';?><?endif?>
								<?endif?>

								<?if( $arPrice["order_number_id"] > 0 ):?>
									<a href="https://tempusshop.ru/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&%3Bset_filter=Y&lang=ru&ID=<?=$arPrice["order_number_id"]?>" target="_blank" style="position: relative;"><span><?=$arPrice["order_number_id"]?></span><?=$t?></a>
								<?else:?>
									<?=$arPrice["order_number_id"]?>
								<?endif?>

							</td>
							<td><?=$arPrice["site_id"]?></td>
							<td class="right">
							<?
							$tmpID = array();
							?>
							<div class="btn-group main-item">
								<button type="button" class="btn btn-sm btn-default add-purchase" data-id="<?=$arPrice["id"]?>">OK</button>

								<?if(isset($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) && is_array($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) && count($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) > 0):?>
								<button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" <?if(!isset($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]])):?>disabled<?endif?>>
									<span class="caret"></span>
									<span class="sr-only">Toggle Dropdown</span>
								</button>
								<ul class="dropdown-menu">
									<?
									$arPriceAlt = array_values($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]);

									//$min = $arPriceAlt[0]["price"];
									$min = $arMinPrice[$arPrice["model"]];

									?>
									<?//foreach($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]] as $arAlt):?>
									<?foreach($arPriceAlt as $key_alt => $arAlt):?>
									<?
										if($arAlt["id"] == $arPrice["id"]) continue;
										$tmpID[] = $arAlt["id"];
									$class = "";
									if($key_alt >= 0){
										$diff = $arAlt["price"] / $min * 100 - 100;
										if($diff <= 3){
											$class = "btn-success";
										}elseif($diff <= 5){
											$class = "btn-warning";
										}elseif($diff <= 10){
											$class = "btn-danger";
										}else{
											$class = "btn-dark";
										}
									}
									?>
									<li class="<?=$class?>"><a href="#" class="add-purchase" data-id="<?=$arAlt["id"]?>"><?=$arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]]?> - <?=$arAlt["price"]?></a></li>
									<?endforeach?>

								</ul>
								<?endif?>
							</div>
							<div class="btn-group" style="float:right;">
								<?//print_r(count($rsALL[$arPrice["model"]]));?>
								<?//if(($arPrice["price_order"] && $arPrice["price_order"] < $arPrice["price"] || in_array($arPrice["supplier_id"], array(44, 47, 74, 103, 116))) && (isset($rsALL[$arPrice["model"]]) && is_array($rsALL[$arPrice["model"]]) && count($rsALL[$arPrice["model"]]) > 0)):?>
								<?if((isset($rsALL[$arPrice["model"]]) && is_array($rsALL[$arPrice["model"]]) && count($rsALL[$arPrice["model"]]) > 0)):?>
								<button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">D</button>
								<ul class="dropdown-menu">
									<?
									//$arM = $rsALL[$arPrice["model"]];
									$list = sort_nested_arrays($rsALL[$arPrice["model"]], array("price" => "asc"), true);
									foreach($list as $key_alt => $arAlt):?>
										<?if($arAlt["id"] == $arPrice["id"] || in_array($arAlt["id"], $tmpID)) continue;?>
										<li class="<?//=$class?>"><a href="#" class="add-purchase" data-id="<?=$arAlt["id"]?>"><?=$arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]]?> - <?=$arAlt["price"]?></a></li>
									<?endforeach?>
								</ul>
								<?endif?>
							</div>

							</td>
						</tr>

				<?endforeach?>
				</tbody>
			</table>
		</div>
		<textarea id="textarea-purchase-<?=$key?>" style="position: fixed;left: -9999px;display:none;"><?=$txt?></textarea>

	<?endforeach?>
		<script>
			//new Clipboard('.btn-clipboard'); // Не забываем инициализировать библиотеку на нашей кнопке
		</script>
	<?if(is_array($arResult["PRICE_NO_SUPP"]) && count($arResult["PRICE_NO_SUPP"]) > 0):?>
		<div class="col-sm-12">
			<table class="table">
				<thead>
					<tr>
						<th style="width: 45%">Нет у поставщиков</th>
						<th style="width: 30%">Цена</th>
						<th style="width: 5%"></th>
						<th style="width: 20%"></th>
					</tr>
				</thead>
				<?foreach($arResult["PRICE_NO_SUPP"] as $key => $arPrice):?>
					<tbody>
						<?/*<tr class="<?if($arPrice["STATUS_ID"] == "N"):?>danger<?endif?>" data-id="<?=$arPrice["id"]?>" id="tbl-<?=$arPrice["id"]?>">*/?>
						<tr class="<?if($arPrice["STATUS_ID"] == "N"):?>danger<?endif?>" data-orderid="<?=$arPrice["PRICE_ACTUAL"]["order_id"]?>" data-orderbasketid="<?=$arPrice["PRICE_ACTUAL"]["order_basket_id"]?>" data-productid="<?=$arPrice["PRICE_ACTUAL"]["product_id"]?>">
							<td><a href="/bitrix/admin/sale_order_view.php?ID=<?=$arPrice["ORDER_ID"]?>&lang=ru&filter=Y&set_filter=Y" target="_blank"><?if($arPrice["ARTICLE"]):?><?=$arPrice["ARTICLE"]?><?else:?><?=$arPrice["NAME"]?><?endif?></a></td>
							<td><?=$arPrice["PRICE"]?></td>
							<td class="right"><?if($arPrice["ORDER_ID"] > 0):?><a href="https://tempusshop.retailcrm.ru/orders/<?=$arCrmID[$arPrice["ORDER_ID"]]?>/edit" target="_blank" style="position: relative;"><span><?=$arPrice["ORDER_NUMBER_ID"]?></span></a><?else:?><?=$arPrice["ORDER_NUMBER_ID"]?><?endif?></td>
							<td class="right">
							<?if($arPrice["PRICE_ACTUAL"]):?>
							<div class="btn-group">
								<button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">D</button>
								<ul class="dropdown-menu">
									<li ><a href="#" class="add-purchase" data-id="<?=$arPrice["PRICE_ACTUAL"]["id"]?>"><?=$arResult["SUPPLIER_NAME"][$arPrice["PRICE_ACTUAL"]["supplier_id"]]?> - <?=$arPrice["PRICE_ACTUAL"]["price"]?></a></li>
									<?
									//prent($arPrice["PRICELIST"]);
									$list = sort_nested_arrays($arPrice["PRICELIST"], array("price" => "asc"), true);
									?>
									<?foreach($list as $key_alt => $arAlt):?>
									<li ><a href="#" class="add-purchase" data-id="<?=$arAlt["id"]?>"><?=$arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]]?> - <?=$arAlt["price"]?></a></li>
									<?endforeach?>

								</ul>
							</div>
							<?endif?>
							</td>
						</tr>
					</tbody>
				<?endforeach?>
			</table>
		</div>
	<?endif?>

	<a href="/admin/ajax/purchase/get_list_order_stock_excel.php?site_id=<?=addslashes($_POST["website"])?>">Список отгрузкок</a>
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

}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}

$end = debug_microtime_float();
prent("end - " . ($end - $start), 0, 1);

?>
</div>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
