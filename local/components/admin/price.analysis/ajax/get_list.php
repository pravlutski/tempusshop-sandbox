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

$objSupplier = new CPanelSupplier;

$service = PanelManager::getPriceManager();
$arResult["TYPE_PRICES"] = $service->getTypePrices(); 
$arResult["TYPE_PRICES_ID"] = array_column($arResult["TYPE_PRICES"], 'id');

if(isset($_REQUEST["website"]) && in_array($_REQUEST["website"], $arResult["TYPE_PRICES_ID"])) {
	$psFilter["price_id"] = strtolower($_REQUEST["website"]);
	$selectedPriceType = $_REQUEST["website"];
}

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

$selectedSupplier = $psFilter["supplier_id"];

// костылем ищем сразу все модели в ci_price_catalog
if(isset($_REQUEST["search_text"]) && strlen($_REQUEST["search_text"]) > 3 && !$_REQUEST["article"]) {
	$article = trim(addslashes($_REQUEST["search_text"]));
	$strSql = "SELECT model FROM ci_price_catalog WHERE model LIKE '%" . addslashes($article) . "%' GROUP BY model";
	$articleList = [];
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$articleList[] = $row['model'];
	}
	$psFilter["article"] = $articleList;
}

if(isset($_REQUEST["article"]) && $_REQUEST["article"] != "null") {
	$psFilter["article"] = explode(",", $_REQUEST["article"]);
}

$price_from = $price_to = 0;
if(
	isset($_REQUEST["search_price_from"]) && $_REQUEST["search_price_from"] > 0
) {
	$price_from = (float)$_REQUEST["search_price_from"];
}

if(isset($_REQUEST["search_price_to"]) && $_REQUEST["search_price_to"] > 0){
	$price_to = (float)$_REQUEST["search_price_to"];
}

if($_REQUEST["remove_duplicates"] != "Y"){
	//if($price_from > 0) $psFilter["price_from"] = $price_from * $arSettings["rate"];
	//if($price_to > 0) $psFilter["price_to"] = $price_to * $arSettings["rate"];
}else{
	unset($psFilter["supplier_id"]);
}

if ($_REQUEST["only_active"] == "N") {
	unset($psFilter["price_id"]);
}

?>
<?if(!in_array($selectedPriceType, $arResult["TYPE_PRICES_ID"])):?>
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
$tmp = $objSupplier->getList();
$arSupName = array();
foreach ($tmp as $arItem) {
	$arSupName[$arItem["id"]] = $arItem["name"];
}

$servicePrice = $service->updatePriceService($selectedPriceType, 'analys');
$config = $servicePrice->market->getConfig();	

/*
if ($_REQUEST['article']) {
	$tmpArticle = explode("\r\n", $_REQUEST['article']);
	//prent($tmpArticle);die;
	//prent($_REQUEST['article']);
	//$tmpArticle = trim($_REQUEST['article']);
	$filter = [
		'article' => '1792027'
	];
	$servicePrice->market->setPriceFilter($filter);
	
	
}
$filter = [
	//'article' => 'T129.410.11.053.00'
	'article' => 'GD-100-1B'
];

;*/

$servicePrice->market->setPriceFilter($psFilter);

//if ($_REQUEST['only_view']) {
//	$servicePrice->market->setOption('only_view', true);
//}
$servicePrice->market->setOption('need_price_rrc', true);

//if ($_REQUEST['margin_platform']) {
//	$servicePrice->market->setOption('global_margin', -intval($_REQUEST['margin_platform']));
//}

$prices = $servicePrice->analysPrices();
//prent($prices);
$arPricePlatform = $_REQUEST["price_competitors"] == "Y" ? $prices['competitorPrices'] : [];



	foreach($tmpPrice as $key => $arItem){
		if(($price_from > 0 && $arItem["price"] < $price_from) || ($price_to > 0 && $arItem["price"] > $price_to) ||
		 !in_array($arItem["supplier_id"], $psFilter["supplier_id"])){
			unset($tmpPrice[$key]);
		}
	}

$arPrice = [];
$arCatalogPrice = $prices['data']['catalog_prices'] ?? [];
$minSupplierPrices = $prices['minSupplierPrices'] ?? [];
// то что расчитали
$calculatedPrices = $prices['calculatedPrices'] ?? [];

