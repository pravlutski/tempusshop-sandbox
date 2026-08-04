<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s3")))
	$website = $_POST["website"];

?>
<?if(!in_array($website, array("s1", "s2", "s3"))):?>
	<p>Выберите сайт</p>
	<?die;?>
<?endif?>
<?

$start = debug_microtime_float();

if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arResult = $arTmp = array();
	$objService = new OrderService;
	$objSupplier = new CPanelSupplier;
	/* массив закупок которые уже добавлены в правую колонку */
	$strSql = "SELECT * FROM ci_purchase WHERE status = 'T' AND active = 'Y' AND site_id = '".$DB->ForSql($website)."'";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		//$arPurchase[$row["model"]] = $row;
		if($row["top_id"] > 0){
			$arPurchase[$row["top_id"]] = $row;
			$arPurchaseArt[$row["model"]] += 1;
		}
	}

	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup){
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
		$settings = json_decode($arSup["settings"], true);//prent($settings);
		foreach($settings["brand"] as $k => $v)
			$arResult["SUPP_BRAND_PRIORITY"][$arSup["id"]][$v["id"]] = $v["priority"];
	}
	
	$arResult["PRICE_DEVIATION"] = CProSet::getOption("PRICE_DEVIATION_TOP");
	
	$arArtIDs = array();
	$arArt = array();
	$strSql = "SELECT * FROM ci_top_models WHERE site_id = '".$DB->ForSql($website)."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		//if(!$arPurchase[$row["model"]])
		if(!$arPurchase[$row["id"]]){
			$arResult["ITEMS"][] = array(
				"ID" => $row["id"],
				"ARTICLE" => $row["model"],
				"BITRIX_ID" => $row["bitrix_id"],
			);
			$arArt[$row["model"]] = $row["model"];
			$arArtIDs[$row["bitrix_id"]] = $row["model"];
		}
	}

	//$supp_id = ($website == "s1" ? 47 : ("s2" ? 44 : -1));//44 - минск, 47 - москва, 71 - польша
	switch($website){
		case "s1":
			$supp_id = 47;
			break;
		case "s2":
			$supp_id = 44;
			break;
		case "s3":
			$supp_id = 71;
			break;
		default:
			$supp_id = -1;
			break;
	}
	
	$arStock = array();
	if(count($arArt) > 0){
		//$strSql = "SELECT model, COUNT(model) as CNT FROM ci_price WHERE model IN ('".implode("','",$arArt)."') AND supplier_id = '{$supp_id}' GROUP BY model";// AND supplier_id <> '{$supp_id}'";
		$strSql = "SELECT model, SUM(count) as CNT FROM ci_price WHERE model IN ('".implode("','",$arArt)."') AND supplier_id = '".$DB->ForSql($supp_id)."' GROUP BY model";// AND supplier_id <> '{$supp_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arStock[$row["model"]] = $row["CNT"];
		}
	}

	$arTop = array();
	if(count($arArt) > 0){
		$strSql = "SELECT model, COUNT(model) as CNT FROM ci_top_models WHERE model IN ('".implode("','",$arArt)."') AND site_id = '".$DB->ForSql($website)."' GROUP BY model";// AND supplier_id <> '{$supp_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arTop[$row["model"]] = $row["CNT"];
		}
	}
	
	//add 19.06.2019
	//очищаем ТОП от того что уже есть в закупках
	foreach($arTop as $model => $cnt){
		if(isset($arPurchaseArt[$model])){
			$arTop[$model] -= $arPurchaseArt[$model];
		}
	}
	
	if(count($arArt) > 0){
		$strSql = "SELECT * FROM ci_price WHERE model IN ('".implode("','",$arArt)."')";// AND supplier_id <> '{$supp_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		
		$arPrice = array();
		while ($row = $results->Fetch()){
			$arPrice[$row["model"]][] = $row;
		}
	}
	$arMinPrice = array();
	
	/*
1) При формировании отчета по ЗАПАСАМ, в перемещение https://yadi.sk/i/pEe5XbZ_kDhV_g попадают даже те модели, на которые есть активные заказы на сайте склада. 

Нужно внести правку: не выводить на перемещение (а закупать) модель, если в разделе "готово к отгрузке" модель встречается больше или столько же раз, сколько штук на складе (другими словами, на модель со склада Москва есть заказы на ру сайте)

ПРИМЕР

В минском топе есть AE-1200WH-1A и она закончилась в Минске. Формируем отчет по запасам и видим, что эту модель предлагается переместить из Склад Москва. Эта модель на Склад Модель одна и на нее есть активный заказ (модель есть в списке "готов к отгруке"), значит модель должна распределиться к поставщику, а не выводиться на перемещение.
	*/
	
	$arFilter = array(
		"STATUS_ID" => array("CO", "WT", "PO", "SE", "TA", "CR", "CL"),
		"!CANCELED" => "Y",
	);
	$ar = array();
	$arOrder = $objService->getOrderCache(array("DATE_INSERT" => "ASC"), $arFilter);
	
	foreach($arOrder as $key => $arItem){
		foreach($arItem["BASKET"] as $arBasket){
			if($arArtIDs[$arBasket["PRODUCT_ID"]])
				$arResult["ORDER_ITEMS"][$arItem["LID"]][$arArtIDs[$arBasket["PRODUCT_ID"]]] += $arBasket["QUANTITY"];
		}
	}
	
	$arSuppSite = array(44 => "s2", 47 => "s1", 74 => "s2",);
	foreach($arPrice as $key => &$arItem){
		foreach($arItem as $k => $item){
			if($arSuppSite[$item["supplier_id"]] && $arResult["ORDER_ITEMS"][$arSuppSite[$item["supplier_id"]]][$item["model"]] > 0){
				$cntOrder = $arResult["ORDER_ITEMS"][$arSuppSite[$item["supplier_id"]]][$item["model"]];
				
				$arPrice[$key][$k]["count"] = $item["count"] - $cntOrder;
				
				if($arPrice[$key][$k]["count"] <= 0) {
					//prent($item);
					unset($arPrice[$key][$k]);
				}
				//prent($item);
				//prent($cntOrder);
				//die;
			}
		}
	}
	unset($arItem);
	//prent($arPrice);
	//prent($arResult["ORDER_ITEMS"]);
	/* end */
	
	
	foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arPrice[$arItem["ARTICLE"]]){
			$arr = array();
			
			$avail = false;
			$arTmp = array();
			foreach($arPrice[$arItem["ARTICLE"]] as $k => $row){
				$arr[$row["id"]] = $row;
				if(isset($arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]]))
					$arr[$row["id"]]["priority"] = $arResult["SUPP_BRAND_PRIORITY"][$row["supplier_id"]][$row["brand_id"]];
				
				$arr[$row["id"]]["top_id"] = $arItem["ID"];
				$arr[$row["id"]]["product_id"] = $arItem["BITRIX_ID"];
				//если есть на складе, то не выводим совсем
				if($row["supplier_id"] == $supp_id){

					if(isset($arStock[$row["model"]]) && isset($arTop[$row["model"]]) && $arTop[$row["model"]] <= $arStock[$row["model"]] && !isset($arTmp[$row["model"]])){
						//$arStock[$row["model"]]--;
						//if(!isset($arTmp[$row["model"]]))
							$avail = true;
					}else{
						
						//prent($row["model"]);
						//if($arTop[$row["model"]] > 0)
						
						if(!isset($arTmp[$row["model"]]))
							$arTop[$row["model"]]--;
						if($arTop[$row["model"]] >= $arStock[$row["model"]]) {
							$arTmp[$row["model"]] = $row["model"];
						//	prent("sdf");
						}
						
						//убираем из массива склад
						foreach($arr as $k => $a){
							if($a["supplier_id"] == $supp_id) unset($arr[$k]);
						}
						
						//break;
					}
//					&& $arStock[$row["model"]] > 0
					// && $arTop[$row["model"]] > $arStock[$row["model"]]
					//$arStock[$row["model"]] >= $arTop[$row["model"]]
					
					//$avail = true;
					//$arStock[$row["model"]]--;
				}
						
				if(!isset($arMinPrice[$row["model"]]) || $arMinPrice[$row["model"]] > $row["price"]){
					$arMinPrice[$row["model"]] = $row["price"];
				}
			}
			
			if($avail === false){
				
				foreach($arr as $k => $v){
					if($arMinPrice[$v["model"]] && $arResult["PRICE_DEVIATION"]){
						$diff = $v["price"] / $arMinPrice[$v["model"]] * 100 - 100;

						if($diff <= $arResult["PRICE_DEVIATION"]){
							$arItem["DEVIATION"][] = $v;
							$last = $k;
						}
					}
				}
				
				if(count($arItem["DEVIATION"]) > 0){
					$arItem["DEVIATION"] = sort_nested_arrays($arItem["DEVIATION"], array("priority" => "asc", "price" => "asc"));
					$arItem["OPTIMAL_DEVIATION"] = $arItem["DEVIATION"][0]["id"];
				}
				
				$arItem["PRICELIST"] = $arr;
			}else
				$arItem["IN_STOCK"] = "Y";
		}
	}
	
	unset($arItem);
	//prent($arResult["ITEMS"]);

	$arResult["PRICE"] = array();
	//prent($arResult["ITEMS"]);
	foreach($arResult["ITEMS"] as $key => &$arItem){
		$ar = array();
		$_ar_min = array();
		foreach($arItem["PRICELIST"] as $arr){
			//if(!$_ar_min["priority"] || ($arr["priority"] < $_ar_min["priority"]) || ($arr["priority"] == $_ar_min["priority"] && $arr["price"] < $_ar_min["price"])){
			if(!$_ar_min || $arr["price"] < $_ar_min["price"]){	
				$_ar_min = $arr;
			}
		}
		
		if($arItem["OPTIMAL_DEVIATION"] && $arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]] && $_ar_min["id"] != $arItem["OPTIMAL_DEVIATION"]){
			$_ar_min = $arItem["PRICELIST"][$arItem["OPTIMAL_DEVIATION"]];
		}
		