foreach($arCatalogPrice as $key => $arItem){
	$supplierPrice = $minSupplierPrices[$arItem["model"]];
	if (!$supplierPrice) continue;
	$calculatedPrice = $calculatedPrices[$arItem["model"]];

	if ($_REQUEST["price"] == "discount") {
		$b_price = $arItem[$config['discount_price_key']];
	} else {
		$b_price = $arItem[$config['discount_price_key']];
	}
	
	//if($psFilter["price_id"] == "wb" || $psFilter["price_id"] == "wbtl"){
	if ($config['sale_per'] > 0 || $config['promo_per'] > 0) {
		$b_price = $b_price / 100 * (100 - $config['sale_per']) / (100 / (100 - $config['promo_per']));
	}

	$b_full_price = $arItem[$config['discount_price_key']];

	$revenue_p = 0;
	$revenue = round($b_price - $supplierPrice["price"], 2);
	if ($supplierPrice["price"] > 0) {
		$revenue_p = ($revenue / $supplierPrice["price"]) * 100;
		$revenue_p = round($revenue_p, 2);
	}
	
	//if ($calculatedPrice["price_level"] == 0) {
	//	$supplierPrice["price_rrc"] = round($calculatedPrice["price_level"]['product']['price'], 2);
	//} else {
	//	$supplierPrice["price_rrc"] = 0;
	//}
	
	$price_platform = ($arPricePlatform[$arItem["model"]]["minPrice"] ? $arPricePlatform[$arItem["model"]]["minPrice"] : 0);
	$price_platform2 = ($arPricePlatform[$arItem["model"]]["minPrice2"] ? $arPricePlatform[$arItem["model"]]["minPrice2"] : 0);
	$price_platform3 = ($arPricePlatform[$arItem["model"]]["minPrice3"] ? $arPricePlatform[$arItem["model"]]["minPrice3"] : 0);

	if ($_REQUEST["price_competitors_act"] == "Y" && $price_platform === 0) continue;
	
	$price_platform_av = $b_price - $price_platform;
	$price_platform_av = round($price_platform_av, 2);

	$set_price = $arItem[$key_set_price];
		
	if($_REQUEST["remove_duplicates"] == "Y"){
		if (!in_array($supplierPrice["supplier_id"], $selectedSupplier)) continue;
	}else{
		//unset($psFilter["supplier_id"]);
	}
	//prent($supplierPrice); 
	$arPrice[] = array(
		"id" => $supplierPrice["id"],
		"article" => $arItem["model"],
		"price" => $supplierPrice["price"],
		"price_raw" => $supplierPrice['price_raw'], // Себестоимость без конвертации валют
		"price_rrc" => $calculatedPrice["price_rrc"], // ррц который использовался
		"price_competitor" => $calculatedPrice["price_competitor"], // цена конкурента которую установили
		"supplier_id" => $supplierPrice["supplier_id"],
		"brand_id" => $supplierPrice["brand_id"],
		"supplier_name" => $supplierPrice["supplier_name"] ? $supplierPrice["supplier_name"] : $servicePrice->market->suppliers[$supplierPrice["supplier_id"]]['name'],
		"b_id" => $arItem["product_id"],//ID битрикс
		"b_link" => $arItem["detail_page_url"],
		"b_price" => $b_price, //цена битрикс
		"b_price_full" => $b_full_price,
		"revenue" => $revenue,
		"revenue_p" => $revenue_p,
		"price_platform" => $price_platform,
		"price_platform2" => $price_platform2,
		"price_platform3" => $price_platform3,
		"price_platform_av" => $price_platform_av,
		"set_price" => $set_price,
	);
}
//
//prent($arPrice);


/*if ($price_from > 0 || $price_to > 0) {
	foreach ($arPrice as $key => $arItem) {
		if ($arItem['b_price'] < $price_from || $arItem['b_price'] > $price_to) {
			unset($arPrice[$key]);
		}
	}
}*/



//убираем из массива позиции с корректными РРЦ
if ($_REQUEST["hide_rrc"] == "Y") {

	foreach ($arPrice as $key => $arItem) {
		if ($arItem["price_rrc"] > 0){

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

			if($arItem["price_rrc"] >= $min && $arItem["price_rrc"] <= $max){
				unset($arPrice[$key]);
			}
		}else{
			unset($arPrice[$key]);
		}
	}
}

if($_REQUEST["without_competitors"] == "Y"){
	foreach($arPrice as $key => $arItem){
		if ($arItem["price_platform"] > 0){
			unset($arPrice[$key]);
		}
	}
}

/*
		$arPrice[] = array(
			"id" => $tmp["id"],
			"article" => $arItem["model"],
			"price" => $tmp["price"],
			"price_raw" => $tmp['price_raw'], // Себестоимость без конвертации валют
			"supplier_id" => $tmp["supplier_id"],
			"brand_id" => $tmp["brand_id"],
			"supplier_name" => $arSupName[$tmp["supplier_id"]],
			"b_id" => $arItem["product_id"],//ID битрикс
			"b_link" => $arItem["detail_page_url"],
			"b_price" => $b_price,//цена битрикс
			"b_price_full" => $b_full_price,
			"price_platform" => $price_platform,
			"price_platform_av" => $price_platform_av,
			"revenue" => $revenue,
			"revenue_p" => $revenue_p,
			"price_rrc" => $tmp["price_rrc"],
			"price_platform2" => $price_platform2,
			"price_platform3" => $price_platform3,
			"set_price" => $set_price,
		);
*/
$page_size = 100;
if(isset($_REQUEST["page_size"]) && intval($_REQUEST["page_size"]) > 0)
	$page_size = intval($_REQUEST["page_size"]);