//		$min = array_keys($ar, min($ar))[0];
		$min = $_ar_min["id"];
		if($arItem["PRICELIST"][$min]) {
			$arItem["PRICE_ACTUAL"] = $arItem["PRICELIST"][$min];
			unset($arItem["PRICELIST"][$min]);
		}
		//
	}
//$end = debug_microtime_float();
//prent($end - $start, 0, 1);
	unset($arItem);
	$arSort = array();
	foreach($arResult["ITEMS"] as $key => $arItem){
		if($arItem["PRICE_ACTUAL"]){
			$tmp = $arItem["PRICE_ACTUAL"];
			//$arSort[$tmp["supplier_id"]] = $tmp["supplier_id"];
			$arSort[$tmp["supplier_id"]] = $arResult["SUPPLIER_NAME"][$tmp["supplier_id"]];
			$arResult["PRICE_GROUP"][$tmp["supplier_id"]][] = $arItem["PRICE_ACTUAL"];
		}elseif($arItem["IN_STOCK"] != "Y"){
			$arResult["PRICE_NO_SUPP"][] = $arItem;
		}
		foreach($arItem["PRICELIST"] as $alt){
			$arResult["PRICE_ALTERNATIVE"][$alt["model"]][$alt["id"]] = $alt;
		}
		
	}
	unset($arItem);
	asort($arSort);
	$tmp = array();
	foreach($arSort as $key => $val){
		$tmp[$key] = $arResult["PRICE_GROUP"][$key];
	}
	$arResult["PRICE_GROUP"] = $tmp;
	//prent($arResult["PRICE_GROUP"]);
	//сортируем альтернативные
	$tmp = array();
	foreach($arResult["PRICE_ALTERNATIVE"] as $model => $ar){
		if(count($ar) > 1){
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
	//prent($arResult["PRICE_GROUP"]);
	foreach($arResult["PRICE_GROUP"] as $key => $arItem):?>
		<?$txt = "";?>
		<div class="col-sm-12">
			<table class="table">
				<thead>
					<tr> 
						<th style="width: 50%"><span class="btn-clipboard" style="cursor:pointer; color: #337ab7;" data-clipboard-target="#textarea-purchase-<?=$key?>"><?=$arResult["SUPPLIER_NAME"][$key]?></span></th>
						<th style="width: 25%">Цена</th>
						<th style="width: 25%"><button type="button" class="btn btn-sm btn-default add-all-purchase-top" style="width: 100%;padding: 1px;">OK</button></th>
					</tr>
				</thead>
				<tbody>
				<?foreach($arItem as $article => $arPrice):?>
					<?$txt .= $arPrice["model"] . "\r\n";?>
						<tr>
							<td><?=$arPrice["model"]?></td>
							<td><?=$arPrice["price"]?></td>
							<td class="right">
							<div class="btn-group"> 
								<button type="button" class="btn btn-default add-purchase-top" data-id="<?=$arPrice["id"]?>" data-topid="<?=$arPrice["top_id"]?>" data-productid="<?=$arPrice["product_id"]?>">OK</button> 
								<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" <?if(!isset($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]])):?>disabled<?endif?>> 
									<span class="caret"></span> 
									<span class="sr-only">Toggle Dropdown</span> 
								</button> 
								<?if(isset($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) && count($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]) > 0):?>
								<ul class="dropdown-menu"> 
									<?
									$arPriceAlt = array_values($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]]);
									
									//$min = $arPriceAlt[0]["price"];
									$min = $arMinPrice[$arPrice["model"]];
									
									?>
									<?//foreach($arResult["PRICE_ALTERNATIVE"][$arPrice["model"]] as $arAlt):?>
									<?foreach($arPriceAlt as $key_alt => $arAlt):?>
									<?
									$class = "";
									if($key_alt >= 0){
										$diff = $arAlt["price"] / $min * 100 - 100;
										//prent($diff);
										if($diff <= 5){
											$class = "btn-success";
										}elseif($diff <= 10){
											$class = "btn-warning";
										}else{
											$class = "btn-danger";
										}
									}
									?>
									<li class="<?=$class?>"><a href="#" class="add-purchase-top" data-id="<?=$arAlt["id"]?>" data-topid="<?=$arAlt["top_id"]?>" data-productid="<?=$arAlt["product_id"]?>"><?=$arResult["SUPPLIER_NAME"][$arAlt["supplier_id"]]?> - <?=$arAlt["price"]?></a></li>
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
		<textarea id="textarea-purchase-<?=$key?>" style="position: fixed;left: -9999px;"><?=$txt?></textarea>

	<?endforeach?>
		<script>
			new Clipboard('.btn-clipboard'); // Не забываем инициализировать библиотеку на нашей кнопке
		</script>
	<?if(count($arResult["PRICE_NO_SUPP"]) > 0):?>
		<div class="col-sm-12">
			<table class="table">
				<thead>
					<tr> 
						<th style="width: 50%">Нет у поставщиков</th>
						<th style="width: 30%">Цена</th>
						<th style="width: 20%"></th>
					</tr>
				</thead>
				<?foreach($arResult["PRICE_NO_SUPP"] as $key => $arPrice):?>
					<tbody>
						<tr data-id="<?=$arPrice["id"]?>" id="tbl-<?=$arPrice["id"]?>">
							<td><?if($arPrice["ARTICLE"]):?><?=$arPrice["ARTICLE"]?><?else:?><?=$arPrice["NAME"]?><?endif?></td>
							<td><?=$arPrice["PRICE"]?></td>
							<td class="right">
							</td>
						</tr>
					</tbody>
				<?endforeach?>
			</table>
		</div>
	<?endif?>
	<?
	//prent($price);

}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}

$end = debug_microtime_float() - $start;
prent("Время - " . $end, 0, 1);

?>
</div>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');