$arSettings = array(
	"round" => $round,
	"rate" => 1,
	"currency" => $currency
);






$filter = $psFilter;

if ($_REQUEST["remove_duplicates"] != "Y") {
	if($price_from > 0) $filter["price_from"] = $price_from * $arSettings["rate"];
	if($price_to > 0) $filter["price_to"] = $price_to * $arSettings["rate"];
} else {
	unset($filter["supplier_id"]);
}



$arAvailSort = array(
	"article" => SORT_STRING,
	"b_price" => SORT_NUMERIC,
	"price" => SORT_NUMERIC,
	"price_platform" => SORT_NUMERIC,
	"price_platform_av" => SORT_NUMERIC,
	"revenue" => SORT_NUMERIC,
	"revenue_p" => SORT_NUMERIC,
	"supplier_name" => SORT_STRING,
);

if($_REQUEST["order"] != "undefined" && $_REQUEST["sort"] != "undefined"){
	$order = $_REQUEST["order"];
	$sort = $_REQUEST["sort"];
}else{
	$order = "asc";
	$sort = "revenue_p";
}

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

	echo json_encode($arPrice, JSON_UNESCAPED_UNICODE);
	die;
}

$cntAll = (is_array($arPrice) ? count($arPrice) : 0);
$arPrice = array_slice($arPrice, 0, $page_size);

?>
<?

$sum = $arResult["cnt_1"] + $arResult["cnt_2"] + $arResult["cnt_3"] + $arResult["cnt_4"];
if($sum > 0){
	$arResult["per_1"] = round($arResult["cnt_1"] / $sum * 100, 2);
	$arResult["per_2"] = round($arResult["cnt_2"] / $sum * 100, 2);
	$arResult["per_3"] = round($arResult["cnt_3"] / $sum * 100, 2);
	$arResult["per_4"] = round($arResult["cnt_4"] / $sum * 100, 2);
}
?>
<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_1"):?>active<?endif?>" data-type="cnt_1">Предложений с минимальной ценой - <span style="color: rgb(42, 173, 46);" ><?=$arResult["cnt_1"]?> - <?=$arResult["per_1"]?>%</span></p>
<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_2"):?>active<?endif?>" data-type="cnt_2">Предложений с ценой TOP3 - <span ><?=$arResult["cnt_2"]?> - <?=$arResult["per_2"]?>%</span></p>
<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_3"):?>active<?endif?>" data-type="cnt_3">Предложений с ценой выше TOP3 - <span style="color: rgb(255, 0, 0)" ><?=$arResult["cnt_3"]?> - <?=$arResult["per_3"]?>%</span></p>
<p class="filter-price <?if($_REQUEST["filter_price"] == "cnt_4"):?>active<?endif?>" data-type="cnt_4">Предложений без конкурентов - <span ><?=$arResult["cnt_4"]?> - <?=$arResult["per_4"]?>%</span></p>

<?if(is_array($arPrice) && count($arPrice) > 0):?>
	<p><a href="#" id="download_xls">Скачать xls</a></p>
<?endif?>
<p>Показаны <?=(is_array($arPrice) ? count($arPrice) : 0)?> из <?=$cntAll?></p>
<?
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
			<button type="button" class="btn btn-primary" id="all-set-price_rrc">РРЦ</button>
			<?/*<button type="button" class="btn btn-primary" id="all-get-price-platform" style="display: none;">КЦ</button>*/?>
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
		<tr class="tr-priceid-<?=$arItem["id"]?> <?if(!$arItem["b_price"] || $arItem["b_price"] == 0):?>no-price<?endif?> <?=$class?>" 
			data-productid="<?=$arItem["b_id"]?>" data-bprice="<?=$arItem["b_price"]?>" 
			id="sdftr-priceid-<?=$arItem["id"]?>"
		>
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
			<td <?if($sort == "supplier_name"):?>class="active"<?endif?>><?=$arItem["supplier_name"]?></td>
			<td>
				<?if($arItem["price"] > 0):?>
				<button type="button" class="btn btn-primary btn-set-price_rrc" data-price_rrc="<?=$arItem["price_rrc"]?>">РРЦ</button>
				<input type="hidden" name="id[<?=$arItem["id"]?>]" value="<?=$arItem["id"]?>">
				<?endif?>
				<?/*if($arItem["price_platform"] > 0):?>
				<button type="button" class="btn btn-primary btn-get-price-platform c-competitors" data-id="<?=$arItem["b_id"]?>" data-priceid="<?=$arItem["id"]?>">КЦ</button>
				<?endif*/?>
			</td>
		</tr>
	<?endforeach?>
	</tbody>
</table>
<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
